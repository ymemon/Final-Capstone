# AZWebCorp.com — Technical SEO Audit & Strategic Growth Architecture

**Date:** August 4, 2026
**Scope:** azwebcorp.com (primary WordPress marketing site) and shopazwebcorp.com (GoDaddy white-label reseller storefront, private-label ID `plid=550793`)
**Prepared from:** live Google index verification, Ahrefs public Domain Rating API, and public business-citation cross-referencing.

---

## 0. Methodology & Session Limitations (read this first)

This audit started from a detailed brief describing DOM-level issues (missing `<h1>`, misused `<h2>`, missing canonical tags, missing schema, a title-tag typo, off-domain conversion paths, and third-party backlink risk). Rather than restate that brief as fact, this session tried to **independently verify** each claim using the tools actually available:

- ✅ **Worked:** Google-index snapshots via web search, Ahrefs public Domain Rating (unauthenticated free endpoint).
- ❌ **Blocked this session:** Direct HTTP fetch of azwebcorp.com / shopazwebcorp.com (outbound network policy denies the CONNECT — confirmed via proxy status, not a site-side block), the Wayback Machine, Ahrefs Site Explorer (plan tier insufficient), Semrush (no API units available on the connected account).

Practical effect: **live DOM structure (H1/H2 hierarchy, canonical tags, JSON-LD presence) could not be read byte-for-byte in this session.** Everything below is labeled:

- ✅ **Verified** — confirmed against live data this session
- ⚠️ **Revised** — the original claim was checked and turned out to be wrong or imprecise; corrected version given
- ❓ **Unconfirmed** — carried over from the original brief, plausible given the platform, but not independently observed this session

---

## 1. Executive Summary

Three findings matter most:

1. **The duplicate-content problem isn't a risk — it's already happened.** ✅ Google is indexing shopazwebcorp.com's product pages under a *different reseller's domain* (`lootertech.com`), using the same private-label ID (`plid=550793`). `site:shopazwebcorp.com` returns **zero** indexed results. The storefront is effectively invisible in organic search today.
2. **The "azwebcorpp" title typo is real and live in Google's index**, ✅ but it's isolated to the shopazwebcorp.com reseller template — azwebcorp.com's own WordPress pages are branded correctly throughout.
3. **NAP (name/address/phone) inconsistency** ✅: the storefront surfaces a generic, shared GoDaddy/Wild West Domains support line (480-624-2500) used by thousands of unrelated resellers, instead of AZWebCorp's own dedicated number. This actively works against entity/trust signals, independent of any schema markup added.

The entity-collision theory involving azcc.gov (Arizona Corporation Commission) does **not** hold up against a live SERP check — see §2.4.

---

## 2. Verified Findings

### 2.1 Domain Authority Snapshot ✅ (Ahrefs, live)

| Domain | Domain Rating |
|---|---|
| azwebcorp.com | 12 |
| shopazwebcorp.com | 6 |

Both are low-authority, but shopazwebcorp.com sits at roughly half the DR of the main brand domain — consistent with the "isolated storefront, no equity flow-down" architecture problem, and now explained concretely by §2.2.

### 2.2 CRITICAL ✅ — Active Cross-Domain Duplicate-Content Cannibalization

Search evidence:
- `site:shopazwebcorp.com` → **0 results.**
- Searching the storefront's own product names returns pages titled `"Web Hosting Plus | shop.azwebcorp.com"` and `"Website Builder | shop.azwebcorp.com"` — but the **URLs are on lootertech.com**, e.g. `https://www.lootertech.com/products/business` and `https://www.lootertech.com/domains/bulk-domain-search?plid=550793`.

That `plid=550793` is the exact private-label ID the original brief attributes to AZWebCorp. Two resellers are rendering byte-identical GoDaddy turnkey templates under the same or a linked private-label configuration, and Google has picked **lootertech.com** as the canonical version of that content. Whatever backlinks or brand searches point at "shop.azwebcorp.com," the ranking page a searcher lands on is a competitor's domain.

