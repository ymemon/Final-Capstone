import json
import urllib.request

creds = json.load(open(r"C:\Users\yasir\.claude-tools\azwebcorp-creds.json"))
KEY = creds["ticket_api_key"]
BASE = "https://azwebcorp.com/wp-json/azwc-tickets/v1"

req = urllib.request.Request(f"{BASE}/checkpoint", headers={"X-AZWC-Key": KEY})
with urllib.request.urlopen(req) as r:
    print(r.read().decode())
