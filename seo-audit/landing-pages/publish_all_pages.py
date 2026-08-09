#!/usr/bin/env python3
"""
ALL-IN-ONE publisher for the AZWebCorp SEO bridge pages.

Everything is embedded in this single file -- no separate .html files
needed. Save this one file anywhere and run it.

Creates 7 pages on azwebcorp.com as WordPress DRAFTS via the REST API.
Nothing goes live until you review each draft and hit Publish yourself.

All JSON-LD emitted by this script is valid, parse-checked JSON. Pages
whose price isn't known yet ship a Product block WITHOUT an offers
object (valid schema) rather than a placeholder -- the script prints a
ready-to-paste offers snippet for those so you can add real pricing
later. Never paste a price you haven't confirmed: wrong prices in
Product/Offer schema can trigger rich-result penalties.

SETUP (PowerShell on Windows):
    pip install requests

    $env:WP_URL = "https://azwebcorp.com"
    $env:WP_USER = "admin"
    $env:WP_APP_PASSWORD = "xxxx xxxx xxxx xxxx xxxx xxxx"

    python publish_all_pages.py

Re-running creates DUPLICATE drafts -- it does not update existing ones.
Delete the old drafts first if you run it twice.

The script sets title/slug/content. Meta title, meta description,
canonical URL and JSON-LD depend on which SEO plugin is active
(Yoast/RankMath/AIOSEO), so those are PRINTED for you to paste into the
plugin's metabox per page. If your SEO plugin already outputs
Organization/Breadcrumb schema sitewide, do not also paste those blocks.
"""
import os
import sys

try:
    import requests
except ImportError:
    sys.exit("Missing dependency. Run:  pip install requests")

WP_URL = os.environ.get("WP_URL", "https://azwebcorp.com").rstrip("/")
WP_USER = os.environ.get("WP_USER")
WP_APP_PASSWORD = os.environ.get("WP_APP_PASSWORD")

if not WP_USER or not WP_APP_PASSWORD:
    sys.exit(
        "WP_USER and WP_APP_PASSWORD environment variables must be set.\n\n"
        "PowerShell:\n"
        '  $env:WP_USER = "admin"\n'
        '  $env:WP_APP_PASSWORD = "xxxx xxxx xxxx xxxx xxxx xxxx"\n'
    )

NEEDS_PRICE_SNIPPET = """  "offers": {
    "@type": "Offer",
    "priceCurrency": "USD",
    "price": "0.00",
    "availability": "https://schema.org/InStock",
    "url": "%s"
  }"""

