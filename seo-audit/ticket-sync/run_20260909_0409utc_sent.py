import json as _json
_AZWC = _json.load(open(r"C:\Users\yasir\.claude-tools\azwebcorp-creds.json"))
import imaplib, email, json
from email.header import decode_header, make_header
from email.utils import parsedate_to_datetime

HOST = "imap.secureserver.net"
PORT = 993
USER = "info@azwebcorp.com"
PASS = _AZWC["mail_password"]
CHECKPOINT_DT = parsedate_to_datetime("Wed, 9 Sep 2026 02:01:23 -0700")

def decode(s):
    if s is None:
        return ""
    try:
        return str(make_header(decode_header(s)))
    except Exception:
        return s

M = imaplib.IMAP4_SSL(HOST, PORT)
M.login(USER, PASS)
M.select("Sent", readonly=True)
typ, data = M.search(None, 'SINCE "07-Sep-2026"')
uids = data[0].split()
results = []
for uid in uids:
    typ, msg_data = M.fetch(uid, '(RFC822)')
    if not msg_data or msg_data[0] is None:
        continue
    raw = msg_data[0][1]
    msg = email.message_from_bytes(raw)
    date_ = msg.get("Date")
    try:
        msg_dt = parsedate_to_datetime(date_)
    except Exception:
        msg_dt = None
    if msg_dt and msg_dt <= CHECKPOINT_DT:
        continue
    results.append({
        "uid": int(uid),
        "from": decode(msg.get("From")),
        "to": decode(msg.get("To")),
        "subject": decode(msg.get("Subject")),
        "date": date_,
        "message_id": msg.get("Message-ID"),
    })
M.logout()
print(json.dumps({"sent_new_count": len(results), "messages": results}, indent=2, ensure_ascii=False))
