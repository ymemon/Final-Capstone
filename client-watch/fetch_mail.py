import json as _json  # credentials live outside the repo
_AZWC = _json.load(open(r"C:\Users\yasir\.claude-tools\azwebcorp-creds.json"))
import imaplib
import email
import json
import sys
from email.header import decode_header, make_header

HOST = "imap.secureserver.net"
PORT = 993
USER = "info@azwebcorp.com"
PASS = _AZWC["mail_password"]

STATE_PATH = "state.json"

def load_state():
    with open(STATE_PATH, "r", encoding="utf-8") as f:
        return json.load(f)

def decode_val(v):
    if v is None:
        return ""
    try:
        return str(make_header(decode_header(v)))
    except Exception:
        return v

def get_body(msg):
    if msg.is_multipart():
        for part in msg.walk():
            ctype = part.get_content_type()
            disp = str(part.get("Content-Disposition") or "")
            if ctype == "text/plain" and "attachment" not in disp:
                try:
                    charset = part.get_content_charset() or "utf-8"
                    return part.get_payload(decode=True).decode(charset, errors="replace")
                except Exception:
                    continue
        for part in msg.walk():
            ctype = part.get_content_type()
            disp = str(part.get("Content-Disposition") or "")
            if ctype == "text/html" and "attachment" not in disp:
                try:
                    charset = part.get_content_charset() or "utf-8"
                    return part.get_payload(decode=True).decode(charset, errors="replace")
                except Exception:
                    continue
        return ""
    else:
        try:
            charset = msg.get_content_charset() or "utf-8"
            return msg.get_payload(decode=True).decode(charset, errors="replace")
        except Exception:
            return str(msg.get_payload())

def main():
    state = load_state()
    last_uid_by_folder = state.get("last_uid_by_folder", {})

    M = imaplib.IMAP4_SSL(HOST, PORT)
    M.login(USER, PASS)

    typ, data = M.list()
    folders = []
    for line in data:
        line = line.decode("utf-8", errors="replace")
        # parse folder name - last quoted or last token
        parts = line.split(' "/" ')
        if len(parts) == 2:
            name = parts[1].strip()
        else:
            parts = line.split(' "." ')
            name = parts[1].strip() if len(parts) == 2 else line.split()[-1]
        name = name.strip('"')
        folders.append(name)

    results = {}
    for folder in folders:
        if folder.lower() == "drafts":
            continue
        try:
            typ, _ = M.select(f'"{folder}"', readonly=True)
            if typ != "OK":
                continue
        except Exception as e:
            results[folder] = {"error": f"select failed: {e}"}
            continue

        last_uid = last_uid_by_folder.get(folder, 0)
        typ, data = M.uid("search", None, f"UID {last_uid+1}:*")
        if typ != "OK":
            results[folder] = {"error": "search failed"}
            continue
        uids = data[0].split()
        # UID SEARCH with range beyond last can return the last existing uid even if none match; filter
        uids = [u for u in uids if int(u) > last_uid]

        msgs = []
        max_uid = last_uid
        for u in uids:
            typ, msg_data = M.uid("fetch", u, "(RFC822)")
            if typ != "OK" or not msg_data or msg_data[0] is None:
                continue
            raw = msg_data[0][1]
            msg = email.message_from_bytes(raw)
            uid_int = int(u)
            max_uid = max(max_uid, uid_int)
            body = get_body(msg)
            msgs.append({
                "uid": uid_int,
                "from": decode_val(msg.get("From")),
                "to": decode_val(msg.get("To")),
                "subject": decode_val(msg.get("Subject")),
                "date": decode_val(msg.get("Date")),
                "body": body[:8000],
            })
        results[folder] = {"messages": msgs, "max_uid": max_uid, "prior_uid": last_uid}

    M.logout()
    print(json.dumps(results, indent=2, ensure_ascii=False))

if __name__ == "__main__":
    main()
