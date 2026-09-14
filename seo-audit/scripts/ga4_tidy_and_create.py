"""
Tidy GA4 display names and create the missing properties, one consent.

WHY A SECOND CONSENT
The token from grant_ga4_viewer.py carries `analytics.manage.users`, which is
enough to grant people access but NOT to edit a property. Renames came back 403.
Creating and renaming properties needs `analytics.edit`.

WHAT IT DOES
  renames  - accounts/179036761 'cooldev' -> 'AZ Web Corp'
             properties/247570709 'cooldev' -> 'azwebcorp.com'
             properties/385648619 'lootertech - GA4' -> 'lootertech.com (GA4)'
  creates  - a property + web data stream for each site on the roster that has
             none, under the AZ Web Corp account

NOTHING IS DELETED. Property 233531759 ('lootertech.com') holds historical
azwebcorp.com traffic - it is the property recover_azw_ga4_from_wrong_property.py
had to filter by hostname - so removing it would destroy real history. Deletion
stays a separate, explicit decision.

Creating a property does NOT start collection: each new stream returns a
measurement ID that still has to be installed on the site. The script prints
them for exactly that reason.

Idempotent: a site that already has a property by display name is skipped.

Usage:
    python ga4_tidy_and_create.py            # dry run
    python ga4_tidy_and_create.py --apply
"""

import json
import sys
from pathlib import Path

from google_auth_oauthlib.flow import InstalledAppFlow
from google.oauth2.credentials import Credentials
from googleapiclient.discovery import build
from googleapiclient.errors import HttpError

CLIENT = Path(r"C:\Users\yasir\.claude-tools\gsc-oauth-client.json")
TOKEN = Path(r"C:\Users\yasir\.claude-tools\ga4-edit-token.json")
SCOPES = [
    "https://www.googleapis.com/auth/analytics.readonly",
    "https://www.googleapis.com/auth/analytics.edit",
]

AZW_ACCOUNT = "accounts/179036761"

RENAMES = {
    AZW_ACCOUNT:            "AZ Web Corp",
    "properties/247570709": "azwebcorp.com",
    "properties/385648619": "lootertech.com (GA4)",
}

# site -> (display name, timezone, currency)
CREATE = [
    ("https://prestigewindowsaz.com",     "prestigewindowsaz.com",     "America/Phoenix", "USD"),
    ("https://prestigehomestudioaz.com",  "prestigehomestudioaz.com",  "America/Phoenix", "USD"),
    ("https://pvcancer.com",              "pvcancer.com",              "America/Phoenix", "USD"),
    ("https://shopazwebcorp.com",         "shopazwebcorp.com",         "America/Phoenix", "USD"),
]

APPLY = "--apply" in sys.argv


def creds():
    if TOKEN.exists():
        try:
            c = Credentials.from_authorized_user_file(str(TOKEN), SCOPES)
            if c and c.refresh_token:
                return c
        except Exception:
            pass
    print("Opening a browser for consent - click Allow, then come back here.")
    flow = InstalledAppFlow.from_client_secrets_file(str(CLIENT), SCOPES)
    c = flow.run_local_server(port=0, prompt="consent")
    TOKEN.write_text(c.to_json(), encoding="utf-8")
    print(f"  consent complete; token saved to {TOKEN.name}\n")
    return c


def main():
    a = build("analyticsadmin", "v1alpha", credentials=creds(), cache_discovery=False)

    existing = {}
    for acc in a.accountSummaries().list(pageSize=200).execute().get("accountSummaries", []):
        for p in acc.get("propertySummaries", []):
            existing[p.get("displayName", "").lower()] = p.get("property")

    print("[1] Renames")
    for res, new in RENAMES.items():
        try:
            if res.startswith("accounts/"):
                old = a.accounts().get(name=res).execute().get("displayName")
                if APPLY:
                    a.accounts().patch(name=res, updateMask="displayName",
                                       body={"displayName": new}).execute()
                print(f"    {'' if APPLY else 'would rename '}ACCOUNT  {res}  '{old}' -> '{new}'")
            else:
                old = a.properties().get(name=res).execute().get("displayName")
                if APPLY:
                    a.properties().patch(name=res, updateMask="displayName",
                                         body={"displayName": new}).execute()
                print(f"    {'' if APPLY else 'would rename '}PROPERTY {res}  '{old}' -> '{new}'")
        except HttpError as e:
            print(f"    {res}: FAILED {e.resp.status} {str(e)[:150]}")

    print("\n[2] Missing properties")
    created = []
    for url, name, tz, cur in CREATE:
        if name.lower() in existing:
            print(f"    skip    {name}  (already exists: {existing[name.lower()]})")
            continue
        if not APPLY:
            print(f"    would create  {name}  ({tz}, {cur})  stream -> {url}")
            continue
        try:
            prop = a.properties().create(body={
                "parent": AZW_ACCOUNT, "displayName": name,
                "timeZone": tz, "currencyCode": cur,
            }).execute()
            stream = a.properties().dataStreams().create(
                parent=prop["name"],
                body={"displayName": name, "type": "WEB_DATA_STREAM",
                      "webStreamData": {"defaultUri": url}},
            ).execute()
            mid = stream.get("webStreamData", {}).get("measurementId", "?")
            created.append((name, prop["name"], mid))
            print(f"    created {name}  {prop['name']}  measurementId={mid}")
        except HttpError as e:
            print(f"    {name}: FAILED {e.resp.status} {str(e)[:170]}")

    print("\n[3] Final inventory")
    for acc in a.accountSummaries().list(pageSize=200).execute().get("accountSummaries", []):
        print(f"    ACCOUNT {acc.get('displayName')}")
        for p in acc.get("propertySummaries", []):
            print(f"       {p.get('property'):<22} {p.get('displayName')}")

    if created:
        print("\n[4] Measurement IDs to install on each site (collection does NOT")
        print("    start until the tag is on the page):")
        for name, prop, mid in created:
            print(f"    {name:<28} {mid}")
    if not APPLY:
        print("\n(dry run - re-run with --apply)")
    return 0


if __name__ == "__main__":
    sys.exit(main())
