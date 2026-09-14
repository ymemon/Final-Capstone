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
- **Color mismatch on the doctor cards — DONE, fixed same session.** yasir
  flagged this after the link audit. The doctor-card "Board Certified
  Oncologist" text used `#2563eb` (a generic off-brand blue) while the
  same page's phone number link right above it used `#007BFF` — two
  visibly different blues competing on the same page. Standardized all 4
  location pages' doctor-card text to `#007BFF` to match the
  already-established on-page accent color. (Note: the page's `_elementor_data`
  still has the old `#2563eb` copy since it's inert for these 4 pages —
  see [[paloverde-wp-technique]] — left as-is, not worth the edit since it
  never renders.) Also spotted but NOT fixed: the "Palo Verde Cancer
  Center – [Location]" heading in the summary box uses the true brand navy
  `#002B5B`, which has very poor contrast on this page's black background
  (nearly illegible) — pre-existing, not part of what was asked, flagged
  for a future pass.
- **Hero background color mismatch — DONE, fixed same session.** yasir
  flagged that the homepage hero was black, About Us/Services use a dark
  purple gradient rounded card (`linear-gradient(135deg, #0f0a2a 0%,
  #2a1b4a 100%)`), and Conditions We Treat used yet another color (a
  slate `#3f3f57`). Root cause on the homepage: its hero is a native
  Elementor container with a `background_image` setting whose `url`/`id`
  were both empty strings — a broken/never-finished background, not an
  intentional black design — so it fell through to the black body
  background. Fixed by giving it the same purple gradient (via
  `background_background:"gradient"` + matching color-stop settings in
  `_elementor_data`, not `post_content` — this container is built from
  native Elementor widgets, not a raw HTML/editor blob, see
  [[paloverde-wp-technique]]). Fixed Conditions We Treat's `.pv-hero` CSS
  the same way (simple color-value edit in `post_content`, that page IS
  post_content-driven). Left the *rest* of the Conditions We Treat page
  (its own slate `#34344A` body / `.pv-section` card theme) as-is — fully
  unifying that page's whole color scheme with About/Services' white-page
  design would be a much bigger redesign than what was asked; flagging
  here in case yasir wants that as a separate follow-up.
- **Doctor photos on location pages — DONE, made smaller same session.**
  yasir flagged the doctor photos as too large (180px). Reduced
  `.doctor-card img { max-width: }` from 180px to 110px across all 4
  location pages' `post_content`.
- **Mobile check — found and fixed a real mobile-only sizing bug.** On
  mobile viewports the 110px `max-width` was NOT taking effect (Astra
  theme's own generic `img{max-width:100%}` responsive-image rule was
  winning over it at that breakpoint), so doctor photos rendered nearly
  full-viewport-width on phones even after the desktop fix. Added
  `!important` to force it. Verified at a real 390px mobile viewport via
  Playwright (not headless-Edge — see [[prestige-wp-technique]] for why
  that tool lies about viewport width). Also verified the new purple
  hero gradient renders correctly on mobile. One transient 404 was seen
  on `/estrella-location/` during mobile testing but did not reproduce
  across 6 immediate follow-up checks — most likely GoDaddy edge
  rate-limiting from this session's heavy testing load, not a real bug;
  worth a quick re-check next session if anyone reports it from a real
  phone.
