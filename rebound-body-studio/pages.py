# -*- coding: utf-8 -*-
"""Page content for the Rebound Body Studio site.

Everything stated as fact came from her own MassageBook profile, staff page and
services page, read 2026-09-09. Nothing is invented:

  name / licence   Randi M., licence AZ MT-50515, qualified 2012
  team             Randi and Wendy Hering (certified personal trainer). Brian
                   Onuffer left the studio on 2026-09-09 and is removed, along
                   with Prenatal Massage, which was his certification.
  address          1138 N. Higley Rd, Suite 109, Mesa, AZ 85205 - confirmed on
                   her main profile, staff page and services page alike
  services         six services, real durations and prices
  packages         the two live 3-packs, with her own savings figures
  bio / portrait   her own words and her own photograph

Still not invented: reviews (she has none published), photographs of the studio,
and any claim that a session treats or cures anything.
"""
from services import SERVICES, PACKAGES, TEAM, service_card, price_row
from graphics import HERO, SPINE

# Appointment and package flows are hosted with this website.
REVIEWS_LIVE = False
CUPPING_LIVE = False
PACKAGE_URL = "/booking/public/packages.html?v=20260912-square-3"
MAP_QUERY = "Speakeasy+Salon+Suites+1138+N+Higley+Rd+Mesa+AZ+85205"
MAP_DIRECTIONS = "https://www.google.com/maps/dir/?api=1&amp;destination=" + MAP_QUERY
DIRECTIONS = """<p><strong>Inside Speakeasy Salon Suites.</strong> Park outside the salon suites, near
Knuckle Sandwiches, and look for the purple Speakeasy sign. Enter the salon suites and look for
Suite 109. The TV directory near the entrance also lists the suite.</p>
<p>Walk straight down the first hallway, almost to the back. If the Suite 109 door is closed,
please wait in the back waiting room. There is also a waiting room at the front.</p>"""

ADDRESS = "1138 N. Higley Rd, Suite&nbsp;109<br>Mesa, AZ 85205"

RAIL = """      <aside class="rail" aria-label="Rates and booking">
        <div class="status"><span class="dot" id="dot"></span><span id="status-text">Open seven days</span></div>
        <div class="rate"><span class="dur">Massage, 60 minutes</span><span class="amt">$95</span></div>
        <div class="rate"><span class="dur">Massage, 90 minutes</span><span class="amt">$130</span></div>
        <a class="btn btn-primary" href="/book/">Check availability</a>
        <p class="rail-note">Sessions from 30 to 120 minutes, and stretch work from $50.
        Evenings until 7pm on weekdays.</p>
      </aside>"""

HOURS_TABLE = """        <table class="hours" id="hours-table">
          <tr data-d="1"><td>Monday</td><td>9:00 AM &ndash; 7:00 PM</td></tr>
          <tr data-d="2"><td>Tuesday</td><td>9:00 AM &ndash; 7:00 PM</td></tr>
          <tr data-d="3"><td>Wednesday</td><td>9:00 AM &ndash; 7:00 PM</td></tr>
          <tr data-d="4"><td>Thursday</td><td>8:00 AM &ndash; 7:00 PM</td></tr>
          <tr data-d="5"><td>Friday</td><td>9:00 AM &ndash; 7:00 PM</td></tr>
          <tr data-d="6"><td>Saturday</td><td>9:00 AM &ndash; 3:00 PM</td></tr>
          <tr data-d="0"><td>Sunday</td><td>9:00 AM &ndash; 4:30 PM</td></tr>
        </table>"""


def head(label, h1, lede):
    return """  <div class="page-head"><div class="wrap">
    <div class="label">%s</div>
    <h1>%s</h1>
    <p class="lede">%s</p>
  </div></div>
""" % (label, h1, lede)


def service_grid(link=True):
    return ('<div class="svc-grid">%s</div>'
            % "".join(service_card(s, link=link) for s in SERVICES))


def package_grid():
    cards = ""
    for name, price, save, blurb in PACKAGES:
        cards += ('<div class="pkg"><div class="pkg-save">Save $%d</div><h3>%s</h3>'
                  '<div class="pkg-amt">$%d</div><p>%s</p>'
                  '<a class="btn btn-ghost" href="/booking/public/packages.html?v=20260912-square-3">Choose a package</a></div>'
                  % (save, name, price, blurb))
    return '<div class="pkg-grid">%s</div>' % cards


def team_grid():
    out = ""
    for m in TEAM:
        img = ('<img class="portrait" src="/assets/%s" width="500" height="375" alt="%s, %s at '
               'Rebound Body Studio">' % (m["photo"], m["name"], m["role"])) if m["photo"] else (
              '<div class="portrait-ph"><span>Photograph to come</span></div>')
        out += ('<div class="member">%s<h3>%s</h3><div class="role">%s</div>'
                '<div class="cred">%s</div><p>%s</p></div>'
                % (img, m["name"], m["role"], m["cred"], m["bio"]))
    return '<div class="team-grid">%s</div>' % out


# ── Home ─────────────────────────────────────────────────────────────────────

