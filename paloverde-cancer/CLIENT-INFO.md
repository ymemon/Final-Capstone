# PaloVerde Cancer Specialists — Client & Site Facts

## Identity

- **Business:** PaloVerde Cancer Specialists — multi-location cancer treatment
  practice in the Phoenix, AZ metro area.
- **Live domain (target):** pvcancer.com — not yet pointed at this site; the
  build is currently on the managed-WordPress temp domain
  `https://875051.us16.myftpupload.com`.
- **Contact:** Michael Bustard, IT Director, PaloVerde Cancer Specialists.
- **Locations (code / city / street address):**
  - **WVO — Estrella** (page: `/estrella-location/`, post ID 533)
  - **TBO — Glendale** (page: `/glendale-location/`, post ID 461) —
    5601 W Eugie Ave #106, Glendale, AZ 85304
  - **SDO — Scottsdale** (page: `/scottsdale-location/`, post ID 501)
  - **GTO — Gilbert** — branded on-site as "East Valley Location"
    (page: `/east-valley-location/`, post ID 544) —
    1488 W Elliot Rd, Gilbert, AZ 85233
- **Doctors (6 total), per Michael Bustard's roster email:**
  - Dr. Haider Zafar, MD
  - Dr. Demetrio Mamani, MD
  - Dr. Amol N.S. Rakkar, MD
  - Dr. Rajinder Grover, MD
  - Dr. Nazish Ahmad, DO
  - Dr. Maqbool A. Halepota, MD, FACP, CPE
  - Each has an individual bio page already published (`dr-haider-zafar`,
    `dr-rajinder-grover`, `dr-maqbool-halepota`, `dr-amol-rakkar` /
    `dr-rakkar`, `dr-nazish-ahmad`, `dr-mamani`) — two of these
    (Rakkar, and possibly others) have duplicate-looking bio pages from an
    earlier draft; not yet reconciled/cleaned up.
- **Doctor → location roster (per Michael Bustard, 2026-08-18):**
  - WVO/Estrella – Zafar, Mamani, Rakkar
  - TBO/Glendale – Rakkar, Mamani, Grover, Ahmad
  - SDO/Scottsdale – Halepota, Grover, Ahmad
  - GTO/Gilbert – Grover, Halepota

## Server access

Managed WordPress (GoDaddy-style, myftpupload.com). See
`reference-paloverde-ssh-access` memory / `ssh_run_paloverde.bat`.
wp-admin login: username `460489pwpadmin` (password in password manager /
session notes, not repeated here).

## Stack notes

- WordPress 7.0.4, Astra theme (active), Elementor 4.2.2 (free, not Pro).
- The 4 location pages are marked `_elementor_edit_mode = builder`, but
  **the live front-end actually renders from `post_content`** (via the
  standard `the_content()` / wpautop pipeline — confirmed by the
  `title="<Location> Location"` attribute wpautop/a filter injects into
  every `<img>`, and by editing `_elementor_data` alone having zero effect
  on the live page). `_elementor_data` is a separate, mostly-parallel copy
  that only matters if/when someone opens the page in the Elementor editor.
  See `reference-paloverde-wp-technique` for the full method used to edit
  both safely.
- No GoDaddy/Cloudflare edge-cache issue was actually in play here — despite
  `CF-Cache-Status: DYNAMIC` / gateway `MISS` on every check, content still
  looked stale until `post_content` itself (not just `_elementor_data`) was
  corrected. Don't waste time chasing cache purges before confirming which
  field actually drives the render.

## Known open items (as of 2026-08-18)

- **Doctors → locations: DONE.** All 4 location pages now show only their
  correct doctor subset, in a single unified `doctor-grid`/`doctor-card`
  HTML+CSS block (Scottsdale previously showed 4 completely unrelated
  placeholder doctors — Lauren D. Stegman, Kurt A. Wharton, Abhilash P.
  Nambiar, John J. Kresl — leftover demo content, now replaced).
- **Page uniformity: partially done, bigger gap found.** The doctor block is
  now identical in markup/CSS across all 4 pages. But the pages' overall
  *section inventory and order* is NOT uniform and this predates today's
  work:
  - Estrella has no "Find Us Here" map section.
  - Glendale has no "Services at This Location" section.
  - Scottsdale's doctors section sits near the bottom of the page (after
    Directions & Parking), not right after "About" like the other 3.
  - East Valley/Gilbert is the only page with the full section set in the
    "About → Doctors → Services → Map → Insurance → Directions → Contact →
    CTA" order.
  - Not yet fixed — needs a decision on canonical section order/inventory
    before touching it (see SESSION-STATUS.md).
- **Full link audit: not started.** Client's step 3, blocked on step 2
  being resolved/confirmed first.
- Duplicate-looking doctor bio pages (`dr-amol-rakkar` post 2092 vs
  `dr-rakkar` post 2161) not yet reconciled.
