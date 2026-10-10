/**
 * Bouton « Signaler l'article » (hero de single.php) — chargé uniquement
 * pour les administrateurs connectés, voir
 * Schilo\Builder\Front\ArticleReportButton.
 */
(function () {
    'use strict';

    var cfg = window.schiloArticleReport || {};
    var btn = document.querySelector('[data-schilo-report]');
    if (!btn || !cfg.ajaxUrl) return;

    var label  = btn.querySelector('[data-schilo-report-label]');
    var status = document.querySelector('[data-schilo-report-status]');
    var busy   = false;

    function setStatus(text) {
        if (status) status.textContent = text;
    }

    btn.addEventListener('click', function () {
        if (busy) return;
        busy = true;
        btn.setAttribute('aria-busy', 'true');

        var body = new URLSearchParams({
            action:  cfg.action,
            nonce:   cfg.nonce,
            post_id: btn.dataset.postId
        });

        fetch(cfg.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body })
            .then(function (r) { return r.json(); })
            .then(function (json) {
                if (!json || !json.success) throw new Error('report failed');

                var reported = !!json.data.reported;
                btn.classList.toggle('is-reported', reported);
                if (label) label.textContent = reported ? cfg.labelReported : cfg.labelReport;
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
})();
