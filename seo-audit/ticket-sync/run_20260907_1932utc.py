import json, imaplib, email
from email.header import decode_header, make_header

creds = json.load(open(r"C:\Users\yasir\.claude-tools\azwebcorp-creds.json"))
pw = creds["mail_password"]

M = imaplib.IMAP4_SSL("imap.secureserver.net", 993)
M.login("info@azwebcorp.com", pw)

typ, folders = M.list()
folder_names = []
for f in folders:
    line = f.decode(errors="replace")
    # folder name is the last quoted or bare token
    parts = line.split(' "/" ')
    if len(parts) == 2:
        name = parts[1].strip().strip('"')
    else:
        name = line.rsplit(' ', 1)[-1].strip('"')
    folder_names.append(name)

print("FOLDERS:", folder_names)

CHECKPOINT_UID = 8740

def dh(s):
    if not s:
        return ""
    try:
        return str(make_header(decode_header(s)))
    except Exception:
        return s

# INBOX
M.select("INBOX", readonly=True)
typ, data = M.uid("search", None, f"UID {CHECKPOINT_UID+1}:*")
uids = data[0].split() if data[0] else []
print("INBOX candidate UIDs:", uids)

results = []
for uid in uids:
    uid_i = int(uid)
    if uid_i <= CHECKPOINT_UID:
        continue
    typ, msg_data = M.uid("fetch", uid, "(RFC822)")
    if not msg_data or not msg_data[0]:
        continue
    raw = msg_data[0][1]
    msg = email.message_from_bytes(raw)
    body = ""
    if msg.is_multipart():
        for part in msg.walk():
            if part.get_content_type() == "text/plain" and not part.get("Content-Disposition"):
                try:
                    body = part.get_payload(decode=True).decode(part.get_content_charset() or "utf-8", errors="replace")
                except Exception:
                    pass
                break
    else:
        try:
            body = msg.get_payload(decode=True).decode(msg.get_content_charset() or "utf-8", errors="replace")
        except Exception:
            body = str(msg.get_payload())

    results.append({
        "uid": uid_i,
        "from": dh(msg.get("From")),
        "to": dh(msg.get("To")),
        "subject": dh(msg.get("Subject")),
        "date": msg.get("Date"),
        "message_id": msg.get("Message-ID"),
        "in_reply_to": msg.get("In-Reply-To"),
        "references": msg.get("References"),
        "body_snippet": body[:1500],
    })

print(json.dumps({"inbox": results}, indent=2, ensure_ascii=False))

M.logout()
