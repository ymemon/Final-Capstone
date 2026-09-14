import json as _json  # credentials live outside the repo
_AZWC = _json.load(open(r"C:\Users\yasir\.claude-tools\azwebcorp-creds.json"))
import imaplib
import email
from email.header import decode_header, make_header
from email.utils import parsedate_to_datetime
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

CHECKPOINT_UID = 4084
CHECKPOINT_DATE_STR = "Sat, 12 Sep 2026 18:15:51 -0400"
checkpoint_dt = parsedate_to_datetime(CHECKPOINT_DATE_STR)

M = imaplib.IMAP4_SSL("imap.secureserver.net", 993)
M.login("info@azwebcorp.com", _AZWC["mail_password"])

results = {"inbox": [], "sent": [], "folders": []}

typ, folder_data = M.list()
for f in folder_data:
    results["folders"].append(f.decode(errors="replace"))

M.select("INBOX", readonly=True)
typ, data = M.uid('search', None, 'UID', f'{CHECKPOINT_UID+1}:*')
uids = data[0].split() if data and data[0] else []
max_uid = CHECKPOINT_UID
max_date = CHECKPOINT_DATE_STR
for uid in uids:
    u = int(uid.decode())
    if u <= CHECKPOINT_UID:
        continue
    typ, msg_data = M.uid('fetch', uid, '(RFC822)')
    if not msg_data or msg_data[0] is None:
        continue
    raw = msg_data[0][1]
    msg = email.message_from_bytes(raw)
    if u > max_uid:
        max_uid = u
        max_date = msg.get("Date")
    results["inbox"].append({
        "uid": uid.decode(),
        "from": safe(msg.get("From")),
        "to": safe(msg.get("To")),
        "subject": safe(msg.get("Subject")),
        "date": msg.get("Date"),
        "message_id": msg.get("Message-ID"),
        "in_reply_to": msg.get("In-Reply-To"),
        "references": msg.get("References"),
        "body": get_body(msg)[:2500],
    })

results["max_inbox_uid"] = max_uid
results["max_inbox_date"] = max_date

try:
    M.select("Sent", readonly=True)
    typ, data = M.uid('search', None, 'UID', '1:*')
    ids = data[0].split() if data and data[0] else []
    for i in ids:
        typ, msg_data = M.uid('fetch', i, '(RFC822)')
        if not msg_data or msg_data[0] is None:
            continue
        raw = msg_data[0][1]
        msg = email.message_from_bytes(raw)
        msg_date_str = msg.get("Date")
        try:
            msg_dt = parsedate_to_datetime(msg_date_str)
            if msg_dt.tzinfo is None:
                continue
            if msg_dt <= checkpoint_dt:
                continue
        except Exception:
            continue
        results["sent"].append({
            "uid": i.decode(),
            "from": safe(msg.get("From")),
            "to": safe(msg.get("To")),
            "subject": safe(msg.get("Subject")),
            "date": msg_date_str,
            "message_id": msg.get("Message-ID"),
            "in_reply_to": msg.get("In-Reply-To"),
            "references": msg.get("References"),
            "body": get_body(msg)[:2500],
        })
except Exception as e:
    results["sent_error"] = str(e)

M.logout()

with open("fetch_run22.json", "w", encoding="utf-8") as f:
    json.dump(results, f, indent=2, ensure_ascii=False)

print("FOLDERS:", results["folders"])
print("INBOX new:", len(results["inbox"]))
print("max_inbox_uid:", max_uid, max_date)
print("SENT new:", len(results.get("sent", [])), results.get("sent_error", ""))
