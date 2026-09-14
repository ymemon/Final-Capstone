"""
Grant the read-only service account Viewer access on the AZ Web Corp GA4
property, so the SEO portal's live-audience feed can call runRealtimeReport.

WHY A SCRIPT AND NOT THE GA4 UI
The Admin UI works, but it is easy to grant on the wrong scope: the parent
account also contains Everything IT's property, so account-level access would
hand this key a client's data too. This script targets property 247570709
explicitly and cannot drift.

WHY IT NEEDS A NEW CONSENT
The saved GA4 token only carries `analytics.readonly`. Creating an access
binding needs `analytics.manage.users`, and the Analytics Admin API returns
ACCESS_TOKEN_SCOPE_INSUFFICIENT without it (confirmed by a real attempt, not
assumed). So this opens one browser consent, then does everything else itself.

Access bindings live in the v1alpha Admin API - v1beta has no accessBindings
resource at all, which surfaces as a confusing
"'Resource' object has no attribute 'accessBindings'".

WHAT IT DOES
  1. one browser consent (click Allow once)
  2. creates the Viewer binding, or reports it already exists
  3. lists the property's bindings so you can see the result
  4. confirms the old reporting token still works, and repairs it if Google
     invalidated it as part of the new consent
  5. calls the live endpoint to prove the portal feed now works

Usage:
    python grant_ga4_viewer.py
"""

import json
import shutil
import sys
import urllib.request
from pathlib import Path

from google.oauth2.credentials import Credentials
from google.auth.transport.requests import Request
from google_auth_oauthlib.flow import InstalledAppFlow
from googleapiclient.discovery import build
from googleapiclient.errors import HttpError

CLIENT = Path(r"C:\Users\yasir\.claude-tools\gsc-oauth-client.json")
REPORT_TOKEN = Path(r"C:\Users\yasir\.claude-tools\ga4-oauth-token.json")
ADMIN_TOKEN = Path(r"C:\Users\yasir\.claude-tools\ga4-admin-token.json")

PROPERTY = "properties/247570709"
SERVICE_ACCOUNT = "gsc-reader@azwebcorp-gsc-77313.iam.gserviceaccount.com"
VIEWER = "predefinedRoles/read"
LIVE_URL = "https://azwebcorp.com/reports/azwebcorp/api/live.php"

SCOPES = [
    "https://www.googleapis.com/auth/analytics.readonly",
    "https://www.googleapis.com/auth/analytics.manage.users",
]


def step(n, msg):
    print(f"\n[{n}] {msg}")


def main():
    step(1, "Opening a browser for consent - click Allow, then come back here.")
    flow = InstalledAppFlow.from_client_secrets_file(str(CLIENT), SCOPES)
    creds = flow.run_local_server(port=0, prompt="consent")
    ADMIN_TOKEN.write_text(creds.to_json(), encoding="utf-8")
    print(f"    consent complete; admin token saved to {ADMIN_TOKEN.name}")

    admin = build("analyticsadmin", "v1alpha", credentials=creds, cache_discovery=False)

    step(2, f"Granting Viewer on {PROPERTY} to {SERVICE_ACCOUNT}")
    try:
        created = admin.properties().accessBindings().create(
            parent=PROPERTY,
            body={"user": SERVICE_ACCOUNT, "roles": [VIEWER]},
        ).execute()
        print(f"    created: {created.get('name')}  roles={created.get('roles')}")
    except HttpError as e:
        detail = getattr(e, "reason", "") or str(e)
        if e.resp.status == 409 or "already exists" in detail.lower():
            print("    already had access - nothing to change.")
        else:
            print(f"    FAILED: HTTP {e.resp.status} - {detail}")
            print("    Nothing else was changed. Fix this before continuing.")
            return 1

    step(3, "Current access on this property")
    bindings = admin.properties().accessBindings().list(parent=PROPERTY).execute()
    for b in bindings.get("accessBindings", []):
        who = b.get("user", "(unknown)")
        mark = "  <-- the portal key" if who == SERVICE_ACCOUNT else ""
        print(f"    {who:<62} {b.get('roles')}{mark}")

    step(4, "Checking the existing reporting token still works")
    repaired = False
    try:
        old = Credentials.from_authorized_user_file(
            str(REPORT_TOKEN), ["https://www.googleapis.com/auth/analytics.readonly"])
        old.refresh(Request())
        print("    reporting token OK - untouched.")
    except Exception as exc:
        print(f"    reporting token no longer refreshes ({str(exc)[:90]})")
        shutil.copyfile(ADMIN_TOKEN, REPORT_TOKEN)
        repaired = True
        print("    repaired: replaced it with the new token (it includes read access).")

    step(5, "Testing the portal's live endpoint")
    import time
    url = f"{LIVE_URL}?t={int(time.time())}"
    try:
        with urllib.request.urlopen(url, timeout=40) as r:
            payload = json.loads(r.read().decode())
        if "error" in payload:
            print(f"    endpoint still reports: {payload['error']}")
            print("    Grants can take a minute to propagate - re-run just this check shortly.")
        else:
            print(f"    LIVE FEED WORKING - activeUsers={payload.get('activeUsers')}, "
                  f"{len(payload.get('pages', []))} page(s) reported")
    except Exception as exc:
        print(f"    could not reach the endpoint: {str(exc)[:120]}")

    print("\nDone." + ("  (reporting token was repaired)" if repaired else ""))
    return 0


if __name__ == "__main__":
    sys.exit(main())
