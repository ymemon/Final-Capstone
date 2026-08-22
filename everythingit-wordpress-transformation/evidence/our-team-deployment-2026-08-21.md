# Our Team Deployment Verification — 2026-08-21

- Git source: `src/elementor-pages/our-team.html`
- Deployment tool: `tools/deploy_our_team_page.py`
- Existing WordPress page replaced in place: page `1295`
- Public URL: <https://everythingit.ie/our-team/>
- HTTP response: `200`
- Canonical: `https://everythingit.ie/our-team/`
- Saved content size: `40,574` bytes
- Heading audit: one H1, six H2 elements
- Source image audit: nine images, all nine with descriptive alt attributes
- Referenced images and internal links: all returned `200` before deployment
- Public H1: “Meet the team behind technology that performs under pressure.”
- Rollback snapshot: `_eit_our_team_before_rebuild_20260821`
- Cache verification: ordinary and cache-busted requests both returned the new page

## Hero hierarchy refinement

- Restored the full Everything IT team photograph as the single hero image.
- Removed the repeated three-person portrait wall from the hero.
- Retained five individual leadership profiles beneath the introductory content.
- Final public verification: one `.eit-hero-photo`, zero `.eit-portrait-wall` elements and five `.eit-member` cards.
- Added a targeted managed-host purge for the canonical Team URL so the ordinary URL and origin remain aligned.

Server credentials and the temporary generated PHP payload are intentionally excluded from Git.
