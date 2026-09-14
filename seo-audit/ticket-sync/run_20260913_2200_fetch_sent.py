import json, imaplib, email
from email.header import decode_header, make_header
from email.utils import parsedate_to_datetime

creds = json.load(open(r"C:\Users\yasir\.claude-tools\azwebcorp-creds.json"))

M = imaplib.IMAP4_SSL("imap.secureserver.net", 993)
M.login(creds["mail_user"], creds["mail_password"])
M.select("Sent", readonly=True)

CHECKPOINT_DATE = parsedate_to_datetime("Sun, 13 Sep 2026 03:09:35 +0000")

typ, data = M.uid('search', None, 'SINCE', '13-Sep-2026')
uids = data[0].split()

results = []
for uid in uids:
    typ, msgdata = M.uid('fetch', uid, '(RFC822)')
    if not msgdata or msgdata[0] is None:
        continue
    raw = msgdata[0][1]
    msg = email.message_from_bytes(raw)

    try:
        msg_date = parsedate_to_datetime(msg.get("Date"))
    except Exception:
        continue
    if msg_date <= CHECKPOINT_DATE:
        continue

    def dec(v):
        if v is None:
            return None
        try:
            return str(make_header(decode_header(v)))
        except Exception:
            return v

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
    else:
        try:
            body = msg.get_payload(decode=True).decode(msg.get_content_charset() or "utf-8", errors="replace")
        except Exception:
            body = str(msg.get_payload())

    results.append({
        "uid": uid.decode(),
        "from": dec(msg.get("From")),
        "to": dec(msg.get("To")),
        "subject": dec(msg.get("Subject")),
        "date": msg.get("Date"),
        "message_id": msg.get("Message-ID"),
        "in_reply_to": msg.get("In-Reply-To"),
        "references": msg.get("References"),
        "body": body[:2000],
    })

M.logout()
print(json.dumps(results, indent=2, ensure_ascii=False))
