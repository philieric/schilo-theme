/**
 * Overlay de traitement IA partagé (grisement plein écran + spinner).
 *
 * Reprend l'apparence de l'overlay de la page Indexation et l'expose comme
 * helper réutilisable sur toutes les pages où l'IA génère quelque chose
 * (classement, définitions, description de catégorie…), pour un retour visuel
 * homogène pendant les appels IA (qui peuvent durer plusieurs minutes).
 *
 * API : window.SchiloAiOverlay.show(msg) / .update(msg) / .hide()
 * Sans dépendance jQuery : sûr à charger sur n'importe quel écran admin.
 */
(function () {
    'use strict';

    var overlay = null;
    var textEl = null;

    function ensure() {
        if (overlay) {
            return;
        }
        overlay = document.createElement('div');
        overlay.className = 'schilo-ai-overlay';
        overlay.setAttribute('role', 'alert');
        overlay.setAttribute('aria-live', 'assertive');
        overlay.style.display = 'none';

        var box = document.createElement('div');
        box.className = 'schilo-ai-overlay__box';

        var spinner = document.createElement('span');
        spinner.className = 'spinner is-active';
        spinner.style.cssText = 'float:none;margin:0;';

        textEl = document.createElement('span');
        textEl.className = 'schilo-ai-overlay__text';

        box.appendChild(spinner);
        box.appendChild(textEl);
        overlay.appendChild(box);
        document.body.appendChild(overlay);
    }

    window.SchiloAiOverlay = {
        show: function (msg) {
            ensure();
            textEl.textContent = msg || 'Traitement IA en cours…';
            overlay.style.display = 'flex';
        },
        update: function (msg) {
            ensure();
            textEl.textContent = msg || '';
        },
        hide: function () {
            if (overlay) {
                overlay.style.display = 'none';
            }
        }
    };
})();
