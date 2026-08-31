"""
Query Search Console via the OAuth user token (covers every property Yasir's
own Google account can see) instead of the per-property service account.

Usage:
    python gsc_query_oauth.py '{"siteUrl": "https://everythingit.ie/", "startDate": "...", ...}'
"""
import json, sys, os

TOKEN = r"C:\Users\yasir\.claude-tools\gsc-oauth-token.json"
SCOPES = ["https://www.googleapis.com/auth/webmasters.readonly"]


def load_credentials():
    from google.oauth2.credentials import Credentials
    from google.auth.transport.requests import Request

    creds = Credentials.from_authorized_user_file(TOKEN, SCOPES)
    if creds.expired and creds.refresh_token:
        creds.refresh(Request())
        with open(TOKEN, "w", encoding="utf-8") as fh:
            fh.write(creds.to_json())
    return creds


def query(site_url, body):
    from googleapiclient.discovery import build
    creds = load_credentials()
    svc = build("searchconsole", "v1", credentials=creds, cache_discovery=False)
    return svc.searchanalytics().query(siteUrl=site_url, body=body).execute()


if __name__ == "__main__":
    argument = sys.argv[1]
    if argument.startswith("@"):
        with open(argument[1:], "r", encoding="utf-8-sig") as fh:
            payload = json.load(fh)
    else:
        payload = json.loads(argument)
    site_url = payload.pop("siteUrl")
    print(json.dumps(query(site_url, payload), indent=2))
