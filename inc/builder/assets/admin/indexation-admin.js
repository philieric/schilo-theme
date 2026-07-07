/* Schilo Builder — Indexation Admin JS v1.2 */
(function ($) {
    'use strict';

    // Donnees injectees par PHP dans la page
    var cfg     = window.siaData || {};
    var nonce   = cfg.nonce   || (typeof schiloBuilder !== 'undefined' ? schiloBuilder.indexationNonce : '');
    var ajaxUrl = cfg.ajaxUrl || (typeof schiloBuilder !== 'undefined' ? schiloBuilder.ajaxUrl : ajaxurl);

    // Etat courant
    var state = {
        prefix:      '',
        search:      '',
        statut:      '',
        postStatus:  $('#sia-post-status-filter').val() || 'publish',
        paged:       1,
        postId:      0,
        debounce:    null,
    };

    /* =========================================================
       HELPERS
    ========================================================= */

    function showSpinner(msg) {
        $('#sia-spinner-text').text(msg || 'Traitement en cours...');
        $('#sia-spinner-overlay').show();
    }
    function hideSpinner() { $('#sia-spinner-overlay').hide(); }

    function notice(msg, type) {
        var cls = type === 'error' ? 'notice-error' : 'notice-success';
        var $n = $('<div class="notice ' + cls + ' is-dismissible" style="margin:8px 0"><p>' + msg + '</p></div>');
        $('#sia-toolbar').before($n);
        setTimeout(function () { $n.slideUp(300, function () { $n.remove(); }); }, 5000);
    }

    /* Notice persistante — reste jusqu'au clic */
    function noticePersist(msg, type) {
        $('.sia-notice-persist').remove();
        var isSuccess = type === 'success';
        var cls   = isSuccess ? 'notice-success' : 'notice-error';
        var icon  = isSuccess ? '&#10003;' : '&#9888;';
        var color = isSuccess ? '#166534' : '#991b1b';
        var $n = $('<div class="sia-notice-persist notice ' + cls + '" style="margin:8px 0;padding:14px 18px;font-size:13px;">'
            + '<button type="button" style="float:right;background:none;border:none;font-size:18px;cursor:pointer;line-height:1;color:' + color + ';" onclick="jQuery(this).parent().remove();">&times;</button>'
            + '<span>' + icon + ' ' + msg + '</span>'
            + '</div>');
        $('#sia-toolbar').before($n);
    }

    var badgeMap = {
        'valide':     '<span class="sia-badge sia-badge-green">Valide</span>',
        'en_attente': '<span class="sia-badge sia-badge-orange">En attente</span>',
        'brouillon':  '<span class="sia-badge sia-badge-grey">Brouillon</span>',
        'rejete':     '<span class="sia-badge sia-badge-red">Rejete</span>',
        '':           '<span class="sia-badge sia-badge-grey" style="color:#94a3b8;background:#f8fafc;">—</span>',
    };
    var srcMap = {
        'claude':     'Claude AI',
        'openai':     'ChatGPT',
        'manuel':     'Manuel',
        'xml_import': 'Import XML',
        '':           '—',
    };

    /* =========================================================
       CHARGEMENT DU TABLEAU VIA AJAX
    ========================================================= */

    function updateStats(stats) {
        if (!stats) return;
        $('.sia-stat-card[data-statut=""] .sia-stat-num').text(stats.total);
        $('.sia-stat-card[data-statut="valide"] .sia-stat-num').text(stats.valides);
        $('.sia-stat-card[data-statut="en_attente"] .sia-stat-num').text(stats.attente);
        $('.sia-stat-card[data-statut="non_indexe"] .sia-stat-num').text(stats.non_indexes);
    }

    function loadArticles(callback) {
        $('#sia-tbody').html('<tr><td colspan="7" style="text-align:center;padding:24px;color:#94a3b8;">Chargement...</td></tr>');
        $('#sia-pagination').hide();

        $.post(ajaxUrl, {
            action:      'schilo_indexation_get_articles',
            nonce:       nonce,
            prefix:      state.prefix,
            search:      state.search,
            statut:      state.statut,
            post_status: state.postStatus,
            paged:       state.paged,
        }, function (res) {
            if (!res.success) {
                $('#sia-tbody').html('<tr><td colspan="7" style="color:#dc2626;padding:20px;">Erreur : ' + (res.data.message || '') + '</td></tr>');
                return;
            }
            renderTable(res.data);
            renderPagination(res.data);
            updateStats(res.data.stats);
            if (typeof callback === 'function') callback();
        }).fail(function () {
            $('#sia-tbody').html('<tr><td colspan="7" style="color:#dc2626;padding:20px;">Erreur reseau.</td></tr>');
        });
    }

    function renderTable(data) {
        var rows  = data.rows || [];
        var html  = '';

        if (!rows.length) {
            html = '<tr><td colspan="7" style="text-align:center;padding:30px;color:#94a3b8;">Aucun article pour ce filtre.</td></tr>';
            $('#sia-tbody').html(html);
            return;
        }

        rows.forEach(function (r) {
            var postId  = r.ID;
            var title   = r.post_title || '';
            var pfx     = title.length >= 3 ? title.substr(0, 3) : '';
            var statut  = r.statut_indexation || '';
            var src     = r.source_indexation || '';
            var dateVal = r.date_validation ? r.date_validation.substr(0, 10).split('-').reverse().join('/') : '—';
            var editHref = cfg.editUrl ? cfg.editUrl.replace('__ID__', postId) : '#';
            var valHref  = (cfg.valUrl || '') + postId;

            html += '<tr data-post-id="' + postId + '">';
            html += '<td class="check-column"><input type="checkbox" class="sia-row-check" value="' + postId + '"></td>';
            html += '<td>';
            html += '<strong>' + esc(title) + '</strong>';
            html += '</td>';
            html += '<td><code style="font-size:12px;background:#f1f5f9;padding:2px 6px;border-radius:4px;">' + esc(pfx) + '</code></td>';
            html += '<td>' + (badgeMap[statut] || badgeMap['']) + '</td>';
            html += '<td style="font-size:12px;color:#64748b;">' + esc(srcMap[src] || src) + '</td>';
            html += '<td style="font-size:12px;color:#64748b;">' + esc(dateVal) + '</td>';
            html += '<td class="sia-actions">';
            html += '<a href="' + editHref + '" class="button button-small" target="_blank" title="Modifier l\'article">Modifier</a> ';
            if (statut) {
                html += '<button type="button" class="button button-small sia-btn-voir" data-post-id="' + postId + '" title="Voir / modifier la fiche d\'indexation" style="background:#e0f2fe;border-color:#0ea5e9;color:#0369a1;">';
                html += '<span class="dashicons dashicons-visibility" style="font-size:14px;height:14px;width:14px;vertical-align:middle;float:none;"></span>';
                html += '</button> ';
            }
            html += '<button type="button" class="button button-small sia-btn-index-ia" data-post-id="' + postId + '" title="Indexer via IA">';
            html += '<span class="dashicons dashicons-superhero" style="font-size:14px;height:14px;width:14px;line-height:14px;vertical-align:middle;float:none;margin-top:0;"></span>';
            html += '</button> ';
            html += '<button type="button" class="button button-small sia-btn-export-xml" data-post-id="' + postId + '" title="Export XML">XML</button>';
            html += '</td>';
            html += '</tr>';
        });

        $('#sia-tbody').html(html);

    }

    function renderPagination(data) {
        var total = data.total || 0;
        var pages = data.pages || 1;
        var paged = data.paged || 1;

        if (pages <= 1) { $('#sia-pagination').hide(); return; }

        var info = 'Page ' + paged + '/' + pages + ' — ' + total + ' articles';
        $('#sia-page-info').text(info);

        var btns = '';
        if (paged > 1) btns += '<button type="button" class="button button-small sia-page-btn" data-page="' + (paged - 1) + '">&laquo;</button> ';

        var start = Math.max(1, paged - 3);
        var end   = Math.min(pages, paged + 3);
        for (var i = start; i <= end; i++) {
            var active = i === paged ? ' button-primary' : '';
            btns += '<button type="button" class="button button-small' + active + ' sia-page-btn" data-page="' + i + '">' + i + '</button> ';
        }
        if (paged < pages) btns += '<button type="button" class="button button-small sia-page-btn" data-page="' + (paged + 1) + '">&raquo;</button>';

        $('#sia-page-buttons').html(btns);
        $('#sia-pagination').show();
    }

    function esc(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    /* =========================================================
       INIT — chargement initial
    ========================================================= */

    if ($('#sia-articles-table').length) {
        loadArticles();
    }

    /* =========================================================
       ONGLETS PREFIXES
    ========================================================= */

    $(document).on('click', '.sia-prefix-tab', function (e) {
        e.preventDefault();
        var pfx = $(this).data('prefix');
        $('.sia-prefix-tab').removeClass('current');
        $(this).addClass('current');
        state.prefix = pfx;
        state.paged  = 1;
        loadArticles();
    });

    /* =========================================================
       FILTRES STATUT (cartes stats)
    ========================================================= */

    $(document).on('click', '.sia-stat-filter', function () {
        var statut = $(this).data('statut');
        $('.sia-stat-filter').removeClass('sia-stat-active');
        $(this).addClass('sia-stat-active');
        state.statut = statut;
        state.paged  = 1;
        // Synchronise le select "indexation" quand la valeur correspond (Tous / Non indexes)
        var $indexedFilter = $('#sia-indexed-filter');
        if (statut === '' || statut === 'non_indexe') {
            $indexedFilter.val(statut);
        } else {
            $indexedFilter.val('');
        }
        loadArticles();
    });

    /* =========================================================
       FILTRE INDEXATION (inclure / exclure les articles indexes)
    ========================================================= */

    $('#sia-indexed-filter').on('change', function () {
        var statut = $(this).val();
        state.statut = statut;
        state.paged  = 1;
        // Synchronise la carte stat correspondante (Tous / Non indexes), sinon aucune active
        $('.sia-stat-filter').removeClass('sia-stat-active');
        $('.sia-stat-filter[data-statut="' + statut + '"]').addClass('sia-stat-active');
        loadArticles();
    });

    /* =========================================================
       FILTRE STATUT DE PUBLICATION (publie / brouillon / attente relecture)
    ========================================================= */

    $('#sia-post-status-filter').on('change', function () {
        state.postStatus = $(this).val();
        state.paged = 1;
        loadArticles();
    });

    /* =========================================================
       RECHERCHE
    ========================================================= */

    $('#sia-search').on('input', function () {
        clearTimeout(state.debounce);
        var val = $(this).val().trim();
        state.debounce = setTimeout(function () {
            state.search = val;
            state.paged  = 1;
            loadArticles();
        }, 350);
    });

    /* =========================================================
       PAGINATION
    ========================================================= */

    $(document).on('click', '.sia-page-btn', function () {
        state.paged = parseInt($(this).data('page'), 10);
        loadArticles();
        $('html, body').animate({ scrollTop: $('#sia-articles-table').offset().top - 60 }, 200);
    });

    /* =========================================================
       SELECTION
    ========================================================= */

    $(document).on('change', '#sia-check-all', function () {
        $('.sia-row-check').prop('checked', this.checked);
    });

    $('#sia-btn-select-all').on('click', function () {
        var checked = $('.sia-row-check:checked').length === $('.sia-row-check').length;
        $('.sia-row-check').prop('checked', !checked);
        $('#sia-check-all').prop('checked', !checked);
    });

    /* Selection par plage (Maj+clic) : coche/decoche toutes les lignes
       entre la derniere case cochee manuellement et celle-ci. */
    var lastRowCheck = null;
    $(document).on('click', '.sia-row-check', function (e) {
        var $boxes = $('.sia-row-check');
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

    /* =========================================================
       INDEXER VIA IA — bouton ligne
    ========================================================= */

    $(document).on('click', '.sia-btn-index-ia', function () {
        var postId   = $(this).data('post-id');
        var provider = $('#sia-provider-select').val() || 'claude';
        var title    = $(this).closest('tr').find('td:nth-child(2) strong a').text();

        console.log('[SIA] Clic IA — postId:', postId, '| provider:', provider, '| nonce:', nonce ? nonce.substring(0, 8) + '...' : '(VIDE)', '| ajaxUrl:', ajaxUrl);

        if (!postId) { notice('post_id introuvable sur le bouton — rechargez la page.', 'error'); return; }
        if (!nonce)  { notice('Nonce manquant — rechargez la page (Ctrl+F5).', 'error'); return; }

        state.postId = postId;
        $('#sia-modal-title').text('IA — ' + (title || 'Article #' + postId));
        showSpinner('Analyse IA en cours (' + provider + ')...');

        $.ajax({
            url:      ajaxUrl,
            type:     'POST',
            timeout:  65000,
            data: {
                action:   'schilo_ia_index_article',
                nonce:    nonce,
                post_id:  postId,
                provider: provider,
            },
        }).done(function (res) {
            console.log('[SIA] Reponse AJAX brute:', res);
            console.log('[SIA] res.data:', JSON.stringify(res.data));
            hideSpinner();
            if (!res || typeof res !== 'object') {
                noticePersist('Reponse non-JSON : ' + JSON.stringify(res));
                return;
            }
            if (!res.success) {
                var raw = (res.data && res.data.message) ? res.data.message : JSON.stringify(res.data || res);
                console.error('[SIA] Erreur PHP:', raw);
                var msg = raw;
                if (/quota|billing|exceeded|insufficient/i.test(raw)) {
                    var prov = $('#sia-provider-select').val() || 'openai';
                    var link = prov === 'openai'
                        ? 'https://platform.openai.com/settings/billing'
                        : 'https://console.anthropic.com/settings/billing';
                    msg = 'Solde insuffisant sur votre compte ' + (prov === 'openai' ? 'OpenAI' : 'Anthropic')
                        + '. Rechargez vos cr&eacute;dits : <a href="' + link + '" target="_blank">' + link + '</a>';
                } else if (/invalid.*key|incorrect.*key|api.key/i.test(raw)) {
                    msg = 'Cl&eacute; API invalide. V&eacute;rifiez la configuration dans <a href="admin.php?page=schilo-builder-ia">Schilo Builder &gt; IA</a>.';
                } else if (/rate.limit|too many/i.test(raw)) {
                    msg = 'Limite de requ&ecirc;tes atteinte. Patientez quelques secondes et r&eacute;essayez.';
                }
                noticePersist(msg);
                return;
            }
            if (res.data.fallback_msg) {
                notice(res.data.fallback_msg, 'success');
            }
            if (res.data.auto_mode) {
                if (res.data.auto_saved) {
                    noticePersist('Article indexe et valide automatiquement (mode automatique).', 'success');
                    loadArticles();
                } else {
                    noticePersist('Mode automatique actif, mais l\'enregistrement a echoue.');
                }
                return;
            }
            openIaModal(res.data.fields, postId);
        }).fail(function (xhr, status, err) {
            hideSpinner();
            var detail = status === 'timeout' ? 'Delai depasse (65s). L\'IA prend trop de temps.' : (err || status);
            console.error('[SIA] Fail AJAX:', status, err);
            noticePersist('Erreur reseau : ' + detail);
        });
    });

    /* =========================================================
       MODAL IA
    ========================================================= */

    var fieldDefs = [
        // Identite
        { key: 'prefix',              label: 'Prefixe (ex: PER)',     type: 'text' },
        // Classification
        { key: 'theme_principal',     label: 'Theme principal',       type: 'text' },
        { key: 'sous_theme',          label: 'Sous-theme',            type: 'text' },
        { key: 'parcours',            label: 'Parcours',              type: 'text' },
        { key: 'serie',               label: 'Serie',                 type: 'text' },
        { key: 'ordre_serie',         label: 'N° dans la serie',      type: 'text' },
        { key: 'public_cible',        label: 'Public cible',          type: 'text' },
        { key: 'niveau_lecture',      label: 'Niveau lecture',        type: 'text' },
        // Contenu
        { key: 'resume',              label: 'Resume (500-800 mots)', type: 'textarea', rows: 5 },
        { key: 'resume_court',        label: 'Resume court (150 mots max)', type: 'textarea', rows: 2 },
        { key: 'mots_cles',           label: 'Mots-cles',             type: 'text',     isArray: true },
        { key: 'concepts',            label: 'Concepts',              type: 'text',     isArray: true },
        { key: 'personnages',         label: 'Personnages',           type: 'text',     isArray: true },
        { key: 'lieux',               label: 'Lieux',                 type: 'text',     isArray: true },
        { key: 'periodes',            label: 'Periodes',              type: 'text',     isArray: true },
        { key: 'references_bibliques',label: 'Ref. bibliques',        type: 'text',     isArray: true },
        { key: 'citations_cles',      label: 'Citations (1 par ligne)',type: 'textarea', rows: 3, isArray: true },
        // SEO
        { key: 'seo_titre',           label: 'SEO titre (70 cars)',   type: 'text' },
        { key: 'seo_description',     label: 'SEO description (160)', type: 'textarea', rows: 2 },
        { key: 'seo_mots_cles',       label: 'SEO mots-cles',         type: 'text',     isArray: true },
        { key: 'og_titre',            label: 'OG titre',              type: 'text' },
        { key: 'og_description',      label: 'OG description',        type: 'textarea', rows: 2 },
        { key: 'schema_type',         label: 'Schema type',           type: 'text' },
        { key: 'robots',              label: 'Robots',                type: 'text' },
        // Relations (1 par ligne)
        { key: 'articles_lies',       label: 'Articles lies',         type: 'textarea', rows: 3, isArray: true, sep: '\n' },
        { key: 'articles_prerequis',  label: 'Articles prerequis',    type: 'textarea', rows: 2, isArray: true, sep: '\n' },
        { key: 'articles_suite',      label: 'Articles suite',        type: 'textarea', rows: 2, isArray: true, sep: '\n' },
        { key: 'sources_externes',    label: 'Sources externes',      type: 'textarea', rows: 2, isArray: true, sep: '\n' },
    ];

    // Champs systeme jamais affichés dans le modal
    var systemKeys = [
        'post_id','id','statut_indexation','source_indexation','donnees_ia_brutes',
        'notes_validation','indexe_par','valide_par','date_indexation','date_validation',
        'version','statut_wp','date_article','date_modification','auteur',
        'categories','tags_wp','schema_json','og_image_url','canonical_url',
        'temps_lecture_min','nb_mots','nb_sections','titre','slug','url'
    ];

    function openIaModal(fields, postId) {
        var html = '<div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">';

        fieldDefs.forEach(function (f) {
            var rawVal = fields[f.key] || (f.isArray ? [] : '');
            var displayVal = (f.isArray && Array.isArray(rawVal))
                ? rawVal.join(f.sep || (f.key === 'citations_cles' ? '\n' : ', '))
                : String(rawVal || '');

            var isFullWidth = (f.type === 'textarea' && (f.rows || 0) >= 4) || f.key === 'resume';
            var colStyle = isFullWidth ? 'grid-column:1/-1;' : '';

            html += '<div class="sia-ia-field" style="' + colStyle + '">';
            html += '<label for="siaif_' + f.key + '">' + f.label + '</label>';
            if (f.type === 'textarea') {
                html += '<textarea id="siaif_' + f.key + '" name="' + f.key + '" rows="' + (f.rows || 3) + '">' + esc(displayVal) + '</textarea>';
            } else {
                html += '<input type="text" id="siaif_' + f.key + '" name="' + f.key + '" value="' + esc(displayVal) + '">';
            }
            html += '</div>';
        });

        html += '</div>';

        // Champs adaptatifs : tout champ retourné par l'IA non couvert par fieldDefs
        var knownKeys = fieldDefs.map(function (f) { return f.key; });
        var extraFields = {};

        // Déplier champs_custom s'il existe (objet JSON)
        if (fields.champs_custom && typeof fields.champs_custom === 'object' && !Array.isArray(fields.champs_custom)) {
            Object.keys(fields.champs_custom).forEach(function (k) {
                extraFields[k] = fields.champs_custom[k];
            });
        }

        // Champs inconnus hors fieldDefs et hors système
        Object.keys(fields).forEach(function (k) {
            if (knownKeys.indexOf(k) === -1 && systemKeys.indexOf(k) === -1 && k !== 'champs_custom') {
                if (!(k in extraFields)) extraFields[k] = fields[k];
            }
        });

        if (Object.keys(extraFields).length > 0) {
            html += '<div style="grid-column:1/-1;margin-top:14px;padding-top:10px;border-top:2px dashed #e2e8f0;">';
            html += '<p style="font-size:11px;font-weight:700;color:#6d3fc0;margin:0 0 8px;">Champs supplementaires detectes par l\'IA</p>';
            html += '<div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">';
            Object.keys(extraFields).forEach(function (k) {
                var val = extraFields[k];
                var displayVal = Array.isArray(val) ? val.join(', ') : String(val || '');
                html += '<div class="sia-ia-field">';
                html += '<label for="siaif_cc_' + k + '">' + k.replace(/_/g, ' ') + ' <span style="color:#94a3b8;font-size:10px;">(extra)</span></label>';
                html += '<input type="text" id="siaif_cc_' + k + '" name="cc_' + k + '" value="' + esc(displayVal) + '">';
                html += '</div>';
            });
            html += '</div></div>';
        }

        html += '<input type="hidden" name="post_id" value="' + postId + '">';

        $('#sia-ia-fields').html(html);
        $('#sia-current-post-id').val(postId);
        $('#sia-ia-modal').show();
    }

    $(document).on('click', '.sia-modal-close', function () {
        $('#sia-ia-modal').hide();
    });

    /* =========================================================
       VALIDER (modal IA)
    ========================================================= */

    $('#sia-btn-validate').on('click', function () {
        var postId = $('#sia-current-post-id').val();
        var data   = { post_id: postId };

        $('#sia-ia-fields input, #sia-ia-fields textarea').each(function () {
            var name = $(this).attr('name');
            if (name && name !== 'post_id') {
                data[name] = $(this).val();
            }
        });

        // Champs tableau separes par virgule
        var arrComma = ['mots_cles','concepts','personnages','lieux','periodes','references_bibliques','seo_mots_cles'];
        arrComma.forEach(function (k) {
            if (data[k] !== undefined) {
                data[k] = data[k].split(',').map(function (s) { return s.trim(); }).filter(Boolean);
            }
        });
        // Champs tableau separes par saut de ligne
        var arrNewline = ['citations_cles','articles_lies','articles_prerequis','articles_suite','sources_externes'];
        arrNewline.forEach(function (k) {
            if (data[k] !== undefined) {
                data[k] = data[k].split('\n').map(function (s) { return s.trim(); }).filter(Boolean);
            }
        });
        // Champs adaptatifs (prefixe cc_) → champs_custom
        var customFields = {};
        Object.keys(data).forEach(function (k) {
            if (k.indexOf('cc_') === 0) {
                var realKey = k.substring(3);
                customFields[realKey] = data[k];
                delete data[k];
            }
        });
        if (Object.keys(customFields).length > 0) {
            data['champs_custom'] = customFields;
        }

        var titreModal = $('#sia-modal-title').text();
        $('#sia-ia-modal').hide();
        showSpinner('Enregistrement...');

        console.log('[SIA] Save — postId:', postId, '| champs:', Object.keys(data).length, '| nonce:', nonce ? nonce.substring(0,8)+'...' : 'VIDE');

        $.ajax({
            url: ajaxUrl, type: 'POST', timeout: 30000,
            data: { action: 'schilo_save_indexation_validated', nonce: nonce, data: data },
        }).done(function (res) {
            console.log('[SIA] Save response:', JSON.stringify(res));
            hideSpinner();
            if (res && res.success) {
                noticePersist('<strong>&#10003; Index enregistre</strong> — ' + (titreModal || 'Article #' + postId) + ' est maintenant <strong>Valide</strong>.', 'success');
                loadArticles(function () {
                    var $row = $('tr[data-post-id="' + postId + '"]');
                    $row.css('background', '#dcfce7');
                    setTimeout(function () { $row.css('background', ''); }, 3000);
                });
            } else {
                noticePersist('Erreur enregistrement : ' + (res && res.data && res.data.message ? res.data.message : JSON.stringify(res)));
            }
        }).fail(function (xhr, status, err) {
            console.error('[SIA] Save fail:', status, err, xhr.responseText ? xhr.responseText.substring(0,200) : '');
            hideSpinner();
            noticePersist('Erreur reseau : ' + (status === 'timeout' ? 'delai depasse' : err));
        });
    });

    /* =========================================================
       REJETER (modal IA)
    ========================================================= */

    $('#sia-btn-reject').on('click', function () {
        var postId = $('#sia-current-post-id').val();
        $('#sia-ia-modal').hide();
        $.post(ajaxUrl, {
            action:  'schilo_indexation_update_status',
            nonce:   nonce,
            post_id: postId,
            statut:  'rejete',
        }, function () { loadArticles(); });
    });

    /* =========================================================
       EXPORT XML
    ========================================================= */

    /* =========================================================
       VOIR / MODIFIER UNE FICHE EXISTANTE
    ========================================================= */

    $(document).on('click', '.sia-btn-voir', function () {
        var postId = $(this).data('post-id');
        var title  = $(this).closest('tr').find('td:nth-child(2) strong').text();
        state.postId = postId;
        showSpinner('Chargement de la fiche...');

        $.ajax({
            url: ajaxUrl, type: 'POST', timeout: 15000,
            data: { action: 'schilo_indexation_get_record', nonce: nonce, post_id: postId },
        }).done(function (res) {
            hideSpinner();
            if (!res || !res.success) {
                noticePersist('Impossible de charger la fiche : ' + (res && res.data ? res.data.message : 'erreur inconnue'));
                return;
            }
            $('#sia-modal-title').text('Fiche indexation — ' + (title || 'Article #' + postId));
            openIaModal(res.data.fields, postId);
        }).fail(function () {
            hideSpinner();
            noticePersist('Erreur reseau lors du chargement de la fiche.');
        });
    });

    $(document).on('click', '.sia-btn-export-xml', function () {
        var postId = $(this).data('post-id');
        state.postId = postId;
        showSpinner('Generation du template XML...');

        $.post(ajaxUrl, {
            action:  'schilo_export_indexation_xml',
            nonce:   nonce,
            post_id: postId,
        }, function (res) {
            hideSpinner();
            if (!res.success) { notice('Erreur export XML.', 'error'); return; }

            var titre   = res.data.titre   || '';
            var contenu = res.data.contenu || '';
            var xml     = res.data.xml     || '';

            // Instructions pour l'IA
            var instructions =
                'Tu es un expert en analyse de contenu biblique et théologique.\n' +
                'Analyse l\'article ci-dessous et remplis tous les champs vides du XML.\n\n' +
                'Contraintes importantes :\n' +
                '- resume : 500 à 800 mots, complet et détaillé\n' +
                '- resume_court : 100 à 150 mots maximum\n' +
                '- seo_titre : 60 à 70 caractères maximum\n' +
                '- seo_description : 150 à 160 caractères maximum\n' +
                '- Listes (mots_cles, concepts, etc.) : un élément par balise <item>\n' +
                '- Retourne UNIQUEMENT le XML complété, sans texte avant ni après\n\n' +
                '=== ARTICLE À ANALYSER ===\n' +
                'Titre : ' + titre + '\n\n' +
                (contenu ? contenu.substring(0, 6000) + (contenu.length > 6000 ? '\n[...]' : '') : '') +
                '\n\n=== XML À REMPLIR ===\n';

            $('#sia-ia-instructions').val(instructions);
            $('#sia-xml-content').val(xml);
            $('#sia-import-zone').hide();
            $('#sia-import-content').val('');
            $('#sia-xml-modal').show();
        }).fail(function () {
            hideSpinner();
            notice('Erreur reseau.', 'error');
        });
    });

    $(document).on('click', '.sia-xml-close', function () { $('#sia-xml-modal').hide(); });

    $('#sia-btn-copy-xml').on('click', function () {
        var instructions = $('#sia-ia-instructions').val() || '';
        var xml          = $('#sia-xml-content').val()     || '';
        var fullText     = instructions + xml;
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(fullText).then(function () {
                $('#sia-btn-copy-xml').text('Copie !');
                setTimeout(function () { $('#sia-btn-copy-xml').text('Copier pour l\'IA'); }, 2000);
            });
        } else {
            var ta = document.createElement('textarea');
            ta.value = fullText;
            document.body.appendChild(ta);
            ta.select();
            document.execCommand('copy');
            document.body.removeChild(ta);
            $(this).text('Copie !');
            setTimeout(function () { $('#sia-btn-copy-xml').text('Copier pour l\'IA'); }, 2000);
        }
    });

    $('#sia-btn-show-import').on('click', function () { $('#sia-import-zone').toggle(); });

    $('#sia-btn-do-import').on('click', function () {
        var content = $('#sia-import-content').val().trim();
        var format  = $('#sia-import-format').val();
        if (!content) { alert('Le contenu est vide.'); return; }

        showSpinner('Analyse du contenu importe...');
        $('#sia-xml-modal').hide();

        $.ajax({
            url:     ajaxUrl,
            type:    'POST',
            timeout: 30000,
            data: {
                action:  'schilo_import_indexation',
                nonce:   nonce,
                post_id: state.postId,
                content: content,
                format:  format,
            },
        }).done(function (res) {
            hideSpinner();
            if (!res || typeof res !== 'object') {
                noticePersist('Reponse serveur inattendue : ' + JSON.stringify(res));
                return;
            }
            if (!res.success) {
                noticePersist('Erreur import : ' + (res.data && res.data.message ? res.data.message : JSON.stringify(res.data)));
                return;
            }
            $('#sia-xml-modal').hide();
            openIaModal(res.data.fields, state.postId);
        }).fail(function (xhr, status, err) {
            hideSpinner();
            noticePersist('Erreur reseau lors de l\'import : ' + (status === 'timeout' ? 'delai depasse' : err));
        });
    });

    /* =========================================================
       INDEXATION EN LOT
    ========================================================= */

    $('#sia-btn-batch-ia').on('click', function () {
        var ids      = [];
        var provider = $('#sia-provider-select').val() || 'claude';
        $('.sia-row-check:checked').each(function () { ids.push($(this).val()); });
        if (!ids.length) { alert('Selectionnez au moins un article.'); return; }
        if (!confirm('Indexer ' + ids.length + ' article(s) via ' + provider + ' ?\nCela peut prendre plusieurs minutes.')) return;
        runBatch(ids, provider);
    });

    function runBatch(ids, provider) {
        showSpinner('Indexation en lot (' + ids.length + ' articles)...');
        $.post(ajaxUrl, {
            action:   'schilo_ia_index_batch',
            nonce:    nonce,
            post_ids: ids,
            provider: provider || 'claude',
        }, function (res) {
            hideSpinner();
            if (res.success) {
                var ok       = (res.data.ok  || []).length;
                var err      = (res.data.error || []).length;
                var statutTxt = res.data.auto_mode ? 'valides automatiquement' : 'en attente de validation';
                notice(ok + ' article(s) indexes avec succes' + (err ? ', ' + err + ' erreur(s)' : '') + '. Statut : ' + statutTxt + '.', 'success');
                loadArticles();
            } else {
                notice('Erreur batch : ' + (res.data.message || ''), 'error');
            }
        }).fail(function () {
            hideSpinner();
            notice('Erreur reseau.', 'error');
        });
    }

})(jQuery);
