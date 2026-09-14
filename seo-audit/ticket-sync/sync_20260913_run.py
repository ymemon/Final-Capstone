import imaplib, json, email
from email.header import decode_header

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

def get_body_snippet(msg, maxlen=1000):
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

sent_folder = "Sent"
M.select(sent_folder, readonly=True)
typ, data = M.uid('search', None, 'ALL')
all_uids = [u.decode() for u in data[0].split()] if data and data[0] else []
# fetch last 40 to scan for anything recent / not yet tracked
recent_uids = all_uids[-40:]
for uid in recent_uids:
    typ, msgdata = M.uid('fetch', uid, '(RFC822)')
    raw = msgdata[0][1]
    msg = email.message_from_bytes(raw)
    result["sent"].append({
        "uid": uid,
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

with open("sync_20260913_run_out.json", "w", encoding="utf-8") as f:
    json.dump(result, f, indent=2)

print("INBOX new:", len(result["inbox"]))
print("Sent scanned:", len(result["sent"]))
print("Max inbox uid:", max([int(u["uid"]) for u in result["inbox"]], default=checkpoint_uid))