PAGES = [
    {
        "slug": 'hosting-domains',
        "title": 'Arizona Web Hosting, Domains & WordPress Plans',
        "meta_title": 'Arizona Web Hosting, Domains & WordPress Plans | AZWebCorp',
        "meta_description": "Compare AZWebCorp's Arizona web hosting, domain, WordPress hosting and website builder plans, backed by Gilbert, AZ support. See pricing and pick the right plan.",
        "canonical": 'https://azwebcorp.com/hosting-domains/',
        "needs_price": False,
        "jsonld": '''{
  "@context": "https://schema.org",
  "@type": "BreadcrumbList",
  "itemListElement": [
    { "@type": "ListItem", "position": 1, "name": "Home", "item": "https://azwebcorp.com/" },
    { "@type": "ListItem", "position": 2, "name": "Hosting & Domains", "item": "https://azwebcorp.com/hosting-domains/" }
  ]
}
{
  "@context": "https://schema.org",
  "@type": "Organization",
  "name": "AZWebCorp",
  "url": "https://azwebcorp.com",
  "telephone": "+1-623-670-1611",
  "email": "info@azwebcorp.com",
  "address": {
    "@type": "PostalAddress",
    "streetAddress": "4690 E Laurel St",
    "addressLocality": "Gilbert",
    "addressRegion": "AZ",
    "postalCode": "85234",
    "addressCountry": "US"
  }
}''',
        "content": '''<h1>Arizona Web Hosting, Domains &amp; WordPress Plans</h1>

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
  <p>Plans are provisioned through our reseller infrastructure, but support requests come to AZWebCorp directly at (623) 670-1611 or info@azwebcorp.com — you're not routed into a generic hosting company's ticket queue.</p>''',
    },
    {
        "slug": 'web-hosting',
        "title": 'Arizona Web Hosting Plans — cPanel Hosting from Gilbert, AZ',
        "meta_title": 'Arizona Web Hosting Plans (cPanel) | AZWebCorp',
        "meta_description": "cPanel web hosting from AZWebCorp, based in Gilbert, AZ. 4 plans from $20.99/mo, free SSL, 99.9% uptime, and support that isn't a call center. Compare plans.",
        "canonical": 'https://azwebcorp.com/web-hosting/',
        "needs_price": False,
        "jsonld": '''{
  "@context": "https://schema.org",
  "@type": "BreadcrumbList",
  "itemListElement": [
    { "@type": "ListItem", "position": 1, "name": "Home", "item": "https://azwebcorp.com/" },
    { "@type": "ListItem", "position": 2, "name": "Hosting & Domains", "item": "https://azwebcorp.com/hosting-domains/" },
    { "@type": "ListItem", "position": 3, "name": "Web Hosting Plus", "item": "https://azwebcorp.com/web-hosting/" }
  ]
}
{
  "@context": "https://schema.org",
  "@type": "Product",
  "name": "Web Hosting Plus",
  "brand": { "@type": "Brand", "name": "AZWebCorp" },
  "offers": [
    { "@type": "Offer", "name": "Launch", "price": "20.99", "priceCurrency": "USD", "availability": "https://schema.org/InStock", "url": "https://azwebcorp.com/web-hosting/" },
    { "@type": "Offer", "name": "Enhance", "price": "36.99", "priceCurrency": "USD", "availability": "https://schema.org/InStock", "url": "https://azwebcorp.com/web-hosting/" },
    { "@type": "Offer", "name": "Grow", "price": "51.99", "priceCurrency": "USD", "availability": "https://schema.org/InStock", "url": "https://azwebcorp.com/web-hosting/" },
    { "@type": "Offer", "name": "Expand", "price": "72.99", "priceCurrency": "USD", "availability": "https://schema.org/InStock", "url": "https://azwebcorp.com/web-hosting/" }
  ]
}''',
        "content": '''<h1>Arizona Web Hosting Plans — cPanel Hosting from Gilbert, AZ</h1>

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
  <p><a href="/hosting-domains/">← Back to all hosting &amp; domain plans</a></p>''',
    },
    {
        "slug": 'wordpress-hosting',
        "title": 'Managed WordPress Hosting for Arizona Businesses',
        "meta_title": 'Managed WordPress Hosting in Gilbert & Phoenix, AZ | AZWebCorp',
        "meta_description": "Managed WordPress hosting from AZWebCorp: staging, auto-updates, free SSL. Built and supported in Gilbert, AZ by the same team that builds Arizona businesses' sites.",
        "canonical": 'https://azwebcorp.com/wordpress-hosting/',
        "needs_price": True,
        "jsonld": '''{
  "@context": "https://schema.org",
  "@type": "BreadcrumbList",
  "itemListElement": [
    { "@type": "ListItem", "position": 1, "name": "Home", "item": "https://azwebcorp.com/" },
    { "@type": "ListItem", "position": 2, "name": "Hosting & Domains", "item": "https://azwebcorp.com/hosting-domains/" },
    { "@type": "ListItem", "position": 3, "name": "WordPress Hosting", "item": "https://azwebcorp.com/wordpress-hosting/" }
  ]
}
{
  "@context": "https://schema.org",
  "@type": "Product",
  "name": "WordPress Hosting",
  "brand": { "@type": "Brand", "name": "AZWebCorp" },
  "description": "Managed WordPress hosting with staging, managed core and plugin updates, free SSL, daily backups and server-level caching.",
  "url": "https://azwebcorp.com/wordpress-hosting/"
}''',
        "content": '''<h1>Managed WordPress Hosting for Arizona Businesses</h1>

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
  <p><a href="/hosting-domains/">← Back to all hosting &amp; domain plans</a></p>''',
    },
    {
        "slug": 'domain-registration',
        "title": 'Domain Registration for Arizona Businesses',
        "meta_title": 'Domain Registration | Arizona-Based Support | AZWebCorp',
        "meta_description": 'Register a domain through AZWebCorp, based in Gilbert, AZ, from $11.99/yr. Free WHOIS privacy, domain locking and full DNS control, backed by local support.',
        "canonical": 'https://azwebcorp.com/domain-registration/',
        "needs_price": False,
        "jsonld": '''{
  "@context": "https://schema.org",
  "@type": "BreadcrumbList",
  "itemListElement": [
    { "@type": "ListItem", "position": 1, "name": "Home", "item": "https://azwebcorp.com/" },
    { "@type": "ListItem", "position": 2, "name": "Hosting & Domains", "item": "https://azwebcorp.com/hosting-domains/" },
    { "@type": "ListItem", "position": 3, "name": "Domain Registration", "item": "https://azwebcorp.com/domain-registration/" }
  ]
}
{
  "@context": "https://schema.org",
  "@type": "Product",
  "name": "Domain Registration",
  "brand": { "@type": "Brand", "name": "AZWebCorp" },
  "offers": {
    "@type": "Offer",
    "price": "11.99",
    "priceCurrency": "USD",
    "availability": "https://schema.org/InStock",
    "url": "https://azwebcorp.com/domain-registration/"
  }
}''',
        "content": '''<h1>Domain Registration for Arizona Businesses</h1>

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
  <p><a href="/hosting-domains/">← Back to all hosting &amp; domain plans</a></p>''',
    },
    {
        "slug": 'domain-transfer',
        "title": 'Domain Transfer to AZWebCorp (Gilbert, AZ)',
        "meta_title": 'Domain Transfer to a Local Arizona Host | AZWebCorp',
        "meta_description": 'Transfer your domain to AZWebCorp, a Gilbert, AZ-based host, without downtime. Free WHOIS privacy and DNS control included. See the transfer steps before you start.',
        "canonical": 'https://azwebcorp.com/domain-transfer/',
        "needs_price": False,
        "jsonld": '''{
  "@context": "https://schema.org",
  "@type": "BreadcrumbList",
  "itemListElement": [
    { "@type": "ListItem", "position": 1, "name": "Home", "item": "https://azwebcorp.com/" },
    { "@type": "ListItem", "position": 2, "name": "Hosting & Domains", "item": "https://azwebcorp.com/hosting-domains/" },
    { "@type": "ListItem", "position": 3, "name": "Domain Transfer", "item": "https://azwebcorp.com/domain-transfer/" }
  ]
}''',
        "content": '''<h1>Domain Transfer to AZWebCorp (Gilbert, AZ)</h1>

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
  <p><a href="/hosting-domains/">← Back to all hosting &amp; domain plans</a></p>''',
    },
    {
        "slug": 'website-builder',
        "title": 'Website Builder for Arizona Small Businesses',
        "meta_title": 'Website Builder for Arizona Small Businesses | AZWebCorp',
        "meta_description": "AZWebCorp's Website Builder gets an Arizona small business site live fast with industry-specific templates. No code required — or let our Gilbert, AZ team build it for you.",
        "canonical": 'https://azwebcorp.com/website-builder/',
        "needs_price": True,
        "jsonld": '''{
  "@context": "https://schema.org",
  "@type": "BreadcrumbList",
  "itemListElement": [
    { "@type": "ListItem", "position": 1, "name": "Home", "item": "https://azwebcorp.com/" },
    { "@type": "ListItem", "position": 2, "name": "Hosting & Domains", "item": "https://azwebcorp.com/hosting-domains/" },
    { "@type": "ListItem", "position": 3, "name": "Website Builder", "item": "https://azwebcorp.com/website-builder/" }
  ]
}
{
  "@context": "https://schema.org",
  "@type": "Product",
  "name": "Website Builder",
  "brand": { "@type": "Brand", "name": "AZWebCorp" },
  "description": "Drag-and-drop website builder with industry-specific templates, mobile-responsive layouts and a free SSL certificate.",
  "url": "https://azwebcorp.com/website-builder/"
}''',
        "content": '''<h1>Website Builder for Arizona Small Businesses</h1>

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
  <p><a href="/hosting-domains/">← Back to all hosting &amp; domain plans</a></p>''',
    },
    {
        "slug": 'business-email-hosting',
        "title": 'Business Email Hosting for Arizona Businesses',
        "meta_title": 'Business Email Hosting for Arizona Companies | AZWebCorp',
        "meta_description": 'Get a professional @yourdomain.com email address from AZWebCorp, based in Gilbert, AZ. Ad-free inboxes, spam filtering, and mobile sync for Arizona businesses.',
        "canonical": 'https://azwebcorp.com/business-email-hosting/',
        "needs_price": True,
        "jsonld": '''{
  "@context": "https://schema.org",
  "@type": "BreadcrumbList",
  "itemListElement": [
    { "@type": "ListItem", "position": 1, "name": "Home", "item": "https://azwebcorp.com/" },
    { "@type": "ListItem", "position": 2, "name": "Hosting & Domains", "item": "https://azwebcorp.com/hosting-domains/" },
    { "@type": "ListItem", "position": 3, "name": "Business Email", "item": "https://azwebcorp.com/business-email-hosting/" }
  ]
}
{
  "@context": "https://schema.org",
  "@type": "Product",
  "name": "Business Email Hosting",
  "brand": { "@type": "Brand", "name": "AZWebCorp" },
  "description": "Professional email hosting on your own domain with spam and virus filtering, ad-free inboxes and mobile/desktop sync.",
  "url": "https://azwebcorp.com/business-email-hosting/"
}''',
        "content": '''<h1>Business Email Hosting for Arizona Businesses</h1>

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
  <p><a href="/hosting-domains/">← Back to all hosting &amp; domain plans</a></p>''',
    },
]


