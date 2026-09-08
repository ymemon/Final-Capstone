"""
Regenerates the client portal's DATA blob from live Google APIs.

WHY THIS EXISTS
The original generator was written in a session scratchpad and is gone; the
portal survived only as a 237KB HTML file on the GoDaddy box, one overwrite
away from being unrecoverable. This rebuilds it from the deployed file's own
schema so any client can be regenerated at will.

    python sync_portal.py azwebcorp
    python sync_portal.py everythingit
    python sync_portal.py --all

Writes data/<client>.json. Run build_portal.py afterwards to produce the page.

HONESTY RULE (carried over from the weekly report and the portal itself):
a source that returns nothing produces an explicit null/empty section that the
page renders as "not connected" — never a zero, never a placeholder number.
A client reading a fabricated 0 cannot tell it apart from a real one.
"""

import argparse
import json
import sys
from datetime import date, timedelta
from pathlib import Path

HERE = Path(__file__).resolve().parent
SEO_AUDIT = HERE.parent
sys.path.insert(0, str(SEO_AUDIT))

from ga4_token import resolve_ga4_token  # noqa: E402 - needs the path set above

DATA_DIR = HERE / "data"
CLIENTS_FILE = HERE / "clients.json"

GSC_TOKEN = Path(r"C:\Users\yasir\.claude-tools\gsc-oauth-token.json")
# Resolved lazily, not hardcoded: ga4-oauth-token.json expired and silently
# took the GA4 half of every portal sync with it.
GSC_SCOPES = ["https://www.googleapis.com/auth/webmasters.readonly"]
GA4_SCOPES = ["https://www.googleapis.com/auth/analytics.readonly"]

# GSC data lags roughly 2 days; asking for today returns a misleading partial.
LAG_DAYS = 3
WINDOW = 28
LONG_WINDOW = 90


def creds(token_path, scopes):
    from google.oauth2.credentials import Credentials
    from google.auth.transport.requests import Request
    from google.auth.exceptions import RefreshError

    c = Credentials.from_authorized_user_file(str(token_path), scopes)
    if c.expired and c.refresh_token:
        try:
            c.refresh(Request())
            token_path.write_text(c.to_json(), encoding="utf-8")
        except RefreshError as e:
            raise SystemExit(
                f"\n{token_path.name} is dead ({e}).\n"
                f"Re-authorize by double-clicking Downloads\\FIX-GOOGLE-SEARCH-CONSOLE.bat"
                if "gsc" in token_path.name
                else f"\n{token_path.name} is dead ({e}).\n"
                     f"Re-run: python seo-audit/scripts/ga4_oauth_setup.py"
            )
    return c


# ----------------------------------------------------------------- GSC

def gsc_service():
    from googleapiclient.discovery import build
    return build("searchconsole", "v1", credentials=creds(GSC_TOKEN, GSC_SCOPES),
                 cache_discovery=False)


def gsc_query(svc, site, start, end, dimensions=None, row_limit=25000,
              dimension_filters=None):
    body = {
        "startDate": str(start), "endDate": str(end),
        "dimensions": dimensions or [], "rowLimit": row_limit,
    }
    if dimension_filters:
        body["dimensionFilterGroups"] = [{"filters": dimension_filters}]
    resp = svc.searchanalytics().query(siteUrl=site, body=body).execute()
    return resp.get("rows", [])


def agg(rows):
    """Totals across rows. GSC's own position is impression-weighted, so a plain
    mean here would disagree with the Search Console UI."""
    clicks = sum(r["clicks"] for r in rows)
    impr = sum(r["impressions"] for r in rows)
    pos = (sum(r["position"] * r["impressions"] for r in rows) / impr) if impr else 0
    return {
        "clicks": clicks, "impressions": impr,
        "ctr": (clicks / impr) if impr else 0, "position": pos,
    }


def keyed(rows, name):
    out = []
    for r in rows:
        out.append({
            name: r["keys"][0], "clicks": r["clicks"], "impressions": r["impressions"],
            "ctr": r["ctr"], "position": r["position"],
        })
    return out


