/* =====================================================================
   CLUB MEDIA — ISET GAFSA  |  Interactions du site public
   Navigation, lightbox, lecteur vidéo, QR des tickets, quantité, Swiper.
   Aucune dépendance obligatoire : si un CDN est indisponible, le site reste utilisable.
   ===================================================================== */
(function () {
  'use strict';

  var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var $ = function (sel, ctx) { return (ctx || document).querySelector(sel); };
  var $$ = function (sel, ctx) { return Array.prototype.slice.call((ctx || document).querySelectorAll(sel)); };

  /* ---------- Icônes Lucide ---------- */
  function renderIcons() {
    if (window.lucide && typeof window.lucide.createIcons === 'function') {
      try { window.lucide.createIcons(); } catch (e) { /* icône inconnue : sans gravité */ }
    }
  }
  renderIcons();

  /* ---------- AOS (révélations simples) ---------- */
  if (typeof window.AOS !== 'undefined') {
    window.AOS.init({ duration: 700, once: true, offset: 60, easing: 'ease-out-cubic', disable: reduceMotion });
  } else {
    // CDN indisponible : on garantit que le contenu reste visible.
    var st = document.createElement('style');
    st.textContent = '[data-aos]{opacity:1!important;transform:none!important}';
    document.head.appendChild(st);
  }

  /* ---------- En-tête : état au défilement ---------- */
  var header = $('#site-header');
  if (header) {
    var onScroll = function () { header.classList.toggle('is-scrolled', window.scrollY > 12); };
    onScroll();
    window.addEventListener('scroll', onScroll, { passive: true });
  }

  /* ---------- Menu mobile ---------- */
  var navToggle = $('.nav-toggle');
  var nav = $('#primary-nav');
  function setNav(open) {
    if (!navToggle || !nav) return;
    nav.classList.toggle('is-open', open);
    navToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    navToggle.setAttribute('aria-label', open ? 'Fermer le menu' : 'Ouvrir le menu');
    document.body.classList.toggle('nav-open', open);
  }
  if (navToggle && nav) {
    navToggle.addEventListener('click', function () { setNav(!nav.classList.contains('is-open')); });
    $$('a', nav).forEach(function (a) { a.addEventListener('click', function () { setNav(false); }); });
    window.addEventListener('resize', function () { if (window.innerWidth > 960) setNav(false); });
  }

  /* ---------- Menu déroulant « Galerie » ---------- */
  $$('.has-dropdown').forEach(function (li) {
    var btn = $('.dropdown-toggle', li);
    if (!btn) return;
    btn.addEventListener('click', function (e) {
      e.stopPropagation();
      var open = !li.classList.contains('is-open');
      li.classList.toggle('is-open', open);
      btn.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
  });
  document.addEventListener('click', function (e) {
    $$('.has-dropdown.is-open').forEach(function (li) {
      if (!li.contains(e.target)) { li.classList.remove('is-open'); var b = $('.dropdown-toggle', li); if (b) b.setAttribute('aria-expanded', 'false'); }
    });
  });

  /* ---------- Overlay générique (lightbox / lecteur vidéo) ---------- */
  var overlay = null, overlayBody = null, lastFocus = null, onOverlayClose = null, onOverlayKey = null;

  function buildOverlay() {
    overlay = document.createElement('div');
    overlay.className = 'overlay';
    overlay.setAttribute('role', 'dialog');
    overlay.setAttribute('aria-modal', 'true');
    overlay.innerHTML = '<button type="button" class="overlay__close" aria-label="Fermer">✕</button>' +
      '<div class="overlay__body"></div>';
    document.body.appendChild(overlay);
    overlayBody = $('.overlay__body', overlay);
    $('.overlay__close', overlay).addEventListener('click', closeOverlay);
    overlay.addEventListener('click', function (e) { if (e.target === overlay) closeOverlay(); });
    document.addEventListener('keydown', function (e) {
      if (!overlay.classList.contains('is-open')) return;
      if (e.key === 'Escape') { closeOverlay(); return; }
      if (onOverlayKey) onOverlayKey(e);
      if (e.key === 'Tab') {
        var f = $$('button, a[href], [tabindex]:not([tabindex="-1"])', overlay).filter(function (n) { return !n.disabled && n.offsetParent !== null; });
        if (!f.length) return;
        var first = f[0], last = f[f.length - 1];
        if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
        else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
      }
    });
  }
  function openOverlay(label) {
    if (!overlay) buildOverlay();
    lastFocus = document.activeElement;
    overlay.setAttribute('aria-label', label || 'Aperçu');
    overlay.classList.add('is-open');
    document.body.style.overflow = 'hidden';
    $('.overlay__close', overlay).focus();
  }
  function closeOverlay() {
    if (!overlay) return;
    overlay.classList.remove('is-open');
    document.body.style.overflow = '';
    if (onOverlayClose) { onOverlayClose(); onOverlayClose = null; }
    onOverlayKey = null;
    overlayBody.innerHTML = '';
    $$('.overlay__nav', overlay).forEach(function (n) { n.remove(); });
    if (lastFocus && lastFocus.focus) lastFocus.focus();
  }

  /* ---------- Lightbox photos ---------- */
  function openLightbox(group, startIndex) {
    var items = $$('[data-lightbox="' + group + '"]');
    if (!items.length) return;
    var idx = startIndex;
    openOverlay('Galerie photo');
    overlayBody.innerHTML = '<figure class="lightbox__figure"><img alt=""><figcaption></figcaption><div class="lightbox__count" aria-live="polite"></div></figure>';
    var img = $('img', overlayBody), cap = $('figcaption', overlayBody), cnt = $('.lightbox__count', overlayBody);

    function show(i) {
      idx = (i + items.length) % items.length;
      var it = items[idx];
      img.src = it.getAttribute('data-src');
      img.alt = it.getAttribute('data-caption') || '';
      cap.textContent = it.getAttribute('data-caption') || '';
      cnt.textContent = (idx + 1) + ' / ' + items.length;
    }
    if (items.length > 1) {
      ['prev', 'next'].forEach(function (dir) {
        var b = document.createElement('button');
        b.type = 'button';
        b.className = 'overlay__nav overlay__nav--' + dir;
        b.setAttribute('aria-label', dir === 'prev' ? 'Photo précédente' : 'Photo suivante');
        b.textContent = dir === 'prev' ? '‹' : '›';
        b.addEventListener('click', function () { show(idx + (dir === 'prev' ? -1 : 1)); });
        overlay.appendChild(b);
      });
      onOverlayKey = function (e) {
        if (e.key === 'ArrowLeft') show(idx - 1);
        if (e.key === 'ArrowRight') show(idx + 1);
      };
      // Balayage tactile
      var sx = null;
      overlayBody.addEventListener('touchstart', function (e) { sx = e.changedTouches[0].clientX; }, { passive: true });
      overlayBody.addEventListener('touchend', function (e) {
        if (sx === null) return;
        var dx = e.changedTouches[0].clientX - sx;
        if (Math.abs(dx) > 50) show(idx + (dx < 0 ? 1 : -1));
        sx = null;
      }, { passive: true });
    }
    show(idx);
  }

  document.addEventListener('click', function (e) {
    var t = e.target.closest ? e.target.closest('[data-lightbox]') : null;
    if (t) {
      e.preventDefault();
      var group = t.getAttribute('data-lightbox');
      var items = $$('[data-lightbox="' + group + '"]');
      openLightbox(group, Math.max(0, items.indexOf(t)));
      return;
    }
    var v = e.target.closest ? e.target.closest('[data-video]') : null;
    if (v) { e.preventDefault(); openVideo(v); }
  });

  /* ---------- Lecteur vidéo (upload ou YouTube) ---------- */
  function openVideo(el) {
    var source = el.getAttribute('data-source');
    var src = el.getAttribute('data-src') || '';
    var title = el.getAttribute('data-title') || '';
    var desc = el.getAttribute('data-desc') || '';
    openOverlay('Lecteur vidéo : ' + title);

    var box = document.createElement('div');
    box.className = 'modal__box';
    var frame = document.createElement('div');
    frame.className = 'modal__frame';

    if (source === 'youtube' && /^[A-Za-z0-9_-]{11}$/.test(src)) {
      var f = document.createElement('iframe');
      f.src = 'https://www.youtube-nocookie.com/embed/' + src + '?autoplay=1&rel=0&modestbranding=1';
      f.title = title || 'Vidéo YouTube';
      f.allow = 'autoplay; encrypted-media; picture-in-picture; fullscreen';
      f.setAttribute('allowfullscreen', '');
      f.referrerPolicy = 'strict-origin-when-cross-origin';
      frame.appendChild(f);
    } else if (source === 'upload') {
      var vid = document.createElement('video');
      vid.controls = true; vid.autoplay = true; vid.playsInline = true; vid.preload = 'metadata';
      vid.src = src;
      var poster = el.getAttribute('data-poster');
      if (poster) vid.poster = poster;
      frame.appendChild(vid);
      onOverlayClose = function () { try { vid.pause(); vid.removeAttribute('src'); vid.load(); } catch (err) {} };
    } else {
      return closeOverlay();
    }
    box.appendChild(frame);
    var h = document.createElement('h2'); h.className = 'modal__title'; h.textContent = title; box.appendChild(h);
    if (desc) { var p = document.createElement('p'); p.className = 'modal__desc'; p.textContent = desc; box.appendChild(p); }
    overlayBody.appendChild(box);
  }

  /* ---------- Swiper (carrousels) ---------- */
  if (typeof window.Swiper !== 'undefined') {
    $$('[data-swiper]').forEach(function (el) {
      var cfg = {
        slidesPerView: 1.08, spaceBetween: 20, grabCursor: true, keyboard: { enabled: true },
        pagination: { el: $('.swiper-pagination', el), clickable: true },
        navigation: { nextEl: $('.swiper-button-next', el), prevEl: $('.swiper-button-prev', el) },
        breakpoints: { 640: { slidesPerView: 2 }, 1024: { slidesPerView: 3, spaceBetween: 28 } },
        a11y: { prevSlideMessage: 'Précédent', nextSlideMessage: 'Suivant' }
      };
      if (el.getAttribute('data-autoplay') === '1' && !reduceMotion) {
        cfg.autoplay = { delay: 4500, disableOnInteraction: true, pauseOnMouseEnter: true };
      }
      new window.Swiper(el, cfg);
    });
  }

  /* ---------- Sélecteur de quantité + total ---------- */
  $$('.stepper').forEach(function (s) {
    var input = $('input', s);
    var min = parseInt(input.min || '1', 10), max = parseInt(input.max || '10', 10);
    function clamp() {
      var v = parseInt(input.value, 10);
      if (isNaN(v)) v = min;
      input.value = Math.min(max, Math.max(min, v));
      updateTotal();
    }
    $$('button', s).forEach(function (b) {
      b.addEventListener('click', function () {
        input.value = (parseInt(input.value, 10) || min) + parseInt(b.getAttribute('data-step'), 10);
        clamp();
      });
    });
    input.addEventListener('change', clamp);
  });
  function updateTotal() {
    var out = $('[data-total]');
    var input = $('.stepper input');
    if (!out || !input) return;
    var unit = parseFloat(out.getAttribute('data-unit-price') || '0');
    if (unit <= 0) { out.textContent = 'Gratuit'; return; }
    var total = unit * (parseInt(input.value, 10) || 1);
    var s = total.toFixed(3).replace(/0+$/, '').replace(/\.$/, '').replace('.', ',');
    out.textContent = s + ' TND';
  }

  /* ---------- QR codes des tickets ---------- */
  if (typeof window.QRCode !== 'undefined') {
    $$('[data-qr]').forEach(function (box) {
      box.innerHTML = '';
      new window.QRCode(box, {
        text: box.getAttribute('data-qr'), width: 300, height: 300,
        colorDark: '#09090F', colorLight: '#FFFFFF', correctLevel: window.QRCode.CorrectLevel.M
      });
      box.setAttribute('role', 'img');
      box.setAttribute('aria-label', 'QR code du ticket');
    });
  } else {
    $$('[data-qr]').forEach(function (box) {
      box.textContent = 'QR indisponible (connexion requise). Ticket : ' + (box.getAttribute('data-number') || '');
      box.style.fontSize = '.8rem'; box.style.color = '#09090F'; box.style.textAlign = 'center';
    });
  }
  $$('[data-qr-download]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var box = document.getElementById(btn.getAttribute('data-qr-download'));
      if (!box) return;
      var canvas = $('canvas', box), imgEl = $('img', box);
      var href = canvas ? canvas.toDataURL('image/png') : (imgEl ? imgEl.src : null);
      if (!href) return;
      var a = document.createElement('a');
      a.href = href; a.download = 'ticket-' + (btn.getAttribute('data-number') || 'club-media') + '.png';
      document.body.appendChild(a); a.click(); a.remove();
    });
  });
  $$('[data-print]').forEach(function (b) { b.addEventListener('click', function () { window.print(); }); });

  /* ---------- Vidéo d'arrière-plan : respect de prefers-reduced-motion ---------- */
  $$('.hero__media video').forEach(function (v) {
    if (reduceMotion) { v.pause(); v.removeAttribute('autoplay'); }
  });

  /* ---------- Confirmation + anti double-envoi ---------- */
  $$('form[data-confirm]').forEach(function (f) {
    f.addEventListener('submit', function (e) { if (!window.confirm(f.getAttribute('data-confirm'))) e.preventDefault(); });
  });
  $$('form[data-once]').forEach(function (f) {
    f.addEventListener('submit', function () {
      if (f.dataset.sent === '1') return;
      f.dataset.sent = '1';
      $$('button[type=submit]', f).forEach(function (b) { setTimeout(function () { b.disabled = true; b.textContent = 'Envoi en cours…'; }, 0); });
    });
  });

  window.ClubMedia = { renderIcons: renderIcons, reduceMotion: reduceMotion };
})();
