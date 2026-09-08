"""Report whether an existing GA4 OAuth token still works, before anyone sits
through the consent flow again.

    python scripts/ga4_token_check.py

Read-only, and deliberately prints no secret material: scopes, expiry and
whether a refresh succeeds, never the token or refresh token itself.
"""

import json
from pathlib import Path

from google.auth.transport.requests import Request
from google.oauth2.credentials import Credentials
from googleapiclient.discovery import build

TOKENS = [
    Path(r"C:\Users\yasir\.claude-tools\ga4-oauth-token.json"),
    Path(r"C:\Users\yasir\.claude-tools\ga4-admin-token.json"),
    Path(r"C:\Users\yasir\.claude-tools\ga4-edit-token.json"),
]


def check(path: Path) -> None:
    print(f"\n{path.name}")
    if not path.exists():
        print("  missing")
        return

    raw = json.loads(path.read_text(encoding="utf-8"))
    scopes = raw.get("scopes", [])
    print("  scopes : " + (", ".join(s.rsplit("/", 1)[-1] for s in scopes) or "(none listed)"))
    print("  expiry : " + str(raw.get("expiry", "(not recorded)")))
    print("  refresh token present: " + ("yes" if raw.get("refresh_token") else "NO"))

    try:
        creds = Credentials.from_authorized_user_file(str(path), scopes)
        if not creds.valid:
            if creds.expired and creds.refresh_token:
                creds.refresh(Request())
                print("  refreshed: yes (expired access token renewed silently)")
            else:
                print("  refreshed: NOT POSSIBLE - re-auth required")
                return
        else:
            print("  refreshed: not needed, still valid")

        admin = build("analyticsadmin", "v1beta", credentials=creds, cache_discovery=False)
        summaries = admin.accountSummaries().list(pageSize=200).execute().get("accountSummaries", [])
        props = sum(len(a.get("propertySummaries", [])) for a in summaries)
        print(f"  LIVE CALL OK: {len(summaries)} account(s), {props} propert(ies) visible")
        for account in summaries:
            print(f"    - {account.get('displayName')}")
            for prop in account.get("propertySummaries", []):
                print(f"        {prop.get('displayName')}  {prop.get('property')}")
    except Exception as exc:  # noqa: BLE001 - surfacing the reason is the point
        print(f"  LIVE CALL FAILED: {type(exc).__name__}: {str(exc)[:160]}")


if __name__ == "__main__":
    for token in TOKENS:
        check(token)
