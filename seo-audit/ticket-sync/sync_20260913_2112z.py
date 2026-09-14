import imaplib, json, email
from email.header import decode_header
from email.utils import getaddresses

creds = json.load(open(r"C:\Users\yasir\.claude-tools\azwebcorp-creds.json"))
M = imaplib.IMAP4_SSL("imap.secureserver.net", 993)
M.login(creds["mail_user"], creds["mail_password"])

def decode_str(s):
    if not s:
        return ""
    parts = decode_header(s)
    out = ""
    for text, enc in parts:
        if isinstance(text, bytes):
            out += text.decode(enc or "utf-8", errors="replace")
        else:
            out += text
    return out

def get_body_snippet(msg, maxlen=800):
    if msg.is_multipart():
        for part in msg.walk():
            ctype = part.get_content_type()
            disp = str(part.get("Content-Disposition") or "")
            if ctype == "text/plain" and "attachment" not in disp:
                try:
                    payload = part.get_payload(decode=True)
                    charset = part.get_content_charset() or "utf-8"
                    return payload.decode(charset, errors="replace")[:maxlen]
                except Exception:
                    continue
        return ""
    else:
        try:
            payload = msg.get_payload(decode=True)
            charset = msg.get_content_charset() or "utf-8"
            return payload.decode(charset, errors="replace")[:maxlen]
        except Exception:
            return ""

checkpoint_uid = 4087

result = {"folders": [], "inbox": [], "sent": []}

typ, data = M.list()
folders = [line.decode() for line in data]
result["folders"] = folders

# INBOX
M.select("INBOX", readonly=True)
typ, data = M.uid('search', None, f'UID {checkpoint_uid+1}:*')
uids = [u for u in data[0].split() if int(u) > checkpoint_uid]
for uid in uids:
    typ, msgdata = M.uid('fetch', uid, '(RFC822)')
    raw = msgdata[0][1]
    msg = email.message_from_bytes(raw)
    result["inbox"].append({
        "uid": uid.decode(),
        "from": decode_str(msg.get("From")),
        "to": decode_str(msg.get("To")),
        "subject": decode_str(msg.get("Subject")),
        "date": msg.get("Date"),
        "message_id": msg.get("Message-ID"),
        "in_reply_to": msg.get("In-Reply-To"),
        "references": msg.get("References"),
        "body": get_body_snippet(msg),
    })
M.close()

# Find a Sent-like folder
sent_folder = None
for line in folders:
    low = line.lower()
    if '"sent' in low or 'sent items' in low or low.endswith('sent"') or ' sent ' in low:
        # extract folder name (last quoted token)
        parts = line.split('"')
        if len(parts) >= 2:
            sent_folder = parts[-2]
        break

result["sent_folder_detected"] = sent_folder

if sent_folder:
    M.select(sent_folder, readonly=True)
    typ, data = M.search(None, 'SINCE', "13-Sep-2026")
    ids = data[0].split() if data and data[0] else []
    for i in ids:
        typ, msgdata = M.fetch(i, '(RFC822)')
        raw = msgdata[0][1]
        msg = email.message_from_bytes(raw)
        result["sent"].append({
            "id": i.decode(),
            "from": decode_str(msg.get("From")),
            "to": decode_str(msg.get("To")),
            "subject": decode_str(msg.get("Subject")),
            "date": msg.get("Date"),
            "message_id": msg.get("Message-ID"),
            "in_reply_to": msg.get("In-Reply-To"),
            "references": msg.get("References"),
            "body": get_body_snippet(msg),
        })
    M.close()

M.logout()

with open("sync_20260913_2112z_out.json", "w", encoding="utf-8") as f:
    json.dump(result, f, indent=2)

print("INBOX new:", len(result["inbox"]))
print("Sent folder detected:", sent_folder)
print("Sent new:", len(result["sent"]))
print("Max inbox uid:", max([int(u["uid"]) for u in result["inbox"]], default=checkpoint_uid))
