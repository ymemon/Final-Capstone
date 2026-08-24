# Homepage hero center deployment — 2026-08-24

Centered the content inside homepage hero widget `5700064` on page ID 146.
No copy was changed.

Verified verbatim after deployment:

- “Managed IT Services & Solutions in Dublin, Ireland”
- “Unlock Your IT Potential”
- “Claim Your Complimentary Audit Today!”
- “Get Your Free Audit →”

The implementation adds a narrowly scoped CSS override that centers the hero
container, caps its readable width at 900px, centers its text and keeps the
supporting paragraph centered. A timestamped Elementor-data rollback snapshot
was created before mutation. Elementor, WordPress object, GoDaddy page and CDN
caches were cleared. A cache-busted public request returned HTTP 200 with the
centering marker and all copy invariants present.

## Services-grid alignment refinement

Following the client's annotated screenshot, the hero was explicitly aligned
to the same 1140px boxed-content width used by the “Our Services” section. The
headline remains centre-aligned within that shared frame, with fluid desktop
gutters plus dedicated tablet and mobile gutters. No hero wording was changed.
