from api import call
status, resp = call("POST", "/checkpoint", {"uid": 8745, "date": "Mon, 07 Sep 2026 08:02:42 -0700"})
print(status)
print(resp)
