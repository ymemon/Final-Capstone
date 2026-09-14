import json, urllib.request, urllib.error

creds = json.load(open(r"C:\Users\yasir\.claude-tools\azwebcorp-creds.json"))
KEY = creds["ticket_api_key"]
BASE = "https://azwebcorp.com/wp-json/azwc-tickets/v1"

def call(method, path, body=None):
    data = json.dumps(body).encode() if body is not None else None
    req = urllib.request.Request(
        BASE + path,
        data=data,
        method=method,
        headers={
            "X-AZWC-Key": KEY,
            "User-Agent": "Mozilla/5.0",
            "Content-Type": "application/json",
        },
    )
    try:
        with urllib.request.urlopen(req) as r:
            return r.status, json.loads(r.read().decode())
    except urllib.error.HTTPError as e:
        return e.code, e.read().decode()

if __name__ == "__main__":
    import sys
    method = sys.argv[1]
    path = sys.argv[2]
    body = json.loads(sys.argv[3]) if len(sys.argv) > 3 else None
    status, resp = call(method, path, body)
    print(status)
    print(json.dumps(resp, indent=2) if isinstance(resp, (dict, list)) else resp)
