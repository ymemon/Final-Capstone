import json, urllib.request, urllib.error

creds = json.load(open(r"C:\Users\yasir\.claude-tools\azwebcorp-creds.json"))
key = creds["ticket_api_key"]

req = urllib.request.Request(
    "https://azwebcorp.com/wp-json/azwc-tickets/v1/stale?hours=48",
    headers={"X-AZWC-Key": key, "User-Agent": "Mozilla/5.0"}
)
try:
    with urllib.request.urlopen(req) as r:
        print(r.status)
        data = r.read().decode()
        print(data)
        with open("_stale_run4_out.json", "w", encoding="utf-8") as f:
            f.write(data)
except urllib.error.HTTPError as e:
    print("HTTP", e.code)
    print(e.read().decode())