def build_gsc(site):
    svc = gsc_service()
    end = date.today() - timedelta(days=LAG_DAYS)
    cur_start = end - timedelta(days=WINDOW - 1)
    prev_end = cur_start - timedelta(days=1)
    prev_start = prev_end - timedelta(days=WINDOW - 1)
    long_start = end - timedelta(days=LONG_WINDOW - 1)

    cur_rows = gsc_query(svc, site, cur_start, end, ["query"])
    prev_rows = gsc_query(svc, site, prev_start, prev_end, ["query"])
    daily_rows = gsc_query(svc, site, long_start, end, ["date"])

    # Headline totals come from the DATE dimension, never from summing query
    # rows. Search Console anonymizes rare queries and omits them entirely from
    # the query dimension - on everythingit.ie that hid 57% of clicks (185 real
    # vs 80 visible over 28 days). Summing query rows would under-report a
    # client's own traffic by more than half, in their own report.
    def window_agg(a, b):
        rows = [r for r in daily_rows if a <= date.fromisoformat(r["keys"][0]) <= b]
        return agg(rows)

    cur, prev = window_agg(cur_start, end), window_agg(prev_start, prev_end)
    cur_named, prev_named = agg(cur_rows), agg(prev_rows)
    prev_by_q = {r["keys"][0]: r for r in prev_rows}

    queries = sorted(cur_rows, key=lambda r: -r["clicks"])
    pages = sorted(gsc_query(svc, site, cur_start, end, ["page"]),
                   key=lambda r: -r["clicks"])
    qp = gsc_query(svc, site, cur_start, end, ["query", "page"], row_limit=200)

    # Ranking distribution across every keyword with an impression.
    buckets = [("Top 3", 1, 3), ("4-10", 4, 10), ("11-20", 11, 20),
               ("21-50", 21, 50), ("51+", 51, 10 ** 6)]
    dist = [{"bucket": b, "keywords": sum(1 for r in cur_rows if lo <= r["position"] <= hi)}
            for b, lo, hi in buckets]

    # Striking distance: real volume, just off page one. The actionable list.
    striking = sorted(
        [r for r in cur_rows if 11 <= r["position"] <= 20 and r["impressions"] >= 10],
        key=lambda r: -r["impressions"])[:15]

    movers = []
    for r in cur_rows:
        p = prev_by_q.get(r["keys"][0])
        if not p or p["impressions"] < 5:
            continue
        movers.append({
            "query": r["keys"][0], "clicks": r["clicks"], "clicksPrev": p["clicks"],
            "clicksDelta": r["clicks"] - p["clicks"],
            "impressions": r["impressions"], "impressionsPrev": p["impressions"],
            "position": round(r["position"], 1), "positionPrev": round(p["position"], 1),
            "positionDelta": round(p["position"] - r["position"], 1),
        })
    gainers = sorted(movers, key=lambda m: -m["positionDelta"])[:12]
    losers = sorted(movers, key=lambda m: m["positionDelta"])[:12]

    # CTR vs position: where clicks are being left on the table.
    bands = [("1-3", 1, 3), ("4-10", 4, 10), ("11-20", 11, 20), ("21+", 21, 10 ** 6)]
    ctr_bands = []
    for label, lo, hi in bands:
        sel = [r for r in cur_rows if lo <= r["position"] <= hi]
        a = agg(sel)
        ctr_bands.append({"band": label, "keywords": len(sel),
                          "clicks": a["clicks"], "impressions": a["impressions"],
                          "ctr": a["ctr"]})

    page_movers = []
    prev_pages = {r["keys"][0]: r for r in gsc_query(svc, site, prev_start, prev_end, ["page"])}
    for r in pages[:40]:
        p = prev_pages.get(r["keys"][0])
        if not p:
            continue
        page_movers.append({
            "page": r["keys"][0], "clicks": r["clicks"], "clicksPrev": p["clicks"],
            "clicksDelta": r["clicks"] - p["clicks"],
            "impressions": r["impressions"], "impressionsPrev": p["impressions"],
            "position": round(r["position"], 1), "positionPrev": round(p["position"], 1),
        })
    page_movers.sort(key=lambda m: -abs(m["clicksDelta"]))

    from datetime import datetime, timezone
    return {
        "siteUrl": site,
        "syncedAt": datetime.now(timezone.utc).isoformat(),
        "range90d": {"start": str(long_start), "end": str(end)},
        "current": {"start": str(cur_start), "end": str(end), **cur},
        "previous": {"start": str(prev_start), "end": str(prev_end), **prev},
        # What the query tables can actually account for. The gap between this
        # and `current` is Search Console's anonymized long tail - surfaced so
        # the page can explain the discrepancy instead of looking wrong.
        "namedQueryTotals": {**cur_named, "queries": len(cur_rows)},
        "anonymizedClicks": max(0, cur["clicks"] - cur_named["clicks"]),
        "daily": [{"date": r["keys"][0], "clicks": r["clicks"],
                   "impressions": r["impressions"], "ctr": r["ctr"],
                   "position": r["position"]} for r in daily_rows],
        "topQueries": keyed(queries[:25], "query"),
        "topPages": keyed(pages[:25], "page"),
        "queryPage": [{"query": r["keys"][0], "page": r["keys"][1], "clicks": r["clicks"],
                       "impressions": r["impressions"], "ctr": r["ctr"],
                       "position": r["position"]}
                      for r in sorted(qp, key=lambda r: -r["impressions"])[:60]],
        "devices": keyed(gsc_query(svc, site, cur_start, end, ["device"]), "device"),
        "countries": keyed(sorted(gsc_query(svc, site, cur_start, end, ["country"]),
                                  key=lambda r: -r["impressions"])[:15], "country"),
        "distribution": dist,
        "striking": keyed(striking, "query"),
        "gainers": gainers,
        "losers": losers,
        "ctrBands": ctr_bands,
        "pageMovers": page_movers[:15],
        "indexation": None,          # no API for this; page shows "not connected"
        "totalKeywords": len(cur_rows),
        "totalKeywordsPrev": len(prev_rows),
    }


