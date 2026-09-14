import json as _json
_AZWC = _json.load(open(r"C:\Users\yasir\.claude-tools\azwebcorp-creds.json"))
import imaplib
import email
from email.header import decode_header, make_header

HOST = "imap.secureserver.net"
PORT = 993
USER = "info@azwebcorp.com"
PASS = _AZWC["mail_password"]
CHECKPOINT_UID = 8828

def dh(v):
    if v is None:
        return ""
    try:
        return str(make_header(decode_header(v)))
    except Exception:
        return v

M = imaplib.IMAP4_SSL(HOST, PORT)
M.login(USER, PASS)
M.select("INBOX", readonly=True)

typ, data = M.uid('search', None, f'UID {CHECKPOINT_UID+1}:*')
uids = data[0].split()

results = []
max_uid = CHECKPOINT_UID
max_date = None

for uid in uids:
    uid_i = int(uid)
    if uid_i <= CHECKPOINT_UID:
        continue
    typ, msg_data = M.uid('fetch', uid, '(RFC822)')
    if not msg_data or msg_data[0] is None:
        continue
    raw = msg_data[0][1]
    msg = email.message_from_bytes(raw)

    body = ""
    if msg.is_multipart():
        for part in msg.walk():
            ctype = part.get_content_type()
            disp = str(part.get("Content-Disposition") or "")
            if ctype == "text/plain" and "attachment" not in disp:
                try:
                    body = part.get_payload(decode=True).decode(part.get_content_charset() or "utf-8", errors="replace")
                except Exception:
                    pass
                break
        if not body:
            for part in msg.walk():
                ctype = part.get_content_type()
                disp = str(part.get("Content-Disposition") or "")
                if ctype == "text/html" and "attachment" not in disp:
                    try:
                        body = part.get_payload(decode=True).decode(part.get_content_charset() or "utf-8", errors="replace")
                    except Exception:
                        pass
                    break
    else:
        try:
            body = msg.get_payload(decode=True).decode(msg.get_content_charset() or "utf-8", errors="replace")
        except Exception:
            body = str(msg.get_payload())

    results.append({
        "uid": uid_i,
        "from": dh(msg.get("From")),
        "to": dh(msg.get("To")),
        "subject": dh(msg.get("Subject")),
        "date": msg.get("Date"),
        "message_id": msg.get("Message-ID"),
        "in_reply_to": msg.get("In-Reply-To"),
        "references": msg.get("References"),
        "body": body[:3000],
    })
    if uid_i > max_uid:
        max_uid = uid_i
        max_date = msg.get("Date")

M.logout()

import json
with open("_run_20260910_inbox.json", "w", encoding="utf-8") as f:
    json.dump({"count": len(results), "max_uid": max_uid, "max_date": max_date, "messages": results}, f, indent=2, ensure_ascii=False)

print(f"count={len(results)} max_uid={max_uid} max_date={max_date}")
