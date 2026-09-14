import json
from api import call

payload = json.load(open("_checkpoint_payload.json", encoding="utf-8"))
status, resp = call("POST", "/checkpoint", payload)
print(status)
print(json.dumps(resp, indent=2, ensure_ascii=False))
