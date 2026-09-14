import json as _json  # credentials live outside the repo
_AZWC = _json.load(open(r"C:\Users\yasir\.claude-tools\azwebcorp-creds.json"))
import imaplib
import email
from email.header import decode_header, make_header
import sys

def safe_print(*args):
    text = " ".join(str(a) for a in args)
    print(text.encode('ascii', errors='replace').decode('ascii'))

M = imaplib.IMAP4_SSL("imap.secureserver.net", 993)
M.login("info@azwebcorp.com", _AZWC["mail_password"])
M.select("INBOX", readonly=True)

typ, data = M.uid('search', None, 'UID', '8449:*')
uids = data[0].split()
safe_print("UIDs found:", uids)

for uid in uids:
    typ, msg_data = M.uid('fetch', uid, '(RFC822)')
    if not msg_data or msg_data[0] is None:
        continue
    raw = msg_data[0][1]
    msg = email.message_from_bytes(raw)
    def dec(v):
        if v is None:
            return ""
        return str(make_header(decode_header(v)))
    safe_print("=====")
    safe_print("UID:", uid.decode())
    safe_print("From:", dec(msg.get("From")))
    safe_print("To:", dec(msg.get("To")))
    safe_print("Subject:", dec(msg.get("Subject")))
    safe_print("Date:", msg.get("Date"))
    safe_print("Message-ID:", msg.get("Message-ID"))
    safe_print("In-Reply-To:", msg.get("In-Reply-To"))
    safe_print("References:", msg.get("References"))
    body = ""
    if msg.is_multipart():
        for part in msg.walk():
            ctype = part.get_content_type()
            cdisp = str(part.get("Content-Disposition"))
            if ctype == "text/plain" and "attachment" not in cdisp:
                try:
                    body = part.get_payload(decode=True).decode(part.get_content_charset() or "utf-8", errors="replace")
                except Exception as e:
                    body = f"[error decoding: {e}]"
                break
    else:
        try:
            body = msg.get_payload(decode=True).decode(msg.get_content_charset() or "utf-8", errors="replace")
        except Exception as e:
            body = f"[error decoding: {e}]"
    safe_print("Body (first 1000 chars):")
    safe_print(body[:1000])
    safe_print()

M.logout()
