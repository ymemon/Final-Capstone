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
CHECKPOINT_UID = 4078
CHECKPOINT_DT = parsedate_to_datetime("Sat, 12 Sep 2026 08:42:14 +0000")

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

def fetch_inbox(M):
    M.select("INBOX", readonly=True)
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

def fetch_sent(M):
    M.select("Sent", readonly=True)
    typ, data = M.search(None, 'ALL')
    uids = data[0].split()
    results = []
    max_uid = 0
    max_date = None
    for uid in uids[-300:]:
        typ, msg_data = M.fetch(uid, '(RFC822)')
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
for f in folders:
    try:
        decoded = f.decode(errors='replace')
    except Exception:
        decoded = str(f)
    folder_names.append(decoded)

inbox_msgs, inbox_max_uid, inbox_max_date = fetch_inbox(M)
sent_msgs, sent_max_uid, sent_max_date = fetch_sent(M)
M.logout()

out = {
    "folders": folder_names,
    "inbox": {"messages": inbox_msgs, "max_uid": inbox_max_uid, "max_date": inbox_max_date},
    "sent": {"messages": sent_msgs, "max_uid": sent_max_uid, "max_date": sent_max_date},
}
with open("_fetch_run_20260912b_out.json", "w", encoding="utf-8") as f:
    json.dump(out, f, indent=2, ensure_ascii=False)
print("inbox new:", len(inbox_msgs), "sent new:", len(sent_msgs))
print("inbox max uid:", inbox_max_uid, inbox_max_date)
print("sent max uid:", sent_max_uid, sent_max_date)
