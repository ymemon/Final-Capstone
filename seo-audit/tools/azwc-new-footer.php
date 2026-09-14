<?php
/**
 * Plugin Name: AZW New Footer
 * Description: Replaces the theme's default widget-based footer with the
 * complete custom AZWebCorp footer design.
 *
 * The active theme (bosa) builds its footer from classic WP widgets in
 * footer.php, not an Elementor template, so there is no template to swap out
 * from a plugin. Instead of editing the parent theme file (lost on a theme
 * update, and against this site's own convention of never touching theme
 * files), the theme's own #colophon is hidden with CSS and the full
 * self-contained footer markup is injected at wp_footer — same technique as
 * every other azw-*.php override on this site.
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'wp_footer',
	static function () {
		?>
<style id="azwc-new-footer-hide-default">
/* The theme's own footer — replaced by the markup below, not deleted, so
   turning this plugin off instantly restores the original. */
#colophon.site-footer { display: none !important; }
</style>
<style>
/* =========================================================
   AZWEBCORP — COMPLETE SITE FOOTER
   ========================================================= */

.azwc-footer,
.azwc-footer * {
  box-sizing: border-box;
}

.azwc-footer {
  --az-bg: #05070a;
  --az-card: #0c1118;
  --az-card-2: #101720;
  --az-text: #f7f8fa;
  --az-muted: #9da8b7;
  --az-gold: #e6b84d;
  --az-gold-light: #f4d27d;
  --az-line: rgba(255,255,255,.09);

  position: relative;
  width: 100%;
  overflow: hidden;
  background:
    radial-gradient(circle at 85% 25%, rgba(230,184,77,.13), transparent 27%),
    radial-gradient(circle at 5% 80%, rgba(69,118,255,.06), transparent 25%),
    var(--az-bg);
  color: var(--az-text);
  font-family: Inter, -apple-system, BlinkMacSystemFont, "Segoe UI",
    Roboto, Helvetica, Arial, sans-serif;
}

/* subtle grid */
.azwc-footer::before {
  content: "";
  position: absolute;
  inset: 0;
  pointer-events: none;
  background-image:
    linear-gradient(rgba(255,255,255,.018) 1px, transparent 1px),
    linear-gradient(90deg, rgba(255,255,255,.018) 1px, transparent 1px);
  background-size: 38px 38px;
  mask-image: linear-gradient(to bottom, black, transparent 85%);
}

.azwc-footer a {
  color: inherit;
  text-decoration: none;
  transition: all .22s ease;
}

.azwc-footer a:hover {
  color: var(--az-gold-light);
}

.azwc-footer-inner {
  position: relative;
  z-index: 2;
  width: min(1200px, calc(100% - 40px));
  margin: 0 auto;
}

/* =========================================================
   TOP CTA
   ========================================================= */

.azwc-footer-cta {
  padding: 56px 0 42px;
  border-bottom: 1px solid var(--az-line);
}

.azwc-footer-cta-box {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 30px;

  padding: 30px 34px;
  border: 1px solid rgba(230,184,77,.22);
  border-radius: 22px;

  background:
    linear-gradient(
      120deg,
      rgba(255,255,255,.035),
      rgba(230,184,77,.055)
    );

  box-shadow: 0 24px 80px rgba(0,0,0,.25);
}

.azwc-footer-cta-copy h2 {
  margin: 0 0 8px;
  color: #fff;
  font-size: clamp(1.7rem, 3vw, 2.45rem);
  line-height: 1.1;
  letter-spacing: -.035em;
}

.azwc-footer-cta-copy p {
  margin: 0;
  max-width: 700px;
  color: var(--az-muted);
  font-size: 1rem;
}

.azwc-footer-btn {
  flex: 0 0 auto;
  display: inline-flex;
  align-items: center;
  justify-content: center;

  padding: 14px 21px;
  border-radius: 999px;

  background: var(--az-gold);
  color: #17130a !important;

  font-weight: 800;
  white-space: nowrap;
}

.azwc-footer-btn:hover {
  color: #17130a !important;
  background: var(--az-gold-light);
  transform: translateY(-2px);
}

/* =========================================================
   MAIN GRID
   ========================================================= */

.azwc-footer-main {
  display: grid;
  grid-template-columns: 1.45fr 1fr 1fr 1fr;
  gap: 44px;

  padding: 60px 0 50px;
}

.azwc-footer-brand {
  max-width: 340px;
}

