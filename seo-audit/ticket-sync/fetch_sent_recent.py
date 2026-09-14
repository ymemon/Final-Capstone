import json as _json  # credentials live outside the repo
_AZWC = _json.load(open(r"C:\Users\yasir\.claude-tools\azwebcorp-creds.json"))
import imaplib
import email
from email.header import decode_header
from email.utils import parsedate_to_datetime

def dec(s):
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

M = imaplib.IMAP4_SSL("imap.secureserver.net", 993)
M.login("info@azwebcorp.com", _AZWC["mail_password"])
M.select("Sent", readonly=True)

typ, data = M.uid('search', None, 'ALL')
uids = data[0].split()
recent = uids[-15:]

for uid in recent:
    typ, msg_data = M.uid('fetch', uid, '(RFC822)')
    if not msg_data or msg_data[0] is None:
        continue
    raw = msg_data[0][1]
    msg = email.message_from_bytes(raw)
    dt = None
    try:
        dt = parsedate_to_datetime(msg.get("Date"))
    except Exception:
        pass
    print("=" * 60)
    print("UID:", uid.decode())
    print("From:", dec(msg.get("From")))
    print("To:", dec(msg.get("To")))
    print("Subject:", dec(msg.get("Subject")))
    print("Date:", msg.get("Date"), "| parsed:", dt)
    print("Message-ID:", msg.get("Message-ID"))

M.logout()