- **Footer "Website by AZWebCorp" credit — DONE, restyled to match Everything IT's London pages same session.** yasir wanted the exact
  animated 3D-globe credit style used on everythingit.ie's London
  pages (`.eit-footer__credit` / `.eit-footer__globe3d`, orbiting-dot
  animation, "Website by AZWebCorp" wording) instead of PaloVerde's
  plain static SVG globe + "Powered by AZWebCorp" wording. Pulled the
  exact CSS/markup live from `https://1249683.eu13.myftpupload.com/central-london/`
  and replicated it verbatim into
  `wp-content/plugins/pvhomed-custom-footer/pvhomed-custom-footer.php`
  (same file already touched earlier for the `/our-physicians/` link
  fix — sitewide, affects every page's footer). `php -l` linted before
  and after deploy; backup of the original saved server-side at
  `/tmp/pvhomed-custom-footer.php.bak`. Verified live via screenshot.
- **"Locations page messed up" — DONE, found and fixed two real bugs
  same session.** yasir asked to re-check the location pages. Found:
  1. The color-mismatch and photo-size fixes from earlier in this same
     session had somehow reverted on all 4 pages (DB-confirmed: back to
     `max-width: 180px` and `#2563eb`, revision history showed the length
     matching the pre-fix version) — root cause not fully identified in
     the time available; re-applied both fixes, verified immediately
     after each write this time. Worth watching for recurrence.
  2. The real "messed up" layout: wpautop (WordPress's auto-`<p>` filter,
     which runs on this post_content-driven page — see
     [[paloverde-wp-technique]]) was inserting stray empty `<p></p>`
     elements as **direct children of `.doctor-grid`** between doctor
     cards, because the HTML comment above each card sat on its own line
     with a blank-ish separator between cards. Since `.doctor-grid` is
     `display:grid`, those invisible paragraphs consumed real grid cells,
     scattering the visible cards into odd positions (e.g. Estrella's 3
     doctors rendering as 1-centered-on-top + 2-split-on-row-2 instead of
     one clean row of 3). Fixed by moving each card's HTML comment inline
     (`<div class="doctor-card"><!-- Name -->`) and joining cards with a
     single `\n`, no blank lines — confirmed via `.doctor-grid`'s direct
     children count now exactly matching each page's doctor count (3, 4,
     3, 2). wpautop still adds harmless stray `<p>`/`<br>` *inside* each
     card, which doesn't affect grid placement since those aren't direct
     children of the grid container.
- **Conditions We Treat page's lighter-blue body — DONE, fully unified
  same session.** yasir flagged this (from the `459546.us16...` domain
  alias — confirmed same site/DB as `875051.us16...`, just a second
  hostname pointing at the same install, both work). After the earlier
  hero-only fix, the rest of the page (`.pv-section`/`.pv-card` etc.)
  still used its own separate slate-blue theme (`#34344A` page bg,
  `#3f3f57` card bg), which now clashed even more with the purple hero
  above it. Recolored the entire page's CSS (same class names, values
  only) to match About Us/Services: white `.pv-section` cards, `#0f0a2a`
  navy headings, `#4a5568` body text, `#f8f7ff` light-purple condition
  cards — verified in DB and via screenshot.
- **Clinical Research and Trials page — DONE, fixed same session.**
  yasir flagged a color issue here too. Found the whole page's CSS used
  a wrong hex value, `#0d1b2a` (a dark navy) — for the hero background,
  section headings, icons, and buttons — despite the page's own code
  comments literally saying `/* DARK PURPLE (same as top) */` at each
  usage. Whoever built this page (or a prior AI pass) used the wrong hex
  for what was clearly always intended to be the site's purple. Also
  found a real legibility bug this caused: the "Types of Clinical
  Trials" heading sits directly on the page's black background (not on
  a white card like every other place `#0d1b2a`/purple text was used),
  so navy-on-black was nearly invisible. Fixed: globally replaced
  `#0d1b2a` with the real purple `#2a1b4a` (20 occurrences), made the
  hero a true two-stop gradient (`#0f0a2a → #2a1b4a`, matching every
  other hero on the site) instead of a flat single-color fake gradient,
  and set the one heading that sits on black to white text with a light
  purple (`#c4a4ff`) accent underline instead of dark purple. This page
  uses a native Elementor "Text Editor" widget (`"editor":"..."` key in
  `_elementor_data`, same pattern as About Us) — verified round-trip
  byte-exact before upload, confirmed in DB, and confirmed live via
  screenshot.
