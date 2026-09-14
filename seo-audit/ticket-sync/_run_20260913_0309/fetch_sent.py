import imaplib, json, email
from email.header import decode_header
import datetime

creds = json.load(open(r"C:\Users\yasir\.claude-tools\azwebcorp-creds.json"))
M = imaplib.IMAP4_SSL("imap.secureserver.net", 993)
M.login(creds["mail_user"], creds["mail_password"])
M.select("Sent", readonly=True)

# checkpoint date reference
CHECKPOINT_DATE = datetime.datetime(2026, 9, 13, 3, 9, 35)

typ, data = M.uid("search", None, "ALL")
uids = data[0].split()
print("max uid in Sent:", uids[-1] if uids else None, "count:", len(uids))

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
        return ""
    else:
        try:
            return msg.get_payload(decode=True).decode(msg.get_content_charset() or "utf-8", errors="replace")
        except Exception:
            return str(msg.get_payload())

# fetch the last 15 by UID and filter by date > checkpoint in python (INTERNALDATE search on this host is unreliable per prior runs)
last_uids = uids[-15:] if len(uids) > 15 else uids
results = []
for uid in last_uids:
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
        "body": body[:2000],
    })

print(json.dumps(results, indent=2, ensure_ascii=False))
M.logout()
