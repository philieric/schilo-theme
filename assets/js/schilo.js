/**
 * Schilo.Theme
 * Namespace : Schilo
 * Classes   : Schilo.Search | Schilo.Anchors | Schilo.Verses | Schilo.MarkRead | Schilo.ArchiveView | Schilo.CategoryAccordion
 *
 * JS principal du thème — ancres, versions versets, mark-read.
 */

var Schilo = Schilo || {};

/* ════════════════════════════════════════════
   Schilo.Search
   Gère l'ouverture de la recherche
════════════════════════════════════════════ */
Schilo.Search = (function () {

    'use strict';

    function init() {
        var btn = document.getElementById('schilo-search-toggle');
        if (!btn) return;

        btn.addEventListener('click', function () {
            var event = new CustomEvent('schilo:search-open', { bubbles: true });
            document.dispatchEvent(event);

            /* Fallback natif */
            if (typeof window.schiloSearchOpen !== 'function') {
                var q = prompt('Rechercher…');
                if (q && q.trim()) {
                    var baseUrl = (window.schiloData && window.schiloData.homeUrl)
                        ? window.schiloData.homeUrl
                        : '/';
                    window.location.href = baseUrl + '?s=' + encodeURIComponent(q.trim());
                }
            }
        });
    }

    return { init: init };

})();


/* ════════════════════════════════════════════
   Schilo.Anchors
   Navigation par ancres active au scroll
════════════════════════════════════════════ */
Schilo.Anchors = (function () {

    'use strict';

    var _links    = [];
    var _sections = [];
    var _observer = null;

    function _setActive(id) {
        for (var i = 0; i < _links.length; i++) {
            var href = _links[i].getAttribute('href');
            _links[i].classList.toggle('active', href === '#' + id);
        }
    }

    function init() {
        _links    = document.querySelectorAll('.schilo-anchor-nav a[href^="#"]');
        _sections = document.querySelectorAll('[data-anchor]');

        if (!_links.length || !_sections.length) return;

        _observer = new IntersectionObserver(function (entries) {
            for (var i = 0; i < entries.length; i++) {
                if (entries[i].isIntersecting) {
                    _setActive(entries[i].target.getAttribute('data-anchor'));
                }
            }
        }, { rootMargin: '-100px 0px -60% 0px' });

        for (var i = 0; i < _sections.length; i++) {
            _observer.observe(_sections[i]);
        }
    }

    return { init: init };

})();


/* ════════════════════════════════════════════
   Schilo.Verses
   Sélecteur de versions bibliques (LSG/BDS/NBS/TOB)
════════════════════════════════════════════ */
Schilo.Verses = (function () {

    'use strict';

    function _handlePillClick(pill) {
        var ev    = pill.getAttribute('data-ev');
        var ver   = pill.getAttribute('data-ver');
        var block = pill.closest ? pill.closest('.schilo-verse') : null;
        if (!block || !ev || !ver) return;

        /* Désactiver les pills du même évangile */
        var pills = block.querySelectorAll('.schilo-vpill--' + ev);
        for (var i = 0; i < pills.length; i++) {
            pills[i].classList.remove('active');
        }
        pill.classList.add('active');

        /* Déclencher l'événement pour Schilo Builder */
        var event = new CustomEvent('schilo:version-change', {
            bubbles: true,
            detail : { ev: ev, ver: ver, block: block }
        });
        block.dispatchEvent(event);
    }

    function init() {
        document.addEventListener('click', function (e) {
            var pill = e.target.closest ? e.target.closest('.schilo-vpill') : null;
            if (pill) _handlePillClick(pill);
        });
    }

    return { init: init };

})();


