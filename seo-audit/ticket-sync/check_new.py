import json as _json
_AZWC = _json.load(open(r"C:\Users\yasir\.claude-tools\azwebcorp-creds.json"))
import imaplib
import email
from email.header import decode_header, make_header

HOST = "imap.secureserver.net"
PORT = 993
USER = "info@azwebcorp.com"
PASS = _AZWC["mail_password"]
CHECKPOINT_UID = 8690

def decode(s):
    if s is None:
        return ""
    try:
        return str(make_header(decode_header(s)))
    except Exception:
        return s

M = imaplib.IMAP4_SSL(HOST, PORT)
M.login(USER, PASS)

typ, folders = M.list()
folder_names = [f.decode() for f in folders]

def check_folder(folder, use_uid_checkpoint):
    M.select(folder, readonly=True)
    typ, data = M.uid('search', None, 'ALL')
    uids = data[0].split()
    print(f"{folder}: total uids={len(uids)}, uidnext-ish max={uids[-1] if uids else None}")
    new = []
    for uid in uids:
        uid_int = int(uid)
        if use_uid_checkpoint and uid_int <= CHECKPOINT_UID:
            continue
        typ, msg_data = M.uid('fetch', uid, '(RFC822)')
        if not msg_data or msg_data[0] is None:
            continue
        raw = msg_data[0][1]
        msg = email.message_from_bytes(raw)
        new.append({
            "uid": uid_int,
            "from": decode(msg.get("From")),
            "to": decode(msg.get("To")),
            "subject": decode(msg.get("Subject")),
            "date": msg.get("Date"),
            "message_id": msg.get("Message-ID"),
        })
    return new

inbox_new = check_folder("INBOX", True)
print("INBOX new since checkpoint:", len(inbox_new))
for m in inbox_new:
    print(m)

# find sent-like folder
sent_folder = None
for name in ["Sent", "Sent Items", "INBOX.Sent", "INBOX/Sent"]:
    for fn in folder_names:
        if f'"{name}"' in fn or fn.rstrip().endswith(name):
            sent_folder = name
            break
    if sent_folder:
        break

print("folder_names:", folder_names)
print("sent_folder guess:", sent_folder)

if sent_folder:
    sent_new = check_folder(sent_folder, False)
    print("SENT total fetched (no uid checkpoint, will filter by date):", len(sent_new))
    for m in sent_new[-10:]:
        print(m)

M.logout()
