import json as _json
_AZWC = _json.load(open(r"C:\Users\yasir\.claude-tools\azwebcorp-creds.json"))
import imaplib

M = imaplib.IMAP4_SSL("imap.secureserver.net", 993)
M.login("info@azwebcorp.com", _AZWC["mail_password"])
typ, data = M.list()
for line in data:
    print(line.decode(errors='replace'))
M.logout()