HOME = """  <div class="hero has-video">
    <div class="hero-media" style="background-image:url('/assets/video/hero-poster.jpg')">
      <video class="hero-video" autoplay muted loop playsinline
             poster="/assets/video/hero-poster.jpg" aria-hidden="true">
        <source src="/assets/video/hero-massage.webm" type="video/webm">
        <source src="/assets/video/hero-massage.mp4" type="video/mp4">
      </video>
      <div class="hero-scrim"></div>
    </div>
    <div class="wrap hero-grid">
      <div>
        <div class="label">Sports &amp; orthopedic bodywork &middot; Mesa, Arizona</div>
        <h1>Massage that works on the reason it hurts.</h1>
        <p class="sub">For the back that tightens every week, the shoulder that will not rotate,
        the hip that changed how you run. Bodywork built around the cause rather than the clock
        &mdash; plus stretch, core and corrective exercise work when hands alone are not the
        answer.</p>
        <div class="hero-cta">
          <a class="btn btn-primary btn-lg" href="/book/">Book a session</a>
          <a class="btn btn-ghost btn-lg" href="/how-massage-works/">How massage actually works</a>
        </div>
        <p style="margin-top:20px"><a href="/#studio-map">Find us on Google Maps</a> &middot;
        <a href="/contact/#directions">Parking and entrance directions</a></p>
      </div>
%(rail)s
    </div>
  </div>

  <div class="wrap">
%(hero_gfx)s
  </div>

  <section>
    <div class="wrap">
      <div class="sec-head">
        <div class="label">What we do</div>
        <h2>Six services, one approach.</h2>
        <p>Massage from thirty to a hundred and twenty minutes, plus stretch, deep core and
        corrective exercise work. Which one suits you gets decided with you, not sold to you.</p>
      </div>
%(services)s
    </div>
  </section>

  <section>
    <div class="wrap">
      <div class="sec-head">
        <div class="label">Packages</div>
        <h2>Book three, pay less.</h2>
        <p>Credited to your account and booked like any other appointment. Redeem within a year.</p>
      </div>
%(packages)s
    </div>
  </section>

  <section>
    <div class="wrap">
      <div class="sec-head">
        <div class="label">Commonly booked for</div>
        <h2>What brings people in.</h2>
      </div>
      <div class="spine-wrap">
%(spine)s
        <div>
          <p style="color:var(--ink-2)">Most of what walks through the door traces back to three
          regions. Which one is the source is not always where it hurts &mdash; a sore knee that
          starts at the hip, a neck that starts in the mid-back &mdash; and finding that is most
          of the value of the appointment.</p>
          <p style="color:var(--ink-2)">If you can point at the problem, a shorter focused session
          is usually enough. If it moves around, book longer.</p>
        </div>
      </div>
      <ul class="cond" style="margin-top:36px">
        <li>Recurring muscle tension</li>
        <li>Back pain</li>
        <li>Neck &amp; shoulder pain</li>
        <li>Restricted range of motion</li>
        <li>Postural strain</li>
        <li>Training recovery</li>
        <li>Desk-work tightness</li>
        <li>Hip and psoas tightness</li>
        <li>Scar tissue &amp; post-surgical areas</li>
        <li>Stress held in the body</li>
      </ul>
    </div>
  </section>

  <section>
    <div class="wrap">
      <div class="sec-head">
        <div class="label">The studio</div>
        <h2>Two people, one plan.</h2>
        <p>Randi came back to Arizona wanting a studio that put bodywork and fitness in the same
        room rather than sending people between them. This is that studio.</p>
      </div>
%(team)s
      <p style="margin-top:30px"><a class="btn btn-ghost" href="/about/">More about the studio</a></p>
    </div>
  </section>
  <section id="studio-map" style="scroll-margin-top:95px">
    <div class="wrap">
      <div class="sec-head"><div class="label">Come find us</div><h2>Google Maps and directions.</h2>
        <p>Inside Speakeasy Salon Suites &mdash; 1138 N. Higley Rd, Suite 109, Mesa, AZ 85205.</p></div>
      <div class="grid-2">
        <div>%(directions)s
          <a class="btn btn-primary" href="%(map_directions)s" target="_blank" rel="noopener">Get directions in Google Maps</a>
          <p style="margin-top:18px"><a href="/contact/">Opening hours and contact details</a></p>
        </div>
        <iframe title="Google Maps: Speakeasy Salon Suites, 1138 N. Higley Road, Mesa"
          src="https://www.google.com/maps/embed?origin=mfe&amp;pb=!1m2!2m1!1s%(map_query)s"
          width="100%%" height="420" style="border:0;border-radius:14px" loading="eager"
          referrerpolicy="no-referrer-when-downgrade" allowfullscreen></iframe>
      </div>
    </div>
  </section>
""" % dict(rail=RAIL, hero_gfx=HERO, spine=SPINE, services=service_grid(),
           packages=package_grid(), team=team_grid(), directions=DIRECTIONS,
           map_directions=MAP_DIRECTIONS, map_query=MAP_QUERY)


