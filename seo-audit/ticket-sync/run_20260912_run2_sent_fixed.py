import json as _json
_AZWC = _json.load(open(r"C:\Users\yasir\.claude-tools\azwebcorp-creds.json"))
import imaplib
import email
import json
from email.header import decode_header, make_header
from email.utils import parsedate_to_datetime
import datetime

CHECKPOINT_DT = parsedate_to_datetime("Sat, 12 Sep 2026 11:41:26 GMT")

def decode(s):
    if s is None:
        return ""
    try:
        return str(make_header(decode_header(s)))
    except Exception:
        return s

M = imaplib.IMAP4_SSL("imap.secureserver.net", 993)
M.login(_AZWC["mail_user"], _AZWC["mail_password"])
M.select("Sent", readonly=True)
typ, data = M.uid('search', None, 'SINCE "10-Sep-2026"')
uids = data[0].split()

results = []
max_internal_dt = None
for uid in uids:
    typ, msg_data = M.uid('fetch', uid, '(RFC822 INTERNALDATE)')
    if not msg_data or msg_data[0] is None:
        continue
    meta = msg_data[0][0].decode(errors='replace')
    # parse INTERNALDATE from the fetch response metadata string
    import re
    m = re.search(r'INTERNALDATE "([^"]+)"', meta)
    internal_dt = None
    if m:
        internal_dt = datetime.datetime.strptime(m.group(1), "%d-%b-%Y %H:%M:%S %z")
    raw = msg_data[0][1]
    msg = email.message_from_bytes(raw)
    if internal_dt and internal_dt <= CHECKPOINT_DT:
        continue
    from_ = decode(msg.get("From"))
    to_ = decode(msg.get("To"))
    subj = decode(msg.get("Subject"))
    date_ = msg.get("Date")
    msgid = msg.get("Message-ID")
    results.append({
        "uid": int(uid),
        "from": from_,
        "to": to_,
        "subject": subj,
        "date_header": date_,
        "internal_date": internal_dt.isoformat() if internal_dt else None,
        "message_id": msgid,
    })
    if internal_dt and (max_internal_dt is None or internal_dt > max_internal_dt):
        max_internal_dt = internal_dt

print(json.dumps({"results": results, "max_internal_dt": max_internal_dt.isoformat() if max_internal_dt else None}, indent=2, ensure_ascii=False))
M.logout()
