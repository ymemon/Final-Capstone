from api import call
status, resp = call("POST", "/checkpoint", {"uid": 8782, "date": "Wed, 9 Sep 2026 12:25:46 +0000"})
print(status, resp)