# ── Services index ───────────────────────────────────────────────────────────

SESSIONS = head("Services &amp; pricing", "Everything on the menu, with the prices.",
                "Six services across four price bands. No add-on menu, and nothing sold to you "
                "partway through a session.") + """
  <section style="border-top:0; padding-top:38px">
    <div class="wrap">
%(services)s
    </div>
  </section>

  <section>
    <div class="wrap">
      <div class="sec-head">
        <div class="label">Packages</div>
        <h2>Book three, pay less.</h2>
        <p>Credited to your account and booked like any other appointment. Redeem within a year.</p>
      </div>
%(packages)s
    </div>
  </section>

  <section><div class="wrap">
    <h2>Your package, at your pace.</h2>
    <p>Order a three-session package without choosing an appointment. The studio will arrange payment,
    then activate your sessions. Use your private package link to check your balance and book when you are ready.</p>
    <a class="btn btn-ghost" href="/booking/public/packages.html?v=20260912-square-3">Order or manage a package</a>
  </div></section>
""" % dict(services=service_grid(), packages=package_grid())


# ── Individual service pages ─────────────────────────────────────────────────
# Structure is shared; the copy is written per service so each page earns its
# own place in search rather than being a template with the noun swapped.

SERVICE_COPY = {
    "therapeutic-massage": dict(
        label="Therapeutic massage",
        h1="Therapeutic massage in Mesa.",
        lede="A session built around what your body needs that day rather than a routine applied "
             "to everyone.",
        left_h="What it is",
        left=["This is the general-purpose session, and the one most people start with. It draws "
              "on deep tissue work, myofascial release, stretching, mobility work and "
              "relaxation-based technique &mdash; whichever combination the problem calls for.",
              "The mix is decided once you are on the table. Two people booking the same ninety "
              "minutes will often get quite different sessions, because the restriction sits in a "
              "different place.",
              "Scar tissue and post-surgical areas are regular work here too. The tissue itself is "
              "not being torn apart &mdash; what changes is how the surrounding fascia moves and "
              "how the area is being guarded &mdash; and people often get a great deal of relief "
              "from it."],
        right_h="Deeper is not automatically better",
        right=["Worth saying plainly, because the industry rarely does: pressure that has you "
               "holding your breath is counterproductive. A muscle bracing against you is a "
               "muscle that is not releasing.",
               "Randi works into deeper tissue only once the surface has released and your "
               "nervous system has settled. That order is the technique &mdash; going deep before "
               "the surface lets go just means fighting the body.",
               "You set the pressure, and it is adjusted whenever you say so."],
        cond=["Recurring muscle tension", "Back pain", "Neck &amp; shoulder pain",
              "Postural strain", "Desk-work tightness", "Scar tissue &amp; post-surgical areas"]),

    "sports-massage": dict(
        label="Sports massage",
        h1="Sports massage in Mesa.",
        lede="Built for active bodies of every kind &mdash; and that includes bodies made tired "
             "by work and parenting, not just by training.",
        left_h="Who it is for",
        left=["Sports massage is usually booked around a training block: loosening what has "
              "tightened, working the areas limiting a movement, and getting you back to the "
              "thing you had to stop doing.",
              "Randi has worked across the whole range &mdash; kids in intramural sports, "
              "ultramarathon runners, people recovering from an injury or an operation, and "
              "people needing palliative care.",
              "You do not have to be an athlete. The same work suits anyone under repetitive "
              "load, which includes most desk jobs and most of parenting."],
        right_h="What a session involves",
        right=["Targeted muscle work, assisted stretching and mobility work. Expect active and "
               "passive range of motion: she will move the joint, and ask you to move it, to find "
               "where the restriction actually sits rather than guessing from where it hurts.",
               "That is usually paired with myofascial work through the connective tissue and "
               "slower, deeper pressure once the surface has released.",
               "You will leave with something to do between sessions. Bodywork changes how tissue "
               "behaves for a while; what you do in that window is what makes it last."],
        cond=["Training recovery", "Restricted range of motion", "Return to running",
              "Post-operative recovery", "Repetitive strain", "Recurring tightness"]),


    "myofascial-unwinding": dict(
        label="Myofascial unwinding",
        h1="Myofascial unwinding.",
        lede="Slow, sustained fascial work &mdash; tissue followed rather than forced, and given "
             "the time it needs to let go in its own direction.",
        left_h="What it is",
        left=["Rather than pressing into a restriction and waiting for it to give, the hands take "
              "up the slack in the fascia and then follow wherever the tissue wants to go. One "
              "place can hold her attention for several minutes, and your own body often makes "
              "small unprompted adjustments as it happens.",
              "It looks like very little is going on. It is the least dramatic thing on this "
              "menu, and for some people it is what finally shifts something that firm pressure "
              "never reached.",
              "None of this is new here &mdash; myofascial work runs through almost every session "
              "Randi does. Booking it by name means the whole appointment is spent this way."],
        right_h="Why book it by name",
        right=["Mostly so you can say what you want before you are on the table. A generic "
               "booking gives her no way of knowing that fascial work is the thing you respond "
               "to; this one does, and the session starts there instead of finding its way "
               "there.",
               "It also suits bodies that do not do well under deep pressure &mdash; ones that "
               "brace, guard, or ache for two days afterwards. Slow fascial work asks very little "
               "of the nervous system, so there is less to defend against.",
               "And it suits long-held restriction: an old injury, a surgical site, or an area "
               "that has quietly reorganised itself around something. That is less a tight muscle "
               "than a pattern, and patterns respond better to being followed than to being "
               "fought."],
        cond=["Long-held holding patterns", "Post-surgical restriction",
              "Scar tissue &amp; adhesion", "Guarded or reactive tissue",
              "Chronic tightness", "Sensitivity to deep pressure"]),

    "deep-core-therapy": dict(
        label="Deep core therapy",
        h1="Deep core therapy.",
        lede="Focused work for the deep front of the body &mdash; the part almost nothing else "
             "reaches.",
        left_h="What it covers",
        left=["The psoas, hip flexors, abdominal tissues, diaphragm and the structures around "
              "them. These tighten from sitting, from stress, from training, from postural strain "
              "and from compensating around an old injury.",
              "It is a shorter, more specific appointment than a full massage &mdash; thirty or "
              "forty-five minutes &mdash; because the work is concentrated rather than "
              "full-body."],
        right_h="Why people book it",
        right=["A great deal of stubborn low back tightness is actually the front of the body "
               "pulling. If your back keeps returning to the same place after treatment, the "
               "reason is often anterior and never gets touched.",
               "Breathing is the other reason. The diaphragm is a muscle, it responds to stress "
               "like any other, and restriction there shows up as tightness that no amount of "
               "back work resolves."],
        cond=["Stubborn low back tightness", "Hip flexor restriction", "Postural compensation",
              "Restricted breathing", "Desk-work tightness"]),

    "stretch-session": dict(
        label="Stretch session",
        h1="Assisted stretch sessions.",
        lede="Sometimes the body needs movement more than it needs pressure.",
        left_h="What it is",
        left=["Mobility work, assisted stretching, joint range of motion and targeted release "
              "technique. You stay dressed and active rather than lying still under a sheet.",
              "It is the lightest-touch thing on the menu and the most immediately useful for "
              "stiffness &mdash; particularly the day after a hard session, when deep pressure is "
              "the last thing a sore body wants."],
        right_h="How it fits with everything else",
        right=["Stretch sessions pair well with massage rather than replacing it. A common pattern "
               "is a full massage when something is genuinely restricted, then shorter stretch "
               "sessions to hold the ground that was gained.",
               "If the same restriction keeps coming back, that is usually a signal for "
               "<a href=\"/corrective-exercise/\">corrective exercise</a> rather than more "
               "bodywork &mdash; something is loading it that way every day."],
        cond=["Post-workout stiffness", "Limited mobility", "Warm-up before an event",
              "Maintenance between massages", "General tightness"]),

    "corrective-exercise": dict(
        label="Corrective exercise",
        h1="Corrective exercise training.",
        lede="For the problem that comes back every time, because something in how you move keeps "
             "putting it there.",
        left_h="What it is for",
        left=["Led by Wendy Hering, a certified personal trainer, these sessions look for the "
              "movement imbalances, weakness patterns and mechanics behind recurring problems "
              "&mdash; the ones bodywork alone will not fully solve.",
              "You leave with guided exercises and strategies rather than a treatment. It is the "
              "other half of the answer when massage keeps working and then wearing off."],
        right_h="Why it sits alongside the massage",
        right=["Most studios send you elsewhere for this, which is how the two halves of a problem "
               "end up being treated by two people who never speak to each other.",
               "Having both under one roof is the reason the studio exists: Wendy was Randi's "
               "mentor when she started practising in Mesa, and the plan they came back to build "
               "was bodywork and fitness in the same room."],
        cond=["Recurring injury", "Movement imbalance", "Weakness patterns", "Poor mechanics",
              "Return to training", "Postural strain"]),
}


