"""
One-time OAuth setup for Search Console — the alternative to per-property
service-account grants.

WHY THIS EXISTS
The service account (gsc-reader@azwebcorp-gsc-77313…) works, but it can only
see properties it has been explicitly added to as a user. Adding it to the
Prestige property failed with "email not found" on 2026-08-27, and that dialog
also requires OWNER rights on the property, not merely Full user.

Authenticating as Yasir's own Google account removes the problem entirely:
the API then sees **every property that account can see**, with no per-property
user administration at all. For an agency adding client properties over time
(Prestige now, PaloVerde and Everything IT later) that is the right shape —
one consent, then every future client property is visible the moment it is
verified, with nothing extra to configure.

SETUP — done once, needs Yasir in a browser
  1. Google Cloud Console → project **eit-gsc** (verified 2026-09-06 from the
     client JSON itself — NOT azwebcorp-gsc-77313, which hosts the older
     service account and is a dead end for this OAuth client)
     → APIs & Services → Credentials
     → Create credentials → OAuth client ID → Application type: **Desktop app**
  2. Download the JSON. Save it as:
         C:\\Users\\yasir\\.claude-tools\\gsc-oauth-client.json
  3. Run this script. A browser opens; sign in as the account that owns the
     Search Console properties and approve read-only access.
  4. The refresh token is written to gsc-oauth-token.json and reused from then
     on. The browser step never needs repeating unless the token is revoked.

If the consent screen is in "Testing" mode, add the signing-in address under
OAuth consent screen → Test users first, or Google will refuse with
"access_denied".

SCOPE is read-only. This cannot change anything in Search Console.
"""

import json
import os
import sys

CLIENT = r"C:\Users\yasir\.claude-tools\gsc-oauth-client.json"
TOKEN = r"C:\Users\yasir\.claude-tools\gsc-oauth-token.json"
SCOPES = ["https://www.googleapis.com/auth/webmasters.readonly"]


def load_credentials():
    """Return usable credentials, refreshing or running the flow as needed."""
    from google.oauth2.credentials import Credentials
    from google.auth.transport.requests import Request

    creds = None
    if os.path.exists(TOKEN):
        creds = Credentials.from_authorized_user_file(TOKEN, SCOPES)

    if creds and creds.valid:
        return creds

    if creds and creds.expired and creds.refresh_token:
        from google.auth.exceptions import RefreshError
        try:
            creds.refresh(Request())
            with open(TOKEN, "w", encoding="utf-8") as fh:
                fh.write(creds.to_json())
            return creds
        except RefreshError as e:
            # A REVOKED refresh token cannot be refreshed, only replaced. Falling
            # through to consent matters because Google expires refresh tokens
            # after 7 days while the consent screen is in "Testing" status, so
            # this path is hit routinely, not rarely.
            print(f"Stored token is dead ({e}); starting a fresh consent.\n")
            creds = None

    if not os.path.exists(CLIENT):
        raise SystemExit(
            f"Missing {CLIENT}\n\n"
            "Create it first: Google Cloud Console -> project azwebcorp-gsc-77313\n"
            "-> APIs & Services -> Credentials -> Create credentials\n"
            "-> OAuth client ID -> Desktop app -> download the JSON to that path."
        )

    from google_auth_oauthlib.flow import InstalledAppFlow
    flow = InstalledAppFlow.from_client_secrets_file(CLIENT, SCOPES)
    creds = flow.run_local_server(port=0, prompt="consent")
    with open(TOKEN, "w", encoding="utf-8") as fh:
        fh.write(creds.to_json())
    print(f"Refresh token saved to {TOKEN}")
    return creds


def list_properties(creds):
    from googleapiclient.discovery import build
    svc = build("searchconsole", "v1", credentials=creds, cache_discovery=False)
    return svc.sites().list().execute().get("siteEntry", [])


if __name__ == "__main__":
    creds = load_credentials()
    sites = list_properties(creds)
    print(f"\n{len(sites)} propert(ies) visible to this account:\n")
    for s in sites:
        print(f"   {s['siteUrl']:<52} {s.get('permissionLevel')}")
    if not sites:
        print("   none — signed in with an account that owns no properties?")
