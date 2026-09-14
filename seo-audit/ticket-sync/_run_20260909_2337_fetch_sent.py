import json, imaplib, email
from email.header import decode_header, make_header

with open(r'C:\Users\yasir\.claude-tools\azwebcorp-creds.json') as f:
    creds = json.load(f)

M = imaplib.IMAP4_SSL('imap.secureserver.net', 993)
M.login(creds['mail_user'], creds['mail_password'])
M.select('Sent', readonly=True)

CHECKPOINT_UID = 8814  # separate UID space per folder, but use same cutoff logic; will widen if needed

# First, let's just see the highest UID and recent UIDs to understand the range
typ, data = M.uid('search', None, 'ALL')
uids = data[0].split()
print('total sent uids:', len(uids))
print('last 10 uids:', uids[-10:])

results = []
# fetch last 15 messages regardless of checkpoint, to inspect recent activity
recent = uids[-15:] if len(uids) > 15 else uids
for uid in recent:
    uid_s = uid.decode()
    typ, msgdata = M.uid('fetch', uid, '(RFC822)')
    if not msgdata or msgdata[0] is None:
        continue
    raw = msgdata[0][1]
    msg = email.message_from_bytes(raw)

    def dh(v):
        if v is None:
            return ''
        try:
            return str(make_header(decode_header(v)))
        except Exception:
            return v

    subject = dh(msg.get('Subject'))
    from_ = dh(msg.get('From'))
    to_ = dh(msg.get('To'))
    date_ = msg.get('Date')
    message_id = msg.get('Message-ID')

    results.append({
        'uid': int(uid_s),
        'subject': subject,
        'from': from_,
        'to': to_,
        'date': date_,
        'message_id': message_id,
    })

M.logout()

with open('_run_20260909_2337_sent_recent.json', 'w', encoding='utf-8') as f:
    json.dump(results, f, indent=2, ensure_ascii=False)

print(f'{len(results)} messages fetched')
for r in results:
    print(r['uid'], '|', r['date'], '|', r['from'], '->', r['to'], '|', r['subject'])
