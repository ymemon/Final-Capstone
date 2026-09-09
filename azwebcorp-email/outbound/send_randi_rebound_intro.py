"""Introduce Phil to Randi at Rebound Body Studio and ask for everything the
build is blocked on.

Sent to the studio address with her personal address cc'd, because the two were
given together and a question that sits unread in a business inbox stalls the
whole build.

Usage:
    python send_randi_rebound_intro.py            # preview
    python send_randi_rebound_intro.py --send
"""

import os
import sys
import textwrap
from email.message import EmailMessage
from email.utils import formatdate, make_msgid

HERE = os.path.dirname(os.path.abspath(__file__))
sys.path.insert(0, os.path.join(HERE, "..", "signature"))
sys.path.insert(0, os.path.join(HERE, ".."))
import azwc_signature_v2 as SIG
import azwc_mail

TO = "massage@reboundbodystudio.com"
CC = "randi.martinez.23@gmail.com"
FROM = "Phil — AZ Web Corp <requests@azwebcorp.com>"
SENDER = "requests@azwebcorp.com"
SUBJECT = "Rebound Body Studio — starting your website, and a few things I need from you"

INK, RULE, MUTE = "#111820", "#e3e6ea", "#5c6773"

INTRO = [
    "I'm Phil, from AZ Web Corp in Gilbert. Yasir has asked me to look after building "
    "your website, so I'll be your point of contact from here.",

    "I've made a start already, working from your MassageBook page, so you can see "
    "something real rather than a blank page and a questionnaire. What I have so far: "
    "the two session lengths at $95 and $130, your hours across all seven days, the phone "
    "number, and booking that runs through MassageBook so nothing about how you actually "
    "take appointments has to change.",

    "One thing worth saying up front, because it shapes the whole design. Nearly every "
    "massage website looks like a day spa — soft focus, candles, lavender. You're a solo "
    "therapist doing sports and orthopedic work, and people come to you because something "
    "hurts and they want it fixed. That's a different thing being bought, so I've built it "
    "to look like a recovery studio rather than a spa. If that's the wrong read, tell me "
    "now and I'll change direction before we go any further.",
]

BLOCKING = [
    ("Your address",
     "Your MassageBook page says 1138 N. Higley Rd, Suite 109. Another listing I found says "
     "1918 N. Higley Rd. One of them is wrong, and I don't want to guess — a wrong address "
     "on a website and a Google listing is genuinely damaging for a local business. Could you "
     "confirm the exact street number and suite? I've put an obvious placeholder in the "
     "meantime so nothing incorrect goes out."),

    ("Your name and licence",
     "How would you like your name shown on the site, and what's your licence type and "
     "number? For sports and orthopedic work this matters more than it would for a spa — "
     "it's the main thing that tells a first-time visitor you're qualified to work on an "
     "injury. Any certifications or specific training worth naming, please send those too."),

    ("A short bio, in your own words",
     "A paragraph or two on how you got into this, what you're best at, and the kind of "
     "client you most like working with. Don't polish it — I'd rather have how you actually "
     "talk and tidy the grammar than write something that sounds like everyone else."),

    ("Photographs",
     "This is the single biggest visual difference between a site that converts and one that "
     "doesn't. Photos of your actual treatment room and of you, not stock images — people can "
     "spot stock instantly and it reads as though there was nothing real to show. If you don't "
     "have good ones, we'll come out and shoot them; it takes about an hour."),
]

DECISIONS = [
    ("Reviews",
     "You don't have any published reviews yet, which is the biggest quick win available. "
     "MassageBook can request one automatically after every appointment — worth switching on "
     "today, because a couple of real reviews next to a booking button does more than almost "
     "anything else on the page. I've left that section out of the site until you have some, "
     "rather than filling it with invented quotes."),

    ("Packages or memberships",
     "Do you want to offer prepaid blocks of sessions or a monthly plan? MassageBook supports "
     "both. If yes, what would you charge? If you'd rather keep it to the two session lengths, "
     "that's a perfectly good answer and I'll drop the section."),

    ("Anything beyond the two session lengths",
     "Is there anything else you offer — cupping, stretch work, anything sport-specific — that "
     "should have its own place on the site rather than being folded into the 60 and 90 minute "
     "sessions?"),

    ("Finding the door",
     "You're in a suite, and suites lose people. Which building, where do they park, and which "
     "entrance do they use? Spelling that out removes a real reason people give up and go "
     "somewhere else."),

    ("Cancellation policy",
     "What's your policy, and how much notice do you need? Better on the site than as an "
     "awkward conversation afterwards."),
]

