import json as _json  # credentials live outside the repo
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
CHECKPOINT_UID = 8719
CHECKPOINT_DT = parsedate_to_datetime("Mon, 7 Sep 2026 07:30:30 +0000")

def decode(s):
    if s is None:
        return ""
    try:
        return str(make_header(decode_header(s)))
    except Exception:
        return s

def fetch_folder(M, folder, checkpoint_uid=None, checkpoint_dt=None):
    M.select(folder, readonly=True)
    if checkpoint_uid is not None:
        typ, data = M.uid('search', None, f'UID {checkpoint_uid+1}:*')
    else:
        typ, data = M.search(None, 'SINCE "05-Sep-2026"')
    uids = data[0].split()
    results = []
    max_uid = checkpoint_uid or 0
    max_date = None
    for uid in uids:
        uid_int = int(uid)
        if checkpoint_uid is not None and uid_int <= checkpoint_uid:
            continue
        if checkpoint_uid is not None:
            typ, msg_data = M.uid('fetch', uid, '(RFC822)')
        else:
            typ, msg_data = M.fetch(uid, '(RFC822)')
        if not msg_data or msg_data[0] is None:
            continue
        raw = msg_data[0][1]
        msg = email.message_from_bytes(raw)
        from_ = decode(msg.get("From"))
        to_ = decode(msg.get("To"))
        subj = decode(msg.get("Subject"))
        date_ = msg.get("Date")
        msgid = msg.get("Message-ID")
        inreply = msg.get("In-Reply-To")
        refs = msg.get("References")

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

        body_snip = body.strip().replace('\r\n', '\n')[:1500]

        if checkpoint_dt is not None:
            try:
                msg_dt = parsedate_to_datetime(date_)
            except Exception:
                msg_dt = None
            if msg_dt and checkpoint_dt and msg_dt <= checkpoint_dt:
                continue

        results.append({
            "uid": uid_int,
            "from": from_,
            "to": to_,
            "subject": subj,
            "date": date_,
            "message_id": msgid,
            "in_reply_to": inreply,
            "references": refs,
            "body": body_snip,
        })

        if uid_int > max_uid:
            max_uid = uid_int
            max_date = date_

    return results, max_uid, max_date

M = imaplib.IMAP4_SSL(HOST, PORT)
M.login(USER, PASS)

typ, folders = M.list()
folder_names = [f.decode() for f in folders]

inbox_msgs, inbox_max_uid, inbox_max_date = fetch_folder(M, "INBOX", checkpoint_uid=CHECKPOINT_UID)

sent_folder = None
for name in ["Sent", "Sent Items", "INBOX.Sent"]:
    if any(fn.rstrip().endswith(name) or fn.rstrip().endswith(f'"{name}"') for fn in folder_names):
        sent_folder = name
        break

sent_msgs = []
sent_max_uid = None
sent_max_date = None
if sent_folder:
    sent_msgs, sent_max_uid, sent_max_date = fetch_folder(M, sent_folder, checkpoint_dt=CHECKPOINT_DT)

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

with open("sync_20260907_run2.json", "w", encoding="utf-8") as f:
    json.dump(out, f, indent=2, ensure_ascii=False)

print(f"INBOX new: {len(inbox_msgs)}, max_uid={inbox_max_uid}")
print(f"SENT ({sent_folder}) new: {len(sent_msgs)}, max_uid={sent_max_uid}")
