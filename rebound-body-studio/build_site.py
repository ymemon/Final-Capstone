# -*- coding: utf-8 -*-
"""Build the Rebound Body Studio site as multiple pages.

WHY A GENERATOR
Yasir: "I'm not too sure about a single page website with too much information,
we need to have multiple pages with rich content." Nine pages hand-maintained
means nine copies of the header, nav, footer and 16KB of CSS, and they drift
apart within a week. One builder, one shared chrome, content per page.

THE CALENDAR
Her real MassageBook diary is embedded on the booking page as of 2026-09-09, when
she sent the account-specific /widget/ URLs. Those turned out to be the exception
to the framing rules her public pages enforce: no X-Frame-Options and no
frame-ancestors header on /widget/services, /widget/reviews or
/widget/reviews-btn, so they embed cleanly.

REMOVED WITH IT: the interim month-grid calendar this file used to build from her
opening hours. It showed which days she works, which was true but was never the
question - a person booking at 11pm wants to know which slots are free. Two
calendars on one page, one of them live and one of them inferred, is how a site
ends up contradicting itself. Git has it if it is ever wanted back.
"""
import io
import os
import re
import shutil
import time

HERE = os.path.dirname(os.path.abspath(__file__))
BUILD = os.path.join(HERE, "build")
OUT = os.path.join(BUILD, "site")

MB = "https://www.massagebook.com/therapists/rebound-sports-therapy-l-c-?src=external"
PHONE_HREF = "tel:+14809440494"
PHONE = "(480) 944-0494"
IG = "https://www.instagram.com/rebound.bodystudio/"

BASE_CSS = io.open(os.path.join(BUILD, "base.css"), encoding="utf-8").read()


def frag(name):
    return io.open(os.path.join(BUILD, "frag-%s.html" % name), encoding="utf-8").read()


