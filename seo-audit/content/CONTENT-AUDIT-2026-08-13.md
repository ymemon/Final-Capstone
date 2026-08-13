# Content Audit — azwebcorp.com

**Date:** 13 August 2026
**Scope:** Editorial/content gap analysis. The technical remediation is covered separately in `../AZWebCorp-Technical-SEO-Audit-2026-08-04.md`.
**Data sources:** Semrush dashboard (Site Audit 2 Aug, On-Page Checker 5 Aug, Position Tracking 15 May–12 Aug), Google Search Console (14 Jul–13 Aug), Google Analytics (14 Jul–12 Aug), all as reported by the account owner on 13 Aug.

Ahrefs and Semrush keyword APIs were **not** available for this audit (Ahrefs returned `Insufficient plan`; the Semrush account is out of API units). Every number below is therefore observed from the account's own dashboards, not pulled fresh. **No search volume, keyword difficulty, or competitor figure is asserted anywhere in this document**, because none could be verified. Volume research is the one prerequisite that has to happen before the priority order here is treated as final — see §6.

---

## 1. Read the traffic problem correctly first

The stated problem is "zero keywords and zero traffic." The data says something more specific, and the distinction changes what content is worth writing.

| Signal | Value | What it means |
|---|---|---|
| GSC impressions | 3K (−70.5%) | Pages **are** indexed. Google is showing them. |
| GSC average position | 57.1 (down 8.3 places) | They surface around page 5–6. |
| GSC clicks | 9 (−35.7%) | Nobody clicks results at position 57. |
| GSC CTR | 0.3% | Consistent with position 57. Not a title/meta problem. |
| Position tracking visibility | 0% | Zero of the 6 tracked money keywords are in the top 10. |
| Organic keywords | 213 (−15.5%) | The site ranks for things — none of them commercial. |

**This is not an indexation problem.** It is a competitiveness problem: the pages exist, Google reads them, and ranks them behind everyone else.

### 1.1 The cause is mostly not content

Section 2.9 of the technical audit documents a **redirect hijack on 5 August 2026**: `home` and `siteurl` were set to `https://everythingit.ie`, and azwebcorp.com served `301 Moved Permanently` to that domain sitewide, landing on 404s. A 301 is a permanent-move instruction. For the duration of that window, azwebcorp.com told Google its content had permanently relocated to a domain it does not control.

The declines above are consistent with that window: impressions −70.5%, average position 8.3 places worse. **Attributing the traffic collapse primarily to thin content would be wrong**, and would lead to over-investing in copy while under-investing in recovery.

Content work is still the right next investment — the site genuinely lacks commercial pages, per §2 below. But set expectations accordingly:

- **Recovery from the 301 must complete first.** Google has to re-crawl, drop the permanent-move signal, and restore the original URLs. That is measured in weeks, not days, and is not something content accelerates.
- **New content will not rank at its true level until that resolves.** Publishing during recovery is correct — it gives Google fresh signals — but judge it on trajectory after the recovery window, not on week-two rankings.
- **The one honest bright spot:** `arizona web company` sits at **position 1.9** with 18 impressions. The domain can rank at the top when a query matches a page well and competition is thin. That is a capability signal, not a fluke.

---

## 2. The actual content gap

Six keywords are tracked. Five have **no dedicated page on the site at all**.

| Tracked keyword | Position | Page that should own it | Status |
|---|---|---|---|
| `arizona web development` | 95 | `/arizona-web-development/` | Exists — 9 on-page issues flagged |
| `seo services arizona` | not ranking | `/arizona-seo-services/` | Exists — thin |
| `az seo optimization services` | not ranking | `/arizona-seo-services/` | Same page, no variant coverage |
| `digital marketing arizona` | not ranking | `/arizona-digital-marketing/` | Exists — thin |
| `seo company in phoenix arizona` | not ranking | — | **No page exists** |
| `seo services gilbert` | not ranking | — | **No page exists** |

Two of the six tracked terms are **city-qualified**, and the site has **no city pages**. You cannot rank for "seo services gilbert" without a page about SEO services in Gilbert. This is the single clearest, most fixable gap in the account.

### 2.1 Gilbert is the biggest miss

AZWebCorp is physically at **4690 E Laurel Ave, Gilbert, AZ 85234**. A real address in the city being searched is the strongest possible local ranking asset, and the site currently does nothing with it. Home-city pages also convert better than metro-wide pages, because proximity is the thing local searchers are actually filtering on.

### 2.2 One city page already exists — in the wrong place

`/2025/12/05/scottsdale-web-design-seo-services/` is a Scottsdale service page buried in a date-based blog URL. It drew 3 pageviews. Date-stamped URLs signal "news, dated, expiring" to both users and crawlers — wrong container for an evergreen service page. It should move to `/web-design-scottsdale-az/` with a 301 from the old URL.

### 2.3 Editorial depth is now near zero

Three WordPress-troubleshooting posts were trashed during remediation (`wordpress-critical-error-on-the-website`, `what-is-critical-error-on-wordpress`, `wordpress-tutorials-...`). That was defensible — they were off-topic for a web agency's commercial intent and two were flagged with 7–8 on-page issues each.

**But be honest about the cost.** `wordpress critical error` was drawing impressions at position 62.5, and those posts were the site's only informational content. Removing them contributes to the −70.5% impressions figure. The site now has essentially no top-of-funnel content, and no internal-linking targets to push authority toward the money pages.

### 2.4 Redirect pointing at the wrong page

