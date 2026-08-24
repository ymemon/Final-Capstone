#!/bin/bash
# ============================================================
#  Creates the 7 AZWebCorp SEO bridge pages as WordPress DRAFTS
#  using WP-CLI, run directly on the server over SSH.
#
#  This avoids the REST API entirely, so the stripped
#  Authorization header that blocked publishing is irrelevant.
#
#  Nothing goes live: every page is created as a draft.
# ============================================================
set -u

# Locate the WordPress install
for DIR in ~/html ~/public_html ~/www .; do
  if [ -f "$DIR/wp-config.php" ]; then WP_PATH="$DIR"; break; fi
done

if [ -z "${WP_PATH:-}" ]; then
  echo "ERROR: couldn't find wp-config.php in ~/html, ~/public_html, ~/www or ."
  echo "Run 'find ~ -name wp-config.php -maxdepth 3' and cd to that folder first."
  exit 1
fi

echo "WordPress found at: $WP_PATH"

if ! command -v wp >/dev/null 2>&1; then
  echo "ERROR: wp-cli not found on this server."
  exit 1
fi

wp --path="$WP_PATH" core version >/dev/null 2>&1 || { echo "ERROR: wp-cli can't read this install."; exit 1; }
echo "WP-CLI OK. WordPress $(wp --path="$WP_PATH" core version)"
echo

WORKDIR=$(mktemp -d)
CREATED=0
SKIPPED=0

# ---------- hosting-domains ----------
EXISTING=$(wp --path="$WP_PATH" post list --post_type=page --name=hosting-domains --format=ids)
if [ -n "$EXISTING" ]; then
  echo "SKIP   hosting-domains (already exists: ID $EXISTING)"
  SKIPPED=$((SKIPPED+1))
else
cat > "$WORKDIR/hosting-domains.html" <<'AZWEBEOF'
<h1>Arizona Web Hosting, Domains &amp; WordPress Plans</h1>

  <p>Every plan below is provisioned through AZWebCorp's hosting infrastructure and supported by our Gilbert, AZ team — not a call center. Pick a category to see full specs, pricing, and what we recommend it for.</p>

  <nav aria-label="Product categories">
    <ul>
      <li><a href="/web-hosting/">Web Hosting Plus</a> — cPanel hosting for standard sites, from $20.99/mo.</li>
      <li><a href="/wordpress-hosting/">WordPress Hosting</a> — managed WordPress with staging and auto-updates.</li>
      <li><a href="/website-builder/">Website Builder</a> — no-code site builder, live in under an hour.</li>
      <li><a href="/domain-registration/">Domain Registration</a> — from $11.99/yr, free WHOIS privacy.</li>
      <li><a href="/domain-transfer/">Domain Transfer</a> — move an existing domain to AZWebCorp.</li>
      <li><a href="/business-email-hosting/">Business Email</a> — professional @yourdomain.com inboxes.</li>
    </ul>
  </nav>

  <h2>Not sure which plan you need?</h2>
  <p>Most small Arizona businesses launching a first site want <a href="/wordpress-hosting/">WordPress Hosting</a> plus a <a href="/domain-registration/">registered domain</a>. Agencies and developers managing multiple client sites tend to outgrow shared plans and move to <a href="/web-hosting/">Web Hosting Plus</a> for the extra RAM and CPU headroom. If you already have a working site elsewhere, start with <a href="/domain-transfer/">Domain Transfer</a> so DNS and email keep working during the move.</p>

  <h2>Support that isn't a shared call queue</h2>
  <p>Plans are provisioned through our reseller infrastructure, but support requests come to AZWebCorp directly at (623) 670-1611 or info@azwebcorp.com — you're not routed into a generic hosting company's ticket queue.</p>
AZWEBEOF
  NEWID=$(wp --path="$WP_PATH" post create "$WORKDIR/hosting-domains.html" \
    --post_type=page \
    --post_status=draft \
    --post_title='Arizona Web Hosting, Domains & WordPress Plans' \
    --post_name=hosting-domains \
    --porcelain)
  echo "CREATE hosting-domains -> ID $NEWID"
  CREATED=$((CREATED+1))
