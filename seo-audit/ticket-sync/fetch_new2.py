import json as _json  # credentials live outside the repo
_AZWC = _json.load(open(r"C:\Users\yasir\.claude-tools\azwebcorp-creds.json"))
import imaplib
import email
from email.header import decode_header
import sys
import json

CHECKPOINT_UID = 8611

def decode_str(s):
    if not s:
        return ""
    parts = decode_header(s)
    out = ""
    for text, enc in parts:
        if isinstance(text, bytes):
            try:
                out += text.decode(enc or "utf-8", errors="replace")
            except LookupError:
                out += text.decode("utf-8", errors="replace")
        else:
            out += text
    return out

def get_body(msg):
    body = ""
    if msg.is_multipart():
        for part in msg.walk():
            ctype = part.get_content_type()
            cdispo = str(part.get("Content-Disposition") or "")
            if ctype == "text/plain" and "attachment" not in cdispo:
                try:
                    payload = part.get_payload(decode=True)
                    charset = part.get_content_charset() or "utf-8"
                    body += payload.decode(charset, errors="replace")
                except Exception as e:
                    body += f"[error decoding part: {e}]"
    else:
        try:
            payload = msg.get_payload(decode=True)
            charset = msg.get_content_charset() or "utf-8"
            body = payload.decode(charset, errors="replace")
        except Exception as e:
            body = f"[error decoding: {e}]"
    return body[:2000]

def fetch_folder(M, folder, uid_cutoff):
    typ, data = M.select(folder, readonly=True)
    if typ != "OK":
        print(f"Could not select {folder}: {data}", file=sys.stderr)
        return []
    typ, data = M.uid('search', None, f'UID {uid_cutoff+1}:*')
    if typ != "OK":
        print(f"Search failed on {folder}", file=sys.stderr)
        return []
    uids = data[0].split()
    results = []
    for uid in uids:
        uid_int = int(uid)
        if uid_int <= uid_cutoff:
            continue
        typ, msgdata = M.uid('fetch', uid, '(RFC822)')
        if typ != "OK" or not msgdata or msgdata[0] is None:
            continue
        raw = msgdata[0][1]
        msg = email.message_from_bytes(raw)
        entry = {
            "uid": uid_int,
            "folder": folder,
            "from": decode_str(msg.get("From")),
            "to": decode_str(msg.get("To")),
            "subject": decode_str(msg.get("Subject")),
            "date": msg.get("Date"),
            "message_id": msg.get("Message-ID"),
            "in_reply_to": msg.get("In-Reply-To"),
            "references": msg.get("References"),
            "body": get_body(msg),
        }
        results.append(entry)
    return results

M = imaplib.IMAP4_SSL("imap.secureserver.net", 993)
M.login("info@azwebcorp.com", _AZWC["mail_password"])

typ, folders = M.list()
folder_names = []
for f in folders:
    decoded = f.decode() if isinstance(f, bytes) else f
    folder_names.append(decoded)

all_results = {}
for folder in ["INBOX"]:
    all_results[folder] = fetch_folder(M, folder, CHECKPOINT_UID)

M.logout()

with open("new_mail2.json", "w", encoding="utf-8") as f:
    json.dump(all_results, f, indent=2, ensure_ascii=False)

with open("folders2.json", "w", encoding="utf-8") as f:
    json.dump(folder_names, f, indent=2, ensure_ascii=False)

for folder, msgs in all_results.items():
    print(f"{folder}: {len(msgs)} new messages")
