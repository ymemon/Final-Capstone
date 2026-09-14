# -*- coding: utf-8 -*-
"""Generate four logo directions for Rebound Body Studio.

Emits:
  logos.html          a presentation sheet for the client, in the site's own
                      visual language, hosted alongside the prototype
  build/logo-N.html   one file per concept, sized for rasterising
  build/logo-N.png    rendered by headless Chrome, for embedding in email

Four genuinely different directions rather than four versions of one idea, so
she is choosing a route and not a detail. Each is drawn as vector, so whichever
she picks scales to a sign, a business card or a favicon without redrawing.
"""
import io, os, subprocess, sys

HERE = os.path.dirname(os.path.abspath(__file__))
BUILD = os.path.join(HERE, "build")
CHROME = r"C:\Program Files\Google\Chrome\Application\chrome.exe"

INK, ACCENT, OCHRE, PAPER, LINE = "#16211d", "#2f6b52", "#a8791f", "#f4f6f3", "#dce2dc"

# ── The four marks. Each is a 120x120 icon, drawn to sit beside the wordmark. ──

GONIOMETER = """
<g fill="none" stroke-linecap="round">
  <path d="M 68 96 A 44 44 0 0 0 51.1 61.3" stroke="{OCHRE}" stroke-width="3"/>
  <line x1="24" y1="96" x2="104" y2="96" stroke="{INK}" stroke-width="7"/>
  <line x1="24" y1="96" x2="70.8" y2="36.1" stroke="{ACCENT}" stroke-width="7"/>
</g>
<circle cx="24" cy="96" r="7.5" fill="{ACCENT}"/>
"""

TRAJECTORY = """
<g fill="none" stroke-linecap="round">
  <line x1="10" y1="100" x2="110" y2="100" stroke="{OCHRE}" stroke-width="3"/>
  <path d="M 14 30 C 28 72, 42 92, 58 92 C 76 92, 92 58, 104 18"
        stroke="{ACCENT}" stroke-width="7"/>
</g>
<circle cx="104" cy="18" r="8" fill="{INK}"/>
"""

MONOGRAM = """
<rect x="8" y="8" width="104" height="104" rx="6" fill="{ACCENT}"/>
<path d="M 104 46 A 42 42 0 0 0 66 15" fill="none" stroke="{OCHRE}" stroke-width="3.5"/>
<text x="60" y="60" text-anchor="middle" dominant-baseline="central"
      font-family="Archivo, Helvetica Neue, Arial, sans-serif" font-weight="800"
      font-size="46" letter-spacing="-1" fill="#ffffff">RB</text>
"""

# Force pressing into layered tissue - lifted straight from Figure 1 on the
# site, so the mark and the page are visibly one system.
STRATA = """
<g fill="none" stroke-linecap="round">
  <line x1="60" y1="16" x2="60" y2="36" stroke="{ACCENT}" stroke-width="6"/>
  <path d="M 50 28 L 60 40 L 70 28" stroke="{ACCENT}" stroke-width="6"/>
  <path d="M 12 52 C 38 52, 44 72, 60 72 C 76 72, 82 52, 108 52"
        stroke="{INK}" stroke-width="6"/>
  <path d="M 12 72 C 38 72, 46 86, 60 86 C 74 86, 82 72, 108 72"
        stroke="{INK}" stroke-width="5" opacity="0.62"/>
  <path d="M 12 92 C 38 92, 48 100, 60 100 C 72 100, 82 92, 108 92"
        stroke="{OCHRE}" stroke-width="4"/>
</g>
"""

CONCEPTS = [
    dict(n=1, key="goniometer", mark=GONIOMETER,
         name="The Goniometer",
         line="Measurement",
         blurb="The arc-and-pivot instrument used to measure how far a joint actually "
               "moves \u2014 your own trade's instrument, and the motif the website is "
               "already built around. It says clinical without saying medical, and no "
               "other massage studio in the valley is using it.",
         best="Strongest tie to the site. Best if you want to be read as orthopedic "
              "rather than spa from the first glance."),
    dict(n=2, key="trajectory", mark=TRAJECTORY,
         name="The Rebound",
         line="Return to form",
         blurb="The path of something dropping, landing, and coming back up higher than "
               "it arrived. It is the literal meaning of your name, drawn as movement, "
               "and it reads instantly without anyone having to work it out.",
         best="The most immediately likeable of the four, and the friendliest. Best if "
              "you want athletic and optimistic ahead of clinical."),
    dict(n=3, key="monogram", mark=MONOGRAM,
         name="The Stamp",
         line="RB monogram",
         blurb="A solid monogram with the goniometer arc cut through the corner. This is "
               "the one that survives being shrunk \u2014 a phone icon, a stamp on a gift "
               "certificate, embroidery on a polo, the little circle next to your name on "
               "Instagram.",
         best="Best if you want something practical you will never have to redraw. Works "
              "as a companion to any of the other three rather than only on its own."),
    dict(n=4, key="strata", mark=STRATA,
         name="Under the Hands",
         line="Pressure through tissue",
         blurb="Force pressing down through layers of tissue, each layer giving a little "
               "less than the one above it. It is taken directly from the diagram on your "
               "site explaining how the work actually reaches muscle, so the mark and the "
               "page say the same thing.",
         best="The most distinctive and the most owned \u2014 nobody else has this. Asks a "
              "little more of the viewer, and rewards them for it."),
]


