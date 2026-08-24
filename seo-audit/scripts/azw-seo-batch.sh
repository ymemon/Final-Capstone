#!/bin/bash
# AZWebCorp SEO batch - creates remaining reseller bridge pages, sets Rank Math
# meta, repoints Shop menu items at owned pages, and refreshes the hub page.
# Idempotent: existing slugs are skipped, so re-running is safe.
set -u
W=/home/client_9d34da8b_644762/html
MENU=primary-menu
T=$(mktemp -d)
CREATED=0; SKIPPED=0

say(){ printf '%s\n' "$*"; }

# create <slug> <title> <seo_title> <meta_desc> <file>
create(){
  local slug="$1" title="$2" stitle="$3" sdesc="$4" file="$5" id
  id=$(wp --path=$W post list --post_type=page --name="$slug" --format=ids)
  if [ -n "$id" ]; then
    say "SKIP   $slug (exists: $id)"; SKIPPED=$((SKIPPED+1))
  else
    id=$(wp --path=$W post create "$file" --post_type=page --post_status=publish \
         --post_title="$title" --post_name="$slug" --porcelain)
    say "CREATE $slug -> $id"; CREATED=$((CREATED+1))
  fi
  wp --path=$W post meta update "$id" rank_math_title "$stitle" >/dev/null
  wp --path=$W post meta update "$id" rank_math_description "$sdesc" >/dev/null
  wp --path=$W post meta update "$id" rank_math_canonical_url "https://azwebcorp.com/$slug/" >/dev/null
  eval "ID_${slug//-/_}=$id"
}

say "=== Checking reseller product URLs ==="
for P in vps ssl website-security managed-ssl website-backup professional-email cpanel; do
  say "  /products/$P -> $(curl -s -o /dev/null -w '%{http_code}' https://www.shopazwebcorp.com/products/$P)"
done

say ""
say "=== Creating pages ==="

cat > $T/vps.html <<'X'
<h1>VPS Hosting for Arizona Businesses</h1>
<p>A VPS gives you guaranteed CPU and RAM rather than sharing a server with hundreds of other sites. That matters once traffic is steady, you are running an application rather than a brochure site, or a slow neighbour on shared hosting starts costing you conversions.</p>
<h2>What you get</h2>
<ul>
<li>Dedicated CPU and RAM, not shared with other accounts</li>
<li>Root access and full server control</li>
<li>Choice of managed or self-managed</li>
<li>Scalable resources as traffic grows</li>
<li>Free SSL certificate</li>
</ul>
<h2>VPS vs. Web Hosting Plus</h2>
<p>Most Arizona small businesses do not need a VPS. <a href="/web-hosting/">Web Hosting Plus</a> handles a standard business site comfortably and costs less. Move to a VPS when you need specific software installed at server level, you are running an application with real resource demands, or compliance requires isolation from other tenants.</p>
<h2>FAQ</h2>
<h3>Do I need technical skills to run a VPS?</h3>
<p>For a self-managed VPS, yes. If you would rather not manage a server, choose the managed option or stay on <a href="/web-hosting/">Web Hosting Plus</a>. We can advise which fits before you commit.</p>
<h3>Can I migrate from shared hosting without downtime?</h3>
<p>Yes. The new server is provisioned and tested before DNS switches over, so visitors keep hitting the working site throughout.</p>
<h3>Is support handled locally?</h3>
<p>Support requests reach our Gilbert, AZ team at (480) 818-5761, not a generic hosting queue.</p>
<p><a href="https://www.shopazwebcorp.com/products/vps?utm_source=azwebcorp&amp;utm_medium=bridge&amp;utm_campaign=vps-hosting" rel="noopener">See VPS plans and pricing &rarr;</a></p>
<p><a href="/hosting-domains/">&larr; Back to all hosting &amp; domain plans</a></p>
X
create vps-hosting "VPS Hosting for Arizona Businesses" \
  "VPS Hosting in Arizona | AZWebCorp" \
  "Managed VPS hosting from AZWebCorp in Gilbert, AZ. Dedicated resources, root access and full control, with local support instead of a shared call queue." \
  "$T/vps.html"

cat > $T/ssl.html <<'X'
<h1>SSL Certificates for Arizona Websites</h1>
<p>Without a valid SSL certificate, browsers show visitors a "Not Secure" warning before they read a word of your site. Google has treated HTTPS as a ranking signal since 2014, so an expired or missing certificate costs you both trust and visibility.</p>
<h2>What an SSL certificate does</h2>
<ul>
<li>Encrypts data between your site and its visitors</li>
<li>Removes the browser "Not Secure" warning</li>
<li>Required for any site taking payments or logins</li>
<li>Supports HTTPS as a confirmed Google ranking factor</li>
<li>Displays the padlock that customers look for</li>
</ul>
<h2>Which certificate do you need?</h2>
<p>A standard domain-validated certificate covers most business sites and issues within minutes. If you take payments directly, an organisation-validated certificate adds verified business identity. Running several subdomains, such as shop and blog, makes a wildcard certificate cheaper than buying each separately. See also <a href="/website-security-firewall/">website security and firewall</a>.</p>
<h2>FAQ</h2>
<h3>Do I need SSL if I do not sell anything online?</h3>
<p>Yes. The browser warning appears on every site without it, including brochure sites, and visitors leave rather than click through a security warning.</p>
<h3>What happens when a certificate expires?</h3>
<p>Browsers block the site with a full-page warning. Auto-renewal prevents that, and we monitor expiry dates for sites we manage.</p>
<h3>Will you install it for me?</h3>
<p>Yes, installation and configuration are included. Call (480) 818-5761 if you would rather we handle the whole thing.</p>
<p><a href="https://www.shopazwebcorp.com/products/ssl?utm_source=azwebcorp&amp;utm_medium=bridge&amp;utm_campaign=ssl-certificates" rel="noopener">See SSL certificate options &rarr;</a></p>
<p><a href="/hosting-domains/">&larr; Back to all hosting &amp; domain plans</a></p>
X
create ssl-certificates "SSL Certificates for Arizona Websites" \
  "SSL Certificates for Arizona Websites | AZWebCorp" \
  "SSL certificates installed and managed by AZWebCorp in Gilbert, AZ. Encrypt customer data, remove browser warnings and protect your search rankings." \
  "$T/ssl.html"

