import json
from datetime import date, timedelta
from pathlib import Path

import pandas as pd
from googleapiclient.discovery import build

ROOT = Path(r"C:\Users\yasir\Documents\Final-Capstone\seo-audit")
TOKEN = Path(r"C:\Users\yasir\.claude-tools\gsc-oauth-token.json")
SITE = "sc-domain:azwebcorp.com"
OUT = ROOT / "reports" / f"AZWebCorp-Google-Reports-{date.today().isoformat()}"
END = date.today() - timedelta(days=3)
START = END - timedelta(days=485)


def credentials():
    from google.auth.transport.requests import Request
    from google.oauth2.credentials import Credentials

    scopes = ["https://www.googleapis.com/auth/webmasters.readonly"]
    creds = Credentials.from_authorized_user_file(str(TOKEN), scopes)
    if creds.expired and creds.refresh_token:
        creds.refresh(Request())
    return creds


def query(service, start, end, dimensions=None, search_type="web", filters=None):
    body = {
        "startDate": start.isoformat(),
        "endDate": end.isoformat(),
        "type": search_type,
        "rowLimit": 25000,
        "dataState": "final",
    }
    if dimensions:
        body["dimensions"] = dimensions
    if filters:
        body["dimensionFilterGroups"] = [{"filters": filters}]
    rows, offset = [], 0
    while True:
        body["startRow"] = offset
        result = service.searchanalytics().query(siteUrl=SITE, body=body).execute()
        batch = result.get("rows", [])
        rows.extend(batch)
        if len(batch) < body["rowLimit"]:
            break
        offset += len(batch)
    records = []
    for row in rows:
        rec = {d: v for d, v in zip(dimensions or [], row.get("keys", []))}
        rec.update({k: row.get(k, 0) for k in ("clicks", "impressions", "ctr", "position")})
        records.append(rec)
    return pd.DataFrame(records)


def totals(service, start, end, search_type="web"):
    df = query(service, start, end, search_type=search_type)
    if df.empty:
        return {"clicks": 0, "impressions": 0, "ctr": 0, "position": 0}
    return df.iloc[0].to_dict()


def add_period(df, period):
    df.insert(0, "period", period)
    return df


def safe_sheet(name):
    return name[:31].replace("/", "-").replace("\\", "-")


