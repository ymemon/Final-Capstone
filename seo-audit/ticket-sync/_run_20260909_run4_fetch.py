import json as _json
_AZWC = _json.load(open(r"C:\Users\yasir\.claude-tools\azwebcorp-creds.json"))
import imaplib
import email
import json
from email.header import decode_header, make_header
from email.utils import parsedate_to_datetime

HOST = "imap.secureserver.net"
PORT = 993
USER = "info@azwebcorp.com"
PASS = _AZWC["mail_password"]
CHECKPOINT_UID = 8815
CHECKPOINT_DT = parsedate_to_datetime("Wed, 09 Sep 2026 19:37:08 -0700")

EXCLUDE_FOLDERS = {"drafts"}

def decode(s):
    if s is None:
        return ""
    try:
        return str(make_header(decode_header(s)))
    except Exception:
        return s

def get_body(msg):
    body = ""
    if msg.is_multipart():
        for part in msg.walk():
            ctype = part.get_content_type()
            cdispo = str(part.get('Content-Disposition'))
            if ctype == 'text/plain' and 'attachment' not in cdispo:
                try:
                    charset = part.get_content_charset() or 'utf-8'
                    body = part.get_payload(decode=True).decode(charset, errors='replace')
                except Exception as e:
                    body = f"[decode error: {e}]"
                break
    else:
        try:
            charset = msg.get_content_charset() or 'utf-8'
            body = msg.get_payload(decode=True).decode(charset, errors='replace')
        except Exception as e:
            body = f"[decode error: {e}]"
    return body.strip().replace('\r\n', '\n')[:1500]

def fetch_folder_by_uid(M, folder, checkpoint_uid):
    M.select(folder, readonly=True)
    typ, data = M.uid('search', None, f'UID {checkpoint_uid+1}:*')
    uids = data[0].split()
    results = []
    max_uid = checkpoint_uid
    max_date = None
    for uid in uids:
        uid_int = int(uid)
        if uid_int <= checkpoint_uid:
            continue
        typ, msg_data = M.uid('fetch', uid, '(RFC822)')
        if not msg_data or msg_data[0] is None:
            continue
        raw = msg_data[0][1]
        msg = email.message_from_bytes(raw)
        results.append({
            "uid": uid_int,
            "from": decode(msg.get("From")),
            "to": decode(msg.get("To")),
            "subject": decode(msg.get("Subject")),
            "date": msg.get("Date"),
            "message_id": msg.get("Message-ID"),
            "in_reply_to": msg.get("In-Reply-To"),
            "references": msg.get("References"),
            "body": get_body(msg),
        })
        if uid_int > max_uid:
            max_uid = uid_int
            max_date = msg.get("Date")
    return results, max_uid, max_date

M = imaplib.IMAP4_SSL(HOST, PORT)
M.login(USER, PASS)

typ, folders = M.list()
folder_names = [f.decode() for f in folders]

inbox_msgs, inbox_max_uid, inbox_max_date = fetch_folder_by_uid(M, "INBOX", CHECKPOINT_UID)

sent_folder = None
for name in ["Sent", "Sent Items", "INBOX.Sent"]:
    if any(fn.rstrip().endswith(name) or fn.rstrip().endswith(f'"{name}"') for fn in folder_names):
        sent_folder = name
        break

sent_msgs = []
sent_max_uid = None
sent_max_date = None
if sent_folder:
    M.select(sent_folder, readonly=True)
    typ, data = M.uid('search', None, 'ALL')
    uids = data[0].split()
    sent_max_uid_local = 0
    for uid in uids:
        typ, msg_data = M.uid('fetch', uid, '(RFC822)')
        if not msg_data or msg_data[0] is None:
            continue
        raw = msg_data[0][1]
        msg = email.message_from_bytes(raw)
        date_ = msg.get("Date")
        try:
            msg_dt = parsedate_to_datetime(date_)
        except Exception:
            msg_dt = None
        if msg_dt and CHECKPOINT_DT and msg_dt <= CHECKPOINT_DT:
            continue
        uid_int = int(uid)
        sent_msgs.append({
            "uid": uid_int,
            "from": decode(msg.get("From")),
            "to": decode(msg.get("To")),
            "subject": decode(msg.get("Subject")),
            "date": date_,
            "message_id": msg.get("Message-ID"),
            "in_reply_to": msg.get("In-Reply-To"),
            "references": msg.get("References"),
            "body": get_body(msg),
        })
        if uid_int > sent_max_uid_local:
            sent_max_uid_local = uid_int
            sent_max_date = date_
    sent_max_uid = sent_max_uid_local or None

M.logout()

out = {
    "folder_names": folder_names,
    "sent_folder_used": sent_folder,
    "inbox": {
        "messages": inbox_msgs,
        "max_uid": inbox_max_uid,
        "max_date": inbox_max_date,
    },
    "sent": {
        "messages": sent_msgs,
        "max_uid": sent_max_uid,
        "max_date": sent_max_date,
    },
}

with open("_run_20260909_run4_fetch.json", "w", encoding="utf-8") as f:
    json.dump(out, f, indent=2, ensure_ascii=False)

print(f"INBOX new: {len(inbox_msgs)}, max_uid={inbox_max_uid}")
print(f"SENT ({sent_folder}) new: {len(sent_msgs)}, max_uid={sent_max_uid}")
print("FOLDERS:", folder_names)