`/affordable-printing-services/` was redirected to `/hire-our-services/` on the stated basis that no printing page existed. The Semrush On-Page report lists **`/arizona-printing-services/`** as a live page with 2 optimization ideas. If that page is live, the redirect is sending printing traffic to a generic services page instead of the relevant one.

**Action:** verify `/arizona-printing-services/` returns 200, then repoint the redirect in `../redirects/azw-301-map.htaccess`.

### 2.5 Homepage carries the traffic and is not optimized

The homepage takes **110 of 167 pageviews (66%)** and carries 9 flagged on-page issues. Average time on page is **7 seconds** with a 43% engagement rate — visitors arrive and leave. Any homepage improvement affects two-thirds of all sessions, which makes it the highest-leverage single page on the site despite not being a keyword target itself.

---

## 3. What was written in this pass

Three pages, in `./` alongside this document:

| File | Target URL | Why this one |
|---|---|---|
| `seo-services-gilbert-az.html` | `/seo-services-gilbert-az/` | Tracked keyword, no page, home city, highest conversion intent |
| `seo-company-phoenix-az.html` | `/seo-company-phoenix-az/` | Tracked keyword, no page, largest metro in the service area |
| `arizona-seo-services.html` | `/arizona-seo-services/` | **Rewrite** of an existing thin page that already receives traffic |

### 3.1 On doorway pages — read before adding more cities

Google penalizes "doorway pages": near-identical templates with the city name swapped. The two city pages here are deliberately **not** interchangeable — different structure, different sections, different arguments, different FAQs, because Gilbert (home city, in-person, small business) and Phoenix (metro, remote-first, larger competitors) are genuinely different sales situations.

**If you add Mesa, Chandler, Tempe, or Scottsdale later, they must be written the same way — from scratch, per city.** Copying either file and find-replacing the city name is the exact pattern that gets a site filtered. If there is nothing specific to say about a city, that city does not get a page.

### 3.2 Honesty constraint applied to the Phoenix page

AZWebCorp has one address, in Gilbert. The Phoenix page therefore says it **serves** Phoenix from the Gilbert office. It does not claim a Phoenix location, and it carries no Phoenix address in its schema. Fabricating a local address is a Google Business Profile violation, and the resulting listing suspension costs more than the page could ever earn.

### 3.3 Schema differs from the bridge pages

These use `ProfessionalService` + `BreadcrumbList` + `FAQPage`. They are services, not products — no `Product`/`Offer` node and no reseller CTA. **`../scripts/audit_pages.py` therefore does not apply to this directory**; it requires `Product` schema and a `shopazwebcorp.com` CTA on every non-hub page and would fail these files by design. See `./README.md`.

### 3.4 Placeholders you must fill before publishing

Both city pages contain bracketed placeholders — `[CLIENT EXAMPLE]` and similar — where a real client name, industry, or result belongs. **These are the parts that make the pages rank and convert**, and they are the parts only you can supply.

Do not invent them, and do not publish with the brackets in place. A page claiming unverifiable results is worse than one that omits them.

---

## 4. Not written, and why

| Item | Reason |
|---|---|
| Mesa / Chandler / Tempe city pages | Same reason as §3.1 — each needs genuine local substance, and volume research (§6) should confirm they are worth the effort before writing four more |
| Scottsdale page migration | The content exists; this is a URL move plus a 301, not a writing task — see §2.2 |
| `/arizona-digital-marketing/` rewrite | Same thin-page problem as `/arizona-seo-services/`, but that page has no measured traffic. Fix the trafficked page first, apply the same pattern after |
| Homepage rewrite | Highest leverage (§2.5), but rewriting the homepage of a live business without a brief on positioning, target segments, and current conversion path would be guesswork. **Recommend this as the next engagement** — it needs your input, not more inference |
| Blog / top-of-funnel content | Real gap (§2.3), but commercial pages come first. Informational content matters once there are money pages worth linking to |

---

## 5. Publishing order

1. **Purge Cloudflare cache** — still outstanding from the technical pass; the CDN is serving a stale sitemap, so Google cannot see current state
2. **Fill the `[CLIENT EXAMPLE]` placeholders** (§3.4)
3. **Publish `/seo-services-gilbert-az/`** — home city, best chance of ranking during recovery
4. **Publish `/seo-company-phoenix-az/`**
5. **Replace the body of `/arizona-seo-services/`** — keep the existing URL, do not create a new one; it already has traffic and whatever link equity exists
6. **Link all three from the main navigation and the footer** — new pages with no internal links are orphans regardless of quality
7. **Add all three to Position Tracking**, then re-crawl

## 6. Prerequisite before extending this plan

Restore keyword API access (Ahrefs plan, or Semrush units at https://www.semrush.com/mcp-access) and verify search volume for the city terms before writing additional city pages. The priority order in §5 is based on **tracked-keyword gaps and conversion logic**, not on measured volume, because measured volume was unavailable. That is a sound basis for the three pages written here — they fill gaps in keywords already chosen for tracking — but it is not sufficient grounds for committing to four more.

---

## 7. Measuring this honestly

Do **not** judge these pages in week two. The 301 recovery (§1.1) dominates the signal until it completes.

| When | Look at | Not at |
|---|---|---|
| Weeks 1–2 | Indexation: do the new URLs appear in GSC Coverage? | Position, traffic |
| Weeks 3–4 | Impressions on the new URLs; average position trend sitewide | Clicks |
| Weeks 6–8 | Position for `seo services gilbert`, `seo company in phoenix arizona`; whether average position has moved off ~57 | — |
| Week 12 | Clicks, conversions, whether more city pages are justified | — |

The metric that tells you recovery is working is **average position moving off 57**, sitewide. That is the number to watch. Everything else follows it.
