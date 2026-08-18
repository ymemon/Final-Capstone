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

**Open / blocked:**
- **Step 3 (full link audit) not started** — both prerequisite steps are
  now done, ready to start next session (or continue this one on request).
- Two duplicate-looking bio pages for Dr. Rakkar (`dr-amol-rakkar` #2092 vs
  `dr-rakkar` #2161) — not reconciled, not blocking current work.
