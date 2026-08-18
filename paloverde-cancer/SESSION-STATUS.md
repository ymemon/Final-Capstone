# PaloVerde Cancer Specialists — Session Status

Read this first when resuming. See `CLIENT-INFO.md` for site/business facts.

## 2026-08-18 — Session 1

**Task from client (Michael Bustard, IT Director):**
1. Assign the correct doctors to each of the 4 location pages.
2. Once doctors are done, make sure all pages are uniform.
3. Once uniform, do a full link audit of the site.

**Done:**
- Set up SSH access (`~/.claude-tools/ssh_run_paloverde.bat`) and confirmed
  WP-CLI access.
- Investigated the 4 location pages (Estrella 533, Glendale 461,
  Scottsdale 501, East Valley/Gilbert 544). Found each page's doctor
  section was either unfiltered (showing all 6 doctors instead of the
  location's subset) or, on Scottsdale, showing 4 completely unrelated
  placeholder names (Lauren D. Stegman, Kurt A. Wharton, Abhilash P.
  Nambiar, John J. Kresl) from leftover demo content.
- Discovered the pages are Elementor "builder" mode but the live front-end
  actually renders `post_content`, not `_elementor_data` — see
  `reference-paloverde-wp-technique` memory / `CLIENT-INFO.md` for how this
  was confirmed and worked around (edited both fields to keep the Elementor
  editor in sync, but `post_content` is what actually matters for the
  live page).
- Rebuilt a single canonical `doctor-grid`/`doctor-card` HTML+CSS block and
  applied the correct filtered doctor list to all 4 pages' `post_content`
  (backups of original `_elementor_data` and `post_content` for all 4 pages
  are in this folder's `backups/` subfolder). Converted Scottsdale from its
  one-off "team-section" design to the same shared block for consistency.
- Verified live via fresh (non-cached, `CF-Cache-Status: DYNAMIC` /
  gateway `MISS`) fetches of all 4 URLs — each now shows exactly the right
  doctor subset. Screenshot-verified Scottsdale renders cleanly (no layout
  breakage) via Playwright.

**Step 2 (page uniformity) — also done, same session, after checking scope
with yasir:**
- Confirmed with yasir: doctor rosters should stay location-specific (not
  made identical) — only the section's markup/position needed unifying.
- Standardized all 4 pages to the same section order/inventory: About →
  Doctors → Services (4 cards) → Map → Insurance → Directions → Contact →
  CTA. Added Estrella's missing map section, Glendale's missing services
  section, moved Scottsdale's doctor section up to match the others'
  position, and added Scottsdale's missing 4th service card.
- `_elementor_data` was NOT re-synced for these structural additions (only
  for the doctors fix). Low priority — see `CLIENT-INFO.md`.

**Step 3 (full link audit) — done same session, urgent same-day-launch pass:**
Crawled all 57 published pages, extracted and checked all ~345 unique
internal links. Found and fixed real (non-orphaned) broken links:
- **Sitewide footer "Our Physicians" link 404'd** on every single page —
  hardcoded in `wp-content/plugins/pvhomed-custom-footer/pvhomed-custom-footer.php`
  line 90, pointing to `/our-physicians/` (a page that was never created).
  Fixed to point to `/your-team/` (the real, existing team page).
- `/about-us/` had its own separate hardcoded copy of the same broken
  link (in both `post_content` and `_elementor_data`) — the shared footer
  plugin fix didn't cover it since this page renders independently. Fixed
  both fields.
- **Homepage "Meet Our Doctors" section had a broken profile link** for
  Dr. Mamani (`/demetrio-mamani-md/`, 404) — fixed to `/your-team/dr-mamani/`.
- **Homepage doctor-location filter data was almost entirely wrong** —
  discovered while chasing the Mamani link. The homepage has its own
  separate doctor→location dataset (with working JS filter buttons) that
  did NOT match Michael Bustard's actual roster at all for 4 of 6 doctors
  (Halepota, Mamani, Rakkar, Zafar all had wrong/nonsensical location
  tags — e.g. Zafar was tagged Scottsdale+Glendale instead of Estrella-only).
  Corrected all 6 doctors' location tags to match the real roster and
  verified by actually clicking each filter button in Playwright and
  confirming the right doctors appear per location.
- Also discovered mid-audit: **the homepage's live doctors section lives in
  `_elementor_data`, not `post_content`**, because it uses the
  `elementor_header_footer` page template — opposite of the location pages.
  A separate near-duplicate, half-broken draft copy existed in
  `post_content` (no working JS, missing markup) that was never actually
  live; edited it too for consistency but it's not what renders. See
  `reference-paloverde-wp-technique` memory — **check `_wp_page_template`
  per page before assuming which field is live; it varies by page even on
  this one site.**
- Found and worked around a **stale static HTML page cache**
  (`wp-content/cache/wpo-cache/`, `wpo-minify/`) that masked fixes after
  `_elementor_data`-only edits (meta updates don't trigger the same
  cache-purge hooks as full `post update`). Cleared it directly via SSH.

**8 orphaned duplicate pages — DONE, unpublished (2026-08-18, same session):**
`conditions-we-treat-2` (2185), `conditions-we-treat-3` (2203), and their
child duplicates `conditions-we-treat-pancreatic-cancer` (2199) + `-2`
(2217), `conditions-we-treat-bladder-cancer` (2188) + `-2` (2206),
`conditions-we-treat-brain-cancer-2` (2207), plus `/your-team/dr-rakkar/`
(2161, a stale duplicate of `/dr-amol-rakkar/`) were all confirmed
unreachable from any real navigation (self-referencing only) and contained
their own internal broken links. Set all 8 to `post_status=draft` via
`wp post update <ids> --post_status=draft` (the first attempt was blocked
by Claude Code's own safety classifier; retried after explicit user
go-ahead and it went through cleanly). Verified live post-flush: all 8
URLs now return 404 to real visitors.
- `/author/` archive link 404s from the blog post byline — low priority,
  blog isn't even in the main nav menu.
- Site is still on the temp domain (`875051.us16.myftpupload.com`) —
  `pvcancer.com` DNS not yet pointed at it. Outside SSH/WP-CLI access,
  needs the client's registrar/DNS action before "going live" is real to
  the public.
