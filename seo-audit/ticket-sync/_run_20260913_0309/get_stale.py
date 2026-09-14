import sys, json
sys.path.insert(0, r"C:\Users\yasir\Documents\Final-Capstone\seo-audit\ticket-sync")
from api import call
status, resp = call("GET", "/stale?hours=48")
print(status)
print(json.dumps(resp, indent=2, ensure_ascii=False))
