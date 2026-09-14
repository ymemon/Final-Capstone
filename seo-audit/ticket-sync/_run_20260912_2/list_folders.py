import json, imaplib

creds = json.load(open(r"C:\Users\yasir\.claude-tools\azwebcorp-creds.json"))
M = imaplib.IMAP4_SSL("imap.secureserver.net", 993)
M.login(creds["mail_user"], creds["mail_password"])
typ, data = M.list()
for line in data:
    print(line.decode(errors="replace"))
M.logout()