def service_page(slug):
    c = SERVICE_COPY[slug]
    svc = next(s for s in SERVICES if s[1] == slug)
    prices = "".join(price_row(m, p) for m, p in svc[3])
    left = "".join('<p style="color:var(--ink-2)">%s</p>' % p for p in c["left"])
    right = "".join('<p style="color:var(--ink-2)">%s</p>' % p for p in c["right"])
    conds = "".join("<li>%s</li>" % x for x in c["cond"])
    who = ('<p class="by" style="margin-top:14px">With %s.</p>' % svc[4]) if svc[4] else ""

    return head(c["label"], c["h1"], c["lede"]) + """
  <section style="border-top:0; padding-top:38px">
    <div class="wrap">
      <div class="price-strip">
        <div class="price-strip-h">Session lengths</div>
        <div class="dur-list wide">%(prices)s</div>
        <a class="btn btn-primary" href="/book/">Book this</a>
      </div>
      %(who)s
      <div class="grid-2" style="margin-top:46px">
        <div><h3>%(lh)s</h3>%(left)s</div>
        <div><h3>%(rh)s</h3>%(right)s</div>
      </div>

      <div style="margin-top:48px">
        <div class="label" style="margin-bottom:18px">Commonly booked for</div>
        <ul class="cond">%(conds)s</ul>
      </div>

      <div class="ph" style="margin-top:44px">
        <b>Before you book</b>
        This is bodywork, not a medical diagnosis. If something is getting worse, follows an
        injury you have not had looked at, or comes with numbness, weakness or pain that wakes you
        at night, see a doctor first. Mention any injury or condition when you book and again at
        the start of the session &mdash; some change how the work is done.
      </div>
    </div>
  </section>
""" % dict(prices=prices, who=who, lh=c["left_h"], left=left, rh=c["right_h"], right=right,
           conds=conds)


