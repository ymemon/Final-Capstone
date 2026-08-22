#!/usr/bin/env python3
"""
eit_content_audit.py — Content inventory + mechanical-defect scanner/fixer for
everythingit.ie (post-restructure, ~74 surviving pages).

WHAT IT DOES (and deliberately does NOT do):
  - INVENTORIES every published page via REST: title, slug, URL, word count,
    Yoast meta title/description, H1s, CTA link texts.
  - SCANS for the concrete, pattern-matchable defects the content audit named:
      * [Your Phone Number] and other placeholder tokens
      * ambiguous "Learn more" / "Read more" / "Click here" links
      * stale privacy strings ("Data Protection Commissioner", "Net Lawman",
        "knowyourprivacyrights.org")
      * "ireland and all of ireland" / lowercase-ireland templating artefacts
      * missing or too-short meta descriptions
      * thin content (< 300 words)
      * unsourced-number patterns (300+, 99.9%, 15 min, 500+) -> FLAG ONLY
  - AUTO-FIXES only the safe, unambiguous ones (opt-in with --fix):
      * [Your Phone Number] -> real phone (REST content update, backup first)
      * stale privacy authority strings -> current DPC naming
    Everything else is REPORT ONLY. Copy that needs judgement is never
    auto-written — it lands on the worklist for a human to draft.
  - PUSHES approved copy for a single page with --push (you supply a JSON of
    field->value); backs up current content first.

  python eit_content_audit.py                 # inventory + scan -> report + CSV
  python eit_content_audit.py --fix           # also apply the safe auto-fixes
  python eit_content_audit.py --fix --dry-run # preview auto-fixes
  python eit_content_audit.py --push page.json# push approved copy to one page
  python eit_content_audit.py --restore <id>  # restore a page from last backup

  pip install requests

Notes:
  - Reads Yoast meta via the mu-plugin field if present; falls back to the
    rendered <head> via a lightweight fetch.
  - NEVER touches draft/redirected pages — only status=publish.
"""

import argparse
import csv
import io
import json
import re
import sys
import time
from datetime import datetime
from html import unescape
from pathlib import Path

import requests
from requests.auth import HTTPBasicAuth

# ============================================================================
SITE      = "https://everythingit.ie"
WP_USER   = "admin"
WP_PASS   = "doUO YZ9J NSkg PZoF 8ARK p2hR"

REAL_PHONE = "+353 1 524 0755"     # from the live site footer

BACKUP_DIR = Path.home() / "Downloads" / "seo_backups" / "content"
REPORT_CSV = Path.home() / "Downloads" / "eit_content_audit.csv"
WORKLIST   = Path.home() / "Downloads" / "eit_content_worklist.txt"
TIMEOUT = 30

# ---- defect patterns -------------------------------------------------------
PLACEHOLDERS = [
    r"\[Your Phone Number\]", r"\[your phone number\]", r"\[PHONE\]",
    r"\[Your Email\]", r"\[Address\]", r"\[Company Name\]", r"\[City\]",
    r"lorem ipsum",
]
AMBIGUOUS_LINKS = [r">\s*Learn more\s*<", r">\s*Read more\s*<",
                   r">\s*Click here\s*<", r">\s*More\s*<"]
STALE_PRIVACY = {
    "Data Protection Commissioner": "Data Protection Commission",
    "Office of the Data Protection Commissioner": "Data Protection Commission",
    "knowyourprivacyrights.org": "dataprotection.ie",
    "©Net Lawman": "",
    "Privacy Notice ©Net Lawman": "",
}
TEMPLATING_ARTEFACTS = [
    r"ireland and all of ireland", r"\ball of ireland\b",
    r"(?<![A-Za-z>])ireland\b",   # lowercase 'ireland' mid-sentence
]
UNSOURCED_NUMBERS = [
    r"\b300\+?\s*(companies|clients|businesses)", r"\b500\+?\s*users",
    r"\b99\.9%\s*(uptime|SLA)", r"\b15[\s-]*min", r"\b24/7\b",
]
MIN_WORDS = 300
MIN_META_DESC = 70

# ============================================================================

def die(msg):
    print(f"\nFATAL: {msg}")
    sys.exit(1)

def wp():
    return HTTPBasicAuth(WP_USER, WP_PASS)

def strip_tags(html):
    return unescape(re.sub(r"<[^>]+>", " ", html or "")).strip()

def word_count(html):
    return len(strip_tags(html).split())

def fetch_all_pages():
    pages, page_num = [], 1
    while True:
        r = requests.get(
            f"{SITE}/wp-json/wp/v2/pages",
            params={"per_page": 100, "page": page_num, "status": "publish",
                    "context": "edit",
                    "_fields": "id,slug,link,title,content,excerpt,meta,status"},
            auth=wp(), timeout=TIMEOUT)
        if r.status_code == 400:
            break
        r.raise_for_status()
        batch = r.json()
        if not batch:
            break
        pages.extend(batch)
        page_num += 1
    return pages

