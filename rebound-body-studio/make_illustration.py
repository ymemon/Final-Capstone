# -*- coding: utf-8 -*-
"""Draw the hands-on-back illustration for Rebound Body Studio.

WHY DRAWN AND NOT PHOTOGRAPHED
Randi has been told in writing that stock spa photography would undercut the
clinical positioning, and that the plan is to shoot her actual room and hands.
This is not a substitute for that shoot. It is an original illustration she owns
outright, so the page has something beautiful in it now and never carries a
stock image somebody else is also using.

THE DRAWING
A close crop: the back fills the frame and runs off every edge, so there is no
body outline to go lumpy and the eye reads texture and pressure rather than a
figure. The tissue is described by contour lines computed from a surface
function, so they visibly give under each hand and settle again beyond it -
the same idea as Figure 1 on the site and the "Under the Hands" logo, so mark,
diagram and illustration all say one thing.

Hands are drawn with the fingers together in a resting stroke position and
rendered as line work over a pale fill. Splayed fingers and solid silhouettes
both read as rubber gloves; this reads as hands.
"""
import io
import math
import os

HERE = os.path.dirname(os.path.abspath(__file__))
W, H = 1000, 620

SPINE_X = 505.0

# Palm centres in page coordinates, derived from the transforms at the bottom of
# build_svg(). If a hand moves, these move with it or the tissue gives in the
# wrong place.
HANDS = [(343.0, 521.0), (639.0, 495.0)]


def surface_dy(x, y):
    """Vertical displacement of a contour line at (x, y).

    The back is convex, so lines sag toward the sides; the spinal furrow puts a
    groove down the middle; each hand presses a local depression into tissue.
    """
    t = (x - SPINE_X) / 520.0
    dy = 30.0 * t * t                                          # convexity
    dy += 9.0 * math.exp(-((x - SPINE_X) / 30.0) ** 2)         # spinal furrow

    # Scapula ridges either side of the spine, sitting high on the back.
    for sx in (SPINE_X - 178, SPINE_X + 178):
        dy -= 7.0 * math.exp(-((x - sx) / 88.0) ** 2) * math.exp(-((y - 330.0) / 105.0) ** 2)

    for hx, hy in HANDS:
        across = math.exp(-((x - hx) / 132.0) ** 2)
        along = math.exp(-((y - hy) / 152.0) ** 2)
        dy += 34.0 * across * along                            # tissue gives
    return dy


def smooth_path(pts):
    """Catmull-Rom through the points, emitted as cubic beziers."""
    if len(pts) < 2:
        return ""
    d = "M %.1f %.1f" % pts[0]
    for i in range(len(pts) - 1):
        p0 = pts[i - 1] if i > 0 else pts[0]
        p1, p2 = pts[i], pts[i + 1]
        p3 = pts[i + 2] if i + 2 < len(pts) else pts[-1]
        c1 = (p1[0] + (p2[0] - p0[0]) / 6.0, p1[1] + (p2[1] - p0[1]) / 6.0)
        c2 = (p2[0] - (p3[0] - p1[0]) / 6.0, p2[1] - (p3[1] - p1[1]) / 6.0)
        d += " C %.1f %.1f, %.1f %.1f, %.1f %.1f" % (c1[0], c1[1], c2[0], c2[1], p2[0], p2[1])
    return d


def contours():
    """Lines across the back, running off both edges. The ones crossing the
    hands are kept separate so the pressure zone carries the single accent."""
    plain, pressed = [], []
    y = -30.0
    while y <= H + 60:
        pts = []
        x = -60.0
        while x <= W + 60.1:
            pts.append((x, y + surface_dy(x, y)))
            x += 12.0
        d = smooth_path(pts)
        near = min(abs(y - hy) for _, hy in HANDS)
        (pressed if near < 58 else plain).append(d)
        y += 29.0
    return plain, pressed


