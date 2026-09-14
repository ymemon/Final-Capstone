import json
from api import call

payload = {"uid": 8814, "date": "Wed, 09 Sep 2026 18:43:58 -0700"}
status, resp = call("POST", "/checkpoint", payload)
print(status)
print(json.dumps(resp, indent=2, ensure_ascii=False))
