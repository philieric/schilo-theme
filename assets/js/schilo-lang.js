/**
 * Schilo.Lang
 * Namespace : Schilo
 * Classe    : Schilo.Lang
 *
 * Sélecteur de langue intégré — trois fournisseurs possibles, choisis dans
 * l'admin (Reglages > Traduction) via window.schiloTranslator.activeProvider :
 *
 *  - "google"       : redirige vers le proxy translate.google.com (gratuit,
 *    sans cle, mais l'URL affichee devient temporairement un sous-domaine
 *    *.translate.goog — on quitte schilo.org). Remplace le widget gratuit de
 *    GTranslate (google.translate.TranslateElement), casse depuis que
 *    Chrome/Edge/Safari bloquent par defaut le mecanisme cookies
 *    tiers/iframe dont il depend.
 *
 *  - "microsoft" / "google_cloud" : traduisent le DOM sur place (Azure
 *    Translator ou Google Cloud Translation, au choix dans l'admin), via le
 *    proxy server-side wp_ajax_schilo_translate — la cle API n'atteint
 *    jamais le navigateur. Restent sur schilo.org, contrairement au mode
 *    "google" ci-dessus. Le front-end ne differencie pas les deux, seul
 *    schiloTranslator.inPlaceReady importe ici : le PHP choisit quelle API
 *    appeler.
 */

var Schilo = Schilo || {};

