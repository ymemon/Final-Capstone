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
CHECKPOINT_UID = 4087
CHECKPOINT_DT = parsedate_to_datetime("Sun, 13 Sep 2026 03:09:35 +0000")

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

def fetch_folder(M, folder, uid_gt=None, dt_gt=None):
    M.select(folder, readonly=True)
    typ, uidnext_data = M.status(folder, '(UIDNEXT)')
    typ, data = M.uid('search', None, 'ALL')
    uids = data[0].split()
    results = []
    max_uid = uid_gt or 0
    max_date = None
    for uid in uids:
        uid_int = int(uid)
        if uid_gt is not None and uid_int <= uid_gt:
            continue
        typ, msg_data = M.uid('fetch', uid, '(RFC822)')
        if not msg_data or msg_data[0] is None:
            continue
        raw = msg_data[0][1]
        msg = email.message_from_bytes(raw)
        date_ = msg.get("Date")
        try:
            msg_dt = parsedate_to_datetime(date_) if date_ else None
        except Exception:
            msg_dt = None
        if dt_gt is not None and msg_dt and msg_dt <= dt_gt:
            continue
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
    return results, max_uid, max_date, uidnext_data[0].decode() if uidnext_data and uidnext_data[0] else None

M = imaplib.IMAP4_SSL(HOST, PORT)
M.login(USER, PASS)
typ, folder_data = M.list()
folders = [f.decode(errors='replace') for f in folder_data]

inbox_msgs, inbox_max_uid, inbox_max_date, inbox_uidnext = fetch_folder(M, "INBOX", uid_gt=CHECKPOINT_UID)

sent_folder = "Sent"
try:
    sent_msgs, sent_max_uid, sent_max_date, sent_uidnext = fetch_folder(M, sent_folder, dt_gt=CHECKPOINT_DT)
    sent_ok = True
except Exception as e:
    sent_msgs, sent_max_uid, sent_max_date, sent_uidnext = [], None, None, None
    sent_ok = False
    sent_err = str(e)

M.logout()

out = {
    "folders": folders,
    "inbox": {"messages": inbox_msgs, "max_uid": inbox_max_uid, "max_date": inbox_max_date, "uidnext": inbox_uidnext},
    "sent": {"messages": sent_msgs, "max_uid": sent_max_uid, "max_date": sent_max_date, "uidnext": sent_uidnext, "ok": sent_ok},
}
with open("_fetch_run_20260913c_out.json", "w", encoding="utf-8") as f:
    json.dump(out, f, indent=2, ensure_ascii=False)
print("inbox new:", len(inbox_msgs), "uidnext:", inbox_uidnext)
print("sent new:", len(sent_msgs), "uidnext:", sent_uidnext, "ok:", sent_ok)
print("folders:", folders)
