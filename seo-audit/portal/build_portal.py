"""
Builds a standalone client portal page from template.html + data/<client>.json.

    python build_portal.py everythingit
    python build_portal.py --all

Output: dist/<client>/index.html — a single self-contained file. No build step,
no framework, nothing to hydrate; deploy is a straight scp.

The data is injected at BUILD time, so the browser never holds an API key and
never calls Google directly. The only runtime call is api/live.php (GA4
realtime), which refreshes its own token server-side.

Template tokens:
    __PORTAL_DATA__    the {gsc, ga4} object from sync_portal.py
    __CLIENT_NAME__    display name
    __CLIENT_DOMAIN__  the client's own domain (labels only, never the host)
"""

import argparse
import json
from pathlib import Path

HERE = Path(__file__).resolve().parent
TEMPLATE = HERE / "template.html"
DATA_DIR = HERE / "data"
DIST = HERE / "dist"
CLIENTS_FILE = HERE / "clients.json"
ASSETS = HERE / "assets"

TOKENS = ("__PORTAL_DATA__", "__CLIENT_NAME__", "__CLIENT_DOMAIN__",
          "__EARTH_DAY__", "__EARTH_NIGHT__", "__PORTAL_INSIGHTS__",
          "__CLIENT_ROSTER__", "__PORTAL_ROLE__")


def earth_textures():
    """The globe's day/night maps, kept out of template.html so the template
    stays readable. See assets/README.md for why these are data: URIs."""
    out = {}
    for key, name in (("__EARTH_DAY__", "earth-day.b64"), ("__EARTH_NIGHT__", "earth-night.b64")):
        f = ASSETS / name
        if not f.exists():
            raise SystemExit(f"missing {f} - run scripts/make_earth_textures.py")
        out[key] = f.read_text(encoding="utf-8").strip()
    return out


def _pct(cur, prev):
    if not prev:
        return None
    return (cur - prev) / prev * 100.0


def _plural(n, word):
    return word if n == 1 else word + "s"


