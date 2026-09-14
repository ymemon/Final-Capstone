import json, imaplib, email
from email.header import decode_header, make_header

with open(r'C:\Users\yasir\.claude-tools\azwebcorp-creds.json') as f:
    creds = json.load(f)

M = imaplib.IMAP4_SSL('imap.secureserver.net', 993)
M.login(creds['mail_user'], creds['mail_password'])
M.select('INBOX', readonly=True)

CHECKPOINT_UID = 8814

typ, data = M.uid('search', None, f'UID {CHECKPOINT_UID+1}:*')
uids = data[0].split()

results = []
for uid in uids:
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
    in_reply_to = msg.get('In-Reply-To')
    references = msg.get('References')

    body = ''
    if msg.is_multipart():
        for part in msg.walk():
            ctype = part.get_content_type()
            cdispo = str(part.get('Content-Disposition'))
            if ctype == 'text/plain' and 'attachment' not in cdispo:
                try:
                    charset = part.get_content_charset() or 'utf-8'
                    body = part.get_payload(decode=True).decode(charset, errors='replace')
                except Exception as e:
                    body = f'[error decoding: {e}]'
                break
    else:
        try:
            charset = msg.get_content_charset() or 'utf-8'
            body = msg.get_payload(decode=True).decode(charset, errors='replace')
        except Exception as e:
            body = f'[error decoding: {e}]'

    results.append({
        'uid': int(uid_s),
        'subject': subject,
        'from': from_,
        'to': to_,
        'date': date_,
        'message_id': message_id,
        'in_reply_to': in_reply_to,
        'references': references,
        'body_snippet': body[:1500],
    })

M.logout()

with open('_run_20260909_2337_inbox.json', 'w', encoding='utf-8') as f:
    json.dump(results, f, indent=2, ensure_ascii=False)

print(f'{len(results)} messages fetched')
for r in results:
    print(r['uid'], '|', r['from'], '|', r['subject'])
