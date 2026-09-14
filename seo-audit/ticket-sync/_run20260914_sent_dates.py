import imaplib, json
creds = json.load(open(r"C:\Users\yasir\.claude-tools\azwebcorp-creds.json"))
pw = creds["mail_password"]
M = imaplib.IMAP4_SSL("imap.secureserver.net", 993)
M.login("info@azwebcorp.com", pw)
M.select("Sent", readonly=True)
typ, data = M.uid('search', None, 'ALL')
uids = data[0].split()
print("max uid:", uids[-1])
for uid in uids[-12:]:
    typ, d = M.uid('fetch', uid, '(INTERNALDATE)')
    print(uid, d)
M.logout()
