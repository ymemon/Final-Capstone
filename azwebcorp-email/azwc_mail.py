"""
Shared send helper for AZ Web Corp outbound mail.

WHY THIS EXISTS
Sending via smtplib does NOT put a copy in the mailbox's Sent folder - only a
mail client does that, by IMAP-APPENDing the message after sending. Every
email sent by script before 2026-08-26 therefore left no trace in Sent, which
meant Yasir had no record of what had gone out. Twice today that also caused
real confusion about whether something had been sent at all.

Use send_and_file() for ALL outbound from now on. It sends, then files a copy
in Sent, and tells you whether each step succeeded.
"""

import json as _json  # credentials live outside the repo
_AZWC = _json.load(open(r"C:\Users\yasir\.claude-tools\azwebcorp-creds.json"))
import smtplib, ssl, imaplib, time

SMTP_HOST, SMTP_PORT = "smtpout.secureserver.net", 465
IMAP_HOST, IMAP_PORT = "imap.secureserver.net", 993
MAIL_USER = "info@azwebcorp.com"
MAIL_PASS = _AZWC["mail_password"]
SENT_FOLDER = "Sent"

# Yasir gets a copy of every script-sent email in his INBOX, not just in Sent.
# Filing in Sent alone is not enough - he asked for a copy and sent mail is
# easy to miss there. CHANGED 2026-09-02 per Yasir: use Cc, not Bcc - he wants
# the client to see he's copied on the thread, not have it hidden.
# CHANGED 2026-09-09 per Yasir: ceo@, not yasir@. He reads ceo@, so copies sent
# to yasir@ were landing in a mailbox he does not watch - which defeats the
# point of copying him at all.
ALWAYS_CC = "ceo@azwebcorp.com"


def file_in_sent(msg, folder=SENT_FOLDER):
    """APPEND a copy to the Sent folder. Returns True on success."""
    try:
        im = imaplib.IMAP4_SSL(IMAP_HOST, IMAP_PORT)
        im.login(MAIL_USER, MAIL_PASS)
        im.append(f'"{folder}"', "\\Seen",
                  imaplib.Time2Internaldate(time.time()),
                  msg.as_bytes())
        im.logout()
        return True
    except Exception as e:
        print(f"   ! could not file copy in {folder}: {e}")
        return False


TICKET_API = "https://azwebcorp.com/wp-json/azwc-tickets/v1"


def ticket_buttons(to_email, subject, name=""):
    """
    Fetch the "All sorted, thanks" / "I'll reply" block for this conversation.

    Finds the existing ticket for the recipient and subject, creating one if
    there is none, and returns ready-to-paste HTML.

    Never raises. If the ticket API is unreachable the email still goes out,
    just without the buttons - a missing button is a small loss, a blocked
    client email is a large one.
    """
    import json as _j
    import urllib.request
    import urllib.parse

    key = _AZWC.get("ticket_api_key", "")
    if not key:
        return ""

    # Cloudflare fronts azwebcorp.com and rejects urllib's default user agent
    # with a 403 "error code: 1010" (banned browser signature). Identifying as a
    # normal browser is the difference between the API working and silently
    # returning nothing.
    _UA = ("Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 "
           "(KHTML, like Gecko) Chrome/128.0.0.0 Safari/537.36")

    def _headers(req):
        req.add_header("X-AZWC-Key", key)
        req.add_header("User-Agent", _UA)
        req.add_header("Accept", "application/json")
        return req

    def _get(path, params):
        url = f"{TICKET_API}/{path}?" + urllib.parse.urlencode(params)
        req = _headers(urllib.request.Request(url, method="GET"))
        with urllib.request.urlopen(req, timeout=25) as r:
            return _j.loads(r.read().decode())

    def _post(path, body):
        # /find and /create are POST routes; calling them with GET returns
        # rest_no_route, which looks identical to "no ticket found".
        url = f"{TICKET_API}/{path}"
        data = urllib.parse.urlencode(body).encode()
        req = _headers(urllib.request.Request(url, data=data, method="POST"))
        with urllib.request.urlopen(req, timeout=25) as r:
            return _j.loads(r.read().decode())

    try:
        found = _post("find", {"email": to_email, "subject": subject})
        ticket = (found or {}).get("ticket_no")
    except Exception:
        ticket = None

    if not ticket:
        try:
            created = _post(
                "create",
                {
                    "email": to_email,
                    "name": name,
                    "subject": subject,
                    "direction": "outbound",
                },
            )
            return (created or {}).get("buttons_html", "")
        except Exception:
            return ""

    try:
        return (_get("client-buttons", {"ticket": ticket}) or {}).get("buttons_html", "")
    except Exception:
        return ""


def send_and_file(msg, folder=SENT_FOLDER):
    """
    Send msg over SMTP, then file a copy in Sent.

    Returns (sent_ok, filed_ok). A failure to file is reported but never
    silently swallowed - if it returns (True, False) the mail DID go out and
    only the local record is missing, which matters for deciding whether a
    retry would double-send.
    """
    # Add Yasir to the visible Cc header (not just the envelope) so the
    # recipient can see he's copied, unless he's already on To/Cc.
    existing = []
    for hdr in ("To", "Cc"):
        if msg[hdr]:
            existing += [a.strip().lower() for a in str(msg[hdr]).split(",") if a.strip()]
    cc_added = bool(ALWAYS_CC) and not any(ALWAYS_CC.lower() in a for a in existing)
    if cc_added:
        if msg["Cc"]:
            new_cc = str(msg["Cc"]) + ", " + ALWAYS_CC
            del msg["Cc"]
            msg["Cc"] = new_cc
        else:
            msg["Cc"] = ALWAYS_CC

    rcpts = []
    for hdr in ("To", "Cc"):
        if msg[hdr]:
            rcpts += [a.strip() for a in str(msg[hdr]).split(",") if a.strip()]

    ctx = ssl.create_default_context()
    with smtplib.SMTP_SSL(SMTP_HOST, SMTP_PORT, context=ctx, timeout=180) as s:
        s.login(MAIL_USER, MAIL_PASS)
        s.send_message(msg, from_addr=MAIL_USER, to_addrs=rcpts)
    print(f"   SENT OK -> {msg['To']}" + (f" (cc {msg['Cc']})" if msg['Cc'] else ""))

    filed = file_in_sent(msg, folder)
    if filed:
        print(f"   copy filed in {folder}")
    return True, filed