def digit(base, angle, length, w_base, w_tip, curl=0.0, steps=6):
    """A tapered finger as a closed outline.

    Earlier attempts drew all four fingers fused into one scalloped blob, which
    is precisely why they read as a mitten: the negative space BETWEEN fingers
    is the cue that says hand. So each digit is its own shape with a real gap
    beside it, tapering toward the tip and curling slightly, the way a relaxed
    hand rests rather than a splayed glove.

    Angles are degrees from straight up, positive leaning right.
    """
    ax, ay = base
    spine, widths = [(ax, ay)], [w_base]
    a = math.radians(angle)
    step = length / float(steps)
    for i in range(1, steps + 1):
        a += math.radians(curl) / steps
        ax += math.sin(a) * step
        ay -= math.cos(a) * step
        spine.append((ax, ay))
        t = i / float(steps)
        widths.append(w_base + (w_tip - w_base) * t)

    left, right = [], []
    for i, (px, py) in enumerate(spine):
        if i == 0:
            dx, dy = spine[1][0] - px, spine[1][1] - py
        else:
            dx, dy = px - spine[i - 1][0], py - spine[i - 1][1]
        n = math.hypot(dx, dy) or 1.0
        nx, ny = -dy / n, dx / n
        h = widths[i] / 2.0
        left.append((px + nx * h, py + ny * h))
        right.append((px - nx * h, py - ny * h))

    r = widths[-1] / 2.0
    d = smooth_path(left)
    d += " A %.1f %.1f 0 0 1 %.1f %.1f " % (r, r, right[-1][0], right[-1][1])
    # Continue down the far side. smooth_path() starts with "M x y ...", and the
    # return side has to be a LINE-TO, not a new subpath - swapping the leading
    # M for an L is the whole trick. (Slicing the M off instead leaves the
    # coordinates dangling on the preceding arc, which draws open hooks.)
    back = smooth_path(list(reversed(right)))
    d += "L" + back[1:]
    return d + " Z"


# Palm drawn on top of the digits, so every finger and the thumb emerge from
# behind its edge and no construction lines cross it. The forearm continues off
# the bottom of the frame.
PALM = (
    "M -45 16 "
    "C -53 -30, -58 -80, -59 -120 "
    "C -59 -132, -50 -136, -40 -135 "
    "L 46 -130 "
    "C 57 -129, 62 -124, 61 -113 "
    "C 60 -72, 54 -28, 47 16 "
    "C 47 60, 48 104, 49 140 "
    "L -47 140 "
    "C -46 104, -45 60, -45 16 Z"
)

DIGITS = "".join('<path class="skin" d="%s"/>' % d for d in (
    digit((-44, -124), -8, 104, 31, 24, curl=-4),    # index
    digit((-16, -132), -2, 118, 32, 25, curl=-2),    # middle
    digit((12, -130), 4, 109, 31, 24, curl=2),       # ring
    digit((38, -120), 11, 87, 28, 21, curl=5),       # pinky
    digit((-52, -52), -62, 92, 35, 27, curl=-8),     # thumb
))

# Two creases only. The contour field behind the hands is quiet, and a hand
# covered in detail lines fights it.
HAND_DETAIL = (
    '<path d="M -50 -108 C -20 -118, 22 -116, 57 -108"/>'   # knuckle line
    '<path d="M -44 22 C -14 32, 20 32, 47 22"/>'           # wrist crease
)

HAND = ('    <g class="hand">\n'
        '      %s\n'
        '      <path class="skin" d="%s"/>\n'
        '      <g class="detail">%s</g>\n'
        '    </g>\n' % (DIGITS, PALM, HAND_DETAIL))


