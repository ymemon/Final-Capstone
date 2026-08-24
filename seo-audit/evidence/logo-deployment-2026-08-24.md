# AZWebCorp logo deployment — 2026-08-24

## Scope

Updated both production logo variants referenced by the public header and
footer. The brand accent was changed from lime-yellow to orange-coral, and the
cactus-shaped `W` was redrawn with a slimmer, clearer silhouette.

## Release safeguards

1. Downloaded and retained local copies of the original public assets.
2. Created matching dark and reversed variants with transparent backgrounds.
3. Preserved the existing 452 × 146 production dimensions.
4. Backed up both live files on the managed host before replacement.
5. Replaced the existing URLs so WordPress/Elementor references did not need to
   change.
6. Flushed the WordPress object cache and requested a CDN purge.
7. Downloaded both public URLs and verified their SHA-256 hashes exactly matched
   the deployed local artifacts.

No credentials or server backup paths are included in this public evidence.
