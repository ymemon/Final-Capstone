import json as _json
_AZWC = _json.load(open(r"C:\Users\yasir\.claude-tools\azwebcorp-creds.json"))
import imaplib
import email
import json
from email.header import decode_header, make_header
from email.utils import parsedate_to_datetime

HOST = "imap.secureserver.net"
PORT = 993
USER = "info@azwebcorp.com"
PASS = _AZWC["mail_password"]
CHECKPOINT_DT = parsedate_to_datetime("Sat, 12 Sep 2026 15:40:46 GMT")

def decode(s):
    if s is None:
        return ""
    try:
        return str(make_header(decode_header(s)))
    except Exception:
        return s

def get_body(msg):
    body = ""
    if msg.is_multipart():
        for part in msg.walk():
            ctype = part.get_content_type()
            cdispo = str(part.get('Content-Disposition'))
            if ctype == 'text/plain' and 'attachment' not in cdispo:
                try:
                    charset = part.get_content_charset() or 'utf-8'
                    body = part.get_payload(decode=True).decode(charset, errors='replace')
                except Exception as e:
                    body = f"[decode error: {e}]"
                break
    else:
        try:
            charset = msg.get_content_charset() or 'utf-8'
            body = msg.get_payload(decode=True).decode(charset, errors='replace')
        except Exception as e:
            body = f"[decode error: {e}]"
    return body.strip().replace('\r\n', '\n')[:2000]

M = imaplib.IMAP4_SSL(HOST, PORT)
M.login(USER, PASS)
M.select('"Sent"', readonly=True)
typ, data = M.search(None, 'ALL')
uids = data[0].split()
print("total sent messages:", len(uids), "last uid:", uids[-1] if uids else None)

results = []
max_uid = 0
max_date = None
# check the most recent 50 to be safe
for uid in uids[-50:]:
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
    uid_int = int(uid)
    if msg_dt and msg_dt <= CHECKPOINT_DT:
        continue
    results.append({
        "uid": uid_int,
        "from": decode(msg.get("From")),
        "to": decode(msg.get("To")),
        "subject": decode(msg.get("Subject")),
        "date": date_,
        "message_id": msg.get("Message-ID"),
        "in_reply_to": msg.get("In-Reply-To"),
        "references": msg.get("References"),
        "body": get_body(msg),
    })
    if uid_int > max_uid:
        max_uid = uid_int
        max_date = date_

M.logout()
out = {"messages": results, "max_uid": max_uid, "max_date": max_date}
with open("_run3_sent_out.json", "w", encoding="utf-8") as f:
    json.dump(out, f, indent=2, ensure_ascii=False)
print("sent new:", len(results))
