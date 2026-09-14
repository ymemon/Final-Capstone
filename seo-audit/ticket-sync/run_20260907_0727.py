import imaplib, json, email
from email.header import decode_header

with open(r"C:\Users\yasir\.claude-tools\azwebcorp-creds.json") as f:
    creds = json.load(f)

def dec(s):
    if not s:
        return ""
    parts = decode_header(s)
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
            ctype = part.get_content_type()
            disp = str(part.get("Content-Disposition") or "")
            if ctype == "text/plain" and "attachment" not in disp:
                try:
                    return part.get_payload(decode=True).decode(part.get_content_charset() or "utf-8", errors="replace")
                except Exception:
                    return ""
        for part in msg.walk():
            ctype = part.get_content_type()
            disp = str(part.get("Content-Disposition") or "")
            if ctype == "text/html" and "attachment" not in disp:
                try:
                    return part.get_payload(decode=True).decode(part.get_content_charset() or "utf-8", errors="replace")
                except Exception:
                    return ""
        return ""
    else:
        try:
            return msg.get_payload(decode=True).decode(msg.get_content_charset() or "utf-8", errors="replace")
        except Exception:
            return str(msg.get_payload())

M = imaplib.IMAP4_SSL("imap.secureserver.net", 993)
M.login(creds["mail_user"], creds["mail_password"])

print("FOLDERS:")
typ, folders = M.list()
for f in folders:
    print(f.decode(errors="replace"))
print()

CHECKPOINT_UID = 8721

M.select("INBOX", readonly=True)
typ, data = M.uid("search", None, f"UID {CHECKPOINT_UID+1}:*")
uids = [u for u in data[0].split() if int(u) > CHECKPOINT_UID]
print(f"INBOX UIDs found > {CHECKPOINT_UID}: {uids}")

for uid in uids:
    typ, msgdata = M.uid("fetch", uid, "(RFC822)")
    if not msgdata or msgdata[0] is None:
        continue
    raw = msgdata[0][1]
    msg = email.message_from_bytes(raw)
    frm = dec(msg.get("From"))
    to = dec(msg.get("To"))
    subj = dec(msg.get("Subject"))
    date = msg.get("Date")
    mid = msg.get("Message-ID")
    inreply = msg.get("In-Reply-To")
    refs = msg.get("References")
    body = get_body(msg)
    print("=" * 60)
    print(f"UID: {uid.decode()}")
    print(f"From: {frm}")
    print(f"To: {to}")
    print(f"Subject: {subj}")
    print(f"Date: {date}")
    print(f"Message-ID: {mid}")
    print(f"In-Reply-To: {inreply}")
    print(f"References: {refs}")
    print("Body (first 1500 chars):")
    print(body[:1500])
    print()

M.logout()
