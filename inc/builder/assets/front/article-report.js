/**
 * Boutons de signalement d'un article (hero de single.php), un par type de
 * problème — chargé uniquement pour les administrateurs connectés, voir
 * Schilo\Builder\Front\ArticleReportButton.
 */
(function () {
    'use strict';

    var cfg     = window.schiloArticleReport || {};
    var buttons = document.querySelectorAll('[data-schilo-report]');
    if (!buttons.length || !cfg.ajaxUrl) return;

    var status = document.querySelector('[data-schilo-report-status]');

    function setStatus(text) {
        if (status) status.textContent = text;
    }

    Array.prototype.forEach.call(buttons, function (btn) {
        var label = btn.querySelector('[data-schilo-report-label]');
        var busy  = false;

        btn.addEventListener('click', function () {
            if (busy) return;
            busy = true;
            btn.setAttribute('aria-busy', 'true');

            var body = new URLSearchParams({
                action:  cfg.action,
                nonce:   cfg.nonce,
                post_id: btn.dataset.postId,
                type:    btn.dataset.type
            });

            fetch(cfg.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body })
                .then(function (r) { return r.json(); })
                .then(function (json) {
                    if (!json || !json.success) throw new Error('report failed');

                    var reported = !!json.data.reported;
                    btn.classList.toggle('is-reported', reported);
                    if (label) {
                        label.textContent = reported ? btn.dataset.labelReported : btn.dataset.labelReport;
                    }
                    setStatus(reported ? cfg.statusReported : cfg.statusRemoved);
                })
                .catch(function () {
                    setStatus(cfg.statusError);
                })
                .then(function () {
                    busy = false;
                    btn.removeAttribute('aria-busy');
                });
        });
    });
})();
