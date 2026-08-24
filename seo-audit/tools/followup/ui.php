<?php
/**
 * Front end: the "what next" CTA, the PDF form, and the booking calendar.
 *
 * @package AZWC
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Only load this where the audit tool actually is.
 *
 * Detected by page slug rather than has_shortcode(): these pages are built in
 * Elementor, which renders from _elementor_data and leaves post_content empty,
 * so a shortcode scan finds nothing on the very pages that need this.
 */
function azwc_fu_should_render() {
	$on = is_page( array( 'free-seo-audit', 'seo-audit-results' ) )
		|| isset( $_GET['azwc_call'] ); // phpcs:ignore WordPress.Security.NonceVerification

	return (bool) apply_filters( 'azwc_fu_should_render', $on );
}

add_action( 'wp_footer', 'azwc_fu_render', 20 );

function azwc_fu_render() {
	if ( ! azwc_fu_should_render() ) {
		return;
	}

	$rest = esc_url_raw( rest_url( 'azwc/v1' ) );
	// data-noptimize: Autoptimize strips inline blocks into a cached aggregate,
	// and a page can go on referencing the previous bundle for a while. Left
	// inline, this always ships with the markup it styles.
	?>
<style id="azwc-fu-css" data-noptimize="1">
	#azwc-followup-cta.azwc-fu-ready{background:linear-gradient(135deg,#050608,#111823 60%,#30240a);
		border:1px solid rgba(230,184,77,.24);border-radius:16px;padding:30px 28px;text-align:left}
	#azwc-followup-cta.azwc-fu-ready h3{margin:0 0 6px;color:#fff;font-size:21px}
	#azwc-followup-cta.azwc-fu-ready > p{margin:0 0 20px;color:#d6dce4;font-size:14.5px;line-height:1.62;max-width:62ch}
	.azwc-fu-cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(232px,1fr));gap:12px}
	.azwc-fu-card{display:block;width:100%;text-align:left;cursor:pointer;font:inherit;
		background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.14);border-radius:12px;padding:16px 18px;
		transition:border-color .15s ease,background .15s ease,transform .06s ease}
	.azwc-fu-card:hover{background:rgba(230,184,77,.12);border-color:#e6b84d}
	.azwc-fu-card:active{transform:translateY(1px)}
	.azwc-fu-card b{display:block;color:#fff;font-size:15.5px;font-weight:750;margin-bottom:3px}
	.azwc-fu-card span{display:block;color:#aab2bd;font-size:13px;line-height:1.5}
	.azwc-fu-card.is-primary{background:#e6b84d;border-color:#e6b84d}
	.azwc-fu-card.is-primary b{color:#161208}
	.azwc-fu-card.is-primary span{color:#4a3d18}
	.azwc-fu-card.is-primary:hover{background:#f5d47d;border-color:#f5d47d}

	.azwc-fu-modal{position:fixed;inset:0;z-index:99999;display:none;align-items:flex-start;justify-content:center;
		padding:24px 14px;overflow-y:auto;background:rgba(4,6,10,.74)}
	.azwc-fu-modal.is-open{display:flex}
	.azwc-fu-box{position:relative;width:100%;max-width:600px;margin:auto;background:#0d1219;color:#d6dce4;
		border:1px solid rgba(230,184,77,.24);
		border-radius:16px;padding:30px 28px;font-size:15px;line-height:1.6}
	.azwc-fu-box h4{margin:0 0 6px;font-size:20px;color:#ffffff}
	.azwc-fu-box .azwc-fu-lede{margin:0 0 18px;color:#aab2bd;font-size:14px}
	.azwc-fu-x{position:absolute;top:12px;right:12px;width:34px;height:34px;border:0;border-radius:9px;
		background:rgba(255,255,255,.08);color:#d6dce4;font-size:19px;line-height:1;cursor:pointer}
	.azwc-fu-x:hover{background:rgba(255,255,255,.16)}
	.azwc-fu-field{margin-bottom:13px}
	.azwc-fu-field label{display:block;font-size:12.5px;font-weight:750;margin-bottom:4px;color:#d6dce4}
	.azwc-fu-field input{width:100%;padding:11px 13px;font:inherit;font-size:15px;color:#ffffff;background:rgba(255,255,255,.06);
		border:1px solid rgba(255,255,255,.22);border-radius:9px}
	.azwc-fu-field input:focus{outline:2px solid #e6b84d;outline-offset:1px;border-color:#e6b84d}
	.azwc-fu-field input::placeholder{color:#98a1ad}
	.azwc-fu-hp{position:absolute!important;left:-9999px!important;width:1px!important;height:1px!important;overflow:hidden}
	.azwc-fu-go{width:100%;padding:14px 20px;font:inherit;font-size:15.5px;font-weight:800;color:#161208;
		background:#e6b84d;border:1px solid #e6b84d;border-radius:10px;cursor:pointer;margin-top:6px}
	.azwc-fu-go:hover{background:#f5d47d}
	.azwc-fu-go[disabled]{opacity:.6;cursor:default}
	.azwc-fu-note{margin:12px 0 0;font-size:12.5px;color:#98a1ad;line-height:1.55}
	.azwc-fu-msg{margin:14px 0 0;padding:12px 14px;border-radius:9px;font-size:14px;display:none}
	.azwc-fu-msg.is-err{display:block;background:rgba(214,69,69,.14);border:1px solid rgba(214,69,69,.40);color:#f2a9a9}
	.azwc-fu-msg.is-ok{display:block;background:rgba(15,157,88,.14);border:1px solid rgba(15,157,88,.40);color:#4cc98a}

	.azwc-fu-days{display:flex;gap:7px;overflow-x:auto;padding-bottom:9px;margin-bottom:14px;color-scheme:dark;
		scrollbar-width:thin;-webkit-overflow-scrolling:touch}
	.azwc-fu-day{flex:0 0 auto;padding:9px 13px;border:1px solid rgba(255,255,255,.20);border-radius:10px;
		background:rgba(255,255,255,.05);font:inherit;font-size:13px;font-weight:700;color:#d6dce4;cursor:pointer;white-space:nowrap}
	.azwc-fu-day:hover{border-color:#e6b84d}
	.azwc-fu-day.is-on{background:#e6b84d;border-color:#e6b84d;color:#161208}
	.azwc-fu-slots{display:grid;grid-template-columns:repeat(auto-fill,minmax(94px,1fr));gap:7px;
		max-height:246px;overflow-y:auto;padding:2px}
	.azwc-fu-slot{padding:10px 6px;border:1px solid rgba(255,255,255,.20);border-radius:9px;
		background:rgba(255,255,255,.05);font:inherit;font-size:13.5px;font-weight:650;color:#ffffff;cursor:pointer;text-align:center}
	.azwc-fu-slot:hover{border-color:#e6b84d;background:rgba(230,184,77,.16)}
	.azwc-fu-slot.is-on{background:#e6b84d;border-color:#e6b84d;color:#161208;font-weight:800}
	.azwc-fu-picked{margin:14px 0 4px;padding:12px 14px;background:rgba(230,184,77,.12);border:1px solid rgba(230,184,77,.34);
		border-radius:9px;font-size:14px;color:#f5d47d}
	.azwc-fu-picked b{display:block;font-size:15px}
	.azwc-fu-back{background:none;border:0;padding:0;margin-bottom:12px;font:inherit;font-size:13px;
		color:#f5d47d;cursor:pointer;text-decoration:underline}
	.azwc-fu-spin{display:inline-block;width:13px;height:13px;margin-right:7px;vertical-align:-2px;
		border:2px solid rgba(22,18,8,.3);border-top-color:#161208;border-radius:50%;animation:azwcfuspin .7s linear infinite}
	@keyframes azwcfuspin{to{transform:rotate(360deg)}}
	@media(prefers-reduced-motion:reduce){.azwc-fu-spin{animation:none}}
	@media(max-width:600px){.azwc-fu-box{padding:26px 20px}}
</style>

<div class="azwc-fu-modal" id="azwc-fu-modal" role="dialog" aria-modal="true" aria-labelledby="azwc-fu-title" hidden>
	<div class="azwc-fu-box">
		<button type="button" class="azwc-fu-x" aria-label="Close">&times;</button>
		<div id="azwc-fu-body"></div>
	</div>
</div>

<script id="azwc-fu-js" data-noptimize="1">
(function () {
	'use strict';

	var REST  = <?php echo wp_json_encode( $rest ); ?>;
	var modal = document.getElementById('azwc-fu-modal');
	if (!modal) { return; }

	var box     = modal.querySelector('.azwc-fu-box');
	var body    = document.getElementById('azwc-fu-body');
	var opener  = null;   // Focus goes back here on close.
	var shownAt = 0;      // Feeds the `elapsed` anti-bot check.

	function esc(s) {
		return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
		});
	}

	/**
	 * Just the hostname.
	 *
	 * The audit reports its normalised URL ("https://example.com/"), which is
	 * right for the audit and wrong in a field labelled "Your website" — the
	 * server re-normalises anyway, so this is purely so it reads like something
	 * a person would have typed.
	 */
	function tidyDomain(value) {
		return String(value || '')
			.trim()
			.replace(/^https?:\/\//i, '')
			.replace(/\/+$/, '');
	}

	/** The domain under audit, wherever we can find it. */
	function currentDomain() {
		var root = document.getElementById('azwc-audit');
		if (root && root.dataset.auditedDomain) { return tidyDomain(root.dataset.auditedDomain); }
		try {
			var q = new URLSearchParams(window.location.search).get('target');
			if (q) { return tidyDomain(q); }
		} catch (e) { /* no URLSearchParams, fall through */ }
		var input = document.getElementById('azwc-audit-domain');
		return input ? tidyDomain(input.value) : '';
	}

	/* ---- modal plumbing ------------------------------------------------- */

	function openModal(render) {
		opener = document.activeElement;
		modal.hidden = false;
		modal.classList.add('is-open');
		document.body.style.overflow = 'hidden';
		shownAt = Date.now();
		render();
		var first = box.querySelector('input, button:not(.azwc-fu-x)');
		if (first) { first.focus(); }
	}

	function closeModal() {
		modal.classList.remove('is-open');
		modal.hidden = true;
		document.body.style.overflow = '';
		body.innerHTML = '';
		if (opener && opener.focus) { opener.focus(); }
	}

	modal.addEventListener('click', function (e) {
		if (e.target === modal || e.target.classList.contains('azwc-fu-x')) { closeModal(); }
	});
	document.addEventListener('keydown', function (e) {
		if (e.key === 'Escape' && modal.classList.contains('is-open')) { closeModal(); }
	});

	function msg(el, text, kind) {
		el.className = 'azwc-fu-msg is-' + kind;
		el.textContent = text;
	}

	function post(path, payload) {
		return fetch(REST + path, {
			method: 'POST',
			headers: { 'Content-Type': 'application/json' },
			body: JSON.stringify(payload)
		}).then(function (r) {
			return r.json().catch(function () { return {}; }).then(function (b) {
				return { ok: r.ok, body: b };
			});
		});
	}

	/** Shared name/email block. */
	function identityFields(withPhone) {
		return '<div class="azwc-fu-field"><label for="azwc-fu-name">Your name</label>'
			+ '<input id="azwc-fu-name" type="text" autocomplete="name" placeholder="What should we call you?"></div>'
			+ '<div class="azwc-fu-field"><label for="azwc-fu-email">Email address</label>'
			+ '<input id="azwc-fu-email" type="email" autocomplete="email" placeholder="you@company.com"></div>'
			+ (withPhone
				? '<div class="azwc-fu-field"><label for="azwc-fu-phone">Phone number</label>'
					+ '<input id="azwc-fu-phone" type="tel" autocomplete="tel" placeholder="Where should we call you?"></div>'
				: '')
			+ '<div class="azwc-fu-hp" aria-hidden="true">'
			+ '<label for="azwc-fu-hp">Leave this empty</label>'
			+ '<input id="azwc-fu-hp" type="text" tabindex="-1" autocomplete="off"></div>';
	}

	function readIdentity() {
		var g = function (id) { var el = document.getElementById(id); return el ? el.value.trim() : ''; };
		return {
			name: g('azwc-fu-name'),
			email: g('azwc-fu-email'),
			phone: g('azwc-fu-phone'),
			hp: g('azwc-fu-hp'),
			elapsed: Date.now() - shownAt
		};
	}

	/* ---- PDF by email --------------------------------------------------- */

	function renderPdfForm() {
		var domain = currentDomain();
		body.innerHTML = '<h4 id="azwc-fu-title">Email me this report</h4>'
			+ '<p class="azwc-fu-lede">We will send the full report for <b>' + esc(domain) + '</b> '
			+ 'as a PDF — every check, with the plain-English explanation under each one.</p>'
			+ identityFields(false)
			+ '<button type="button" class="azwc-fu-go" id="azwc-fu-send">Send me the report</button>'
			+ '<p class="azwc-fu-note">We will use this to send the report and to follow up once. '
			+ 'No list, no sequence, and we do not pass it on.</p>'
			+ '<div class="azwc-fu-msg" id="azwc-fu-msg" role="status" aria-live="polite"></div>';

		var go = document.getElementById('azwc-fu-send');
		var m  = document.getElementById('azwc-fu-msg');

		go.addEventListener('click', function () {
			var f = readIdentity();
			if (f.name.length < 2)      { return msg(m, 'Please tell us what to call you.', 'err'); }
			if (!/.+@.+\..+/.test(f.email)) { return msg(m, 'That email address does not look right.', 'err'); }

			go.disabled = true;
			go.innerHTML = '<span class="azwc-fu-spin"></span>Building your report…';
			m.className = 'azwc-fu-msg';

			post('/report', {
				domain: domain, name: f.name, email: f.email, hp: f.hp, elapsed: f.elapsed
			}).then(function (r) {
				if (r.ok && r.body.ok) {
					body.innerHTML = '<h4 id="azwc-fu-title">On its way</h4>'
						+ '<p>' + esc(r.body.message) + '</p>'
						+ '<p class="azwc-fu-note">Not there in a few minutes? Check your spam folder, '
						+ 'or call us on 480-818-5761 and we will send it again.</p>';
					return;
				}
				go.disabled = false;
				go.textContent = 'Send me the report';
				msg(m, (r.body && r.body.error) || 'Something went wrong. Please try again.', 'err');
			}).catch(function () {
				go.disabled = false;
				go.textContent = 'Send me the report';
				msg(m, 'We could not reach the server. Please try again.', 'err');
			});
		});
	}

	/* ---- booking -------------------------------------------------------- */

	var picked = null;
	var slotData = null;

	/**
	 * Arizona does not observe daylight saving, so for a good part of the year
	 * the visitor's clock disagrees with ours. Show both rather than letting
	 * somebody turn up an hour out.
	 */
	function localHint(gmt) {
		try {
			var here = Intl.DateTimeFormat().resolvedOptions().timeZone;
			if (here === 'America/Phoenix') { return ''; }
			var d = new Date(gmt.replace(' ', 'T') + 'Z');
			var t = d.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
			return ' — that is ' + t + ' where you are';
		} catch (e) { return ''; }
	}

	function renderBooking() {
		body.innerHTML = '<h4 id="azwc-fu-title">Book a free 30-minute call</h4>'
			+ '<p class="azwc-fu-lede">Before we speak we audit your site by hand — the parts a crawler '
			+ 'cannot judge — then walk you through what we would do first. No charge, no obligation.</p>'
			+ '<p class="azwc-fu-note" id="azwc-fu-loading">Loading available times…</p>';

		fetch(REST + '/slots').then(function (r) { return r.json(); }).then(function (data) {
			slotData = data;
			if (!data.days || !data.days.length) {
				body.innerHTML += '<p>We have no free slots in the next few weeks. '
					+ 'Call us on <b>480-818-5761</b> and we will find a time.</p>';
				return;
			}
			drawCalendar();
		}).catch(function () {
			var el = document.getElementById('azwc-fu-loading');
			if (el) { el.textContent = 'We could not load the calendar. Please call 480-818-5761.'; }
		});
	}

	function drawCalendar() {
		var days = slotData.days;
		body.innerHTML = '<h4 id="azwc-fu-title">Pick a time</h4>'
			+ '<p class="azwc-fu-lede">30 minutes, by phone. All times are '
			+ esc(slotData.tzlabel) + '.</p>'
			+ '<div class="azwc-fu-days" role="tablist"></div>'
			+ '<div class="azwc-fu-slots"></div>'
			+ '<div id="azwc-fu-after"></div>';

		var strip = body.querySelector('.azwc-fu-days');
		var grid  = body.querySelector('.azwc-fu-slots');

		days.forEach(function (day, i) {
			var b = document.createElement('button');
			b.type = 'button';
			b.className = 'azwc-fu-day' + (i === 0 ? ' is-on' : '');
			b.textContent = day.label;
			b.addEventListener('click', function () {
				strip.querySelectorAll('.azwc-fu-day').forEach(function (x) { x.classList.remove('is-on'); });
				b.classList.add('is-on');
				drawSlots(grid, day);
			});
			strip.appendChild(b);
		});

		drawSlots(grid, days[0]);
	}

	function drawSlots(grid, day) {
		grid.innerHTML = '';
		day.slots.forEach(function (slot) {
			var b = document.createElement('button');
			b.type = 'button';
			b.className = 'azwc-fu-slot';
			b.textContent = slot.label;
			b.addEventListener('click', function () {
				grid.querySelectorAll('.azwc-fu-slot').forEach(function (x) { x.classList.remove('is-on'); });
				b.classList.add('is-on');
				picked = { gmt: slot.gmt, label: day.long + ' at ' + slot.label };
				renderDetails();
			});
			grid.appendChild(b);
		});
	}

	function renderDetails() {
		var domain = currentDomain();
		shownAt = Date.now();

		body.innerHTML = '<button type="button" class="azwc-fu-back" id="azwc-fu-back">‹ Pick a different time</button>'
			+ '<h4 id="azwc-fu-title">Almost there</h4>'
			+ '<div class="azwc-fu-picked"><b>' + esc(picked.label) + '</b>'
			+ esc(slotData.tzlabel) + esc(localHint(picked.gmt)) + '</div>'
			+ '<div class="azwc-fu-field" style="margin-top:14px"><label for="azwc-fu-domain">Your website</label>'
			+ '<input id="azwc-fu-domain" type="text" value="' + esc(domain) + '" placeholder="yourdomain.com"></div>'
			+ identityFields(true)
			+ '<button type="button" class="azwc-fu-go" id="azwc-fu-book">Book this call</button>'
			+ '<p class="azwc-fu-note">We will email you a link to confirm. The slot is held until you click it — '
			+ 'we ask because the hand audit is real work and we would rather not do it into an empty room.</p>'
			+ '<div class="azwc-fu-msg" id="azwc-fu-msg" role="status" aria-live="polite"></div>';

		document.getElementById('azwc-fu-back').addEventListener('click', drawCalendar);

		var go = document.getElementById('azwc-fu-book');
		var m  = document.getElementById('azwc-fu-msg');

		go.addEventListener('click', function () {
			var f   = readIdentity();
			var dom = document.getElementById('azwc-fu-domain').value.trim();

			if (f.name.length < 2)          { return msg(m, 'Please tell us what to call you.', 'err'); }
			if (!/.+@.+\..+/.test(f.email)) { return msg(m, 'That email address does not look right.', 'err'); }
			if (!dom)                       { return msg(m, 'Which website is this about?', 'err'); }

			go.disabled = true;
			go.innerHTML = '<span class="azwc-fu-spin"></span>Booking…';
			m.className = 'azwc-fu-msg';

			post('/booking', {
				domain: dom, slot: picked.gmt, name: f.name, email: f.email,
				phone: f.phone, hp: f.hp, elapsed: f.elapsed
			}).then(function (r) {
				if (r.ok && r.body.ok) {
					body.innerHTML = '<h4 id="azwc-fu-title">Check your email</h4>'
						+ '<div class="azwc-fu-picked"><b>' + esc(r.body.slot) + '</b></div>'
						+ '<p style="margin-top:14px">' + esc(r.body.message) + '</p>'
						+ '<p class="azwc-fu-note">Nothing is on our calendar until you click that link, '
						+ 'so do have a look — including in spam, occasionally.</p>';
					return;
				}
				go.disabled = false;
				go.textContent = 'Book this call';
				msg(m, (r.body && r.body.error) || 'Something went wrong. Please try again.', 'err');
			}).catch(function () {
				go.disabled = false;
				go.textContent = 'Book this call';
				msg(m, 'We could not reach the server. Please try again.', 'err');
			});
		});
	}

	/* ---- the CTA itself ------------------------------------------------- */

	function mountCta(domain) {
		var cta = document.getElementById('azwc-followup-cta');
		if (!cta) { return; }

		var root = document.getElementById('azwc-audit');
		if (root && domain) { root.dataset.auditedDomain = domain; }

		cta.className = 'azwc-cta azwc-fu-ready';
		cta.innerHTML = '<h3>What would you like to do with this?</h3>'
			+ '<p>This report is automated: it reads what your page sends to a browser, which catches a great '
			+ 'deal but cannot tell you whether you are chasing the right search terms, or why a competitor '
			+ 'outranks you on the ones that pay. That part takes a person.</p>'
			+ '<div class="azwc-fu-cards">'
			+ '<button type="button" class="azwc-fu-card is-primary" data-fu="call">'
			+ '<b>Book a free 30-minute call</b>'
			+ '<span>We audit your site by hand first, then talk you through it. No charge.</span></button>'
			+ '<button type="button" class="azwc-fu-card" data-fu="pdf">'
			+ '<b>Email me this as a PDF</b>'
			+ '<span>The full report, with every finding explained in plain English.</span></button>'
			+ '</div>';

		cta.querySelectorAll('[data-fu]').forEach(function (b) {
			b.addEventListener('click', function () {
				openModal(b.dataset.fu === 'pdf' ? renderPdfForm : renderBooking);
			});
		});
	}

	document.addEventListener('azwc:rendered', function (e) {
		mountCta((e.detail && e.detail.domain) || '');
	});

	// Deep link from an email: /free-seo-audit/?azwc_call=book
	try {
		if (new URLSearchParams(window.location.search).get('azwc_call') === 'book') {
			openModal(renderBooking);
		}
	} catch (e) { /* older browser: the buttons still work */ }
}());
</script>
	<?php
}