def main():
    OUT.mkdir(parents=True, exist_ok=True)
    service = build("searchconsole", "v1", credentials=credentials(), cache_discovery=False)
    reports = {}

    reports["daily_web"] = query(service, START, END, ["date"])
    daily = reports["daily_web"].copy()
    daily["date"] = pd.to_datetime(daily["date"])
    for label, freq in (("weekly_web", "W-MON"), ("monthly_web", "MS")):
        grouped = daily.set_index("date").resample(freq).agg({"clicks": "sum", "impressions": "sum"}).reset_index()
        grouped["ctr"] = grouped["clicks"].div(grouped["impressions"]).fillna(0)
        grouped["position"] = float("nan")
        reports[label] = grouped
    reports["queries_web"] = query(service, START, END, ["query"])
    reports["pages_web"] = query(service, START, END, ["page"])
    reports["query_page_web"] = query(service, START, END, ["query", "page"])
    reports["countries_web"] = query(service, START, END, ["country"])
    reports["devices_web"] = query(service, START, END, ["device"])
    reports["appearance_web"] = query(service, START, END, ["searchAppearance"])
    reports["date_device_web"] = query(service, START, END, ["date", "device"])
    reports["page_device_web"] = query(service, START, END, ["page", "device"])
    reports["query_country_web"] = query(service, START, END, ["query", "country"])

    type_rows = []
    for stype in ("web", "image", "video", "news", "discover", "googleNews"):
        try:
            value = totals(service, START, END, stype)
            type_rows.append({"search_type": stype, **value})
            if value.get("impressions", 0):
                reports[f"daily_{stype}"] = query(service, START, END, ["date"], stype)
                reports[f"queries_{stype}"] = query(service, START, END, ["query"], stype)
                reports[f"pages_{stype}"] = query(service, START, END, ["page"], stype)
        except Exception as exc:
            type_rows.append({"search_type": stype, "error": str(exc)})
    reports["search_types"] = pd.DataFrame(type_rows)

    comparisons = []
    for days in (7, 28, 90, 365):
        cur_start = END - timedelta(days=days - 1)
        prev_end = cur_start - timedelta(days=1)
        prev_start = prev_end - timedelta(days=days - 1)
        for label, pstart, pend in (("current", cur_start, END), ("previous", prev_start, prev_end)):
            value = totals(service, pstart, pend)
            comparisons.append({"window_days": days, "period": label, "start": pstart, "end": pend, **value})
            reports[f"queries_{days}d_{label}"] = add_period(query(service, pstart, pend, ["query"]), label)
            reports[f"pages_{days}d_{label}"] = add_period(query(service, pstart, pend, ["page"]), label)
    reports["period_comparisons"] = pd.DataFrame(comparisons)

    q = reports["queries_web"].copy()
    if not q.empty:
        reports["quick_wins"] = q[(q.impressions >= 10) & (q.position.between(4, 20))].sort_values(["impressions", "position"], ascending=[False, True])
        reports["high_impression_low_ctr"] = q[(q.impressions >= 20) & (q.ctr < 0.02)].sort_values("impressions", ascending=False)
        reports["striking_distance"] = q[q.position.between(8, 20)].sort_values("impressions", ascending=False)
        reports["brand_queries"] = q[q["query"].str.contains(r"az\s*web\s*corp|azwebcorp", case=False, regex=True, na=False)]
        reports["nonbrand_queries"] = q[~q.index.isin(reports["brand_queries"].index)]

    qp = reports["query_page_web"].copy()
    if not qp.empty:
        counts = qp.groupby("query")["page"].nunique().reset_index(name="ranking_pages")
        cannibal = counts[counts.ranking_pages > 1]
        reports["cannibalization"] = qp.merge(cannibal, on="query").sort_values(["ranking_pages", "impressions"], ascending=[False, False])

    sitemap_rows = []
    try:
        for item in service.sitemaps().list(siteUrl=SITE).execute().get("sitemap", []):
            row = {k: item.get(k) for k in ("path", "lastSubmitted", "isPending", "isSitemapsIndex", "type", "lastDownloaded", "warnings", "errors")}
            contents = item.get("contents", [])
            row["submitted"] = sum(x.get("submitted", 0) for x in contents)
            row["indexed"] = sum(x.get("indexed", 0) for x in contents)
            sitemap_rows.append(row)
    except Exception as exc:
        sitemap_rows.append({"error": str(exc)})
    reports["sitemaps"] = pd.DataFrame(sitemap_rows)

    for name, df in reports.items():
        df.to_csv(OUT / f"{name}.csv", index=False, encoding="utf-8-sig")

    workbook = OUT / "AZWebCorp-GSC-Complete.xlsx"
    with pd.ExcelWriter(workbook, engine="openpyxl") as writer:
        for name, df in reports.items():
            df.to_excel(writer, sheet_name=safe_sheet(name), index=False)
            ws = writer.book[safe_sheet(name)]
            ws.freeze_panes = "A2"
            ws.auto_filter.ref = ws.dimensions

    manifest = {
        "generated": date.today().isoformat(),
        "property": SITE,
        "data_start": START.isoformat(),
        "data_end": END.isoformat(),
        "report_count": len(reports),
        "reports": {name: len(df) for name, df in reports.items()},
        "notes": [
            "Search Console final data is normally delayed by roughly 2-3 days.",
            "Rows are API data, not sampled estimates.",
            "The Search Analytics API can omit anonymized queries.",
        ],
    }
    (OUT / "manifest.json").write_text(json.dumps(manifest, indent=2, default=str), encoding="utf-8")
    print(json.dumps({"output": str(OUT), **manifest}, indent=2, default=str))


if __name__ == "__main__":
    main()
