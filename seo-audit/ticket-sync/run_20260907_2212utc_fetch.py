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
CHECKPOINT_UID = 8755
CHECKPOINT_DT = parsedate_to_datetime("Mon, 7 Sep 2026 21:09:00 +0000")

def decode(s):
    if s is None:
        return ""
    try:
        return str(make_header(decode_header(s)))
    except Exception:
        return s

def extract(M, uids, use_uid, filter_by_uid=True, checkpoint_dt=None):
    results = []
    max_uid = CHECKPOINT_UID if filter_by_uid else 0
    for uid in uids:
        uid_int = int(uid)
        if filter_by_uid and uid_int <= CHECKPOINT_UID:
            continue
        if use_uid:
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
            if msg_dt and msg_dt <= checkpoint_dt:
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

    return results, max_uid

M = imaplib.IMAP4_SSL(HOST, PORT)
M.login(USER, PASS)

typ, folders = M.list()
folder_names = [f.decode() for f in folders]

M.select("INBOX", readonly=True)
typ, data = M.uid('search', None, f'UID {CHECKPOINT_UID+1}:*')
uids = data[0].split()
inbox_msgs, inbox_max_uid = extract(M, uids, use_uid=True)

sent_folder = None
for name in ["Sent", "Sent Items", "INBOX.Sent", "Sent Messages"]:
    for fn in folder_names:
        if fn.rstrip().endswith(f'"{name}"') or fn.rstrip().endswith(name):
            sent_folder = name
            break
    if sent_folder:
        break

sent_msgs = []
sent_max_uid = None
if sent_folder:
    M.select(sent_folder, readonly=True)
    since_str = (CHECKPOINT_DT - __import__('datetime').timedelta(days=2)).strftime("%d-%b-%Y")
    typ, data = M.search(None, f'SINCE "{since_str}"')
    uids = data[0].split()
    sent_msgs, sent_max_uid = extract(M, uids, use_uid=False, filter_by_uid=False, checkpoint_dt=CHECKPOINT_DT)

M.logout()

out = {
    "folder_names": folder_names,
    "sent_folder_used": sent_folder,
    "inbox": {
        "messages": inbox_msgs,
        "max_uid": inbox_max_uid,
    },
    "sent": {
        "messages": sent_msgs,
        "max_uid": sent_max_uid,
    },
}

with open("run_20260907_2212utc_fetch.json", "w", encoding="utf-8") as f:
    json.dump(out, f, indent=2, ensure_ascii=False)

print(f"Folders: {folder_names}")
print(f"Sent folder used: {sent_folder}")
print(f"INBOX new: {len(inbox_msgs)}, max_uid={inbox_max_uid}")
print(f"SENT new: {len(sent_msgs)}, max_uid={sent_max_uid}")