- **`<br />` tags injected inside `<style>` blocks — DONE, fixed same
  session.** yasir pasted raw view-source of the Estrella page and asked
  if this was broken code — it was: every single-line CSS declaration
  inside every `<style>` block was getting a literal `<br />` appended
  at render time (e.g. `padding: 80px 20px;<br />`). Confirmed via
  `wp post get --field=post_content` that the **stored** content was
  clean (zero `<br>`) — this is wpautop (WordPress's auto-paragraph
  filter, see [[paloverde-wp-technique]]) converting every bare
  single-newline to `<br />` at render time, including inside `<style>`
  tags, which it doesn't understand as non-prose content. Same root
  mechanism as the earlier stray-`<p>`-in-grid bug, different symptom.
  Fixed by minifying every `<style>...</style>` block's CSS to a single
  line (collapsing whitespace only, no other rewriting, to avoid
  touching anything inside quoted string values) across all 4 location
  pages — 9, 9, 9, and 8 style blocks respectively. Verified: 0 of 25
  (Estrella/Glendale/Scottsdale) / 24 (East Valley) style blocks contain
  `<br` post-fix. Checked Conditions We Treat and Clinical Trials too —
  both already clean (their CSS was already effectively single-line from
  earlier fixes), no action needed there.
  Also investigated, while looking at this: the doctor photo `src` URLs
  render as `c1b.872.myftpupload.com` when viewed via the
  `459546.us16...` hostname alias instead of `875051.us16...` — this is
  **not** a bug, confirmed via curl: it 301-redirects and serves the
  image fine (200, image/jpeg). This is GoDaddy's own image CDN doing
  per-hostname-alias rewriting; harmless, no fix needed.
- Site is still on the temp domain (`875051.us16.myftpupload.com`) —
  `pvcancer.com` DNS not yet pointed at it. Outside SSH/WP-CLI access,
  needs the client's registrar/DNS action before "going live" is real to
  the public.

## 2026-08-31 — Michael Bustard correction list completed

Michael supplied four issues: physician assignments, missing Dr. Mamani,
missing Gilbert carousel image, low-resolution location photos, and the PET
page's inconsistent format/missing map/all-location block.

### Live changes

- Re-verified all four office rosters against Michael's list; the live rosters
  already matched exactly and were preserved.
- Rebuilt `/your-team/` from its previous introduction-only state into a
  responsive six-physician grid. Dr. Demetrio Mamani is included and all six
  profile links return HTTP 200.
- Added the 2048px Gilbert/GTO office image to the homepage carousel. The
  carousel now has five images and five working dots.
- Replaced the four 160x111 office images with verified full-resolution files:
  Estrella 2048px, Glendale 1280px, Scottsdale 2048px, Gilbert 2048px. Updated
  both `post_content` and `_elementor_data` where present.
- Rebuilt `/pet-scan-imaging/` with the PET location address, dedicated Google
  map iframe, direction button, responsive location-detail layout, preserved
  treatment/FAQ information, and removed the unrelated all-office list and
  malformed social/footer residue from the page body.
- Server backup of every affected `post_content` and `_elementor_data` field:
  `/home/client_b9c1bb2d60_875051/pv-michael-corrections-20260831-183700/`.

### Verification and client handoff

- Automated evidence: `michael-corrections-verification.json`.
- Desktop screenshots are under `screenshots/michael-confirmation-*.png`.
- Mobile checks at 390px show no horizontal overflow on Your Team, PET, or
  East Valley/Gilbert.
- Confirmation email draft and attachment list:
  `MICHAEL-CONFIRMATION-EMAIL-2026-08-31.md`.

## 2026-09-14 — Content depth pass + discovered the site now renders from `_elementor_data`, not `post_content`