.azwc-footer-logo {
  display: inline-flex;
  align-items: center;
  gap: 13px;
  margin-bottom: 20px;
}

.azwc-footer-logo img {
  display: block;
  max-width: 185px;
  height: auto;
}

.azwc-footer-brand-name {
  color: #fff;
  font-size: 1.35rem;
  font-weight: 900;
  letter-spacing: -.02em;
}

.azwc-footer-description {
  margin: 0 0 23px;
  color: var(--az-muted);
  font-size: .96rem;
  line-height: 1.75;
}

.azwc-footer-local {
  display: inline-flex;
  align-items: center;
  gap: 9px;
  margin-top: 4px;
  color: var(--az-gold-light);
  font-size: .84rem;
  font-weight: 800;
}

.azwc-footer-local-dot {
  width: 7px;
  height: 7px;
  border-radius: 999px;
  background: var(--az-gold);
  box-shadow: 0 0 12px rgba(230,184,77,.8);
}

/* columns */

.azwc-footer-col h3 {
  margin: 0 0 20px;
  color: #fff;
  font-size: .84rem;
  font-weight: 900;
  text-transform: uppercase;
  letter-spacing: .12em;
}

.azwc-footer-links {
  padding: 0;
  margin: 0;
  list-style: none;
}

.azwc-footer-links li {
  margin: 0 0 11px;
}

.azwc-footer-links a {
  display: inline-block;
  color: var(--az-muted);
  font-size: .94rem;
}

.azwc-footer-links a:hover {
  color: var(--az-gold-light);
  transform: translateX(3px);
}

/* =========================================================
   CONTACT STRIP
   ========================================================= */

.azwc-footer-contact {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 1px;

  border-top: 1px solid var(--az-line);
  border-bottom: 1px solid var(--az-line);
  background: var(--az-line);
}

.azwc-footer-contact-item {
  min-height: 105px;
  padding: 25px 30px;
  background: #080b10;
}

.azwc-footer-contact-label {
  display: block;
  margin-bottom: 7px;

  color: var(--az-gold);
  font-size: .72rem;
  font-weight: 900;
  text-transform: uppercase;
  letter-spacing: .12em;
}

.azwc-footer-contact-value {
  color: #fff;
  font-size: .94rem;
  line-height: 1.6;
}

.azwc-footer-contact-value a {
  color: #fff;
}

/* =========================================================
   SHOP / HOURS BAR
   ========================================================= */

.azwc-footer-servicebar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 25px;
  padding: 22px 0;
  border-bottom: 1px solid var(--az-line);
}

.azwc-footer-hours {
  color: var(--az-muted);
  font-size: .83rem;
}

.azwc-footer-hours strong {
  color: #fff;
}

.azwc-shop24 {
  color: var(--az-gold-light);
  font-weight: 800;
}

/* =========================================================
   BOTTOM BAR
   ========================================================= */

.azwc-footer-bottom {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 25px;

  padding: 24px 0 30px;
}

.azwc-footer-copyright {
  color: #7f8996;
  font-size: .82rem;
}

.azwc-footer-legal {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 18px;
}

.azwc-footer-legal a {
  color: #8e98a6;
  font-size: .82rem;
}

/* =========================================================
   TINY ANIMATED GLOBE
   ========================================================= */

.azwc-globe-line {
  display: inline-flex;
  align-items: center;
  gap: 5px;
}

