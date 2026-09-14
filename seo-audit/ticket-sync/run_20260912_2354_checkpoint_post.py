import json as _json
_AZWC = _json.load(open(r"C:\Users\yasir\.claude-tools\azwebcorp-creds.json"))
import json
import urllib.request

BASE = "https://azwebcorp.com/wp-json/azwc-tickets/v1"
KEY = _AZWC["ticket_api_key"]

payload = {"uid": 4085, "date": "Sat, 12 Sep 2026 23:56:19 GMT"}
req = urllib.request.Request(
    BASE + "/checkpoint",
    data=json.dumps(payload).encode("utf-8"),
    headers={"X-AZWC-Key": KEY, "Content-Type": "application/json; charset=utf-8", "User-Agent": "curl/8.4.0"},
    method="POST",
)
with urllib.request.urlopen(req) as resp:
    print(resp.status)
    print(resp.read().decode())
