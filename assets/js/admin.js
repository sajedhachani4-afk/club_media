/* =====================================================================
   CLUB MEDIA — ISET GAFSA  |  Administration
   Menu mobile, confirmations, anti double-envoi, aperçus d'images,
   bascule YouTube / fichier, scanner de tickets (caméra + saisie manuelle).
   ===================================================================== */
(function () {
  'use strict';
  var $ = function (s, c) { return (c || document).querySelector(s); };
  var $$ = function (s, c) { return Array.prototype.slice.call((c || document).querySelectorAll(s)); };

  if (window.lucide && window.lucide.createIcons) { try { window.lucide.createIcons(); } catch (e) {} }

  /* ---------- Menu mobile ---------- */
  var btn = $('.admin-menu-btn'), side = $('#admin-sidebar');
  if (btn && side) {
    var set = function (open) {
      side.classList.toggle('is-open', open);
      btn.setAttribute('aria-expanded', open ? 'true' : 'false');
      document.body.classList.toggle('admin-nav-open', open);
    };
    btn.addEventListener('click', function () { set(!side.classList.contains('is-open')); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') set(false); });
    $$('a', side).forEach(function (a) { a.addEventListener('click', function () { set(false); }); });
  }

  /* ---------- Confirmation avant action destructive ---------- */
  $$('form[data-confirm]').forEach(function (f) {
    f.addEventListener('submit', function (e) { if (!window.confirm(f.getAttribute('data-confirm'))) e.preventDefault(); });
  });
  $$('form[data-once]').forEach(function (f) {
    f.addEventListener('submit', function (e) {
      if (e.defaultPrevented) return;
      if (f.dataset.sent === '1') { e.preventDefault(); return; }
      f.dataset.sent = '1';
      $$('button[type=submit]', f).forEach(function (b) { setTimeout(function () { b.disabled = true; }, 0); });
    });
  });

  /* ---------- Aperçu des images choisies ---------- */
  $$('input[type=file][data-preview]').forEach(function (input) {
    var target = document.getElementById(input.getAttribute('data-preview'));
    if (!target) return;
    input.addEventListener('change', function () {
      target.innerHTML = '';
      Array.prototype.slice.call(input.files || []).slice(0, 24).forEach(function (file) {
        if (!/^image\//.test(file.type)) return;
        var img = document.createElement('img');
        img.alt = file.name;
        img.src = URL.createObjectURL(file);
        img.onload = function () { URL.revokeObjectURL(img.src); };
        target.appendChild(img);
      });
    });
  });

  /* ---------- Vidéo : fichier ou YouTube ---------- */
  var source = $('select[data-source-toggle]');
  if (source) {
    var apply = function () {
      $$('[data-source]').forEach(function (el) {
        var show = el.getAttribute('data-source') === source.value;
        el.hidden = !show;
        $$('input', el).forEach(function (i) { i.disabled = !show; });
      });
    };
    source.addEventListener('change', apply);
    apply();
  }

  /* ---------- Scanner de tickets ---------- */
  var scanner = $('#scanner');
  if (!scanner) return;

  var csrf = scanner.getAttribute('data-csrf');
  var endpoint = scanner.getAttribute('data-endpoint');
  var video = $('video', scanner), msg = $('.scanner__msg', scanner);
  var result = $('#scan-result');
  var manual = $('#manual-form'), manualInput = $('#manual-input');
  var startBtn = $('#scan-start'), stopBtn = $('#scan-stop');
  var stream = null, rafId = null, busy = false, lastToken = '', lastAt = 0;
  var canvas = document.createElement('canvas'), ctx = canvas.getContext('2d', { willReadFrequently: true });

  function post(action, value) {
    var body = new URLSearchParams();
    body.set('action', action);
    body.set('value', value);
    return fetch(endpoint, {
      method: 'POST', credentials: 'same-origin',
      headers: { 'X-Requested-With': 'fetch', 'X-CSRF-Token': csrf, 'Content-Type': 'application/x-www-form-urlencoded' },
      body: body.toString()
    }).then(function (r) { return r.json(); });
  }

  function el(tag, text, cls) { var n = document.createElement(tag); if (text !== undefined) n.textContent = text; if (cls) n.className = cls; return n; }

  function render(data) {
    result.innerHTML = '';
    result.setAttribute('data-state', data.state || 'invalid');
    result.appendChild(el('h3', data.label || 'Erreur', 'scan-result__title'));
    if (data.ticket) {
      var dl = el('dl');
      [['Ticket', data.ticket.number], ['Participant', data.ticket.holder], ['Événement', data.ticket.event],
       ['Date', data.ticket.when], ['Lieu', data.ticket.location]].forEach(function (p) {
        dl.appendChild(el('dt', p[0])); dl.appendChild(el('dd', p[1]));
      });
      if (data.ticket.used_at) { dl.appendChild(el('dt', 'Utilisé le')); dl.appendChild(el('dd', data.ticket.used_at)); }
      result.appendChild(dl);
    }
    if (data.message) result.appendChild(el('p', data.message));
    if (data.state === 'valid' && data.ticket) {
      var b = el('button', 'Autoriser l\'entrée', 'btn btn-primary');
      b.type = 'button';
      b.addEventListener('click', function () {
        b.disabled = true;
        post('checkin', data.ticket.token).then(render).catch(function () { render({ state: 'invalid', label: 'Erreur réseau', message: 'Réessayez.' }); });
      });
      result.appendChild(b);
    }
    result.focus();
  }

  function check(value) {
    if (busy) return;
    busy = true;
    post('check', value).then(render).catch(function () {
      render({ state: 'invalid', label: 'Erreur réseau', message: 'Impossible de contacter le serveur.' });
    }).then(function () { busy = false; });
  }

  manual.addEventListener('submit', function (e) {
    e.preventDefault();
    var v = manualInput.value.trim();
    if (v) { check(v); manualInput.select(); }
  });

  function loop() {
    if (!stream) return;
    if (video.readyState === video.HAVE_ENOUGH_DATA && window.jsQR) {
      canvas.width = video.videoWidth; canvas.height = video.videoHeight;
      ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
      var img = ctx.getImageData(0, 0, canvas.width, canvas.height);
      var code = window.jsQR(img.data, img.width, img.height, { inversionAttempts: 'dontInvert' });
      if (code && code.data) {
        var now = Date.now();
        if (code.data !== lastToken || now - lastAt > 4000) { lastToken = code.data; lastAt = now; check(code.data); }
      }
    }
    rafId = window.requestAnimationFrame(loop);
  }

  function stop() {
    if (rafId) window.cancelAnimationFrame(rafId);
    rafId = null;
    if (stream) { stream.getTracks().forEach(function (t) { t.stop(); }); stream = null; }
    video.srcObject = null;
    msg.hidden = false; msg.textContent = 'Caméra arrêtée.';
    startBtn.hidden = false; stopBtn.hidden = true;
  }

  startBtn.addEventListener('click', function () {
    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
      msg.hidden = false;
      msg.textContent = 'Caméra indisponible : utilisez localhost ou HTTPS, ou saisissez le ticket à la main.';
      return;
    }
    if (!window.jsQR) { msg.hidden = false; msg.textContent = 'Bibliothèque de lecture QR non chargée (connexion requise). Saisie manuelle possible.'; return; }
    navigator.mediaDevices.getUserMedia({ video: { facingMode: { ideal: 'environment' } }, audio: false }).then(function (s) {
      stream = s; video.srcObject = s; video.setAttribute('playsinline', 'true');
      return video.play();
    }).then(function () {
      msg.hidden = true; startBtn.hidden = true; stopBtn.hidden = false; loop();
    }).catch(function () {
      msg.hidden = false; msg.textContent = 'Accès à la caméra refusé ou impossible. Autorisez la caméra ou utilisez la saisie manuelle.';
    });
  });
  stopBtn.addEventListener('click', stop);
  window.addEventListener('pagehide', stop);
})();
