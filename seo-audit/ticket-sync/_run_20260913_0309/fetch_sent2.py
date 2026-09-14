import imaplib, json, email
from email.header import decode_header, make_header
from email.utils import parsedate_to_datetime

creds = json.load(open(r"C:\Users\yasir\.claude-tools\azwebcorp-creds.json"))
M = imaplib.IMAP4_SSL("imap.secureserver.net", 993)
M.login(creds["mail_user"], creds["mail_password"])
M.select("Sent", readonly=True)

CHECKPOINT_DT = parsedate_to_datetime("Sun, 13 Sep 2026 03:09:35 +0000")

typ, data = M.search(None, 'SINCE "12-Sep-2026"')
uids = data[0].split()

def decode(s):
    if s is None:
        return ""
    try:
        return str(make_header(decode_header(s)))
    except Exception:
        return s

def get_body(msg):
    if msg.is_multipart():
        for part in msg.walk():
            ct = part.get_content_type()
            if ct == "text/plain" and "attachment" not in str(part.get("Content-Disposition") or ""):
                try:
                    return part.get_payload(decode=True).decode(part.get_content_charset() or "utf-8", errors="replace")
                except Exception:
                    continue
        return ""
    else:
        try:
            return msg.get_payload(decode=True).decode(msg.get_content_charset() or "utf-8", errors="replace")
        except Exception:
            return str(msg.get_payload())

results = []
max_dt = CHECKPOINT_DT
max_date_str = None
for uid in uids:
    typ, msgdata = M.fetch(uid, "(RFC822 UID)")
    if not msgdata or msgdata[0] is None:
        continue
    raw = msgdata[0][1]
    msg = email.message_from_bytes(raw)
    date_ = msg.get("Date")
    try:
        msg_dt = parsedate_to_datetime(date_) if date_ else None
    except Exception:
        msg_dt = None
    if msg_dt is None or msg_dt <= CHECKPOINT_DT:
        continue
    results.append({
        "uid": uid.decode(),
        "from": decode(msg.get("From")),
        "to": decode(msg.get("To")),
        "subject": decode(msg.get("Subject")),
        "date": date_,
        "message_id": msg.get("Message-ID"),
        "in_reply_to": msg.get("In-Reply-To"),
        "references": msg.get("References"),
        "body": get_body(msg)[:2000],
    })
    if msg_dt > max_dt:
        max_dt = msg_dt
        max_date_str = date_

print(json.dumps({"new_count": len(results), "messages": results, "max_date": max_date_str}, indent=2, ensure_ascii=False))
M.logout()