# ── About / team ─────────────────────────────────────────────────────────────

ABOUT = head("The studio", "Two people, one plan.",
             "Bodywork and fitness in the same room, rather than sending people between "
             "them.") + """
  <section style="border-top:0; padding-top:38px">
    <div class="wrap">
%(team)s
    </div>
  </section>

  <section>
    <div class="wrap grid-2">
      <div>
        <h3>Randi, in her own words</h3>
        <p style="color:var(--ink-2); margin-top:14px">&ldquo;I have always worked in
        sports/orthopedic types of environments. I enjoy working with clients to find and correct
        dysfunctional patterns in their bodies, promoting quicker healing in recovery and
        increased mobility and physical performance.&rdquo;</p>
        <p style="color:var(--ink-2)">&ldquo;My massages reach deep tissues, using passive and
        active range of motion, slow and deep pressure, and myofascial work. I have worked on many
        different types of issues, from chronic to very acute, from localized to broad &mdash; and
        many different people, from kids in intramural sports to ultramarathon runners, from
        people post injury or operation to people needing palliative care.&rdquo;</p>
        <p style="color:var(--ink-2)">&ldquo;I love connecting with clients and learning what they
        need and how I can help, and I will meet the client wherever they're at. I work on deep
        tissues after trust has been built, superficial soft tissues have been released, and the
        client's nervous system is relaxed. I am always working with my clients rather than just
        on them.&rdquo;</p>
      </div>
      <div>
        <h3>How the studio got here</h3>
        <p style="color:var(--ink-2); margin-top:14px">Randi qualified in 2012 at the Southwest
        Institute of Healing Arts in Tempe, and started practising at Koenke Chiropractic &mdash;
        now North Mesa Performance Chiropractic &mdash; under the mentorship of Wendy Hering.</p>
        <p style="color:var(--ink-2)">In 2017 she moved to New Haven, Connecticut for a graduate
        degree in Linguistics, and found that her calling was back in the massage world. She took
        over a therapeutic and sports massage business there and ran it until she missed Arizona
        too much.</p>
        <p style="color:var(--ink-2)">She came back with a plan for a studio built around
        integrated bodywork and fitness &mdash; a plan Wendy shared, and together they are
        building it.</p>
        <div class="fact-list">
          <div class="fact"><span>Randi's licence</span><b>AZ MT-50515</b></div>
          <div class="fact"><span>Qualified</span><b>2012</b></div>
          <div class="fact"><span>Trained at</span><b>Southwest Institute of Healing Arts, Tempe</b></div>
          <div class="fact"><span>Also licensed</span><b>Connecticut</b></div>
        </div>
      </div>
    </div>
  </section>

""" % dict(team=team_grid())


FIRST_VISIT = head("Your first visit", "No mystery, no awkwardness.",
                   "Most people who have never had clinical bodywork hesitate because nobody "
                   "tells them what actually happens. So here is the whole thing.") + """
  <section style="border-top:0; padding-top:38px">
    <div class="wrap grid-2">
      <div class="steps">
        <div class="step">
          <div>
            <h3>We talk first</h3>
            <p>A few minutes on what hurts, when it started, what makes it worse, and what you
            need to get back to doing. You stay dressed for this part.</p>
          </div>
        </div>
        <div class="step">
          <div>
            <h3>Then we work</h3>
            <p>Hands-on treatment for the rest of the session, at a pressure you set. You are
            draped throughout, and you can change your mind about anything at any point.</p>
          </div>
        </div>
        <div class="step">
          <div>
            <h3>You leave with a plan</h3>
            <p>What was found, what to do between now and next time, and an honest answer on
            whether you need to come back at all.</p>
          </div>
        </div>
      </div>
      <div>
        <h3>Practical things</h3>
        <p><a href="/contact/#directions">Parking, entrance directions and Google Maps</a></p>
        <p style="color:var(--ink-2); margin-top:14px"><strong>Arrive 5&ndash;10 minutes early</strong>
        for a first visit so the paperwork does not eat into your table time.</p>
        <p style="color:var(--ink-2)"><strong>Wear whatever is comfortable.</strong> You will be
        draped throughout and only the area being worked on is uncovered. Stretch and corrective
        exercise sessions are done fully clothed, so bring something you can move in.</p>
        <p style="color:var(--ink-2)"><strong>Say if anything is wrong.</strong> Pressure,
        temperature, the music, a spot you would rather was left alone. None of it is awkward and
        all of it makes the session better.</p>
        <p style="color:var(--ink-2)"><strong>Mention injuries and conditions</strong> when you
        book and again at the start. Some change how the work is done, and a few mean it should
        wait or be cleared with your doctor first.</p>
      </div>
    </div>
  </section>

%(faq)s
"""