# ── Extra CSS: multi-page chrome, the calendar, and page furniture ────────────
EXTRA_CSS = """
/* ── Her actual brand palette ─────────────────────────────────────────────────
   She already HAS a logo - teal script with a yellow wave - which only came to
   light once her staff page was read. The earlier eucalyptus-and-ochre scheme
   was invented for her, and a site whose colours fight the logo in its own
   header is a site that looks borrowed.

   So the tokens are rebuilt from the logo itself: sampled teal #0090b0, deep
   blue #005080 and yellow #f0e010. The teal is taken to a deeper, less candied
   register for text and buttons so the clinical positioning survives, and the
   raw yellow - unreadable as a hairline on white - is deepened for the measure
   marks while the bright original stays available for larger shapes. Neutrals
   carry a cool bias toward the blue so they read as chosen.

   UPDATED 2026-09-09: Randi asked for more dark blue to read sportier and more
   orthopedic. The accent moves from the bright teal down onto the #005080 that
   is already in her logo; the teal stays as the lighter hover and dark-mode
   tone so the logo still sits in its own colour family. She chose the softer
   logo deliberately - the studio is in salon suites and she does not want to
   look unapproachable to salon clients - so this stays a shift, not a rebrand. */
:root{
  --paper:#f2f6f7;
  --surface:#ffffff;
  --sunk:#e6eef0;
  --ink:#0e1c28;
  --ink-2:#39515f;
  --ink-3:#68808d;
  --line:#d7e2e6;
  --accent:#0a5474;
  --accent-ink:#063d57;
  --on-accent:#ffffff;
  --ochre:#a8830f;
  --shadow:0 1px 2px rgba(17,33,42,.04), 0 18px 40px -24px rgba(17,33,42,.30);
}
@media (prefers-color-scheme: dark){
  :root:not([data-theme="light"]){
    --paper:#0c161b; --surface:#122027; --sunk:#18272f;
    --ink:#e6eff2; --ink-2:#a3b8c1; --ink-3:#7a8f99;
    --line:#21343d; --accent:#5cb2d0; --accent-ink:#8fd3e6;
    --on-accent:#0c161b; --ochre:#e0be3c;
    --shadow:0 1px 2px rgba(0,0,0,.4), 0 18px 40px -24px rgba(0,0,0,.8);
  }
}
:root[data-theme="dark"]{
  --paper:#0c161b; --surface:#122027; --sunk:#18272f;
  --ink:#e6eff2; --ink-2:#a3b8c1; --ink-3:#7a8f99;
  --line:#21343d; --accent:#5cb2d0; --accent-ink:#8fd3e6;
  --on-accent:#0c161b; --ochre:#e0be3c;
  --shadow:0 1px 2px rgba(0,0,0,.4), 0 18px 40px -24px rgba(0,0,0,.8);
}

/* Her logo, in place of the typeset wordmark it was standing in for. */
.brand img{display:block; height:40px; width:auto}
@media(max-width:520px){ .brand img{height:36px} }

.portrait{width:100%; height:auto; display:block; border:var(--rule); border-radius:3px}
.fact-list{margin-top:22px; border-top:var(--rule)}
.fact{display:flex; justify-content:space-between; gap:18px; padding:12px 0;
  border-bottom:var(--rule); font-size:16px}
.fact span{color:var(--ink-3)}
.fact b{font-family:"Archivo",sans-serif; font-size:14.5px; color:var(--ink); text-align:right}

/* ── Service menu ───────────────────────────────────────────────────────────
   Six services with several durations each. Rendered as a menu of equal cards
   rather than competing "packages", because none of them is the upsell. */
.svc-grid{display:grid; grid-template-columns:repeat(3,1fr); gap:22px}
@media(max-width:1000px){ .svc-grid{grid-template-columns:repeat(2,1fr)} }
@media(max-width:700px){ .svc-grid{grid-template-columns:1fr} }
.svc-card{border:var(--rule); border-radius:3px; background:var(--surface); padding:24px;
  display:flex; flex-direction:column}
.svc-card h3{margin-bottom:10px}
.svc-card h3 a{color:var(--ink); text-decoration:none}
.svc-card h3 a:hover{color:var(--accent-ink)}
.svc-card p{font-size:16px; color:var(--ink-2); margin:0 0 16px}
.svc-card .by{font-size:14.5px; color:var(--ochre); margin:-8px 0 16px}
.dur-list{margin-top:auto; border-top:var(--rule)}
.dur-row{display:flex; justify-content:space-between; align-items:baseline; gap:14px;
  padding:9px 0; border-bottom:1px solid var(--line)}
.dur-row:last-child{border-bottom:0}
.dur-min{font-family:"Archivo",sans-serif; font-size:14px; font-weight:600; color:var(--ink-2)}
.dur-amt{font-family:"Archivo",sans-serif; font-size:18px; font-weight:800; letter-spacing:-.02em;
  font-variant-numeric:tabular-nums}

/* On a service page the durations sit in one row beside the booking button. */
.price-strip{display:flex; align-items:center; gap:30px; flex-wrap:wrap; padding:24px;
  border:var(--rule); border-radius:3px; background:var(--surface)}
.price-strip-h{font-family:"Archivo",sans-serif; font-size:11.5px; font-weight:700;
  letter-spacing:.14em; text-transform:uppercase; color:var(--ink-3)}
.dur-list.wide{display:flex; gap:26px; flex-wrap:wrap; margin:0; border-top:0; flex:1}
.dur-list.wide .dur-row{border-bottom:0; padding:0; gap:9px}
.price-strip .btn{margin-left:auto}
@media(max-width:700px){ .price-strip .btn{margin-left:0; width:100%} }
.by{font-size:15.5px; color:var(--ochre)}

/* ── Packages ───────────────────────────────────────────────────────────── */
.pkg-grid{display:grid; grid-template-columns:repeat(2,1fr); gap:22px}
@media(max-width:760px){ .pkg-grid{grid-template-columns:1fr} }
.pkg{border:var(--rule); border-radius:3px; background:var(--surface); padding:26px;
  position:relative; display:flex; flex-direction:column}
.pkg-save{position:absolute; top:0; right:0; background:var(--ochre); color:#fff;
  font-family:"Archivo",sans-serif; font-size:11px; font-weight:700; letter-spacing:.1em;
  text-transform:uppercase; padding:6px 12px}
.pkg h3{margin:0 0 6px; padding-right:90px}
.pkg-amt{font-family:"Archivo",sans-serif; font-weight:800; font-size:34px; letter-spacing:-.03em;
  font-variant-numeric:tabular-nums; margin-bottom:10px}
.pkg p{font-size:16px; color:var(--ink-2); margin:0 0 20px}
.pkg .btn{margin-top:auto; align-self:flex-start}

/* ── Team ───────────────────────────────────────────────────────────────── */
.team-grid{display:grid; grid-template-columns:repeat(3,1fr); gap:26px}
@media(max-width:860px){ .team-grid{grid-template-columns:1fr} }
.member h3{margin:16px 0 4px}
.member .role{font-family:"Archivo",sans-serif; font-size:14.5px; font-weight:600;
  color:var(--accent-ink)}
.member .cred{font-family:"Archivo",sans-serif; font-size:12px; font-weight:600;
  letter-spacing:.08em; text-transform:uppercase; color:var(--ochre); margin-top:5px}
.member p{font-size:16px; color:var(--ink-2); margin:12px 0 0}
.portrait-ph{aspect-ratio:4/3; border:1.5px dashed var(--line); border-radius:3px;
  background:var(--sunk); display:grid; place-items:center}
.portrait-ph span{font-family:"Archivo",sans-serif; font-size:11.5px; font-weight:700;
  letter-spacing:.14em; text-transform:uppercase; color:var(--ink-3)}

.lic{margin-top:12px; font-size:14px; color:var(--ink-3); line-height:1.5}

/* ── Multi-page chrome ───────────────────────────────────────────────────── */
nav.links a.here{color:var(--accent-ink); position:relative}
nav.links a.here::after{content:""; position:absolute; left:0; right:0; bottom:-6px; height:2px;
  background:var(--ochre)}

.crumbs{font-family:"Archivo",sans-serif; font-size:13px; color:var(--ink-3); padding:22px 0 0}
.crumbs a{color:var(--ink-3); text-decoration:none}
.crumbs a:hover{color:var(--accent-ink)}
.crumbs span{margin:0 8px; opacity:.6}

.page-head{padding:34px 0 8px}
.page-head h1{margin:16px 0 0}
.page-head .lede{font-size:clamp(19px,2vw,21px); color:var(--ink-2); margin-top:20px; max-width:56ch}

/* Cross-links between pages. A multi-page site only works if the pages point at
   each other; otherwise every page is a dead end and people bounce. */
.next-grid{display:grid; grid-template-columns:repeat(3,1fr); gap:20px; margin-top:14px}
@media(max-width:860px){ .next-grid{grid-template-columns:1fr} }
.next-card{border:var(--rule); border-radius:3px; background:var(--surface); padding:22px;
  text-decoration:none; display:block; transition:transform .15s ease, border-color .15s ease}
.next-card:hover{transform:translateY(-2px); border-color:var(--accent)}
.next-card b{display:block; font-family:"Archivo",sans-serif; font-size:17px; color:var(--ink);
  margin-bottom:7px}
.next-card span{font-size:15.5px; color:var(--ink-2); line-height:1.55}

"""

