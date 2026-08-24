#!/usr/bin/env bash
#
# AZWebCorp site-health remediation — run on the server, from the WordPress root.
#
#   ./azw-fix-all.sh              # DRY RUN: shows every change, touches nothing
#   ./azw-fix-all.sh --apply      # actually makes the changes
#
# Steps:
#   1. Back up the database and .htaccess
#   2. Install the 301 map for retired URLs
#   3. Remove the five dead links from the navigation menus
#   4. Make vps-hosting + ssl-certificates indexable (they are live but absent
#      from page-sitemap.xml, so Google is not being told they exist)
#   5. Publish /website-security-firewall/ (currently a 404 the hub links to)
#   6. Retire the stale sitemap pages, the automation pages and the Dublin page
#   7. Flush rewrite rules, caches and the sitemap
#   8. Verify and report
#
# Safe to re-run: every step checks current state before acting.

set -uo pipefail

APPLY=0
[ "${1:-}" = "--apply" ] && APPLY=1

C_OK=$'\033[32m'; C_WARN=$'\033[33m'; C_ERR=$'\033[31m'; C_DIM=$'\033[2m'; C_OFF=$'\033[0m'

step()  { printf '\n%s=== %s%s\n' "$C_DIM" "$1" "$C_OFF"; }
ok()    { printf '  %sOK%s    %s\n'   "$C_OK"   "$C_OFF" "$1"; }
warn()  { printf '  %sWARN%s  %s\n'   "$C_WARN" "$C_OFF" "$1"; }
err()   { printf '  %sERR%s   %s\n'   "$C_ERR"  "$C_OFF" "$1"; }
info()  { printf '        %s\n' "$1"; }
would() { printf '  %sDRY%s   would: %s\n' "$C_WARN" "$C_OFF" "$1"; }

# Run a wp command, or just describe it in dry-run mode. Swallows WP-CLI's own
# output here rather than at each call site, so the dry-run notice always shows.
do_wp() {
  if [ "$APPLY" -eq 1 ]; then
    wp "$@" >/dev/null 2>&1
  else
    would "wp $*"
  fi
}

command -v wp >/dev/null 2>&1 || { err "WP-CLI not found in PATH"; exit 1; }
wp core is-installed >/dev/null 2>&1 || { err "not a WordPress root — cd to it first"; exit 1; }

SITE=$(wp option get home 2>/dev/null)
printf '%sAZWebCorp remediation%s   site: %s   mode: %s\n' "$C_DIM" "$C_OFF" "$SITE" \
  "$([ "$APPLY" -eq 1 ] && echo APPLY || echo 'DRY RUN (pass --apply to commit)')"

# Pages that are live but must not be touched.
BRIDGE_SLUGS=(hosting-domains web-hosting wordpress-hosting vps-hosting website-builder
              domain-registration domain-transfer business-email-hosting ssl-certificates
              website-security-firewall website-backup)

# Dead URLs currently sitting in the navigation menus.
DEAD_NAV=(/mobile-development/ /frontend-development/ /news-and-insights/
          /arizona-backend-development/ /affordable-printing-services/)

# Pages to retire: stale sitemap entries, the automation pages, the Dublin leftover.
RETIRE=(frontend-development arizona-backend-development mobile-app-development-services-arizona
        workflow-automation workflow-automation-2 marketing-automation2
        managed-it-services-dublin)

# ---------------------------------------------------------------- 1. backups
step "1. Backups"
STAMP=$(date +%Y%m%d-%H%M%S)
if [ "$APPLY" -eq 1 ]; then
  if wp db export "azw-backup-$STAMP.sql" --quiet 2>/dev/null; then
    ok "database → azw-backup-$STAMP.sql"
  else
    err "database export FAILED — stopping, nothing else has run"
    exit 1
  fi
  if [ -f .htaccess ]; then
    cp .htaccess ".htaccess.bak-$STAMP" && ok ".htaccess → .htaccess.bak-$STAMP"
  else
    warn "no .htaccess in $(pwd)"
  fi
else
  would "wp db export azw-backup-$STAMP.sql"
  would "cp .htaccess .htaccess.bak-$STAMP"
fi

# ------------------------------------------------------------- 2. 301 map
step "2. Retired-URL 301 map"
MAP_MARKER="# --- AZWebCorp retired-URL 301 map"
if [ -f .htaccess ] && grep -qF "$MAP_MARKER" .htaccess; then
  ok "301 map already present — skipping"