fi

# ---------- web-hosting ----------
EXISTING=$(wp --path="$WP_PATH" post list --post_type=page --name=web-hosting --format=ids)
if [ -n "$EXISTING" ]; then
  echo "SKIP   web-hosting (already exists: ID $EXISTING)"
  SKIPPED=$((SKIPPED+1))
else
cat > "$WORKDIR/web-hosting.html" <<'AZWEBEOF'
<h1>Arizona Web Hosting Plans — cPanel Hosting from Gilbert, AZ</h1>

  <p>Web Hosting Plus is built for sites that have outgrown a builder-only plan but don't need a full VPS: standard cPanel access, one-click installs, and resource limits that scale with traffic instead of throttling it. Unlike national hosts, support calls go to our Gilbert, AZ office, not a shared queue. If you're running WordPress specifically, see <a href="/wordpress-hosting/">WordPress Hosting</a> instead — it includes staging and managed updates this plan doesn't.</p>

  <h2>Plans &amp; Pricing</h2>
  <table>
    <thead><tr><th>Plan</th><th>Price</th><th>Storage</th><th>RAM</th></tr></thead>
    <tbody>
      <tr><td>Launch</td><td>$20.99/mo</td><td>100 GB</td><td>4 GB</td></tr>
      <tr><td>Enhance</td><td>$36.99/mo</td><td>200 GB</td><td>8 GB</td></tr>
      <tr><td>Grow</td><td>$51.99/mo</td><td>300 GB</td><td>16 GB</td></tr>
      <tr><td>Expand</td><td>$72.99/mo</td><td><!-- CONFIRM: storage figure not in source brief --></td><td>32 GB, 16 CPUs</td></tr>
    </tbody>
  </table>
  <p><small>Pricing excludes applicable taxes and ICANN fees where relevant.</small></p>

  <h2>Which tier should you pick?</h2>
  <p><strong>Launch</strong> comfortably runs a single small-business brochure site or blog. <strong>Enhance</strong> is the right starting point if you're running WooCommerce or expect meaningful traffic spikes. <strong>Grow</strong> and <strong>Expand</strong> are built for agencies hosting multiple client sites on one account, where the extra CPU headroom matters more than storage.</p>

  <h2>FAQ</h2>
  <h3>Can I upgrade plans later without downtime?</h3>
  <p>Yes — plan upgrades are applied to your existing account; there's no need to re-provision or point DNS elsewhere.</p>
  <h3>Does this include a free SSL certificate?</h3>
  <p>Yes, all Web Hosting Plus tiers include a free SSL certificate provisioned automatically.</p>

  <p><a href="https://www.shopazwebcorp.com/products/business?utm_source=azwebcorp&amp;utm_medium=bridge&amp;utm_campaign=web-hosting" rel="noopener">See live pricing and sign up →</a></p>
  <p><a href="/hosting-domains/">← Back to all hosting &amp; domain plans</a></p>
AZWEBEOF
  NEWID=$(wp --path="$WP_PATH" post create "$WORKDIR/web-hosting.html" \
    --post_type=page \
    --post_status=draft \
    --post_title='Arizona Web Hosting Plans - cPanel Hosting from Gilbert, AZ' \
    --post_name=web-hosting \
    --porcelain)
  echo "CREATE web-hosting -> ID $NEWID"
  CREATED=$((CREATED+1))
fi

# ---------- wordpress-hosting ----------
EXISTING=$(wp --path="$WP_PATH" post list --post_type=page --name=wordpress-hosting --format=ids)
if [ -n "$EXISTING" ]; then
  echo "SKIP   wordpress-hosting (already exists: ID $EXISTING)"
  SKIPPED=$((SKIPPED+1))