# ── Shared chrome ────────────────────────────────────────────────────────────
# Seven service pages will not fit in a nav bar. Services is the hub; the
# individual pages are reached from it and from the footer, which is also where
# search engines will find them.
NAV = [
    ("Services", "/sessions/"),
    ("Deals", "/deals/"),
    ("How it works", "/how-massage-works/"),
    ("First visit", "/first-visit/"),
    ("About", "/about/"),
    ("Map &amp; directions", "/contact/"),
]

BASE_JS = """
  document.getElementById('yr').textContent = new Date().getFullYear();
  var hdr = document.getElementById('hdr');
  addEventListener('scroll', function () {
    hdr.classList.toggle('stuck', scrollY > 8);
  }, { passive: true });

  /* Open / closed from her real hours. "Are they open right now" is the question
     a first-time visitor actually has, and answering it saves a phone call. */
  (function () {
    var dot = document.getElementById('dot');
    if (!dot) return;
    var HOURS = { 0:[9,16.5], 1:[9,19], 2:[9,19], 3:[9,19], 4:[8,19], 5:[9,19], 6:[9,15] };
    var DAYS = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
    function clock(h) {
      var hh = Math.floor(h), mm = Math.round((h - hh) * 60);
      var ap = hh >= 12 ? 'pm' : 'am', h12 = ((hh + 11) % 12) + 1;
      return mm ? h12 + ':' + String(mm).padStart(2, '0') + ap : h12 + ap;
    }
    function paint() {
      var now = new Date(), d = now.getDay(), t = now.getHours() + now.getMinutes() / 60;
      var span = HOURS[d], open = t >= span[0] && t < span[1];
      dot.classList.toggle('on', open);
      document.getElementById('status-text').textContent =
        open ? 'Open now until ' + clock(span[1])
             : t < span[0] ? 'Opens ' + clock(span[0]) + ' today'
                           : 'Closed \\u00b7 opens ' + DAYS[(d + 1) % 7] + ' ' + clock(HOURS[(d + 1) % 7][0]);
      var rows = document.querySelectorAll('#hours-table tr');
      for (var i = 0; i < rows.length; i++) {
        rows[i].classList.toggle('today', Number(rows[i].dataset.d) === d);
      }
    }
    paint();
    setInterval(paint, 60000);
  }());
"""


