"""
Give the portal's read-only service account Viewer on every AZ Web Corp GA4
property, so each client dashboard can read its own data without a fresh
consent later.

Uses the manage.users token from grant_ga4_viewer.py - no new consent.
Property-scoped on purpose: never granted at account level, so a key is only
ever able to read the properties it is explicitly given.

Usage:
    python ga4_grant_all_properties.py            # dry run
    python ga4_grant_all_properties.py --apply
"""

import json
import sys
from pathlib import Path

from google.oauth2.credentials import Credentials
from googleapiclient.discovery import build
from googleapiclient.errors import HttpError

TOKEN = Path(r"C:\Users\yasir\.claude-tools\ga4-admin-token.json")
SERVICE_ACCOUNT = "gsc-reader@azwebcorp-gsc-77313.iam.gserviceaccount.com"
VIEWER = "predefinedRoles/viewer"
AZW_ACCOUNT_NAME = "AZ Web Corp"

APPLY = "--apply" in sys.argv


def main():
    tok = json.loads(TOKEN.read_text(encoding="utf-8"))
    creds = Credentials.from_authorized_user_file(str(TOKEN), tok.get("scopes"))
    a = build("analyticsadmin", "v1alpha", credentials=creds, cache_discovery=False)

    targets = []
    for acc in a.accountSummaries().list(pageSize=200).execute().get("accountSummaries", []):
        if acc.get("displayName") != AZW_ACCOUNT_NAME:
            continue
        for p in acc.get("propertySummaries", []):
            targets.append((p["property"], p.get("displayName")))

    print(f"{len(targets)} properties under '{AZW_ACCOUNT_NAME}'\n")
    for prop, name in targets:
        try:
            bound = [b for b in a.properties().accessBindings().list(parent=prop)
                     .execute().get("accessBindings", [])
                     if b.get("user") == SERVICE_ACCOUNT]
            if bound:
                print(f"  have  {name:<28} {prop}")
                continue
            if not APPLY:
                print(f"  would grant  {name:<28} {prop}")
                continue
            a.properties().accessBindings().create(
                parent=prop, body={"user": SERVICE_ACCOUNT, "roles": [VIEWER]}
            ).execute()
            print(f"  GRANTED  {name:<28} {prop}")
        except HttpError as e:
            print(f"  FAILED   {name:<28} {e.resp.status} {str(e)[:120]}")

    if not APPLY:
        print("\n(dry run - re-run with --apply)")
    return 0


if __name__ == "__main__":
    sys.exit(main())
