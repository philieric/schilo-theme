/**
 * Schilo.Lang
 * Namespace : Schilo
 * Classe    : Schilo.Lang
 *
 * Sélecteur de langue intégré — pilote GTranslate en arrière-plan
 * via un clic simulé sur ses liens natifs [data-gt-lang].
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
        ]
    };

    /* ── État interne ── */
    var _state = {
        currentLang: 'fr',
        wrapper    : null,
        btn        : null,
        dropdown   : null,
        isOpen     : false
    };

    /* ────────────────────────────────────────────
       MÉTHODES PRIVÉES
    ──────────────────────────────────────────── */

    function _readCookieLang() {
        var m = document.cookie.match('(^|;) ?googtrans=([^;]*)(;|$)');
        if (!m || !m[2]) return null;
        var parts = m[2].split('/');
        return parts[2] || null;
    }

    function _clearCookies() {
        var exp  = 'expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/';
        var host = location.hostname;
        document.cookie = 'googtrans=; ' + exp + ';';
        document.cookie = 'googtrans=; ' + exp + '; domain=' + host + ';';
        document.cookie = 'googtrans=; ' + exp + '; domain=.' + host + ';';
    }

    function _findGlink(code) {
        return document.querySelector('a[data-gt-lang="' + code + '"]');
    }

    function _triggerLang(code) {
        /* Retour au français */
        if (!code || code === _config.defaultLang) {
            _clearCookies();
            location.reload();
            return;
        }

        /* Clic sur le lien natif GTranslate (avec ses listeners déjà attachés) */
        var link = _findGlink(code);
        if (link) {
            var events = ['pointerover', 'pointerenter', 'mouseover', 'mouseenter'];
            for (var i = 0; i < events.length; i++) {
                link.dispatchEvent(new MouseEvent(events[i], { bubbles: true, cancelable: true }));
            }
            setTimeout(function () {
                link.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true }));
            }, 50);
            return;
        }

        /* Fallback : cookie + reload */
        var val  = '/' + _config.defaultLang + '/' + code;
        var host = location.hostname;
        document.cookie = 'googtrans=' + val + '; path=/';
        document.cookie = 'googtrans=' + val + '; path=/; domain=.' + host;
        location.reload();
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

        _state.currentLang = _readCookieLang() || _config.defaultLang;
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
        /* Attendre 500ms que GTranslate ait injecté ses liens */
        setTimeout(function () {
            _buildSelector();
            _bindEvents();
        }, 500);
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
