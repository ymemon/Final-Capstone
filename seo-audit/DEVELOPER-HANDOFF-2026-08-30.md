# Arizona Web Corp SEO developer handoff — 30 August 2026

This is the continuation point for Claude, Codex, or another developer. Read
this file and the latest section of `SESSION-STATUS.md` before changing the
live site. The WordPress root is `/html`; current access helpers are outside
Git at `C:\Users\yasir\.claude-tools\ssh_run.bat` and `scp_run.bat`.

## Live work completed in this pass

- `azw-organization-geo.php` is deployed in `/html/wp-content/mu-plugins/`.
  It enriches Rank Math's existing Organization node with the verified Gilbert
  coordinates `33.3717,-111.7076`; it does not create a second Organization.
- `azwebcorp-security-pages.php` now points Security and SSL Service schema to
  `https://azwebcorp.com/#organization`. Server backup:
  `/html/wp-content/mu-plugins/azwebcorp-security-pages.php.bak-20260830-provider`.
- `azw-audit-results-noindex.php` is deployed. `/seo-audit-results/` is
  `noindex, follow`, excluded as post 2491 from Rank Math's sitemap;
  `/free-seo-audit/` remains indexable.
- `azw-author-archive-noindex.php` is deployed. Author archives are
  `noindex, follow`; `authors_sitemap=off`.
- `azw-category-archive-noindex.php` is deployed. Thin category archives are
  `noindex, follow`; `tax_category_sitemap=off`. This was based on current GSC:
  category archives produced no clicks, have zero or one post each, and were
  competing for unrelated commercial queries. Server option backups:
  `/home/client_9d34da8b_644762/rankmath-titles-backup-20260830-category-noindex.json`
  and `/home/client_9d34da8b_644762/rankmath-sitemap-backup-20260830-category-noindex.json`.
- Page 2257 stores the improved title
  `Phoenix Web Development Services | AZWebCorp`. H1 and page copy were not
  altered.

## Verification already completed

- Cache-busted live checks show Organization geo, normalized Service provider,
  audit-results noindex, author noindex, category noindex, and the new Phoenix
  title at origin.
- Sitemap index has no author or category sitemap. Its page sitemap excludes
  `/seo-audit-results/` and includes `/free-seo-audit/`.
- Latest crawl evidence is `evidence/azw-live-crawl-2026-08-30.json`: 30 sitemap
  pages, 40 internal URLs, zero duplicate titles/descriptions, zero broken
  internal links and zero redirecting internal links. The one title-length
  finding is a stale Cloudflare edge copy described below.
- Fresh GSC query/page evidence for 1 June–29 August 2026 is
  `evidence/gsc-query-page-2026-06-01_to_2026-08-29.json` (1,261 rows). The
  matching request payload is beside it. `gsc_query_oauth.py` now supports an
  `@request-file.json` argument so PowerShell does not corrupt inline JSON.

## Do this first on the next login

1. Purge the Cloudflare cache for
   `https://azwebcorp.com/phoenix-web-development/` or purge the entire zone
   from the Cloudflare dashboard. The bare URL still returns the old
   73-character title with `CF-Cache-Status: HIT` and a 31-day cache lifetime;
   a cache-busted URL returns the corrected title. WordPress has no usable
   Cloudflare API credential, and its plugin purge hook makes no effective API
   request. Do not revert the correct origin title.
2. Re-run `tools/azw_live_seo_crawl.py`. Expected after the dashboard purge:
   30 pages and all issue counts zero.
3. Confirm the bare category and author URLs eventually show `noindex, follow`;
   origin/cache-busted responses already do.

## External/account-dependent work still open

- The separate reseller storefront is still publicly returning the typo title
  `azwebcorpp` on its homepage and `/products/wordpress`, with no canonical.
  This cannot be corrected from the main WordPress SSH account. Obtain the
  GoDaddy reseller/storefront template login, fix the brand variable globally,
  add self-referencing product canonicals, and then crawl the full storefront.
- GA4 receives `form_submit`, but marking it as a key event needs a Google OAuth
  grant with Analytics edit scope. The saved token is read-only.
- Do not invent Website Builder pricing, `foundingDate`, or portfolio ownership.
  Website Builder has no verified public price; founding date is unverified;
  several portfolio images appear to be another agency's Australian work.
- SSH password rotation requires the GoDaddy dashboard.
- Only four of the client's 38 rank-tracker rows were provided. Do not infer the
  other 34.

## Git discipline

The repository contains many unrelated modified and untracked files belonging
to other client work. Preserve them. Stage only files explicitly related to
this Arizona SEO pass; never use `git add .`, `git reset --hard`, or checkout
commands that could discard another developer's changes.