def header(slug):
    links = ""
    for label, href in NAV:
        cls = ' class="here"' if href == slug else ""
        links += '      <a href="%s"%s>%s</a>\n' % (href, cls, label)
    mobile_links = '<a href="/">Home</a>\n' + links + '<a href="/book/">Book a session</a>\n<a href="/booking/public/packages.html?v=20260912-square-3#recover">My packages</a>'
    return """<header id="hdr">
  <div class="wrap bar">
    <a class="brand" href="/"><img src="/assets/logo.png" width="522" height="270"
       alt="Rebound Body Studio"></a>
    <nav class="links" aria-label="Main navigation">
%s    </nav>
    <div class="head-cta">
      <a class="tel" href="%s">%s</a>
      <a class="signin" href="/booking/public/packages.html?v=20260912-square-3#recover">My packages</a>
      <a class="btn btn-primary" href="/book/">Book</a>
    </div>
    <details class="mobile-menu">
      <summary>Menu <span aria-hidden="true">&#9776;</span></summary>
      <nav aria-label="Mobile navigation">%s</nav>
    </details>
  </div>
</header>""" % (links, PHONE_HREF, PHONE, mobile_links)


FOOTER = """<footer>
  <div class="wrap">
    <div class="foot">
      <div>
        <div class="brand" style="margin-bottom:14px"><span><b>Rebound</b><small>Body Studio</small></span></div>
        <div>1138 N. Higley Rd, Suite&nbsp;109<br>Mesa, AZ 85205</div>
        <div style="margin-top:10px"><a href="%(tel)s">%(phone)s</a></div>
        <div class="lic">Randi M., Licensed Massage Therapist<br>Arizona licence MT-50515</div>
      </div>
      <div>
        <strong>Services</strong>
        <div style="margin-top:12px">
          <a href="/therapeutic-massage/">Therapeutic massage</a><br>
          <a href="/sports-massage/">Sports massage</a><br>
          <a href="/myofascial-unwinding/">Myofascial unwinding</a><br>
          <a href="/deep-core-therapy/">Deep core therapy</a><br>
          <a href="/stretch-session/">Stretch sessions</a><br>
          <a href="/corrective-exercise/">Corrective exercise</a><br>
          <!-- When CUPPING_LIVE goes True in pages.py, uncomment this line:
          <a href="/cupping-and-scraping/">Cupping &amp; scraping</a><br> -->
          <a href="/sessions/">All prices</a><br>
          <a href="/deals/">Deals &amp; packages</a>
        </div>
      </div>
      <div>
        <strong>More</strong>
        <div style="margin-top:12px">
          <a href="/how-massage-works/">How massage works</a><br>
          <a href="/first-visit/">Your first visit</a><br>
          <a href="/about/">About the studio</a><br>
          <a href="/contact/">Hours &amp; directions</a>
        </div>
      </div>
      <div>
        <strong>Elsewhere</strong>
        <div style="margin-top:12px">
          <a href="%(ig)s" rel="noopener">Instagram</a><br>
          <a href="/book/">Book online</a>
        </div>
      </div>
    </div>
    <div class="legal">
      &copy; <span id="yr">2026</span> Rebound Sports Therapy L.C. trading as Rebound Body Studio.
      &nbsp;&middot;&nbsp; Site by <a href="https://azwebcorp.com/">AZ Web Corp</a>.
      <div style="margin-top:6px; font-style:italic">A design by Memon</div>
    </div>
  </div>
</footer>

<div class="mobile-book">
  <a class="btn btn-ghost" href="%(tel)s">Call</a>
  <a class="btn btn-primary" href="/book/">Book</a>
</div>""" % dict(tel=PHONE_HREF, phone=PHONE, ig=IG, mb=MB)


def crumbs(title, slug):
    if slug == "/":
        return ""
    return ('<div class="wrap"><div class="crumbs"><a href="/">Home</a><span>&rsaquo;</span>%s'
            '</div></div>' % title)


def next_cards(cards):
    out = '  <section><div class="wrap">\n    <div class="label">Keep reading</div>\n'
    out += '    <div class="next-grid">\n'
    for href, title, blurb in cards:
        out += ('      <a class="next-card" href="%s"><b>%s</b><span>%s</span></a>\n'
                % (href, title, blurb))
    return out + "    </div>\n  </div></section>\n"


