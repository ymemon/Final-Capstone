/* Everything IT — website chat widget. No dependencies. */
(function () {
  'use strict';

  var root = document.getElementById('eit-chat-root');
  if (!root || typeof EIT_CHAT === 'undefined') { return; }

  var NAME = EIT_CHAT.name || 'Niall';
  var key = null;
  var lastId = 0;
  var poller = null;
  var busy = false;
  var started = false;

  /* ---------- markup ---------- */

  root.innerHTML =
    '<button class="eitc-launch" type="button" aria-label="Open chat">' +
      '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' +
      '<path d="M21 11.5a8.4 8.4 0 0 1-9 8.4 8.9 8.9 0 0 1-4-.9L3 21l1.9-4.6A8.4 8.4 0 0 1 4 11.5a8.4 8.4 0 0 1 9-8.4 8.4 8.4 0 0 1 8 8.4z"/></svg>' +
      '<span>Chat with us</span>' +
    '</button>' +
    '<div class="eitc-panel" hidden role="dialog" aria-label="Chat with Everything IT">' +
      '<div class="eitc-head">' +
        '<span class="eitc-dot" aria-hidden="true"></span>' +
        '<span class="eitc-who"><b>' + esc(NAME) + '</b><span>Everything IT</span></span>' +
        '<button class="eitc-x" type="button" aria-label="Close chat">&times;</button>' +
      '</div>' +
      '<div class="eitc-log" role="log" aria-live="polite"></div>' +
      '<div class="eitc-lead" hidden>' +
        '<p>Leave your details and the team will come back to you.</p>' +
        '<p class="eitc-err" hidden></p>' +
        '<input type="text" class="eitc-f-name" placeholder="Your name" autocomplete="name">' +
        '<div class="eitc-lead-row">' +
          '<input type="email" class="eitc-f-email" placeholder="Email" autocomplete="email">' +
          '<input type="tel" class="eitc-f-phone" placeholder="Phone" autocomplete="tel">' +
        '</div>' +
        '<button type="button" class="eitc-f-send">Ask for a callback</button>' +
        '<button type="button" class="eitc-skip">No thanks, keep chatting</button>' +
      '</div>' +
      '<form class="eitc-form">' +
        '<textarea rows="1" placeholder="Ask a question…" aria-label="Your message"></textarea>' +
        '<button type="submit" aria-label="Send">' +
          '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' +
          '<path d="M22 2 11 13M22 2l-7 20-4-9-9-4 20-7z"/></svg>' +
        '</button>' +
      '</form>' +
      '<div class="eitc-foot">Everything IT &middot; Dublin</div>' +
    '</div>';

  var launch = root.querySelector('.eitc-launch');
  var panel  = root.querySelector('.eitc-panel');
  var log    = root.querySelector('.eitc-log');
  var form   = root.querySelector('.eitc-form');
  var box    = form.querySelector('textarea');
  var sendBt = form.querySelector('button');
  var lead   = root.querySelector('.eitc-lead');
  var closeB = root.querySelector('.eitc-x');

  /* ---------- helpers ---------- */

  function esc(s) {
    return String(s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }

  // Escape first, then linkify — never the other way round.
  function withLinks(s) {
    return esc(s).replace(/(https?:\/\/[^\s<]+[^\s<.,;:)\]}'"])/g, function (u) {
      return '<a href="' + u + '" target="_blank" rel="noopener nofollow">' + u + '</a>';
    });
  }

  function add(kind, text) {
    var el = document.createElement('div');
    if (kind === 'staff') {
      el.className = 'eitc-msg eitc-staff';
      el.innerHTML = '<b>Everything IT team</b>' + withLinks(text);
    } else if (kind === 'note') {
      el.className = 'eitc-note';
      el.textContent = text;
    } else {
      el.className = 'eitc-msg ' + (kind === 'you' ? 'eitc-you' : 'eitc-bot');
      el.innerHTML = withLinks(text);
    }
    log.appendChild(el);
    log.scrollTop = log.scrollHeight;
    return el;
  }

  function typing(on) {
    var t = log.querySelector('.eitc-typing');
    if (on && !t) {
      t = document.createElement('div');
      t.className = 'eitc-typing';
      t.innerHTML = '<i></i><i></i><i></i>';
      log.appendChild(t);
      log.scrollTop = log.scrollHeight;
    } else if (!on && t) {
      t.remove();
    }
  }

  function api(path, opts) {
    opts = opts || {};
    var url = EIT_CHAT.root + path;
    var init = { method: opts.method || 'GET', headers: { 'Content-Type': 'application/json' } };
    if (opts.body) { init.body = JSON.stringify(opts.body); }
    if (opts.query) {
      var qs = Object.keys(opts.query).map(function (k) {
        return encodeURIComponent(k) + '=' + encodeURIComponent(opts.query[k]);
      }).join('&');
      url += (url.indexOf('?') > -1 ? '&' : '?') + qs;
    }
    return fetch(url, init).then(function (r) {
      return r.json().then(function (j) {
        if (!r.ok) { throw new Error((j && j.message) || 'Request failed'); }
        return j;
      });
    });
  }

  /* ---------- flow ---------- */

  function open() {
    panel.hidden = false;
    launch.hidden = true;
    box.focus();
    if (!started) { start(); }
  }

  function close() {
    panel.hidden = true;
    launch.hidden = false;
    stopPolling();
  }

  function start() {
    started = true;
    api('/start', {
      method: 'POST',
      body: { page_url: EIT_CHAT.page, referrer: EIT_CHAT.ref }
    }).then(function (r) {
      key = r.key;
      add('bot', r.greeting);
      startPolling();
    }).catch(function () {
      add('note', 'The chat is unavailable right now. Please use the contact page and we will come straight back to you.');
      form.hidden = true;
    });
  }

  function send(text) {
    if (busy || !key) { return; }
    busy = true;
    sendBt.disabled = true;
    add('you', text);
    typing(true);

    api('/message', { method: 'POST', body: { key: key, text: text } })
      .then(function (r) {
        typing(false);
        if (r.reply) { add('bot', r.reply); }
        if (r.mode === 'staff' && !r.reply) {
          add('note', 'You are now talking to the team.');
        }
        if (r.handoff) { showLead(); }
      })
      .catch(function (e) {
        typing(false);
        add('note', e.message || 'Something went wrong. Please try again.');
      })
      .then(function () {
        busy = false;
        sendBt.disabled = false;
        box.focus();
      });
  }

  function showLead() {
    if (lead.dataset.done === '1') { return; }
    lead.hidden = false;
  }

  /* ---------- staff messages ---------- */

  function startPolling() {
    stopPolling();
    poller = setInterval(function () {
      if (!key || document.hidden) { return; }
      api('/poll', { query: { key: key, after: lastId } })
        .then(function (r) {
          (r.messages || []).forEach(function (m) {
            if (m.id > lastId) { lastId = m.id; }
            add('staff', m.body);
          });
        })
        .catch(function () { /* transient — keep polling */ });
    }, 5000);
  }

  function stopPolling() {
    if (poller) { clearInterval(poller); poller = null; }
  }

  /* ---------- events ---------- */

  launch.addEventListener('click', open);
  closeB.addEventListener('click', close);

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    var t = box.value.trim();
    if (!t) { return; }
    box.value = '';
    box.style.height = 'auto';
    send(t);
  });

  box.addEventListener('input', function () {
    box.style.height = 'auto';
    box.style.height = Math.min(box.scrollHeight, 96) + 'px';
  });

  box.addEventListener('keydown', function (e) {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      form.dispatchEvent(new Event('submit', { cancelable: true }));
    }
  });

  root.querySelector('.eitc-skip').addEventListener('click', function () {
    lead.hidden = true;
  });

  root.querySelector('.eitc-f-send').addEventListener('click', function () {
    var err = lead.querySelector('.eitc-err');
    var payload = {
      key: key,
      name: lead.querySelector('.eitc-f-name').value.trim(),
      email: lead.querySelector('.eitc-f-email').value.trim(),
      phone: lead.querySelector('.eitc-f-phone').value.trim(),
      type: 'callback'
    };
    if (!payload.email && !payload.phone) {
      err.textContent = 'Please add an email address or a phone number.';
      err.hidden = false;
      return;
    }
    err.hidden = true;
    api('/lead', { method: 'POST', body: payload })
      .then(function (r) {
        lead.dataset.done = '1';
        lead.hidden = true;
        add('note', r.message);
      })
      .catch(function (e) {
        err.textContent = e.message || 'Could not send that. Please try again.';
        err.hidden = false;
      });
  });

  window.addEventListener('beforeunload', function () {
    if (key && navigator.sendBeacon) {
      navigator.sendBeacon(EIT_CHAT.root + '/end',
        new Blob([JSON.stringify({ key: key })], { type: 'application/json' }));
    }
  });
})();
