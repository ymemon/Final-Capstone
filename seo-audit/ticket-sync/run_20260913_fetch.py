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

def fetch_folder_by_uid(M, folder, uid_gt):
    typ, _ = M.select(folder, readonly=True)
    if typ != 'OK':
        return [], uid_gt, None, "SELECT_FAILED"
    typ, uidnext_data = M.status(folder, '(UIDNEXT)')
    typ, data = M.uid('search', None, f'UID {uid_gt+1}:*')
    uids = [u for u in data[0].split() if int(u) > uid_gt]
    results = []
    max_uid = uid_gt
    max_date = None
    for uid in uids:
        uid_int = int(uid)
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
    return results, max_uid, max_date, uidnext_data[0].decode() if uidnext_data and uidnext_data[0] else None

M = imaplib.IMAP4_SSL(HOST, PORT)
M.login(USER, PASS)
typ, folder_data = M.list()
folders = [f.decode(errors='replace') for f in folder_data]

inbox_msgs, inbox_max_uid, inbox_max_date, inbox_uidnext = fetch_folder_by_uid(M, "INBOX", CHECKPOINT_UID)

sent_folder = None
for f in folders:
    if '"Sent"' in f or 'Sent Items' in f or 'Sent Messages' in f:
        # extract the actual mailbox name (last quoted token)
        import re
        m = re.findall(r'"([^"]*)"', f)
        if m:
            sent_folder = m[-1]
        break

if sent_folder:
    sent_msgs, sent_max_uid, sent_max_date, sent_uidnext = fetch_folder_by_uid(M, sent_folder, 0)
    # filter by date since sent folder has no shared checkpoint UID space
    sent_msgs = [m for m in sent_msgs if (parsedate_to_datetime(m["date"]) if m["date"] else None) and parsedate_to_datetime(m["date"]) > CHECKPOINT_DT]
else:
    sent_msgs, sent_max_uid, sent_max_date, sent_uidnext = [], None, None, None

M.logout()

out = {
    "folders": folders,
    "sent_folder_found": sent_folder,
    "inbox": {"messages": inbox_msgs, "max_uid": inbox_max_uid, "max_date": inbox_max_date, "uidnext": inbox_uidnext},
    "sent": {"messages": sent_msgs, "max_uid": sent_max_uid, "max_date": sent_max_date, "uidnext": sent_uidnext},
}
with open("run_20260913_fetch_out.json", "w", encoding="utf-8") as f:
    json.dump(out, f, indent=2, ensure_ascii=False)
print("inbox new:", len(inbox_msgs), "uidnext:", inbox_uidnext)
print("sent_folder_found:", sent_folder)
print("sent new:", len(sent_msgs))
