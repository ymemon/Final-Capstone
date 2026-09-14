import api
status, resp = api.call("POST", "/checkpoint", {"uid": 4078, "date": "Sat, 12 Sep 2026 11:41:26 GMT"})
print(status)
print(resp)
