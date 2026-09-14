import json, urllib.request

creds = json.load(open(r"C:\Users\yasir\.claude-tools\azwebcorp-creds.json"))
key = creds["ticket_api_key"]
BASE = "https://azwebcorp.com/wp-json/azwc-tickets/v1"

def call(path, payload=None):
    data = json.dumps(payload).encode() if payload is not None else None
    req = urllib.request.Request(
        BASE + path,
        data=data,
        headers={"X-AZWC-Key": key, "User-Agent": "Mozilla/5.0 (AZWC-TicketSync)", "Content-Type": "application/json"},
        method="POST" if data is not None else "GET"
    )
    with urllib.request.urlopen(req) as resp:
        return json.loads(resp.read().decode())

# re-affirm checkpoint unchanged
r1 = call("/checkpoint", {"uid": 4079, "date": "Sat, 12 Sep 2026 18:11:02 GMT"})
print("checkpoint:", r1)

r2 = call("/stale?hours=48")
print("stale:", json.dumps(r2, indent=2))
