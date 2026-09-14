import json, imaplib, email
from email.header import decode_header, make_header
from email.utils import parsedate_to_datetime

creds = json.load(open(r"C:\Users\yasir\.claude-tools\azwebcorp-creds.json"))

M = imaplib.IMAP4_SSL("imap.secureserver.net", 993)
M.login(creds["mail_user"], creds["mail_password"])
M.select("Sent", readonly=True)

checkpoint_date = parsedate_to_datetime("Mon, 07 Sep 2026 08:02:42 -0700")

typ, data = M.uid('search', None, 'ALL')
uids = data[0].split()

results = []
for uid in uids[-30:]:  # look at the most recent 30 to be safe, filter by date
    typ, msgdata = M.uid('fetch', uid, '(RFC822)')
    if not msgdata or msgdata[0] is None:
        continue
    raw = msgdata[0][1]
    msg = email.message_from_bytes(raw)

    def dec(v):
        if v is None:
            return ""
        try:
            return str(make_header(decode_header(v)))
        except Exception:
            return v

    date_ = msg.get("Date")
    try:
        dt = parsedate_to_datetime(date_)
    except Exception:
        dt = None

    if dt is None or dt <= checkpoint_date:
        continue

    subject = dec(msg.get("Subject"))
    from_ = dec(msg.get("From"))
    to_ = dec(msg.get("To"))
    msgid = msg.get("Message-ID")
    inreplyto = msg.get("In-Reply-To")
    references = msg.get("References")

    body = ""
    if msg.is_multipart():
        for part in msg.walk():
            ctype = part.get_content_type()
            cdispo = str(part.get("Content-Disposition"))
            if ctype == "text/plain" and "attachment" not in cdispo:
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
        "subject": subject,
        "from": from_,
        "to": to_,
        "date": date_,
        "message_id": msgid,
        "in_reply_to": inreplyto,
        "references": references,
        "body_snippet": body[:800]
    })

M.logout()
print(json.dumps(results, indent=2, ensure_ascii=False))
