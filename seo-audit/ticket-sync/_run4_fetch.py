import json as _json
_AZWC = _json.load(open(r"C:\Users\yasir\.claude-tools\azwebcorp-creds.json"))
import imaplib
import email
import json
from email.header import decode_header, make_header

HOST = "imap.secureserver.net"
PORT = 993
USER = "info@azwebcorp.com"
PASS = _AZWC["mail_password"]
CHECKPOINT_UID = 4078

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
    return body.strip().replace('\r\n', '\n')[:2000]

def fetch_folder(M, folder):
    M.select(f'"{folder}"', readonly=True)
    typ, data = M.uid('search', None, f'UID {CHECKPOINT_UID+1}:*')
    uids = data[0].split()
    results = []
    max_uid = CHECKPOINT_UID
    max_date = None
    for uid in uids:
        uid_int = int(uid)
        if uid_int <= CHECKPOINT_UID:
            continue
        typ, msg_data = M.uid('fetch', uid, '(RFC822)')
        if not msg_data or msg_data[0] is None:
            continue
        raw = msg_data[0][1]
        msg = email.message_from_bytes(raw)
        date_ = msg.get("Date")
        results.append({
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
        if uid_int > max_uid:
            max_uid = uid_int
            max_date = date_
    return results, max_uid, max_date

M = imaplib.IMAP4_SSL(HOST, PORT)
M.login(USER, PASS)

typ, folders = M.list()
folder_names = []
for line in folders:
    folder_names.append(line.decode(errors='replace'))

inbox_msgs, inbox_max_uid, inbox_max_date = fetch_folder(M, "INBOX")
sent_msgs, sent_max_uid, sent_max_date = fetch_folder(M, "Sent")

M.logout()

out = {
    "folders": folder_names,
    "inbox": {"messages": inbox_msgs, "max_uid": inbox_max_uid, "max_date": inbox_max_date},
    "sent": {"messages": sent_msgs, "max_uid": sent_max_uid, "max_date": sent_max_date},
}
with open("_run4_fetch_out.json", "w", encoding="utf-8") as f:
    json.dump(out, f, indent=2, ensure_ascii=False)
print("inbox new:", len(inbox_msgs), "max_uid", inbox_max_uid)
print("sent new:", len(sent_msgs), "max_uid", sent_max_uid)
