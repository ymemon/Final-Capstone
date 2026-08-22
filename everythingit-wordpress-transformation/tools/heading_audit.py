#!/usr/bin/env python3
"""
eit_headings.py — Set H1/H2 heading structure on classic WordPress pages
WITHOUT destroying the existing body content.

Unlike a naive rewrite, this:
  - authenticates with the correct WORDPRESS credentials (admin + app password)
  - reads each page's current content first
  - ensures exactly one H1 (adds or fixes the top heading)
  - backs up every page to Downloads/seo_backups/headings/ before writing
  - runs in --dry-run by default; you pass --apply to actually write

  python eit_headings.py                 # dry run: shows what would change
  python eit_headings.py --apply         # writes changes (backup first)
  python eit_headings.py --restore 989819

Only pages listed in TARGETS are touched. Edit that dict to control scope.
"""
import argparse, json, re, sys
from datetime import datetime
from html import unescape
from pathlib import Path
import requests
from requests.auth import HTTPBasicAuth

SITE = "https://everythingit.ie"
WP_USER = "admin"                                  # WordPress admin user
WP_PASS = "doUO YZ9J NSkg PZoF 8ARK p2hR"          # WP application password
BACKUP_DIR = Path.home() / "Downloads" / "seo_backups" / "headings"
TIMEOUT = 30

# slug -> desired H1. Only the H1 is enforced here (that's the audit gap:
# pages with 0 H1). H2s already exist in the styled rewrites; we don't
# fabricate section headings over real content.
TARGETS = {
    "asset-management":            "IT Asset Management Services for Irish Businesses",
    "business-broadband":          "Business Broadband for Irish Companies",
    "hosted-data-centre-services": "Hosted Data Centre Services in Ireland",
    "inventory-rotation-warehousing-services": "Inventory Rotation & Warehousing Services",
    "it-change-management":        "IT Change Management for Irish Businesses",
    "managed-print-services":      "Managed Print Services for Irish Businesses",
    "network-security-monitoring": "Network Security Monitoring",
    "software-license-management": "Software Licence Management",
    "strategic-procurement":       "Strategic IT Procurement",
    "third-party-services":        "Third-Party IT Services",
    "virtualization-services":     "Virtualisation Services",
    "voip-cloud-pbx-technology":   "VoIP & Cloud PBX Phone Systems",
    "on-demand-structured-it-procurement": "On-Demand & Structured IT Procurement",
    "audio-visual":                "Audio Visual Services for Irish Businesses",
    "cloud-infrastructure-deployment": "Cloud Infrastructure Deployment",
    "company-relocation":          "IT Relocation Services for Irish Businesses",
    "disaster-recovery-planning-testing": "Disaster Recovery Planning & Testing",
    "modern-cloud-working":        "Modern Cloud Working",
    "secure-scalable-network-design": "Secure & Scalable Network Design",
    "software-acquisition":        "Software Acquisition for Irish Businesses",
    "supply-chain-logistics-amp-quality-control": "Supply Chain Logistics & Quality Control",
}

def wp(): return HTTPBasicAuth(WP_USER, WP_PASS)
def die(m): print(f"\nFATAL: {m}"); sys.exit(1)
def plain(h): return unescape(re.sub(r"<[^>]+>"," ",h or "")).strip()

def fetch_targets():
    pages, page = [], 1
    while True:
        r = requests.get(f"{SITE}/wp-json/wp/v2/pages",
            params={"per_page":100,"page":page,"status":"publish","context":"edit",
                    "_fields":"id,slug,content"}, auth=wp(), timeout=TIMEOUT)
        if r.status_code == 401: die("WordPress auth failed (401). Check admin app password.")
        if r.status_code == 400: break
        r.raise_for_status()
        b = r.json()
        if not b: break
        pages += b; page += 1
    return {p["slug"]: p for p in pages if p["slug"] in TARGETS}

def set_h1(raw, h1):
    """Ensure the content has exactly one H1 as its first heading."""
    existing = re.findall(r"<h1[^>]*>.*?</h1>", raw, re.I|re.S)
    if len(existing) == 1:
        # already has one H1 — leave content alone, report unchanged
        return raw, "already has 1 H1"
    if len(existing) == 0:
        # prepend an H1
        return f"<h1>{h1}</h1>\n{raw}", "added H1"
    # multiple H1s: demote all but the first to H2
    first = True
    def repl(m):
        nonlocal first
        if first:
            first = False
            return m.group(0)
        return "<h2>" + m.group(0)[m.group(0).find(">")+1:-5] + "</h2>"
    new = re.sub(r"<h1[^>]*>.*?</h1>", repl, raw, flags=re.I|re.S)
    return new, f"demoted {len(existing)-1} extra H1(s) to H2"

def backup(pid, slug, raw):
    BACKUP_DIR.mkdir(parents=True, exist_ok=True)
    p = BACKUP_DIR / f"{pid}_{slug}_{datetime.now():%Y%m%d_%H%M%S}.html"
    p.write_text(raw, encoding="utf-8"); return p

def run(apply):
    r = requests.get(f"{SITE}/wp-json/wp/v2/users/me", auth=wp(), timeout=TIMEOUT)
    if r.status_code != 200: die(f"WordPress auth failed ({r.status_code}).")
    print(f"Auth OK as '{WP_USER}'. Mode: {'APPLY' if apply else 'DRY RUN'}\n" + "="*60)
    found = fetch_targets()
    changed = skipped = 0
    for slug, h1 in TARGETS.items():
        if slug not in found:
            print(f"  --  /{slug}/  not found (published) — skipped")
            continue
        p = found[slug]; raw = (p.get("content",{}) or {}).get("raw","")
        new, note = set_h1(raw, h1)
        if new == raw:
            print(f"  ok  /{slug}/  {note}"); skipped += 1; continue
        print(f"  ->  /{slug}/  {note}")
        if apply:
            backup(p["id"], slug, raw)
            resp = requests.post(f"{SITE}/wp-json/wp/v2/pages/{p['id']}",
                json={"content": new}, auth=wp(), timeout=TIMEOUT)
            resp.raise_for_status()
        changed += 1
    print("="*60)
    print(f"{changed} to change, {skipped} already fine." +
          ("" if apply else "  Re-run with --apply to write."))

def restore(pid):
    baks = sorted(BACKUP_DIR.glob(f"{pid}_*.html"), reverse=True)
    if not baks: die(f"No backup for {pid}")
    r = requests.post(f"{SITE}/wp-json/wp/v2/pages/{pid}",
        json={"content": baks[0].read_text(encoding='utf-8')}, auth=wp(), timeout=TIMEOUT)
    r.raise_for_status()
    print(f"Restored {pid} from {baks[0].name}")

def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--apply", action="store_true")
    ap.add_argument("--restore", type=int)
    a = ap.parse_args()
    if a.restore: restore(a.restore)
    else: run(a.apply)

if __name__ == "__main__":
    main()
