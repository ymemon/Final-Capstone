import imaplib, json

creds = json.load(open(r"C:\Users\yasir\.claude-tools\azwebcorp-creds.json"))

m = imaplib.IMAP4_SSL("imap.secureserver.net", 993)
m.login(creds["mail_user"], creds["mail_password"])
typ, data = m.list()
folders = []
for line in data:
    folders.append(line.decode(errors="replace"))
print(json.dumps(folders, indent=2))
m.logout()
