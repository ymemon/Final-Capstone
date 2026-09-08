"""Re-authorise the read-only GA4 token that sync_portal.py and the EIT
monthly report depend on.

    python scripts/ga4_oauth_reauth.py

Same scopes and output file as ga4_oauth_setup.py. The difference is that the
consent URL is printed rather than opened, so the flow can be started by one
process and completed by whoever is actually sitting at the browser.

WHY THE CALLBACK IS HANDLED BY HAND
The obvious version of this - call authorization_url() to get a link, print
it, then call run_local_server() to catch the redirect - does not work.
run_local_server() builds its own authorisation URL internally, which mints a
second CSRF state and makes the server expect that one. The printed link still
carries the first, so a correctly-completed consent comes back and is rejected
with MismatchingStateError. So the URL is generated once here, a minimal
server catches the code, the state is compared explicitly, and the token is
exchanged directly.
"""

import os
import sys
from http.server import BaseHTTPRequestHandler, HTTPServer

# Google returns every scope this OAuth client has previously been granted,
# not only the two asked for here, because other tokens issued from the same
# client already carry analytics.edit and webmasters.readonly. oauthlib treats
# any difference between requested and returned scope as fatal, so a correct
# consent dies during the token exchange. Relaxing the comparison accepts the
# superset; the scopes actually granted are printed at the end so a wider
# grant than intended is visible rather than silent.
os.environ.setdefault("OAUTHLIB_RELAX_TOKEN_SCOPE", "1")
from pathlib import Path
from urllib.parse import parse_qs, urlparse

from google_auth_oauthlib.flow import InstalledAppFlow
from googleapiclient.discovery import build

CLIENT = Path(r"C:\Users\yasir\.claude-tools\gsc-oauth-client.json")
TOKEN = Path(r"C:\Users\yasir\.claude-tools\ga4-oauth-token.json")
PORT = 8765
# Generous, because the person clicking the link is often not the person who
# started the flow, and a listener that lapses while they are walking to the
# right machine just wastes another round trip.
TIMEOUT_SECONDS = 3600

SCOPES = [
    "https://www.googleapis.com/auth/analytics.readonly",
    "https://www.googleapis.com/auth/analytics.manage.users.readonly",
]

PAGE = (
    "<html><body style='font-family:system-ui;background:#0b0d10;color:#e8eaed;"
    "padding:60px;text-align:center'><h2 style='color:#e6b84d'>{title}</h2>"
    "<p>{body}</p></body></html>"
)


class Catcher(BaseHTTPRequestHandler):
    """Single-shot handler: records the query string and stops."""

    result = None

    def do_GET(self):  # noqa: N802 - name fixed by BaseHTTPRequestHandler
        params = parse_qs(urlparse(self.path).query)
        Catcher.result = params
        ok = "code" in params
        html = PAGE.format(
            title="Authenticated" if ok else "Authentication failed",
            body="You can close this tab and return to the terminal."
            if ok
            else "No authorisation code came back. Close this tab and re-run the command.",
        )
        self.send_response(200)
        self.send_header("Content-Type", "text/html; charset=utf-8")
        self.end_headers()
        self.wfile.write(html.encode("utf-8"))

    def log_message(self, *_args):
        return  # keep the request log out of stdout


def main() -> int:
    flow = InstalledAppFlow.from_client_secrets_file(str(CLIENT), SCOPES)
    flow.redirect_uri = f"http://localhost:{PORT}/"
    # No include_granted_scopes: asking for the union back is what triggered
    # the scope-change failure, and this token only needs the two read scopes.
    auth_url, expected_state = flow.authorization_url(
        prompt="consent",
        access_type="offline",
    )

    print("CONSENT_URL_BEGIN", flush=True)
    print(auth_url, flush=True)
    print("CONSENT_URL_END", flush=True)
    print(f"listening on http://localhost:{PORT}/ for up to {TIMEOUT_SECONDS // 60} minutes", flush=True)

    server = HTTPServer(("localhost", PORT), Catcher)
    server.timeout = TIMEOUT_SECONDS
    server.handle_request()
    server.server_close()

    params = Catcher.result
    if not params:
        print("FAILED: nothing arrived on the callback before the timeout.", flush=True)
        return 1
    if "error" in params:
        print(f"FAILED: consent returned error={params['error'][0]}", flush=True)
        return 1
    if params.get("state", [None])[0] != expected_state:
        print("FAILED: state did not match the one issued with the link. Re-run and "
              "use only the newest link.", flush=True)
        return 1

    flow.fetch_token(code=params["code"][0])
    creds = flow.credentials
    if not creds or not creds.refresh_token:
        print("FAILED: no refresh token returned. Re-run and approve the consent "
              "screen rather than closing it.", flush=True)
        return 1

    TOKEN.write_text(creds.to_json(), encoding="utf-8")
    print(f"SAVED {TOKEN}", flush=True)
    granted = sorted(creds.scopes or [])
    print("GRANTED SCOPES:", flush=True)
    for scope in granted:
        extra = "" if scope in SCOPES else "   <-- broader than this script asked for"
        print(f"  {scope}{extra}", flush=True)

    admin = build("analyticsadmin", "v1beta", credentials=creds, cache_discovery=False)
    summaries = admin.accountSummaries().list(pageSize=200).execute().get("accountSummaries", [])
    total = sum(len(a.get("propertySummaries", [])) for a in summaries)
    print(f"VERIFIED {len(summaries)} account(s), {total} propert(ies)", flush=True)
    for account in summaries:
        print(f"  {account.get('displayName')}", flush=True)
        for prop in account.get("propertySummaries", []):
            print(f"    {prop.get('displayName')}  {prop.get('property')}", flush=True)
    return 0


if __name__ == "__main__":
    sys.exit(main())
