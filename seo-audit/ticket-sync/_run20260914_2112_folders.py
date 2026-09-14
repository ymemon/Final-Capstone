import imaplib, json
creds = json.load(open(r"C:\Users\yasir\.claude-tools\azwebcorp-creds.json"))
m = imaplib.IMAP4_SSL("imap.secureserver.net", 993)
m.login(creds["mail_user"], creds["mail_password"])
typ, data = m.list()
for line in data:
    print(line.decode(errors="replace"))
m.logout()