CONTACT = head("Hours &amp; directions", "Finding the studio.",
               "Open seven days, with weekday evenings until 7pm &mdash; which most studios "
               "nearby do not do.") + """
  <section style="border-top:0; padding-top:38px">
    <div class="wrap grid-2">
      <div>
        <h3 style="margin-bottom:20px">Opening hours</h3>
%(hours)s
        <p style="margin-top:18px; color:var(--ink-3); font-size:16px">
          Weekday evenings run to 7pm. Thursday opens an hour earlier, at 8am.
        </p>
      </div>
      <div>
        <h3 style="margin-bottom:20px">Where to find us</h3>
        <p style="color:var(--ink-2); font-size:19px">%(address)s</p>
        <p><a class="tel" href="tel:+14809440494" style="font-size:19px">(480) 944-0494</a></p>
        <p style="margin-top:22px">
          <a class="btn btn-primary" href="/book/">Book a session</a>
        </p>
        <div id="directions" style="margin-top:26px">
          <h3>Parking and the entrance</h3>
          %(directions)s
          <a class="btn btn-ghost" href="%(map_directions)s" target="_blank" rel="noopener">Get directions in Google Maps</a>
        </div>
      </div>
    </div>
  </section>
  <section style="padding-top:0"><div class="wrap">
    <h2>Google Maps</h2>
    <p>The map takes you to Speakeasy Salon Suites. Rebound Body Studio is inside, in Suite 109.</p>
    <iframe title="Google Maps: Speakeasy Salon Suites, 1138 N. Higley Road, Mesa"
      src="https://www.google.com/maps/embed?origin=mfe&amp;pb=!1m2!2m1!1s%(map_query)s"
      width="100%%" height="420" style="border:0;border-radius:14px" loading="eager"
      referrerpolicy="no-referrer-when-downgrade" allowfullscreen></iframe>
  </div></section>
""" % dict(hours=HOURS_TABLE, address=ADDRESS, directions=DIRECTIONS, map_query=MAP_QUERY, map_directions=MAP_DIRECTIONS)


BOOK = head("Booking", "Book your session.",
            "Open seven days. Massage from $70, stretch and core work from $50.") + """
  <section style="border-top:0; padding-top:38px"><div class="wrap">
    <iframe src="/booking/public/index.html" title="Request an appointment at Rebound Body Studio"
      width="100%" height="1100" style="border:none;border-radius:8px;margin-bottom:30px"></iframe>
    <p><a href="/booking/public/index.html">Open the booking form in a full page</a></p>
    <div class="mb-grid">
      <div class="mb-card"><h3>Three-session packages</h3>
        <p>Three 60-minute massages for $265, or three 90-minute massages for $360. Order now and choose your appointments later.</p>
        <a class="btn btn-ghost" href="/booking/public/packages.html?v=20260912-square-3">Order a package</a></div>
      <div class="mb-card"><h3>Already have a package?</h3>
        <p>Use the private link in your email to see your remaining sessions and book with a credit.</p>
        <a class="btn btn-ghost" href="/booking/public/packages.html?v=20260912-square-3#recover">Find my package</a></div>
      <div class="mb-card"><h3>Your visit</h3>
        <p>Randi reviews every appointment request. Please arrive 5&ndash;10 minutes early.</p>
        <a class="btn btn-ghost" href="/contact/#directions">Parking and directions</a></div>
    </div>
    <p>Wendy's corrective exercise sessions are arranged separately. Call <a href="tel:+14809440494">(480) 944-0494</a> to get in touch.</p>
    <p>Check your email for confirmation and reminders. Your confirmation includes a link to manage or cancel your appointment.</p>
  </div></section>
"""

DEALS = head("Packages", "A little more time for you.",
             "Choose a three-session package now, and book each massage when you are ready.") + """
  <section style="border-top:0;padding-top:38px"><div class="wrap">
%(packages)s
    <div class="mb-grid" style="margin-top:32px">
      <div class="mb-card"><h3>1. Choose your package</h3><p>No appointment is required to place an order.
      The studio arranges payment through Square or in person. Your order shows payment pending until it is recorded.</p></div>
      <div class="mb-card"><h3>2. Book when you are ready</h3><p>After payment, use your private link to book eligible
      therapeutic, sports or myofascial massages at your package's session length. Redeem within one year of payment.</p></div>
      <div class="mb-card"><h3>3. Keep track</h3><p>See available, reserved and used sessions, plus your session history.
      A cancelled or declined appointment returns its package credit. The studio's cancellation policy still applies.</p></div>
    </div>
    <h2 style="margin-top:38px">A package can be a gift.</h2>
    <p>Share your private package link with the recipient. They can book using their own name and email.
    Anyone with that link can use the remaining sessions, so share it only with someone you choose.</p>
    <p><a href="/booking/public/packages.html?v=20260912-square-3#recover">Find my package and check my balance</a></p>
    <p>For other gift amounts, call <a href="tel:+14809440494">(480) 944-0494</a>.</p>
  </div></section>
""" % dict(packages=package_grid())


