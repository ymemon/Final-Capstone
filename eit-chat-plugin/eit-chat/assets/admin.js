/* Everything IT chat — staff monitor.
   Polls for open conversations and raises a desktop notification the moment a
   new one appears, so whoever is on duty can watch and step in. */
(function () {
  'use strict';

  if (typeof EIT_CHAT_ADMIN === 'undefined') { return; }

  var liveBox   = document.getElementById('eit-chat-live');
  var threadBox = document.getElementById('eit-chat-thread');
  var replyBar  = document.getElementById('eit-chat-replybar');
  var replyTxt  = document.getElementById('eit-chat-reply');
  var sendBtn   = document.getElementById('eit-chat-send');
  var rebuild   = document.getElementById('eit-chat-rebuild');
  var rebuildOut= document.getElementById('eit-chat-rebuild-out');

  var openId = EIT_CHAT_ADMIN.open || 0;
  var seen = {};
  var firstPass = true;

  function api(path, opts) {
    opts = opts || {};
    var url = EIT_CHAT_ADMIN.root + path;
    if (opts.query) {
      url += (url.indexOf('?') > -1 ? '&' : '?') + Object.keys(opts.query).map(function (k) {
        return encodeURIComponent(k) + '=' + encodeURIComponent(opts.query[k]);
      }).join('&');
    }
    var init = {
      method: opts.method || 'GET',
      headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': EIT_CHAT_ADMIN.nonce },
      credentials: 'same-origin'
    };
    if (opts.body) { init.body = JSON.stringify(opts.body); }
    return fetch(url, init).then(function (r) {
      return r.json().then(function (j) {
        if (!r.ok) { throw new Error((j && j.message) || 'Request failed'); }
        return j;
      });
    });
  }

  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }

  /* ---------- desktop alert ---------- */

  if ('Notification' in window && Notification.permission === 'default') {
    Notification.requestPermission();
  }

  function alertNew(s) {
    if (!('Notification' in window) || Notification.permission !== 'granted') { return; }
    var n = new Notification('Someone is chatting on the website', {
      body: (s.page || 'Website chat') + ' — ' + s.msgs + ' messages',
      tag: 'eit-chat-' + s.id
    });
    n.onclick = function () { window.focus(); loadThread(s.id); n.close(); };
  }

  /* ---------- live list ---------- */

  function renderLive(sessions) {
    if (!sessions.length) {
      liveBox.innerHTML = '<p class="eit-chat-empty">Nobody is chatting at the moment.</p>';
      return;
    }
    liveBox.innerHTML = sessions.map(function (s) {
      var contact = [s.name, s.email, s.phone].filter(Boolean).join(' · ');
      return '<div class="eitc-live-card' + (s.mode === 'staff' ? ' is-staff' : '') + '">' +
        '<span class="eitc-pill' + (s.mode === 'staff' ? ' is-staff' : '') + '">' +
          (s.mode === 'staff' ? 'You' : 'Assistant') + '</span>' +
        '<span class="eitc-live-meta"><b>' + s.msgs + ' messages</b>' +
          (contact ? ' — ' + esc(contact) : '') +
          '<span>' + esc(s.page || '') + '</span></span>' +
        '<button class="button button-small eit-chat-open" data-id="' + s.id + '">Open</button>' +
      '</div>';
    }).join('');
  }

  function pollLive() {
    api('/live').then(function (r) {
      renderLive(r.sessions || []);
      (r.sessions || []).forEach(function (s) {
        if (!seen[s.id]) {
          seen[s.id] = true;
          if (!firstPass) { alertNew(s); }
        }
      });
      firstPass = false;
      if (openId) { refreshThread(openId, true); }
    }).catch(function () { /* transient */ });
  }

  /* ---------- thread ---------- */

  function renderThread(messages) {
    if (!messages.length) {
      threadBox.innerHTML = '<p class="eit-chat-empty">No messages.</p>';
      return;
    }
    threadBox.innerHTML = messages.map(function (m) {
      if (m.role === 'system') {
        return '<div class="eitc-t eitc-t-system">' + esc(m.body) + '</div>';
      }
      var who = m.role === 'visitor' ? 'Visitor' : (m.role === 'staff' ? 'You' : 'Assistant');
      return '<div class="eitc-t eitc-t-' + esc(m.role) + '">' +
             '<span class="eitc-t-who">' + who + '</span>' + esc(m.body) + '</div>';
    }).join('');
    threadBox.scrollTop = threadBox.scrollHeight;
  }

  function loadThread(id) {
    openId = id;
    replyBar.hidden = false;
    refreshThread(id, false);
  }

  var lastCount = -1;
  function refreshThread(id, quiet) {
    api('/thread', { query: { id: id } }).then(function (r) {
      var msgs = r.messages || [];
      if (quiet && msgs.length === lastCount) { return; }
      lastCount = msgs.length;
      renderThread(msgs);
    }).catch(function () { /* transient */ });
  }

  document.addEventListener('click', function (e) {
    var b = e.target.closest('.eit-chat-open');
    if (b) {
      e.preventDefault();
      lastCount = -1;
      loadThread(parseInt(b.dataset.id, 10));
    }
  });

  if (sendBtn) {
    sendBtn.addEventListener('click', function () {
      var t = replyTxt.value.trim();
      if (!t || !openId) { return; }
      sendBtn.disabled = true;
      api('/reply', { method: 'POST', body: { id: openId, text: t } })
        .then(function () {
          replyTxt.value = '';
          lastCount = -1;
          refreshThread(openId, false);
        })
        .catch(function (err) { window.alert(err.message); })
        .then(function () { sendBtn.disabled = false; });
    });
  }

  if (rebuild) {
    rebuild.addEventListener('click', function (e) {
      e.preventDefault();
      rebuild.disabled = true;
      rebuildOut.textContent = ' Building…';
      api('/rebuild-kb', { method: 'POST' })
        .then(function (r) { rebuildOut.textContent = ' Indexed ' + r.pages + ' pages.'; })
        .catch(function (err) { rebuildOut.textContent = ' ' + err.message; })
        .then(function () { rebuild.disabled = false; });
    });
  }

  pollLive();
  setInterval(pollLive, 6000);
  if (openId) { loadThread(openId); }
})();
