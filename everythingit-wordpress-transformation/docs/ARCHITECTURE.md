# Architecture and Delivery Model

```text
Elementor HTML source
        │
        ├── preflight audits: headings, links, SVG accessibility, schema
        │
        ▼
WordPress page
  ├── post_content fallback
  ├── _elementor_data HTML widget
  ├── Yoast title/description/canonical
  └── rollback snapshot
        │
        ▼
Cache invalidation
  ├── Elementor element cache
  ├── WordPress object cache
  ├── managed-host page/CDN cache
  └── Cloudflare edge cache
        │
        ▼
Public verification
  ├── 200 response and rendered H1
  ├── canonical and metadata
  ├── desktop/mobile navigation
  ├── direct legacy 301 redirects
  └── origin vs edge comparison
```

## Why self-contained Elementor HTML?

The page sources combine scoped CSS, semantic HTML, lightweight JavaScript and inline decorative SVGs. A single Elementor HTML widget keeps the design portable and versionable while avoiding dependence on a large number of fragile visual-editor widgets.

## Deployment safeguards

- Target existing canonical page IDs rather than creating accidental duplicates.
- Save previous content, Elementor data, title, slug, status and SEO metadata.
- Validate page invariants before mutation.
- Update WordPress and Elementor representations together.
- Avoid storing credentials in source control; inject environment-specific access outside the repository.
- Treat redirect and menu updates as discrete, verifiable releases.

## Verification model

Database success is not treated as deployment success. A release is complete only after the public URL, rendered HTML, navigation and redirect behaviour have been checked through the delivery edge.

