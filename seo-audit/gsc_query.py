import json, time, base64, sys
import urllib.request, urllib.parse
from cryptography.hazmat.primitives import hashes, serialization
from cryptography.hazmat.primitives.asymmetric import padding

import os

KEY_PATH = r"C:\Users\yasir\.claude-tools\gsc-reader-key.json"

# Which property to query. Defaults to azwebcorp.com so every existing caller
# keeps working unchanged; override per-run for a client property, e.g.
#     GSC_SITE=https://prestigewindowsaz.com/ python gsc_query.py '{...}'
# The trailing slash matters - Search Console treats the siteUrl as an exact
# string, and "https://example.com" is a different property from
# "https://example.com/". Domain properties take the form "sc-domain:example.com".
SITE = os.environ.get("GSC_SITE", "https://azwebcorp.com/")
if SITE.startswith("http") and not SITE.endswith("/"):
    SITE += "/"

SCOPE = "https://www.googleapis.com/auth/webmasters.readonly"

def b64url(data: bytes) -> str:
    return base64.urlsafe_b64encode(data).rstrip(b"=").decode()

def get_token():
    with open(KEY_PATH) as f:
        key = json.load(f)
    now = int(time.time())
    header = {"alg": "RS256", "typ": "JWT"}
    claims = {
        "iss": key["client_email"],
        "scope": SCOPE,
        "aud": "https://oauth2.googleapis.com/token",
        "iat": now,
        "exp": now + 3600,
    }
    signing_input = b64url(json.dumps(header).encode()) + "." + b64url(json.dumps(claims).encode())
    private_key = serialization.load_pem_private_key(key["private_key"].encode(), password=None)
    signature = private_key.sign(signing_input.encode(), padding.PKCS1v15(), hashes.SHA256())
    jwt = signing_input + "." + b64url(signature)

    data = urllib.parse.urlencode({
        "grant_type": "urn:ietf:params:oauth:grant-type:jwt-bearer",
        "assertion": jwt,
    }).encode()
    req = urllib.request.Request("https://oauth2.googleapis.com/token", data=data)
    with urllib.request.urlopen(req) as resp:
        return json.load(resp)["access_token"]

OAUTH_TOKEN = r"C:\Users\yasir\.claude-tools\gsc-oauth-token.json"


def oauth_token():
    """Access token for Yasir's own Google account.

    The service account (gsc-reader@azwebcorp-gsc-77313) is verified on
    azwebcorp.com only; every client property returns 403 for it. Yasir's
    account is on all of them, which is why the client portal has their data
    and this script did not. Same token file the portal uses.
    """
    from google.oauth2.credentials import Credentials
    from google.auth.transport.requests import Request

    c = Credentials.from_authorized_user_file(OAUTH_TOKEN, [SCOPE])
    if c.expired and c.refresh_token:
        c.refresh(Request())
        with open(OAUTH_TOKEN, "w", encoding="utf-8") as fh:
            fh.write(c.to_json())
    return c.token


def _call(token, site, body):
    url = ("https://www.googleapis.com/webmasters/v3/sites/"
           + urllib.parse.quote(site, safe="") + "/searchAnalytics/query")
    req = urllib.request.Request(url, data=json.dumps(body).encode(), headers={
        "Authorization": "Bearer " + token,
        "Content-Type": "application/json",
    })
    with urllib.request.urlopen(req) as resp:
        return json.load(resp)


def query(token, body):
    """Query one property, falling back to OAuth where the service account cannot.

    The property is taken from the body's siteUrl when present, otherwise from
    GSC_SITE / the default.

    Honouring siteUrl matters. This function used to build the URL from the
    module-level SITE and silently ignore siteUrl in the body, so every caller
    that passed one got azwebcorp.com's figures back regardless of what it
    asked for. That is how three clients' weekly reports would have gone out
    carrying our own numbers under their names.
    """
    body = dict(body)                      # never mutate the caller's dict
    site = body.pop("siteUrl", None) or SITE
    if site.startswith("http") and not site.endswith("/"):
        site += "/"
    try:
        return _call(token, site, body)
    except urllib.error.HTTPError as e:
        if e.code not in (401, 403):
            raise
        return _call(oauth_token(), site, body)

if __name__ == "__main__":
    token = get_token()
    body = json.loads(sys.argv[1]) if len(sys.argv) > 1 else {}
    print(json.dumps(query(token, body), indent=2))
