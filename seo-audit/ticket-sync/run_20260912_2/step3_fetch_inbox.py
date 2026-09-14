import json, imaplib, email, re
from email.header import decode_header, make_header
from email.utils import parsedate_to_datetime

creds = json.load(open(r"C:\Users\yasir\.claude-tools\azwebcorp-creds.json"))
CHECKPOINT_DT = parsedate_to_datetime("Sat, 12 Sep 2026 11:41:26 GMT")
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
        if not body:
            for part in msg.walk():
                ctype = part.get_content_type()
                if ctype == 'text/html':
                    try:
                        charset = part.get_content_charset() or 'utf-8'
                        raw = part.get_payload(decode=True).decode(charset, errors='replace')
                        body = re.sub('<[^<]+?>', ' ', raw)
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

M = imaplib.IMAP4_SSL("imap.secureserver.net", 993)
M.login("info@azwebcorp.com", creds["mail_password"])
M.select("INBOX", readonly=True)
typ, data = M.search(None, 'ALL')
uids = data[0].split()

results = []
max_uid = CHECKPOINT_UID
max_date = CHECKPOINT_DT
id_re = re.compile(rb'INTERNALDATE "([^"]+)"')

for uid in uids:
    uid_int = int(uid)
    if uid_int <= CHECKPOINT_UID:
        continue
    typ, msg_data = M.fetch(uid, '(INTERNALDATE RFC822)')
    if not msg_data or msg_data[0] is None:
        continue
    header_bytes = msg_data[0][0]
    m = id_re.search(header_bytes)
    internal_dt = None
    if m:
        from datetime import datetime
        internal_dt = datetime.strptime(m.group(1).decode(), "%d-%b-%Y %H:%M:%S %z")
    raw = msg_data[0][1]
    msg = email.message_from_bytes(raw)

    results.append({
        "uid": uid_int,
        "internaldate": internal_dt.isoformat() if internal_dt else None,
        "from": decode(msg.get("From")),
        "to": decode(msg.get("To")),
        "subject": decode(msg.get("Subject")),
        "date": msg.get("Date"),
        "message_id": msg.get("Message-ID"),
        "in_reply_to": msg.get("In-Reply-To"),
        "references": msg.get("References"),
        "body": get_body(msg),
    })
    if internal_dt and internal_dt > max_date:
        max_date = internal_dt
    if uid_int > max_uid:
        max_uid = uid_int

M.logout()

out = {"messages": results, "max_uid": max_uid, "max_date": max_date.isoformat()}
with open("step3_inbox_out.json", "w", encoding="utf-8") as f:
    json.dump(out, f, indent=2, ensure_ascii=False)
print("inbox new count:", len(results))
print("max_uid:", max_uid, "max_date:", max_date.isoformat())
