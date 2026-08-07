/**
 * Schilo.ArticleVersion
 * Switch de version (grand public / académique...) en AJAX, sans
 * rechargement de page : remplace titre + extrait + contenu, met à jour
 * l'URL (pushState) et l'état actif des pastilles.
 */
var Schilo = Schilo || {};

Schilo.ArticleVersion = (function () {
    'use strict';

    var _switcher = null;
    var _busy = false;

    function _setBusy(isBusy) {
        _busy = isBusy;
        if (_switcher) {
            _switcher.classList.toggle('is-loading', isBusy);
        }
    }

    function _updateActivePill(toId) {
        var pills = _switcher.querySelectorAll('.schilo-version-pill');
        for (var i = 0; i < pills.length; i++) {
            var isMatch = pills[i].getAttribute('data-post-id') === String(toId);
            pills[i].classList.toggle('is-active', isMatch);
        }
        _switcher.setAttribute('data-current-id', String(toId));
    }

    function _applyResponse(data) {
        var titleEl = document.querySelector('.schilo-single-hero__title');
        if (titleEl && typeof data.title === 'string') {
            titleEl.innerHTML = data.title;
        }

        var excerptEl = document.querySelector('.schilo-single-hero__excerpt');
        if (excerptEl && typeof data.excerpt === 'string') {
            excerptEl.innerHTML = data.excerpt;
        }

        var contentEl = document.getElementById('schilo-single-main');
        if (contentEl && typeof data.content === 'string') {
            contentEl.innerHTML = data.content;
        }

        if (Array.isArray(data.switcher)) {
            var pillsWrap = document.getElementById('schilo-version-pills');
            if (pillsWrap) {
                var html = '';
                for (var i = 0; i < data.switcher.length; i++) {
                    var v = data.switcher[i];
                    html += '<a href="' + v.permalink + '" class="schilo-version-pill'
                        + (v.isCurrent ? ' is-active' : '') + '" data-post-id="' + v.id + '">'
                        + v.label + '</a>';
                }
                pillsWrap.innerHTML = html;
            }
        } else {
            _updateActivePill(data.postId);
        }

        if (data.permalink) {
            try {
                window.history.pushState({ schiloVersionPostId: data.postId }, '', data.permalink);
            } catch (e) { /* pushState indisponible (contexte non http) : ignorer */ }
        }

        document.dispatchEvent(new CustomEvent('schilo:version-switched', { bubbles: true, detail: data }));
    }

    function _switchTo(fromId, toId, fallbackHref) {
        if (_busy || fromId === toId || !window.schiloVersionData || typeof fetch !== 'function') {
            return false;
        }

        _setBusy(true);

        var body = 'action=schilo_switch_version'
            + '&nonce=' + encodeURIComponent(window.schiloVersionData.nonce)
            + '&from_id=' + encodeURIComponent(fromId)
            + '&to_id=' + encodeURIComponent(toId);

        fetch(window.schiloVersionData.ajaxUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body
        })
            .then(function (response) { return response.json(); })
            .then(function (json) {
                if (json && json.success && json.data) {
                    _applyResponse(json.data);
                    if (window.scrollTo) {
                        window.scrollTo({ top: 0, behavior: 'smooth' });
                    }
                    _setBusy(false);
                } else {
                    window.location.href = fallbackHref;
                }
            })
            .catch(function () {
                // Repli : navigation normale vers la version ciblee.
                window.location.href = fallbackHref;
            });

        return true;
    }

    function init() {
        _switcher = document.getElementById('schilo-version-switcher');
        if (!_switcher) {
            return;
        }

        _switcher.addEventListener('click', function (e) {
            var pill = e.target.closest ? e.target.closest('.schilo-version-pill') : null;
            if (!pill) {
                return;
            }

            var fromId = _switcher.getAttribute('data-current-id');
            var toId = pill.getAttribute('data-post-id');
            var started = _switchTo(fromId, toId, pill.getAttribute('href'));

            if (started) {
                e.preventDefault();
            }
            // Sinon (fetch indisponible, navigateur ancien) : laisse le clic
            // suivre le href normal de la pastille, navigation classique.
        });

        window.addEventListener('popstate', function () {
            // Navigation via precedent/suivant du navigateur : rechargement
            // normal, plus simple et fiable qu'un re-swap en AJAX.
            window.location.reload();
        });
    }

    return { init: init };

})();

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () { Schilo.ArticleVersion.init(); });
} else {
    Schilo.ArticleVersion.init();
}