**Root-cause finding, supersedes all older "post_content is live" notes in this
file and in CLIENT-INFO.md:** confirmed live that the 4 location pages
(533/461/501/544) currently render from **`_elementor_data`** (a single
`text-editor` widget holding the entire page as one HTML blob), not
`post_content`. `post_content` is a stale, wrapper-less leftover with no
`class` attributes at all — editing it has zero visible effect. This matches
the 2026-08-31 `_elementor_data` JSON-repair note in
`reference-paloverde-wp-technique` ("pages render from `_elementor_data` as
designed" after the corrupt-JSON fix) — that correction just hadn't been
re-confirmed against these 4 specific pages until today. **Any future edit to
these 4 pages must go into `_elementor_data`'s `editor` key, not
`post_content`.**

**Two real, live bugs found and fixed while doing the content pass (not what
was asked, but too significant to leave alone on a cancer-care site):**
- **Scottsdale (501) was publicly showing 4 completely fake doctors** —
  Lauren D. Stegman, Kurt A. Wharton, Abhilash P. Nambiar, John J. Kresl —
  reusing other doctors' photos under the wrong names. This is the exact bug
  reported fixed on 2026-08-18, but that fix only ever landed in
  `post_content`, which stopped being live after the 08-31 JSON repair — so
  the fake roster silently came back. Replaced with the correct roster
  (Halepota, Grover, Ahmad per Michael Bustard's list) using the same
  doctor-card format as the other 3 pages, and moved the section to sit
  right after About (matching the other pages' order — it had been at the
  very bottom of the page).
- **East Valley (544)'s "View Profile" link for Dr. Mamani 404'd**
  (`/demetrio-mamani-md/`). Fixed to `/your-team/dr-mamani/` (confirmed 200).
- Also found Glendale (461) was live with **no Services section at all**
  between Doctors and Map — the 08-18 fix for this had the same
  post_content-only fate as the Scottsdale doctor fix. Added it back (4
  cards, matching the other pages).

**Content-depth work (the actual ask — "content is still pretty thin"),
applied to all 4 location pages' `_elementor_data`:**
- About section: 1 short paragraph + 4 generic bullets → 2 real paragraphs
  (facility location/service area grounded in each page's actual address and
  the real driving-directions text already on the page, cross-location care
  coordination) + 6 bullets.
- All service cards (Medical Oncology, Hematology, Immunotherapy, Radiation
  Therapy where applicable): one generic sentence → 2 sentences with real
  informational depth, written as general patient-education content only —
  no clinical claims, outcomes, or statistics invented.
- Insurance & Billing: "Please call to verify your insurance." → a real
  paragraph about the billing-verification process.
- **New section added to all 4 pages: "What to Expect at Your First Visit"**
  (before you arrive / your consultation / building your care team / ongoing
  support) — reuses the existing `.services-grid`/`.service-card` CSS, no
  new styles needed.
- Doctor cards on Estrella/Glendale/Scottsdale (the 3 pages using the simple
  doctor-card block) now link to each doctor's existing bio page
  (`View Full Bio →`, inline-styled, no new CSS). East Valley already had
  this via its filterable multi-location doctor grid.
- Did **not** touch `post_content` on any of the 4 pages — confirmed dead,
  not worth keeping in sync (nobody sees it, not even the Elementor editor,
  which also reads `_elementor_data`).

**Deploy status: DATABASE UPDATED, but NOT YET VISIBLE LIVE — blocked on a
cache purge that needs wp-admin credentials.** Yasir ran
`deploy_paloverde_content.bat` (2026-09-14) — all 4 `wp post meta update
_elementor_data` calls reported Success, confirmed by re-reading the DB
directly (`wp post meta get 501 _elementor_data` shows the new Scottsdale
roster, no trace of the old fake doctors). But the live site is still
showing the OLD content on 3 of 4 pages (Glendale is correct — see why
below), even when bypassing Cloudflare's edge cache entirely
(`?nocache=1` still returns `CF-Cache-Status: DYNAMIC` yet shows stale
HTML). Root cause: **GoDaddy's managed WordPress stack has an origin-level
Varnish/CDN cache** (`wp-content/mu-plugins/gd-system-plugin/includes/
class-cache-v2.php`, `Cache_V2` class) that sits in front of PHP — separate
from both Cloudflare's edge cache AND the `wpo-cache`/`wpo-minify` static
files the deploy script already clears. `wp post meta update` (meta-only)
never fires WordPress's `save_post`/`clean_post_cache` hooks, which is what
this plugin listens on to know to purge. Tried triggering it directly via
`wp eval 'clean_post_cache($id)'` for all 4 IDs (a standard, safe core WP
call, not a hack) — it should register the plugin's `do_purge()` →
`shutdown` → `purge()` chain, but the live pages were still stale 15+
seconds later, so either it isn't propagating or something (`has_ban()`
guard, an async API call to GoDaddy's infra) is short-circuiting it. The
one confirmed-working purge path is the wp-admin admin-bar "Empty Cache"
button / `?wpaas_action=flush_cache&wpaas_nonce=...` URL, which requires an
authenticated admin session — **`current_user_can()` is checked server-side,
so this cannot be done with just SSH/WP-CLI access; it needs the actual
wp-admin login** (username `460489pwpadmin`, password not stored in any
file this session can read).

**Why Glendale (461) looks correct and the other 3 don't:** pure accident
of cache timing, not a real difference in the fix. Cloudflare's edge cache
has a 31-day TTL. Earlier in this same session, before the deploy, live
`curl` checks were run against Estrella/Scottsdale/East Valley (debugging
the wrong-doctors and broken-link bugs) — those requests got cached at the
CDN edge with the OLD content and a long TTL. Glendale was never fetched
before the deploy, so the first-ever cache entry for it was the post-deploy
verification fetch, which picked up the new content. **Lesson for next
time: avoid live `curl` verification passes on cacheable pages before a
content deploy on this host — it can poison the CDN cache with stale
content that then survives the deploy.**

**RESOLVED, same session — all 4 pages confirmed live and correct.** Yasir
supplied the wp-admin password; logged in via cookie-authenticated curl
(`wp-login.php`, standard `log`/`pwd`/`wp-submit`/`testcookie` fields — note
a bare POST without a prior GET fails with "Cookies are blocked" because the
`wordpress_test_cookie` needs to be set by a GET first). Turned out there
were **three separate stale layers**, not one, and all three had to be
cleared before the real content showed up:
1. Cloudflare's edge cache (31-day TTL) — cleared by the `wpaas_action=
   flush_cache&wpaas_nonce=...` URL (nonce scraped from the admin bar HTML
   after login).
2. **A Redis object cache** (`wp cache type` → `Redis`) — this was the
   real culprit keeping the origin itself stale even with `?nocache=1`
   bypassing Cloudflare entirely. `wp cache flush` cleared it. This is a
   new finding for this site, not previously documented.
3. Cloudflare again, because it had re-cached a stale snapshot from
   mid-troubleshooting — re-ran the `wpaas_action=flush_cache` purge
   *after* the Redis flush so it picked up the now-correct origin content.
Also tried Elementor's own `elementor_site_clear_cache` action along the
way (harmless, didn't hurt, wasn't the actual fix).

**Final verification (plain fetches, no cache-busting params):** all 4
pages return `CF-Cache-Status: MISS→` fresh content. Scottsdale shows only
Halepota/Grover/Ahmad (zero trace of Stegman/Wharton/Nambiar/Kresl).
East Valley's Mamani link is `/your-team/dr-mamani/`. Glendale has its
Services section. All 4 have "What to Expect at Your First Visit". Checked
`doctor-card`/`service-card`/`pv-doctor-card` counts and scanned for the
site's known wpautop stray-`<p>`-in-grid corruption pattern — none found on
any of the 4 pages. Browser screenshot verification was not available this
session (Claude in Chrome extension not connected) — HTTP-level content and
structural checks stood in for it.

**New technique note for [[paloverde-wp-technique]]:** this site's
managed-WordPress stack has at least 3 cache layers (Cloudflare edge, a
GoDaddy Varnish-style layer reachable via the wp-admin `wpaas_action=
flush_cache` nonce URL, and a Redis object cache reachable via `wp cache
flush`). A `wp post meta update` (as opposed to a full `wp post update`)
skips the hooks that would normally invalidate all of these automatically.
For any future meta-only edit to a live page on this site, after the DB
write: (1) `wp cache flush` for Redis, (2) hit the wpaas flush-cache URL for
CDN/Varnish, in that order — Redis first, since purging CDN before Redis is
fixed just re-caches the stale content again.
