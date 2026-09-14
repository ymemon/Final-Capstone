from api import call
status, resp = call("POST", "/checkpoint", {"uid": 4099, "date": "Mon, 14 Sep 2026 08:00:35 -0700"})
print(status)
print(resp)