def build_insights(data):
    """Headline findings for the ticker, derived only from measured rows.

    Every line has to survive one test: would it still be true if the client
    checked it in Search Console themselves? That rules out the tempting ones -
    a query that "climbed 70 places" off a single impression is noise, and
    putting it on a scrolling banner as a win is how a dashboard loses its
    credibility. Hence the volume floors below.

    Returns a list of {tone, label, text} - tone drives colour only.
    """
    g, ai = data.get("gsc") or {}, data.get("ai")
    cur, prev = g.get("current") or {}, g.get("previous") or {}
    out = []

    days = 0
    try:
        from datetime import date
        s = [int(x) for x in cur["start"].split("-")]
        e = [int(x) for x in cur["end"].split("-")]
        days = (date(*e) - date(*s)).days + 1
    except Exception:
        days = 28
    window = f"last {days} days"

    # --- headline traffic movement -----------------------------------------
    dc, di = _pct(cur.get("clicks", 0), prev.get("clicks", 0)), _pct(cur.get("impressions", 0), prev.get("impressions", 0))
    if di is not None and abs(di) >= 1:
        out.append({
            "tone": "good" if di > 0 else "bad",
            "label": "Search visibility",
            "text": f"Impressions {'up' if di > 0 else 'down'} {abs(di):.1f}% to {cur['impressions']:,} "
                    f"over the {window}, versus the previous {days} days",
        })
    if dc is not None and abs(dc) >= 1:
        out.append({
            "tone": "good" if dc > 0 else "bad",
            "label": "Search clicks",
            "text": f"Clicks {'up' if dc > 0 else 'down'} {abs(dc):.1f}% to {cur['clicks']:,} over the {window}",
        })

    # --- the single best-performing query ----------------------------------
    # Guarded twice over: one stray click on a page-9 ranking is not a "top
    # keyword", and announcing it as one tells the client we are not reading
    # our own numbers.
    tq = [q for q in (g.get("topQueries") or [])
          if q.get("clicks", 0) >= 2 and q.get("position", 999) <= 30]
    if tq:
        q = tq[0]
        out.append({
            "tone": "good", "label": "Top keyword",
            "text": f"“{q['query']}” brought {q['clicks']:,} {_plural(q['clicks'], 'click')} from "
                    f"{q['impressions']:,} impressions at average position {q['position']:.1f}",
        })

    # --- keywords genuinely gaining ----------------------------------------
    # Volume floors: a rank jump on a handful of impressions is measurement
    # noise, not a result worth announcing.
    climbers = [r for r in (g.get("gainers") or [])
                if r.get("positionDelta", 0) >= 3 and r.get("impressions", 0) >= 25]
    climbers.sort(key=lambda r: r["positionDelta"], reverse=True)
    if climbers:
        r = climbers[0]
        out.append({
            "tone": "good", "label": "Biggest climb",
            "text": f"“{r['query']}” moved up {r['positionDelta']:.0f} places to position "
                    f"{r['position']:.1f} on {r['impressions']:,} impressions",
        })

    winners = [r for r in (g.get("gainers") or []) if r.get("clicksDelta", 0) >= 2]
    winners.sort(key=lambda r: r["clicksDelta"], reverse=True)
    if winners:
        r = winners[0]
        out.append({
            "tone": "good", "label": "Gaining clicks",
            "text": f"“{r['query']}” gained {r['clicksDelta']:,} {_plural(r['clicksDelta'], 'click')} "
                    f"versus the previous {days} days",
        })

    # --- nearest win: strong impressions stuck just outside the top 10 ------
    strike = [r for r in (g.get("striking") or [])
              if 10 <= r.get("position", 0) <= 20 and r.get("impressions", 0) >= 50]
    strike.sort(key=lambda r: r["impressions"], reverse=True)
    if strike:
        r = strike[0]
        out.append({
            "tone": "warn", "label": "Closest opportunity",
            "text": f"“{r['query']}” sits at position {r['position']:.1f} with {r['impressions']:,} "
                    f"impressions - the shortest route to more clicks is moving it into the top 10",
        })

    # --- best-moving landing page ------------------------------------------
    movers = [p for p in (g.get("pageMovers") or []) if p.get("clicksDelta", 0) >= 3]
    movers.sort(key=lambda p: p["clicksDelta"], reverse=True)
    if movers:
        p = movers[0]
        path = p["page"].split("//", 1)[-1]
        path = path[path.find("/"):] if "/" in path else "/"
        label = "the homepage" if path in ("/", "") else path
        # Clicks and rank can move in opposite directions. Only claim the page
        # rose in position when it actually did - otherwise report the clicks
        # alone rather than implying an improvement the numbers contradict.
        improved = p.get("positionPrev") and p.get("position") and p["position"] < p["positionPrev"]
        moved = (f" and moved from position {p['positionPrev']:.1f} to {p['position']:.1f}"
                 if improved else "")
        out.append({
            "tone": "good", "label": "Page on the rise",
            "text": f"{label} gained {p['clicksDelta']:,} {_plural(p['clicksDelta'], 'click')}{moved}",
        })

    # --- ranking footprint --------------------------------------------------
    tk, tkp = g.get("totalKeywords"), g.get("totalKeywordsPrev")
    if tk and tkp:
        d = tk - tkp
        if abs(d) >= 10:
            out.append({
                "tone": "good" if d > 0 else "bad", "label": "Ranking keywords",
                "text": f"Now ranking for {tk:,} keywords, {'up' if d > 0 else 'down'} {abs(d):,} "
                        f"on the previous period",
            })

    top3 = next((b["keywords"] for b in (g.get("distribution") or []) if b.get("bucket") == "Top 3"), None)
    if top3:
        out.append({"tone": "good", "label": "Top 3 rankings",
                    "text": f"{top3:,} keywords are ranking in the top 3 positions on Google"})

    # --- AI answer engines --------------------------------------------------
    if ai and ai.get("aiHits"):
        # byBot rows carry search crawlers too; only the AI ones belong here.
        bots = [b.get("bot") for b in (ai.get("byBot") or []) if b.get("kind") == "ai" and b.get("bot")]
        named = ", ".join(bots[:3])
        out.append({
            "tone": "good", "label": "AI visibility",
            "text": f"AI engines crawled the site {ai['aiHits']:,} times in {ai['days']} days"
                    + (f" - {named} led the way" if named else "")
                    + ". This measures crawling, not citations",
        })
        if ai.get("llmsTxtHits") == 0:
            out.append({
                "tone": "flat", "label": "llms.txt",
                "text": f"No AI engine requested /llms.txt in the last {ai['days']} days - "
                        f"worth knowing before anyone sells it as an AI-visibility fix",
            })

    return out


def assert_isolated(slug, cfg, clients, html):
    """A client build must not contain any other client's identity.

    This is the guarantee that lets a portal link be handed to a client
    directly, so it is enforced at build time rather than trusted. It is a
    substring check on purpose: crude, but it cannot be fooled by data ending
    up somewhere unexpected in the page.
    """
    own = {cfg["domain"].lower(), slug.lower()}
    hay = html.lower()
    for other_slug, other in clients.items():
        if other_slug == slug or other.get("pending"):
            continue
        # Agency-owned properties (internal: true) are exempt: azwebcorp.com is
        # the host, the branding and the support address, so it appears in the
        # chrome of every client page by design. The promise being enforced here
        # is that no OTHER CLIENT's identity is present.
        if other.get("internal"):
            continue
        # Domains and slugs only. Display names are not safe to match on: a
        # client called "Everything IT" turns every "everything it takes" in the
        # page copy into a false positive, and a check that cries wolf gets
        # switched off, which is worse than not having it.
        for token in (other["domain"].lower(), other_slug.lower()):
            # Skip anything that is a substring of this client's own identity -
            # "azwebcorp.com" legitimately appears inside "shopazwebcorp.com".
            if any(token in o or o in token for o in own):
                continue
            if token in hay:
                raise SystemExit(
                    f"ISOLATION FAILURE: the {cfg['name']} build contains "
                    f"{token!r} from {other['name']}. Refusing to ship it.")