Schilo.Lang = (function () {

    'use strict';

    /* ── Configuration ── */
    var _config = {
        flagBase  : '/wp-content/plugins/gtranslate/flags/svg/',
        defaultLang: 'fr',
        langs: [
            { code: 'fr',    label: 'Français',   flag: 'fr'    },
            { code: 'en',    label: 'English',     flag: 'en'    },
            { code: 'es',    label: 'Español',     flag: 'es'    },
            { code: 'de',    label: 'Deutsch',     flag: 'de'    },
            { code: 'pt',    label: 'Português',   flag: 'pt'    },
            { code: 'ar',    label: 'العربية',     flag: 'ar'    },
            { code: 'zh-CN', label: '中文',         flag: 'zh-CN' },
            { code: 'ru',    label: 'Русский',     flag: 'ru'    },
            { code: 'it',    label: 'Italiano',    flag: 'it'    },
            { code: 'nl',    label: 'Nederlands',  flag: 'nl'    },
        ],
        /* Mode "sur place" (Microsoft/Google Cloud) : exclus du parcours DOM et des langues RTL */
        excludeSelector: '#schilo-lang-selector, [translate="no"], .notranslate',
        rtlLangs: ['ar'],
        persistKey: 'schilo_lang_active',
        chunkSize: 80
    };

    /* ── État interne ── */
    var _state = {
        currentLang    : 'fr',
        wrapper        : null,
        btn            : null,
        dropdown       : null,
        isOpen         : false,
        translatedNodes: [],  // {node, original} — mode "sur place", pour le retour au francais
        noticeTimeout  : null
    };

    function _provider() {
        return (typeof schiloTranslator !== 'undefined') ? schiloTranslator.activeProvider : 'google';
    }

    /* "microsoft" et "google_cloud" partagent le meme comportement front-end
       (traduction sur place via le proxy server-side) */
    function _isInPlaceProvider(provider) {
        return provider === 'microsoft' || provider === 'google_cloud';
    }

    function _inPlaceReady() {
        return typeof schiloTranslator !== 'undefined' && !!schiloTranslator.inPlaceReady;
    }

    /* Petit message discret pres du selecteur — jamais de redirection de
       secours vers Google : si un fournisseur "sur place" est choisi mais
       n'est pas encore configure/active, on reste sur la page. */
    function _showNotice(msg) {
        if (!_state.wrapper) return;
        var el = _state.wrapper.querySelector('#schilo-lang-notice');
        if (!el) {
            el = document.createElement('div');
            el.id = 'schilo-lang-notice';
            el.style.cssText =
                'position:absolute!important;top:calc(100% + 6px)!important;right:0!important;' +
                'left:auto!important;background:#fff3cd!important;border:1px solid #ffe69c!important;' +
                'color:#664d03!important;font-size:12px!important;padding:8px 12px!important;' +
                'border-radius:8px!important;box-shadow:0 4px 12px rgba(0,0,0,.12)!important;' +
                'white-space:nowrap!important;z-index:99999!important;';
            _state.wrapper.appendChild(el);
        }
        el.textContent = msg;
        el.style.display = 'block';
        clearTimeout(_state.noticeTimeout);
        _state.noticeTimeout = setTimeout(function () { el.style.display = 'none'; }, 4000);
    }

    /* ────────────────────────────────────────────
       MÉTHODES PRIVÉES
    ──────────────────────────────────────────── */

    /* Reconstruit l'URL "reelle" (schilo.org) meme si la page est deja
       affichee via le proxy *.translate.goog (ex: schilo-org.translate.goog) */
    function _realUrl() {
        var host = location.hostname;
        var m    = host.match(/^(.*)\.translate\.goog$/);
        if (m) host = m[1].replace(/-/g, '.');
        return location.protocol + '//' + host + location.pathname;
    }

    /* Langue actuellement affichee, deduite de l'URL (pas de cookie ici) */
    function _currentLangFromUrl() {
        if (!/\.translate\.goog$/.test(location.hostname)) return _config.defaultLang;
        var m = location.search.match(/[?&]_x_tr_tl=([^&]+)/);
        return m ? decodeURIComponent(m[1]) : _config.defaultLang;
    }

    function _triggerLang(code) {
        var provider = _provider();

        /* Retour au français */
        if (!code || code === _config.defaultLang) {
            if (_isInPlaceProvider(provider)) { _restoreOriginal(); return; }
            _triggerGoogle(code);
            return;
        }

        if (_isInPlaceProvider(provider)) {
            /* Jamais de repli silencieux vers Google : si Microsoft/Google
               Cloud est choisi mais pas encore configuré/activé, on reste
               sur la page et on prévient l'utilisateur. */
            if (_inPlaceReady()) {
                _triggerInPlace(code);
            } else {
                _showNotice('Traduction non configurée pour le moment.');
            }
            return;
        }

        _triggerGoogle(code);
    }

    function _triggerGoogle(code) {
        var target = _realUrl();

        /* Retour au français : on quitte le proxy translate.goog */
        if (!code || code === _config.defaultLang) {
            location.href = target;
            return;
        }

        /* Redirige vers le proxy de traduction Google (fonctionne sans
           dependre des cookies tiers, contrairement au widget GTranslate) */
        location.href = 'https://translate.google.com/translate?sl=' + _config.defaultLang +
            '&tl=' + encodeURIComponent(code) + '&u=' + encodeURIComponent(target);
    }

    /* ── Mode "sur place" (Microsoft/Google Cloud) : parcours du DOM ── */
    function _collectTextNodes() {
        var results = [];
        var walker = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT, {
            acceptNode: function (node) {
                var parent = node.parentElement;
                if (!parent) return NodeFilter.FILTER_REJECT;
                var tag = parent.tagName;
                if (tag === 'SCRIPT' || tag === 'STYLE' || tag === 'NOSCRIPT' || tag === 'TEXTAREA') {
                    return NodeFilter.FILTER_REJECT;
                }
                if (parent.closest && parent.closest(_config.excludeSelector)) return NodeFilter.FILTER_REJECT;
                if (!node.nodeValue || !node.nodeValue.trim()) return NodeFilter.FILTER_REJECT;
                return NodeFilter.FILTER_ACCEPT;
            }
        });
        var n;
        while ((n = walker.nextNode())) results.push({ node: n });
        return results;
    }

    function _chunkNodes(nodes, size) {
        var chunks = [];
        for (var i = 0; i < nodes.length; i += size) {
            var slice = nodes.slice(i, i + size);
            slice.startIndex = i;
            chunks.push(slice);
        }
        return chunks;
    }

    /* Appelle le proxy server-side (la clé Azure ne sort jamais du serveur) */
    function _ajaxTranslate(texts, lang, cb) {
        if (typeof schiloTranslator === 'undefined') { cb(true); return; }
        var params = new URLSearchParams();
        params.append('action', 'schilo_translate');
        params.append('nonce', schiloTranslator.nonce);
        params.append('target_lang', lang);
        for (var i = 0; i < texts.length; i++) params.append('texts[]', texts[i]);

        fetch(schiloTranslator.ajaxUrl, {
            method : 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body   : params.toString()
        }).then(function (r) { return r.json(); })
          .then(function (r) {
              if (r && r.success && r.data && Array.isArray(r.data.translations)) {
                  cb(null, r.data.translations);
              } else {
                  cb(true);
              }
          })
          .catch(function () { cb(true); });
    }

    function _applyTranslations(nodes, translations, code) {
        _state.translatedNodes = [];
        for (var i = 0; i < nodes.length; i++) {
            var node = nodes[i].node;
            var t    = translations[i];
            if (!node.isConnected || typeof t !== 'string' || t === '') continue;
            _state.translatedNodes.push({ node: node, original: node.nodeValue });
            node.nodeValue = t;
        }
        document.documentElement.dir = (_config.rtlLangs.indexOf(code) !== -1) ? 'rtl' : 'ltr';
        try { localStorage.setItem(_config.persistKey, code); } catch (e) {}
    }

    function _triggerInPlace(code) {
        var nodes = _collectTextNodes();
        if (!nodes.length) return;

        var originals = nodes.map(function (item) { return item.node.nodeValue; });
        var cacheKey  = 'schilo_tr_' + location.pathname + '_' + code;

        try {
            var raw = sessionStorage.getItem(cacheKey);
            if (raw) {
                var parsed = JSON.parse(raw);
                if (parsed && Array.isArray(parsed.originals) && parsed.originals.length === originals.length &&
                    parsed.originals.every(function (o, i) { return o === originals[i]; })) {
                    _applyTranslations(nodes, parsed.translations, code);
                    return;
                }
            }
        } catch (e) {}

        var chunks  = _chunkNodes(nodes, _config.chunkSize);
        var results = new Array(nodes.length);
        var pending = chunks.length;
        var failed  = false;

        chunks.forEach(function (chunk) {
            var texts = chunk.map(function (item) { return item.node.nodeValue; });
            _ajaxTranslate(texts, code, function (err, translations) {
                if (err) {
                    failed = true;
                } else {
                    for (var i = 0; i < translations.length; i++) results[chunk.startIndex + i] = translations[i];
                }
                pending--;
                if (pending === 0 && !failed) {
                    _applyTranslations(nodes, results, code);
                    try {
                        sessionStorage.setItem(cacheKey, JSON.stringify({ originals: originals, translations: results }));
                    } catch (e) {}
                }
            });
        });
    }

    function _restoreOriginal() {
        for (var i = 0; i < _state.translatedNodes.length; i++) {
            var item = _state.translatedNodes[i];
            if (item.node.isConnected) item.node.nodeValue = item.original;
        }
        _state.translatedNodes = [];
        document.documentElement.dir = 'ltr';
        try { localStorage.removeItem(_config.persistKey); } catch (e) {}
    }

    function _getLangDef(code) {
        for (var i = 0; i < _config.langs.length; i++) {
            if (_config.langs[i].code === code) return _config.langs[i];
        }
        return _config.langs[0];
    }

    /* ── UI : ouvrir le dropdown ── */
    function _open() {
        if (!_state.dropdown) return;
        _state.isOpen = true;
        _state.dropdown.style.setProperty('opacity',        '1',     'important');
        _state.dropdown.style.setProperty('visibility',     'visible','important');
        _state.dropdown.style.setProperty('transform',      'translateY(0)', 'important');
        _state.dropdown.style.setProperty('pointer-events', 'auto',  'important');
        _state.btn.setAttribute('aria-expanded', 'true');
    }

    /* ── UI : fermer le dropdown ── */
    function _close() {
        if (!_state.dropdown) return;
        _state.isOpen = false;
        _state.dropdown.style.setProperty('opacity',        '0',     'important');
        _state.dropdown.style.setProperty('visibility',     'hidden','important');
        _state.dropdown.style.setProperty('transform',      'translateY(-4px)', 'important');
        _state.dropdown.style.setProperty('pointer-events', 'none',  'important');
        _state.btn.setAttribute('aria-expanded', 'false');
    }

    /* ── UI : mettre à jour le bouton principal ── */
    function _updateBtn(def) {
        var img = _state.btn.querySelector('.schilo-lang__flag-img');
        var lbl = _state.btn.querySelector('.schilo-lang__current');
        if (img) { img.src = _config.flagBase + def.flag + '.svg'; img.alt = def.label; }
        if (lbl) lbl.textContent = def.code.toUpperCase().slice(0, 2);
    }

    /* ── UI : mettre à jour l'option active ── */
    function _setActiveOption(code) {
        var opts = _state.dropdown.querySelectorAll('.schilo-lang__option');
        for (var i = 0; i < opts.length; i++) {
            var isActive = opts[i].getAttribute('data-lang') === code;
            opts[i].classList.toggle('active', isActive);
            opts[i].setAttribute('aria-selected', String(isActive));
            opts[i].style.setProperty('background', isActive ? '#e2eefb' : 'transparent', 'important');
            opts[i].style.setProperty('color',      isActive ? '#0e3f88' : '#1a2230',     'important');
            opts[i].style.setProperty('font-weight',isActive ? '500'     : '400',         'important');
        }
    }

    /* ── Masquer le widget natif GTranslate ── */
    function _hideNativeWidget() {
        var s = document.createElement('style');
        s.id  = 'schilo-gt-hide';
        s.textContent =
            '#gt_float_wrapper, .gt_switcher_wrapper {' +
            '  position:fixed!important; top:-9999px!important;' +
            '  left:-9999px!important; visibility:hidden!important;' +
            '  pointer-events:none!important; z-index:-1!important; }' +
            '.goog-te-banner-frame { display:none!important; }' +
            'body { top:0!important; }';
        document.head.appendChild(s);
    }

    /* ── Construire le sélecteur dans le DOM ── */
    function _buildSelector() {
        _state.wrapper = document.getElementById('schilo-lang-selector');
        if (!_state.wrapper) return;

        /* Bouton bascule admin ("Reglages > Traduction") : masque le
           selecteur entierement, quel que soit le fournisseur.
           Note : wp_localize_script serialise les booleens PHP en chaines
           ("" pour false, "1" pour true), d'ou la coercion !! plutot
           qu'une comparaison stricte a `false`. */
        var selectorEnabled = (typeof schiloTranslator === 'undefined') || !!schiloTranslator.selectorEnabled;

        /* Fournisseur "sur place" choisi mais pas configuré/activé : on ne
           construit pas le bouton du tout (rien à cliquer) plutot que
           d'afficher un selecteur non fonctionnel. */
        if (!selectorEnabled || (_isInPlaceProvider(_provider()) && !_inPlaceReady())) {
            _state.wrapper.style.display = 'none';
            _state.wrapper.innerHTML = '';
            return;
        }
        _state.wrapper.style.display = '';

        if (_isInPlaceProvider(_provider())) {
            var stored = null;
            try { stored = localStorage.getItem(_config.persistKey); } catch (e) {}
            _state.currentLang = (stored && _getLangDef(stored).code === stored) ? stored : _config.defaultLang;
        } else {
            _state.currentLang = _currentLangFromUrl();
        }
        var activeDef = _getLangDef(_state.currentLang);

        /* Bouton */
        _state.btn = document.createElement('button');
        _state.btn.className = 'schilo-lang__btn';
        _state.btn.setAttribute('aria-haspopup', 'listbox');
        _state.btn.setAttribute('aria-expanded', 'false');
        _state.btn.setAttribute('aria-label', 'Changer de langue');
        _state.btn.innerHTML =
            '<img src="' + _config.flagBase + activeDef.flag + '.svg" ' +
                'class="schilo-lang__flag-img" width="20" height="20" alt="">' +
            '<span class="schilo-lang__current">' +
                activeDef.code.toUpperCase().slice(0, 2) + '</span>' +
            '<i class="ti ti-chevron-down schilo-lang__arrow" aria-hidden="true"></i>';

        /* Dropdown */
        _state.dropdown = document.createElement('div');
        _state.dropdown.id = 'schilo-lang-dropdown';
        _state.dropdown.className = 'schilo-lang__dropdown';
        _state.dropdown.setAttribute('role', 'listbox');

        /* Options */
        for (var i = 0; i < _config.langs.length; i++) {
            var lang     = _config.langs[i];
            var isActive = lang.code === _state.currentLang;
            var opt      = document.createElement('button');
            opt.className = 'schilo-lang__option' + (isActive ? ' active' : '');
            opt.setAttribute('data-lang',     lang.code);
            opt.setAttribute('role',          'option');
            opt.setAttribute('aria-selected', String(isActive));
            opt.innerHTML =
                '<img src="' + _config.flagBase + lang.flag + '.svg" ' +
                    'width="20" height="20" alt="" ' +
                    'style="border-radius:3px;flex-shrink:0;display:inline-block">' +
                '<span>' + lang.label + '</span>';
            opt.style.cssText =
                'display:flex!important;flex-direction:row!important;align-items:center!important;' +
                'gap:10px;width:100%;padding:9px 14px;' +
                'background:' + (isActive ? '#e2eefb' : 'transparent') + ';' +
                'color:'      + (isActive ? '#0e3f88' : '#1a2230')     + ';' +
                'font-weight:'+ (isActive ? '500'     : '400')         + ';' +
                'border:none;border-bottom:1px solid #f0f2f5;border-radius:0;' +
                'font-size:13px;font-family:inherit;cursor:pointer;' +
                'text-align:left;margin:0;float:none;box-sizing:border-box;';
            _state.dropdown.appendChild(opt);
        }

        /* Injecter dans le DOM */
        _state.wrapper.innerHTML = '';
        _state.wrapper.appendChild(_state.btn);
        _state.wrapper.appendChild(_state.dropdown);

        /* Style inline dropdown */
        _state.dropdown.style.cssText =
            'position:absolute!important;top:calc(100% + 6px)!important;right:0!important;' +
            'left:auto!important;background:#fff!important;border:1px solid #dde2ea!important;' +
            'border-radius:10px!important;' +
            'box-shadow:0 8px 24px rgba(0,0,0,.12),0 2px 6px rgba(0,0,0,.06)!important;' +
            'min-width:190px!important;width:190px!important;z-index:99999!important;' +
            'overflow:hidden!important;opacity:0!important;visibility:hidden!important;' +
            'transform:translateY(-4px)!important;pointer-events:none!important;' +
            'transition:opacity .15s,visibility .15s,transform .15s!important;' +
            'display:block!important;margin:0!important;padding:0!important;';
    }

    /* ── Attacher les événements ── */
    function _bindEvents() {
        if (!_state.btn || !_state.dropdown) return;

        /* Toggle */
        _state.btn.addEventListener('click', function (e) {
            e.stopPropagation();
            _state.isOpen ? _close() : _open();
        });

        /* Clic extérieur */
        document.addEventListener('click', function (e) {
            if (!_state.wrapper.contains(e.target)) _close();
        });

        /* Escape */
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && _state.isOpen) {
                _close();
                _state.btn.focus();
            }
        });

        /* Sélection d'une langue */
        _state.dropdown.addEventListener('click', function (e) {
            var opt  = e.target.closest ? e.target.closest('.schilo-lang__option') : null;
            if (!opt) return;
            var code = opt.getAttribute('data-lang');
            if (!code) return;

            /* Fournisseur "sur place" choisi mais pas encore prêt : on ne
               change rien à l'affichage (le drapeau resterait sur une
               langue non réellement traduite) et on prévient au lieu de
               basculer. */
            if (code !== _config.defaultLang && _isInPlaceProvider(_provider()) && !_inPlaceReady()) {
                _close();
                _showNotice('Traduction non configurée pour le moment.');
                return;
            }

            _setActiveOption(code);
            _updateBtn(_getLangDef(code));
            _state.currentLang = code;
            _close();
            _triggerLang(code);
        });
    }

    /* ────────────────────────────────────────────
       MÉTHODE PUBLIQUE : init()
    ──────────────────────────────────────────── */

    function init() {
        _hideNativeWidget();
        _buildSelector();
        _bindEvents();

        /* Persistance inter-navigation (fournisseurs "sur place" uniquement
           — le mode Google reste "traduit" naturellement tant qu'on navigue
           sur le domaine translate.goog, dont les liens internes restent
           proxies) */
        if (_isInPlaceProvider(_provider()) && _inPlaceReady() && _state.currentLang !== _config.defaultLang) {
            _triggerInPlace(_state.currentLang);
        }
    }

    /* ── API publique ── */
    return {
        init       : init,
        open       : _open,
        close      : _close,
        setLang    : _triggerLang,
        getCurrentLang: function () { return _state.currentLang; }
    };

})();


/* ── Auto-init ── */
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () { Schilo.Lang.init(); });
} else {
    Schilo.Lang.init();
}
