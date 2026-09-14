import imaplib, json, email
from email.header import decode_header
from email.utils import parsedate_to_datetime

creds = json.load(open(r"C:\Users\yasir\.claude-tools\azwebcorp-creds.json"))
M = imaplib.IMAP4_SSL("imap.secureserver.net", 993)
M.login(creds["mail_user"], creds["mail_password"])
M.select("Sent", readonly=True)

typ, data = M.uid("search", None, "ALL")
uids = data[0].split()

def dec(s):
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

cutoff = parsedate_to_datetime("Mon, 14 Sep 2026 08:00:35 -0700")

results = []
for uid in uids[-60:]:
    typ, msgdata = M.uid("fetch", uid, "(RFC822)")
    if not msgdata or msgdata[0] is None:
        continue
    raw = msgdata[0][1]
    msg = email.message_from_bytes(raw)
    d = msg.get("Date")
    try:
        dt = parsedate_to_datetime(d)
    except Exception:
        continue
    if dt is None or dt.tzinfo is None:
        continue
    if dt <= cutoff:
        continue
    body = ""
    if msg.is_multipart():
        for part in msg.walk():
            if part.get_content_type() == "text/plain" and not part.get_filename():
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
        "cc": dec(msg.get("Cc")),
        "subject": dec(msg.get("Subject")),
        "date": d,
        "message_id": msg.get("Message-ID"),
        "in_reply_to": msg.get("In-Reply-To"),
        "references": msg.get("References"),
        "body": body[:1500],
    })

print(json.dumps(results, indent=2, ensure_ascii=False))
M.logout()