/* ════════════════════════════════════════════
   Schilo.MarkRead
   Bouton "Marquer comme lu"
════════════════════════════════════════════ */
Schilo.MarkRead = (function () {

    'use strict';

    function _toggle(btn) {
        var postId = btn.getAttribute('data-post-id');
        if (!postId) return;

        var isDone = btn.classList.toggle('done');
        var icon   = btn.querySelector('i');
        if (icon) {
            icon.className = isDone
                ? 'ti ti-circle-check-filled'
                : 'ti ti-circle-check';
        }

        /* Synchroniser tous les boutons du même post */
        var siblings = document.querySelectorAll(
            '[data-schilo-action="mark-read"][data-post-id="' + postId + '"]'
        );
        for (var i = 0; i < siblings.length; i++) {
            siblings[i].classList.toggle('done', isDone);
        }

        /* Appel AJAX si Schilo Builder est actif et fetch disponible */
        if (typeof fetch === 'function' && window.schiloData && window.schiloData.ajaxUrl) {
            var body = 'action=schilo_mark_read'
                     + '&post_id=' + encodeURIComponent(postId)
                     + '&value='   + (isDone ? '1' : '0')
                     + '&nonce='   + encodeURIComponent(window.schiloData.nonce);

            fetch(window.schiloData.ajaxUrl, {
                method : 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body   : body
            }).catch(function (err) { console.warn('[Schilo.MarkRead] AJAX error:', err); });
        }
    }

    function init() {
        document.addEventListener('click', function (e) {
            var btn = e.target.closest
                ? e.target.closest('[data-schilo-action="mark-read"]')
                : null;
            if (btn) _toggle(btn);
        });
    }

    return { init: init };

})();


/* ════════════════════════════════════════════
   Schilo.Date
   Affiche la date du jour (verset du jour)
════════════════════════════════════════════ */
Schilo.Date = (function () {

    'use strict';

    var DAYS   = ['dimanche','lundi','mardi','mercredi','jeudi','vendredi','samedi'];
    var MONTHS = ['janvier','février','mars','avril','mai','juin',
                  'juillet','août','septembre','octobre','novembre','décembre'];

    function init() {
        var el = document.getElementById('vjDate');
        if (!el) return;
        var now = new Date();
        el.textContent = DAYS[now.getDay()] + ' ' + now.getDate()
                       + ' ' + MONTHS[now.getMonth()] + ' ' + now.getFullYear();
    }

    return { init: init };

})();


/* ════════════════════════════════════════════
   Schilo.ArchiveView
   Toggle grille / liste sur les pages d'archive
════════════════════════════════════════════ */
Schilo.ArchiveView = (function () {

    'use strict';

    var STORAGE_KEY = 'schilo_archive_view';

    function _applyView(view, posts, buttons) {
        posts.classList.remove('schilo-archive-posts--grid', 'schilo-archive-posts--list');
        posts.classList.add('schilo-archive-posts--' + view);
        buttons.forEach(function (btn) {
            var active = btn.dataset.view === view;
            btn.classList.toggle('is-active', active);
            btn.setAttribute('aria-pressed', active ? 'true' : 'false');
        });
    }

    function _buildArchiveUrl(baseUrl, selects) {
        /* Le tri par defaut differe selon le contexte (categories : plus ancien
           d'abord, autres archives : plus recent d'abord) — lu depuis data-default
           sur chaque select plutot que code en dur, pour rester coherent avec le
           defaut applique cote serveur (Schilo_Setup::apply_archive_sort). */
        var parts = [];
        for (var i = 0; i < selects.length; i++) {
            var sel   = selects[i];
            var param = sel.dataset.param;
            var def   = sel.dataset.default || '';
            var val   = String(sel.value || '');
            if (param && val && val !== def) {
                parts.push(encodeURIComponent(param) + '=' + encodeURIComponent(val));
            }
        }
        var base = baseUrl.split('?')[0];
        return base + (parts.length ? '?' + parts.join('&') : '');
    }

    function init() {
        var posts   = document.getElementById('schilo-archive-posts');
        var buttons = Array.prototype.slice.call(
            document.querySelectorAll('.schilo-archive-view-btn')
        );
        if (!posts || !buttons.length) return;

        // Restaurer la préférence vue
        var saved = localStorage.getItem(STORAGE_KEY) || 'grid';
        _applyView(saved, posts, buttons);

        buttons.forEach(function (btn) {
            btn.addEventListener('click', function () {
                var view = btn.dataset.view;
                _applyView(view, posts, buttons);
                try { localStorage.setItem(STORAGE_KEY, view); } catch (e) {}
            });
        });

        // Zoom texte des cartes
        var zoomSlider = document.getElementById('schilo-archive-zoom');
        if (zoomSlider && posts) {
            var ZOOM_KEY = 'schilo_archive_zoom';
            var savedZoom = parseInt(localStorage.getItem(ZOOM_KEY), 10) || 100;
            var _applyZoom = function (val) {
                posts.style.setProperty('--card-zoom', val / 100);
                var pct = ((val - 80) / (130 - 80) * 100).toFixed(1) + '%';
                zoomSlider.style.setProperty('--zoom-pct', pct);
                zoomSlider.value = val;
            };
            _applyZoom(savedZoom);
            zoomSlider.addEventListener('input', function () {
                var val = parseInt(zoomSlider.value, 10);
                _applyZoom(val);
                try { localStorage.setItem(ZOOM_KEY, val); } catch (e) {}
            });
        }

        // Selects tri et par-page — navigation avec conservation des deux params
        var selects = Array.prototype.slice.call(
            document.querySelectorAll('.schilo-archive-select')
        );
        selects.forEach(function (sel) {
            sel.addEventListener('change', function () {
                var baseUrl = sel.dataset.baseUrl || window.location.href;
                window.location.href = _buildArchiveUrl(baseUrl, selects);
            });
        });
    }

    return { init: init };

})();