import services as _svc
import graphics as _gfx

BUSINESS_LD = """{
  "@context": "https://schema.org",
  "@type": "HealthAndBeautyBusiness",
  "@id": "https://reboundbodystudio.com/#studio",
  "name": "Rebound Body Studio",
  "legalName": "Rebound Sports Therapy L.C.",
  "description": "Sports and orthopedic massage and bodywork in Mesa, Arizona.",
  "url": "https://reboundbodystudio.com/",
  "telephone": "+1-480-944-0494",
  "priceRange": "$95-$130",
  "address": {
    "@type": "PostalAddress",
    "addressLocality": "Mesa",
    "addressRegion": "AZ",
    "postalCode": "85205",
    "addressCountry": "US"
  },
  "sameAs": ["https://www.instagram.com/rebound.bodystudio/"],
  "openingHoursSpecification": [
    { "@type": "OpeningHoursSpecification", "dayOfWeek": ["Monday","Tuesday","Wednesday","Friday"], "opens": "09:00", "closes": "19:00" },
    { "@type": "OpeningHoursSpecification", "dayOfWeek": "Thursday", "opens": "08:00", "closes": "19:00" },
    { "@type": "OpeningHoursSpecification", "dayOfWeek": "Saturday", "opens": "09:00", "closes": "15:00" },
    { "@type": "OpeningHoursSpecification", "dayOfWeek": "Sunday", "opens": "09:00", "closes": "16:30" }
  ],
  "makesOffer": [
%(offers)s
  ],
  "employee": [
    { "@type": "Person", "name": "Randi M.", "jobTitle": "Licensed Massage Therapist",
      "hasCredential": { "@type": "EducationalOccupationalCredential",
        "credentialCategory": "license", "identifier": "AZ MT-50515" } },
    { "@type": "Person", "name": "Wendy Hering", "jobTitle": "Certified Personal Trainer" }
  ]
}"""


def render(page):
    slug = page["slug"]
    canonical = "https://reboundbodystudio.com" + slug
    extra_ld = ""
    for blob in page.get("ld", []):
        extra_ld += '<script type="application/ld+json">\n%s\n</script>\n' % blob

    js = BASE_JS

    return """<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<!-- Prototype. noindex until the real street address and licence details are
     confirmed - a wrong address indexed for a real local business is worse than
     no listing at all. -->
<title>%(title)s</title>
<meta name="description" content="%(desc)s">
<link rel="canonical" href="%(canonical)s">
<meta property="og:type" content="website">
<meta property="og:title" content="%(title)s">
<meta property="og:description" content="%(desc)s">
<meta property="og:url" content="%(canonical)s">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Archivo:wght@500;600;700;800&family=Newsreader:opsz,wght@6..72,400;6..72,500&display=swap">
<style>
%(css)s
%(extra)s
%(gfx)s
</style>
</head>
<body>

%(header)s

<main id="top">
%(crumbs)s
%(body)s
</main>

%(footer)s

<script type="application/ld+json">
%(business)s
</script>
%(extra_ld)s
<script>
%(js)s
</script>
</body>
</html>
""" % dict(title=page["title"], desc=page["desc"], canonical=canonical,
           css=BASE_CSS, extra=EXTRA_CSS, gfx=_gfx.CSS, header=header(slug),
           crumbs=crumbs(page.get("crumb", page["nav"]), slug),
           body=page["body"], footer=FOOTER,
           business=BUSINESS_LD % dict(offers=_svc.all_offers_ld()),
           extra_ld=extra_ld, js=js)


# The preview is served from a subdirectory of azwebcorp.com, but the pages are
# authored with root-absolute links (/book/, /assets/logo.png) so that they are
# correct the day the site moves to reboundbodystudio.com. Rewriting them at
# write time keeps the source clean: set BASE_PATH to "" for the real domain.
BASE_PATH = os.environ.get("REBOUND_BASE", "/preview/rebound")

