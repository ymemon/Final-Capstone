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

# Collapsed before tokenising, so a multi-word phrase and its abbreviation land
# on the same page instead of two competing ones.
PHRASE = {
    "search engine optimization": "seo",
    "search engine optimisation": "seo",
    "search engine marketing": "sem",
}

# Queries shaped like spreadsheet rows — "keyword,10.00,low,29,approved" — are
# keyword-tool exports that got indexed, not searches worth a page. They mean a
# page is publishing raw research output and should be found and removed.
JUNK = re.compile(r",\s*\d+\.\d{2}\s*,|,\s*(low|medium|high)\s*,|,\s*approved\b", re.I)

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


def open_csv(path):
    """Open a CSV whatever encoding it arrived in.

    PowerShell's `>` redirection writes UTF-16LE, so an inventory piped to a
    file on Windows is not the UTF-8 everything else assumes. Sniff the BOM
    rather than making the caller remember -Encoding utf8.
    """
    head = Path(path).open("rb").read(64)
    if head.startswith((b"\xff\xfe", b"\xfe\xff")):
        encoding = "utf-16"
    elif head.startswith(b"\xef\xbb\xbf"):
        encoding = "utf-8-sig"
    elif head.count(b"\x00") > len(head) // 4:
        # UTF-16 written without a BOM: ASCII text leaves a null in every other
        # byte. Guess the endianness from where the nulls land.
        encoding = "utf-16-le" if head[1:2] == b"\x00" else "utf-16-be"
    else:
        encoding = "utf-8"
    return open(path, newline="", encoding=encoding)


def tokens(text):
    text = text.lower()
    for phrase, short in PHRASE.items():
        text = text.replace(phrase, short)
    words = re.findall(r"[a-z0-9]+", text)
    out = set()
    for w in words:
        w = SYNONYM.get(w, w)
        if w not in STOP and len(w) > 1:
            out.add(w)
    return out


def pick_column(fieldnames, want):
    """Find the column holding `want`.

    Search Console names columns differently depending on the report. A plain
    export gives "Impressions"; switch on date comparison and the same column
    becomes "Last 6 months Impressions" alongside a "Previous ..." twin. Match
    on the substring and prefer the current period, so a comparison export does
    not silently read as all zeroes.
    """
    lowered = [(f, f.lower()) for f in fieldnames if f]
    hits = [f for f, low in lowered if want in low]
    if not hits:
        return None
    for f in hits:
        if "previous" not in f.lower():
            return f
    return hits[0]


def number(text):
    text = (text or "").replace(",", "").replace("%", "").strip()
    try:
        return float(text)
    except ValueError:
        return 0.0


def read_gsc(path):
    rows = []
    with open_csv(path) as fh:
        reader = csv.DictReader(fh)
        fields = reader.fieldnames or []
        c_query = pick_column(fields, "quer") or pick_column(fields, "top")
        c_imp = pick_column(fields, "impression")
        c_clk = pick_column(fields, "click")
        c_pos = pick_column(fields, "position")
        if not c_query or not c_imp:
            raise SystemExit(
                "Could not find query/impression columns in "
                f"{path}.\nColumns present: {fields}"
            )
        for row in reader:
            q = (row.get(c_query) or "").strip()
            if not q:
                continue
            rows.append({
                "query": q,
                "impressions": int(number(row.get(c_imp))),
                "clicks": int(number(row.get(c_clk))) if c_clk else 0,
                "position": number(row.get(c_pos)) if c_pos else None,
                "tokens": tokens(q),
            })
    return rows


def read_pages(path):
    pages = []
    with open_csv(path) as fh:
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


def slugify(text):
    return re.sub(r"-+", "-", re.sub(r"[^a-z0-9]+", "-", text.lower())).strip("-")


def cluster(queries, min_overlap=0.6):
    """Group unmatched queries into the pages that would serve them.

    A flat list of 600 missing queries is not a plan. Most of them are the same
    search worded differently — "arizona web design", "web design arizona",
    "az web design" all want one page, and writing three would be three doorway
    pages competing with each other.

    Greedy agglomeration, seeded in impression order so the highest-demand
    phrasing names the cluster: that phrasing is the one to build the page
    around, since it is what most people actually type.

    Membership is tested against the seed's tokens, which never change. Letting
    the cluster's token set move as members join makes it drift — intersecting
    erodes it toward one or two generic words, at which point the cluster
    becomes a magnet and swallows everything ("seo" + "arizona" absorbed 198
    unrelated queries). A fixed seed keeps each cluster about one thing.
    """
    clusters = []
    for q in sorted(queries, key=lambda r: -r["impressions"]):
        if not q["tokens"]:
            continue
        for c in clusters:
            shared = len(q["tokens"] & c["tokens"])
            # Two generic words in common is coincidence, not a shared subject.
            if shared < 2 and min(len(q["tokens"]), len(c["tokens"])) > 1:
                continue
            if shared / min(len(q["tokens"]), len(c["tokens"])) >= min_overlap:
                c["queries"].append(q)
                c["impressions"] += q["impressions"]
                c["clicks"] += q["clicks"]
                break
        else:
            clusters.append({
                "seed": q["query"],
                "tokens": set(q["tokens"]),
                "queries": [q],
                "impressions": q["impressions"],
                "clicks": q["clicks"],
            })
    return sorted(clusters, key=lambda c: -c["impressions"])


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

    # The NO PAGE bucket is the write-list, but as a flat list of hundreds of
    # queries it is unusable. Clustered, it becomes a page count.
    unmatched = [q for q, page, v, _ in rows if v == "NO PAGE"]

    junk = [q for q in unmatched if JUNK.search(q["query"])]
    if junk:
        print("=" * 68)
        print("INDEXED KEYWORD-TOOL OUTPUT — not searches, a leaked page")
        print("=" * 68)
        print()
        print(f"{len(junk)} queries, {sum(q['impressions'] for q in junk):,} impressions, "
              "shaped like spreadsheet rows rather than things people type.")
        print("Something on the site is publishing raw keyword research and Google")
        print("indexed it. Find and remove the page — these are not content gaps.\n")
        for q in sorted(junk, key=lambda r: -r["impressions"])[:5]:
            print(f"    {q['impressions']:>5,}  {q['query']}")
        print()

    unmatched = [q for q in unmatched if not JUNK.search(q["query"])]
    clusters = [c for c in cluster(unmatched) if c["impressions"] >= 50]
    if clusters:
        print("=" * 68)
        print("PAGES TO WRITE — unmatched demand, grouped into one page each")
        print("=" * 68)
        print()
        covered = sum(c["impressions"] for c in clusters)
        print(f"{len(clusters)} pages would cover {covered:,} impressions "
              f"({covered / total_imp * 100:.0f}% of all demand)\n")
        for i, c in enumerate(clusters, 1):
            # Name the page after the highest-demand phrasing, not the shared
            # tokens: alphabetised tokens give /arizona-design-web/, while the
            # seed gives /arizona-web-design/, which is what people type.
            slug = slugify(c["seed"])
            print(f"{i:>2}. {c['seed']}")
            print(f"    {c['impressions']:,} impressions across {len(c['queries'])} queries"
                  f"   suggested slug: /{slug}/")
            for q in sorted(c["queries"], key=lambda r: -r["impressions"])[:4]:
                pos = f"  pos {q['position']:.0f}" if q["position"] else ""
                print(f"       {q['impressions']:>5,}  {q['query']}{pos}")
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
