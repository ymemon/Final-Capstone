import json as _json  # credentials live outside the repo
_AZWC = _json.load(open(r"C:\Users\yasir\.claude-tools\azwebcorp-creds.json"))
import imaplib
import email
from email.header import decode_header, make_header

HOST = "imap.secureserver.net"
PORT = 993
USER = "info@azwebcorp.com"
PASS = _AZWC["mail_password"]


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
        return ""
    try:
        charset = msg.get_content_charset() or "utf-8"
        return msg.get_payload(decode=True).decode(charset, errors="replace")
    except Exception:
        return str(msg.get_payload())


M = imaplib.IMAP4_SSL(HOST, PORT)
M.login(USER, PASS)

typ, data = M.list()
folders = []
for line in data:
    line = line.decode("utf-8", errors="replace")
    parts = line.split(' "/" ')
    if len(parts) == 2:
        name = parts[1].strip()
    else:
        parts = line.split(' "." ')
        name = parts[1].strip() if len(parts) == 2 else line.split()[-1]
    folders.append(name.strip('"'))

hits = []
for folder in folders:
    if folder.lower() == "drafts":
        continue
    try:
        typ, _ = M.select(f'"{folder}"', readonly=True)
        if typ != "OK":
            continue
    except Exception:
        continue

    typ, data = M.uid("search", None, "TEXT", "linkedin")
    if typ != "OK" or not data[0]:
        continue
    uids = data[0].split()
    for u in uids:
        typ, msg_data = M.uid("fetch", u, "(RFC822)")
        if typ != "OK" or not msg_data or msg_data[0] is None:
            continue
        msg = email.message_from_bytes(msg_data[0][1])
        from_ = decode_val(msg.get("From"))
        if "faraz" not in from_.lower() and "eit.ie" not in from_.lower():
            continue
        subject = decode_val(msg.get("Subject"))
        date = decode_val(msg.get("Date"))
        body = get_body(msg)
        hits.append({
            "folder": folder, "uid": u.decode(), "from": from_,
            "subject": subject, "date": date, "body": body[:3000]
        })

M.logout()

print(f"Found {len(hits)} message(s) from Faraz/eit.ie mentioning 'linkedin':\n")
for h in hits:
    print("=" * 80)
    print(f"Folder: {h['folder']}  UID: {h['uid']}  Date: {h['date']}")
    print(f"From: {h['from']}")
    print(f"Subject: {h['subject']}")
    print("-" * 80)
    print(h["body"].encode("ascii", "replace").decode("ascii"))
    print()
