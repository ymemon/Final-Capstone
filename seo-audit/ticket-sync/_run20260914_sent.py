import imaplib, json, email
from email.header import decode_header
from email.utils import parsedate_to_datetime

creds = json.load(open(r"C:\Users\yasir\.claude-tools\azwebcorp-creds.json"))
pw = creds["mail_password"]
M = imaplib.IMAP4_SSL("imap.secureserver.net", 993)
M.login("info@azwebcorp.com", pw)
M.select("Sent", readonly=True)

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

# checkpoint date cutoff
cutoff = parsedate_to_datetime("Sun, 13 Sep 2026 03:09:35 +0000")

typ, data = M.uid('search', None, 'ALL')
uids = data[0].split()
print("total sent uids:", len(uids), "last few:", uids[-10:])

results = []
for uid in uids[-30:]:
    typ, msgdata = M.uid('fetch', uid, '(RFC822)')
    if not msgdata or msgdata[0] is None:
        continue
    raw = msgdata[0][1]
    msg = email.message_from_bytes(raw)
    dt = None
    try:
        dt = parsedate_to_datetime(msg.get("Date"))
    except Exception:
        pass
    if dt and dt.tzinfo is None:
        import datetime
        dt = dt.replace(tzinfo=datetime.timezone.utc)
    if dt and dt <= cutoff:
        continue
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
        "body": body[:1500],
    })

json.dump(results, open("_run20260914_sent_out.json", "w", encoding="utf-8"), indent=2)
print("Wrote", len(results), "messages newer than checkpoint")
M.logout()
