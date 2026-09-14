import json as _json  # credentials live outside the repo
_AZWC = _json.load(open(r"C:\Users\yasir\.claude-tools\azwebcorp-creds.json"))
import imaplib
import email
from email.header import decode_header, make_header
import json

def safe(v):
    if v is None:
        return ""
    try:
        return str(make_header(decode_header(v)))
    except Exception:
        return str(v)

def get_body(msg):
    body = ""
    if msg.is_multipart():
        for part in msg.walk():
            ctype = part.get_content_type()
            cdisp = str(part.get("Content-Disposition"))
            if ctype == "text/plain" and "attachment" not in cdisp:
                try:
                    body = part.get_payload(decode=True).decode(part.get_content_charset() or "utf-8", errors="replace")
                except Exception as e:
                    body = f"[error decoding: {e}]"
                break
        if not body:
            for part in msg.walk():
                ctype = part.get_content_type()
                cdisp = str(part.get("Content-Disposition"))
                if ctype == "text/html" and "attachment" not in cdisp:
                    try:
                        body = part.get_payload(decode=True).decode(part.get_content_charset() or "utf-8", errors="replace")
                    except Exception as e:
                        body = f"[error decoding: {e}]"
                    break
    else:
        try:
            body = msg.get_payload(decode=True).decode(msg.get_content_charset() or "utf-8", errors="replace")
        except Exception as e:
            body = f"[error decoding: {e}]"
    return body

M = imaplib.IMAP4_SSL("imap.secureserver.net", 993)
M.login("info@azwebcorp.com", _AZWC["mail_password"])

results = {"folders": [], "inbox": [], "sent": []}

typ, data = M.list()
results["folders"] = [d.decode(errors="replace") for d in data] if data else []

CHECKPOINT_UID = 8634

M.select("INBOX", readonly=True)
typ, data = M.uid('search', None, 'UID', f'{CHECKPOINT_UID+1}:*')
uids = data[0].split()
max_uid = CHECKPOINT_UID
for uid in uids:
    u = int(uid.decode())
    if u <= CHECKPOINT_UID:
        continue
    typ, msg_data = M.uid('fetch', uid, '(RFC822)')
    if not msg_data or msg_data[0] is None:
        continue
    raw = msg_data[0][1]
    msg = email.message_from_bytes(raw)
    max_uid = max(max_uid, u)
    results["inbox"].append({
        "uid": uid.decode(),
        "from": safe(msg.get("From")),
        "to": safe(msg.get("To")),
        "subject": safe(msg.get("Subject")),
        "date": msg.get("Date"),
        "message_id": msg.get("Message-ID"),
        "in_reply_to": msg.get("In-Reply-To"),
        "references": msg.get("References"),
        "body": get_body(msg)[:2000],
    })

results["max_inbox_uid"] = max_uid

try:
    M.select("Sent", readonly=True)
    typ, data = M.uid('search', None, 'SINCE', '01-Sep-2026')
    ids = data[0].split()
    for i in ids:
        typ, msg_data = M.uid('fetch', i, '(RFC822)')
        if not msg_data or msg_data[0] is None:
            continue
        raw = msg_data[0][1]
        msg = email.message_from_bytes(raw)
        results["sent"].append({
            "uid": i.decode(),
            "from": safe(msg.get("From")),
            "to": safe(msg.get("To")),
            "subject": safe(msg.get("Subject")),
            "date": msg.get("Date"),
            "message_id": msg.get("Message-ID"),
            "in_reply_to": msg.get("In-Reply-To"),
            "references": msg.get("References"),
            "body": get_body(msg)[:2000],
        })
except Exception as e:
    results["sent_error"] = str(e)

M.logout()

with open("fetch_run7.json", "w", encoding="utf-8") as f:
    json.dump(results, f, indent=2, ensure_ascii=False)

print("INBOX new:", len(results["inbox"]))
print("max_inbox_uid:", max_uid)
print("SENT:", len(results.get("sent", [])), results.get("sent_error", ""))
