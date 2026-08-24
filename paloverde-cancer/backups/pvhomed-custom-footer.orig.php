<?php
/**
 * Plugin Name: PVHOMED Custom Footer
 * Description: Injects the redesigned PVHOMED footer and hides the old one.
 * Version: 1.1
 * Author: AZWebCorp
 */

// Hide old Elementor footer
add_action('wp_head', function() {
    echo '<style>
    footer#colophon,
    .site-footer .elementor-section-wrap,
    .ast-small-footer,
    footer .ast-footer-overlay,
    .site-footer > .elementor,
    .site-footer .elementor-location-footer {
        display: none !important;
    }
    </style>';
});

// Inject new footer
add_action('wp_footer', function() {
?>
<style>
@import url('https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=Source+Sans+3:wght@300;400;500;600&display=swap');
.pvhomed-footer { background: #0d1117; color: #b0b3b8; font-family: 'Source Sans 3', sans-serif; }
.footer-accent { height: 3px; background: #0f2a42; }
.footer-main { max-width: 100%; margin: 0 auto; padding: 50px 5% 30px; }
.footer-grid { display: grid; grid-template-columns: 1.4fr 1fr 1fr 1.2fr; gap: 80px; margin-bottom: 40px; }
.footer-brand img { max-width: 220px; height: auto; margin-bottom: 18px; background: #fff; padding: 10px; border-radius: 4px; }
.footer-brand p { font-size: 14.5px; line-height: 1.7; color: #b0b3b8; margin-bottom: 22px; }
.footer-social { display: flex; gap: 10px; }
.footer-social a { display: flex; align-items: center; justify-content: center; width: 40px; height: 40px; border-radius: 8px; background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.1); color: #b0b3b8; text-decoration: none; transition: all 0.3s ease; }
.footer-social a:hover { background: #0f2a42; border-color: #0f2a42; color: #fff; transform: translateY(-2px); box-shadow: 0 4px 12px rgba(15, 42, 66, 0.35); }
.footer-social a svg { width: 18px; height: 18px; fill: currentColor; }
.footer-heading { font-family: 'Playfair Display', serif; font-size: 16px; font-weight: 600; color: #ffffff; margin-bottom: 20px; letter-spacing: 0.5px; position: relative; padding-bottom: 12px; }
.footer-heading::after { content: ''; position: absolute; bottom: 0; left: 0; width: 30px; height: 2px; background: #0f2a42; border-radius: 2px; }
.footer-links { list-style: none; padding: 0; margin: 0; }
.footer-links li { margin-bottom: 9px; }
.footer-links a { color: #b0b3b8; text-decoration: none; font-size: 14px; transition: all 0.25s ease; display: inline-flex; align-items: center; gap: 6px; }
.footer-links a::before { content: ''; display: inline-block; width: 0; height: 1px; background: #0f2a42; transition: width 0.25s ease; }
.footer-links a:hover { color: #ffffff; padding-left: 2px; }
.footer-links a:hover::before { width: 12px; }
.location-item { margin-bottom: 14px; display: block; }
.location-item .loc-name { font-weight: 600; color: #d0d3d8; font-size: 13.5px; display: block; margin-bottom: 2px; }
.location-item .loc-phone { display: block; }
.location-item .loc-phone a { color: #8a8d91; font-size: 13px; text-decoration: none; transition: color 0.2s; }
.location-item .loc-phone a:hover { color: #0f2a42; }
.contact-item { display: flex; align-items: flex-start; gap: 12px; margin-bottom: 16px; }
.contact-icon { flex-shrink: 0; width: 36px; height: 36px; border-radius: 8px; background: rgba(15, 42, 66, 0.15); display: flex; align-items: center; justify-content: center; margin-top: 2px; }
.contact-icon svg { width: 16px; height: 16px; fill: #0f2a42; }
.contact-text { font-size: 14px; line-height: 1.6; color: #b0b3b8; }
.contact-text a { color: #b0b3b8; text-decoration: none; transition: color 0.2s; }
.contact-text a:hover { color: #0f2a42; }
.contact-label { font-size: 11px; text-transform: uppercase; letter-spacing: 1px; color: #6e7177; margin-bottom: 2px; }
.footer-cta { display: inline-flex; align-items: center; gap: 8px; margin-top: 12px; padding: 10px 22px; background: #0f2a42; color: #fff; border: 1px solid rgba(255,255,255,0.05); border-radius: 6px; font-size: 13.5px; font-weight: 600; text-decoration: none; letter-spacing: 0.3px; transition: all 0.3s ease; cursor: pointer; }
.footer-cta:hover { background: #0a1e30; transform: translateY(-1px); box-shadow: 0 6px 20px rgba(15, 42, 66, 0.35); }
.footer-cta svg { width: 14px; height: 14px; fill: currentColor; }
.footer-divider { height: 1px; background: linear-gradient(90deg, transparent, rgba(255,255,255,0.08), transparent); margin-bottom: 24px; }
.footer-bottom { max-width: 100%; margin: 0 auto; padding: 0 5% 28px; }
.footer-bottom-inner { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; }
.footer-copyright { font-size: 13px; color: #6e7177; }
.footer-powered { display: flex; align-items: center; gap: 8px; font-size: 12.5px; color: #6e7177; }
.footer-powered a { color: #8a8d91; text-decoration: none; font-weight: 600; transition: color 0.2s; }
.footer-powered a:hover { color: #0f2a42; }
.footer-powered .globe-icon { width: 16px; height: 16px; fill: #0f2a42; opacity: 0.8; }
@media (max-width: 960px) { .footer-grid { grid-template-columns: 1fr 1fr; gap: 30px; } }
@media (max-width: 600px) { .footer-grid { grid-template-columns: 1fr; gap: 28px; } .footer-main { padding: 36px 20px 24px; } .footer-bottom { padding: 0 20px 20px; } .footer-bottom-inner { flex-direction: column; text-align: center; } .footer-brand img { max-width: 180px; } .footer-social { justify-content: center; } }
</style>

<footer class="pvhomed-footer">
  <div class="footer-accent"></div>
  <div class="footer-main">
    <div class="footer-grid">
      <div class="footer-brand">
        <img src="/wp-content/themes/paloverde/images/logo.jpg" alt="Palo Verde Cancer Specialists">
        <p>Palo Verde Cancer Specialists is committed to providing accessible, high-quality cancer care and enhancing patient well-being across the Valley.</p>
        <div class="footer-social">
          <a href="https://www.facebook.com/pvhomed" aria-label="Facebook" target="_blank"><svg viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg></a>
          <a href="https://www.instagram.com/paloverdecancerspecialists/" aria-label="Instagram" target="_blank"><svg viewBox="0 0 24 24"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z"/></svg></a>
          <a href="https://www.linkedin.com/company/palo-verde-cancer-specialists/" aria-label="LinkedIn" target="_blank"><svg viewBox="0 0 24 24"><path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433a2.062 2.062 0 01-2.063-2.065 2.064 2.064 0 112.063 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/></svg></a>
        </div>
      </div>
      <div>
        <h4 class="footer-heading">Quick Links</h4>
        <ul class="footer-links">
          <li><a href="/about-us/">About Us</a></li>
          <li><a href="/your-team/">Our Physicians</a></li>
          <li><a href="/services/">Services</a></li>
          <li><a href="/conditions-we-treat/">Conditions We Treat</a></li>
          <li><a href="/patient-forms/">Patient Forms</a></li>
          <li><a href="/schedule/">Schedule Appointment</a></li>
          <li><a href="/clinical-research/">Clinical Research</a></li>
          <li><a href="/pet-scan-imaging/">PET Scan Imaging</a></li>
        </ul>
      </div>
      <div>
        <h4 class="footer-heading">Our Locations</h4>
        <div>
          <div class="location-item"><span class="loc-name">Scottsdale</span><span class="loc-phone"><a href="tel:4809411211">(480) 941-1211</a></span></div>
          <div class="location-item"><span class="loc-name">East Valley (Gilbert)</span><span class="loc-phone"><a href="tel:4809411211">(480) 941-1211</a></span></div>
          <div class="location-item"><span class="loc-name">Glendale &mdash; Eugie Ave</span><span class="loc-phone"><a href="tel:6029786255">(602) 978-6255</a></span></div>
          <div class="location-item"><span class="loc-name">Glendale &mdash; Zanjero</span><span class="loc-phone"><a href="tel:6023756230">(602) 375-6230</a></span></div>
          <div class="location-item"><span class="loc-name">Estrella</span><span class="loc-phone"><a href="tel:6234788091">(623) 478-8091</a></span></div>
        </div>
      </div>
      <div>
        <h4 class="footer-heading">Contact Us</h4>
        <div>
          <div class="contact-item">
            <div class="contact-icon"><svg viewBox="0 0 24 24"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5a2.5 2.5 0 010-5 2.5 2.5 0 010 5z"/></svg></div>
            <div class="contact-text"><div class="contact-label">Main Office</div>7373 N Scottsdale Rd, Ste. E-100<br>Scottsdale, AZ 85253</div>
          </div>
          <div class="contact-item">
            <div class="contact-icon"><svg viewBox="0 0 24 24"><path d="M6.62 10.79c1.44 2.83 3.76 5.14 6.59 6.59l2.2-2.2c.27-.27.67-.36 1.02-.24 1.12.37 2.33.57 3.57.57.55 0 1 .45 1 1V20c0 .55-.45 1-1 1-9.39 0-17-7.61-17-17 0-.55.45-1 1-1h3.5c.55 0 1 .45 1 1 0 1.25.2 2.45.57 3.57.11.35.03.74-.25 1.02l-2.2 2.2z"/></svg></div>
            <div class="contact-text"><div class="contact-label">Phone</div><a href="tel:6029786255">(602) 978-6255</a></div>
          </div>
          <div class="contact-item">
            <div class="contact-icon"><svg viewBox="0 0 24 24"><path d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/></svg></div>
            <div class="contact-text"><div class="contact-label">Email</div><a href="mailto:info@pvhomed.com">info@pvhomed.com</a></div>
          </div>
          <a href="/schedule/" class="footer-cta"><svg viewBox="0 0 24 24"><path d="M19 4h-1V2h-2v2H8V2H6v2H5c-1.11 0-1.99.9-1.99 2L3 20a2 2 0 002 2h14c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 16H5V10h14v10zm0-12H5V6h14v2z"/></svg>Schedule an Appointment</a>
        </div>
      </div>
    </div>
    <div class="footer-divider"></div>
  </div>
  <div class="footer-bottom">
    <div class="footer-bottom-inner">
      <span class="footer-copyright">&copy; 2026 Palo Verde Cancer Specialists. All rights reserved.</span>
      <div class="footer-powered">
        <svg class="globe-icon" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 17.93c-3.95-.49-7-3.85-7-7.93 0-.62.08-1.21.21-1.79L9 15v1c0 1.1.9 2 2 2v1.93zm6.9-2.54c-.26-.81-1-1.39-1.9-1.39h-1v-3c0-.55-.45-1-1-1H8v-2h2c.55 0 1-.45 1-1V7h2c1.1 0 2-.9 2-2v-.41c2.93 1.19 5 4.06 5 7.41 0 2.08-.8 3.97-2.1 5.39z"/></svg>
        Powered by <a href="https://azwebcorp.com" target="_blank">AZWebCorp</a>
      </div>
    </div>
  </div>
</footer>
<?php
}, 5);
