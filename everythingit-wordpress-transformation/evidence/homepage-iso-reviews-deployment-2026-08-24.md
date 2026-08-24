# Homepage ISO/reviews deployment — 2026-08-24

## Scope

Replaced the legacy white ISO 27001 section on homepage page ID 146 with the
modern dark certification component in `src/components/homepage-iso-reviews.html`.

The supplied concept displayed “40 reviews” but contained only three hard-coded
review excerpts. The production version removes that partial, potentially
misleading subset and links directly to the verified Everything IT Google
Business Profile (`ChIJPQFEVAsJZ0gRmKsfrTWPS7A`) with a clear “Read all 40
reviews” action.

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
