/**
 * Recherche avancée (page-recherche-avancee.php) : filtres cochables +
 * texte libre, résultats en AJAX. Construction du DOM via createElement/
 * textContent (jamais innerHTML sur du contenu serveur) — même convention
 * de sécurité que search-modal.js.
 */
(function () {
    'use strict';

    var cfg  = window.schiloData || {};
    var root = document.getElementById('schilo-adv-search');
    if (!root) return;

    var FIELDS = ['theme', 'parcours', 'serie', 'type', 'livre'];

    var qInput      = document.getElementById('schilo-adv-q');
    var resultsEl   = document.getElementById('schilo-adv-results');
    var countEl     = document.getElementById('schilo-adv-count');
    var emptyEl     = document.getElementById('schilo-adv-empty');
    var loadMoreBtn = document.getElementById('schilo-adv-loadmore');
    var spinnerEl   = document.getElementById('schilo-adv-spinner');
    var resetBtn    = document.getElementById('schilo-adv-reset');

    var debounceTimer = null;
    var currentAbort  = null;
    var offset        = 0;
    var shownCount    = 0;
    var requestToken  = 0; // ignore les réponses d'une requête devenue obsolète

    function collectCriteria() {
        var criteria = { q: qInput.value.trim() };
        FIELDS.forEach(function (field) {
            var checked = root.querySelectorAll('input[name="' + field + '[]"]:checked');
            criteria[field] = Array.prototype.map.call(checked, function (el) { return el.value; });
        });
        return criteria;
    }

    function buildParams(criteria, currentOffset) {
        var params = new URLSearchParams();
        params.set('action', 'schilo_advanced_search');
        params.set('nonce', root.dataset.nonce || cfg.nonce || '');
        params.set('offset', String(currentOffset));
        if (criteria.q) params.set('q', criteria.q);
        FIELDS.forEach(function (field) {
            (criteria[field] || []).forEach(function (value) { params.append(field + '[]', value); });
        });
        return params;
    }

    /* Reflète les critères actifs dans l'URL (partageable / retour arrière),
       sans recharger la page. */
    function syncUrl(criteria) {
        var params = new URLSearchParams();
        if (criteria.q) params.set('q', criteria.q);
        FIELDS.forEach(function (field) {
            (criteria[field] || []).forEach(function (value) { params.append(field, value); });
        });
        var qs  = params.toString();
        var url = window.location.pathname + (qs ? '?' + qs : '');
        window.history.replaceState(null, '', url);
    }

    function restoreFromUrl() {
        var params = new URLSearchParams(window.location.search);
        var q = params.get('q');
        if (q) qInput.value = q;

        FIELDS.forEach(function (field) {
            params.getAll(field).forEach(function (value) {
                var input = root.querySelector('input[name="' + field + '[]"][value="' + CSS.escape(value) + '"]');
                if (input) input.checked = true;
            });
        });
    }

    function buildCard(item) {
        var article = document.createElement('article');
        article.className = 'schilo-archive-card';

        var thumbLink = document.createElement('a');
        thumbLink.className   = 'schilo-archive-card__thumb-link';
        thumbLink.href        = item.url;
        thumbLink.tabIndex    = -1;
        thumbLink.setAttribute('aria-hidden', 'true');

        if (item.thumbnail) {
            var img = document.createElement('img');
            img.className = 'schilo-archive-card__thumb';
            img.loading   = 'lazy';
            img.src       = item.thumbnail;
            img.alt       = '';
            thumbLink.appendChild(img);
        } else {
            var placeholder = document.createElement('div');
            placeholder.className = 'schilo-archive-card__thumb schilo-archive-card__thumb--placeholder';
            placeholder.setAttribute('aria-hidden', 'true');
            var icon = document.createElement('i');
            icon.className = 'ti ti-book-2';
            placeholder.appendChild(icon);
            thumbLink.appendChild(placeholder);
        }

        if (item.prefix) {
            var badge = document.createElement('span');
            badge.className = 'schilo-archive-card__per-badge';
            badge.textContent = item.prefix;
            thumbLink.appendChild(badge);
        }

        article.appendChild(thumbLink);

        var body = document.createElement('div');
        body.className = 'schilo-archive-card__body';

        if (item.category) {
            var catLink = document.createElement('a');
            catLink.className = 'schilo-archive-card__cat';
            catLink.href      = item.category.url;
            catLink.textContent = item.category.name;
            body.appendChild(catLink);
        }

        var h2 = document.createElement('h2');
        h2.className = 'schilo-archive-card__title';
        var titleLink = document.createElement('a');
        titleLink.href = item.url;
        titleLink.textContent = item.title;
        h2.appendChild(titleLink);
        body.appendChild(h2);

        var excerpt = document.createElement('p');
        excerpt.className = 'schilo-archive-card__excerpt';
        excerpt.textContent = item.excerpt;
        body.appendChild(excerpt);

        var footer = document.createElement('footer');
        footer.className = 'schilo-archive-card__footer';
        var readMore = document.createElement('a');
        readMore.className = 'schilo-archive-card__read-more';
        readMore.href = item.url;
        readMore.appendChild(document.createTextNode((cfg.strings && cfg.strings.readMore) || 'Lire l’étude'));
        var arrowIcon = document.createElement('i');
        arrowIcon.className = 'ti ti-arrow-right';
        arrowIcon.setAttribute('aria-hidden', 'true');
        readMore.appendChild(arrowIcon);
        footer.appendChild(readMore);
        body.appendChild(footer);

        article.appendChild(body);

        return article;
    }

    function setLoading(isLoading) {
        if (spinnerEl) spinnerEl.hidden = !isLoading;
        if (loadMoreBtn) loadMoreBtn.disabled = isLoading;
    }

    function updateCount() {
        if (!countEl) return;
        if (shownCount === 0) {
            countEl.textContent = '';
            return;
        }
        countEl.textContent = shownCount === 1
            ? '1 article'
            : shownCount + ' articles';
    }

    function runSearch(reset) {
        var criteria = collectCriteria();
        var token = ++requestToken;

        if (reset) {
            offset     = 0;
            shownCount = 0;
            resultsEl.textContent = '';
            emptyEl.hidden = true;
            syncUrl(criteria);
        }

        if (currentAbort) currentAbort.abort();
        currentAbort = ('AbortController' in window) ? new AbortController() : null;

        setLoading(true);

        var params = buildParams(criteria, offset);

        fetch((cfg.ajaxUrl || '/wp-admin/admin-ajax.php') + '?' + params.toString(), {
            credentials: 'same-origin',
            signal: currentAbort ? currentAbort.signal : undefined
        })
            .then(function (r) { return r.json(); })
            .then(function (json) {
                if (token !== requestToken) return; // réponse obsolète, ignorée
                setLoading(false);

                if (!json || !json.success) {
                    if (loadMoreBtn) loadMoreBtn.hidden = true;
                    return;
                }

                var data = json.data || {};
                var items = data.items || [];

                items.forEach(function (item) {
                    resultsEl.appendChild(buildCard(item));
                });

                shownCount += items.length;
                offset     += items.length;
                updateCount();

                emptyEl.hidden = shownCount > 0;

                if (loadMoreBtn) loadMoreBtn.hidden = !data.hasMore;
            })
            .catch(function (err) {
                if (err && err.name === 'AbortError') return;
                setLoading(false);
            });
    }

    function scheduleSearch() {
        clearTimeout(debounceTimer);
        debounceTimer = window.setTimeout(function () { runSearch(true); }, 300);
    }

    qInput.addEventListener('input', scheduleSearch);

    root.addEventListener('change', function (e) {
        if (e.target && e.target.matches('input[type="checkbox"]')) {
            runSearch(true);
        }
    });

    if (loadMoreBtn) {
        loadMoreBtn.addEventListener('click', function () { runSearch(false); });
    }

    if (resetBtn) {
        resetBtn.addEventListener('click', function () {
            qInput.value = '';
            root.querySelectorAll('input[type="checkbox"]:checked').forEach(function (el) { el.checked = false; });
            runSearch(true);
        });
    }

    restoreFromUrl();
    runSearch(true);
})();