# ── Cupping and scraping: written, staged, deliberately not published ────────
# She has the cups. She does not yet have the 4-hour certificate her insurer wants,
# and Wendy reportedly does both but nothing about Wendy's certifications is
# published anywhere we can verify. So: no claim about Wendy, and nothing offered
# until the certificate exists. Flip CUPPING_LIVE at the top of this file and the
# page appears in the menu, the footer and the sitemap. The one line to add to the
# footer in build_site.py when that happens is marked there.
#
# The tone matches /how-massage-works/ rather than the service pages, because the
# honest version of cupping is more interesting than the marketed one, and this
# site has already committed to not selling folklore.
CUPPING = head("Cupping &amp; scraping", "Cupping and scraping, honestly.",
               "Two techniques people ask for by name. Here is what they actually do, what the "
               "marks are, and what they will not fix.") + """
  <section style="border-top:0; padding-top:38px">
    <div class="wrap">
      <div class="grid-2">
        <div>
          <h3>Cupping</h3>
          <p style="color:var(--ink-2)">Every other technique in this studio pushes into tissue.
          Cupping is the one that pulls. A cup is placed on the skin and the air inside it
          decompressed, which lifts the skin and the fascia underneath away from the layers below
          instead of compressing them together.</p>
          <p style="color:var(--ink-2)">That matters for tissue that has become stuck to what is
          beneath it &mdash; around an old surgical site, or an area that has been guarded for
          years. Compression can only work one direction. Decompression is the other.</p>
          <p style="color:var(--ink-2)">Cups are usually left in place for a few minutes, or slid
          across an oiled area so the lift travels. Neither hurts. Most people describe it as a
          strong pull rather than pressure.</p>
        </div>
        <div>
          <h3>The marks are not bruises</h3>
          <p style="color:var(--ink-2)">A bruise is bleeding caused by impact. Cupping marks are
          not that: nothing has struck you, and nothing is torn. The suction draws blood and
          interstitial fluid toward the surface, and the circle it leaves is the colour of that
          fluid sitting where it can be seen.</p>
          <p style="color:var(--ink-2)">They are painless, they fade over several days, and their
          darkness is not a score. There is a persistent piece of folklore that darker circles mean
          more toxins or a worse problem. There is no evidence for it. Skin, hydration and how
          long the cup sat there explain most of the variation.</p>
          <p style="color:var(--ink-2)">Worth knowing before a wedding, a holiday or anything else
          where you would rather not have circles on your back for a week. Say so and the work
          goes elsewhere.</p>
        </div>
      </div>

      <div class="grid-2" style="margin-top:46px">
        <div>
          <h3>Scraping, or IASTM</h3>
          <p style="color:var(--ink-2)">Instrument-assisted soft tissue mobilisation &mdash;
          scraping, gua sha, Graston, depending on who is selling it. A smooth-edged tool is drawn
          across the tissue, which lets a therapist feel texture through the instrument that
          fingers alone can miss, and lets them work an area for longer than hands would last.</p>
          <p style="color:var(--ink-2)">It can leave small red speckling. Same story as the cups:
          surface capillaries, not damage, and it settles within a few days.</p>
        </div>
        <div>
          <h3>What neither of them does</h3>
          <p style="color:var(--ink-2)">Neither removes toxins. Neither breaks down scar tissue or
          adhesions &mdash; the forces required to tear collagen would injure you long before they
          reorganised anything, and this is worth saying plainly because it is the claim most often
          made for both.</p>
          <p style="color:var(--ink-2)">What they plausibly do is change how an area feels and how
          it glides, for a while, which is enough to work in. That is the same honest ceiling every
          other technique here has, and it is on the
          <a href="/how-massage-works/">physiology page</a> in more detail.</p>
        </div>
      </div>

      <div class="ph" style="margin-top:44px">
        <b>Who these are not for</b>
        Cupping and scraping are not appropriate over broken or inflamed skin, on anyone taking
        blood thinners, or where there is a bleeding disorder, DVT risk, or active infection in the
        area. Mention any of these when you book. They also mark, so tell her if that is a problem
        for you.
      </div>
    </div>
  </section>
"""


HOW = head("The physiology", "What is actually happening under the hands.",
           "Massage is sold with a lot of folklore. Here is the version that holds up: what "
           "pressure does to tissue, why it changes how much something hurts, and what it "
           "honestly cannot do.")


