/**
 * schilo-single.js — Onglets + scroll spy
 */
(function () {
  'use strict';

  var TYPE_ANCHORS = {
    'schilo-section-intro':                       'sec-resume',
    'schilo-section-liens-articles':              'sec-consultation',
    'schilo-section-details-techniques':          'sec-details',
    'schilo-section-detail-technique-img-droite': 'sec-detail-img',
    'schilo-section-details-colonnes':            'sec-details-col',
    'schilo-section-image-textes':                'sec-image-textes',
    'schilo-section-evangiles':                   'sec-versets',
    'schilo-section-paragraphe':                  'sec-commentaires',
    'schilo-section-questions':                   'sec-questions',
    'schilo-section-references':                  'sec-articles',
  };

  // ── Barre de progression de lecture ──────────────────────────────────
  (function () {
    var bar = document.createElement('div');
    bar.id = 'schilo-read-progress';
    document.body.appendChild(bar);

    var main = document.getElementById('schilo-main');
    function update() {
      if (!main) return;
      var rect   = main.getBoundingClientRect();
      var total  = main.offsetHeight - window.innerHeight;
      var pct    = total > 0 ? Math.min(100, Math.max(0, -rect.top / total * 100)) : 0;
      bar.style.width = pct + '%';
    }
    window.addEventListener('scroll', update, { passive: true });
    window.addEventListener('resize', update, { passive: true });
    update();
  })();

  document.addEventListener('DOMContentLoaded', function () {
    var tabnav = document.getElementById('schilo-tabnav');
    if (!tabnav) return;

    // 1. Assigner les IDs aux sections
    // classList.forEach n'est pas universel — on passe par className.split
    var assigned = {};
    document.querySelectorAll('.schilo-section').forEach(function (el) {
      var classes = el.className.split(/\s+/);
      for (var i = 0; i < classes.length; i++) {
        var anchor = TYPE_ANCHORS[classes[i]];
        if (anchor && !assigned[anchor]) {
          el.id = anchor;
          assigned[anchor] = true;
          break;
        }
      }
    });

    // 2. Sticky après le hero — fallback scroll si IntersectionObserver absent/polyfillé
    var hero = document.querySelector('.schilo-single-hero');
    if (hero) {
      if (typeof IntersectionObserver !== 'undefined' && 'IntersectionObserver' in window) {
        var heroObs = new IntersectionObserver(function (entries) {
          tabnav.classList.toggle('is-sticky', !entries[0].isIntersecting);
        }, { threshold: 0 });
        heroObs.observe(hero);
      } else {
        window.addEventListener('scroll', function () {
          var bottom = hero.getBoundingClientRect().bottom;
          tabnav.classList.toggle('is-sticky', bottom <= 0);
        }, { passive: true });
      }
    }

    // 3. Scroll spy simple (IntersectionObserver)
    var links = tabnav.querySelectorAll('.schilo-tabnav-link');
    var sections = Array.from(document.querySelectorAll('[id^="sec-"]'));
    if (!sections.length || !links.length) return;

    var navH = tabnav.offsetHeight;

    var spy = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) {
          links.forEach(function (l) { l.classList.remove('is-active'); });
          var active = tabnav.querySelector('[data-anchor="' + entry.target.id + '"]');
          if (active) active.classList.add('is-active');
        }
      });
    }, { rootMargin: '-' + (navH + 8) + 'px 0px -60% 0px', threshold: 0 });

    sections.forEach(function (s) { spy.observe(s); });
  });
})();
