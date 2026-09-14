import json
from api import call

payload = {
    "email": "massage@reboundbodystudio.com",
    "name": "Randi (Rebound Body Studio)",
    "subject": "Re: Rebound Body Studio — one more thing, about what you pay MassageBook",
    "direction": "outbound",
    "message_id": "<178900463857.3076.14997922428455809902@azwebcorp.com>",
}
status, resp = call("POST", "/create", payload)
print(status)
print(json.dumps(resp, indent=2, ensure_ascii=False))
