import json, urllib.request, urllib.error

creds = json.load(open(r"C:\Users\yasir\.claude-tools\azwebcorp-creds.json"))
key = creds["ticket_api_key"]

req = urllib.request.Request(
    "https://azwebcorp.com/wp-json/azwc-tickets/v1/checkpoint",
    headers={"X-AZWC-Key": key, "User-Agent": "Mozilla/5.0"}
)
try:
    with urllib.request.urlopen(req) as r:
        print(r.status)
        print(r.read().decode())
except urllib.error.HTTPError as e:
    print("HTTP", e.code)
    print(e.read().decode())
