import json as _json  # credentials live outside the repo
_AZWC = _json.load(open(r"C:\Users\yasir\.claude-tools\azwebcorp-creds.json"))
import imaplib
import email
from email.header import decode_header
import json
import sys

HOST = "imap.secureserver.net"
PORT = 993
USER = "info@azwebcorp.com"
PASSWORD = _AZWC["mail_password"]
CHECKPOINT_UID = 8642

def decode_str(s):
    if s is None:
        return ""
    parts = decode_header(s)
    out = ""
    for text, enc in parts:
        if isinstance(text, bytes):
            try:
                out += text.decode(enc or "utf-8", errors="replace")
            except Exception:
                out += text.decode("utf-8", errors="replace")
        else:
            out += text
    return out

def get_body_snippet(msg, max_len=1500):
    body = ""
    if msg.is_multipart():
        for part in msg.walk():
            ctype = part.get_content_type()
            cdispo = str(part.get("Content-Disposition") or "")
            if ctype == "text/plain" and "attachment" not in cdispo:
                try:
                    payload = part.get_payload(decode=True)
                    charset = part.get_content_charset() or "utf-8"
                    body = payload.decode(charset, errors="replace")
                    break
                except Exception:
                    continue
        if not body:
            for part in msg.walk():
                ctype = part.get_content_type()
                cdispo = str(part.get("Content-Disposition") or "")
                if ctype == "text/html" and "attachment" not in cdispo:
                    try:
                        payload = part.get_payload(decode=True)
                        charset = part.get_content_charset() or "utf-8"
                        body = payload.decode(charset, errors="replace")
                        break
                    except Exception:
                        continue
    else:
        try:
            payload = msg.get_payload(decode=True)
            charset = msg.get_content_charset() or "utf-8"
            body = payload.decode(charset, errors="replace")
        except Exception:
            body = str(msg.get_payload())
    return body[:max_len]

def main():
    result = {"folders": [], "inbox_messages": [], "sent_messages": [], "errors": []}

    try:
        M = imaplib.IMAP4_SSL(HOST, PORT)
        M.login(USER, PASSWORD)
    except Exception as e:
        result["errors"].append(f"IMAP login failed: {e}")
        print(json.dumps(result, indent=2))
        return

    try:
        typ, folder_data = M.list()
        folders = []
        for f in folder_data:
            if f:
                folders.append(f.decode(errors="replace"))
        result["folders"] = folders
    except Exception as e:
        result["errors"].append(f"LIST failed: {e}")

    # Identify inbox and sent folder names, skipping Drafts entirely
    import re
    inbox_name = "INBOX"
    sent_name = None
    for line in result["folders"]:
        m = re.match(r'^\(([^)]*)\)\s+"([^"]+)"\s+(.+)$', line.strip())
        if not m:
            continue
        flags, delim, name = m.group(1), m.group(2), m.group(3)
        name = name.strip()
        if name.startswith('"') and name.endswith('"'):
            name = name[1:-1]
        if "\\Drafts" in flags:
            continue
        if "\\Sent" in flags:
            sent_name = name

    result["inbox_folder_used"] = inbox_name
    result["sent_folder_used"] = sent_name

    # Fetch INBOX messages with UID > checkpoint
    try:
        typ, _ = M.select("INBOX", readonly=True)
        typ, uid_data = M.uid('search', None, f'(UID {CHECKPOINT_UID+1}:*)')
        uids = uid_data[0].split() if uid_data and uid_data[0] else []
        for uid in uids:
            uid_int = int(uid)
            if uid_int <= CHECKPOINT_UID:
                continue
            typ, msg_data = M.uid('fetch', uid, '(RFC822)')
            if not msg_data or not msg_data[0]:
                continue
            raw = msg_data[0][1]
            msg = email.message_from_bytes(raw)
            entry = {
                "uid": uid_int,
                "from": decode_str(msg.get("From")),
                "to": decode_str(msg.get("To")),
                "subject": decode_str(msg.get("Subject")),
                "date": msg.get("Date"),
                "message_id": msg.get("Message-ID"),
                "in_reply_to": msg.get("In-Reply-To"),
                "references": msg.get("References"),
                "body_snippet": get_body_snippet(msg),
            }
            result["inbox_messages"].append(entry)
    except Exception as e:
        result["errors"].append(f"INBOX fetch failed: {e}")

    # Fetch Sent messages if sent folder found
    if sent_name:
        try:
            typ, _ = M.select(f'"{sent_name}"', readonly=True)
            typ, uid_data = M.uid('search', None, 'ALL')
            uids = uid_data[0].split() if uid_data and uid_data[0] else []
            # Only look at recent ones - last 200 to bound cost, then filter by date later in analysis
            uids = uids[-200:]
            for uid in uids:
                typ, msg_data = M.uid('fetch', uid, '(RFC822)')
                if not msg_data or not msg_data[0]:
                    continue
                raw = msg_data[0][1]
                msg = email.message_from_bytes(raw)
                entry = {
                    "uid": int(uid),
                    "from": decode_str(msg.get("From")),
                    "to": decode_str(msg.get("To")),
                    "subject": decode_str(msg.get("Subject")),
                    "date": msg.get("Date"),
                    "message_id": msg.get("Message-ID"),
                    "in_reply_to": msg.get("In-Reply-To"),
                    "references": msg.get("References"),
                    "body_snippet": get_body_snippet(msg),
                }
                result["sent_messages"].append(entry)
        except Exception as e:
            result["errors"].append(f"Sent fetch failed: {e}")

    M.logout()
    print(json.dumps(result, indent=2))

if __name__ == "__main__":
    main()
