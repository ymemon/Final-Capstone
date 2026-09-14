import json as _json
_AZWC = _json.load(open(r"C:\Users\yasir\.claude-tools\azwebcorp-creds.json"))
import imaplib

M = imaplib.IMAP4_SSL("imap.secureserver.net", 993)
M.login(_AZWC["mail_user"], _AZWC["mail_password"])
M.select("Sent", readonly=True)
typ, data = M.uid('fetch', '1187', '(INTERNALDATE)')
print(data)
M.logout()