# ----------------------------------------------------------------- GA4

def build_ga4(property_id):
    """Returns None when no property is configured - the page then renders an
    explicit 'not connected' panel rather than a wall of zeros."""
    if not property_id:
        return None
    from googleapiclient.discovery import build as gbuild
    from datetime import datetime, timezone

    svc = gbuild("analyticsdata", "v1beta", credentials=creds(resolve_ga4_token(GA4_SCOPES), GA4_SCOPES),
                 cache_discovery=False)
    end = date.today() - timedelta(days=1)
    start = end - timedelta(days=WINDOW - 1)
    prop = f"properties/{property_id}"

    def run(dimensions, metrics, limit=25, order_metric=None):
        body = {
            "dateRanges": [{"startDate": str(start), "endDate": str(end)}],
            "dimensions": [{"name": d} for d in dimensions],
            "metrics": [{"name": m} for m in metrics],
            "limit": limit,
        }
        if order_metric:
            body["orderBys"] = [{"metric": {"metricName": order_metric}, "desc": True}]
        resp = svc.properties().runReport(property=prop, body=body).execute()
        rows = []
        for r in resp.get("rows", []):
            d = [x["value"] for x in r.get("dimensionValues", [])]
            m = [float(x["value"]) for x in r.get("metricValues", [])]
            rows.append((d, m))
        return rows

    tot = run([], ["sessions", "totalUsers", "newUsers", "screenPageViews",
                   "engagementRate", "averageSessionDuration", "eventCount"])
    totals = {}
    if tot:
        keys = ["sessions", "totalUsers", "newUsers", "screenPageViews",
                "engagementRate", "averageSessionDuration", "eventCount"]
        totals = dict(zip(keys, tot[0][1]))

    daily = [{"date": d[0], "sessions": m[0], "users": m[1], "newUsers": m[2],
              "pageviews": m[3], "engagementRate": m[4], "avgDuration": m[5]}
             for d, m in run(["date"], ["sessions", "totalUsers", "newUsers",
                                        "screenPageViews", "engagementRate",
                                        "averageSessionDuration"], limit=400)]
    daily.sort(key=lambda x: x["date"])

    return {
        "property": prop,
        "syncedAt": datetime.now(timezone.utc).isoformat(),
        "current": {"start": str(start), "end": str(end)},
        "totals": totals,
        "daily": daily,
        "channels": [{"channel": d[0], "sessions": m[0], "users": m[1],
                      "engagementRate": m[2]}
                     for d, m in run(["sessionDefaultChannelGroup"],
                                     ["sessions", "totalUsers", "engagementRate"],
                                     order_metric="sessions")],
        "sources": [{"source": d[0], "sessions": m[0], "users": m[1]}
                    for d, m in run(["sessionSourceMedium"],
                                    ["sessions", "totalUsers"], order_metric="sessions")],
        "topPages": [{"page": d[0], "pageviews": m[0], "sessions": m[1],
                      "avgDuration": m[2]}
                     for d, m in run(["pagePath"],
                                     ["screenPageViews", "sessions",
                                      "averageSessionDuration"],
                                     order_metric="screenPageViews")],
        "countries": [{"country": d[0], "sessions": m[0], "users": m[1]}
                      for d, m in run(["country"], ["sessions", "totalUsers"],
                                      order_metric="sessions")],
        "cities": [{"city": d[0], "sessions": m[0], "users": m[1]}
                   for d, m in run(["city"], ["sessions", "totalUsers"],
                                   order_metric="sessions")],
        "devices": [{"device": d[0], "sessions": m[0], "users": m[1]}
                    for d, m in run(["deviceCategory"], ["sessions", "totalUsers"],
                                    order_metric="sessions")],
        "browsers": [{"browser": d[0], "sessions": m[0]}
                     for d, m in run(["browser"], ["sessions"], order_metric="sessions")],
        "events": [{"event": d[0], "count": m[0]}
                   for d, m in run(["eventName"], ["eventCount"],
                                   order_metric="eventCount")],
    }


