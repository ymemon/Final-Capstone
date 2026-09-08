# Service & Location Content

Editorial pages targeting commercial keywords the site tracks but has no page for. The gap analysis behind them is in `CONTENT-AUDIT-2026-08-13.md`.

## Files

| File | Target URL | New or rewrite |
|---|---|---|
| `seo-services-gilbert-az.html` | `/seo-services-gilbert-az/` | New |
| `seo-company-phoenix-az.html` | `/seo-company-phoenix-az/` | New |
| `case-studies.html` | `/case-studies/` | New |

`case-studies.html` carries a publishing constraint of its own: only the Everything IT entry is cleared. Palo Verde Cancer Specialists and Prestige Windows are drafted as commented-out blocks at the foot of the file and must not be published until each client has agreed to be named — and, for Palo Verde, until `pvcancer.com` actually resolves to the site we built.

## `/arizona-seo-services/` is not managed here any more

That page was rebuilt in **Elementor** on the site (`_elementor_data`, `elementor_header_footer` template). Elementor is its display source, so `post_content` renders to nobody — a section added there is invisible to visitors, which is exactly what happened when a case-study cross-link was written into it. Its `.html` file has been removed from this directory so nobody publishes over it expecting a visible change.

Edit that page in Elementor. Its schema is handled outside the database too: Rank Math emits WebPage, Service and BreadcrumbList, and `tools/azw-arizona-faq-schema.php` (an mu-plugin) supplies the FAQPage its accordion markup does not give Rank Math.

## Schema that lives somewhere else

`REQUIRED_SCHEMA` in `validate.py` expects all three types in the file. When a type is legitimately emitted elsewhere — Rank Math builds FAQPage automatically from visible Q&A on some pages, and a second copy is duplicate schema rather than a fix — declare it instead of adding a duplicate:

```html
<!-- schema-provided-elsewhere: FAQPage -->
```

## Do not edit these pages in the WordPress editor

Saving a schema-carrying page in the block editor **strips `<script>` tags but keeps their contents**. On `/seo-company-phoenix-az/` that turned three JSON-LD blocks into 3KB of raw JSON displayed to visitors, with the quotes curled by `wptexturize`, and the page lost all of its structured data. Publish changes through `../scripts/azw-publish-content.php`, which disables kses for the write.

## Before publishing

1. **Fill the `[CLIENT EXAMPLE]` placeholders** in both city pages. They mark where a real client name, industry and result belong. Do not invent them — delete the paragraph if you cannot substantiate it. An unverifiable claim is worse than an absent one.
2. **Link the pages from navigation and footer.** An unlinked page is an orphan no matter how good it is.
3. **Add the two new URLs to Position Tracking** so there is a baseline to measure against.

## Why these are not in `../landing-pages/`

Different content type, different schema, different validator.

The bridge pages in `../landing-pages/` are **product** pages: `Product` schema with `Offer` nodes, and a CTA handing off to shopazwebcorp.com for checkout. `../scripts/audit_pages.py` enforces exactly that — it fails any non-hub page missing `Product` schema or a `www.shopazwebcorp.com` CTA.

These are **service** pages. They use `ProfessionalService` schema, have no offers and no reseller CTA, because nothing here is bought through the storefront. Running `audit_pages.py` against this directory would report failures by design. Use `validate.py` in this directory instead.

```bash
python3 seo-audit/content/validate.py
```

It checks JSON-LD validity, exactly one `<h1>`, canonical matching the filename slug, presence of BreadcrumbList + ProfessionalService + FAQPage, and — the one Google actually penalizes — that every FAQ Q&A in the schema appears byte-identically in the visible copy.

## NAP

Authoritative, and must stay byte-identical to the Google Business Profile, the site footer, and `../landing-pages/hosting-domains.html`:

**(480) 818-5761** · **4690 E Laurel Ave, Gilbert, AZ 85234** · **info@azwebcorp.com**

## Two constraints that carry real risk

**Do not fabricate locations.** AZWebCorp has one address, in Gilbert. `seo-company-phoenix-az.html` says it *serves* Phoenix from Gilbert and carries the Gilbert address in its schema. A fabricated local address is a Google Business Profile violation, and a listing suspension costs more than any page earns.

**Do not clone these for more cities.** Near-identical pages with the city name swapped are doorway pages, and Google filters them. The Gilbert and Phoenix pages here are deliberately different in structure and argument because the two sales situations genuinely differ. If Mesa or Chandler get pages later, write them from scratch — and if there is nothing specific to say about a city, it does not get a page.
