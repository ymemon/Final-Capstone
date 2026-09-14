import json as _json  # credentials live outside the repo
_AZWC = _json.load(open(r"C:\Users\yasir\.claude-tools\azwebcorp-creds.json"))
import imaplib
import email
from email.header import decode_header
import json

def decode_str(s):
    if not s:
        return ""
    parts = decode_header(s)
    out = ""
    for text, enc in parts:
        if isinstance(text, bytes):
            out += text.decode(enc or "utf-8", errors="replace")
        else:
            out += text
    return out

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
    else:
        try:
            body = msg.get_payload(decode=True).decode(msg.get_content_charset() or "utf-8", errors="replace")
        except Exception as e:
            body = f"[error decoding: {e}]"
    return body

CHECKPOINT_UID = 8660
from email.utils import parsedate_to_datetime
import datetime
CHECKPOINT_DT = parsedate_to_datetime("Thu, 3 Sep 2026 12:18:06 -0400")

M = imaplib.IMAP4_SSL("imap.secureserver.net", 993)
M.login("info@azwebcorp.com", _AZWC["mail_password"])

results = {"folders": [], "inbox": [], "sent": []}

typ, folder_data = M.list()
for f in folder_data:
    results["folders"].append(f.decode(errors="replace"))

# INBOX
M.select("INBOX", readonly=True)
typ, data = M.uid('search', None, f'UID {CHECKPOINT_UID+1}:*')
uids = [u for u in data[0].split() if int(u) > CHECKPOINT_UID]
for uid in uids:
    typ, msg_data = M.uid('fetch', uid, '(RFC822)')
    if not msg_data or msg_data[0] is None:
        continue
    raw = msg_data[0][1]
    msg = email.message_from_bytes(raw)
    results["inbox"].append({
        "uid": uid.decode(),
        "from": decode_str(msg.get("From")),
        "to": decode_str(msg.get("To")),
        "subject": decode_str(msg.get("Subject")),
        "date": msg.get("Date"),
        "message_id": msg.get("Message-ID"),
        "in_reply_to": msg.get("In-Reply-To"),
        "references": msg.get("References"),
        "body": get_body(msg)[:2000],
    })

# Sent folder
sel_typ, sel_data = M.select("Sent", readonly=True)
print("Select sent folder:", sel_typ, sel_data)
typ, data2 = M.search(None, 'SINCE "01-Sep-2026"')
sent_uids = data2[0].split()
for uid in sent_uids:
    typ, msg_data = M.fetch(uid, '(RFC822)')
    if not msg_data or msg_data[0] is None:
        continue
    raw = msg_data[0][1]
    msg = email.message_from_bytes(raw)
    try:
        msg_dt = parsedate_to_datetime(msg.get("Date"))
    except Exception:
        msg_dt = None
    if msg_dt and msg_dt <= CHECKPOINT_DT:
        continue
    results["sent"].append({
        "seq": uid.decode(),
        "from": decode_str(msg.get("From")),
        "to": decode_str(msg.get("To")),
        "subject": decode_str(msg.get("Subject")),
        "date": msg.get("Date"),
        "message_id": msg.get("Message-ID"),
        "in_reply_to": msg.get("In-Reply-To"),
        "references": msg.get("References"),
        "body": get_body(msg)[:2000],
    })

M.logout()

with open("run_20260903_sync.json", "w", encoding="utf-8") as f:
    json.dump(results, f, indent=2, ensure_ascii=False)

print("INBOX new:", len(results["inbox"]))
print("SENT candidates:", len(results["sent"]))
