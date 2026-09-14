# -*- coding: utf-8 -*-
"""Original vector graphics for the Rebound Body Studio home page.

WHAT THIS IS AND IS NOT
It is not photography. Photographs of the room and of the three of them need the
shoot that has been offered; stock would read as stock and undercut the whole
positioning. This is the other half - original marks the studio owns outright.

WHAT IS DELIBERATELY NOT ATTEMPTED
Figurative human anatomy. Six separate attempts at drawing hands in vector
produced mittens, claws and broken paths, and a bad drawing of hands is worse on
this page than none. Everything here is geometric or instrument-derived, which is
what actually worked earlier in the build: the goniometer, the tissue strata, the
logo marks.

Every colour is a CSS token so both themes resolve, and every drawn shape has an
explicit stroke or fill - an inherited fill is the classic invisible-SVG bug.
"""

# ── Service icons ────────────────────────────────────────────────────────────
# One mark per service, 64x64, built from the same vocabulary: an arc for range,
# a stack for tissue depth, a plumb line for alignment. They are siblings rather
# than six unrelated pictograms.

def _unwind():
    """A coil that opens out into a straight line: tissue letting go.

    Drawn from an equation rather than hand-fitted arc flags, because the six
    previous icons are geometric siblings and a hand-drawn spiral next to them
    reads as a seventh vocabulary. 1.8 turns, radius opening 4 -> 22.
    """
    import math
    pts = []
    for i in range(97):
        t = i / 96.0
        ang = -math.pi / 2 + t * 3.6 * math.pi
        r = 4 + t * 18
        pts.append("%.1f %.1f" % (30 + r * math.cos(ang), 33 + r * math.sin(ang)))
    return "M " + " L ".join(pts)


ICONS = {
    # Layered tissue with pressure entering: the general-purpose session.
    "therapeutic-massage": """
      <path class="i-line" d="M 10 26 C 22 26, 26 34, 32 34 C 38 34, 42 26, 54 26"/>
      <path class="i-line" d="M 10 38 C 22 38, 27 45, 32 45 C 37 45, 42 38, 54 38"/>
      <path class="i-warm" d="M 10 50 C 22 50, 28 55, 32 55 C 36 55, 42 50, 54 50"/>
      <path class="i-accent" d="M 32 8 L 32 19"/>
      <path class="i-accent" d="M 26 14 L 32 21 L 38 14"/>""",

    # A rebound trajectory: down, contact, and back up higher.
    "sports-massage": """
      <path class="i-accent" d="M 10 14 C 17 34, 22 46, 30 46 C 39 46, 47 30, 54 12"/>
      <circle class="i-dot" cx="54" cy="12" r="4"/>
      <path class="i-warm" d="M 8 54 L 56 54"/>""",

    # Nested protective arcs.
    "prenatal-massage": """
      <path class="i-line" d="M 14 50 A 20 20 0 1 1 50 50"/>
      <path class="i-accent" d="M 22 50 A 12 12 0 1 1 42 50"/>
      <path class="i-warm" d="M 8 54 L 56 54"/>""",

    # A coil unwinding: slow release rather than applied force.
    "myofascial-unwinding": """
      <path class="i-line" d="%s"/>
      <circle class="i-accent-f" cx="30" cy="29" r="3"/>
      <path class="i-warm" d="M 8 56 L 56 56"/>""" % _unwind(),

    # Concentric rings closing on a point: depth, not breadth.
    "deep-core-therapy": """
      <circle class="i-line" cx="32" cy="32" r="22"/>
      <circle class="i-line" cx="32" cy="32" r="14"/>
      <circle class="i-accent-f" cx="32" cy="32" r="6"/>
      <path class="i-warm" d="M 32 4 L 32 12"/>""",

    # An opening angle: range of motion increasing.
    "stretch-session": """
      <circle class="i-accent-f" cx="14" cy="50" r="4"/>
      <path class="i-line" d="M 14 50 L 56 50"/>
      <path class="i-accent" d="M 14 50 L 46 16"/>
      <path class="i-warm" d="M 40 50 A 26 26 0 0 0 32 33"/>""",

    # A plumb line against a drifted column: alignment.
    "corrective-exercise": """
      <path class="i-warm" d="M 32 8 L 32 56"/>
      <rect class="i-line" x="20" y="12" width="16" height="11" rx="2"/>
      <rect class="i-line" x="24" y="27" width="16" height="11" rx="2"/>
      <rect class="i-accent-s" x="19" y="42" width="16" height="11" rx="2"/>""",
}


def icon(slug):
    body = ICONS.get(slug)
    if not body:
        return ""
    return ('<svg class="svc-icon" viewBox="0 0 64 64" aria-hidden="true" focusable="false">%s'
            '</svg>' % body)


# ── Hero: the goniometer ─────────────────────────────────────────────────────
# The arc-and-degree instrument used to measure how far a joint actually moves.
# Her trade's own instrument, drawn to scale, and structure that encodes
# something true rather than decorating.

HERO = """<svg class="gonio" viewBox="0 0 900 230" role="img"
     aria-label="A goniometer, the arc instrument used to measure how far a joint moves">
  <path class="arc" d="M 80 196 A 210 210 0 0 1 500 196"/>
  <path class="sweep" d="M 80 196 A 210 210 0 0 1 185 14" pathLength="340"/>
  <g class="tick">
    <line x1="80" y1="196" x2="60" y2="196"/>
    <line x1="108" y1="134" x2="92" y2="126"/>
    <line x1="185" y1="76" x2="176" y2="60"/>
    <line x1="290" y1="54" x2="290" y2="36"/>
    <line x1="395" y1="76" x2="404" y2="60"/>
    <line x1="472" y1="134" x2="488" y2="126"/>
    <line x1="500" y1="196" x2="520" y2="196"/>
  </g>
  <line class="limb" x1="80" y1="196" x2="500" y2="196"/>
  <line class="limb" x1="80" y1="196" x2="185" y2="14"/>
  <circle class="pivot" cx="80" cy="196" r="7"/>
  <text class="g-lead" x="556" y="150">Measured, not guessed</text>
  <text class="g-sub" x="556" y="178">RANGE OF MOTION IS THE NUMBER THAT MOVES</text>
  <text class="g-sub" x="556" y="196">WHEN THE WORK IS WORKING</text>
</svg>"""


