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

    /* Notice persistante en haut de la liste — reste jusqu'au clic (X) */
    function noticePersist($before, msg, isError) {
        $('.scl-notice-persist').remove();
        var cls   = isError ? 'notice-error' : 'notice-success';
        var color = isError ? '#991b1b' : '#166534';
        var $n = $('<div class="scl-notice-persist notice ' + cls + '" style="margin:8px 0;padding:10px 14px;position:relative;">'
            + '<button type="button" style="position:absolute;top:6px;right:10px;background:none;border:none;font-size:16px;cursor:pointer;color:' + color + ';" onclick="jQuery(this).parent().remove();">&times;</button>'
            + '<span>' + msg + '</span></div>');
        $before.before($n);
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
        var $table = $('#scl-articles-table');
        $btn.prop('disabled', true);
        noticePersist($table, 'Classement en cours (' + ids.length + ' article(s))... ne fermez pas cette page.', false);

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
                noticePersist($table, (res.data && res.data.message) || 'Erreur batch.', true);
                return;
            }
            var okIds = res.data.ok || [];
            var errList = res.data.error || [];
            var autoMode = !!res.data.auto_mode;

            okIds.forEach(function (postId) {
                var $row = $table.find('tr[data-post-id="' + postId + '"]');
                if (!$row.length) return;
                var $statusCell = $row.find('td').eq(5);
                if (autoMode) {
                    $statusCell.html('<span class="scl-badge scl-badge-green">Classé</span>');
                } else {
                    $statusCell.html('<span class="scl-badge scl-badge-orange" title="Une suggestion IA a ete generee (classement en lot) et attend votre validation.">Suggestion prête</span>');
                    $row.find('.scl-action-link').addClass('button-primary').text('Valider');
                }
                $row.css('background', '#fef9e7');
                setTimeout(function () { $row.css('background', ''); }, 2500);
            });

            var msg = autoMode
                ? '<strong>' + okIds.length + ' article(s) classé(s) automatiquement.</strong>'
                : '<strong>' + okIds.length + ' suggestion(s) générée(s)</strong> — repérables au badge orange "Suggestion prête", à valider individuellement (bouton "Valider").';
            if (errList.length) msg += ' — ' + errList.length + ' erreur(s) : ' + errList.map(function (e) { return e.msg; }).join(' ; ');
            noticePersist($table, msg, errList.length > 0 && okIds.length === 0);
        }).fail(function () {
            $btn.prop('disabled', false);
            noticePersist($table, 'Erreur réseau.', true);
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

    function itemName(item) { return typeof item === 'object' && item ? (item.name || '') : String(item || ''); }
    function itemDescription(item) { return typeof item === 'object' && item ? (item.description || '') : ''; }

    function renderCurationPreview(suggestion) {
        window.sclCurationSuggestion = suggestion;
        var html = '<p><strong>Suggestion IA</strong> — décochez ce que vous ne voulez pas créer, puis validez. '
            + 'Les termes qui existent déjà (même nom) seront simplement réutilisés (leur description sera mise à jour si besoin), jamais dupliqués.</p>';

        ['schilo_theme', 'schilo_parcours'].forEach(function (tax) {
            var items = suggestion[tax] || [];
            html += '<div style="margin-top:10px;"><strong>' + taxLabels[tax] + '</strong>';
            items.forEach(function (item, pIndex) {
                var desc = itemDescription(item);
                html += '<label class="scl-term-row"><input type="checkbox" class="scl-curation-parent" checked data-tax="' + tax + '" data-p="' + pIndex + '"> <strong>' + esc(itemName(item)) + '</strong></label>';
                if (desc) html += '<p class="scl-curation-desc">' + esc(desc) + '</p>';
                (item.children || []).forEach(function (child, cIndex) {
                    var cDesc = itemDescription(child);
                    html += '<label class="scl-term-row scl-term-child"><input type="checkbox" class="scl-curation-child" checked data-tax="' + tax + '" data-p="' + pIndex + '" data-c="' + cIndex + '"> ' + esc(itemName(child)) + '</label>';
                    if (cDesc) html += '<p class="scl-curation-desc scl-curation-desc--child">' + esc(cDesc) + '</p>';
                });
            });
            html += '</div>';
        });

        var series = suggestion.schilo_serie || [];
        html += '<div style="margin-top:10px;"><strong>' + taxLabels.schilo_serie + '</strong>';
        series.forEach(function (item, pIndex) {
            var desc = itemDescription(item);
            html += '<label class="scl-term-row"><input type="checkbox" class="scl-curation-serie" checked data-p="' + pIndex + '"> ' + esc(itemName(item)) + '</label>';
            if (desc) html += '<p class="scl-curation-desc">' + esc(desc) + '</p>';
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
                    if (cChecked) children.push({ name: itemName(child), description: itemDescription(child) });
                });
                filtered[tax].push({ name: itemName(item), description: itemDescription(item), children: children });
            });
        });

        (suggestion.schilo_serie || []).forEach(function (item, pIndex) {
            var checked = $('.scl-curation-serie[data-p="' + pIndex + '"]').prop('checked');
            if (checked) filtered.schilo_serie.push({ name: itemName(item), description: itemDescription(item) });
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

    /* ---- Page termes : mise a jour de la description -------------- */
    $(document).on('blur', '.scl-term-description', function () {
        var $textarea = $(this);
        $.post(ajaxUrl, {
            action: 'schilo_classement_save_term_description',
            nonce: nonce,
            term_id: $textarea.data('term-id'),
            taxonomy: $textarea.data('taxonomy'),
            description: $textarea.val()
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
        var description = $('#scl-new-term-description').val();
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
            description: description,
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
