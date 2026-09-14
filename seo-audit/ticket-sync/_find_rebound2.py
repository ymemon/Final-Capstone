import json
from api import call

payload = {
    "email": "massage@reboundbodystudio.com",
    "subject": "Re: Rebound Body Studio — one more thing, about what you pay MassageBook",
    "message_ids": ["<178900463857.3076.14997922428455809902@azwebcorp.com>"],
}
status, resp = call("POST", "/find", payload)
print(status)
print(json.dumps(resp, indent=2, ensure_ascii=False))
