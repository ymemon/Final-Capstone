import json
from pathlib import Path

from google.oauth2.credentials import Credentials
from googleapiclient.discovery import build

TOKEN = Path(r"C:\Users\yasir\.claude-tools\ga4-oauth-token.json")
SCOPES = ["https://www.googleapis.com/auth/analytics.readonly", "https://www.googleapis.com/auth/analytics.manage.users.readonly"]
PROPERTY = "properties/233531759"


def run(dimensions, metrics, start="2020-01-01", end="2026-08-29"):
    creds = Credentials.from_authorized_user_file(str(TOKEN), SCOPES)
    svc = build("analyticsdata", "v1beta", credentials=creds, cache_discovery=False)
    body = {
        "dateRanges": [{"startDate": start, "endDate": end}],
        "dimensions": [{"name": d} for d in dimensions],
        "metrics": [{"name": m} for m in metrics],
        "dimensionFilter": {"filter": {"fieldName": "hostName", "stringFilter": {"matchType": "EXACT", "value": "azwebcorp.com", "caseSensitive": False}}},
        "limit": "100000",
    }
    result = svc.properties().runReport(property=PROPERTY, body=body).execute()
    rows = []
    for row in result.get("rows", []):
        values = [x.get("value", "") for x in row.get("dimensionValues", []) + row.get("metricValues", [])]
        rows.append(dict(zip(dimensions + metrics, values)))
    return rows


if __name__ == "__main__":
    output = {
        "referrers": run(["pageReferrer"], ["sessions", "totalUsers", "screenPageViews", "keyEvents"]),
        "source_medium": run(["sessionSourceMedium"], ["sessions", "totalUsers", "screenPageViews", "keyEvents"]),
        "countries": run(["country"], ["sessions", "totalUsers", "screenPageViews", "keyEvents"]),
        "channels": run(["sessionDefaultChannelGroup"], ["sessions", "totalUsers", "screenPageViews", "keyEvents"]),
        "monthly": run(["yearMonth"], ["sessions", "totalUsers", "screenPageViews", "keyEvents"]),
    }
    print(json.dumps(output, indent=2))