else
cat > "$WORKDIR/wordpress-hosting.html" <<'AZWEBEOF'
<h1>Managed WordPress Hosting for Arizona Businesses</h1>

  <p>This is the plan we put our own clients on. AZWebCorp builds WordPress sites for Gilbert, Phoenix, and Mesa-area businesses every week (see our <a href="/arizona-web-development/">web development services</a>) — this hosting is tuned for the same stack: staging environments, managed core/plugin updates, and server-level caching, instead of a generic shared-hosting box running whatever CMS you install.</p>

  <h2>What's included</h2>
  <ul>
    <li>One-click WordPress install with staging environment</li>
    <li>Managed core and plugin updates</li>
    <li>Free SSL certificate</li>
    <li>Daily backups</li>
    <li>Server-level caching tuned for WordPress</li>
  </ul>

  <h2>WordPress Hosting vs. Web Hosting Plus</h2>
  <p>If you're running WordPress specifically, this plan's staging environment and managed updates save real time over <a href="/web-hosting/">Web Hosting Plus</a>'s general-purpose cPanel setup. If you're running a non-WordPress site or need raw resource headroom for multiple client sites, Web Hosting Plus is the better fit.</p>

  <h2>FAQ</h2>
  <h3>Will you migrate my existing WordPress site?</h3>
  <p>Yes — migration from your current host is included when you sign up.</p>
  <h3>Do plugin updates ever break my site?</h3>
  <p>Updates apply to a staging copy first; production only updates after that copy is confirmed working.</p>

  <p><a href="https://www.shopazwebcorp.com/products/wordpress?utm_source=azwebcorp&amp;utm_medium=bridge&amp;utm_campaign=wordpress-hosting" rel="noopener">See live pricing and sign up →</a></p>
  <p><a href="/hosting-domains/">← Back to all hosting &amp; domain plans</a></p>
AZWEBEOF
  NEWID=$(wp --path="$WP_PATH" post create "$WORKDIR/wordpress-hosting.html" \
    --post_type=page \
    --post_status=draft \
    --post_title='Managed WordPress Hosting for Arizona Businesses' \
    --post_name=wordpress-hosting \
    --porcelain)
  echo "CREATE wordpress-hosting -> ID $NEWID"
  CREATED=$((CREATED+1))
fi

# ---------- domain-registration ----------
EXISTING=$(wp --path="$WP_PATH" post list --post_type=page --name=domain-registration --format=ids)
if [ -n "$EXISTING" ]; then
  echo "SKIP   domain-registration (already exists: ID $EXISTING)"
  SKIPPED=$((SKIPPED+1))
else
cat > "$WORKDIR/domain-registration.html" <<'AZWEBEOF'
<h1>Domain Registration for Arizona Businesses</h1>

  <p>Registering a domain is the easy part — what matters is what happens after: who controls your DNS, whether your WHOIS contact info is public, and how fast you can point the domain at a new host if you switch later. AZWebCorp domains ship with full control over all of that from day one, and if something goes wrong, you're calling a Gilbert, AZ office, not a script.</p>

  <h2>What's included</h2>
  <ul>
    <li>Domain forwarding and masking</li>
    <li>Domain locking (prevents unauthorized transfers)</li>
    <li>Full DNS control</li>
    <li>Free WHOIS privacy</li>
    <li>Status alerts and auto-renew protection</li>
  </ul>
  <p><small>.com, .net and .org domains from $11.99/yr. Pricing excludes applicable taxes and ICANN fees.</small></p>

  <h2>Already have a domain elsewhere?</h2>
  <p>See <a href="/domain-transfer/">Domain Transfer</a> to move it here without downtime.</p>

  <h2>FAQ</h2>
  <h3>Is WHOIS privacy really free?</h3>
  <p>Yes, WHOIS privacy is included at no extra cost on new registrations.</p>
  <h3>What happens if I forget to renew?</h3>
  <p>Auto-renew protection and status alerts are on by default, so you're notified before a domain lapses.</p>

  <p><a href="https://www.shopazwebcorp.com/products/domain-registration?utm_source=azwebcorp&amp;utm_medium=bridge&amp;utm_campaign=domain-registration" rel="noopener">Search &amp; register a domain →</a></p>
  <p><a href="/hosting-domains/">← Back to all hosting &amp; domain plans</a></p>
