import imaplib, json
creds = json.load(open(r"C:\Users\yasir\.claude-tools\azwebcorp-creds.json"))
M = imaplib.IMAP4_SSL("imap.secureserver.net", 993)
M.login(creds["mail_user"], creds["mail_password"])
typ, data = M.list()
out = [x.decode(errors="replace") for x in data]
print(json.dumps(out, indent=2))
M.logout()
