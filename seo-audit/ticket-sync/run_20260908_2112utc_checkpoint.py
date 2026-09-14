import api
status, resp = api.call("POST", "/checkpoint", {"uid": 8758, "date": "Mon, 07 Sep 2026 18:30:29 -0700"})
print(status, resp)
