import json
import sys
from api import call

method = sys.argv[1]
path = sys.argv[2]
payload = None
if len(sys.argv) > 3:
    with open(sys.argv[3], "r", encoding="utf-8") as f:
        payload = json.load(f)
status, resp = call(method, path, payload)
print(status)
print(json.dumps(resp, indent=2, ensure_ascii=False))
