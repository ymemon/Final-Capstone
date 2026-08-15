#!/usr/bin/env python3
"""
Match Search Console demand against the pages that exist, and say what to do.

The site has ~850 queries drawing impressions and almost no clicks. That is not
a discovery problem — Google is already showing these pages — it is a position
problem, and position is won by having a page that actually answers the query.
So the useful question is not "what should we write about", it is "which of the
queries we are already visible for have no real page behind them".

Two inputs:

  1. Search Console export. Performance -> Queries -> Export -> CSV.
     Needs the columns: Top queries, Clicks, Impressions. Position if present.

  2. A page inventory from the live site:

     wp --path=/html post list --post_type=page --post_status=any \\
        --fields=ID,post_name,post_title,post_status --format=csv > pages.csv

Then:

     python3 azw-query-gap.py gsc-queries.csv pages.csv

Output is ordered by impressions, because impressions are demand you are
already being shown for and therefore the cheapest to convert.

Verdicts:
  NO PAGE    nothing matches — the gap is real, write the page
  THIN       a page matches but has almost nothing on it — fill it
  NOINDEXED  a page matches and is deliberately hidden — reconsider, this
             query has demand
  SPLIT      several pages match — they compete, pick one and consolidate
  OK         a substantial page matches — this is a ranking problem, not a
             content gap; do not write another page for it
"""

import csv
import re
import sys
from collections import defaultdict
from pathlib import Path

# Words that carry no matching signal. "arizona"/"az" are deliberately kept:
# a state qualifier genuinely distinguishes a page here.
STOP = {
    "a", "an", "and", "for", "in", "of", "on", "the", "to", "with", "your",
    "best", "top", "affordable", "cheap", "effective", "good", "great",
    "company", "companies", "agency", "agencies", "firm", "service", "services",
    "near", "me", "cost", "price", "pricing", "plans", "plan",
}

SYNONYM = {
    "az": "arizona",
    "seo": "seo",
    "optimisation": "optimization",
    "developer": "development",
    "developers": "development",
    "designer": "design",
    "designers": "design",
    "website": "web",
    "websites": "web",
    "sites": "web",
    "site": "web",
}

THIN_WORDS = 150

# A page carrying a city the query did not ask for is not that query's page.
# Without this, "arizona web design" matches web-design-gilbert-az on token
# overlap alone and a statewide gap disappears behind a city page that can
# never rank for it.
CITIES = {
    "gilbert", "phoenix", "mesa", "chandler", "scottsdale", "tempe",
    "glendale", "peoria", "surprise", "avondale", "goodyear", "buckeye",
    "queen", "creek", "maricopa", "casa", "grande", "flagstaff", "tucson",
    "yuma", "prescott", "sedona", "apache", "junction",
}


def tokens(text):
    words = re.findall(r"[a-z0-9]+", text.lower())
    out = set()
    for w in words:
        w = SYNONYM.get(w, w)
        if w not in STOP and len(w) > 1:
            out.add(w)
    return out


def read_gsc(path):
    rows = []
    with open(path, newline="", encoding="utf-8-sig") as fh:
        for row in csv.DictReader(fh):
            key = {k.lower().strip(): k for k in row}
            q = row[key.get("top queries") or key.get("query")].strip()
            imp = row.get(key.get("impressions", ""), "0")
            clicks = row.get(key.get("clicks", ""), "0")
            pos = row.get(key.get("position", ""), "")
            rows.append({
                "query": q,
                "impressions": int(float(imp.replace(",", "") or 0)),
                "clicks": int(float(clicks.replace(",", "") or 0)),
                "position": float(pos.replace(",", "")) if pos.strip() else None,
                "tokens": tokens(q),
            })
    return rows


def read_pages(path):
    pages = []
    with open(path, newline="", encoding="utf-8-sig") as fh:
        for row in csv.DictReader(fh):
            slug = (row.get("post_name") or "").strip()
            if not slug:
                continue
            pages.append({
                "id": row.get("ID", "").strip(),
                "slug": slug,
                "title": (row.get("post_title") or "").strip(),
                "status": (row.get("post_status") or "").strip(),
                "words": int(row["words"]) if (row.get("words") or "").strip().isdigit() else None,
                "noindex": (row.get("noindex") or "").strip().lower() in ("1", "yes", "true"),
                "tokens": tokens(slug.replace("-", " ") + " " + (row.get("post_title") or "")),
            })
    return pages