# ── "Where it hurts": a spine, with the regions people actually book for ─────
# A stack of vertebrae rather than a body outline. Geometric, so it holds up;
# and it labels the three regions that account for most bookings.

def _vertebrae():
    out = []
    y = 44
    i = 0
    while y < 380:
        w = 44 + (y - 44) * 0.072          # widens toward the lumbar spine
        h = 17 + (y - 44) * 0.014
        cls = "v-hot" if (70 < y < 130 or 190 < y < 250 or 300 < y < 360) else "v"
        out.append('<rect class="%s" x="%.1f" y="%.1f" width="%.1f" height="%.1f" rx="4"/>'
                   % (cls, 150 - w / 2, y, w, h))
        y += h + 7
        i += 1
    return "".join(out)


SPINE = """<svg class="spine-map" viewBox="0 0 640 420" role="img"
     aria-label="A diagram of the spine with the neck, mid-back and low back marked as the regions most commonly booked for">
  <line class="axis" x1="150" y1="34" x2="150" y2="392"/>
  %(vert)s

  <g class="lead">
    <line x1="212" y1="96" x2="268" y2="96"/>
    <line x1="212" y1="216" x2="268" y2="216"/>
    <line x1="212" y1="330" x2="268" y2="330"/>
  </g>
  <g class="zone">
    <text x="282" y="90">Neck &amp; shoulders</text>
    <text class="z-sub" x="282" y="110">DESK WORK, DRIVING, CARRYING</text>
    <text x="282" y="210">Mid-back</text>
    <text class="z-sub" x="282" y="230">POSTURE, BREATHING, ROTATION</text>
    <text x="282" y="324">Low back &amp; hips</text>
    <text class="z-sub" x="282" y="344">SITTING, LIFTING, RUNNING</text>
  </g>
</svg>""" % dict(vert=_vertebrae())


CSS = """
/* ── Home-page graphics ─────────────────────────────────────────────────────
   Original vector, not photography and not stock. Geometric by choice: the
   figurative attempts in this build were poor, and these are the register that
   worked - instrument and diagram rather than illustration. */

/* Service icons */
.svc-icon{width:46px; height:46px; display:block; margin-bottom:15px}
.svc-icon .i-line{fill:none; stroke:var(--ink-3); stroke-width:2.6; stroke-linecap:round}
.svc-icon .i-accent{fill:none; stroke:var(--accent); stroke-width:3; stroke-linecap:round}
.svc-icon .i-accent-f{fill:var(--accent); stroke:none}
.svc-icon .i-accent-s{fill:none; stroke:var(--accent); stroke-width:2.4}
.svc-icon .i-warm{fill:none; stroke:var(--ochre); stroke-width:2.6; stroke-linecap:round}
.svc-icon .i-dot{fill:var(--ink)}
.svc-icon rect.i-line{fill:none}

/* Goniometer */
.gonio{width:100%; height:auto; display:block; margin-top:46px}
.gonio .arc{fill:none; stroke:var(--line); stroke-width:1.5}
.gonio .sweep{fill:none; stroke:var(--accent); stroke-width:3.5; stroke-linecap:round;
  stroke-dasharray:340; stroke-dashoffset:340;
  animation:gonio-sweep 2.1s .3s cubic-bezier(.22,.8,.25,1) forwards}
@media (prefers-reduced-motion:reduce){ .gonio .sweep{stroke-dashoffset:0; animation:none} }
@keyframes gonio-sweep{to{stroke-dashoffset:0}}
.gonio .tick{stroke:var(--ochre); stroke-width:1.6}
.gonio .limb{stroke:var(--ink); stroke-width:2.6; stroke-linecap:round}
.gonio .pivot{fill:var(--accent)}
.gonio .g-lead{font-family:"Archivo",sans-serif; font-size:26px; font-weight:800;
  letter-spacing:-.02em; fill:var(--ink)}
.gonio .g-sub{font-family:"Archivo",sans-serif; font-size:11.5px; font-weight:700;
  letter-spacing:.14em; fill:var(--ink-3)}
@media(max-width:760px){ .gonio .g-lead{font-size:22px} .gonio .g-sub{font-size:10.5px} }

/* Spine map */
.spine-wrap{display:grid; grid-template-columns:1fr 1fr; gap:48px; align-items:center}
@media(max-width:900px){ .spine-wrap{grid-template-columns:1fr; gap:28px} }
.spine-map{width:100%; height:auto; display:block; max-width:560px}
.spine-map .axis{stroke:var(--line); stroke-width:1.5; stroke-dasharray:5 6}
.spine-map .v{fill:var(--sunk); stroke:var(--ink-3); stroke-width:1.4}
.spine-map .v-hot{fill:var(--accent); stroke:var(--accent-ink); stroke-width:1.4; opacity:.85}
.spine-map .lead line{stroke:var(--ochre); stroke-width:1.4}
.spine-map .zone text{font-family:"Archivo",sans-serif; font-size:17px; font-weight:700;
  fill:var(--ink)}
.spine-map .zone .z-sub{font-size:11px; font-weight:600; letter-spacing:.12em; fill:var(--ink-3)}
"""
