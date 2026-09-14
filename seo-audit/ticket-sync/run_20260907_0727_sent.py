import imaplib, json, email
from email.header import decode_header
from email.utils import parsedate_to_datetime

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
        return ""
    else:
        try:
            return msg.get_payload(decode=True).decode(msg.get_content_charset() or "utf-8", errors="replace")
        except Exception:
            return str(msg.get_payload())

CHECKPOINT_DT = parsedate_to_datetime("Mon, 7 Sep 2026 09:40:24 +0000")

M = imaplib.IMAP4_SSL("imap.secureserver.net", 993)
M.login(creds["mail_user"], creds["mail_password"])
M.select("Sent", readonly=True)

typ, data = M.search(None, 'SINCE "06-Sep-2026"')
uids = data[0].split()
print(f"Sent candidate UIDs since 06-Sep-2026: {len(uids)}")

for uid in uids:
    typ, msgdata = M.fetch(uid, "(RFC822)")
    if not msgdata or msgdata[0] is None:
        continue
    raw = msgdata[0][1]
    msg = email.message_from_bytes(raw)
    date_ = msg.get("Date")
    try:
        msg_dt = parsedate_to_datetime(date_)
    except Exception:
        msg_dt = None
    if msg_dt and msg_dt <= CHECKPOINT_DT:
        continue
    frm = dec(msg.get("From"))
    to = dec(msg.get("To"))
    subj = dec(msg.get("Subject"))
    mid = msg.get("Message-ID")
    inreply = msg.get("In-Reply-To")
    refs = msg.get("References")
    body = get_body(msg)
    print("=" * 60)
    print(f"UID: {uid.decode()}")
    print(f"From: {frm}")
    print(f"To: {to}")
    print(f"Subject: {subj}")
    print(f"Date: {date_}")
    print(f"Message-ID: {mid}")
    print(f"In-Reply-To: {inreply}")
    print(f"References: {refs}")
    print("Body (first 800 chars):")
    print(body[:800])
    print()

M.logout()
