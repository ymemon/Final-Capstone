# -*- coding: utf-8 -*-
"""The real service menu, team and packages, read from her live MassageBook
listing on 2026-09-09, then corrected by Randi the same day.

WHY THIS FILE EXISTS
The site was built on "two session lengths, $95 and $130", which is what we were
told at the start. Her actual listing has six services across four price bands,
two live package deals, and - importantly - three people rather than one. A site
that advertises two services for a business that sells six is not a simplified
site, it is a wrong one, and "one therapist, the same hands every visit" was a
claim about a studio that has three practitioners in it.

UPDATED 2026-09-09 per Randi: Brian Onuffer has left the studio, so he is removed
from the team and Prenatal Massage is removed with him - he held the prenatal
certification. She is considering either training in it herself or replacing the
slot with something else; until she decides, the site shows five services rather
than advertising one nobody there can deliver.

UPDATED AGAIN 2026-09-09, second reply: Myofascial Unwinding is added as a sixth
service at therapeutic prices. This is NOT the "fill the empty grid cell" service
that was argued against - she does myofascial work already, in almost every
session, and her stated reason for listing it is that clients who book generic
"Therapeutic" have no way of telling her in advance which techniques they prefer.
A named service is the booking form asking that question for her.

NOT ADDED: cupping and IASTM/scraping. She has the cups but is not yet certified,
and says she will take the course so her insurer is satisfied. The page is written
and staged (pages.py, CUPPING_LIVE) and goes live the day she has the certificate,
not before. Wendy reportedly does both, but nothing about Wendy's certifications is
published anywhere we can read, so no claim about her is made.

NOTE FOR WHOEVER READS THE LIVE MENU NEXT: her MassageBook service list still
contains Prenatal Massage naming Brian as the practitioner (verified in the widget
HTML, 2026-09-09). The embedded booking widget mirrors her account, so that entry
appears on the website until she removes it in MassageBook. It cannot be edited
from this end. Same for Myofascial Unwinding: it is on the website, but it is not
bookable until she adds it to her MassageBook menu.

Nothing here is invented. Durations, prices, service descriptions and the two
named colleagues are all published by her. Where a description is quoted it is
trimmed for length only. No licence numbers are given for Brian or Wendy because
none are published - only Randi's (AZ MT-50515) appears on the staff page.
"""

# name, slug-or-None, blurb, [(minutes, price)], practitioner-or-None
SERVICES = [
    ("Therapeutic Massage", "therapeutic-massage",
     "A customised session designed around what your body needs most that day, combining deep "
     "tissue, myofascial release, stretching, mobility work and relaxation-based methods.",
     [(30, 70), (60, 95), (90, 130), (120, 175)], None),

    ("Sports Massage", "sports-massage",
     "Built for active bodies of every kind &mdash; reducing tension from repetitive movement, "
     "training, long workdays, parenting and daily wear. Targeted muscle work, assisted "
     "stretching and mobility work.",
     [(30, 70), (60, 95), (90, 130), (120, 175)], None),

    ("Myofascial Unwinding", "myofascial-unwinding",
     "Slow, sustained fascial work. The hands take up the slack and then follow where the tissue "
     "wants to go, rather than pressing into a restriction and waiting for it to give. Same "
     "lengths and prices as a therapeutic session.",
     [(30, 70), (60, 95), (90, 130), (120, 175)], None),

    ("Deep Core Therapy", "deep-core-therapy",
     "Focused work for the deep front of the body &mdash; psoas, hip flexors, abdominal tissues, "
     "diaphragm and the structures around them. These tighten from sitting, stress, training, "
     "postural strain and compensation patterns.",
     [(30, 50), (45, 80)], None),

    ("Stretch Session", "stretch-session",
     "Sometimes the body needs movement more than pressure. Mobility work, assisted stretching, "
     "joint range of motion and targeted release. Good for stiffness and post-workout recovery.",
     [(30, 50), (45, 75), (60, 90)], None),

    ("Corrective Exercise Training", "corrective-exercise",
     "Identifying movement imbalances, weakness patterns, poor mechanics and the recurring issues "
     "that bodywork alone will not fully solve, with guided exercises to work on.",
     [(30, 75), (45, 90), (60, 110), (75, 120), (90, 130)],
     "Wendy Hering, a certified personal trainer"),
]

# Live on her MassageBook "Special Offers" tab.
PACKAGES = [
    ("3 pack &mdash; 60-minute massage", 265, 20,
     "Three 60-minute sessions, credited to your account and booked as normal. Redeem within "
     "one year."),
    ("3 pack &mdash; 90-minute massage", 360, 30,
     "Three 90-minute sessions, credited to your account and booked as normal. Redeem within "
     "one year."),
]

TEAM = [
    dict(name="Randi M.", role="Licensed Massage Therapist",
         cred="Licence AZ MT-50515 &middot; qualified 2012",
         photo="randi.webp",
         bio="Sports and orthopedic bodywork. Trained at the Southwest Institute of Healing Arts "
             "in Tempe and licensed in both Arizona and Connecticut, Randi has worked with "
             "everyone from kids in intramural sports to ultramarathon runners, and from "
             "post-operative recovery to palliative care."),
    dict(name="Wendy Hering", role="Certified Personal Trainer",
         cred="Corrective exercise training",
         photo=None,
         bio="Wendy was Randi's mentor when she began practising in Mesa, and now leads the "
             "studio's corrective exercise work &mdash; the movement side of the problem that "
             "hands-on work alone will not fully solve."),
]


def price_row(mins, price):
    return ('<div class="dur-row"><span class="dur-min">%d min</span>'
            '<span class="dur-amt">$%d</span></div>' % (mins, price))


def service_card(s, link=True):
    from graphics import icon
    name, slug, blurb, prices, who = s
    rows = "".join(price_row(m, p) for m, p in prices)
    by = ('<p class="by">With %s.</p>' % who) if who else ""
    head = ('<h3><a href="/%s/">%s</a></h3>' % (slug, name)) if (link and slug) else (
        "<h3>%s</h3>" % name)
    return ('<div class="svc-card">%s%s<p>%s</p>%s<div class="dur-list">%s</div></div>'
            % (icon(slug or ""), head, blurb, by, rows))


def all_offers_ld():
    """Every duration of every service as a schema.org Offer."""
    out = []
    for name, _slug, _b, prices, _w in SERVICES:
        for mins, price in prices:
            out.append(
                '    { "@type": "Offer", "name": "%d-minute %s", "price": "%d.00", '
                '"priceCurrency": "USD", "availability": "https://schema.org/InStock",\n'
                '      "itemOffered": { "@type": "Service", "name": "%s (%d minutes)" } }'
                % (mins, name, price, name, mins))
    return ",\n".join(out)