else
  MAP=$(cat <<'HTACCESS'
# --- AZWebCorp retired-URL 301 map (managed by azw-fix-all.sh) ---
Redirect 301 /featured-projects/ https://azwebcorp.com/our-featured-projects/
Redirect 301 /optimize-your-digital-presence/ https://azwebcorp.com/arizona-digital-marketing/
Redirect 301 /mobile-development/ https://azwebcorp.com/arizona-web-development/
Redirect 301 /develop-your-mobile-app/ https://azwebcorp.com/arizona-web-development/
Redirect 301 /mobile-app-development-services-arizona/ https://azwebcorp.com/arizona-web-development/
Redirect 301 /frontend-development/ https://azwebcorp.com/arizona-web-development/
Redirect 301 /arizona-backend-development/ https://azwebcorp.com/arizona-web-development/
Redirect 301 /backend-dev-services/ https://azwebcorp.com/arizona-web-development/
Redirect 301 /2020/07/29/how-much-does-seo-cost-in-arizona/ https://azwebcorp.com/arizona-seo-services/
Redirect 301 /2020/07/29/7-best-internal-linking-plugins-for-wordpress-websites/ https://azwebcorp.com/wordpress-tutorials-resolve-at-arizona-azwebcorp/
Redirect 301 /2020/07/29/free-instagram-followers-hack-50k-free-that-works-in-2024/ https://azwebcorp.com/arizona-digital-marketing/
Redirect 301 /news-and-insights/ https://azwebcorp.com/case-studies/
Redirect 301 /affordable-printing-services/ https://azwebcorp.com/hire-our-services/
Redirect 301 /managed-it-services-dublin/ https://azwebcorp.com/hire-our-services/
# --- end 301 map ---
HTACCESS
)
  if [ "$APPLY" -eq 1 ]; then
    # Must sit ABOVE the WordPress block or mod_rewrite swallows these first.
    printf '%s\n\n%s' "$MAP" "$(cat .htaccess 2>/dev/null)" > .htaccess.new \
      && mv .htaccess.new .htaccess \
      && ok "14 redirects prepended to .htaccess"
  else
    would "prepend 14 Redirect 301 directives to .htaccess"
  fi
fi

# ------------------------------------------------------- 3. dead nav links
step "3. Dead navigation links"
FOUND_NAV=0
for menu_id in $(wp menu list --field=term_id 2>/dev/null); do
  menu_name=$(wp menu list --format=json 2>/dev/null \
              | python3 -c "import sys,json;print(next((m['name'] for m in json.load(sys.stdin) if str(m['term_id'])=='$menu_id'),'?'))" 2>/dev/null || echo "?")
  while IFS=$'\t' read -r item_id item_url item_title; do
    [ -z "${item_id:-}" ] && continue
    for dead in "${DEAD_NAV[@]}"; do
      case "$item_url" in
        *"$dead")
          FOUND_NAV=$((FOUND_NAV + 1))
          warn "menu '$menu_name': '$item_title' → $dead (404)"
          do_wp menu item delete "$item_id" && \
            [ "$APPLY" -eq 1 ] && info "deleted menu item $item_id"
          ;;
      esac
    done
  done < <(wp menu item list "$menu_id" --fields=db_id,url,title --format=tsv 2>/dev/null | tail -n +2)
done
[ "$FOUND_NAV" -eq 0 ] && ok "no dead links found in any menu"

# ------------------------------------------- 4. sitemap visibility of bridges
step "4. Bridge-page indexability"
for slug in "${BRIDGE_SLUGS[@]}"; do
  pid=$(wp post list --post_type=page --name="$slug" --field=ID --format=ids 2>/dev/null | head -1)
  if [ -z "$pid" ]; then
    warn "/$slug/ — no page found"
    continue
  fi
  robots=$(wp post meta get "$pid" rank_math_robots --format=json 2>/dev/null || echo "")
  if printf '%s' "$robots" | grep -q noindex; then
    err "/$slug/ (ID $pid) is set to NOINDEX — this is why it is missing from the sitemap"
    do_wp post meta delete "$pid" rank_math_robots && \
      [ "$APPLY" -eq 1 ] && info "cleared noindex on $pid"
  else
    ok "/$slug/ (ID $pid) indexable"
  fi
done

# ------------------------------------ 5. publish website-security-firewall
step "5. /website-security-firewall/"
WSF_ID=$(wp post list --post_type=page --name=website-security-firewall --field=ID --format=ids 2>/dev/null | head -1)
if [ -n "$WSF_ID" ]; then
  status=$(wp post get "$WSF_ID" --field=post_status 2>/dev/null)
  if [ "$status" = "publish" ]; then
    ok "already published (ID $WSF_ID)"
  else
    warn "exists as '$status' (ID $WSF_ID) — publishing"
    do_wp post update "$WSF_ID" --post_status=publish
  fi
