/**
 * Schilo.Polyfills
 * Namespace : Schilo
 * Classe    : Schilo.Polyfills
 *
 * Polyfills ES5-compatible pour IE11, anciens Safari, Firefox, Android WebView.
 * Chargé dans le <head> avant tout autre script.
 */

/* ── Namespace global Schilo ── */
var Schilo = Schilo || {};

/* ── Schilo.Polyfills ── */
Schilo.Polyfills = (function () {

    'use strict';

    /* ────────────────────────────────────────────
       MÉTHODES PRIVÉES (helpers internes)
    ──────────────────────────────────────────── */

    function _define(obj, name, value) {
        if (!obj[name]) {
            try {
                Object.defineProperty(obj, name, {
                    value       : value,
                    configurable: true,
                    writable    : true,
                    enumerable  : false
                });
            } catch (e) {
                obj[name] = value;
            }
        }
    }

    /* ────────────────────────────────────────────
       MÉTHODES PUBLIQUES D'INSTALLATION
    ──────────────────────────────────────────── */

    /**
     * Element.closest()
     * IE11, anciens Android
     */
    function installClosest() {
        if (Element.prototype.closest) return;
        _define(Element.prototype, 'closest', function (selector) {
            var el = this;
            while (el && el.nodeType === 1) {
                if (el.matches ? el.matches(selector) : el.msMatchesSelector(selector)) {
                    return el;
                }
                el = el.parentElement || el.parentNode;
            }
            return null;
        });
    }

    /**
     * Element.matches()
     * IE11
     */
    function installMatches() {
        if (Element.prototype.matches) return;
        Element.prototype.matches =
            Element.prototype.msMatchesSelector  ||
            Element.prototype.webkitMatchesSelector;
    }

    /**
     * NodeList.forEach()
     * IE11, Edge ≤ 15
     */
    function installNodeListForEach() {
        if (typeof NodeList !== 'undefined' &&
            NodeList.prototype &&
            !NodeList.prototype.forEach) {
            NodeList.prototype.forEach = Array.prototype.forEach;
        }
    }

    /**
     * Array.prototype.find()
     * IE11
     */
    function installArrayFind() {
        if (Array.prototype.find) return;
        _define(Array.prototype, 'find', function (predicate, ctx) {
            for (var i = 0; i < this.length; i++) {
                if (predicate.call(ctx, this[i], i, this)) return this[i];
            }
            return undefined;
        });
    }

    /**
     * Array.from()
     * IE11
     */
    function installArrayFrom() {
        if (Array.from) return;
        Array.from = function (arrayLike) {
            return Array.prototype.slice.call(arrayLike);
        };
    }

    /**
     * Object.assign()
     * IE11
     */
    function installObjectAssign() {
        if (Object.assign) return;
        Object.assign = function (target) {
            for (var i = 1; i < arguments.length; i++) {
                var src = arguments[i];
                if (src) {
                    for (var key in src) {
                        if (Object.prototype.hasOwnProperty.call(src, key)) {
                            target[key] = src[key];
                        }
                    }
                }
            }
            return target;
        };
    }

    /**
     * Object.keys()
     * IE8
     */
    function installObjectKeys() {
        if (Object.keys) return;
        Object.keys = function (obj) {
            var keys = [];
            for (var k in obj) {
                if (Object.prototype.hasOwnProperty.call(obj, k)) keys.push(k);
            }
            return keys;
        };
    }

    /**
     * String.prototype.includes()
     * IE11
     */
    function installStringIncludes() {
        if (String.prototype.includes) return;
        _define(String.prototype, 'includes', function (search, start) {
            return this.indexOf(search, start || 0) !== -1;
        });
    }

    /**
     * String.prototype.startsWith()
     * IE11
     */
    function installStringStartsWith() {
        if (String.prototype.startsWith) return;
        _define(String.prototype, 'startsWith', function (search, pos) {
            pos = pos || 0;
            return this.substring(pos, pos + search.length) === search;
        });
    }

    /**
     * String.prototype.endsWith()
     * IE11
     */
    function installStringEndsWith() {
        if (String.prototype.endsWith) return;
        _define(String.prototype, 'endsWith', function (search, len) {
            if (len === undefined || len > this.length) len = this.length;
            return this.substring(len - search.length, len) === search;
        });
    }

    /**
     * CustomEvent
     * IE11
     */
    function installCustomEvent() {
        if (typeof window.CustomEvent === 'function') return;
        function CustomEvent(event, params) {
            params = params || { bubbles: false, cancelable: false, detail: null };
            var evt = document.createEvent('CustomEvent');
            evt.initCustomEvent(event, params.bubbles, params.cancelable, params.detail);
            return evt;
        }
        CustomEvent.prototype = window.Event.prototype;
        window.CustomEvent = CustomEvent;
    }

    /**
     * PointerEvent
     * Anciens Safari / Firefox
     */
    function installPointerEvent() {
        if (typeof window.PointerEvent !== 'undefined') return;
        window.PointerEvent = window.MouseEvent;
    }

    /**
     * requestAnimationFrame
     * Anciens navigateurs
     */
    function installRAF() {
        var lastTime = 0;
        var vendors  = ['ms', 'moz', 'webkit', 'o'];

        for (var i = 0; i < vendors.length && !window.requestAnimationFrame; i++) {
            window.requestAnimationFrame = window[vendors[i] + 'RequestAnimationFrame'];
            window.cancelAnimationFrame  = window[vendors[i] + 'CancelAnimationFrame'] ||
                                           window[vendors[i] + 'CancelRequestAnimationFrame'];
        }

        if (!window.requestAnimationFrame) {
            window.requestAnimationFrame = function (callback) {
                var now  = new Date().getTime();
                var next = Math.max(0, 16 - (now - lastTime));
                var id   = setTimeout(function () { callback(now + next); }, next);
                lastTime = now + next;
                return id;
            };
        }

        if (!window.cancelAnimationFrame) {
            window.cancelAnimationFrame = function (id) { clearTimeout(id); };
        }
    }

    /**
     * IntersectionObserver
     * Safari < 12.1, IE
     */
    function installIntersectionObserver() {
        if ('IntersectionObserver' in window) return;
        window.IntersectionObserver = function (callback) {
            return {
                observe   : function (el) { callback([{ isIntersecting: true, target: el }]); },
                unobserve : function () {},
                disconnect: function () {}
            };
        };
    }

    /**
     * dataset
     * IE10
     */
    function installDataset() {
        if ('dataset' in document.createElement('div')) return;
        Object.defineProperty(HTMLElement.prototype, 'dataset', {
            get: function () {
                var attrs = this.attributes;
                var map   = {};
                for (var i = 0; i < attrs.length; i++) {
                    var a = attrs[i];
                    if (a.name.indexOf('data-') === 0) {
                        var key = a.name.slice(5).replace(/-([a-z])/g, function (m, c) {
                            return c.toUpperCase();
                        });
                        map[key] = a.value;
                    }
                }
                return map;
            }
        });
    }

    /**
     * classList.toggle second argument
     * IE11
     */
    function installClassListToggle() {
        var testEl = document.createElement('_');
        try {
            testEl.classList.toggle('c', false);
            if (testEl.classList.contains('c')) {
                var _toggle = DOMTokenList.prototype.toggle;
                DOMTokenList.prototype.toggle = function (token, force) {
                    if (arguments.length > 1 && !this.contains(token) === !force) {
                        return force;
                    }
                    return _toggle.call(this, token);
                };
            }
        } catch (e) {}
    }

    /**
     * Smooth scroll
     * Safari < 15.4, IE
     */
    function installSmoothScroll() {
        if ('scrollBehavior' in document.documentElement.style) return;
        document.addEventListener('click', function (e) {
            var anchor = e.target.closest ? e.target.closest('a[href^="#"]') : null;
            if (!anchor) return;
            var target = document.querySelector(anchor.getAttribute('href'));
            if (!target) return;
            e.preventDefault();
            target.scrollIntoView({ block: 'start' });
        });
    }

    /**
     * focus-visible (polyfill léger)
     * Tous navigateurs
     */
    function installFocusVisible() {
        var usedKeyboard = false;
        document.addEventListener('keydown', function ()    { usedKeyboard = true;  });
        document.addEventListener('mousedown', function ()  { usedKeyboard = false; });
        document.addEventListener('pointerdown', function () { usedKeyboard = false; });
        document.addEventListener('focusin', function (e) {
            if (usedKeyboard) e.target.setAttribute('data-focus-visible', '');
        });
        document.addEventListener('focusout', function (e) {
            e.target.removeAttribute('data-focus-visible');
        });
    }

    /**
     * CSS Custom Properties — détection
     * IE11
     */
    function installCSSVarsDetection() {
        var support = window.CSS &&
                      window.CSS.supports &&
                      window.CSS.supports('--a', '0');
        if (!support) {
            document.documentElement.className += ' no-css-vars';
        }
    }

    /**
     * fetch() — IE11
     * Fallback minimal via XMLHttpRequest.
     * Couvre les usages POST/GET simples du thème (mark-read AJAX).
     */
    function installFetch() {
        if (typeof window.fetch === 'function') return;
        window.fetch = function (url, options) {
            options = options || {};
            return new Promise(function (resolve, reject) {
                var xhr = new XMLHttpRequest();
                xhr.open(options.method || 'GET', url, true);
                var headers = options.headers || {};
                for (var h in headers) {
                    if (Object.prototype.hasOwnProperty.call(headers, h)) {
                        xhr.setRequestHeader(h, headers[h]);
                    }
                }
                xhr.onload = function () {
                    resolve({
                        ok      : xhr.status >= 200 && xhr.status < 300,
                        status  : xhr.status,
                        json    : function () { return Promise.resolve(JSON.parse(xhr.responseText)); },
                        text    : function () { return Promise.resolve(xhr.responseText); }
                    });
                };
                xhr.onerror = function () { reject(new TypeError('Network error')); };
                xhr.send(options.body || null);
            });
        };
    }

    /**
     * Promise — IE11
     * Implémentation minimale (resolve/reject/then/catch).
     */
    function installPromise() {
        if (typeof window.Promise === 'function') return;
        window.Promise = function (executor) {
            var self     = this;
            self._state  = 'pending';
            self._value  = undefined;
            self._cbs    = [];

            function _resolve(val) {
                if (self._state !== 'pending') return;
                self._state = 'fulfilled';
                self._value = val;
                self._cbs.forEach(function (cb) { setTimeout(function () { cb.onFulfilled(val); }, 0); });
            }
            function _reject(reason) {
                if (self._state !== 'pending') return;
                self._state = 'rejected';
                self._value = reason;
                self._cbs.forEach(function (cb) { setTimeout(function () { cb.onRejected(reason); }, 0); });
            }
            try { executor(_resolve, _reject); } catch (e) { _reject(e); }
        };
        window.Promise.prototype.then = function (onFulfilled, onRejected) {
            var self = this;
            return new window.Promise(function (resolve, reject) {
                function handle(cb, fallback) {
                    return function (val) {
                        try { resolve(cb ? cb(val) : fallback(val)); } catch (e) { reject(e); }
                    };
                }
                var entry = {
                    onFulfilled: handle(onFulfilled, function (v) { return v; }),
                    onRejected : handle(onRejected,  function (r) { throw r; })
                };
                if (self._state === 'fulfilled') { setTimeout(function () { entry.onFulfilled(self._value); }, 0); }
                else if (self._state === 'rejected') { setTimeout(function () { entry.onRejected(self._value); }, 0); }
                else { self._cbs.push(entry); }
            });
        };
        window.Promise.prototype['catch'] = function (onRejected) {
            return this.then(null, onRejected);
        };
        window.Promise.resolve = function (val)    { return new window.Promise(function (r) { r(val); }); };
        window.Promise.reject  = function (reason) { return new window.Promise(function (_, r) { r(reason); }); };
    }

    /* ────────────────────────────────────────────
       MÉTHODE PUBLIQUE : init()
       Lance tous les polyfills dans l'ordre
    ──────────────────────────────────────────── */

    function init() {
        installMatches();
        installClosest();
        installNodeListForEach();
        installArrayFind();
        installArrayFrom();
        installObjectAssign();
        installObjectKeys();
        installStringIncludes();
        installStringStartsWith();
        installStringEndsWith();
        installCustomEvent();
        installPointerEvent();
        installRAF();
        installIntersectionObserver();
        installDataset();
        installClassListToggle();
        installSmoothScroll();
        installFocusVisible();
        installCSSVarsDetection();
        installPromise(); // doit être avant fetch
        installFetch();
    }

    /* ── API publique ── */
    return {
        init              : init,
        installClosest    : installClosest,
        installMatches    : installMatches,
        installCustomEvent: installCustomEvent,
        installRAF        : installRAF,
        installPromise    : installPromise,
        installFetch      : installFetch,
    };

})();

/* ── Auto-init immédiat (avant DOMContentLoaded) ── */
Schilo.Polyfills.init();
