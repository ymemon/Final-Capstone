import json, imaplib, email
from email.header import decode_header, make_header
from email.utils import parsedate_to_datetime

creds = json.load(open(r"C:\Users\yasir\.claude-tools\azwebcorp-creds.json"))

M = imaplib.IMAP4_SSL("imap.secureserver.net", 993)
M.login(creds["mail_user"], creds["mail_password"])

def dec(v):
    if v is None:
        return ""
    try:
        return str(make_header(decode_header(v)))
    except Exception:
        return v

# INBOX check: anything with UID > 8745?
M.select("INBOX", readonly=True)
typ, data = M.uid('search', None, 'UID', '8746:*')
uids = [u for u in data[0].split() if int(u) > 8745]
print("INBOX new UIDs beyond 8745:", uids)

for uid in uids:
    typ, msgdata = M.uid('fetch', uid, '(RFC822)')
    raw = msgdata[0][1]
    msg = email.message_from_bytes(raw)
    print("  UID", uid.decode(), "|", dec(msg.get("From")), "|", dec(msg.get("Subject")), "|", msg.get("Date"))

# Sent check: anything after checkpoint date?
checkpoint_date = parsedate_to_datetime("Mon, 07 Sep 2026 08:02:42 -0700")
typ, folders = M.list()
sent_folder = None
for f in folders:
    fdec = f.decode() if isinstance(f, bytes) else f
    if "Sent" in fdec:
        sent_folder = fdec
print("Folders with 'Sent':", sent_folder)

M.select("Sent", readonly=True)
typ, data = M.uid('search', None, 'ALL')
uids = data[0].split()
new_sent = []
for uid in uids[-10:]:
    typ, msgdata = M.uid('fetch', uid, '(RFC822)')
    if not msgdata or msgdata[0] is None:
        continue
    raw = msgdata[0][1]
    msg = email.message_from_bytes(raw)
    date_ = msg.get("Date")
    try:
        dt = parsedate_to_datetime(date_)
    except Exception:
        continue
    if dt > checkpoint_date:
        new_sent.append((uid.decode(), dec(msg.get("From")), dec(msg.get("To")), dec(msg.get("Subject")), date_))

print("Sent messages newer than checkpoint date:")
for row in new_sent:
    print(" ", row)

M.logout()