def main():
    session = requests.Session()
    session.auth = (WP_USER, WP_APP_PASSWORD)

    created, failed = [], []

    for page in PAGES:
        try:
            resp = session.post(
                f"{WP_URL}/wp-json/wp/v2/pages",
                json={
                    "title": page["title"],
                    "slug": page["slug"],
                    "content": page["content"],
                    "status": "draft",
                },
                timeout=30,
            )
        except requests.RequestException as exc:
            failed.append((page["slug"], str(exc)))
            print(f"FAILED  {page['slug']}: {exc}", file=sys.stderr)
            continue

        if resp.status_code >= 300:
            failed.append((page["slug"], f"HTTP {resp.status_code}"))
            print(f"FAILED  {page['slug']}: HTTP {resp.status_code} {resp.text[:200]}", file=sys.stderr)
            continue

        data = resp.json()
        created.append((page["slug"], data.get("id"), data.get("link")))
        print(f"Created draft #{data.get('id')}: {page['slug']}")

    print("\n" + "=" * 70)
    print(f"RESULT: {len(created)} created, {len(failed)} failed, {len(PAGES)} total")
    print("=" * 70)

    if failed:
        print("\nFAILED PAGES:")
        for slug, why in failed:
            print(f"   {slug}: {why}")

    if created:
        print("\nEdit each draft here:")
        for slug, pid, link in created:
            print(f"   {slug:24} {WP_URL}/wp-admin/post.php?post={pid}&action=edit")

    print("\n\nSEO PLUGIN FIELDS -- paste into Yoast/RankMath per page:")
    print("=" * 70)
    for page in PAGES:
        print(f"\n[{page['slug']}]")
        print(f"  SEO title:  {page['meta_title']}")
        print(f"  Meta desc:  {page['meta_description']}")
        print(f"  Canonical:  {page['canonical']}")

    print("\n\nJSON-LD -- all blocks below are validated JSON. Add per page ONLY")
    print("if your SEO plugin isn't already emitting this schema sitewide:")
    print("=" * 70)
    for page in PAGES:
        if page["jsonld"].strip():
            print(f"\n[{page['slug']}]")
            print(page["jsonld"])
            if page["needs_price"]:
                print("\n  ^ Product block has NO offer (valid as-is). Once you have the")
                print("    real price, insert this after the \"url\" line, comma-separated,")
                print("    replacing 0.00:")
                print()
                print(NEEDS_PRICE_SNIPPET % page["canonical"])

    return 1 if failed else 0


if __name__ == "__main__":
    sys.exit(main())