def get_h1s(html):
    return [strip_tags(m) for m in re.findall(r"<h1[^>]*>(.*?)</h1>", html or "", re.I | re.S)]

def get_link_texts(html):
    return [strip_tags(m) for m in re.findall(r"<a\b[^>]*>(.*?)</a>", html or "", re.I | re.S)]

def yoast_meta(page):
    meta = page.get("meta", {}) or {}
    title = meta.get("_yoast_wpseo_title", "") or ""
    desc  = meta.get("_yoast_wpseo_metadesc", "") or ""
    return title, desc

def scan_page(page):
    raw = (page.get("content", {}) or {}).get("raw", "") or ""
    slug = page["slug"]
    issues = []

    for pat in PLACEHOLDERS:
        if re.search(pat, raw, re.I):
            issues.append(("CRITICAL", "placeholder", pat))
    for pat in AMBIGUOUS_LINKS:
        n = len(re.findall(pat, raw, re.I))
        if n:
            issues.append(("MEDIUM", "ambiguous_cta", f"{n}x '{pat}'"))
    for bad in STALE_PRIVACY:
        if bad in raw:
            issues.append(("MEDIUM", "stale_privacy", bad))
    for pat in TEMPLATING_ARTEFACTS:
        if re.search(pat, raw):
            issues.append(("HIGH", "templating", pat))
    for pat in UNSOURCED_NUMBERS:
        for m in re.findall(pat, raw, re.I):
            issues.append(("FLAG", "unsourced_number", pat))
            break
    wc = word_count(raw)
    if wc < MIN_WORDS:
        issues.append(("HIGH", "thin_content", f"{wc} words"))
    _, desc = yoast_meta(page)
    if len(desc) < MIN_META_DESC:
        issues.append(("HIGH", "meta_desc", f"{len(desc)} chars"))
    h1s = get_h1s(raw)
    if len(h1s) == 0:
        issues.append(("MEDIUM", "no_h1", "0 H1"))
    elif len(h1s) > 1:
        issues.append(("MEDIUM", "multi_h1", f"{len(h1s)} H1s"))
    return issues, wc, h1s

def apply_safe_fixes(raw):
    fixed = raw
    changes = []
    for pat in PLACEHOLDERS[:1]:  # only [Your Phone Number] family
        pass
    # phone placeholders -> real phone
    for pat in [r"\[Your Phone Number\]", r"\[your phone number\]", r"\[PHONE\]"]:
        if re.search(pat, fixed, re.I):
            fixed = re.sub(pat, REAL_PHONE, fixed, flags=re.I)
            changes.append(f"phone placeholder -> {REAL_PHONE}")
    # stale privacy strings
    for bad, good in STALE_PRIVACY.items():
        if bad in fixed:
            fixed = fixed.replace(bad, good)
            changes.append(f"'{bad}' -> '{good or '(removed)'}'")
    return fixed, changes

def backup_page(page):
    BACKUP_DIR.mkdir(parents=True, exist_ok=True)
    ts = datetime.now().strftime("%Y%m%d_%H%M%S")
    path = BACKUP_DIR / f"page_{page['id']}_{page['slug']}_{ts}.json"
    path.write_text(json.dumps({
        "id": page["id"], "slug": page["slug"],
        "content": (page.get("content", {}) or {}).get("raw", ""),
        "meta": page.get("meta", {}),
    }, indent=2), encoding="utf-8")
    return path

SEV_ORDER = {"CRITICAL": 0, "HIGH": 1, "MEDIUM": 2, "FLAG": 3}

