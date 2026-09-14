import json, imaplib, email
from email.header import decode_header

creds = json.load(open(r"C:\Users\yasir\.claude-tools\azwebcorp-creds.json"))
m = imaplib.IMAP4_SSL("imap.secureserver.net", 993)
m.login(creds["mail_user"], creds["mail_password"])
m.select("INBOX", readonly=True)

CHECKPOINT_UID = 8786

typ, data = m.uid('search', None, f'UID {CHECKPOINT_UID+1}:*')
uids = data[0].split()

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
    if msg.is_multipart():
        for part in msg.walk():
            ct = part.get_content_type()
            cd = str(part.get("Content-Disposition") or "")
            if ct == "text/plain" and "attachment" not in cd:
                try:
                    return part.get_payload(decode=True).decode(part.get_content_charset() or "utf-8", errors="replace")
                except Exception:
                    pass
        for part in msg.walk():
            ct = part.get_content_type()
            if ct == "text/html":
                try:
                    return part.get_payload(decode=True).decode(part.get_content_charset() or "utf-8", errors="replace")
                except Exception:
                    pass
        return ""
    else:
        try:
            return msg.get_payload(decode=True).decode(msg.get_content_charset() or "utf-8", errors="replace")
        except Exception:
            return str(msg.get_payload())

results = []
maxuid = CHECKPOINT_UID
maxdate = None
for uid in uids:
    iuid = int(uid.decode())
    if iuid <= CHECKPOINT_UID:
        continue
    typ, msgdata = m.uid('fetch', uid, '(RFC822)')
    if not msgdata or msgdata[0] is None:
        continue
    raw = msgdata[0][1]
    msg = email.message_from_bytes(raw)
    body = get_body(msg)
    entry = {
        "uid": uid.decode(),
        "from": decode_str(msg.get("From")),
        "to": decode_str(msg.get("To")),
        "subject": decode_str(msg.get("Subject")),
        "date": msg.get("Date"),
        "message_id": msg.get("Message-ID"),
        "in_reply_to": msg.get("In-Reply-To"),
        "references": msg.get("References"),
        "body": body[:2000],
    }
    results.append(entry)
    if iuid > maxuid:
        maxuid = iuid
        maxdate = msg.get("Date")

m.logout()

print(json.dumps({"count": len(results), "maxuid": maxuid, "maxdate": maxdate, "messages": results}, indent=2, ensure_ascii=False))
