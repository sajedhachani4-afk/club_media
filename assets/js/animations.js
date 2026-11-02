/* =====================================================================
   CLUB MEDIA — ISET GAFSA  |  Animations GSAP + ScrollTrigger
   - GSAP : entrée du hero, titres de sections, compteurs, parallaxe, timeline.
   - AOS (dans main.js) gère les révélations simples des cartes : jamais sur les mêmes éléments.
   - Tout est désactivé si l'utilisateur demande moins d'animations.
   ===================================================================== */
(function () {
  'use strict';

  var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var hasGsap = typeof window.gsap !== 'undefined';
  var $$ = function (s, c) { return Array.prototype.slice.call((c || document).querySelectorAll(s)); };

  if (!hasGsap || reduce) { return; } // le contenu reste affiché tel quel (valeurs finales rendues par PHP)

  var gsap = window.gsap;
  var hasST = typeof window.ScrollTrigger !== 'undefined';
  if (hasST) { gsap.registerPlugin(window.ScrollTrigger); }

  /* ---------- Hero : révélation du texte ---------- */
  var heroLines = $$('.hero__title .line__inner');
  if (heroLines.length) {
    var tl = gsap.timeline({ defaults: { ease: 'power4.out' } });
    tl.from('.hero__tag', { y: 20, opacity: 0, duration: .7 })
      .from(heroLines, { yPercent: 115, duration: 1.1, stagger: .16 }, '-=.3')
      .from('.hero__lead', { y: 26, opacity: 0, duration: .8 }, '-=.6')
      .from('.hero__actions .btn', { y: 22, opacity: 0, duration: .7, stagger: .12 }, '-=.5')
      .from('.scroll-cue', { opacity: 0, duration: .6 }, '-=.2');
  }

  /* ---------- Parallaxe des lumières violettes ---------- */
  if (hasST && $$('.hero').length) {
    gsap.to('.hero__glow--1', { yPercent: -35, ease: 'none', scrollTrigger: { trigger: '.hero', start: 'top top', end: 'bottom top', scrub: true } });
    gsap.to('.hero__glow--2', { yPercent: 30, ease: 'none', scrollTrigger: { trigger: '.hero', start: 'top top', end: 'bottom top', scrub: true } });
    gsap.to('.hero__media img, .hero__media video', { scale: 1.12, ease: 'none', scrollTrigger: { trigger: '.hero', start: 'top top', end: 'bottom top', scrub: true } });
  }

  /* ---------- Titres de sections ---------- */
  if (hasST) {
    $$('[data-gsap="head"]').forEach(function (el) {
      gsap.from(el, { y: 44, opacity: 0, duration: .9, ease: 'power3.out', scrollTrigger: { trigger: el, start: 'top 85%', once: true } });
    });

    /* ---------- Compteurs animés ---------- */
    $$('[data-count]').forEach(function (el) {
      var end = parseInt(el.getAttribute('data-count'), 10) || 0;
      var obj = { v: 0 };
      el.textContent = '0';
      gsap.to(obj, {
        v: end, duration: 1.8, ease: 'power2.out',
        onUpdate: function () { el.textContent = Math.round(obj.v).toLocaleString('fr-FR'); },
        scrollTrigger: { trigger: el, start: 'top 90%', once: true }
      });
    });

    /* ---------- Ligne de la timeline ---------- */
    var tlEl = document.querySelector('.timeline');
    var fill = document.querySelector('.timeline__fill');
    if (tlEl && fill) {
      gsap.to(fill, { height: '100%', ease: 'none', scrollTrigger: { trigger: tlEl, start: 'top 70%', end: 'bottom 70%', scrub: true } });
    }

    /* ---------- Bandeau d'appel à l'action ---------- */
    $$('.cta-band').forEach(function (el) {
      gsap.from(el, { scale: .94, opacity: 0, duration: 1, ease: 'power3.out', scrollTrigger: { trigger: el, start: 'top 88%', once: true } });
    });
  } else {
    $$('[data-count]').forEach(function (el) {
      el.textContent = (parseInt(el.getAttribute('data-count'), 10) || 0).toLocaleString('fr-FR');
    });
  }

  /* ---------- Effet magnétique sur les boutons principaux du hero ---------- */
  if (window.matchMedia('(hover: hover)').matches) {
    $$('.hero__actions .btn').forEach(function (btn) {
      var xTo = gsap.quickTo(btn, 'x', { duration: .4, ease: 'power3' });
      var yTo = gsap.quickTo(btn, 'y', { duration: .4, ease: 'power3' });
      btn.addEventListener('mousemove', function (e) {
        var r = btn.getBoundingClientRect();
        xTo((e.clientX - (r.left + r.width / 2)) * .18);
        yTo((e.clientY - (r.top + r.height / 2)) * .28);
      });
      btn.addEventListener('mouseleave', function () { xTo(0); yTo(0); });
    });
  }
})();