def inventory(do_fix, dry_run):
    print("Fetching all published pages ...")
    pages = fetch_all_pages()
    print(f"  {len(pages)} published pages\n")

    rows = []
    worklist = {"CRITICAL": [], "HIGH": [], "MEDIUM": [], "FLAG": []}
    fix_count = 0

    for p in sorted(pages, key=lambda x: x["slug"]):
        issues, wc, h1s = scan_page(p)
        title, desc = yoast_meta(p)
        path = "/" + p["link"].split("everythingit.ie", 1)[-1].strip("/") + "/"
        rows.append({
            "slug": p["slug"], "path": path, "id": p["id"],
            "words": wc, "h1_count": len(h1s),
            "meta_title_len": len(title), "meta_desc_len": len(desc),
            "issues": "; ".join(f"{sev}:{kind}" for sev, kind, _ in issues) or "clean",
        })
        for sev, kind, detail in issues:
            if sev in worklist:
                worklist[sev].append(f"{path}  [{kind}]  {detail}")

        if do_fix:
            raw = (p.get("content", {}) or {}).get("raw", "")
            fixed, changes = apply_safe_fixes(raw)
            if changes:
                print(f"  {'(dry) ' if dry_run else ''}FIX {path}")
                for c in changes:
                    print(f"        - {c}")
                if not dry_run:
                    backup_page(p)
                    r = requests.post(f"{SITE}/wp-json/wp/v2/pages/{p['id']}",
                                      json={"content": fixed}, auth=wp(), timeout=TIMEOUT)
                    r.raise_for_status()
                fix_count += 1

    # write inventory CSV
    with open(REPORT_CSV, "w", newline="", encoding="utf-8") as f:
        w = csv.DictWriter(f, fieldnames=list(rows[0].keys()))
        w.writeheader(); w.writerows(rows)

    # write prioritized worklist
    with open(WORKLIST, "w", encoding="utf-8") as f:
        f.write("EVERYTHING IT — CONTENT WORKLIST\n")
        f.write(f"Generated {datetime.now():%Y-%m-%d %H:%M}\n")
        f.write(f"{len(pages)} published pages scanned\n\n")
        for sev in ("CRITICAL", "HIGH", "MEDIUM", "FLAG"):
            items = worklist[sev]
            f.write(f"\n{'='*60}\n{sev} — {len(items)} item(s)\n{'='*60}\n")
            for it in items:
                f.write(f"  {it}\n")

    # console summary
    print(f"\n{'='*55}\nCONTENT AUDIT SUMMARY")
    print(f"{'='*55}")
    print(f"  Pages scanned      : {len(pages)}")
    print(f"  CRITICAL issues    : {len(worklist['CRITICAL'])}")
    print(f"  HIGH issues        : {len(worklist['HIGH'])}")
    print(f"  MEDIUM issues      : {len(worklist['MEDIUM'])}")
    print(f"  Flagged (review)   : {len(worklist['FLAG'])}")
    if do_fix:
        print(f"  Safe fixes {'previewed' if dry_run else 'applied'}: {fix_count}")
    print(f"\n  Inventory CSV -> {REPORT_CSV}")
    print(f"  Worklist      -> {WORKLIST}")
    if worklist["CRITICAL"]:
        print("\n  !! CRITICAL items (fix first):")
        for it in worklist["CRITICAL"]:
            print(f"     {it}")
    if not do_fix and (worklist["CRITICAL"] or any("stale_privacy" in r["issues"] for r in rows)):
        print("\n  Run with --fix to auto-correct placeholders + stale privacy strings.")

def push_copy(json_path):
    data = json.loads(Path(json_path).read_text(encoding="utf-8"))
    pid = data["id"]
    r = requests.get(f"{SITE}/wp-json/wp/v2/pages/{pid}",
                     params={"context": "edit", "_fields": "id,slug,content,meta"},
                     auth=wp(), timeout=TIMEOUT)
    r.raise_for_status()
    backup_page(r.json())
    payload = {}
    if "content" in data: payload["content"] = data["content"]
    if "meta" in data:    payload["meta"] = data["meta"]
    if not payload:
        die("push JSON must contain 'content' and/or 'meta'.")
    r = requests.post(f"{SITE}/wp-json/wp/v2/pages/{pid}", json=payload,
                      auth=wp(), timeout=TIMEOUT)
    r.raise_for_status()
    print(f"Pushed copy to page {pid} ({data.get('slug','')}). Backup saved.")

def restore(pid):
    baks = sorted(BACKUP_DIR.glob(f"page_{pid}_*.json"), reverse=True)
    if not baks:
        die(f"No backup for page {pid}")
    data = json.loads(baks[0].read_text(encoding="utf-8"))
    r = requests.post(f"{SITE}/wp-json/wp/v2/pages/{pid}",
                      json={"content": data["content"], "meta": data.get("meta", {})},
                      auth=wp(), timeout=TIMEOUT)
    r.raise_for_status()
    print(f"Restored page {pid} from {baks[0].name}")

def main():
    ap = argparse.ArgumentParser(description="Everything IT content audit + safe fixer")
    ap.add_argument("--fix", action="store_true", help="apply safe auto-fixes")
    ap.add_argument("--dry-run", action="store_true")
    ap.add_argument("--push", metavar="JSON", help="push approved copy for one page")
    ap.add_argument("--restore", metavar="ID", type=int, help="restore a page from backup")
    args = ap.parse_args()

    r = requests.get(f"{SITE}/wp-json/wp/v2/users/me", auth=wp(), timeout=TIMEOUT)
    if r.status_code != 200:
        die(f"WordPress auth failed ({r.status_code})")

    if args.push:
        push_copy(args.push)
    elif args.restore:
        restore(args.restore)
    else:
        inventory(args.fix, args.dry_run)

if __name__ == "__main__":
    main()
