<?php
/**
 * Plugin Name: AZW SEO Quick Start Bar
 * Description: Puts a working audit input at the very top of the Free SEO Check page.
 * Version: 1.0.0
 * Author: AZWebCorp
 *
 * ---------------------------------------------------------------------------
 * WHY
 *
 * Visitors were landing on /free-seo-audit/ and clicking the breadcrumb
 * ("Arizona Web Development & Design | AZ Web Corp > Free SEO Audit"), which
 * sent them back to the home page. The real input sits well below the fold,
 * so the first interactive thing on screen was a link out of the page.
 *
 * This puts a real, working input immediately under the breadcrumb. It does not
 * duplicate the audit engine - there is exactly one tool on the page, and this
 * bar hands its value to it, submits it, and scrolls the visitor to the report.
 * If the tool has not mounted yet (its own script relocates it into the page
 * design), the bar keeps the typed value and retries, so an early submit is
 * never silently dropped.
 *
 * NOTE ON THE PLACEHOLDER: it is deliberately "yourdomain.com", not
 * "yourbusiness.com". The page design's moveExistingAuditTool() locates the
 * audit form by looking for an input whose placeholder contains
 * "yourbusiness.com" and moves that form into #azseo-tool-mount. This bar sits
 * earlier in the DOM, so sharing the placeholder made the mover hijack THIS
 * form and leave the real tool behind - the bar rendered with no input at all.
 * ---------------------------------------------------------------------------
 */

defined('ABSPATH') || exit;

/** Only the audit page. */
function azw_quickbar_is_target() {
    if (is_admin() || !is_singular('page')) {
        return false;
    }
    $p = get_post();
    return $p && 'free-seo-audit' === $p->post_name;
}

function azw_quickbar_markup() {
    ob_start();
    ?>
<section class="azw-qb" aria-labelledby="azw-qb-title">
  <div class="azw-qb-inner">
    <p class="azw-qb-eyebrow">Free website SEO audit</p>
    <h2 class="azw-qb-title" id="azw-qb-title">Check your website now</h2>
    <p class="azw-qb-sub">Enter any public page address. Real measured results in about 30 seconds &mdash; no email, nothing stored.</p>

    <form class="azw-qb-form" novalidate>
      <label class="screen-reader-text" for="azw-qb-input">Website address to audit</label>
      <input id="azw-qb-input" type="text" inputmode="url" autocomplete="url"
             placeholder="yourdomain.com" aria-describedby="azw-qb-note">
      <button type="submit">Run my free SEO audit</button>
    </form>
    <p class="azw-qb-note" id="azw-qb-note" role="status" aria-live="polite"></p>
  </div>
</section>
    <?php
    return ob_get_clean();
}

function azw_quickbar_styles() {
    return '<style id="azw-qb-css" data-noptimize="1">'
        . '.azw-qb{background:linear-gradient(135deg,#050608,#111823 60%,#30240a);padding:34px 20px 38px}'
        . '.azw-qb-inner{max-width:1080px;margin:0 auto}'
        . '.azw-qb-eyebrow{margin:0 0 6px;font-size:12px;font-weight:800;letter-spacing:.14em;text-transform:uppercase;color:#f5d47d}'
        . '.azw-qb-title{margin:0 0 8px;font-size:clamp(26px,3.4vw,38px);line-height:1.12;color:#fff;letter-spacing:-.02em}'
        . '.azw-qb-sub{margin:0 0 18px;font-size:14.5px;line-height:1.55;color:#d6dce4;max-width:56ch}'
        . '.azw-qb-form{display:flex;gap:10px;flex-wrap:wrap;align-items:stretch}'
        . '.azw-qb-form input{flex:1 1 300px;min-height:56px;padding:0 18px;font-size:16px;color:#e6edf3;'
        . 'background:#161d26;border:1px solid rgba(230,184,77,.45);border-radius:11px}'
        . '.azw-qb-form input::placeholder{color:#9fb0c0;opacity:1}'
        . '.azw-qb-form input:focus{outline:2px solid #e6b84d;outline-offset:1px;border-color:#e6b84d;background:#1b2430}'
        . '.azw-qb-form button{min-height:56px;padding:0 26px;font-size:15px;font-weight:800;cursor:pointer;'
        . 'color:#161208;background:#e6b84d;border:1px solid #e6b84d;border-radius:11px}'
        . '.azw-qb-form button:hover,.azw-qb-form button:focus{background:#f5d47d;border-color:#f5d47d}'
        . '.azw-qb-form button[disabled]{opacity:.6;cursor:progress}'
        . '.azw-qb-note{margin:10px 0 0;min-height:18px;font-size:13px;color:#f5d47d}'
        . '@media(max-width:640px){.azw-qb{padding:26px 16px 30px}.azw-qb-form button{width:100%}}'
        . '</style>';
}

function azw_quickbar_script() {
    return <<<'JS'
<script id="azw-qb-js" data-noptimize="1">
(function () {
  var form  = document.querySelector('.azw-qb-form');
  if (!form) { return; }
  var input = document.getElementById('azw-qb-input');
  var btn   = form.querySelector('button');
  var note  = document.getElementById('azw-qb-note');

  /* The audit tool is relocated into the page design by that design's own
     script, so it may not be in place the instant someone submits. Poll
     briefly rather than dropping the request. */
  function withTool(cb, tries) {
    tries = tries === undefined ? 24 : tries;
    var target = document.getElementById('azwc-audit-domain');
    var tform  = target ? target.closest('form') : null;
    if (target && tform) { cb(target, tform); return; }
    if (tries <= 0) {
      note.textContent = 'The audit tool is still loading. Give it a moment and try again.';
      btn.disabled = false;
      return;
    }
    window.setTimeout(function () { withTool(cb, tries - 1); }, 250);
  }

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    var v = (input.value || '').trim();
    if (!v) { input.focus(); return; }
    btn.disabled = true;
    note.textContent = 'Starting the audit…';

    withTool(function (target, tform) {
      target.value = v;
      // Fire the tool's own submit path so all its validation and staging run.
      if (typeof tform.requestSubmit === 'function') {
        tform.requestSubmit();
      } else {
        tform.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
      }
      var results = document.querySelector('#azwc-audit .azwc-progress')
                 || document.querySelector('#azwc-audit');
      if (results && results.scrollIntoView) {
        var y = results.getBoundingClientRect().top + window.pageYOffset - 110;
        window.scrollTo({ top: y > 0 ? y : 0, behavior: 'smooth' });
      }
      note.textContent = 'Running — your report appears below.';
      btn.disabled = false;
    });
  });
})();
</script>
JS;
}

/**
 * Prepend, at a priority past the content-replacing filters that run at 999 on
 * some pages, so the bar cannot be discarded.
 */
add_filter('the_content', static function ($content) {
    if (!azw_quickbar_is_target() || !in_the_loop() || !is_main_query()) {
        return $content;
    }
    if (strpos($content, 'azw-qb-form') !== false) {
        return $content;
    }
    return azw_quickbar_styles() . azw_quickbar_markup() . $content . azw_quickbar_script();
}, 1000);