else
  warn "missing — the hub page currently links to a 404"
  if [ "$APPLY" -eq 1 ]; then
    WSF_ID=$(wp post create --post_type=page --post_status=publish --porcelain \
      --post_title="Website Security & Firewall for Arizona Businesses" \
      --post_name="website-security-firewall" \
      --post_content='<p>A hacked site is not just a technical problem. Google blocklists infected sites, which removes you from search results entirely and shows a red warning screen to anyone who tries to visit. Recovery takes far longer than prevention.</p>
<h2>What a web application firewall does</h2>
<ul>
<li>Blocks attacks before they reach your site</li>
<li>Scans continuously for malware</li>
<li>Removes infections and cleans compromised files</li>
<li>Prevents Google blocklisting and the warnings that follow</li>
<li>Mitigates DDoS traffic floods</li>
</ul>
<h2>Why WordPress sites are targeted</h2>
<p>WordPress powers a large share of the web, which makes its plugins a standing target for automated attacks. Most breaches are not personal: bots scan continuously for known plugin vulnerabilities. A firewall blocks those attempts before an outdated plugin becomes an entry point. If you are on <a href="/wordpress-hosting/">managed WordPress hosting</a>, core and plugin updates are handled for you, which closes the most common route in.</p>
<h2>Layering security properly</h2>
<p>A firewall stops attacks, <a href="/ssl-certificates/">SSL</a> encrypts traffic, and <a href="/website-backup/">backups</a> get you back online if something still goes wrong. They solve different problems and most sites need all three.</p>
<h2>FAQ</h2>
<h3>My site is small. Is it really a target?</h3>
<p>Attacks are automated and indiscriminate. Bots scan for vulnerable software, not for businesses worth attacking, so site size is irrelevant.</p>
<h3>What if my site is already infected?</h3>
<p>Malware removal is included with the firewall service. Call (480) 818-5761 and we will start with a scan to establish scope.</p>
<h3>Does a firewall slow my site down?</h3>
<p>No. Traffic is filtered at the CDN edge, and the caching that comes with it usually makes sites faster, not slower.</p>
<p><a href="https://www.shopazwebcorp.com/products/website-security?utm_source=azwebcorp&amp;utm_medium=bridge&amp;utm_campaign=website-security" rel="noopener">See website security plans &rarr;</a></p>
<p><a href="/hosting-domains/">&larr; Back to all hosting &amp; domain plans</a></p>' 2>/dev/null)
    if [ -n "$WSF_ID" ]; then
      wp post meta set "$WSF_ID" rank_math_title 'Website Security & Firewall (WAF) | AZWebCorp' >/dev/null 2>&1
      wp post meta set "$WSF_ID" rank_math_description "Website firewall and malware protection from AZWebCorp in Gilbert, AZ. Block attacks, clean infections and keep your site off Google's blocklist." >/dev/null 2>&1
      wp post meta set "$WSF_ID" rank_math_canonical_url 'https://azwebcorp.com/website-security-firewall/' >/dev/null 2>&1
      ok "created and published (ID $WSF_ID) with Rank Math meta"
    else
      err "page creation failed"
    fi
  else
    would "create + publish /website-security-firewall/ with Rank Math meta"
  fi
fi

# ---------------------------------------------------- 6. retire dead pages
step "6. Retire stale / unwanted pages"
for slug in "${RETIRE[@]}"; do
  pid=$(wp post list --post_type=page --name="$slug" --post_status=any --field=ID --format=ids 2>/dev/null | head -1)
  if [ -z "$pid" ]; then
    ok "/$slug/ — already gone (sitemap entry is stale cache; step 7 clears it)"
  else
    warn "/$slug/ (ID $pid) → trash"
    # Trash, not --force: reversible from wp-admin if a target was wrong.
    do_wp post delete "$pid" && [ "$APPLY" -eq 1 ] && info "trashed $pid"
  fi
done

# ----------------------------------------------------------- 7. flush caches
step "7. Flush rewrite rules, caches and sitemap"
do_wp rewrite flush
do_wp cache flush
do_wp transient delete --all
if [ "$APPLY" -eq 1 ]; then
  # Rank Math caches the sitemap; deleting it forces a rebuild on next request.
  wp option delete rank_math_sitemap_cache >/dev/null 2>&1
  wp rank-math sitemap generate >/dev/null 2>&1
  ok "rewrite rules, object cache, transients and sitemap cache cleared"
fi

# --------------------------------------------------------------- 8. verify
step "8. Verification"
if [ "$APPLY" -eq 1 ]; then
  printf '  %-42s %s\n' "URL" "STATUS"
  for slug in "${BRIDGE_SLUGS[@]}"; do
    code=$(curl -s -o /dev/null -w '%{http_code}' --max-time 15 "$SITE/$slug/" 2>/dev/null)
    if [ "$code" = "200" ]; then
      printf '  %-42s %s%s%s\n' "/$slug/" "$C_OK" "$code" "$C_OFF"
    else
      printf '  %-42s %s%s%s\n' "/$slug/" "$C_ERR" "$code" "$C_OFF"
    fi
    sleep 1
  done
  echo
  info "Sitemap should now list all 11. Check:"
  info "  curl -s $SITE/page-sitemap.xml | grep -c '<loc>'"
else
  info "dry run — nothing changed. Re-run with --apply to commit."
fi

step "Remaining, not automated"
info "ShortPixel: 97 images 302 via sp-ao.shortpixel.ai back to origin, so the"
info "CDN is failing over and serving no benefit. Check the account quota and"
info "CDN config; if it cannot be fixed, disabling Adaptive Images is faster."
info ""
info "Printing services: no printing page exists any more. /affordable-printing-"
info "services/ currently points at /hire-our-services/ — build a real page if"
info "printing is still offered."
echo