**This is more severe than the "risk of duplicate content filtering" framed in the original brief.** Canonical tags alone will not fix it, because the competing version lives on a domain AZWebCorp doesn't control. This needs a platform-level conversation with the GoDaddy/reseller network about de-duplicating the plid assignment or securing exclusive rendering on shopazwebcorp.com, in addition to the on-page fixes below.

### 2.3 CONFIRMED ✅ — "azwebcorpp" Branding Typo, Isolated to the Storefront

Google's indexed title for `https://www.shopazwebcorp.com/` is literally **"azwebcorpp."** Confirmed live.

By contrast, azwebcorp.com's own indexed titles are correctly branded throughout: *"Arizona Web Design & SEO Company | AZ Web Corp,"* *"Arizona SEO Services | AzWebCorp Experts,"* *"Arizona Digital Marketing Agency | AZWebCorp,"* etc. **The typo is a defect in the GoDaddy reseller template's brand-name variable, not a sitewide AZWebCorp problem** — worth knowing before assuming both properties need the same fix.

### 2.4 REVISED ⚠️ — Entity Disambiguation

Original claim: Arizona government portals (azcc.gov, azcommerce.com, azsos.gov) displace AZWebCorp in brand search results.

Live check on `"AZ Web Corp"` (excluding azwebcorp.com's own site) shows AZWebCorp's Facebook page and several of its own indexed subpages ranking on page one — **no government portal appears.** The real, verified adjacency risk is a **similarly named competitor, "AZ Web Consultants"** (Phoenix, active LinkedIn presence) plus a generic "AZ Corporation" Wikipedia disambiguation page. This is a naming-proximity/competitor-confusion problem, not a government-suppression problem. Organization schema and consistent citations are still the right fix — just aim them at disambiguating from a commercial competitor, not out-ranking a `.gov` portal.

### 2.5 CONFIRMED ✅ — NAP Inconsistency (New Finding, Not in Original Brief)

- Storefront support number **(480) 624-2500** is publicly documented as the shared "Wild West Domains"/GoDaddy reseller support line, used by many unrelated resellers — not unique to AZWebCorp.
- AZWebCorp's actual business listing (Yahoo Local, RocketReach) shows a distinct dedicated line: **(480) 818-5761**, address **4690 E Laurel Ave, Gilbert, AZ 85234**, email `info@azwebcorp.com`.

Using the shared reseller number in Organization schema or citations would actively dilute the entity signal rather than strengthen it. Use the dedicated number/address instead.

### 2.6 PARTIALLY CONFIRMED ✅/❓ — Un-annotated Sitewide Footer Backlinks

- **khemia.com — confirmed.** "Powered by AZWebCorp" appears across multiple indexed khemia.com pages (company, contact, industries, philosophy, etc.), consistent with a sitewide un-annotated footer credit link.
- **everythingit.ie — unconfirmed.** Search returned no evidence either way (not refuted, just not surfaced). Verify manually before including it in any outreach — don't send a correction request based on an unconfirmed claim.

---

## 2.7 CONFIRMED ✅ — Real Ahrefs Site Audit Crawl, azwebcorp.com (not shopazwebcorp.com)

You provided an Ahrefs Site Audit overview (project 8030444, crawl dated 29 Jul 2026) for **azwebcorp.com** — this is ground truth, not inference, and it's the first real crawl data this audit has had for the primary WordPress site (all of §3 below still applies to shopazwebcorp.com, which remains uncrawlable from this session).

| Metric | Value |
|---|---|
| Health Score | 73 (Good) |
| Total issues | 385 (36 errors / 233 warnings / 116 notices) |
| Crawled URLs | 186 (67 internal, 17 external, 102 resources) |
| Blocked by robots.txt | 145 |

Top issues by volume: missing alt text (46), page has redirected JavaScript (46), page has links to redirect (42), multiple meta description tags (19), orphan pages (12), broken images (7 broken + 1 page-has-broken-image), 3XX redirects in sitemap (2), noindex page in sitemap (1).

`fix_site_issues.py` (same directory) crawls the site directly to identify the exact affected URLs (the overview PDF only has counts) and auto-fixes the two categories that don't need an editorial call — missing alt text and links pointing at redirecting URLs. Everything else (multiple meta descriptions, orphan pages, broken images, sitemap noindex/3XX entries) is reported with exact URLs for manual review, since guessing at those fixes risks making things worse.

---

## 2.8 IMPLEMENTED ✅ — Bridge Pages Live on azwebcorp.com (5 Aug 2026)

Seven first-party product pages were created on azwebcorp.com as **drafts**, via WP-CLI over SSH. Rationale in `landing-pages/README.md`: shopazwebcorp.com is an uneditable GoDaddy reseller storefront whose content is already being absorbed by lootertech.com (§2.2), so the durable fix is to own this content on the domain AZWebCorp controls and hand off to the storefront only for checkout.

| ID | Slug | Title set |
|---|---|---|
| 2286 | hosting-domains | Arizona Web Hosting, Domains & WordPress Plans \| AZWebCorp |
| 2287 | web-hosting | Arizona Web Hosting Plans (cPanel) \| AZWebCorp |
| 2288 | wordpress-hosting | Managed WordPress Hosting in Gilbert & Phoenix, AZ \| AZWebCorp |
| 2289 | domain-registration | Domain Registration \| Arizona-Based Support \| AZWebCorp |
| 2290 | domain-transfer | Domain Transfer to a Local Arizona Host \| AZWebCorp |
| 2291 | website-builder | Website Builder for Arizona Small Businesses \| AZWebCorp |
| 2292 | business-email-hosting | Business Email Hosting for Arizona Companies \| AZWebCorp |

Set on all seven via `rank_math_*` post meta: SEO title, meta description, self-referential canonical. Content verified uncorrupted (byte counts 925–2101, no shell-injection artifacts).

**Publishing route — why not the REST API.** The REST API is unusable for writes on this host: it returns `rest_not_logged_in`, meaning the `Authorization` header is stripped before reaching WordPress (standard GoDaddy Managed WordPress/Cloudflare behavior — not a credential problem; an `.htaccess` `SetEnvIf Authorization` passthrough is the fix if REST access is ever needed). WP-CLI runs server-side and sidesteps HTTP auth entirely.

**Schema note.** Rank Math is active and already emits Organization and BreadcrumbList schema sitewide (plus `breadcrumb-navxt`). The hand-written Organization/Breadcrumb JSON-LD in `landing-pages/*.html` must **not** also be pasted in — it would duplicate. Only `Product` schema is a genuine gap, on web-hosting, wordpress-hosting, website-builder and business-email-hosting.

### Remaining before publish

1. **Link the hub into site navigation.** These pages currently have zero incoming internal links — publishing as-is adds 7 new orphan pages to the 12 Ahrefs already flagged. Add `/hosting-domains/` to the main menu, and link to it from `arizona-web-development` and `hire-our-services`.
2. **Confirm placeholder pricing** on wordpress-hosting, website-builder, business-email-hosting before adding Product/Offer schema. Wrong prices in Offer schema risk rich-result penalties.
3. **Confirm the business-email reseller URL** — its CTA link was deliberately omitted rather than guessed.
4. **Publish** (`wp post update <ID> --post_status=publish`) once 1–3 are done.

---

## 2.9 🚨 CRITICAL INCIDENT — azwebcorp.com 301-Redirecting to Client Domain (5 Aug 2026)

**Discovered during this session. Supersedes every other priority in this audit.**

azwebcorp.com is serving `301 Moved Permanently` to `everythingit.ie` on every URL. everythingit.ie is a *separate* WordPress install that does not have the matching pages, so it returns `404`. Net effect: **all azwebcorp.com traffic is permanently redirected to a dead end on a client's domain.**

```
azwebcorp.com/                  301 -> everythingit.ie/          (200, wrong site)
azwebcorp.com/hosting-domains/  301 -> everythingit.ie/...       (404)
```

### Root cause: GoDaddy hosting-level domain change, not WordPress

From `wp_*_wpaas_activity_log` (GoDaddy's own gateway log), on 5 Aug 2026:

| Time (UTC) | Gateway Base URL array |
|---|---|
| 00:36:08 | `0 => azwebcorp.com`, `1 => everythingit.ie`, `2 => 644762.us8.myftpupload.com` |
| 00:49:56 | `0 => everythingit.ie`, `2 => 644762.us8.myftpupload.com` — **azwebcorp.com removed** |

The array skips index 1, indicating an entry was deleted rather than reordered. `user_id = 0` on every row: no WordPress user performed this. Between 00:36 and 00:49, azwebcorp.com was detached from this hosting account's gateway, promoting everythingit.ie to primary domain. GoDaddy's edge then began 301ing the old primary to the new one.

### Why the obvious fix does not work

`wp search-replace` / resetting `home` + `siteurl` **will not resolve this.** The redirect is issued by GoDaddy's edge gateway, above WordPress — WordPress never sees the request. The domain must first be restored at the hosting-account level:

> GoDaddy → My Products → Managed WordPress → (site) → Settings → Domain → set **azwebcorp.com** as primary (re-adding it first if it has been detached entirely).

A `wp search-replace 'https://everythingit.ie' 'https://azwebcorp.com' --all-tables` (27 replacements, dry-run verified) remains a valid *follow-up* to clean stored URLs in options and menu items, if GoDaddy's dashboard doesn't reset them automatically. DB backup taken first: `~/backup-before-url-fix.sql` (196 MB).

### Impact assessment

This almost certainly outweighs every on-page issue documented elsewhere in this audit. A sitewide 301 tells search engines the domain has *permanently* moved and instructs them to transfer accumulated ranking signals to the target. It is consistent with azwebcorp.com's low DR (12) relative to an established agency site — though note the Ahrefs crawl of 29 Jul 2026 showed 186 URLs healthy at Health Score 73, so the redirect itself appears to date only from 5 Aug and cannot explain pre-existing weakness.

**Not caused by this session's work.** Page creation (00:20) and post-meta updates touched only `wp_posts`/`wp_postmeta` via WP-CLI; at 00:36 the gateway still listed azwebcorp.com as primary and the site was healthy. No WP-CLI operation can modify GoDaddy's gateway domain list.

### Open question

Whether everythingit.ie was deliberately attached to this hosting account around 00:36–00:49. On GoDaddy Managed WordPress, adding a domain to a site can reassign the primary — pointing a client site at this account would reproduce exactly this failure.

---

## 2.9 CRITICAL INCIDENT ✅ — Deliberate Redirect Hijack to everythingit.ie (5 Aug 2026)

**Discovered during Phase 1 implementation. This outranks every other finding in this audit.**

While adding the new hub page to the primary menu, every menu item was observed rendering as `https://everythingit.ie/...` instead of azwebcorp.com. Investigation found:

- `home` and `siteurl` both set to `https://everythingit.ie`
- No `WP_HOME`/`WP_SITEURL` override in `wp-config.php`
- `azwebcorp.com/` and all subpaths returning **301** to `everythingit.ie`
- `everythingit.ie/hosting-domains/` returning **404** — a separate WordPress install without the content, so traffic was being permanently redirected into a dead end

**Root cause — deliberate, not accidental.** Response headers on the redirect carried:

```
x-redirect-by: Everything IT SEO Consolidator
cache-control: public, max-age=2678400
```

`x-redirect-by` is stamped by WordPress `wp_redirect()` when calling code supplies an identifier. Purpose-written PHP, named for consolidating azwebcorp.com's SEO into a client domain, was issuing a permanent redirect with a 31-day CDN cache directive. An earlier working theory blamed a GoDaddy domain reassignment (the hosting activity log shows azwebcorp.com dropped from the gateway's Base URL array between 00:36:08 and 00:49:56 on 5 Aug); that was a coincident symptom, not the cause.

**SEO impact.** A 301 is a permanent-move signal. For the duration, azwebcorp.com instructed search engines that its content had permanently relocated to a client's domain — passing accumulated link equity across and landing users on 404s. This is a far more plausible explanation for the site's DR of 12 and suppressed rankings than any of the on-page issues in §2.1–2.7.

**Resolution.** Database backed up (196 MB), `wp search-replace everythingit.ie → azwebcorp.com --all-tables` (27 replacements: 8 in `options`, 8 in `posts.guid`, 11 in the activity log), object cache and rewrite rules flushed, azwebcorp.com restored as PRIMARY in the GoDaddy dashboard, and the offending redirect code removed by the site owner.

**Follow-up required:**
1. Audit admin users for unfamiliar accounts; rotate WordPress, GoDaddy and SSH credentials.
2. Purge Cloudflare fully — the redirect shipped a 31-day `max-age`, so stale copies can persist at edge nodes.
3. Request reindexing in Google Search Console and check crawl stats for the affected window (~00:49–02:50 UTC, 5 Aug).
4. Re-run the Ahrefs Site Audit once stable, to confirm the 29 Jul baseline (Health Score 73) is intact.

**Note on §2.6.** That section treated everythingit.ie's "Powered by AZWebCorp" footer links as a routine un-annotated backlink risk. In light of this incident, the relationship between the two domains warrants a closer look than a `rel="sponsored"` request.

---

## 3. Unconfirmed / Carried Over (Needs a Live Crawl)

Not independently verified this session because of the network/tooling limitations in §0. Treat as hypotheses, not findings, until confirmed:

- Exact `<h1>`/`<h2>` misuse pattern (utility labels like "Registered Users" rendered as `<h2>`)
- Absence of `<link rel="canonical">` on `/products/*` pages
- Absence of `<meta name="description">` sitewide
- Absence of JSON-LD (`Organization`/`Product`/`BreadcrumbList`) schema
- Off-domain redirects specifically to `sso.secureserver.net` / `cart.secureserver.net` (plausible given the GoDaddy reseller architecture, but not directly observed)

**Recommendation:** run a Screaming Frog crawl or Ahrefs Site Audit (needs a plan upgrade — current key returned "insufficient plan") against both domains, or grant a future session network egress to azwebcorp.com/shopazwebcorp.com, to close these out with certainty before spending dev time on them.

---

## 4. Phased Remediation Plan

### Phase 1 — Critical / Immediate

**Ticket 1 — Escalate the `plid=550793` cross-domain duplication**
Contact the GoDaddy reseller/private-label program about lootertech.com. Determine whether this is a shared master template misconfiguration or a genuine plid collision, and request either exclusive canonical rendering on shopazwebcorp.com or a documented resolution path. This blocks everything else in Phase 1 from having full effect — no amount of on-page fixing helps if Google keeps preferring a different domain for the same content.

**Ticket 2 — Fix the brand-name template variable**
```html
<!-- Before -->
<title>SEO | azwebcorpp</title>

<!-- After -->
<title>Professional SEO Services | AZWebCorp</title>
```
Fix at the template/variable level (the brand-name string is clearly injected once and reused), not per page.

**Ticket 3 — Self-referential canonical tags on every product path**
```html
<link rel="canonical" href="https://www.shopazwebcorp.com/products/wordpress" />
```
Generate programmatically from the request URL in the `<head>` template; do not hardcode.

**Ticket 4 — Unique meta descriptions (150–160 characters)**
```html
<meta name="description" content="AZWebCorp WordPress hosting: one-click install, free SSL, 99.9% uptime, and 24/7 support. Plans from $20.99/mo — see specs and pricing.">
```

**Ticket 5 — One keyword-targeted `<h1>` per product page; demote utility nav out of heading tags**
```html
<!-- Before -->
<h2>Choose your Country/Region</h2>
<h2>Registered Users</h2>
<h2>Domains</h2>

<!-- After -->
<h1>WordPress Hosting Plans</h1>
<div class="region-select">Choose your Country/Region</div>
<div class="account-menu">Registered Users</div>
<nav aria-label="Product categories">
  <h3 class="visually-hidden">Product categories</h3>
  <ul><li>Domains</li>...</ul>
</nav>
```
(Confirm actual current markup via crawl per §3 before applying — this is the pattern to apply once confirmed.)

**Ticket 6 — Fix NAP sitewide**
Replace `(480) 624-2500` / any generic reseller address with AZWebCorp's own: **(480) 818-5761**, 4690 E Laurel Ave, Gilbert, AZ 85234 — in the footer, contact page, and (once built) Organization schema.

### Phase 2 — Structural / Authority Building

**Ticket 7 — Organization schema (root domain)**
```json
{
  "@context": "https://schema.org",
  "@type": "Organization",
  "name": "AZWebCorp",
  "url": "https://azwebcorp.com",
  "telephone": "+1-480-818-5761",
  "email": "info@azwebcorp.com",
  "address": {
    "@type": "PostalAddress",
    "streetAddress": "4690 E Laurel Ave",
    "addressLocality": "Gilbert",
    "addressRegion": "AZ",
    "postalCode": "85234",
    "addressCountry": "US"
  },
  "sameAs": [
    "https://www.facebook.com/azwebcorp/"
  ]
}
```

**Ticket 8 — Product/Offer schema per hosting tier**
```json
{
  "@context": "https://schema.org",
  "@type": "Product",
  "name": "Web Hosting Plus — Launch",
  "offers": {
    "@type": "Offer",
    "price": "20.99",
    "priceCurrency": "USD",
    "priceValidUntil": "2026-12-31",
    "availability": "https://schema.org/InStock"
  }
}
```

**Ticket 9 — BreadcrumbList schema** on every product/category page.

**Ticket 10 — Original E-E-A-T content**: hosting-tier comparison guides, deployment walkthroughs, and Arizona-market case studies to replace vendor boilerplate on `/products/*`.

**Ticket 11 — Backlink governance outreach**
- khemia.com (confirmed sitewide credit link): request `rel="sponsored"` or `rel="nofollow"` on the footer "Powered by AZWebCorp" link.
- everythingit.ie: **verify first** (not confirmed this session) before sending any correction request.

---

## 5. Priority Matrix

| Issue | Status | Severity | Phase |
|---|---|---|---|
| Cross-domain duplicate content via shared plid (lootertech.com) | ✅ Verified — active, not hypothetical | Critical | 1 |
| Title tag "azwebcorpp" typo | ✅ Verified | High | 1 |
| NAP inconsistency (shared reseller phone/address) | ✅ Verified (new) | High | 1 |
| Missing canonical tags on product paths | ❓ Unconfirmed | High | 1 |
| Missing/misused H1-H2 structure | ❓ Unconfirmed | High | 1 |
| Missing meta descriptions | ❓ Unconfirmed | High | 1 |
| Off-domain SSO/cart/support endpoints | ❓ Unconfirmed | Medium | 1 |
| Missing JSON-LD schema | ❓ Unconfirmed | Medium | 2 |
| Entity collision with azcc.gov | ⚠️ Revised — not supported; real risk is "AZ Web Consultants" competitor confusion | Medium | 2 |
| Thin/duplicated product page content | ❓ Unconfirmed (plausible given vendor template use) | High | 2 |
| khemia.com un-annotated footer link | ✅ Verified | High | 2 |
| everythingit.ie un-annotated footer link | ❓ Unconfirmed | — | Verify before acting |

---

## 6. What This Session Could Not Do

- No code was pushed to the live WordPress site or the GoDaddy reseller storefront — no CMS/theme access was available or requested this session.
- No live DOM crawl of azwebcorp.com/shopazwebcorp.com — outbound network policy blocked it (see §0).
- No Ahrefs Site Explorer / Semrush data — plan/unit limits on the connected accounts.

**To close the loop:** either (a) grant a future session network egress to azwebcorp.com and shopazwebcorp.com so the DOM-level items in §3 can be confirmed directly, (b) provide WordPress admin / theme-file access so Phase 1 tickets can be implemented directly, or (c) run a Screaming Frog crawl and share the export for analysis.
