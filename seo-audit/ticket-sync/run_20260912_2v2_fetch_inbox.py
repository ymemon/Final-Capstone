import imaplib, json, email
from email.header import decode_header, make_header

creds = json.load(open(r"C:\Users\yasir\.claude-tools\azwebcorp-creds.json"))
M = imaplib.IMAP4_SSL("imap.secureserver.net", 993)
M.login(creds["mail_user"], creds["mail_password"])
M.select("INBOX", readonly=True)

CHECKPOINT_UID = 4078

typ, data = M.uid('search', None, f'UID {CHECKPOINT_UID+1}:*')
uids = data[0].split()

results = []
for uid in uids:
    typ, msgdata = M.uid('fetch', uid, '(RFC822)')
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

    # get body text
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
        if not body:
            for part in msg.walk():
                if part.get_content_type() == "text/html":
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
        "body": body[:2000],
    })

print(json.dumps(results, indent=2, ensure_ascii=False))
M.logout()
