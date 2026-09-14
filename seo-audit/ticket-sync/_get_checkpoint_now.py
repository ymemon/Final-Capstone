import json, urllib.request
creds = json.load(open(r"C:\Users\yasir\.claude-tools\azwebcorp-creds.json"))
req = urllib.request.Request(
    "https://azwebcorp.com/wp-json/azwc-tickets/v1/checkpoint",
    headers={"X-AZWC-Key": creds["ticket_api_key"]}
)
with urllib.request.urlopen(req) as r:
    print(r.read().decode())
