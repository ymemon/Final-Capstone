import imaplib, email, json, re, sys
from email.header import decode_header

sys.stdout.reconfigure(encoding="utf-8", errors="replace")

with open(r"C:\Users\yasir\.claude-tools\azwebcorp-creds.json") as f:
    creds = json.load(f)

M = imaplib.IMAP4_SSL("imap.secureserver.net", 993)
M.login(creds["mail_user"], creds["mail_password"])

typ, data = M.list()
print("=== FOLDERS ===")
for line in data:
    print(line.decode(errors="replace"))

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

def fetch_folder(folder, checkpoint_uid):
    M.select(folder, readonly=True)
    typ, data = M.uid('search', None, f'(UID {checkpoint_uid+1}:*)')
    uids = data[0].split()
    print(f"\n=== {folder}: {len(uids)} candidate UIDs ===")
    results = []
    for uid in uids:
        uid_int = int(uid)
        if uid_int <= checkpoint_uid:
            continue
        typ, msg_data = M.uid('fetch', uid, '(RFC822)')
        if not msg_data or not msg_data[0]:
            continue
        raw = msg_data[0][1]
        msg = email.message_from_bytes(raw)
        subject = decode_str(msg.get("Subject"))
        from_ = decode_str(msg.get("From"))
        to_ = decode_str(msg.get("To"))
        date_ = msg.get("Date")
        message_id = msg.get("Message-ID")
        in_reply_to = msg.get("In-Reply-To")
        references = msg.get("References")

        body = ""
        if msg.is_multipart():
            for part in msg.walk():
                ctype = part.get_content_type()
                cdisp = str(part.get("Content-Disposition"))
                if ctype == "text/plain" and "attachment" not in cdisp:
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

        body_snip = re.sub(r'\s+', ' ', body).strip()[:600]

        print(f"\n--- UID {uid_int} ---")
        print(f"From: {from_}")
        print(f"To: {to_}")
        print(f"Subject: {subject}")
        print(f"Date: {date_}")
        print(f"Message-ID: {message_id}")
        print(f"In-Reply-To: {in_reply_to}")
        print(f"References: {references}")
        print(f"Body snippet: {body_snip}")
        results.append(uid_int)
    return results

checkpoint_uid = 8746
inbox_uids = fetch_folder("INBOX", checkpoint_uid)

print("\nMAX_INBOX_UID:", max(inbox_uids) if inbox_uids else "NONE")

M.logout()
