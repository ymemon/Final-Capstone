import imaplib, json, email
from email.header import decode_header

creds = json.load(open(r"C:\Users\yasir\.claude-tools\azwebcorp-creds.json"))
M = imaplib.IMAP4_SSL("imap.secureserver.net", 993)
M.login(creds["mail_user"], creds["mail_password"])
M.select("INBOX", readonly=True)

CHECKPOINT_UID = 4087

typ, data = M.uid("search", None, f"UID {CHECKPOINT_UID+1}:*")
uids = data[0].split()

def dh(v):
    if not v:
        return ""
    parts = decode_header(v)
    out = []
    for text, enc in parts:
        if isinstance(text, bytes):
            out.append(text.decode(enc or "utf-8", errors="replace"))
        else:
            out.append(text)
    return "".join(out)

def get_body(msg):
    if msg.is_multipart():
        for part in msg.walk():
            ct = part.get_content_type()
            if ct == "text/plain" and "attachment" not in str(part.get("Content-Disposition") or ""):
                try:
                    return part.get_payload(decode=True).decode(part.get_content_charset() or "utf-8", errors="replace")
                except Exception:
                    continue
        for part in msg.walk():
            ct = part.get_content_type()
            if ct == "text/html" and "attachment" not in str(part.get("Content-Disposition") or ""):
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
if uids and uids != [b'']:
    for uid in uids:
        typ, msgdata = M.uid("fetch", uid, "(RFC822)")
        raw = msgdata[0][1]
        msg = email.message_from_bytes(raw)
        body = get_body(msg)
        results.append({
            "uid": uid.decode(),
            "from": dh(msg.get("From")),
            "to": dh(msg.get("To")),
            "subject": dh(msg.get("Subject")),
            "date": msg.get("Date"),
            "message_id": msg.get("Message-ID"),
            "in_reply_to": msg.get("In-Reply-To"),
            "references": msg.get("References"),
            "body": body[:3000],
        })

print(json.dumps(results, indent=2, ensure_ascii=False))
M.logout()