/* ════════════════════════════════════════════
   Schilo.CategoryAccordion
   Accordéon "Ressources et séries thématiques"
   — toggle individuel + tout ouvrir / replier
════════════════════════════════════════════ */
Schilo.CategoryAccordion = (function () {

    'use strict';

    function _toggleGroup(btn, groupSel, childrenSel) {
        var group    = btn.closest(groupSel);
        if (!group) return;
        var children = document.getElementById(btn.getAttribute('aria-controls'));
        if (!children) return;
        var isOpen = group.classList.contains('open');
        if (isOpen) {
            children.setAttribute('hidden', '');
            group.classList.remove('open');
            btn.setAttribute('aria-expanded', 'false');
        } else {
            children.removeAttribute('hidden');
            group.classList.add('open');
            btn.setAttribute('aria-expanded', 'true');
        }
    }

    function init() {
        /* Nouveau design : .schilo-home-lib__toggle dans .schilo-home-lib__group */
        document.querySelectorAll('.schilo-home-lib__toggle').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                _toggleGroup(btn, '.schilo-home-lib__group', '.schilo-home-lib__children');
            });
        });
    }

    return { init: init };

})();


/* ════════════════════════════════════════════
   Schilo.App
   Point d'entrée — initialise tous les modules
════════════════════════════════════════════ */
Schilo.App = (function () {

    'use strict';

    function init() {
        Schilo.Search.init();
        Schilo.Anchors.init();
        Schilo.Verses.init();
        Schilo.MarkRead.init();
        Schilo.Date.init();
        Schilo.ArchiveView.init();
        Schilo.CategoryAccordion.init();

        if (window.schiloData) {
            console.log('[Schilo.App] v' + window.schiloData.version + ' — prêt.');
        }
    }

    return { init: init };

})();


/* ════════════════════════════════════════════
   Schilo.TextZoom
   Widget flottant A- / A+ — zoom du contenu principal
════════════════════════════════════════════ */
Schilo.TextZoom = (function () {
    'use strict';

    var STORAGE_KEY = 'schilo_text_zoom';
    var MIN = 80, MAX = 130, STEP = 5;
    var current = 100;

    function _apply(val) {
        var main = document.getElementById('schilo-main');
        if (main) { main.style.zoom = (val / 100).toFixed(2); }
        var level = document.getElementById('schilo-zoom-level');
        if (level) { level.textContent = val + '%'; }
        current = val;
    }

    function init() {
        var btnOut  = document.getElementById('schilo-zoom-out');
        var btnIn   = document.getElementById('schilo-zoom-in');
        if (!btnOut || !btnIn) { return; }

        try { current = parseInt(localStorage.getItem(STORAGE_KEY), 10) || 100; } catch(e) {}
        current = Math.min(MAX, Math.max(MIN, current));
        _apply(current);

        btnOut.addEventListener('click', function () {
            if (current > MIN) {
                _apply(current - STEP);
                try { localStorage.setItem(STORAGE_KEY, current); } catch(e) {}
            }
        });
        btnIn.addEventListener('click', function () {
            if (current < MAX) {
                _apply(current + STEP);
                try { localStorage.setItem(STORAGE_KEY, current); } catch(e) {}
            }
        });
    }

    return { init: init };
})();

/* ── Démarrage ── */
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () { Schilo.App.init(); Schilo.TextZoom.init(); });
} else {
    Schilo.App.init();
    Schilo.TextZoom.init();
}