# ---- Why every preview link carries ?b=<timestamp> --------------------------
# The gateway in front of azwebcorp.com serves this directory through Cloudflare
# with "Cache-Control: public, max-age=2678400" - 31 days - and sets that header
# itself, so it cannot be overridden from .htaccess (tried, 2026-09-09: the same
# header came back on a fresh cache MISS). The effect is nasty in a quiet way:
# you upload a new build, the client reloads, and sees the old one with nothing
# to indicate it. Caught here only because a screenshot of the deployed page
# still said "Five services" after the six-service menu had gone up.
#
# Cloudflare keys its cache on the full URL including the query string, so a
# build stamp on every internal link makes each build a fresh set of keys and a
# stale page becomes unservable. Preview only: on her own domain BASE_PATH is
# empty and nothing is stamped.
STAMP = os.environ.get("REBOUND_STAMP") or time.strftime("%Y%m%d%H%M")


def rebase(html):
    if not BASE_PATH:
        return html
    # poster is here alongside href/src for the same reason src is: the hero
    # video's poster="/assets/..." needs the same subdirectory + cache-bust
    # treatment or it 404s under /preview/rebound/ while src quietly works.
    html = re.sub(r'(href|src|poster)="/(?!/)', r'\1="%s/' % BASE_PATH, html)
    # The reduced-motion fallback is a CSS url(...) inside a style attribute,
    # not an href/src/poster - same 404-under-the-subdirectory trap, different
    # syntax, so it needs its own pass rather than falling out of the one above.
    html = re.sub(r"url\('/(?!/)", r"url('%s/" % BASE_PATH, html)
    html = html.replace('%s//' % BASE_PATH, '%s/' % BASE_PATH)

    def stamp(m):
        attr, url = m.group(1), m.group(2)
        if "?" in url:
            return m.group(0)
        if "#" in url:
            path, frag_ = url.split("#", 1)
            return '%s="%s?b=%s#%s"' % (attr, path, STAMP, frag_)
        return '%s="%s?b=%s"' % (attr, url, STAMP)

    def stamp_css_url(m):
        url = m.group(1)
        if "?" in url:
            return m.group(0)
        return "url('%s?b=%s')" % (url, STAMP)

    html = re.sub(r'(href|src|poster)="(%s[^"]*)"' % re.escape(BASE_PATH), stamp, html)
    return re.sub(r"url\('(%s[^']*)'\)" % re.escape(BASE_PATH), stamp_css_url, html)


def write(page):
    slug = page["slug"]
    d = OUT if slug == "/" else os.path.join(OUT, slug.strip("/"))
    if not os.path.isdir(d):
        os.makedirs(d)
    path = os.path.join(d, "index.html")
    out = rebase(render(page))
    io.open(path, "w", encoding="utf-8").write(out)
    return path, len(out.encode("utf-8"))


def main():
    import pages  # content lives next door so this file stays about structure
    if os.path.isdir(OUT):
        shutil.rmtree(OUT)
    os.makedirs(OUT)

    # Assets: her own portrait and her own logo.
    assets_src = os.path.join(HERE, "assets")
    assets_out = os.path.join(OUT, "assets")
    os.makedirs(assets_out)
    for f in ("randi.webp", "logo.png"):
        shutil.copy2(os.path.join(assets_src, f), os.path.join(assets_out, f))

    # Hero video: stock footage, hands and back only, chosen so no face or
    # location is identifiable - see the video/ folder for how it was cut
    # down from a 29MB source to a ~1.3MB loop.
    video_out = os.path.join(assets_out, "video")
    os.makedirs(video_out)
    for f in ("hero-massage.mp4", "hero-massage.webm", "hero-poster.jpg"):
        shutil.copy2(os.path.join(assets_src, "video", f), os.path.join(video_out, f))

    # Fragments lifted from the single-page prototype. The physiology section
    # arrives with its own sec-head, which would duplicate the page's own H1 and
    # lede, so that block is dropped on the way in.
    # Anchored on the closing tag's indentation: the block contains a nested
    # <div class="label">, so a plain non-greedy .*?</div> stops at the wrong
    # one and leaves the section unbalanced.
    science = re.sub(r'\n      <div class="sec-head">.*?\n      </div>\n', "\n",
                     frag("science"), count=1, flags=re.S)
    assert 'class="sec-head"' not in science, "sec-head not stripped from physiology fragment"

    for page in pages.PAGES:
        body = page["body"]
        if page["slug"] == "/how-massage-works/":
            body += science
        if "%(faq)s" in body:
            body = body % dict(faq=frag("faq"))
        page["body"] = body

    built = []
    for page in pages.PAGES:
        built.append(write(page))
    for path, size in built:
        print("  %-58s %6d bytes" % (os.path.relpath(path, OUT), size))
    print("%d pages" % len(built))


if __name__ == "__main__":
    main()