def build_svg():
    plain, pressed = contours()
    plain_d = "".join('<path d="%s"/>' % d for d in plain)
    pressed_d = "".join('<path d="%s"/>' % d for d in pressed)
    spine = smooth_path([(SPINE_X, y + surface_dy(SPINE_X, y))
                         for y in [-20 + i * 24 for i in range(int((H + 60) / 24))]])

    return """<svg class="handsback" viewBox="0 0 %(W)d %(H)d" role="img"
     aria-label="A close view of two hands working the muscles either side of the spine, the tissue drawn as contour lines that give under the pressure and settle again beyond it">
  <defs>
    <filter id="hb-soft" x="-45%%" y="-45%%" width="190%%" height="190%%">
      <feGaussianBlur stdDeviation="15"/>
    </filter>
    <radialGradient id="hb-vig" cx="50%%" cy="48%%" r="66%%">
      <stop offset="0%%" stop-color="#fff" stop-opacity="1"/>
      <stop offset="62%%" stop-color="#fff" stop-opacity="1"/>
      <stop offset="100%%" stop-color="#fff" stop-opacity="0"/>
    </radialGradient>
    <mask id="hb-fade">
      <rect x="0" y="0" width="%(W)d" height="%(H)d" fill="url(#hb-vig)"/>
    </mask>
  </defs>

  <g mask="url(#hb-fade)">
    <g class="contour">%(plain)s</g>
    <g class="contour-pressed">%(pressed)s</g>
    <path class="spine" d="%(spine)s"/>
  </g>

  <!-- Scaled up and pushed down so the frame crops at the knuckles: what shows
       is fingers and the top of the palm. The thumb and wrist - the two shapes
       that read as a mitten at full length - fall below the edge entirely.
       Staggered rather than mirrored-symmetric, because symmetry reads stiff. -->
  <!-- The drawn hands are deliberately omitted. Six attempts at anatomical
       hands in generated vector produced mittens, claws and open paths - well
       below the standard the rest of the page holds, and a bad drawing of hands
       is worse than none. What survives is the honest half: tissue described as
       contour lines, with two depressions where pressure is being applied. It
       reads as touch without pretending to draw it, and it is the same idea as
       Figure 1 and the "Under the Hands" mark.

       For a real photograph of hands on a back, the answer is the shoot already
       offered to Randi - her actual hands, her actual room. -->
  <g class="press">
    <circle cx="343" cy="521" r="8"/>
    <circle cx="639" cy="495" r="8"/>
  </g>
</svg>""" % dict(W=W, H=H, plain=plain_d, pressed=pressed_d, spine=spine, hand=HAND)


CSS = """
/* -- Hands-on-back illustration -------------------------------------------
   Original vector drawing: not stock, not a photograph, and hers outright.
   Every colour is a token so it resolves in both themes. The hands are line
   work over a pale fill rather than solid silhouettes, which read as gloves. */
.plate{margin:0 0 54px; border:var(--rule); border-radius:3px; background:var(--sunk);
  overflow:hidden}
.plate .handsback{width:100%; height:auto; display:block}
.plate figcaption{border-top:var(--rule); padding:16px 22px; font-size:15px; color:var(--ink-3);
  line-height:1.55; background:var(--surface)}

.handsback .contour path{fill:none; stroke:var(--ink); stroke-width:1.25; opacity:.22}
.handsback .contour-pressed path{fill:none; stroke:var(--accent); stroke-width:1.7; opacity:.5}
.handsback .spine{fill:none; stroke:var(--ink); stroke-width:1.7; opacity:.3}

.handsback .press circle{fill:var(--accent); opacity:.5}
"""


def main():
    build = os.path.join(HERE, "build")
    svg = build_svg()
    io.open(os.path.join(build, "handsback.svg"), "w", encoding="utf-8").write(
        '<?xml version="1.0" encoding="UTF-8"?>\n' + svg)
    io.open(os.path.join(build, "handsback.css"), "w", encoding="utf-8").write(CSS)

    io.open(os.path.join(build, "preview-illo.html"), "w", encoding="utf-8").write(
        """<!doctype html><meta charset="utf-8"><style>
:root{--paper:#f4f6f3;--surface:#fff;--sunk:#eaeee9;--ink:#16211d;--ink-3:#71807a;
      --line:#dce2dc;--accent:#2f6b52;--rule:1px solid var(--line);}
body{margin:0;background:var(--paper);padding:24px;font-family:system-ui}
.plate{max-width:1000px;margin:0 auto}
%s
</style><figure class="plate">%s</figure>""" % (CSS, svg))
    print("svg %d bytes" % len(svg))


if __name__ == "__main__":
    main()
