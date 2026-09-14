import sys
sys.path.insert(0, r"C:\Users\yasir\Documents\Final-Capstone\seo-audit\ticket-sync")
from api import call
status, resp = call("POST", "/checkpoint", {"uid": 4087, "date": "Sun, 13 Sep 2026 03:09:35 +0000"})
print(status, resp)