# ----------------------------------------------------------------- AI crawlers

# Bots that actually feed answer engines, versus ordinary search crawlers.
# Keeping them apart matters: folding Bingbot's volume into an "AI visibility"
# number would inflate it enormously and tell the client something untrue.
AI_BOTS = {
    "GPTBot": "OpenAI", "ChatGPT-User": "OpenAI", "OAI-SearchBot": "OpenAI",
    "ClaudeBot": "Anthropic", "Claude-Web": "Anthropic", "anthropic-ai": "Anthropic",
    "Claude-User": "Anthropic", "Claude-SearchBot": "Anthropic",
    "PerplexityBot": "Perplexity", "Perplexity-User": "Perplexity",
    "Google-Extended": "Google (Gemini)", "Applebot-Extended": "Apple",
    "meta-externalagent": "Meta AI", "Bytespider": "ByteDance",
    "CCBot": "Common Crawl", "Amazonbot": "Amazon", "cohere-ai": "Cohere",
    "YouBot": "You.com", "DuckAssistBot": "DuckDuckGo",
}
SEARCH_BOTS = {"Googlebot", "Bingbot", "bingbot", "Yandex", "DuckDuckBot",
               "Slurp", "Baiduspider"}

AI_DAYS = 30


def build_ai_crawlers(cfg):
    """Reads the site's own ai-crawler-logger JSONL over SSH. Returns None when
    the client has no logger - the page then says so instead of showing zeros."""
    helper, log_dir = cfg.get("ssh_helper"), cfg.get("ai_log_dir")
    if not helper or not log_dir:
        return None

    import subprocess
    from collections import Counter, defaultdict
    from datetime import datetime, timezone

    bat = Path(r"C:\Users\yasir\.claude-tools") / helper
    if not bat.exists():
        print(f"       AI crawlers: helper {helper} not found")
        return None

    # Concatenate the most recent files. Names are ISO dates, so a plain sort is
    # chronological; tail -N is the window without needing date arithmetic.
    cmd = f'for f in $(ls -1 {log_dir}/*.jsonl 2>/dev/null | tail -{AI_DAYS}); do cat "$f"; done'
    try:
        r = subprocess.run([str(bat), cmd], capture_output=True, text=True, timeout=180)
    except Exception as e:
        print(f"       AI crawlers FAILED: {e}")
        return None

    lines = [l for l in (r.stdout or "").splitlines() if l.strip().startswith("{")]
    if not lines:
        print("       AI crawlers: no log rows found")
        return None

    by_bot, by_day, by_uri = Counter(), Counter(), Counter()
    ai_hits = search_hits = other_hits = llms_hits = 0
    days_seen = set()

    for line in lines:
        try:
            row = json.loads(line)
        except Exception:
            continue
        bot = row.get("bot") or "(unnamed)"
        day = (row.get("time") or "")[:10]
        if day:
            days_seen.add(day)
        by_bot[bot] += 1
        if row.get("is_llms_txt"):
            llms_hits += 1
        if bot in AI_BOTS:
            ai_hits += 1
            by_day[day] += 1
            uri = row.get("uri") or "/"
            by_uri[uri] += 1
        elif bot in SEARCH_BOTS:
            search_hits += 1
        else:
            other_hits += 1

    return {
        "syncedAt": datetime.now(timezone.utc).isoformat(),
        "days": len(days_seen),
        "source": "ai-crawler-logger.php server logs",
        # Stated on the page verbatim. This measures crawling, not citations -
        # and the difference is the whole ballgame when a client reads it.
        "caveat": ("Measures how often AI engines crawl the site. It is a leading "
                   "indicator of AI visibility, not a count of citations in "
                   "AI-generated answers, which needs a paid data source."),
        "aiHits": ai_hits,
        "searchHits": search_hits,
        "otherHits": other_hits,
        "llmsTxtHits": llms_hits,
        "byBot": [{"bot": b, "vendor": AI_BOTS.get(b, ""), "hits": n,
                   "kind": "ai" if b in AI_BOTS else ("search" if b in SEARCH_BOTS else "other")}
                  for b, n in by_bot.most_common(20)],
        "daily": [{"date": d, "hits": n} for d, n in sorted(by_day.items()) if d],
        "topPages": [{"uri": u, "hits": n} for u, n in by_uri.most_common(15)],
    }


