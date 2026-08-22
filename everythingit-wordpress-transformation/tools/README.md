# Tools

- `content_audit.py` — inventories page content and SEO fields.
- `heading_audit.py` — checks heading structure across URLs.
- `internal_link_audit.py` — validates internal destinations and response behaviour.
- `redirect_gap_check.py` — identifies missing or inconsistent canonical redirects.
- `url_inventory.py` — builds a site URL worklist.
- `deploy_mdm_page.py` — prepares a validated WordPress/Elementor deployment payload with rollback metadata.
- `deploy_our_team_page.py` — validates the version-controlled Team page and builds a rollback-safe WordPress/Elementor payload.

Production access configuration is intentionally absent. Review any target IDs and URLs before adapting deployment code to another environment.
