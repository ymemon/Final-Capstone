# AZWebCorp SEO — Session Status (5 Aug 2026)

## Done

**7 bridge pages live on azwebcorp.com** (IDs 2286–2292): hosting-domains,
web-hosting, wordpress-hosting, domain-registration, domain-transfer,
website-builder, business-email-hosting. Each has a single `<h1>`, unique
Rank Math SEO title, unique meta description, and self-referential canonical.
Content verified uncorrupted. Published.

**Hub added to primary menu** (item 2300) so the cluster isn't orphaned.

**Redirect hijack found and resolved** — see §2.9 of the main audit. Site now
returns 200, canonical `https://azwebcorp.com/`, `index, follow`, no
`x-redirect-by` header.

**NAP standardised** on (480) 818-5761 / 4690 E Laurel Ave (the site's own
values) across audit and page files.

## Open

| Item | Status |
|---|---|
| ~~Phone fix on live page 2286~~ | **DONE** — now (480) 818-5761 |
| ~~Menu → reseller links~~ | **DONE** — items 453/454/456/457 repointed to owned pages |
| ~~Duplicate meta description (43 pages)~~ | **FIXED.** Source was orphaned Yoast postmeta (`_yoast_wpseo_metadesc`) still being read after the plugin was removed. Confirmed exactly 43 rows; deleted 153 `_yoast_wpseo%` rows total. Page now emits 1 description. Rank Math descriptions now surface in SERPs instead of stale Yoast text |
| Security follow-up | Admin audit clean: only `admin1` (owner) and `Junaid` (developer, since Jul 2024). `SEO Consolidator` gone from wp-content. `wp-config.php` inspected and **clean** — stub config, no `WP_HOME`/`WP_SITEURL` override, no redirect. **Open:** credential rotation (SSH password appeared in chat) |
| ~~Duplicate Organization schema~~ | **FIXED.** `azw-entity-graph.php` and Rank Math both defined `@id: .../#organization`. Disabled the mu-plugin (renamed `.disabled`); JSON-LD blocks 2 → 1, `#organization` refs 10 → 3. Rank Math is now the single source |
| Rank Math Local SEO fields | **Needed** — disabling the mu-plugin dropped `telephone`, `geo` and `foundingDate`. Re-enter under Titles & Meta → Local SEO (phone +1 480-818-5761, 4690 E Laurel Ave, Gilbert AZ 85234, geo 33.3717/-111.7076) |
| FAQ schema | `azw-faq-schema.php` holds 20 FAQPage refs but `/arizona-seo-services/` renders none. Check which pages it targets |
| Remaining mu-plugins | `azw-faq-schema.php`, `azw-h1-normalizer.php`, `azw-auto-alt.php` (regex fixed to treat `alt=""` as missing), `azw-footer-credit.php`, `azw-hardening.php`, `azwebcorp-anchor-fix.php`. All are prior in-house automation (stamped with the generating script), not hostile |
| Missing alt text (46) | Media-library attachments all HAVE alt text — an `azw-auto-alt.php` mu-plugin fills it at render time. The 46 are therefore likely images hardcoded in Elementor content, which that plugin doesn't reach. Needs a different fix |
| Product schema | Blocked on pricing for wordpress-hosting, website-builder, business-email-hosting |
| business-email CTA | Reseller URL unconfirmed; link deliberately omitted |

## Re-crawl before chasing the rest

The 5 Aug 01:30 Ahrefs crawl ran *during* the hijack window (00:49–02:50).
These counts are likely artifacts and should be re-measured before any work:
links to redirect (43), redirected JavaScript (46), image redirects (25),
page has redirected image (15), 3XX redirect (9).

Orphan pages rose 12 → 18, consistent with the 7 new pages landing before the
menu link existed. Should fall back once re-crawled.

## Access notes

Claude's sandbox cannot reach azwebcorp.com (HTTPS 403 by egress policy) or
port 22 (no TCP handshake). All server work is run by the user over SSH.
WordPress REST API is unusable for writes — the host strips the
`Authorization` header (`rest_not_logged_in`); WP-CLI over SSH is the working
path. Large pastes into the SSH terminal corrupt — keep blocks small.
