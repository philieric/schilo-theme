/**
 * Modale de recherche enrichie.
 * Remplace le fallback prompt() de Schilo.Search (schilo.js) en définissant
 * window.schiloSearchOpen et en écoutant l'event 'schilo:search-open'.
 */
(function () {
    'use strict';

    var cfg = window.schiloData || {};

    var modal, input, body, defaultBodyHTML;
    var lastTrigger    = null;
    var items          = [];
    var activeIndex    = -1;
    var debounceTimer  = null;
    var currentAbort   = null;

    function isOpen() {
        return !!modal && modal.classList.contains('is-open');
    }

    function trapFocus(e) {
        var panel = modal.querySelector('.schilo-search-modal__panel');
        if (panel && !panel.contains(e.target)) {
            input.focus();
        }
    }

    function openModal() {
        if (!modal) return;
        if (isOpen()) { input.focus(); return; }

        lastTrigger = document.activeElement;
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        document.documentElement.classList.add('schilo-search-open');

        input.value = '';
        resetBody();
        document.addEventListener('focus', trapFocus, true);

        window.setTimeout(function () { input.focus(); }, 0);
    }

    function closeModal() {
        if (!isOpen()) return;

        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        document.documentElement.classList.remove('schilo-search-open');
        document.removeEventListener('focus', trapFocus, true);

        if (currentAbort) { currentAbort.abort(); currentAbort = null; }
        clearTimeout(debounceTimer);

        if (lastTrigger && typeof lastTrigger.focus === 'function') {
            lastTrigger.focus();
        }
    }

    function resetBody() {
        body.innerHTML = defaultBodyHTML;
        items = [];
        activeIndex = -1;
    }

    function appendHighlighted(el, text, query) {
        if (!query) { el.appendChild(document.createTextNode(text)); return; }
        var idx = text.toLowerCase().indexOf(query.toLowerCase());
        if (idx === -1) { el.appendChild(document.createTextNode(text)); return; }
        el.appendChild(document.createTextNode(text.slice(0, idx)));
        var mark = document.createElement('mark');
        mark.textContent = text.slice(idx, idx + query.length);
        el.appendChild(mark);
        el.appendChild(document.createTextNode(text.slice(idx + query.length)));
    }

    function insertTerm(term) {
        var value       = input.value;
        var trimmedEnd  = value.replace(/\s+$/, '');
        var lastSpace   = trimmedEnd.lastIndexOf(' ');
        var prefix      = lastSpace === -1 ? '' : trimmedEnd.slice(0, lastSpace + 1);

        input.value = prefix + term + ' ';
        input.focus();
        resetBody();
    }

    function buildSuggestionItem(sugg, query) {
        var row = document.createElement('div');
        row.className = 'schilo-search-modal__item';
        row.dataset.type = sugg.type;
        row.setAttribute('role', 'option');

        var icon = document.createElement('span');
        icon.className = 'schilo-search-modal__item-icon';
        var i = document.createElement('i');
        i.className = 'ti ' + String(sugg.icon || 'ti-tag').replace(/[^a-z0-9-]/gi, '');
        i.setAttribute('aria-hidden', 'true');
        icon.appendChild(i);
        row.appendChild(icon);

        var textWrap = document.createElement('span');
        textWrap.className = 'schilo-search-modal__item-text';
        var termEl = document.createElement('span');
        termEl.className = 'schilo-search-modal__item-term';
        appendHighlighted(termEl, sugg.term, query);
        var typeEl = document.createElement('span');
        typeEl.className = 'schilo-search-modal__item-type';
        typeEl.textContent = sugg.typeLabel;
        textWrap.appendChild(termEl);
        textWrap.appendChild(typeEl);
        row.appendChild(textWrap);

        var go = document.createElement('a');
        go.className = 'schilo-search-modal__item-go';
        go.href = sugg.url;
        go.setAttribute('aria-label', 'Rechercher « ' + sugg.term + ' »');
        var goIcon = document.createElement('i');
        goIcon.className = 'ti ti-arrow-right';
        goIcon.setAttribute('aria-hidden', 'true');
        go.appendChild(goIcon);
        go.addEventListener('click', function (e) { e.stopPropagation(); });
        row.appendChild(go);

        var action = function () { insertTerm(sugg.term); };
        row.addEventListener('click', function (e) {
            if (go.contains(e.target)) return;
            action();
        });

        return { el: row, action: action };
    }

    function groupByType(suggestions) {
        var order = [];
        var map   = {};
        suggestions.forEach(function (s) {
            if (!map[s.type]) {
                map[s.type] = { label: s.typeLabel, items: [] };
                order.push(s.type);
            }
            map[s.type].items.push(s);
        });
        return order.map(function (t) { return map[t]; });
    }

    function appendFooterHint() {
        var value = input.value.trim();
        if (!value) return;

        var hint = document.createElement('p');
        hint.className = 'schilo-search-modal__footer-hint';
        hint.appendChild(document.createTextNode('↵ '));
        var strong = document.createElement('strong');
        strong.textContent = 'Entrée';
        hint.appendChild(strong);
        hint.appendChild(document.createTextNode(' pour rechercher « ' + value + ' » dans tous les articles'));
        body.appendChild(hint);
    }

    function renderResults(data, query) {
        body.innerHTML = '';
        items = [];
        activeIndex = -1;

        var suggestions = data.suggestions || [];
        var articles    = data.articles || [];

        if (!suggestions.length && !articles.length) {
            var empty = document.createElement('p');
            empty.className = 'schilo-search-modal__empty';
            empty.textContent = 'Aucune suggestion pour « ' + query + ' ». Essayez un terme plus général.';
            body.appendChild(empty);
            appendFooterHint();
            return;
        }

        if (suggestions.length) {
            groupByType(suggestions).forEach(function (group) {
                var section = document.createElement('div');
                section.className = 'schilo-search-modal__group';

                var title = document.createElement('div');
                title.className = 'schilo-search-modal__group-title';
                title.textContent = group.label;
                section.appendChild(title);

                var list = document.createElement('div');
                list.className = 'schilo-search-modal__list';
                list.setAttribute('role', 'listbox');

                group.items.forEach(function (sugg) {
                    var built = buildSuggestionItem(sugg, query);
                    list.appendChild(built.el);
                    items.push(built);
                });

                section.appendChild(list);
                body.appendChild(section);
            });
        }

        if (articles.length) {
            var artSection = document.createElement('div');
            artSection.className = 'schilo-search-modal__group';

            var artTitle = document.createElement('div');
            artTitle.className = 'schilo-search-modal__group-title';
            artTitle.textContent = 'Voir directement';
            artSection.appendChild(artTitle);

            articles.forEach(function (article) {
                var a = document.createElement('a');
                a.className = 'schilo-search-modal__article';
                a.href = article.url;

                var artIcon = document.createElement('i');
                artIcon.className = 'ti ti-file-text';
                artIcon.setAttribute('aria-hidden', 'true');
                a.appendChild(artIcon);

                var textWrap = document.createElement('span');
                textWrap.className = 'schilo-search-modal__article-text';

                var titleEl = document.createElement('span');
                titleEl.className = 'schilo-search-modal__article-title';
                titleEl.textContent = article.title;
                textWrap.appendChild(titleEl);

                if (article.summary) {
                    var summaryEl = document.createElement('span');
                    summaryEl.className = 'schilo-search-modal__article-summary';
                    summaryEl.textContent = article.summary;
                    textWrap.appendChild(summaryEl);
                }

                a.appendChild(textWrap);

                artSection.appendChild(a);
                items.push({ el: a, action: function () { window.location.href = article.url; } });
            });

            body.appendChild(artSection);
        }

        appendFooterHint();
    }

    function fetchSuggestions(term) {
        if (currentAbort) currentAbort.abort();
        currentAbort = ('AbortController' in window) ? new AbortController() : null;

        var params = new URLSearchParams({
            action: 'schilo_search_suggest',
            nonce:  cfg.nonce || '',
            term:   term
        });

        fetch((cfg.ajaxUrl || '/wp-admin/admin-ajax.php') + '?' + params.toString(), {
            credentials: 'same-origin',
            signal: currentAbort ? currentAbort.signal : undefined
        })
            .then(function (r) { return r.json(); })
            .then(function (json) {
                if (!json || !json.success) { renderResults({ suggestions: [], articles: [] }, term); return; }
                renderResults(json.data, term);
            })
            .catch(function (err) {
                if (err && err.name === 'AbortError') return;
            });
    }

    function scheduleFetch(token) {
        clearTimeout(debounceTimer);
        if (!token || token.length < 2) {
            resetBody();
            return;
        }
        debounceTimer = window.setTimeout(function () { fetchSuggestions(token); }, 200);
    }

    function onInput() {
        var value         = input.value;
        var trailingSpace = /\s$/.test(value);
        var lastToken     = trailingSpace ? '' : (value.trim().split(/\s+/).pop() || '');
        scheduleFetch(lastToken);
    }

    function moveActive(delta) {
        if (!items.length) return;
        if (activeIndex >= 0 && items[activeIndex]) {
            items[activeIndex].el.classList.remove('is-active');
        }
        activeIndex = (activeIndex + delta + items.length) % items.length;
        var current = items[activeIndex].el;
        current.classList.add('is-active');
        if (current.scrollIntoView) current.scrollIntoView({ block: 'nearest' });
    }

    function submitFullSearch() {
        var value = input.value.trim();
        if (!value) return;
        window.location.href = (cfg.homeUrl || '/') + '?s=' + encodeURIComponent(value);
    }

    function onKeydown(e) {
        if (e.key === 'ArrowDown') { e.preventDefault(); moveActive(1); return; }
        if (e.key === 'ArrowUp')   { e.preventDefault(); moveActive(-1); return; }
        if (e.key === 'Enter') {
            e.preventDefault();
            if (activeIndex >= 0 && items[activeIndex]) {
                items[activeIndex].action();
            } else {
                submitFullSearch();
            }
        }
    }

    function init() {
        modal = document.getElementById('schilo-search-modal');
        if (!modal) return;

        input = document.getElementById('schilo-search-modal-input');
        body  = document.getElementById('schilo-search-modal-body');
        if (!input || !body) return;

        defaultBodyHTML = body.innerHTML;

        var closers = modal.querySelectorAll('[data-schilo-search-close]');
        for (var c = 0; c < closers.length; c++) {
            closers[c].addEventListener('click', closeModal);
        }

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && isOpen()) closeModal();
        });

        input.addEventListener('input', onInput);
        input.addEventListener('keydown', onKeydown);

        /* Filet de sécurité : certains contextes (IME, remplissage programmatique)
           ne déclenchent pas toujours 'input' de façon fiable. */
        input.addEventListener('keyup', function (e) {
            if (['Escape', 'ArrowUp', 'ArrowDown', 'Enter'].indexOf(e.key) !== -1) return;
            onInput();
        });

        document.addEventListener('schilo:search-open', function () { openModal(); });
        window.schiloSearchOpen = openModal;
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
