import json, imaplib, email
from email.header import decode_header, make_header
from email.utils import parsedate_to_datetime

creds = json.load(open(r"C:\Users\yasir\.claude-tools\azwebcorp-creds.json"))
m = imaplib.IMAP4_SSL("imap.secureserver.net", 993)
m.login(creds["mail_user"], creds["mail_password"])
m.select("Sent", readonly=True)

CHECKPOINT_DT = parsedate_to_datetime("Wed, 9 Sep 2026 12:25:46 +0000")

def decode(s):
    if s is None:
        return ""
    try:
        return str(make_header(decode_header(s)))
    except Exception:
        return s

typ, data = m.search(None, 'SINCE "09-Sep-2026"')
uids = data[0].split()

results = []
for uid in uids:
    typ, msgdata = m.fetch(uid, '(RFC822)')
    if not msgdata or msgdata[0] is None:
        continue
    raw = msgdata[0][1]
    msg = email.message_from_bytes(raw)
    date_ = msg.get("Date")
    try:
        msg_dt = parsedate_to_datetime(date_)
    except Exception:
        msg_dt = None
    if msg_dt and CHECKPOINT_DT and msg_dt <= CHECKPOINT_DT:
        continue

    body = ""
    if msg.is_multipart():
        for part in msg.walk():
            ct = part.get_content_type()
            cd = str(part.get("Content-Disposition") or "")
            if ct == "text/plain" and "attachment" not in cd:
                try:
                    body = part.get_payload(decode=True).decode(part.get_content_charset() or "utf-8", errors="replace")
                except Exception:
                    pass
                break
    else:
        try:
            body = msg.get_payload(decode=True).decode(msg.get_content_charset() or "utf-8", errors="replace")
        except Exception:
            pass

    results.append({
        "uid": uid.decode(),
        "from": decode(msg.get("From")),
        "to": decode(msg.get("To")),
        "subject": decode(msg.get("Subject")),
        "date": date_,
        "message_id": msg.get("Message-ID"),
        "in_reply_to": msg.get("In-Reply-To"),
        "references": msg.get("References"),
        "body": body[:1500],
    })

m.logout()
print(json.dumps({"count": len(results), "messages": results}, indent=2, ensure_ascii=False))
