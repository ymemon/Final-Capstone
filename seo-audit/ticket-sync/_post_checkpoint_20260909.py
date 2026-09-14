from api import call
status, resp = call("POST", "/checkpoint", {"uid": 8776, "date": "Wed, 9 Sep 2026 02:01:23 -0700"})
print(status)
print(resp)