def score(q_tokens, p_tokens):
    """Share of the query covered by the page.

    Coverage rather than similarity, so a longer page title is not penalised for
    saying more than the query did — except for cities, which are disqualifying.
    A Gilbert page cannot serve a statewide query no matter how well the rest of
    the tokens line up, and treating it as a match buries the real gap.
    """
    if not q_tokens:
        return 0.0
    if (p_tokens & CITIES) - q_tokens:
        return 0.0
    return len(q_tokens & p_tokens) / len(q_tokens)


def verdict(page, matches):
    if page is None:
        return "NO PAGE"
    if page["status"] != "publish":
        return "NOINDEXED"
    if page["noindex"]:
        return "NOINDEXED"
    if page["words"] is not None and page["words"] < THIN_WORDS:
        return "THIN"
    if len(matches) > 1:
        return "SPLIT"
    return "OK"


def main(argv):
    if len(argv) < 3:
        print(__doc__)
        return 1

    queries = read_gsc(argv[1])
    pages = read_pages(argv[2])
    if not pages:
        print("No pages read — check the inventory CSV.", file=sys.stderr)
        return 1

    queries.sort(key=lambda r: -r["impressions"])

    grouped = defaultdict(lambda: {"impressions": 0, "clicks": 0, "queries": []})
    rows = []

    for q in queries:
        scored = sorted(
            ((score(q["tokens"], p["tokens"]), p) for p in pages),
            key=lambda t: (-t[0], t[1]["slug"]),
        )
        best_score = scored[0][0] if scored else 0.0
        strong = [p for s, p in scored if s >= 0.75 and s == best_score]
        page = strong[0] if best_score >= 0.75 else None
        v = verdict(page, strong)

        rows.append((q, page, v, best_score))
        key = (v, page["slug"] if page else "—")
        grouped[key]["impressions"] += q["impressions"]
        grouped[key]["clicks"] += q["clicks"]
        grouped[key]["queries"].append(q)

    total_imp = sum(q["impressions"] for q in queries)
    total_clk = sum(q["clicks"] for q in queries)
    print(f"{len(queries)} queries | {total_imp:,} impressions | {total_clk} clicks "
          f"| {total_clk / total_imp * 100:.2f}% CTR\n" if total_imp else "")

    order = {"NO PAGE": 0, "THIN": 1, "NOINDEXED": 2, "SPLIT": 3, "OK": 4}
    ranked = sorted(
        grouped.items(),
        key=lambda kv: (order.get(kv[0][0], 9), -kv[1]["impressions"]),
    )

    for (v, slug), agg in ranked:
        if agg["impressions"] < 20:
            continue
        head = f"[{v}] {slug}"
        print(f"{head}")
        print(f"    {agg['impressions']:,} impressions, {agg['clicks']} clicks, "
              f"{len(agg['queries'])} quer{'y' if len(agg['queries']) == 1 else 'ies'}")
        for q in sorted(agg["queries"], key=lambda r: -r["impressions"])[:6]:
            pos = f"  pos {q['position']:.0f}" if q["position"] else ""
            print(f"      {q['impressions']:>5,}  {q['query']}{pos}")
        print()

    print("Priorities, in order:")
    print("  NO PAGE   — demand you are visible for with nothing behind it. Write these first.")
    print("  THIN      — the page exists and is nearly empty. Cheapest wins on the list.")
    print("  NOINDEXED — hidden despite real demand. Fill and unhide, or accept losing it.")
    print("  SPLIT     — pages competing with each other. Consolidate to one.")
    print("  OK        — a real page already targets this. Losing here is a ranking")
    print("              problem; another page makes it worse, not better.")
    return 0


if __name__ == "__main__":
    raise SystemExit(main(sys.argv))
