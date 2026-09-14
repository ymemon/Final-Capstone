import json
from api import call

status, resp = call("POST", "/notify-new-tickets", {"tickets": ["AZW-1030"]})
print(status)
print(json.dumps(resp, indent=2, ensure_ascii=False))