.azwc-globe {
  position: relative;
  display: inline-block;

  width: 12px;
  height: 12px;
  border-radius: 50%;

  background:
    radial-gradient(circle at 35% 25%, #dff6ff 0 7%, transparent 8%),
    linear-gradient(140deg, #55bdff 0%, #156ed5 55%, #062b72 100%);

  border: 1px solid rgba(137,214,255,.85);

  box-shadow:
    0 0 8px rgba(67,168,255,.35),
    inset -2px -2px 3px rgba(0,0,0,.25);

  overflow: hidden;
}

.azwc-globe::before,
.azwc-globe::after {
  content: "";
  position: absolute;
}

.azwc-globe::before {
  width: 5px;
  height: 3px;
  left: 1px;
  top: 3px;
  border-radius: 60% 40% 55% 40%;
  background: #55d983;

  box-shadow:
    5px 2px 0 -1px #39b86d,
    1px 5px 0 -1px #45c878;

  animation: azwc-globe-land 4.8s linear infinite;
}

.azwc-globe::after {
  inset: 1px 4px;
  border-left: 1px solid rgba(255,255,255,.25);
  border-right: 1px solid rgba(255,255,255,.16);
  border-radius: 50%;
}

@keyframes azwc-globe-land {
  0% {
    transform: translateX(-4px);
  }

  100% {
    transform: translateX(8px);
  }
}

/* =========================================================
   MOBILE
   ========================================================= */

@media (max-width: 980px) {

  .azwc-footer-main {
    grid-template-columns: 1.4fr 1fr 1fr;
  }

  .azwc-footer-brand {
    grid-column: 1 / -1;
    max-width: 650px;
  }

  .azwc-footer-cta-box {
    align-items: flex-start;
    flex-direction: column;
  }
}

@media (max-width: 720px) {

  .azwc-footer-inner {
    width: min(100% - 28px, 1200px);
  }

  .azwc-footer-main {
    grid-template-columns: 1fr 1fr;
    gap: 38px 26px;
    padding: 48px 0 42px;
  }

  .azwc-footer-contact {
    grid-template-columns: 1fr;
  }

  .azwc-footer-contact-item {
    min-height: auto;
  }

  .azwc-footer-servicebar,
  .azwc-footer-bottom {
    align-items: flex-start;
    flex-direction: column;
  }
}

@media (max-width: 480px) {

  .azwc-footer-main {
    grid-template-columns: 1fr;
  }

  .azwc-footer-cta {
    padding-top: 38px;
  }

  .azwc-footer-cta-box {
    padding: 25px 22px;
  }

  .azwc-footer-btn {
    width: 100%;
  }
}

@media (prefers-reduced-motion: reduce) {
  .azwc-globe::before {
    animation: none;
  }

  .azwc-footer a,
  .azwc-footer-btn {
    transition: none;
  }
}
</style>


<footer class="azwc-footer">

  <!-- =====================================================
       TOP CTA
       ===================================================== -->

  <section class="azwc-footer-cta">
    <div class="azwc-footer-inner">

      <div class="azwc-footer-cta-box">

        <div class="azwc-footer-cta-copy">
          <h2>Ready to Build Something Better?</h2>

          <p>
            Talk with AZWebCorp about web development, SEO,
            business email, domains, hosting, or your next
            custom technology project.
          </p>
        </div>

        <a
          class="azwc-footer-btn"
          href="https://azwebcorp.com/contact-us/"
        >
          Start a Project →
        </a>

      </div>
    </div>
  </section>


  <!-- =====================================================
       MAIN FOOTER
       ===================================================== -->

  <div class="azwc-footer-inner">

    <div class="azwc-footer-main">

      <!-- BRAND -->

      <div class="azwc-footer-brand">

        <a
          class="azwc-footer-logo"
          href="https://azwebcorp.com/"
          aria-label="AZWebCorp Home"
        >
          <img
            src="https://azwebcorp.com/wp-content/uploads/2024/06/Azwebcorp-white_logo.png"
            alt="AZWebCorp"
          >
        </a>

        <p class="azwc-footer-description">
          Arizona-based web development, WordPress, SEO,
          eCommerce, domains, business email, hosting, and
          custom technology solutions for businesses locally
          and nationwide.
        </p>

        <div class="azwc-footer-local">
          <span class="azwc-footer-local-dot"></span>
          Proudly based in Gilbert, Arizona
        </div>

      </div>


      <!-- SERVICES -->

      <div class="azwc-footer-col">

        <h3>Solutions</h3>

        <ul class="azwc-footer-links">

          <li>
            <a href="https://azwebcorp.com/web-development/">
              Web Development
            </a>
          </li>

          <li>
            <a href="https://azwebcorp.com/arizona-seo-services/">
              SEO Services
            </a>
          </li>

          <li>
            <a href="https://azwebcorp.com/web-development/#wordpress">
              WordPress Development
            </a>
          </li>

          <li>
            <a href="https://azwebcorp.com/web-development/#custom-development">
              Custom Development
            </a>
          </li>

          <li>
            <a href="https://azwebcorp.com/web-development/#ecommerce">
              eCommerce Development
            </a>
          </li>

          <li>
            <a href="https://azwebcorp.com/contact-us/">
              Website Maintenance
            </a>
          </li>

        </ul>

      </div>


      <!-- SHOP -->

      <div class="azwc-footer-col">

        <h3>Our Shop</h3>

        <ul class="azwc-footer-links">

          <li>
            <a href="https://azwebcorp.com/domain-registration/">
              Domain Registration
            </a>
          </li>

          <li>
            <a href="https://azwebcorp.com/business-email/">
              Business Email
            </a>
          </li>

          <li>
            <a
              href="https://www.shopazwebcorp.com/products/microsoft-365"
              target="_blank"
              rel="noopener"
            >
              Microsoft 365
            </a>
          </li>

          <li>
            <a
              href="https://www.shopazwebcorp.com/"
              target="_blank"
              rel="noopener"
            >
              Managed WordPress
            </a>
          </li>

          <li>
            <a
              href="https://www.shopazwebcorp.com/"
              target="_blank"
              rel="noopener"
            >
              cPanel Hosting
            </a>
          </li>

          <li>
            <a
              href="https://www.shopazwebcorp.com/"
              target="_blank"
              rel="noopener"
            >
              VPS Hosting
            </a>
          </li>

          <li>
            <a
              href="https://www.shopazwebcorp.com/"
              target="_blank"
              rel="noopener"
            >
              SSL Certificates
            </a>
          </li>

          <li>
            <a
              href="https://www.shopazwebcorp.com/"
              target="_blank"
              rel="noopener"
            >
              Firewall / WAF
            </a>
          </li>

        </ul>

      </div>


      <!-- COMPANY -->

      <div class="azwc-footer-col">

        <h3>Company</h3>

        <ul class="azwc-footer-links">

          <li>
            <a href="https://azwebcorp.com/about-azwebcorp/">
              About AZWebCorp
            </a>
          </li>

          <li>
            <a href="https://azwebcorp.com/our-featured-projects/">
              Featured Projects
            </a>
          </li>

          <li>
            <a href="https://azwebcorp.com/contact-us/">
              Contact Us
            </a>
          </li>

          <li>
            <a
              href="https://www.shopazwebcorp.com/"
              target="_blank"
              rel="noopener"
            >
              ShopAZWebCorp
            </a>
          </li>

        </ul>

      </div>

    </div>

  </div>


  <!-- =====================================================
       CONTACT STRIP
       ===================================================== -->

  <div class="azwc-footer-contact">

    <div class="azwc-footer-contact-item">

      <span class="azwc-footer-contact-label">
        Address
      </span>

      <div class="azwc-footer-contact-value">
        4690 E Laurel Ave<br>
        Gilbert, AZ 85234 USA
      </div>

    </div>


    <div class="azwc-footer-contact-item">

      <span class="azwc-footer-contact-label">
        Phone
      </span>

      <div class="azwc-footer-contact-value">

        <a href="tel:+14808185761">
          (480) 818-5761
        </a>

      </div>

    </div>


    <div class="azwc-footer-contact-item">

      <span class="azwc-footer-contact-label">
        Email
      </span>

      <div class="azwc-footer-contact-value">

        <a href="mailto:info@azwebcorp.com">
          info@azwebcorp.com
        </a>

      </div>

    </div>

  </div>


  <!-- =====================================================
       BUSINESS HOURS
       ===================================================== -->

  <div class="azwc-footer-inner">

    <div class="azwc-footer-servicebar">

      <div class="azwc-footer-hours">

        <strong>AZWebCorp</strong>
        &nbsp;|&nbsp;
        Monday – Friday
        &nbsp;|&nbsp;
        9:00 AM – 6:00 PM PST

      </div>


      <div class="azwc-footer-hours">

        <a
          class="azwc-shop24"
          href="https://www.shopazwebcorp.com/"
          target="_blank"
          rel="noopener"
        >
          ShopAZWebCorp — 24/7
        </a>

      </div>

    </div>


    <!-- ===================================================
         BOTTOM
         =================================================== -->

    <div class="azwc-footer-bottom">

      <div class="azwc-footer-copyright">

        © 2026 AZWebCorp.
        All rights reserved.

        <span class="azwc-globe-line">
          Arizona
          <span
            class="azwc-globe"
            aria-hidden="true"
          ></span>
          Worldwide
        </span>

      </div>


      <div class="azwc-footer-legal">

        <a href="https://azwebcorp.com/privacy-policy/">
          Privacy Policy
        </a>

        <a href="https://azwebcorp.com/contact-us/">
          Contact
        </a>

        <a
          href="https://www.shopazwebcorp.com/"
          target="_blank"
          rel="noopener"
        >
          Shop
        </a>

      </div>

    </div>

  </div>

</footer>
		<?php
	},
	20
);
