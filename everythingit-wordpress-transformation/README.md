# EverythingIT.ie WordPress Transformation

Production redesign, technical SEO and automation work for [EverythingIT.ie](https://everythingit.ie/), an Irish managed IT services provider.

## Project summary

I modernised a live WordPress/Elementor website while preserving service availability and existing search equity. The engagement combined frontend page engineering, WordPress data management, navigation architecture, SEO consolidation, redirects, cache debugging and repeatable Python-based audits.

The work was performed against a production WordPress installation with explicit backups, scoped changes and post-deployment verification. This public portfolio contains safe source artifacts and anonymised evidence only; credentials, customer records, database exports and server backups are intentionally excluded.

## Highlights

- Built responsive, self-contained Elementor HTML experiences for MDM, managed services, IT consultancy, cybersecurity, services and location content.
- Audited 74 content records and prepared metadata recommendations for 73 URLs.
- Analysed 98 deduplication candidates and consolidated legacy/duplicate URL paths with direct `301` redirects.
- Repaired desktop and mobile information architecture, including a dedicated Cyber Security destination and consistent navigation ordering.
- Implemented semantic heading checks, canonical URLs, metadata, FAQ structured data, accessible decorative SVG handling and internal-link verification.
- Diagnosed layered WordPress, Elementor, managed-host and Cloudflare caching using origin-versus-edge comparisons.
- Preserved rollback data before production mutations and verified status codes, redirects, rendered headings and navigation after releases.

## Featured live pages

- [Our Services](https://everythingit.ie/our-services/)
- [MDM Services Dublin](https://everythingit.ie/mdm-services-dublin/)
- [Cyber Security](https://everythingit.ie/cybersecurity-dublin/)
- [Managed IT Services](https://everythingit.ie/managed-it-services/)
- [Our Team](https://everythingit.ie/our-team/)
- [IT Support Cork](https://everythingit.ie/cork/)

Live pages may continue to evolve after the snapshot represented in this repository.

## Repository map

```text
everythingit-wordpress-transformation/
├── docs/       Case study, architecture, release history and safety model
├── evidence/   Sanitised audit outputs
├── seo/        Metadata, deduplication and redirect planning artifacts
├── src/        Elementor-ready page and component source
└── tools/      Reusable audit and deployment utilities
```

## Technical stack

WordPress, Elementor, WP-CLI, PHP, Python, HTML5, CSS, JavaScript, JSON-LD, Apache redirects, Yoast SEO, SSH/SFTP, Cloudflare and managed WordPress caching.

## Engineering approach

1. Inventory the live site and identify content, metadata, link and redirect risks.
2. Create page/component source independently from WordPress storage.
3. Validate headings, links, structured data and accessibility before deployment.
4. Back up affected WordPress records and deploy through narrow, repeatable scripts.
5. Purge each cache layer and compare origin, cache-busted and ordinary public responses.
6. Verify canonical URLs, redirects, navigation, response codes and rendered content.

See the full [case study](docs/CASE_STUDY.md) and [architecture notes](docs/ARCHITECTURE.md).
