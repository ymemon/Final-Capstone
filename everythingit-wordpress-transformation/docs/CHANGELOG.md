# Project Changelog

This is a portfolio-level reconstruction of the major workstreams. It intentionally omits credentials and private operational details.

## 2026-08-24 — Homepage ISO and Google reviews

- Replaced the legacy white ISO 27001 homepage section with a responsive dark
  brand-aligned certification section.
- Removed three hard-coded review excerpts that implied a complete review feed.
- Added an authoritative link to the verified Google Business Profile so all 40
  public reviews are accessible directly from Google.
- Backed up the original homepage Elementor data before mutation and verified
  the new section at the database, Elementor-render and public HTTP layers.
- Added reusable, credential-free inspection and rollback-safe deployment tools.

## Discovery and audit

- Built URL, content, heading, metadata, duplicate-content and internal-link inventories.
- Identified legacy URL structures, draft duplicates and redirect gaps.
- Produced evidence files and prioritised remediation plans.

## Information architecture and SEO

- Consolidated duplicate service URLs around stable canonical destinations.
- Corrected redirect targets and removed unnecessary redirect hops.
- Improved page titles, descriptions, headings, internal links and canonical metadata.
- Separated Our Services from Business Continuity in both URL structure and navigation.

## Frontend and Elementor

- Created responsive managed-services, MDM, consultancy, cybersecurity, services and Cork/location experiences.
- Added a premium Our Team experience with leadership profiles, responsive imagery and descriptive image alternatives.
- Refined the Team page hierarchy around one full-team photographic hero followed by individual member profiles, removing repeated portraits from the hero.
- Added reusable footer/header and homepage component source.
- Applied semantic HTML, scoped CSS, responsive behaviour and accessible SVG treatment.

## Navigation

- Corrected desktop and mobile menu assignments.
- Removed the Case Studies tab while the page remained deactivated from public navigation.
- Promoted Cyber Security to a dedicated top-level destination.
- Normalised the top-level order to Services, MDM, Cyber Security, Locations, Team and Contact Us.

## Production operations

- Replaced the legacy MDM page at its canonical URL.
- Added direct redirects from legacy and duplicate MDM paths.
- Removed the unwanted Cork hero implementation.
- Diagnosed stale Elementor and Cloudflare output using origin-versus-edge testing.
- Added rollback snapshots and removed temporary deployment payloads after verification.
