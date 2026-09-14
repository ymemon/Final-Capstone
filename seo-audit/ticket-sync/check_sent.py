import imaplib, json, email
from email.header import decode_header

creds = json.load(open(r"C:\Users\yasir\.claude-tools\azwebcorp-creds.json"))
M = imaplib.IMAP4_SSL("imap.secureserver.net", 993)
M.login(creds["mail_user"], creds["mail_password"])
M.select("Sent", readonly=True)
typ, data = M.status("Sent", "(MESSAGES UIDNEXT UIDVALIDITY)")
print("Status:", data)

# checkpoint was recorded against INBOX UIDs typically; for Sent we need date-based
# since we have no per-folder checkpoint, use the checkpoint date to filter
typ, data = M.search(None, 'SINCE', "13-Sep-2026")
print("Since checkpoint date:", data)

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

ids = data[0].split()
for i in ids:
    typ, msgdata = M.fetch(i, '(RFC822)')
    raw = msgdata[0][1]
    msg = email.message_from_bytes(raw)
    print("="*80)
    print("From:", decode_str(msg.get("From")))
    print("To:", decode_str(msg.get("To")))
    print("Subject:", decode_str(msg.get("Subject")))
    print("Date:", msg.get("Date"))
    print("Message-ID:", msg.get("Message-ID"))

M.logout()
