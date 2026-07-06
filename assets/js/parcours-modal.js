(function () {
    'use strict';

    var openModal = null;

    function open(modal) {
        if (!modal) return;
        close();
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('schilo-modal-open');
        openModal = modal;
    }

    function close() {
        if (!openModal) return;
        openModal.classList.remove('is-open');
        openModal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('schilo-modal-open');
        openModal = null;
    }

    document.addEventListener('click', function (e) {
        var trigger = e.target.closest('[data-modal-trigger]');
        if (trigger) {
            var modal = document.getElementById(trigger.getAttribute('data-modal-trigger'));
            open(modal);
            return;
        }

        if (e.target.closest('[data-modal-close]')) {
            close();
        }
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') close();
    });
})();

/**
 * Barre d'onglets des étapes/sous-thèmes (taxonomy-schilo_parcours.php et
 * taxonomy-schilo_theme.php) — sticky après le hero + surbrillance de
 * l'onglet actif au scroll, calque simplifié de schilo-single.js (les
 * ancres sec-{term_id} sont déjà posées côté PHP, pas besoin de les assigner en JS).
 */
(function () {
    'use strict';

    // Les scripts places en pied de page s'executent souvent apres que
    // DOMContentLoaded ait deja ete emis : un simple addEventListener
    // raterait alors l'evenement de facon intermittente selon les
    // navigateurs/la vitesse de chargement. On verifie donc readyState.
    function ready(fn) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', fn);
        } else {
            fn();
        }
    }

    ready(function () {
        var tabnav = document.getElementById('schilo-parcours-tabnav');
        if (!tabnav) return;

        var hero = document.querySelector('.schilo-hero');
        if (hero) {
            if (typeof IntersectionObserver !== 'undefined') {
                var heroObs = new IntersectionObserver(function (entries) {
                    tabnav.classList.toggle('is-sticky', !entries[0].isIntersecting);
                }, { threshold: 0 });
                heroObs.observe(hero);
            } else {
                window.addEventListener('scroll', function () {
                    tabnav.classList.toggle('is-sticky', hero.getBoundingClientRect().bottom <= 0);
                }, { passive: true });
            }
        }

        var links = tabnav.querySelectorAll('.schilo-tabnav-link');
        var sections = Array.prototype.slice.call(document.querySelectorAll('[id^="sec-"]'));
        if (!sections.length || !links.length || typeof IntersectionObserver === 'undefined') return;

        var siteNav = document.querySelector('.schilo-nav');
        var navH = tabnav.offsetHeight + ( siteNav ? siteNav.offsetHeight : 0 );
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
