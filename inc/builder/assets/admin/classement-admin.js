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

    /* Selection par plage (Maj+clic) : coche/decoche toutes les lignes
       entre la derniere case cochee manuellement et celle-ci. */
    var lastRowCheck = null;
    $(document).on('click', '.scl-row-check', function (e) {
        var $boxes = $('.scl-row-check');
        if (e.shiftKey && lastRowCheck) {
            var start = $boxes.index(lastRowCheck);
            var end = $boxes.index(this);
            if (start > -1 && end > -1) {
                if (start > end) { var tmp = start; start = end; end = tmp; }
                $boxes.slice(start, end + 1).prop('checked', this.checked);
            }
        }
        lastRowCheck = this;
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
            var currentStatut = (window.sclData && window.sclData.statut) || '';

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

                if (autoMode && currentStatut === 'non_classe') {
                    // Cet article vient d'etre classe : il n'appartient plus a la vue "Non classes".
                    setTimeout(function () {
                        $row.fadeOut(400, function () {
                            $row.remove();
                            if (!$table.find('tbody tr').length) {
                                $table.replaceWith('<p class="scl-empty">Aucun article indexé (valide) à afficher pour ce filtre.</p>');
                            }
                        });
                    }, 1200);
                } else {
                    setTimeout(function () { $row.css('background', ''); }, 2500);
                }
            });

            // Compteurs "Classes" / "Non classes" en tete de page.
            if (autoMode && okIds.length) {
                var $classeNum = $('.scl-stat-card[data-statut="classe"] .scl-stat-num');
                var $nonClasseNum = $('.scl-stat-card[data-statut="non_classe"] .scl-stat-num');
                if ($classeNum.length) $classeNum.text((parseInt($classeNum.text(), 10) || 0) + okIds.length);
                if ($nonClasseNum.length) $nonClasseNum.text(Math.max(0, (parseInt($nonClasseNum.text(), 10) || 0) - okIds.length));
            }

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

    /* ---- Page termes : suggestion de vocabulaire via IA ------------
       En 2 temps pour rester rapide/fiable : 1) structure seule (noms +
       hierarchie, un appel rapide par taxonomie), 2) descriptions par
       petits lots sequentiels (5-6 termes/appel) avec progression visible.
       Un seul appel demandant tout (60+ termes x 150-250 mots) saturait
       la reponse ou depassait le temps d'attente d'un appel HTTP bloquant. */
    var taxLabels = { schilo_theme: 'Thèmes', schilo_parcours: 'Parcours', schilo_serie: 'Séries' };
    var DESC_BATCH_SIZE = 6;

    function itemName(item) { return typeof item === 'object' && item ? (item.name || '') : String(item || ''); }
    function itemDescription(item) { return typeof item === 'object' && item ? (item.description || '') : ''; }

    // Un terme deja genere via IA (item.generated_at rempli par le PHP, voir
    // ClassementService::proposeTermStructure) n'est pas remis dans la file :
    // conserve sa description existante telle quelle, evite un aller-retour
    // IA inutile. Le bouton individuel "Générer via IA" reste le moyen de
    // forcer une regeneration ponctuelle.
    function needsGeneration(item) { return !itemGeneratedAt(item); }
    function itemGeneratedAt(item) { return typeof item === 'object' && item ? (item.generated_at || '') : ''; }

    function buildDescriptionQueue(structure) {
        var queue = [];
        ['schilo_theme', 'schilo_parcours'].forEach(function (tax) {
            var names = [];
            (structure[tax] || []).forEach(function (item) {
                if (needsGeneration(item)) names.push(itemName(item));
                (item.children || []).forEach(function (child) {
                    if (needsGeneration(child)) names.push(itemName(child));
                });
            });
            for (var i = 0; i < names.length; i += DESC_BATCH_SIZE) {
                queue.push({ tax: tax, names: names.slice(i, i + DESC_BATCH_SIZE) });
            }
        });
        var serieNames = (structure.schilo_serie || []).filter(needsGeneration).map(itemName);
        for (var j = 0; j < serieNames.length; j += DESC_BATCH_SIZE) {
            queue.push({ tax: 'schilo_serie', names: serieNames.slice(j, j + DESC_BATCH_SIZE) });
        }
        return queue;
    }

    function applyDescriptionMap(structure, descMap) {
        // Seuls les termes effectivement traites (presents dans descMap) sont
        // mis a jour ; les termes sautes gardent la description que le PHP a
        // deja posee dans la structure (existing.description).
        ['schilo_theme', 'schilo_parcours'].forEach(function (tax) {
            (structure[tax] || []).forEach(function (item) {
                var key = tax + '::' + itemName(item);
                if (Object.prototype.hasOwnProperty.call(descMap, key)) item.description = descMap[key];
                (item.children || []).forEach(function (child) {
                    var ckey = tax + '::' + itemName(child);
                    if (Object.prototype.hasOwnProperty.call(descMap, ckey)) child.description = descMap[ckey];
                });
            });
        });
        (structure.schilo_serie || []).forEach(function (item) {
            var key = 'schilo_serie::' + itemName(item);
            if (Object.prototype.hasOwnProperty.call(descMap, key)) item.description = descMap[key];
        });
    }

    function runDescriptionBatches(structure, provider, $btn, $feedback) {
        var queue = buildDescriptionQueue(structure);
        var total = queue.length;
        var descMap = {};

        function next(index) {
            if (index >= total) {
                applyDescriptionMap(structure, descMap);
                $feedback.hide();
                renderCurationPreview(structure);
                $btn.prop('disabled', false);
                return;
            }

            var batch = queue[index];
            showFeedback($feedback, 'Génération des descriptions… lot ' + (index + 1) + '/' + total + ' (' + (taxLabels[batch.tax] || batch.tax) + ')', false);

            $.ajax({
                url: ajaxUrl,
                type: 'POST',
                timeout: 90000,
                data: {
                    action: 'schilo_classement_propose_term_descriptions',
                    nonce: nonce,
                    provider: provider,
                    taxonomy: batch.tax,
                    names: JSON.stringify(batch.names)
                }
            }).done(function (res) {
                if (!res.success) {
                    $btn.prop('disabled', false);
                    showFeedback($feedback, 'Lot ' + (index + 1) + '/' + total + ' échoué : ' + ((res.data && res.data.message) || 'Erreur IA.'), true);
                    return;
                }
                var descs = (res.data && res.data.descriptions) || {};
                Object.keys(descs).forEach(function (name) {
                    descMap[batch.tax + '::' + name] = descs[name];
                });
                next(index + 1);
            }).fail(function () {
                $btn.prop('disabled', false);
                showFeedback($feedback, 'Erreur réseau au lot ' + (index + 1) + '/' + total + '.', true);
            });
        }

        next(0);
    }

    $('#scl-btn-propose-terms').on('click', function () {
        var $btn = $(this);
        var provider = $('#scl-curation-provider').val() || 'claude';
        var $feedback = $('#scl-curation-feedback');
        var $preview = $('#scl-curation-preview');

        $btn.prop('disabled', true);
        showFeedback($feedback, 'Récupération de la structure (parcours/thèmes/séries)...', false);
        $preview.hide().empty();

        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            timeout: 180000,
            data: { action: 'schilo_classement_propose_term_structure', nonce: nonce, provider: provider }
        }).done(function (res) {
            if (!res.success) {
                $btn.prop('disabled', false);
                showFeedback($feedback, (res.data && res.data.message) || 'Erreur IA.', true);
                return;
            }
            runDescriptionBatches(res.data.structure || {}, provider, $btn, $feedback);
        }).fail(function () {
            $btn.prop('disabled', false);
            showFeedback($feedback, 'Erreur réseau (structure).', true);
        });
    });

    function renderCurationPreview(suggestion) {
        window.sclCurationSuggestion = suggestion;
        var html = '<div style="background:#fff8e1;border:1px solid #f0c419;border-radius:6px;padding:10px 14px;margin-bottom:12px;">'
            + '<strong>⚠️ Brouillon — rien n\'est encore enregistré.</strong> Ce que tu vois ci-dessous est une proposition de l\'IA : '
            + 'le tableau « Termes existants » plus bas n\'est pas encore modifié. Décoche ce que tu ne veux pas garder, puis clique sur '
            + '« Créer les termes sélectionnés » en bas de cet aperçu pour enregistrer réellement (le statut « Généré via IA » n\'apparaîtra qu\'à ce moment-là).</div>'
            + '<p><strong>Suggestion IA</strong> — décochez ce que vous ne voulez pas créer, puis validez. '
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

        html += '<div style="margin-top:14px;">'
            + '<p style="color:#92400e;font-size:12px;margin-bottom:6px;">Rien n\'est encore enregistré : clique ci-dessous pour appliquer et mettre à jour le statut des termes dans le tableau plus bas.</p>'
            + '<button type="button" id="scl-btn-apply-terms" class="button button-primary">Créer les termes sélectionnés</button>'
            + '</div>';

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

    /* ---- Page termes : generation individuelle (bouton sur le nom et
       sur la description) --------------------------------------------- */
    $(document).on('click', '.scl-btn-generate-desc', function () {
        var $btn = $(this);
        var termId = $btn.data('term-id');
        var taxonomy = $btn.data('taxonomy');
        var provider = $('#scl-curation-provider').val() || 'claude';
        var $row = $btn.closest('tr');
        var $textarea = $row.find('.scl-term-description');
        var $status = $row.find('.scl-term-gen-status small');

        $row.find('.scl-btn-generate-desc').prop('disabled', true);
        $status.text('Génération en cours…').css('color', '#2872d4');

        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            timeout: 90000,
            data: {
                action: 'schilo_classement_generate_term_description',
                nonce: nonce,
                provider: provider,
                taxonomy: taxonomy,
                term_id: termId
            }
        }).done(function (res) {
            $row.find('.scl-btn-generate-desc').prop('disabled', false);
            if (!res.success) {
                $status.text((res.data && res.data.message) || 'Erreur IA.').css('color', '#dc2626');
                return;
            }
            var d = res.data || {};
            $textarea.val(d.description || '');
            $status.text(d.generated_at ? 'Généré via IA le ' + formatMysqlDate(d.generated_at) : '').css('color', '#64748b');
        }).fail(function () {
            $row.find('.scl-btn-generate-desc').prop('disabled', false);
            $status.text('Erreur réseau.').css('color', '#dc2626');
        });
    });

    function formatMysqlDate(mysql) {
        var dt = new Date(mysql.replace(' ', 'T'));
        if (isNaN(dt.getTime())) return mysql;
        var pad = function (n) { return ('0' + n).slice(-2); };
        return pad(dt.getDate()) + '/' + pad(dt.getMonth() + 1) + '/' + dt.getFullYear() + ' à ' + pad(dt.getHours()) + ':' + pad(dt.getMinutes());
    }

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
