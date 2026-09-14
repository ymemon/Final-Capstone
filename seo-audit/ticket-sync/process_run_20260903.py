import json
from api import call

with open("run_20260903_sync.json", "r", encoding="utf-8") as f:
    data = json.load(f)

print("INBOX new:", len(data["inbox"]))
print("SENT new:", len(data["sent"]))

for msg in data["sent"]:
    to = msg["to"]
    # extract email address
    import re
    m = re.search(r'[\w\.\-+]+@[\w\.\-]+', to)
    to_email = m.group(0) if m else to
    print("---")
    print("To:", to_email, "| Subject:", msg["subject"])
    status, resp = call("POST", "/find", {
        "email": to_email,
        "subject": msg["subject"],
        "message_ids": [msg["message_id"]] if msg["message_id"] else []
    })
    print("find:", status, resp)

status, resp = call("POST", "/checkpoint", {"uid": 8660, "date": "Thu, 3 Sep 2026 09:21:09 -0700"})
print("checkpoint:", status, resp)

status, resp = call("GET", "/stale?hours=48")
print("stale:", status, resp)
