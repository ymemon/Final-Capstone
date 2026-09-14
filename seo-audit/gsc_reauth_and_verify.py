"""
One-shot: re-authorize Search Console, then PROVE it works.

WHY THIS EXISTS
The console UI for this is genuinely confusing, and the old setup script died
with a stack trace when the token was revoked rather than just re-prompting.
This is the single thing to run when Search Console access stops working:
it re-consents, lists the properties, and then pulls real rows for a real
site so there is no ambiguity about whether it actually worked.

Run it directly, or double-click FIX-GOOGLE-SEARCH-CONSOLE.bat in Downloads.
"""

import os
import sys

HERE = os.path.dirname(os.path.abspath(__file__))
sys.path.insert(0, HERE)

VERIFY_SITE = "sc-domain:everythingit.ie"
LINE = "=" * 68


def main():
    print(LINE)
    print(" Google Search Console — re-authorize and verify")
    print(LINE)
    print()
    print(" A browser window is about to open.")
    print()
    print("   1. Sign in as the Google account that owns the Search Console")
    print("      properties (yasirmemon1976@gmail.com).")
    print("   2. If you see \"Google hasn't verified this app\", click")
    print("      Advanced  ->  Go to ... (unsafe).  That is expected and safe:")
    print("      this is your own tool, asking for READ-ONLY access.")
    print("   3. Approve.")
    print()
    print()

    import gsc_oauth_setup as setup

    try:
        creds = setup.load_credentials()
    except Exception as e:
        print(f" FAILED during sign-in: {e}")
        return 1

    try:
        sites = setup.list_properties(creds)
    except Exception as e:
        print(f" Signed in, but could not list properties: {e}")
        return 1

    print(f" Signed in OK — {len(sites)} propert(ies) visible:")
    print()
    for s in sites:
        print(f"    {s['siteUrl']:<48} {s.get('permissionLevel','')}")
    print()

    # Listing properties only proves the token is valid. Pulling real rows
    # proves the whole path works, which is the thing that actually broke.
    print(f" Pulling live data for {VERIFY_SITE} to confirm...")
    print()
    try:
        from gsc_query_oauth import query
        body = {
            "startDate": "2026-08-08",
            "endDate": "2026-09-04",
            "dimensions": ["query"],
            "rowLimit": 5,
        }
        resp = query(VERIFY_SITE, body)
        rows = resp.get("rows", [])
    except Exception as e:
        print(f" Auth worked, but the data query failed: {e}")
        return 1

    if not rows:
        print(" Auth works, but that property returned no rows for the window.")
        print(" Not necessarily broken — tell Claude and it will investigate.")
        return 0

    print(f" {'query':<38}{'pos':>6}{'impr':>8}{'clicks':>8}")
    for r in rows:
        kw = r["keys"][0][:36]
        print(f" {kw:<38}{r['position']:>6.1f}{r['impressions']:>8.0f}{r['clicks']:>8.0f}")

    print()
    print(LINE)
    print(" SUCCESS — Search Console access is working again.")
    print(" Tell Claude \"GSC is back\" and it will carry on.")
    print(LINE)
    return 0


if __name__ == "__main__":
    try:
        code = main()
    except KeyboardInterrupt:
        print("\n Cancelled.")
        code = 1
    sys.exit(code)
