import imaplib, json, email
from email.header import decode_header, make_header
from datetime import datetime, timedelta, timezone

creds = json.load(open(r"C:\Users\yasir\.claude-tools\azwebcorp-creds.json"))
M = imaplib.IMAP4_SSL("imap.secureserver.net", 993)
M.login(creds["mail_user"], creds["mail_password"])
M.select("Sent", readonly=True)

# checkpoint date fallback (Sent folder tracked separately - no UID checkpoint
# for it, so use last run's checkpoint date minus small buffer as safety net)
since_date = (datetime(2026, 9, 12, 11, 41, 26, tzinfo=timezone.utc) - timedelta(hours=1))
since_str = since_date.strftime("%d-%b-%Y")

typ, data = M.search(None, f'(SINCE {since_str})')
uids = data[0].split()

results = []
for uid in uids:
    typ, msgdata = M.fetch(uid, '(RFC822 INTERNALDATE)')
    if not msgdata or msgdata[0] is None:
        continue
    raw = msgdata[0][1]
    msg = email.message_from_bytes(raw)

    def hdr(name):
        v = msg.get(name)
        if v is None:
            return ""
        try:
            return str(make_header(decode_header(v)))
        except Exception:
            return v

    body = ""
    if msg.is_multipart():
        for part in msg.walk():
            ctype = part.get_content_type()
            disp = str(part.get("Content-Disposition"))
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
        "from": hdr("From"),
        "to": hdr("To"),
        "subject": hdr("Subject"),
        "date": hdr("Date"),
        "message_id": hdr("Message-ID"),
        "in_reply_to": hdr("In-Reply-To"),
        "references": hdr("References"),
        "body": body[:1500],
    })

print(json.dumps(results, indent=2, ensure_ascii=False))
M.logout()