def mark_svg(c, size=120):
    body = c["mark"].format(INK=INK, ACCENT=ACCENT, OCHRE=OCHRE)
    return ('<svg viewBox="0 0 120 120" width="%d" height="%d" role="img" '
            'aria-label="%s logo mark">%s</svg>' % (size, size, c["name"], body))


def lockup(c, size=120, wm=38, sub=12.5, gap=26):
    """Horizontal lockup: mark, then the name over the descriptor."""
    return (
        '<div class="lock" style="gap:%dpx">%s'
        '<div class="wm">'
        '<div class="wm-1" style="font-size:%.1fpx">REBOUND</div>'
        '<div class="wm-2" style="font-size:%.1fpx">Body Studio</div>'
        '</div></div>' % (gap, mark_svg(c, size), wm, sub))


PAGE_CSS = """
:root{--paper:%(PAPER)s;--surface:#ffffff;--ink:%(INK)s;--ink-2:#42514a;--ink-3:#71807a;
      --line:%(LINE)s;--accent:%(ACCENT)s;--accent-ink:#1f4a38;--ochre:%(OCHRE)s;}
*{box-sizing:border-box}
body{margin:0;background:var(--paper);color:var(--ink);
     font-family:"Newsreader",Georgia,serif;font-size:18px;line-height:1.68}
h1,h2,h3{margin:0;font-family:"Archivo",Arial,sans-serif;line-height:1.08;
         letter-spacing:-.022em;text-wrap:balance;font-weight:800}
h1{font-size:clamp(34px,4.6vw,54px)}
h2{font-size:26px}
p{margin:0 0 16px;max-width:66ch}
.wrap{max-width:1120px;margin:0 auto;padding:0 24px}
.label{font-family:"Archivo",sans-serif;font-size:11.5px;font-weight:700;letter-spacing:.18em;
       text-transform:uppercase;color:var(--accent-ink);display:flex;align-items:center;gap:11px}
.label::before{content:"";width:26px;height:13px;flex:none;border:1.5px solid var(--ochre);
       border-bottom:0;border-radius:26px 26px 0 0}
header{padding:56px 0 34px}
header p{color:var(--ink-2);margin-top:18px}
.lock{display:flex;align-items:center}
.wm-1{font-family:"Archivo",sans-serif;font-weight:800;letter-spacing:-.03em;line-height:1;
      color:var(--ink)}
.wm-2{font-family:"Archivo",sans-serif;font-weight:600;letter-spacing:.22em;
      text-transform:uppercase;color:var(--ink-3);margin-top:7px;line-height:1}
.card{border:1px solid var(--line);border-radius:3px;background:var(--surface);margin-bottom:34px;
      overflow:hidden}
.card .stage{padding:56px 44px;display:flex;justify-content:center;border-bottom:1px solid var(--line)}
.card .meta{display:grid;grid-template-columns:1fr 1fr;gap:34px;padding:28px 44px 34px}
@media(max-width:820px){.card .meta{grid-template-columns:1fr;gap:20px}
                        .card .stage{padding:38px 20px}}
.card h2{margin-bottom:4px}
.card .kicker{font-family:"Archivo",sans-serif;font-size:11.5px;font-weight:700;
      letter-spacing:.16em;text-transform:uppercase;color:var(--ochre);margin-bottom:10px}
.card p{font-size:16.5px;color:var(--ink-2);margin:0}
.card .best{font-size:16.5px;color:var(--ink-2);margin:0;padding-left:20px;
      border-left:2px solid var(--ochre)}
.tests{display:flex;align-items:center;gap:38px;flex-wrap:wrap;padding:22px 44px;
      background:%(PAPER)s;border-top:1px solid var(--line)}
.tests .t{display:flex;flex-direction:column;align-items:center;gap:9px}
.tests .cap{font-family:"Archivo",sans-serif;font-size:10px;font-weight:700;letter-spacing:.14em;
      text-transform:uppercase;color:var(--ink-3)}
.mono svg *{fill:%(INK)s!important;stroke:%(INK)s!important}
.mono svg rect{fill:%(INK)s!important}
.mono svg text{fill:#ffffff!important}
.rev{background:%(INK)s;padding:16px 20px;border-radius:3px}
.rev svg *{stroke:#ffffff!important}
.rev svg circle{fill:#ffffff!important}
.rev svg rect{fill:none!important;stroke:#ffffff!important;stroke-width:3!important}
.rev svg text{fill:#ffffff!important}
footer{padding:40px 0 64px;border-top:1px solid var(--line);margin-top:20px;
       color:var(--ink-3);font-size:15.5px}
footer em{font-style:italic}
""" % dict(PAPER=PAPER, INK=INK, LINE=LINE, ACCENT=ACCENT, OCHRE=OCHRE)


