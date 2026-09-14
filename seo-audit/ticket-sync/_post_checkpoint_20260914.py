from _api import call
status, resp = call("POST", "/checkpoint", {"uid": 4093, "date": "Mon, 14 Sep 2026 05:38:45 -0500 (CDT)"})
print(status)
print(resp)
