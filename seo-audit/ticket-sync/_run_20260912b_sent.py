import json, imaplib, email
from email.header import decode_header
from email.utils import parsedate_to_datetime

creds = json.load(open(r"C:\Users\yasir\.claude-tools\azwebcorp-creds.json"))
CHECKPOINT_DATE = parsedate_to_datetime("Sat, 12 Sep 2026 19:51:07 +0000")

def decode(s):
    if not s:
        return ""
    parts = decode_header(s)
    out = []
    for text, enc in parts:
        if isinstance(text, bytes):
            out.append(text.decode(enc or "utf-8", errors="replace"))
        else:
            out.append(text)
    return "".join(out)

M = imaplib.IMAP4_SSL("imap.secureserver.net", 993)
M.login(creds["mail_user"], creds["mail_password"])
M.select('"Sent"', readonly=True)
typ, data = M.uid("search", None, "SINCE", "12-Sep-2026")
uids = data[0].split() if data and data[0] else []
print("candidate uids:", uids)

out = []
for uid in uids:
    typ, msgdata = M.uid("fetch", uid, "(RFC822)")
    raw = msgdata[0][1]
    msg = email.message_from_bytes(raw)
    try:
        d = parsedate_to_datetime(msg.get("Date"))
    except Exception:
        continue
    if d <= CHECKPOINT_DATE:
        continue
    body = ""
    if msg.is_multipart():
        for part in msg.walk():
            if part.get_content_type() == "text/plain" and not part.get("Content-Disposition"):
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
    out.append({
        "uid": uid.decode(),
        "from": decode(msg.get("From")),
        "to": decode(msg.get("To")),
        "subject": decode(msg.get("Subject")),
        "date": msg.get("Date"),
        "message_id": msg.get("Message-ID"),
        "in_reply_to": msg.get("In-Reply-To"),
        "references": msg.get("References"),
        "body": body[:2000],
    })

with open("_run_20260912b_sent_out.json", "w", encoding="utf-8") as f:
    json.dump(out, f, indent=2, ensure_ascii=False)
print("new sent since checkpoint:", len(out))
M.logout()