CLOSING = [
    "On the technical side, your domain reboundbodystudio.com currently just forwards to "
    "MassageBook and has no security certificate on it, so anyone typing it in gets a browser "
    "warning. That's included in the build and nothing for you to worry about — I mention it "
    "only so you know it's been spotted.",

    "You'll keep everything MassageBook does. Booking, gift certificates, reminders, your "
    "client records and your payments all stay exactly where they are — the site sits in front "
    "of it so people find you on your own name instead of a directory that lists other "
    "therapists alongside you.",

    "No rush on all of it at once. The address, your name and licence, and the photos are what "
    "I'm actually blocked on; the rest can follow. Reply to whichever of these you have to hand "
    "and I'll keep building around the gaps.",
]


def build_html():
    intro = "".join(
        f'<p style="margin:0 0 18px 0;font-size:15px;line-height:1.75;color:{INK};">{p}</p>'
        for p in INTRO)

    def block(items, heading, note):
        rows = ""
        for title, body in items:
            rows += (
                f'<tr><td style="padding:0 0 18px 0;">'
                f'<p style="margin:0 0 5px 0;font-size:14.5px;font-weight:700;color:{INK};">{title}</p>'
                f'<p style="margin:0;font-size:14.5px;line-height:1.7;color:{MUTE};">{body}</p>'
                f'</td></tr>')
        return (
            f'<p style="margin:26px 0 6px 0;font-size:13px;font-weight:700;letter-spacing:.06em;'
            f'text-transform:uppercase;color:{INK};">{heading}</p>'
            f'<p style="margin:0 0 18px 0;font-size:14px;color:{MUTE};">{note}</p>'
            f'<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">{rows}</table>')

    closing = "".join(
        f'<p style="margin:0 0 18px 0;font-size:15px;line-height:1.75;color:{INK};">{p}</p>'
        for p in CLOSING)

    return f"""<div style="background-color:#eef1f4;padding:28px 0;">
<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%"
       style="border-collapse:collapse;">
 <tr><td align="center">
  <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="660"
         style="border-collapse:collapse;width:660px;max-width:100%;background-color:#ffffff;">
   <tr><td style="padding:38px 44px 8px 44px;
                  font-family:'Segoe UI',Roboto,Helvetica,Arial,sans-serif;">
     <p style="margin:0 0 20px 0;font-size:15px;line-height:1.75;color:{INK};">Hi Randi,</p>
     {intro}
     {block(BLOCKING, "What I'm blocked on", "These four are the ones holding the build up.")}
     {block(DECISIONS, "Decisions when you have a minute", "None of these stop me working.")}
     {closing}
     <p style="margin:22px 0 4px 0;font-size:15px;line-height:1.75;color:{INK};">
       Best regards,
     </p>
   </td></tr>
   <tr><td style="padding:16px 44px 0 44px;">
     <div style="border-top:1px solid {RULE};font-size:0;line-height:0;">&nbsp;</div>
   </td></tr>
   <tr><td style="padding:20px 44px 34px 44px;">
     {SIG.html(name="Phil", title="Account &amp; Delivery", sender=SENDER)}
   </td></tr>
  </table>
 </td></tr>
</table>
</div>"""


def _plain(s):
    return (s.replace("&ldquo;", '"').replace("&rdquo;", '"')
             .replace("’", "'").replace("“", '"').replace("”", '"')
             .replace("—", "-").replace("<strong>", "").replace("</strong>", ""))


def build_text():
    out = ["Hi Randi,", ""]
    for p in INTRO:
        out += textwrap.wrap(_plain(p), width=74) + [""]

    out += ["WHAT I'M BLOCKED ON", "These four are the ones holding the build up.", ""]
    for title, body in BLOCKING:
        out += [f"* {title}"]
        out += textwrap.wrap(_plain(body), width=70, initial_indent="  ", subsequent_indent="  ")
        out += [""]

    out += ["DECISIONS WHEN YOU HAVE A MINUTE", "None of these stop me working.", ""]
    for title, body in DECISIONS:
        out += [f"* {title}"]
        out += textwrap.wrap(_plain(body), width=70, initial_indent="  ", subsequent_indent="  ")
        out += [""]

    for p in CLOSING:
        out += textwrap.wrap(_plain(p), width=74) + [""]

    out += ["Best regards,", "", SIG.text(name="Phil", title="Account & Delivery", sender=SENDER)]
    return "\n".join(out)


def main():
    msg = EmailMessage()
    msg["From"] = FROM
    msg["To"] = TO
    msg["Cc"] = CC
    msg["Subject"] = SUBJECT
    msg["Date"] = formatdate(localtime=True)
    msg["Message-ID"] = make_msgid(domain="azwebcorp.com")
    msg["Reply-To"] = SENDER
    msg.set_content(build_text())
    msg.add_alternative(build_html(), subtype="html")

    if "--send" not in sys.argv:
        print(build_text())
        print("\nPREVIEW ONLY — re-run with --send")
        return
    azwc_mail.send_and_file(msg)
    print("Sent to", TO, "cc", CC)


if __name__ == "__main__":
    main()
