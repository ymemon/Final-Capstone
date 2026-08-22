# Case Study: EverythingIT.ie

## Context

EverythingIT.ie had a large WordPress service architecture accumulated across multiple generations of content. The site contained nested legacy URLs, duplicate or draft variants, inconsistent menu assignments, pages split between classic WordPress content and Elementor metadata, and several caching layers that could conceal successful production changes.

## Responsibilities

I worked across frontend implementation, WordPress data structures, technical SEO and production operations. My responsibilities included:

- auditing content, headings, internal links, metadata and redirect targets;
- designing and implementing responsive Elementor-compatible pages;
- consolidating duplicate URLs without discarding search equity;
- repairing desktop and mobile navigation;
- improving semantic HTML, SVG accessibility and structured data;
- developing Python/PHP deployment and verification utilities;
- diagnosing cache behaviour across Elementor, WordPress object cache, managed hosting and Cloudflare;
- maintaining rollback snapshots before changes.

## Selected challenges

### WordPress and Elementor store different representations

Updating `post_content` alone was insufficient because Elementor renders from `_elementor_data`. Deployment tooling therefore updated both representations, invalidated Elementor-generated caches and retained the previous state in a dedicated backup record.

### Duplicate URLs and redirect chains

Legacy service paths and duplicate page slugs risked fragmenting ranking signals. Redirect candidates were checked against direct `200` destinations, then consolidated into single-hop permanent redirects. Canonical metadata and menu destinations were aligned with the selected URLs.

### A correct origin could still look incorrect publicly

The stack used browser caching, Cloudflare, managed-host page caching, WordPress object caching and Elementor render caches. Verification compared:

- WordPress database state;
- cache-busted origin responses;
- normal public responses;
- Cloudflare cache headers and object age.

This isolated stale edge objects from actual content defects and prevented unnecessary repeated edits.

### Navigation existed in desktop and mobile variants

The menu repair treated desktop and mobile as separate structures. The final top-level order was normalised to:

1. Services
2. MDM
3. Cyber Security
4. Locations
5. Team
6. Contact Us

Existing service, location and team dropdowns were preserved, while Cyber Security was promoted to its own published page.

## SEO and accessibility decisions

- One descriptive H1 per primary landing page.
- Human-readable, stable URLs with canonical metadata.
- Direct `301` redirects for retired MDM paths.
- Descriptive titles and meta descriptions based on search intent and service geography.
- FAQ JSON-LD aligned with visible page content.
- Decorative inline SVGs removed from the accessibility tree with `aria-hidden="true"` and `focusable="false"`.
- Internal links verified against live response codes.
- Business Continuity retained as a distinct page while Our Services received its own destination.

## Evidence included

- 74-row content audit.
- 73-row metadata plan.
- 98-row deduplication plan.
- Internal-link verification output.
- Redirect correction and canonicalisation artifacts.
- Eight reusable Elementor page/component sources.
- Six reusable Python audit/deployment utilities.

## Outcome

The result is a clearer service architecture, reusable frontend source, safer production deployment process and a documented technical SEO workflow. The project demonstrates the ability to work beyond visual page building: diagnosing the full delivery stack, preserving rollback paths and verifying what real users and crawlers receive.

