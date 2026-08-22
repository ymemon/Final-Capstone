# Public Repository Safety

This repository is a sanitised engineering portfolio, not a production backup.

Excluded by design:

- SSH/SFTP usernames, passwords, host keys and server paths;
- API keys, tokens and Cloudflare credentials;
- WordPress database exports and `wp-config.php`;
- private backups and rollback payloads;
- customer reviews, personal data and non-public client records;
- Search Console account exports and authentication material.

Deployment tools in this repository contain only portable logic. Environment-specific credentials must be supplied through an approved secret manager or local environment and must never be committed.