def build(slug, cfg, clients=None, owner=False):
    """Build one client page.

    owner=False (the client build) embeds that client and nothing else: the
    account switcher has a single entry, so a client who is sent their link has
    no way to learn that any other property exists. That isolation is
    structural rather than cosmetic - the other clients' data is simply not in
    the file - and assert_isolated() below refuses to ship a page that breaks it.

    owner=True builds the same page with a switcher across every property, for
    the agency's own use behind HTTP auth.
    """
    if cfg.get("pending"):
        print(f"  {cfg['name']:<16} SKIPPED - pending access ({cfg['domain']})")
        return None

    data_file = DATA_DIR / f"{slug}.json"
    if not data_file.exists():
        raise SystemExit(f"no data for {slug!r} - run: python sync_portal.py {slug}")

    tpl = TEMPLATE.read_text(encoding="utf-8")
    missing = [t for t in TOKENS if t not in tpl]
    if missing:
        raise SystemExit(f"template.html is missing token(s): {', '.join(missing)}")

    data = json.loads(data_file.read_text(encoding="utf-8"))

    # Order matters: inject identity first, because __PORTAL_DATA__ is replaced
    # with arbitrary JSON that could itself contain a token-looking string from
    # a page title or query. Substituting data last means nothing inside it is
    # ever re-scanned for tokens.
    tex = earth_textures()
    insights = build_insights(data)

    if owner and clients:
        roster = [{"slug": s, "name": c["name"]} for s, c in clients.items() if not c.get("pending")]
    else:
        roster = [{"slug": slug, "name": cfg["name"]}]

    html = (tpl
            .replace("__CLIENT_NAME__", cfg["name"])
            .replace("__CLIENT_DOMAIN__", cfg["domain"])
            .replace("__CLIENT_ROSTER__", json.dumps(roster, separators=(",", ":")))
            .replace("__PORTAL_ROLE__", "owner" if owner else "client")
            .replace("__EARTH_DAY__", tex["__EARTH_DAY__"])
            .replace("__EARTH_NIGHT__", tex["__EARTH_NIGHT__"])
            .replace("__PORTAL_INSIGHTS__", json.dumps(insights, separators=(",", ":")))
            .replace("__PORTAL_DATA__", json.dumps(data, separators=(",", ":"))))

    for t in TOKENS:
        if t in html:
            raise SystemExit(f"token {t} survived the build - refusing to ship it")

    if not owner:
        assert_isolated(slug, cfg, clients or {}, html)

    out_dir = (DIST / "_owner" / slug) if owner else (DIST / slug)
    out_dir.mkdir(parents=True, exist_ok=True)
    out = out_dir / "index.html"
    out.write_text(html, encoding="utf-8")

    g = data["gsc"]["current"]
    ga4 = data.get("ga4")
    print(f"  {cfg['name']:<16} {out.stat().st_size // 1024:>4} KB   "
          f"{g['clicks']} clicks / {g['impressions']} impr / "
          f"{data['gsc']['totalKeywords']} keywords   "
          f"GA4 {'yes' if ga4 else 'NOT CONNECTED'}   "
          f"{len(insights)} insights")
    return out


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("client", nargs="?")
    ap.add_argument("--all", action="store_true")
    ap.add_argument("--owner", action="store_true",
                    help="also build the agency copy (every property, switcher enabled) "
                         "into dist/_owner/ - deploy behind HTTP auth, never link from a client page")
    a = ap.parse_args()

    clients = {k: v for k, v in json.loads(CLIENTS_FILE.read_text(encoding="utf-8")).items()
               if not k.startswith("_")}
    targets = list(clients) if a.all else ([a.client] if a.client else [])
    if not targets:
        raise SystemExit(f"pass a client slug ({', '.join(clients)}) or --all")

    for slug in targets:
        if slug not in clients:
            raise SystemExit(f"unknown client {slug!r}")
        build(slug, clients[slug], clients)

    if a.owner:
        print("\n  --- agency copy (all properties, switcher on) ---")
        for slug in targets:
            build(slug, clients[slug], clients, owner=True)


if __name__ == "__main__":
    main()
