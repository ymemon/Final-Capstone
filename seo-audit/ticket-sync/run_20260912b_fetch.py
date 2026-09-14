import json, imaplib, email
from email.header import decode_header

creds = json.load(open(r"C:\Users\yasir\.claude-tools\azwebcorp-creds.json"))

CHECKPOINT_UID = 4081

def decode(s):
    if not s:
        return ""
    parts = decode_header(s)
    out = []
    for text, enc in parts:
        if isinstance(text, bytes):
            out.append(text.decode(enc or "utf-8", errors="replace"))
        else:
            out.append(text)
    return "".join(out)

M = imaplib.IMAP4_SSL("imap.secureserver.net", 993)
M.login(creds["mail_user"], creds["mail_password"])

typ, folders = M.list()
folder_names = []
for f in folders:
    folder_names.append(f.decode(errors="replace"))

result = {"folders": folder_names, "inbox": [], "sent": []}

def fetch_folder(name, uid_gt=None):
    typ, _ = M.select(f'"{name}"', readonly=True)
    if typ != "OK":
        return None
    if uid_gt:
        typ, data = M.uid("search", None, f"UID {uid_gt+1}:*")
    else:
        typ, data = M.uid("search", None, "ALL")
    if typ != "OK" or not data or not data[0]:
        return []
    uids = data[0].split()
    msgs = []
    for uid in uids:
        typ, msgdata = M.uid("fetch", uid, "(RFC822)")
        if typ != "OK":
            continue
        raw = msgdata[0][1]
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
        msgs.append({
            "uid": uid.decode(),
            "from": decode(msg.get("From")),
            "to": decode(msg.get("To")),
            "subject": decode(msg.get("Subject")),
            "date": msg.get("Date"),
            "message_id": msg.get("Message-ID"),
            "in_reply_to": msg.get("In-Reply-To"),
            "references": msg.get("References"),
            "body": body[:3000],
        })
    return msgs

result["inbox"] = fetch_folder("INBOX", uid_gt=CHECKPOINT_UID)

with open("run_20260912b_out.json", "w", encoding="utf-8") as f:
    json.dump(result, f, indent=2, ensure_ascii=False)

print("INBOX new:", len(result["inbox"]))
print("FOLDERS:")
for fn in folder_names:
    print(fn)

M.logout()
