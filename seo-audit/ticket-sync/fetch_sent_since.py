import json as _json  # credentials live outside the repo
_AZWC = _json.load(open(r"C:\Users\yasir\.claude-tools\azwebcorp-creds.json"))
import imaplib
import email
from email.header import decode_header
from email.utils import parsedate_to_datetime

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

CHECKPOINT_DT = parsedate_to_datetime("Thu, 3 Sep 2026 05:43:46 +0000")

M = imaplib.IMAP4_SSL("imap.secureserver.net", 993)
M.login("info@azwebcorp.com", _AZWC["mail_password"])
M.select("Sent", readonly=True)

typ, data = M.search(None, 'SINCE "03-Sep-2026"')
uids = data[0].split()
print(f"Candidates since 03-Sep-2026: {len(uids)}")

found = 0
for uid in uids:
    typ, msg_data = M.fetch(uid, '(RFC822)')
    if not msg_data or msg_data[0] is None:
        continue
    raw = msg_data[0][1]
    msg = email.message_from_bytes(raw)
    try:
        msg_dt = parsedate_to_datetime(msg.get("Date"))
    except Exception:
        msg_dt = None
    if msg_dt and msg_dt <= CHECKPOINT_DT:
        continue
    found += 1
    print("=" * 60)
    print("Seq:", uid.decode())
    print("From:", decode_str(msg.get("From")))
    print("To:", decode_str(msg.get("To")))
    print("Subject:", decode_str(msg.get("Subject")))
    print("Date:", msg.get("Date"))
    print("Message-ID:", msg.get("Message-ID"))

print(f"Total new sent messages: {found}")
M.logout()
