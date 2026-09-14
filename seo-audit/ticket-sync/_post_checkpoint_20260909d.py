import api
status, resp = api.call("POST", "/checkpoint", {"uid": 8795, "date": "Wed, 09 Sep 2026 09:00:54 -0700"})
print(status)
print(resp)
