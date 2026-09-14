# Prestige Windows — Client & Site Facts

## Identity

- **Business:** Prestige Windows — Mesa, AZ owner-operated window & door contractor
- **Live site:** https://prestigewindowsaz.com (WordPress, launched ~May 15, 2026)
- **Phone:** 480-331-3209
- **Email:** info@prestigewindowsaz.com
- **Showroom (separate entity):** Prestige Home Studio LLC —
  prestigehomestudiollc.web.broadlume.com
- **Business address (confirmed, also used as GBP/NAP address):**
  34462 N Scottsdale Rd, Scottsdale, AZ 85266 — this is the showroom address,
  and the client uses it as their business address too. Note this contradicts
  the roadmap's "Mesa-based" framing; use this Scottsdale address for GBP,
  schema, and citations regardless — don't substitute a Mesa address.
- **Brand:** deep black + antique gold, premium/luxury positioning. Tagline:
  "Where Luxury Meets Natural Light."
- **NOT an authorized dealer for anyone.** Corrected by the client
  2026-08-24: "no we are not and nothing in the website should connect to
  that at all. Please remove all the languages ASAP." The earlier
  "authorized dealer for Pella, Renewal by Andersen, American Windows &
  Doors" line came from the original roadmap blueprint and was wrong.
  All references were removed from the live site on 2026-08-24 (Home, FAQ,
  About, Suppliers and the home-backup template) — do not reintroduce brand
  or dealer/distributor claims anywhere, including schema, meta
  descriptions, ad copy or social bios.
- **Site pages:** Home, About, Products (6 categories: Double-Hung, Casement,
  Sliding, Bay & Bow, Picture, Specialty Shape), Window Configurator, Gallery,
  Suppliers, Blog, FAQ, Testimonials, Contact

## Server access

Managed WordPress hosting (GoDaddy/Media Temple-style, myftpupload.com), separate
account from azwebcorp's own hosting. Use `ssh_run_prestige.bat` (see
`reference-prestige-ssh-access` memory) — **do not** use azwebcorp's `ssh_run.bat`
for this client.

- Web root: `/html` (symlinked from `~/html`)
- WP-CLI available at `/usr/local/bin/wp` — needs `--path=/html`
- PHP 8.3.33, MySQL/Percona 8.4.10, WP-CLI 2.12.0

## Still to confirm with client (per blueprint's [CLIENT TO PROVIDE] items)

Business hours, exact service-area city list for FAQ copy, frame color/finish
names actually offered, warranty details beyond manufacturer standard, 5+
testimonials — check whether these were supplied and published since the
build finished; not yet verified in this project. (Business address
confirmed above.)

**Gallery/project photography — confirmed genuinely missing (20 Aug 2026),
not just unverified.** Checked the full media library: everything present
is stock/reference imagery from the build, including one image
(`Marketing-Prestige-Windows.png`) that's actually an MLS real-estate
listing photo with a visible watermark. No real photos of Prestige
Windows' own installations exist in the project. This blocks the
"Project Proof" social content pillar and any Facebook cover photo —
needs the client to supply real photos before that work can proceed.
