import json, imaplib
creds = json.load(open(r"C:\Users\yasir\.claude-tools\azwebcorp-creds.json"))
M = imaplib.IMAP4_SSL("imap.secureserver.net", 993)
M.login(creds["mail_user"], creds["mail_password"])
typ, data = M.status('"INBOX"', "(UIDNEXT MESSAGES)")
print(typ, data)
M.logout()
