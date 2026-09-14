import json as _json
_AZWC = _json.load(open(r"C:\Users\yasir\.claude-tools\azwebcorp-creds.json"))
import imaplib

HOST = "imap.secureserver.net"
PORT = 993
USER = "info@azwebcorp.com"
PASS = _AZWC["mail_password"]

M = imaplib.IMAP4_SSL(HOST, PORT)
M.login(USER, PASS)
typ, data = M.list()
for line in data:
    print(line.decode(errors="replace"))
M.logout()
