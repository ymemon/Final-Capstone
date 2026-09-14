from _api import call
status, resp = call("GET", "/stale?hours=48")
print(status)
print(resp)