AZWEBEOF
  NEWID=$(wp --path="$WP_PATH" post create "$WORKDIR/domain-registration.html" \
    --post_type=page \
    --post_status=draft \
    --post_title='Domain Registration for Arizona Businesses' \
    --post_name=domain-registration \
    --porcelain)
  echo "CREATE domain-registration -> ID $NEWID"
  CREATED=$((CREATED+1))
fi

# ---------- domain-transfer ----------
EXISTING=$(wp --path="$WP_PATH" post list --post_type=page --name=domain-transfer --format=ids)
if [ -n "$EXISTING" ]; then
  echo "SKIP   domain-transfer (already exists: ID $EXISTING)"
  SKIPPED=$((SKIPPED+1))
else
cat > "$WORKDIR/domain-transfer.html" <<'AZWEBEOF'
<h1>Domain Transfer to AZWebCorp (Gilbert, AZ)</h1>

  <p>Moving a domain doesn't have to mean downtime for your site or email. The transfer runs in the background while your existing DNS keeps resolving — nothing changes for visitors until the new records are live and confirmed.</p>

  <h2>Before you start</h2>
  <ul>
    <li>Unlock the domain at your current registrar</li>
    <li>Get the authorization (EPP) code from your current registrar</li>
    <li>Confirm the WHOIS email is one you can access — the transfer approval goes there</li>
    <li>Domains registered or transferred in the last 60 days generally can't transfer again yet</li>
  </ul>

  <h2>What's included after transfer</h2>
  <ul>
    <li>Full DNS control</li>
    <li>Free WHOIS privacy</li>
    <li>Domain locking and status alerts</li>
  </ul>

  <h2>FAQ</h2>
  <h3>Will my email stop working during the transfer?</h3>
  <p>No — as long as DNS records are copied over correctly before the transfer completes, mail flow isn't interrupted. We can walk through this with you if you're also moving hosting at the same time.</p>
  <h3>How long does a transfer take?</h3>
  <p>Typically 5–7 days, largely governed by the registrar approval window, not anything on our end.</p>

  <p><a href="https://www.shopazwebcorp.com/products/domain-transfer?utm_source=azwebcorp&amp;utm_medium=bridge&amp;utm_campaign=domain-transfer" rel="noopener">Start your domain transfer →</a></p>
  <p><a href="/hosting-domains/">← Back to all hosting &amp; domain plans</a></p>
AZWEBEOF
  NEWID=$(wp --path="$WP_PATH" post create "$WORKDIR/domain-transfer.html" \
    --post_type=page \
    --post_status=draft \
    --post_title='Domain Transfer to AZWebCorp (Gilbert, AZ)' \
    --post_name=domain-transfer \
    --porcelain)
  echo "CREATE domain-transfer -> ID $NEWID"
  CREATED=$((CREATED+1))
fi

# ---------- website-builder ----------
EXISTING=$(wp --path="$WP_PATH" post list --post_type=page --name=website-builder --format=ids)
if [ -n "$EXISTING" ]; then
  echo "SKIP   website-builder (already exists: ID $EXISTING)"
  SKIPPED=$((SKIPPED+1))