def build_sheet():
    cards = []
    for c in CONCEPTS:
        cards.append("""
  <div class="card">
    <div class="stage">%(lock)s</div>
    <div class="meta">
      <div>
        <div class="kicker">Option %(n)d &middot; %(line)s</div>
        <h2>%(name)s</h2>
        <p style="margin-top:12px">%(blurb)s</p>
      </div>
      <div><p class="best">%(best)s</p></div>
    </div>
    <div class="tests">
      <div class="t">%(icon48)s<span class="cap">App icon</span></div>
      <div class="t">%(icon24)s<span class="cap">Favicon</span></div>
      <div class="t mono">%(icon48b)s<span class="cap">One colour</span></div>
      <div class="t"><span class="rev">%(icon48c)s</span><span class="cap">Reversed</span></div>
    </div>
  </div>""" % dict(
            lock=lockup(c), n=c["n"], line=c["line"], name=c["name"],
            blurb=c["blurb"], best=c["best"],
            icon48=mark_svg(c, 48), icon24=mark_svg(c, 24),
            icon48b=mark_svg(c, 48), icon48c=mark_svg(c, 48)))

    return """<!doctype html>
<html lang="en"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Rebound Body Studio &mdash; Logo Options</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Archivo:wght@600;700;800&family=Newsreader:opsz,wght@6..72,400&display=swap">
<style>%(css)s</style></head><body>
<header><div class="wrap">
  <div class="label">Rebound Body Studio &middot; identity</div>
  <h1 style="margin:18px 0 0">Four directions for your logo.</h1>
  <p>Pick a direction, not a detail. Whichever you point at gets refined properly &mdash;
  spacing, weights, a stacked version for square spaces, and the files you will actually
  need. All four are drawn as vector, so they scale from a favicon to a window decal with
  no loss.</p>
  <p>There is no wrong answer here and no need to justify it. &ldquo;The second one, but I
  do not like the dot&rdquo; is a perfectly useful reply.</p>
</div></header>
<div class="wrap">%(cards)s</div>
<footer><div class="wrap">
  Prepared for Randi Martinez by AZ Web Corp, Gilbert, Arizona.<br>
  <em>A design by Memon</em>
</div></footer>
</body></html>""" % dict(css=PAGE_CSS, cards="".join(cards))


def build_single(c):
    """One concept on a plain field, sized for rasterising into the email."""
    return """<!doctype html>
<html lang="en"><head><meta charset="utf-8">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Archivo:wght@600;700;800&display=swap">
<style>%(css)s
html,body{height:100%%;margin:0;background:#ffffff}
.frame{height:100%%;display:flex;flex-direction:column;align-items:center;
       justify-content:center;gap:26px}
.cap{font-family:"Archivo",sans-serif;font-size:15px;font-weight:700;letter-spacing:.16em;
     text-transform:uppercase;color:%(OCHRE)s}
</style></head><body>
<div class="frame">
  <div class="cap">Option %(n)d &middot; %(name)s</div>
  %(lock)s
</div></body></html>""" % dict(css=PAGE_CSS, OCHRE=OCHRE, n=c["n"], name=c["name"],
                               lock=lockup(c, size=150, wm=48, sub=15, gap=32))


def main():
    if not os.path.isdir(BUILD):
        os.makedirs(BUILD)

    sheet = os.path.join(HERE, "logos.html")
    io.open(sheet, "w", encoding="utf-8").write(build_sheet())
    print("wrote", sheet)

    for c in CONCEPTS:
        f = os.path.join(BUILD, "logo-%d.html" % c["n"])
        io.open(f, "w", encoding="utf-8").write(build_single(c))
        png = os.path.join(BUILD, "logo-%d.png" % c["n"])
        subprocess.run([
            CHROME, "--headless=new", "--disable-gpu", "--no-sandbox",
            "--hide-scrollbars", "--force-color-profile=srgb",
            "--virtual-time-budget=5000", "--window-size=1000,268",
            "--user-data-dir=" + os.path.join(BUILD, "cdata"),
            "--screenshot=" + png, "file:///" + f.replace("\\", "/"),
        ], capture_output=True)
        ok = os.path.exists(png)
        print("  option %d %-16s %s" % (c["n"], c["key"],
                                        ("%d bytes" % os.path.getsize(png)) if ok else "FAILED"))
        if not ok:
            sys.exit("rasterise failed for option %d" % c["n"])


if __name__ == "__main__":
    main()
