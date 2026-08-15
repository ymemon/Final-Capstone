# Bridge Landing Pages — Hosting, Domains & Website Products

> **Four of these are stale. Do not republish them.**
>
> `hosting-domains`, `web-hosting`, `wordpress-hosting` and `vps-hosting` were
> expanded directly in Elementor after these files were written. The live pages
> now run several times longer than the copies here, so publishing these would
> be a downgrade, not an update.
>
> | Page | Live | This file |
> |---|---|---|
> | `hosting-domains` (2286) | substantially expanded | 322 words |
> | `web-hosting` (2287) | substantially expanded | 237 words |
> | `wordpress-hosting` (2288) | substantially expanded | 195 words |
> | `vps-hosting` (2309) | substantially expanded | 237 words |
>
> Pull the live content down and reconcile it into these files before treating
> them as the source of truth again:
>
> ```bash
> wp --path=/html eval-file azw-elementor.php dump <id> azwchtml
> ```
>
> The other seven are current. `business-email-hosting` was published to
> Elementor on 15 Aug 2026, replacing an embedded GoDaddy storefront block.

## Why these exist

shopazwebcorp.com is a GoDaddy white-label reseller storefront (private-label ID `plid=550793`). It cannot be edited (no template/DOM access), and the technical audit found its content is actively being outranked/absorbed by a different reseller's domain (lootertech.com) using the same plid — see `../AZWebCorp-Technical-SEO-Audit-2026-08-04.md` §2.2. Trying to "SEO" that storefront is not viable.

Instead, these are **first-party pages for azwebcorp.com** — one per product category. Each page:

- Is fully optimized (single `<h1>`, unique title/meta description, self-referential canonical, JSON-LD schema)
- Carries original, differentiated editorial content, not the vendor's boilerplate spec sheet — the E-E-A-T fix from the audit's Phase 2
- Ends with a clear CTA handing off to the matching shopazwebcorp.com product page to complete the purchase

This turns the reseller storefront into a pure checkout step, while the organic-search value accrues to azwebcorp.com.

## Pages in this set (11)

Filenames match their target slug exactly, so a page cannot be published to the wrong URL.

| File | Target azwebcorp.com URL | Reseller CTA (`www.shopazwebcorp.com`) |
|---|---|---|
| `hosting-domains.html` | `/hosting-domains/` | hub page — links to all 10 below |
| `web-hosting.html` | `/web-hosting/` | `/products/business` |
| `wordpress-hosting.html` | `/wordpress-hosting/` | `/products/wordpress` |
| `vps-hosting.html` | `/vps-hosting/` | `/products/vps` |
| `website-builder.html` | `/website-builder/` | `/products/website-builder` |
| `domain-registration.html` | `/domain-registration/` | `/products/domain-registration` |
| `domain-transfer.html` | `/domain-transfer/` | `/products/domain-transfer` |
| `business-email-hosting.html` | `/business-email-hosting/` | `/products/professional-email` |
| `ssl-certificates.html` | `/ssl-certificates/` | `/products/ssl` + `/products/ssl-managed` |
| `website-security-firewall.html` | `/website-security-firewall/` | `/products/website-security` |
| `website-backup.html` | `/website-backup/` | `/products/website-backup` |

**The `www.` hostname is mandatory.** Bare `shopazwebcorp.com/products/...` 404s; `www.shopazwebcorp.com/products/...` returns 200. All 11 slugs above were confirmed 200 with the `www` prefix. `audit_pages.py` fails the build if a CTA loses the `www`.

## Tooling

Both scripts live in `../scripts/` and are safe to re-run.

```bash
python3 scripts/audit_pages.py       # audits all pages; exits non-zero on any error
python3 scripts/add_faq_schema.py    # regenerates FAQPage JSON-LD from visible FAQ copy
```

- **`audit_pages.py`** checks, per page: title/description present and within SERP limits, canonical matches the filename slug, exactly one `<h1>`, valid JSON-LD, BreadcrumbList + Product + FAQPage present, breadcrumb terminates on its own URL with sequential positions, every `Offer` carries price + currency, a reseller CTA exists and uses the `www` host with UTM tagging, and every internal link resolves to a page that exists in the set. Across the set it also catches duplicate titles/descriptions and any page orphaned from the hub. Use it as a pre-publish gate.
- **`add_faq_schema.py`** derives FAQPage schema from the rendered `<h3>`/`<p>` pairs under each `<h2>FAQ</h2>`. Google requires FAQ structured data to match visible page text, so generating it from the copy prevents drift when the copy is edited. Byte-idempotent: run it after any FAQ edit.

## Implementation notes

1. **Pricing.** Only the Web Hosting Plus tiers and the domain registration price came from the original brief and are used as given ($20.99–$72.99/mo, $11.99/yr). No other price is invented. Product blocks without an `offers` node are valid schema as-is; each such file carries a commented `offers` template to paste real numbers into. Wrong prices in Product/Offer schema can trigger rich-result penalties, so do not fill these from memory.
2. **Outbound reseller links are plain `follow` links**, not `nofollow`/`sponsored`. The audit's `nofollow` recommendation applies to *third-party sites crediting AZWebCorp*, a different situation — these link to your own storefront. Each CTA carries `?utm_source=azwebcorp&utm_medium=bridge&utm_campaign=<product>` so conversions stay traceable across the domain hop. Pair with GA4 cross-domain measurement (GA4 Admin → Data Streams → Configure tag settings → add shopazwebcorp.com) so one session isn't split in two.
3. **NAP.** Authoritative values, used in the hub's Organization schema and in on-page copy: **(480) 818-5761**, **4690 E Laurel Ave, Gilbert, AZ 85234**, **info@azwebcorp.com**. These must stay byte-identical to the Google Business Profile and the site footer. (An earlier revision of this file listed `(623) 670-1611` and `E Laurel St` — both wrong; corrected 2026-08-05.)
4. **Duplicate-schema hazard.** `hosting-domains.html` carries an Organization block that is correct **only for a static build**. If these publish through WordPress with Rank Math active, Rank Math emits Organization sitewide and this block must be deleted — keeping it re-creates the duplicate-Organization fault fixed on 2026-08-05 by disabling `azw-entity-graph.php`. The file has an inline warning at that block.
5. **Drop-in format.** Each file is a self-contained `<head>` fragment + `<body>` fragment. In WordPress the head block goes through the SEO plugin's fields or a header snippet; the body becomes page content. In a static build both are used as-is.
6. **Internal linking.** Every product page links back to the hub and cross-links to genuinely related products (security ↔ SSL ↔ backup; VPS ↔ Web Hosting Plus). The hub links to all 10 children, grouped under Hosting / Domains & email / Security & backup. `audit_pages.py` fails if any page becomes orphaned from the hub.

## Known open item

`/arizona-web-development/` is linked from `wordpress-hosting.html` and `website-builder.html`. It is a pre-existing site page outside this set, so the audit script skips it (see `EXTERNAL_INTERNAL`). Confirm it resolves 200 before publishing; if the slug differs, update both files.

## Publishing to the live site

This sandbox has **no outbound network** — the egress policy denies HTTPS to every host (verified: even `example.com` fails) and blocks port 22, so neither WP-CLI over SSH nor the REST API can be driven from here. That is structural, not a credentials problem.

Run the publish step from a machine with real network access. Before publishing anything:

```bash
python3 scripts/audit_pages.py   # must exit 0
```

Then review each page, fill any `offers` placeholders with real numbers, and publish.
