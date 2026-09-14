import imaplib, json

creds = json.load(open(r"C:\Users\yasir\.claude-tools\azwebcorp-creds.json"))
M = imaplib.IMAP4_SSL("imap.secureserver.net", 993)
M.login(creds["mail_user"], creds["mail_password"])
typ, data = M.select("INBOX", readonly=True)
print("Select INBOX:", data)
typ, data = M.status("INBOX", "(UIDNEXT UIDVALIDITY MESSAGES)")
print("Status:", data)
M.logout()
