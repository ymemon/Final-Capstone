import json as _json
_AZWC = _json.load(open(r"C:\Users\yasir\.claude-tools\azwebcorp-creds.json"))
import imaplib
import email

M = imaplib.IMAP4_SSL("imap.secureserver.net", 993)
M.login("info@azwebcorp.com", _AZWC["mail_password"])
M.select("Sent", readonly=True)
for uid in [1181, 1187]:
    typ, msg_data = M.fetch(str(uid), '(RFC822 INTERNALDATE)')
    raw = msg_data[0][1]
    msg = email.message_from_bytes(raw)
    print(uid, "Date header:", repr(msg.get("Date")))
    print(uid, "raw internaldate response:", msg_data[0][0])
M.logout()
