import json
import sys
sys.path.insert(0, r"C:\Users\yasir\Documents\Final-Capstone\seo-audit\ticket-sync")
from api import call

payload = json.load(open(r"C:\Users\yasir\Documents\Final-Capstone\seo-audit\ticket-sync\_checkpoint_payload_run2.json"))
status, resp = call("POST", "/checkpoint", payload)
print(status)
print(json.dumps(resp, indent=2))
