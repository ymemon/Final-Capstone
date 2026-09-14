"""One-time read-only OAuth setup for Google Analytics 4 reporting."""

from pathlib import Path

from google_auth_oauthlib.flow import InstalledAppFlow
from googleapiclient.discovery import build

CLIENT = Path(r"C:\Users\yasir\.claude-tools\gsc-oauth-client.json")
TOKEN = Path(r"C:\Users\yasir\.claude-tools\ga4-oauth-token.json")
SCOPES = [
    "https://www.googleapis.com/auth/analytics.readonly",
    "https://www.googleapis.com/auth/analytics.manage.users.readonly",
]


def main():
    flow = InstalledAppFlow.from_client_secrets_file(str(CLIENT), SCOPES)
    creds = flow.run_local_server(port=0, prompt="consent")
    TOKEN.write_text(creds.to_json(), encoding="utf-8")
    print(f"Saved read-only Analytics token: {TOKEN}")
    admin = build("analyticsadmin", "v1beta", credentials=creds, cache_discovery=False)
    summaries = admin.accountSummaries().list(pageSize=200).execute().get("accountSummaries", [])
    for account in summaries:
        print(f"ACCOUNT {account.get('displayName')} {account.get('account')}")
        for prop in account.get("propertySummaries", []):
            print(f"  PROPERTY {prop.get('displayName')} {prop.get('property')}")


if __name__ == "__main__":
    main()
