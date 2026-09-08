"""Prove the resolver keeps GA4 reporting working while ga4-oauth-token.json
is expired: resolve a token, then pull real rows from the Data API for the
properties the portal and the EIT monthly report actually read.

    python scripts/ga4_stopgap_check.py

Read-only.
"""

import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
sys.path.insert(0, str(ROOT))

from google.oauth2.credentials import Credentials  # noqa: E402
from googleapiclient.discovery import build  # noqa: E402

from ga4_token import resolve_ga4_token  # noqa: E402

SCOPES = ["https://www.googleapis.com/auth/analytics.readonly"]

PROPERTIES = {
    "everythingit.ie (EIT monthly report)": "properties/552051066",
    "azwebcorp.com (portal)": "properties/247570709",
}


def main() -> int:
    token = resolve_ga4_token(SCOPES)
    print(f"token in use: {token.name}\n")

    creds = Credentials.from_authorized_user_file(str(token), SCOPES)
    svc = build("analyticsdata", "v1beta", credentials=creds, cache_discovery=False)

    failures = 0
    for label, prop in PROPERTIES.items():
        try:
            resp = svc.properties().runReport(
                property=prop,
                body={
                    "dateRanges": [{"startDate": "28daysAgo", "endDate": "yesterday"}],
                    "metrics": [{"name": "sessions"}, {"name": "totalUsers"}],
                },
            ).execute()
            rows = resp.get("rows", [])
            if rows:
                sessions, users = (v["value"] for v in rows[0]["metricValues"])
                print(f"OK  {label}: {sessions} sessions, {users} users (last 28 days)")
            else:
                print(f"OK  {label}: query succeeded, no rows in range")
        except Exception as exc:  # noqa: BLE001 - the reason is the output
            failures += 1
            print(f"FAIL {label}: {type(exc).__name__}: {str(exc)[:140]}")

    print("\n" + ("all GA4 reads working" if not failures else f"{failures} property read(s) failing"))
    return 1 if failures else 0


if __name__ == "__main__":
    sys.exit(main())