PAGES = [
    dict(slug="/", nav="Home",
         title="Rebound Body Studio — sports & orthopedic massage, Mesa AZ",
         desc="Sports and orthopedic massage, stretch and corrective exercise in Mesa, Arizona. "
              "Sessions from 30 to 120 minutes, open seven days. Randi M., LMT, AZ MT-50515.",
         body=HOME),

    dict(slug="/sessions/", nav="Services", crumb="Services &amp; pricing",
         title="Services & pricing — Rebound Body Studio, Mesa AZ",
         desc="Massage from $70, deep core and stretch work from $50, corrective exercise from "
              "$75. Full price list for Rebound Body Studio in Mesa, Arizona.",
         body=SESSIONS),

    dict(slug="/therapeutic-massage/", nav="Therapeutic", crumb="Therapeutic massage",
         title="Therapeutic massage in Mesa — Rebound Body Studio",
         desc="Customised therapeutic massage in Mesa, Arizona, combining deep tissue, myofascial "
              "release and mobility work. 30 to 120 minutes, from $70.",
         body=service_page("therapeutic-massage")),

    dict(slug="/sports-massage/", nav="Sports", crumb="Sports massage",
         title="Sports massage in Mesa — Rebound Body Studio",
         desc="Sports and recovery massage in Mesa, Arizona, for training recovery, restricted "
              "range of motion and return to sport. 30 to 120 minutes, from $70.",
         body=service_page("sports-massage")),

    dict(slug="/myofascial-unwinding/", nav="Myofascial", crumb="Myofascial unwinding",
         title="Myofascial unwinding in Mesa — Rebound Body Studio",
         desc="Slow, sustained myofascial release and unwinding in Mesa, Arizona, for long-held "
              "restriction, scar tissue and bodies that do not respond well to deep pressure. "
              "30 to 120 minutes, from $70.",
         body=service_page("myofascial-unwinding")),

    dict(slug="/deep-core-therapy/", nav="Deep core", crumb="Deep core therapy",
         title="Deep core therapy in Mesa — Rebound Body Studio",
         desc="Focused work for the psoas, hip flexors, abdominal tissue and diaphragm in Mesa, "
              "Arizona. 30 or 45 minutes, from $50.",
         body=service_page("deep-core-therapy")),

    dict(slug="/stretch-session/", nav="Stretch", crumb="Stretch sessions",
         title="Assisted stretch sessions in Mesa — Rebound Body Studio",
         desc="Assisted stretching, mobility and joint range of motion work in Mesa, Arizona. "
              "30 to 60 minutes, from $50.",
         body=service_page("stretch-session")),

    dict(slug="/corrective-exercise/", nav="Corrective", crumb="Corrective exercise",
         title="Corrective exercise training in Mesa — Rebound Body Studio",
         desc="Corrective exercise training in Mesa, Arizona with a certified personal trainer, "
              "for movement imbalances and recurring injury. From $75.",
         body=service_page("corrective-exercise")),

    dict(slug="/deals/", nav="Deals", crumb="Special offers",
         title="Special offers & packages — Rebound Body Studio, Mesa AZ",
         desc="Three 60-minute massages for $265 or three 90-minute for $360, plus gift "
              "certificates. Current deals at Rebound Body Studio in Mesa, Arizona.",
         body=DEALS),

    dict(slug="/how-massage-works/", nav="How it works", crumb="How massage works",
         title="How massage actually works — Rebound Body Studio",
         desc="What pressure does to skin, fascia and muscle, why it reduces pain at the spinal "
              "cord, and which common claims about massage are false.",
         body=HOW),

    dict(slug="/first-visit/", nav="First visit", crumb="Your first visit",
         title="Your first visit — Rebound Body Studio",
         desc="What actually happens at a first massage appointment in Mesa: the consultation, "
              "the session, draping, pressure, and what you leave with.",
         body=FIRST_VISIT),

    dict(slug="/about/", nav="About", crumb="The studio",
         title="About the studio — Rebound Body Studio, Mesa AZ",
         desc="Randi M. (LMT, AZ MT-50515) and Wendy Hering. Bodywork and fitness "
              "in the same room in Mesa, Arizona.",
         body=ABOUT),

    dict(slug="/book/", nav="Book", crumb="Book",
         title="Book a session — Rebound Body Studio, Mesa AZ",
         desc="Book massage, stretch or corrective exercise in Mesa. Open seven days with weekday "
              "evenings until 7pm. (480) 944-0494.",
         body=BOOK),

    dict(slug="/contact/", nav="Contact", crumb="Hours & directions",
         title="Hours & directions — Rebound Body Studio, Mesa AZ",
         desc="Rebound Body Studio, 1138 N. Higley Rd Suite 109, Mesa AZ 85205. Open seven days, "
              "weekday evenings until 7pm. (480) 944-0494.",
         body=CONTACT),
]

if CUPPING_LIVE:
    PAGES.insert(8, dict(
        slug="/cupping-and-scraping/", nav="Cupping", crumb="Cupping &amp; scraping",
        title="Cupping and scraping (IASTM) in Mesa — Rebound Body Studio",
        desc="What cupping and scraping actually do, what the marks are, and what they will not "
             "fix. Honest answers from a licensed massage therapist in Mesa, Arizona.",
        body=CUPPING))