cat > $T/sec.html <<'X'
<h1>Website Security &amp; Firewall for Arizona Businesses</h1>
<p>A hacked site is not just a technical problem. Google blocklists infected sites, which removes you from search results entirely and shows a red warning screen to anyone who tries to visit. Recovery takes far longer than prevention.</p>
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
<p><a href="/hosting-domains/">&larr; Back to all hosting &amp; domain plans</a></p>
X
create website-security-firewall "Website Security & Firewall (WAF)" \
  "Website Security & Firewall (WAF) | AZWebCorp" \
  "Website firewall and malware protection from AZWebCorp in Gilbert, AZ. Block attacks, clean infections and keep your site off Google's blocklist." \
  "$T/sec.html"

cat > $T/bak.html <<'X'
<h1>Website Backup Services for Arizona Businesses</h1>
<p>Most sites are lost to something mundane: a bad plugin update, a botched edit, a failed migration. Hosting backups are a safety net for the host, not a restore plan for you. A dedicated backup service means you can roll back to yesterday in minutes.</p>
<h2>What is included</h2>
<ul>
<li>Automatic daily backups</li>
<li>One-click restore to any saved point</li>
<li>Off-server storage, so a server failure does not take the backups with it</li>
<li>Files and database captured together</li>
<li>Restore history you can browse by date</li>
</ul>
<h2>Why your host's backup is not enough</h2>
<p>Host-level backups usually exist for disaster recovery across the whole platform, not for restoring one customer's site to a specific hour. They can be slow to request, limited in retention, and stored on the same infrastructure as the site. If a plugin update breaks your checkout on a Friday afternoon, you want a restore point you control.</p>
<h2>FAQ</h2>
<h3>How often should a business site be backed up?</h3>
<p>Daily for most sites. If you take orders or publish frequently, real-time or hourly is worth the difference, because a day of lost orders costs more than the plan does.</p>
<h3>How fast is a restore?</h3>
<p>Usually minutes. The slow part is deciding which restore point you want, which is why browsable history matters.</p>
<h3>Does this work alongside a firewall?</h3>
<p>Yes, and they pair well. <a href="/website-security-firewall/">Website security</a> prevents most incidents; backup covers everything else.</p>
<p><a href="https://www.shopazwebcorp.com/products/website-backup?utm_source=azwebcorp&amp;utm_medium=bridge&amp;utm_campaign=website-backup" rel="noopener">See website backup plans &rarr;</a></p>
<p><a href="/hosting-domains/">&larr; Back to all hosting &amp; domain plans</a></p>
X
create website-backup "Website Backup Services for Arizona Businesses" \
  "Website Backup Services in Arizona | AZWebCorp" \
  "Automatic daily website backups with one-click restore, set up by AZWebCorp in Gilbert, AZ. Recover from bad updates, failed edits and server problems fast." \
  "$T/bak.html"

say ""
say "=== Repointing Shop menu items to owned pages ==="
wp --path=$W menu item update 455 --link="https://azwebcorp.com/vps-hosting/" --title="VPS Hosting" 2>/dev/null && say "  455 -> /vps-hosting/"
wp --path=$W menu item update 458 --link="https://azwebcorp.com/ssl-certificates/" --title="SSL Certificates" 2>/dev/null && say "  458 -> /ssl-certificates/"
wp --path=$W menu item update 459 --link="https://azwebcorp.com/website-security-firewall/" --title="Website Security" 2>/dev/null && say "  459 -> /website-security-firewall/"

HUB=$(wp --path=$W post list --post_type=page --name=hosting-domains --format=ids)
if [ -n "$HUB" ]; then
  wp --path=$W post get "$HUB" --field=content > $T/hub.html
  if ! grep -q '/vps-hosting/' $T/hub.html; then
    sed -i 's|</ul>|<li><a href="/vps-hosting/">VPS Hosting</a> - dedicated CPU and RAM for demanding sites.</li>\n<li><a href="/ssl-certificates/">SSL Certificates</a> - encryption and the browser padlock.</li>\n<li><a href="/website-security-firewall/">Website Security</a> - firewall and malware protection.</li>\n<li><a href="/website-backup/">Website Backup</a> - daily backups with one-click restore.</li>\n</ul>|' $T/hub.html
    wp --path=$W post update "$HUB" $T/hub.html >/dev/null && say "  hub page $HUB updated with 4 new links"
  else
    say "  hub page already links new pages"
  fi
fi

wp --path=$W cache flush >/dev/null
wp --path=$W rewrite flush >/dev/null
rm -rf $T

say ""
say "=== Verify ==="
for S in hosting-domains web-hosting wordpress-hosting domain-registration domain-transfer website-builder business-email-hosting vps-hosting ssl-certificates website-security-firewall website-backup; do
  say "  $(curl -s -o /dev/null -w '%{http_code}' https://azwebcorp.com/$S/)  $S"
done
say ""
say "Created:$CREATED  Skipped:$SKIPPED"
