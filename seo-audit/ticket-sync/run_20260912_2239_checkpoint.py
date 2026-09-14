import json, urllib.request

with open(r"C:\Users\yasir\.claude-tools\azwebcorp-creds.json") as f:
    creds = json.load(f)

req = urllib.request.Request(
    "https://azwebcorp.com/wp-json/azwc-tickets/v1/checkpoint",
    headers={"X-AZWC-Key": creds["ticket_api_key"]},
)
with urllib.request.urlopen(req) as resp:
    print(resp.read().decode())
