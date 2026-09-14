import json as _json
_AZWC = _json.load(open(r"C:\Users\yasir\.claude-tools\azwebcorp-creds.json"))
import imaplib

HOST = "imap.secureserver.net"
PORT = 993
USER = "info@azwebcorp.com"
PASS = _AZWC["mail_password"]

M = imaplib.IMAP4_SSL(HOST, PORT)
M.login(USER, PASS)
M.select("Sent", readonly=True)
typ, data = M.uid('fetch', '1190', '(INTERNALDATE)')
print(data)
M.logout()
