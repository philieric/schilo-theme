/**
 * Boutons de signalement d'un article (hero de single.php), un par type de
 * problème, et note facultative de chaque signalement actif — chargé
 * uniquement pour les administrateurs connectés, voir
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

    function post(params) {
        params.nonce = cfg.nonce;
        return fetch(cfg.ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            body: new URLSearchParams(params)
        })
            .then(function (r) { return r.json(); })
            .then(function (json) {
                if (!json || !json.success) throw new Error('report failed');
                return json.data;
            });
    }

    function noteBlock(type) {
        return document.querySelector('[data-schilo-report-note="' + type + '"]');
    }

    /* ── Signaler / retirer ── */
    Array.prototype.forEach.call(buttons, function (btn) {
        var label = btn.querySelector('[data-schilo-report-label]');
        var busy  = false;

        btn.addEventListener('click', function () {
            if (busy) return;
            busy = true;
            btn.setAttribute('aria-busy', 'true');

            post({ action: cfg.action, post_id: btn.dataset.postId, type: btn.dataset.type })
                .then(function (data) {
                    var reported = !!data.reported;
                    btn.classList.toggle('is-reported', reported);
                    if (label) {
                        label.textContent = reported ? btn.dataset.labelReported : btn.dataset.labelReport;
                    }

                    // La note n'existe que tant que le signalement existe :
                    // le champ apparaît avec lui et repart vide avec lui.
                    var block = noteBlock(btn.dataset.type);
                    if (block) {
                        block.hidden = !reported;
                        var input = block.querySelector('input');
                        if (input) input.value = '';
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

    /* ── Note facultative ── */
    Array.prototype.forEach.call(document.querySelectorAll('[data-schilo-report-note]'), function (block) {
        var input = block.querySelector('input');
        var save  = block.querySelector('[data-schilo-report-note-save]');
        if (!input || !save) return;

        var busy = false;

        function saveNote() {
            if (busy) return;
            busy = true;
            save.setAttribute('aria-busy', 'true');

            post({
                action:  cfg.noteAction,
                post_id: block.dataset.postId,
                type:    block.dataset.schiloReportNote,
                note:    input.value
            })
                .then(function (data) {
                    input.value = data.note;
                    setStatus(cfg.statusNote);
                })
                .catch(function () {
                    setStatus(cfg.statusError);
                })
                .then(function () {
                    busy = false;
                    save.removeAttribute('aria-busy');
                });
        }

        save.addEventListener('click', saveNote);
        input.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                saveNote();
            }
        });
    });
})();
