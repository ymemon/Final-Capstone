import json, imaplib

creds = json.load(open(r"C:\Users\yasir\.claude-tools\azwebcorp-creds.json"))

M = imaplib.IMAP4_SSL("imap.secureserver.net", 993)
M.login(creds["mail_user"], creds["mail_password"])
typ, folders = M.list()
for f in folders:
    print(f.decode() if isinstance(f, bytes) else f)
M.logout()
