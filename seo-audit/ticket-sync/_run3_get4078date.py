import json as _json
_AZWC = _json.load(open(r"C:\Users\yasir\.claude-tools\azwebcorp-creds.json"))
import imaplib, email

M = imaplib.IMAP4_SSL("imap.secureserver.net", 993)
M.login("info@azwebcorp.com", _AZWC["mail_password"])
M.select('"INBOX"', readonly=True)
typ, msg_data = M.uid('fetch', '4078', '(RFC822)')
raw = msg_data[0][1]
msg = email.message_from_bytes(raw)
print(repr(msg.get("Date")))
M.logout()
