import json, urllib.request, urllib.error

creds = json.load(open(r"C:\Users\yasir\.claude-tools\azwebcorp-creds.json"))
key = creds["ticket_api_key"]

payload = json.dumps({"uid": 4080, "date": "Sat, 12 Sep 2026 19:37:31 +0000"}).encode()
req = urllib.request.Request(
    "https://azwebcorp.com/wp-json/azwc-tickets/v1/checkpoint",
    data=payload,
    method="POST",
    headers={"X-AZWC-Key": key, "User-Agent": "Mozilla/5.0", "Content-Type": "application/json"}
)
try:
    with urllib.request.urlopen(req) as r:
        print(r.status)
        print(r.read().decode())
except urllib.error.HTTPError as e:
    print("HTTP", e.code)
    print(e.read().decode())
