import json, imaplib, email
from email.header import decode_header, make_header

creds = json.load(open(r"C:\Users\yasir\.claude-tools\azwebcorp-creds.json"))
M = imaplib.IMAP4_SSL("imap.secureserver.net", 993)
M.login(creds["mail_user"], creds["mail_password"])
M.select("INBOX", readonly=True)

CHECKPOINT_UID = 8763

typ, data = M.uid("search", None, f"UID {CHECKPOINT_UID+1}:*")
uids = data[0].split()
# de-dup in case checkpoint uid itself gets returned by some servers
uids = [u for u in uids if int(u) > CHECKPOINT_UID]

results = []
for u in uids:
    typ, msgdata = M.uid("fetch", u, "(RFC822)")
    if not msgdata or msgdata[0] is None:
        continue
    raw = msgdata[0][1]
    msg = email.message_from_bytes(raw)

    def dh(val):
        if val is None:
            return ""
        try:
            return str(make_header(decode_header(val)))
        except Exception:
            return val

    subject = dh(msg.get("Subject"))
    from_ = dh(msg.get("From"))
    to_ = dh(msg.get("To"))
    date_ = msg.get("Date")
    message_id = msg.get("Message-ID")
    in_reply_to = msg.get("In-Reply-To")
    references = msg.get("References")

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
        "uid": u.decode(),
        "from": from_,
        "to": to_,
        "subject": subject,
        "date": date_,
        "message_id": message_id,
        "in_reply_to": in_reply_to,
        "references": references,
        "body_excerpt": body[:1500],
    })

M.logout()
print(json.dumps(results, indent=2, ensure_ascii=False))
