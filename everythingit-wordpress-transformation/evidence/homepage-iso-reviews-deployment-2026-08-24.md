# Homepage ISO/reviews deployment — 2026-08-24

## Scope

Replaced the legacy white ISO 27001 section on homepage page ID 146 with the
modern dark certification component in `src/components/homepage-iso-reviews.html`.

The supplied concept displayed “40 reviews” but contained only three hard-coded
review excerpts. The production version uses all 10 authenticated written review
excerpts available in the project evidence inside a CSS-native horizontal
slider. It also links directly to the verified Everything IT Google Business
Profile (`ChIJPQFEVAsJZ0gRmKsfrTWPS7A`) with a clear “Read all 40 reviews”
action, covering the complete public profile rather than implying that the ten
written excerpts are the entire review set.

## Safeguards and verification

- Downloaded the live `_elementor_data` before editing.
- Targeted exactly one existing section: `d12903a`.
- Stored a timestamped full homepage rollback snapshot outside the web root.
- Replaced only the target section; all other homepage Elementor nodes remained
  unchanged.
- Cleared Elementor, WordPress object, GoDaddy page and GoDaddy CDN caches.
- Verified both the cache-busted and ordinary public homepage returned HTTP 200.
- Verified both public responses contained the modern ISO component and complete
  review-profile link, with zero references to the old ISO image.

Public checks after deployment:

- `eit-iso-modern`: present
- `Read all 40 reviews`: present
- old `iso-27001-certified-logo-it.jpg.png`: absent
- placeholder review cards: zero
- authenticated review cards: 10
- working review navigation anchors: 10
- CSS scroll snapping and mobile swipe: enabled

## Lower-page relocation and responsive frame

The review slider was subsequently separated from the ISO section and inserted
after homepage section `c58e3f3` (Corporate IT Procurement), making it the final
homepage content section before the global footer. The ISO section remains in
its original position.

The review frame now uses:

- fluid `minmax()` columns and clamped outer gutters on wide screens;
- a two-row tablet layout with the slider spanning the full frame width;
- a one-column mobile layout;
- wrapping review navigation controls;
- horizontally scrollable, snap-aligned cards sized to the available viewport.

Public source order was verified as ISO → Manufacturers → Manufacturers →
Corporate IT Procurement → Google reviews. Ten cards and ten controls remained
present after relocation.

## Continuous review slider

The lower-page Google review row was upgraded to a seamless, continuously
moving marquee. It retains 10 unique authenticated review excerpts and repeats
that set visually to avoid a gap when the animation loops. The repeated set is
marked `aria-hidden="true"`, so assistive technology encounters only the 10
unique reviews.

- 62-second linear continuous loop with no snap-back gap;
- pauses on pointer hover and keyboard focus;
- falls back to manual horizontal scrolling when reduced motion is requested;
- duplicate visual set is hidden in reduced-motion mode;
- responsive card sizing and the direct link to all 40 Google reviews remain;
- public verification returned one review section, 20 rendered cards, 10 unique
  review IDs, one duplicate set and the continuous-animation marker.

The review layout was then refined so the Google rating and stars sit in a
centred header above the slider. The continuous review track now spans the full
responsive content frame rather than occupying only the third desktop column.