# ----------------------------------------------------------------- main

def sync(slug, cfg):
    print(f"\n=== {cfg['name']} ({slug}) ===")
    # A client can be registered in the portal before its data sources exist -
    # useful for holding the slug and config while access is being granted.
    # Syncing one would just 403; skip it and say why.
    if cfg.get("pending"):
        print(f"  SKIPPED - pending: {cfg['pending']}")
        return None
    print(f"  GSC  {cfg['gsc_property']}")
    gsc = build_gsc(cfg["gsc_property"])
    print(f"       {gsc['current']['clicks']} clicks, {gsc['current']['impressions']} impr, "
          f"{gsc['totalKeywords']} keywords")

    ga4 = None
    if cfg.get("ga4_property"):
        print(f"  GA4  properties/{cfg['ga4_property']}")
        # BaseException, not Exception: a dead token raises SystemExit from
        # creds(), and letting that escape aborts the whole run - which would
        # mean an expired GA4 login also blocks the Search Console refresh that
        # has nothing to do with it. GA4 failing degrades to "not connected".
        try:
            ga4 = build_ga4(cfg["ga4_property"])
            print(f"       {int(ga4['totals'].get('sessions', 0))} sessions, "
                  f"{len(ga4['daily'])} days")
        except (Exception, SystemExit) as e:
            print(f"       GA4 FAILED: {e}")
            print("       -> continuing; this property's page will say "
                  "'not connected' rather than showing zeros")
            ga4 = None
    else:
        print("  GA4  not configured - page will show 'not connected'")

    print("  AI   reading ai-crawler-logger logs over SSH")
    ai = build_ai_crawlers(cfg)
    if ai:
        print(f"       {ai['aiHits']} AI-engine hits across {ai['days']} days "
              f"({ai['searchHits']} search-crawler, {ai['llmsTxtHits']} llms.txt)")

    DATA_DIR.mkdir(parents=True, exist_ok=True)
    out = DATA_DIR / f"{slug}.json"
    out.write_text(json.dumps({"gsc": gsc, "ga4": ga4, "ai": ai}, indent=1), encoding="utf-8")
    print(f"  ->   {out} ({out.stat().st_size // 1024} KB)")
    return out


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("client", nargs="?", help="client slug from clients.json")
    ap.add_argument("--all", action="store_true")
    a = ap.parse_args()

    clients = {k: v for k, v in json.loads(CLIENTS_FILE.read_text(encoding="utf-8")).items()
               if not k.startswith("_")}

    # Log paths that carry a client's hosting account name are kept out of the
    # repository; fold them in here so clients.json can stay committable.
    creds_file = Path(r"C:\Users\yasir\.claude-tools\azwebcorp-creds.json")
    if creds_file.exists():
        extra = json.loads(creds_file.read_text(encoding="utf-8")).get("portal_ai_log_dirs") or {}
        for slug, path in extra.items():
            if slug in clients and not clients[slug].get("ai_log_dir"):
                clients[slug]["ai_log_dir"] = path
    if a.all:
        targets = list(clients)
    elif a.client:
        if a.client not in clients:
            raise SystemExit(f"unknown client {a.client!r}; known: {', '.join(clients)}")
        targets = [a.client]
    else:
        raise SystemExit(f"pass a client slug ({', '.join(clients)}) or --all")

    for slug in targets:
        sync(slug, clients[slug])


if __name__ == "__main__":
    main()
