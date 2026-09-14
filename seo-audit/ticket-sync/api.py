import json as _json  # credentials live outside the repo
_AZWC = _json.load(open(r"C:\Users\yasir\.claude-tools\azwebcorp-creds.json"))
import json
import sys
import urllib.request

BASE = "https://azwebcorp.com/wp-json/azwc-tickets/v1"
KEY = _AZWC["ticket_api_key"]

def call(method, path, payload=None):
    url = BASE + path
    data = None
    headers = {"X-AZWC-Key": KEY, "User-Agent": "curl/8.4.0"}
    if payload is not None:
        data = json.dumps(payload).encode("utf-8")
        headers["Content-Type"] = "application/json; charset=utf-8"
    req = urllib.request.Request(url, data=data, headers=headers, method=method)
    try:
        with urllib.request.urlopen(req) as resp:
            body = resp.read().decode("utf-8")
            return resp.status, json.loads(body) if body else {}
    except urllib.error.HTTPError as e:
        body = e.read().decode("utf-8")
        try:
            return e.code, json.loads(body) if body else {}
        except json.JSONDecodeError:
            return e.code, {"raw": body}

if __name__ == "__main__":
    method = sys.argv[1]
    path = sys.argv[2]
    payload = json.loads(sys.argv[3]) if len(sys.argv) > 3 else None
    status, resp = call(method, path, payload)
    print(status)
    print(json.dumps(resp, indent=2, ensure_ascii=False))
