import json
from api import call

for status_filter in [None, "waiting_us", "waiting_client", "resolved"]:
    path = "/list" if status_filter is None else f"/list?status={status_filter}"
    status, resp = call("GET", path)
    print("===", status_filter, status, "===")
    print(json.dumps(resp, indent=2, ensure_ascii=False))