else
cat > "$WORKDIR/website-builder.html" <<'AZWEBEOF'
<h1>Website Builder for Arizona Small Businesses</h1>

  <p>For a straightforward brochure site, a drag-and-drop builder with an industry-specific starting template gets you live the same day. If your site needs custom functionality, a booking system, or e-commerce beyond the basics, our <a href="/arizona-web-development/">Gilbert, AZ-based web development team</a> can build it properly instead — worth a quick call before you commit either way.</p>

  <h2>What's included</h2>
  <ul>
    <li>Industry-specific templates</li>
    <li>Drag-and-drop editing, no code required</li>
    <li>Mobile-responsive by default</li>
    <li>Free SSL certificate</li>
  </ul>

  <h2>Builder vs. custom development — which do you need?</h2>
  <p>The builder is the right call for a simple, mostly-static site (hours, contact info, service list, a few photos). If you need custom booking flows, integrations, membership logins, or anything beyond a template's flexibility, custom development ends up cheaper than fighting the builder's limits later.</p>

  <h2>FAQ</h2>
  <h3>Can I move my site off the builder later?</h3>
  <p>Ask us before you build — some builder platforms make export difficult. We'll tell you upfront if that's a constraint for your use case.</p>

  <p><a href="https://www.shopazwebcorp.com/products/website-builder?utm_source=azwebcorp&amp;utm_medium=bridge&amp;utm_campaign=website-builder" rel="noopener">Start building your site →</a></p>
  <p><a href="/hosting-domains/">← Back to all hosting &amp; domain plans</a></p>
AZWEBEOF
  NEWID=$(wp --path="$WP_PATH" post create "$WORKDIR/website-builder.html" \
    --post_type=page \
    --post_status=draft \
    --post_title='Website Builder for Arizona Small Businesses' \
    --post_name=website-builder \
    --porcelain)
  echo "CREATE website-builder -> ID $NEWID"
  CREATED=$((CREATED+1))
fi

# ---------- business-email-hosting ----------
EXISTING=$(wp --path="$WP_PATH" post list --post_type=page --name=business-email-hosting --format=ids)
if [ -n "$EXISTING" ]; then
  echo "SKIP   business-email-hosting (already exists: ID $EXISTING)"
  SKIPPED=$((SKIPPED+1))
else
cat > "$WORKDIR/business-email-hosting.html" <<'AZWEBEOF'
<h1>Business Email Hosting for Arizona Businesses</h1>

  <p>A free @gmail.com address costs you credibility every time a customer sees it on a quote or invoice. A hosted @yourdomain.com inbox fixes that without needing your own mail server, set up and supported by our Gilbert, AZ team.</p>

  <h2>What's included</h2>
  <ul>
    <li>Custom @yourdomain.com addresses</li>
    <li>Spam and virus filtering</li>
    <li>Mobile and desktop sync (IMAP/webmail)</li>
    <li>Ad-free inbox</li>
  </ul>

  <h2>FAQ</h2>
  <h3>Can I keep my existing email while I set this up?</h3>
  <p>Yes — MX records only cut over once the new mailboxes are ready, so nothing goes down mid-setup.</p>
  <h3>Does this work with a domain registered elsewhere?</h3>
  <p>Yes, as long as you can update the domain's DNS/MX records. If not, see <a href="/domain-transfer/">Domain Transfer</a> first.</p>

  <p><a href="https://www.shopazwebcorp.com/products/email?utm_source=azwebcorp&amp;utm_medium=bridge&amp;utm_campaign=business-email" rel="noopener"><!-- CONFIRM URL PATH -->Get a business email address →</a></p>
  <p><a href="/hosting-domains/">← Back to all hosting &amp; domain plans</a></p>
AZWEBEOF
  NEWID=$(wp --path="$WP_PATH" post create "$WORKDIR/business-email-hosting.html" \
    --post_type=page \
    --post_status=draft \
    --post_title='Business Email Hosting for Arizona Businesses' \
    --post_name=business-email-hosting \
    --porcelain)
  echo "CREATE business-email-hosting -> ID $NEWID"
  CREATED=$((CREATED+1))
fi

rm -rf "$WORKDIR"
echo
echo "============================================================"
echo "  Created: $CREATED   Skipped (already existed): $SKIPPED"
echo "============================================================"
echo
echo "Review the drafts here:"
wp --path="$WP_PATH" post list --post_type=page --post_status=draft \
  --fields=ID,post_name,post_title --format=table
echo
echo "Nothing is published. Review each draft in wp-admin, then Publish."
