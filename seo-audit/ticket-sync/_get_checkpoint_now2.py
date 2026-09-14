import json, urllib.request

creds = json.load(open(r"C:\Users\yasir\.claude-tools\azwebcorp-creds.json"))
key = creds["ticket_api_key"]

req = urllib.request.Request(
    "https://azwebcorp.com/wp-json/azwc-tickets/v1/checkpoint",
    headers={"X-AZWC-Key": key, "User-Agent": "Mozilla/5.0 (AZWC-Ticket-Sync)"}
)
try:
    with urllib.request.urlopen(req) as resp:
        print(resp.status)
        print(resp.read().decode())
except urllib.error.HTTPError as e:
    print("HTTP", e.code)
    print(e.read().decode()[:1000])
