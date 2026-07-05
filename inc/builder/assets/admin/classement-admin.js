jQuery(function ($) {
    'use strict';

    if (typeof window.sclData === 'undefined') return;

    var nonce = window.sclData.nonce;
    var ajaxUrl = window.sclData.ajaxUrl;

    function showFeedback($el, message, isError) {
        $el.text(message)
            .css('color', isError ? '#dc2626' : '#059669')
            .show();
    }

    function esc(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    /* ---- Page validation : "Classer via IA" -------------------- */
    $(document).on('click', '#scl-btn-classify', function () {
        var $btn = $(this);
        var postId = $btn.data('post-id');
        var provider = $('#scl-provider-select').val();
        var $box = $('#scl-ia-suggestion');

        $btn.prop('disabled', true).text('Analyse en cours...');
        $box.hide();

        $.post(ajaxUrl, {
            action: 'schilo_classement_classify',
            nonce: nonce,
            post_id: postId,
            provider: provider
        }).done(function (res) {
            $btn.prop('disabled', false).text('Classer via IA (suggestion)');
            if (!res.success) {
                $box.html('<p style="color:#dc2626;">' + (res.data && res.data.message ? res.data.message : 'Erreur IA.') + '</p>').show();
                return;
            }
            var s = res.data.suggestion || {};
            var parcours = Array.isArray(s.parcours) ? s.parcours.join(', ') : (s.parcours || '');
            $box.html(
                '<p><strong>Suggestion IA</strong> — cochez/complétez manuellement les cases correspondantes ci-dessous :</p>' +
                '<p>Thème : ' + (s.theme || '—') + '</p>' +
                '<p>Sous-thème : ' + (s.sous_theme || '—') + '</p>' +
                '<p>Parcours : ' + (parcours || '—') + '</p>' +
                '<p>Série : ' + (s.serie || '—') + '</p>' +
                '<p>Ordre suggéré : ' + (s.ordre || 0) + '</p>'
            ).show();
        }).fail(function () {
            $btn.prop('disabled', false).text('Classer via IA (suggestion)');
            $box.html('<p style="color:#dc2626;">Erreur réseau.</p>').show();
        });
    });

    /* ---- Page validation : enregistrer le classement ------------ */
    $(document).on('submit', '#scl-classement-form', function (e) {
        e.preventDefault();
        var $form = $(this);
        var $feedback = $('#scl-val-feedback');
        var $submit = $form.find('button[type="submit"]');

        $submit.prop('disabled', true);
        showFeedback($feedback, 'Enregistrement...', false);

        var data = $form.serialize() + '&action=schilo_classement_save&nonce=' + encodeURIComponent(nonce);

        $.post(ajaxUrl, data).done(function (res) {
            $submit.prop('disabled', false);
            if (!res.success) {
                showFeedback($feedback, (res.data && res.data.message) || 'Échec.', true);
                return;
            }
            showFeedback($feedback, 'Classement enregistré.', false);
            setTimeout(function () {
                if (window.sclData.backUrl) window.location.href = window.sclData.backUrl;
            }, 700);
        }).fail(function () {
            $submit.prop('disabled', false);
            showFeedback($feedback, 'Erreur réseau.', true);
        });
    });

    /* ---- Page liste : selection des lignes ------------------------ */
    $(document).on('change', '#scl-check-all', function () {
        $('.scl-row-check').prop('checked', this.checked);
    });

    $('#scl-btn-select-all').on('click', function () {
        var allChecked = $('.scl-row-check:checked').length === $('.scl-row-check').length;
        $('.scl-row-check').prop('checked', !allChecked);
        $('#scl-check-all').prop('checked', !allChecked);
    });

    /* ---- Page liste : classement en lot ---------------------------- */
    $('#scl-btn-batch-ia').on('click', function () {
        var ids = [];
        var provider = $('#scl-batch-provider').val() || 'claude';
        $('.scl-row-check:checked').each(function () { ids.push($(this).val()); });

        if (!ids.length) { window.alert('Sélectionnez au moins un article.'); return; }
        if (!window.confirm('Classer ' + ids.length + ' article(s) via ' + provider + ' ?\nCela peut prendre plusieurs minutes.')) return;

        var $btn = $(this);
        var $feedback = $('#scl-batch-feedback');
        $btn.prop('disabled', true);
        showFeedback($feedback, 'Classement en cours (' + ids.length + ' articles)...', false);

        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            timeout: 300000,
            data: {
                action: 'schilo_classement_classify_batch',
                nonce: nonce,
                post_ids: ids,
                provider: provider
            }
        }).done(function (res) {
            $btn.prop('disabled', false);
            if (!res.success) {
                showFeedback($feedback, (res.data && res.data.message) || 'Erreur batch.', true);
                return;
            }
            var ok = (res.data.ok || []).length;
            var err = (res.data.error || []).length;
            var msg = res.data.auto_mode
                ? ok + ' article(s) classé(s) automatiquement' + (err ? ', ' + err + ' erreur(s)' : '') + '.'
                : ok + ' suggestion(s) générée(s), à valider individuellement' + (err ? ' (' + err + ' erreur(s))' : '') + '.';
            showFeedback($feedback, msg, false);
            setTimeout(function () { window.location.reload(); }, 1200);
        }).fail(function () {
            $btn.prop('disabled', false);
            showFeedback($feedback, 'Erreur réseau.', true);
        });
    });

    /* ---- Page termes : suggestion de vocabulaire via IA ------------ */
    $('#scl-btn-propose-terms').on('click', function () {
        var $btn = $(this);
        var provider = $('#scl-curation-provider').val() || 'claude';
        var $feedback = $('#scl-curation-feedback');
        var $preview = $('#scl-curation-preview');

        $btn.prop('disabled', true);
        showFeedback($feedback, 'Analyse en cours (peut prendre 1 à 2 minutes)...', false);
        $preview.hide().empty();

        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            timeout: 180000,
            data: { action: 'schilo_classement_propose_terms', nonce: nonce, provider: provider }
        }).done(function (res) {
            $btn.prop('disabled', false);
            if (!res.success) {
                showFeedback($feedback, (res.data && res.data.message) || 'Erreur IA.', true);
                return;
            }
            $feedback.hide();
            renderCurationPreview(res.data.suggestion || {});
        }).fail(function () {
            $btn.prop('disabled', false);
            showFeedback($feedback, 'Erreur réseau.', true);
        });
    });

    var taxLabels = { schilo_theme: 'Thèmes', schilo_parcours: 'Parcours', schilo_serie: 'Séries' };

    function renderCurationPreview(suggestion) {
        window.sclCurationSuggestion = suggestion;
        var html = '<p><strong>Suggestion IA</strong> — décochez ce que vous ne voulez pas créer, puis validez. '
            + 'Les termes qui existent déjà (même nom) seront simplement réutilisés, jamais dupliqués.</p>';

        ['schilo_theme', 'schilo_parcours'].forEach(function (tax) {
            var items = suggestion[tax] || [];
            html += '<div style="margin-top:10px;"><strong>' + taxLabels[tax] + '</strong>';
            items.forEach(function (item, pIndex) {
                html += '<label class="scl-term-row"><input type="checkbox" class="scl-curation-parent" checked data-tax="' + tax + '" data-p="' + pIndex + '"> <strong>' + esc(item.name || '') + '</strong></label>';
                (item.children || []).forEach(function (child, cIndex) {
                    html += '<label class="scl-term-row scl-term-child"><input type="checkbox" class="scl-curation-child" checked data-tax="' + tax + '" data-p="' + pIndex + '" data-c="' + cIndex + '"> ' + esc(child) + '</label>';
                });
            });
            html += '</div>';
        });

        var series = suggestion.schilo_serie || [];
        html += '<div style="margin-top:10px;"><strong>' + taxLabels.schilo_serie + '</strong>';
        series.forEach(function (name, pIndex) {
            html += '<label class="scl-term-row"><input type="checkbox" class="scl-curation-serie" checked data-p="' + pIndex + '"> ' + esc(name) + '</label>';
        });
        html += '</div>';

        html += '<div style="margin-top:14px;"><button type="button" id="scl-btn-apply-terms" class="button button-primary">Créer les termes sélectionnés</button></div>';

        $('#scl-curation-preview').html(html).show();
    }

    $(document).on('click', '#scl-btn-apply-terms', function () {
        var suggestion = window.sclCurationSuggestion || {};
        var filtered = { schilo_theme: [], schilo_parcours: [], schilo_serie: [] };

        ['schilo_theme', 'schilo_parcours'].forEach(function (tax) {
            (suggestion[tax] || []).forEach(function (item, pIndex) {
                var checked = $('.scl-curation-parent[data-tax="' + tax + '"][data-p="' + pIndex + '"]').prop('checked');
                if (!checked) return;
                var children = [];
                (item.children || []).forEach(function (child, cIndex) {
                    var cChecked = $('.scl-curation-child[data-tax="' + tax + '"][data-p="' + pIndex + '"][data-c="' + cIndex + '"]').prop('checked');
                    if (cChecked) children.push(child);
                });
                filtered[tax].push({ name: item.name, children: children });
            });
        });

        (suggestion.schilo_serie || []).forEach(function (name, pIndex) {
            var checked = $('.scl-curation-serie[data-p="' + pIndex + '"]').prop('checked');
            if (checked) filtered.schilo_serie.push(name);
        });

        var $btn = $(this);
        var $feedback = $('#scl-curation-feedback');
        $btn.prop('disabled', true);
        showFeedback($feedback, 'Création des termes...', false);

        $.post(ajaxUrl, {
            action: 'schilo_classement_apply_terms',
            nonce: nonce,
            suggestion: JSON.stringify(filtered)
        }).done(function (res) {
            $btn.prop('disabled', false);
            if (!res.success) {
                showFeedback($feedback, (res.data && res.data.message) || 'Échec.', true);
                return;
            }
            var d = res.data || {};
            var msg = 'Termes créés/réutilisés : ' + (d.schilo_theme || 0) + ' thème(s), ' + (d.schilo_parcours || 0) + ' parcours, ' + (d.schilo_serie || 0) + ' série(s).';
            if (d.errors && d.errors.length) msg += ' — ' + d.errors.length + ' erreur(s).';
            showFeedback($feedback, msg, false);
            setTimeout(function () { window.location.reload(); }, 1500);
        }).fail(function () {
            $btn.prop('disabled', false);
            showFeedback($feedback, 'Erreur réseau.', true);
        });
    });

    /* ---- Page termes : mise a jour de l'ordre -------------------- */
    $(document).on('change', '.scl-term-ordre', function () {
        var $input = $(this);
        var termId = $input.data('term-id');
        $.post(ajaxUrl, {
            action: 'schilo_classement_save_term_order',
            nonce: nonce,
            term_id: termId,
            ordre: $input.val()
        });
    });

    /* ---- Page termes : supprimer un terme ------------------------ */
    $(document).on('click', '.scl-btn-delete-term', function () {
        if (!window.confirm('Supprimer ce terme ? Les articles qui y sont classés perdront cette association.')) return;
        var $btn = $(this);
        var termId = $btn.data('term-id');
        var taxonomy = $btn.data('taxonomy');

        $.post(ajaxUrl, {
            action: 'schilo_classement_delete_term',
            nonce: nonce,
            term_id: termId,
            taxonomy: taxonomy
        }).done(function (res) {
            if (res.success) {
                $btn.closest('tr').fadeOut(200, function () { $(this).remove(); });
            } else {
                window.alert((res.data && res.data.message) || 'Suppression impossible.');
            }
        });
    });

    /* ---- Page termes : ajouter un terme --------------------------- */
    $(document).on('click', '#scl-btn-add-term', function () {
        var $btn = $(this);
        var taxonomy = $btn.data('taxonomy');
        var name = $('#scl-new-term-name').val();
        var parent = $('#scl-new-term-parent').length ? $('#scl-new-term-parent').val() : 0;
        var $feedback = $('#scl-terms-feedback');

        if (!name) {
            showFeedback($feedback, 'Nom requis.', true);
            return;
        }

        $btn.prop('disabled', true);
        $.post(ajaxUrl, {
            action: 'schilo_classement_save_term',
            nonce: nonce,
            taxonomy: taxonomy,
            name: name,
            parent: parent,
            ordre: 0
        }).done(function (res) {
            $btn.prop('disabled', false);
            if (!res.success) {
                showFeedback($feedback, (res.data && res.data.message) || 'Échec.', true);
                return;
            }
            window.location.reload();
        }).fail(function () {
            $btn.prop('disabled', false);
            showFeedback($feedback, 'Erreur réseau.', true);
        });
    });
});
