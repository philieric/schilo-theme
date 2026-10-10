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

    // Cache des versions déjà rendues (clé = to_id). Alimenté par le
    // préchargement « au survol/intention » (pointerover/focus/touch sur une
    // pastille) ET au clic : une bascule vers une version en cache est
    // instantanée (aucun aller-retour serveur). _inflight évite de lancer deux
    // fois la même requête (ex. survol puis clic rapproché). Rien n'est
    // préchargé au chargement de la page : on ne rend une autre version que si
    // l'utilisateur manifeste l'intention d'y aller.
    var _cache = {};
    var _inflight = {};

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

        if (typeof data.perCode === 'string') {
            var perBadgeEl = document.querySelector('.schilo-single-hero__per');
            if (perBadgeEl) {
                perBadgeEl.innerHTML = data.perCode;
            }
            var breadcrumbCodeEl = document.querySelector('.schilo-single-hero__breadcrumb-code');
            if (breadcrumbCodeEl) {
                breadcrumbCodeEl.innerHTML = data.perCode;
            }
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
            if (typeof data.postId !== 'undefined') {
                _switcher.setAttribute('data-current-id', String(data.postId));
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

    /**
     * Récupère (et met en cache) la version cible rendue. Retourne une Promise
     * résolue avec `data`. La relation de version étant symétrique côté serveur,
     * la réponse ne dépend que de to_id : on met donc en cache par to_id,
     * indépendamment de from_id. Utilisé par le préchargement au survol comme
     * par la bascule au clic.
     */
    function _fetchVersion(fromId, toId) {
        var key = String(toId);

        if (_cache[key]) {
            return Promise.resolve(_cache[key]);
        }
        if (_inflight[key]) {
            return _inflight[key];
        }
        if (!window.schiloVersionData || typeof fetch !== 'function') {
            return Promise.reject(new Error('fetch indisponible'));
        }

        var body = 'action=schilo_switch_version'
            + '&nonce=' + encodeURIComponent(window.schiloVersionData.nonce)
            + '&from_id=' + encodeURIComponent(fromId)
            + '&to_id=' + encodeURIComponent(toId);

        var p = fetch(window.schiloVersionData.ajaxUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body
        })
            .then(function (response) { return response.json(); })
            .then(function (json) {
                delete _inflight[key];
                if (json && json.success && json.data) {
                    _cache[key] = json.data;
                    return json.data;
                }
                return Promise.reject(new Error('réponse invalide'));
            })
            .catch(function (err) {
                delete _inflight[key];
                return Promise.reject(err);
            });

        _inflight[key] = p;
        return p;
    }

    /**
     * Préchargement « au survol/intention » : dès que l'utilisateur survole,
     * focalise ou touche une pastille, on lance (une seule fois) le rendu de la
     * version visée pour le mettre en cache — soit ~200-300 ms avant le clic.
     * La bascule qui suit est alors quasi instantanée. Silencieux en cas d'échec.
     */
    function _prefetchIntent(toId) {
        if (!toId || !window.schiloVersionData || typeof fetch !== 'function') {
            return;
        }
        var fromId = _switcher ? _switcher.getAttribute('data-current-id') : null;
        if (!fromId || String(fromId) === String(toId) || _cache[String(toId)]) {
            return;
        }
        _fetchVersion(fromId, toId).then(function () {}, function () {});
    }

    function _switchTo(fromId, toId, fallbackHref) {
        if (_busy || String(fromId) === String(toId) || !window.schiloVersionData || typeof fetch !== 'function') {
            return false;
        }

        // Cache chaud (préchargé au survol ou déjà visité) : bascule instantanée.
        if (_cache[String(toId)]) {
            _applyResponse(_cache[String(toId)]);
            if (window.scrollTo) {
                window.scrollTo({ top: 0, behavior: 'smooth' });
            }
            return true;
        }

        _setBusy(true);

        _fetchVersion(fromId, toId)
            .then(function (data) {
                _applyResponse(data);
                if (window.scrollTo) {
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                }
                _setBusy(false);
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

        // Préchargement au survol / focus / toucher d'une pastille : warm le
        // cache juste avant le clic probable, sans rien précharger au chargement.
        var _intentHandler = function (e) {
            var pill = (e.target && e.target.closest) ? e.target.closest('.schilo-version-pill') : null;
            if (pill) {
                _prefetchIntent(pill.getAttribute('data-post-id'));
            }
        };
        _switcher.addEventListener('pointerover', _intentHandler);
        _switcher.addEventListener('focusin', _intentHandler);
        _switcher.addEventListener('touchstart', _intentHandler, { passive: true });

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
