import json
from datetime import date, timedelta
from pathlib import Path

import pandas as pd
from google.oauth2.credentials import Credentials
from googleapiclient.discovery import build

PROPERTY = "properties/247570709"
TOKEN = Path(r"C:\Users\yasir\.claude-tools\ga4-oauth-token.json")
OUT = Path(r"C:\Users\yasir\Documents\Final-Capstone\seo-audit\reports") / f"AZWebCorp-Google-Reports-{date.today().isoformat()}"
START = date(2020, 1, 1)
END = date.today() - timedelta(days=1)
SCOPES = ["https://www.googleapis.com/auth/analytics.readonly"]


def run(service, dimensions, metrics, start=START, end=END, dimension_filter=None, limit=100000):
    body = {
        "dateRanges": [{"startDate": start.isoformat(), "endDate": end.isoformat()}],
        "dimensions": [{"name": x} for x in dimensions],
        "metrics": [{"name": x} for x in metrics],
        "limit": str(limit),
        "keepEmptyRows": False,
    }
    if dimension_filter:
        body["dimensionFilter"] = dimension_filter
    result = service.properties().runReport(property=PROPERTY, body=body).execute()
    columns = dimensions + metrics
    records = []
    for row in result.get("rows", []):
        vals = [x.get("value", "") for x in row.get("dimensionValues", [])]
        vals += [x.get("value", "") for x in row.get("metricValues", [])]
        records.append(dict(zip(columns, vals)))
    df = pd.DataFrame(records, columns=columns)
    for metric in metrics:
        if metric in df:
            df[metric] = pd.to_numeric(df[metric], errors="coerce")
    return df


def main():
    OUT.mkdir(parents=True, exist_ok=True)
    creds = Credentials.from_authorized_user_file(str(TOKEN), SCOPES)
    service = build("analyticsdata", "v1beta", credentials=creds, cache_discovery=False)
    common = ["sessions", "totalUsers", "newUsers", "activeUsers", "engagedSessions", "engagementRate", "averageSessionDuration", "screenPageViews", "eventCount", "keyEvents"]
    specs = {
        "ga4_daily_overview": (["date"], common),
        "ga4_weekly_overview": (["year", "week"], common),
        "ga4_monthly_overview": (["yearMonth"], common),
        "ga4_channel_acquisition": (["sessionDefaultChannelGroup"], common),
        "ga4_source_medium": (["sessionSourceMedium"], common),
        "ga4_campaigns": (["sessionCampaignName"], common),
        "ga4_first_user_channel": (["firstUserDefaultChannelGroup"], common),
        "ga4_first_user_source": (["firstUserSourceMedium"], common),
        "ga4_landing_pages": (["landingPagePlusQueryString"], common),
        "ga4_pages": (["pagePathPlusQueryString", "pageTitle"], ["screenPageViews", "totalUsers", "activeUsers", "userEngagementDuration", "eventCount", "keyEvents"]),
        "ga4_hostname_pages": (["hostName", "pagePath"], ["screenPageViews", "totalUsers", "eventCount"]),
        "ga4_events": (["eventName"], ["eventCount", "totalUsers", "eventCountPerUser", "eventValue", "keyEvents"]),
        "ga4_country": (["country"], common),
        "ga4_region": (["country", "region"], common),
        "ga4_city": (["country", "region", "city"], common),
        "ga4_devices": (["deviceCategory"], common),
        "ga4_browsers": (["browser"], common),
        "ga4_operating_systems": (["operatingSystem"], common),
        "ga4_platform_device": (["platform", "deviceCategory"], common),
        "ga4_screen_resolution": (["screenResolution"], ["sessions", "totalUsers", "engagementRate", "keyEvents"]),
        "ga4_language": (["language"], common),
        "ga4_day_hour": (["dayOfWeekName", "hour"], ["sessions", "totalUsers", "engagementRate", "keyEvents"]),
        "ga4_page_referrer": (["pageReferrer"], ["sessions", "totalUsers", "screenPageViews", "keyEvents"]),
        "ga4_google_ads_campaigns": (["googleAdsCampaignName"], ["sessions", "totalUsers", "advertiserAdCost", "advertiserAdClicks", "keyEvents"]),
        "ga4_ecommerce_items": (["itemName", "itemBrand", "itemCategory"], ["itemsViewed", "itemsAddedToCart", "itemsPurchased", "itemRevenue"]),
    }
    reports, errors = {}, {}
    for name, (dims, mets) in specs.items():
        try:
            reports[name] = run(service, dims, mets)
        except Exception as exc:
            errors[name] = str(exc)

    periods = []
    for days in (7, 28, 90, 365):
        cur_start = END - timedelta(days=days - 1)
        prev_end = cur_start - timedelta(days=1)
        prev_start = prev_end - timedelta(days=days - 1)
        for label, start, end in (("current", cur_start, END), ("previous", prev_start, prev_end)):
            frame = run(service, [], common, start, end)
            row = frame.iloc[0].to_dict() if not frame.empty else {m: 0 for m in common}
            periods.append({"window_days": days, "period": label, "start": start, "end": end, **row})
    reports["ga4_period_comparisons"] = pd.DataFrame(periods)

    organic_filter = {"filter": {"fieldName": "sessionDefaultChannelGroup", "stringFilter": {"matchType": "EXACT", "value": "Organic Search"}}}
    reports["ga4_organic_landing_pages"] = run(service, ["landingPagePlusQueryString"], common, dimension_filter=organic_filter)

    for name, df in reports.items():
        df.to_csv(OUT / f"{name}.csv", index=False, encoding="utf-8-sig")
    with pd.ExcelWriter(OUT / "AZWebCorp-GA4-Complete.xlsx", engine="openpyxl") as writer:
        for name, df in reports.items():
            sheet = name.replace("ga4_", "")[:31]
            df.to_excel(writer, sheet_name=sheet, index=False)
            ws = writer.book[sheet]
            ws.freeze_panes = "A2"
            ws.auto_filter.ref = ws.dimensions
    manifest = {"property": PROPERTY, "measurement_id": "G-R5RNDSH327", "start": START.isoformat(), "end": END.isoformat(), "reports": {k: len(v) for k, v in reports.items()}, "errors": errors}
    (OUT / "ga4-manifest.json").write_text(json.dumps(manifest, indent=2, default=str), encoding="utf-8")
    print(json.dumps({"output": str(OUT), **manifest}, indent=2, default=str))


if __name__ == "__main__":
    main()
