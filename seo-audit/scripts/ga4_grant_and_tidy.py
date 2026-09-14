"""
Finish the GA4 work: grant the portal's service account Viewer on the AZ Web
Corp property, then tidy the account/property display names.

Reuses the admin token saved by grant_ga4_viewer.py - no second consent.

ROLE STRINGS: the Admin API rejects `predefinedRoles/read`. Reading the real
bindings back showed the account owner holds `predefinedRoles/admin`, so the
pattern is predefinedRoles/<role> and the read-only one is
`predefinedRoles/viewer`.

RENAMES ONLY - NOTHING IS DELETED. Property 233531759 ("lootertech.com") holds
historical azwebcorp.com traffic (it is the property the earlier
recover_azw_ga4_from_wrong_property.py script had to filter by hostname), so
deleting it would destroy real history. Deletion stays a separate, explicit
decision.

Usage:
    python ga4_grant_and_tidy.py            # show what it would do
    python ga4_grant_and_tidy.py --apply
"""

import json
import sys
import time
import urllib.request
from pathlib import Path

from google.oauth2.credentials import Credentials
from googleapiclient.discovery import build
from googleapiclient.errors import HttpError

TOKEN = Path(r"C:\Users\yasir\.claude-tools\ga4-admin-token.json")
PROPERTY = "properties/247570709"
SERVICE_ACCOUNT = "gsc-reader@azwebcorp-gsc-77313.iam.gserviceaccount.com"
VIEWER = "predefinedRoles/viewer"
LIVE_URL = "https://azwebcorp.com/reports/azwebcorp/api/live.php"

# resource -> new display name. Renames only; no deletions.
RENAMES = {
    "accounts/179036761":   "AZ Web Corp",
    "properties/247570709": "azwebcorp.com",
    "properties/385648619": "lootertech.com (GA4)",
}

APPLY = "--apply" in sys.argv


def svc():
    tok = json.loads(TOKEN.read_text(encoding="utf-8"))
    creds = Credentials.from_authorized_user_file(str(TOKEN), tok.get("scopes"))
    return build("analyticsadmin", "v1alpha", credentials=creds, cache_discovery=False)


def main():
    a = svc()

    print("[1] Granting Viewer to the portal service account")
    if not APPLY:
        print(f"    would grant {VIEWER} on {PROPERTY} to {SERVICE_ACCOUNT}")
    else:
        try:
            r = a.properties().accessBindings().create(
                parent=PROPERTY, body={"user": SERVICE_ACCOUNT, "roles": [VIEWER]}
            ).execute()
            print(f"    granted: {r.get('name')} roles={r.get('roles')}")
        except HttpError as e:
            if e.resp.status == 409 or "already exists" in str(e).lower():
                print("    already had access.")
            else:
                print(f"    FAILED: {e.resp.status} {str(e)[:200]}")
                return 1

    print("\n[2] Access now on the AZ Web Corp property")
    for b in a.properties().accessBindings().list(parent=PROPERTY).execute().get("accessBindings", []):
        mark = "  <-- portal key" if b.get("user") == SERVICE_ACCOUNT else ""
        print(f"    {b.get('user'):<50} {b.get('roles')}{mark}")

    print("\n[3] Display names")
    for res, new in RENAMES.items():
        try:
            if res.startswith("accounts/"):
                cur = a.accounts().get(name=res).execute()
                old = cur.get("displayName")
                if not APPLY:
                    print(f"    would rename ACCOUNT  {res}  '{old}' -> '{new}'")
                else:
                    a.accounts().patch(name=res, updateMask="displayName",
                                       body={"displayName": new}).execute()
                    print(f"    ACCOUNT  {res}  '{old}' -> '{new}'")
            else:
                cur = a.properties().get(name=res).execute()
                old = cur.get("displayName")
                if not APPLY:
                    print(f"    would rename PROPERTY {res}  '{old}' -> '{new}'")
                else:
                    a.properties().patch(name=res, updateMask="displayName",
                                         body={"displayName": new}).execute()
                    print(f"    PROPERTY {res}  '{old}' -> '{new}'")
        except HttpError as e:
            print(f"    {res}: FAILED {e.resp.status} {str(e)[:140]}")

    print("\n[4] Final inventory")
    for acc in a.accountSummaries().list(pageSize=200).execute().get("accountSummaries", []):
        print(f"    ACCOUNT {acc.get('displayName')}")
        for p in acc.get("propertySummaries", []):
            print(f"       {p.get('property'):<22} {p.get('displayName')}")

    if APPLY:
        print("\n[5] Portal live endpoint")
        try:
            with urllib.request.urlopen(f"{LIVE_URL}?t={int(time.time())}", timeout=40) as r:
                p = json.loads(r.read().decode())
            if "error" in p:
                print(f"    still: {p['error']}  (grants can take a minute to propagate)")
            else:
                print(f"    LIVE - activeUsers={p.get('activeUsers')}, pages={len(p.get('pages', []))}")
        except Exception as e:
            print(f"    endpoint error: {str(e)[:140]}")
    else:
        print("\n(dry run - re-run with --apply)")
    return 0


if __name__ == "__main__":
    sys.exit(main())
