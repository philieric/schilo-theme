(function ($) {
    'use strict';

    let sectionIndex = $('#schilo-sections-list .schilo-section-item').length;
    let mediaFrame = null;
    let currentImageField = null;

    function updateSectionCount() {
        $('#schilo-section-count').text($('#schilo-sections-list .schilo-section-item').length);
    }

    function getEditorContent(editorId) {
        if (typeof tinymce !== 'undefined' && tinymce.get(editorId)) {
            return tinymce.get(editorId).getContent();
        }

        return $('#' + editorId).val() || '';
    }

    function removeEditor(editorId) {
        if (typeof tinymce !== 'undefined' && tinymce.get(editorId)) {
            tinymce.get(editorId).remove();
        }

        if (typeof QTags !== 'undefined' && QTags.instances && QTags.instances[editorId]) {
            delete QTags.instances[editorId];
        }
    }

    function initEditor(editorId) {
        if (typeof wp === 'undefined' || !wp.editor || !wp.editor.initialize) {
            return;
        }

        wp.editor.initialize(editorId, {
            tinymce: {
                wpautop: true,
                plugins: 'charmap colorpicker compat3x directionality fullscreen hr image lists media paste tabfocus textcolor wordpress wpeditimage wpemoji wpgallery wplink wptextpattern',
                toolbar1: 'schilo_h1,schilo_h2,schilo_h3,schilo_h4,schilo_h5,schilo_h6,schilo_p,bold,italic,underline,bullist,numlist,blockquote,alignleft,aligncenter,alignright,link,unlink,pastetext,undo,redo',
                toolbar2: 'strikethrough,hr,forecolor,removeformat,charmap,outdent,indent,schilo_shortcodes',
                height: 500
            },
            quicktags: true,
            mediaButtons: false
        });
    }

    function refreshIndexes() {
        $('#schilo-sections-list .schilo-section-item').each(function (newIndex) {
            const item = $(this);
            item.attr('data-section-index', newIndex);
            item.find('.schilo-section-number').text(newIndex + 1);

            item.find('[name]').each(function () {
                const input = $(this);
                const name = input.attr('name');
                if (!name) return;
                input.attr('name', name.replace(/schilo_sections\[\d+\]/, 'schilo_sections[' + newIndex + ']'));
            });
        });

        updateSectionCount();
    }

    function labelFromType(type) {
        return type.replace('-', ' ').replace(/\b\w/g, function (l) { return l.toUpperCase(); });
    }

    function imageFieldTemplate(index, fieldKey, label) {
        fieldKey = fieldKey || 'image_id';
        label = label || 'Image principale';

        return `
            <div class="schilo-image-field">
                <strong>${escapeHtml(label)}</strong>

                <input type="hidden"
                       class="schilo-image-id"
                       name="schilo_sections[${index}][data][${fieldKey}]"
                       value="">

                <div class="schilo-image-preview">
                    <span>Aucune image sélectionnée</span>
                </div>

                <div class="schilo-image-actions">
                    <button type="button" class="button schilo-select-image">Choisir une image</button>
                    <button type="button" class="button schilo-remove-image">Retirer</button>
                </div>
            </div>
        `;
    }



    function escapeHtml(value) {
        return String(value === undefined || value === null ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function getEvangilesConfig() {
        const structures = window.SchiloBuilderAdmin && SchiloBuilderAdmin.sectionStructures
            ? SchiloBuilderAdmin.sectionStructures
            : {};

        const field = structures.evangiles
            && structures.evangiles.fields
            && structures.evangiles.fields.versets
            ? structures.evangiles.fields.versets
            : {};

        return {
            label: field.label || 'Références bibliques',
            help: field.help || 'Saisis uniquement la référence biblique. Le shortcode [bnv]...[/bnv] sera ajouté automatiquement côté front.',
            defaultItems: Array.isArray(field.default_items) ? field.default_items : [],
            classChoices: field.class_choices && typeof field.class_choices === 'object' ? field.class_choices : {}
        };
    }


    function getFirstClassChoice(classChoices) {
        const choices = Object.keys(classChoices || {});
        return choices.length ? choices[0] : '';
    }

    function evangilesOptions(classChoices, selected) {
        const choices = Object.keys(classChoices || {});

        if (!choices.length) {
            return '<option value="">Aucune classe</option>';
        }

        return Object.keys(classChoices).map(function (choice) {
            const label = classChoices[choice] || choice;
            return `<option value="${escapeHtml(choice)}" ${choice === selected ? 'selected' : ''}>${escapeHtml(label)}</option>`;
        }).join('');
    }

    function evangilesRefItemTemplate(sectionIndex, itemIndex, line, classChoices, isAddTemplate) {
        const lineLabel = line && line.label !== undefined ? line.label : '';
        const lineClass = line && line.class !== undefined ? line.class : getFirstClassChoice(classChoices);
        const lineReference = line && line.reference !== undefined ? line.reference : '';
        const itemIndexValue = isAddTemplate ? '__ITEM_INDEX__' : itemIndex;

        return `
            <div class="schilo-bible-ref-item">
                <label>
                    <span>Ligne</span>
                    <input type="text"
                           class="widefat"
                           name="schilo_sections[${sectionIndex}][data][versets][${itemIndexValue}][label]"
                           value="${escapeHtml(lineLabel)}">
                </label>

                <label>
                    <span>Classe CSS</span>
                    <select class="widefat"
                            name="schilo_sections[${sectionIndex}][data][versets][${itemIndexValue}][class]">
                        ${evangilesOptions(classChoices, lineClass)}
                    </select>
                </label>

                <label class="schilo-bible-ref-reference">
                    <span>Référence</span>
                    <input type="text"
                           class="widefat"
                           name="schilo_sections[${sectionIndex}][data][versets][${itemIndexValue}][reference]"
                           value="${escapeHtml(lineReference)}"
                           placeholder="Exemple : Luc 23.28-31">
                </label>

                <button type="button" class="button schilo-remove-bible-ref-item">Retirer</button>
            </div>
        `;
    }

    function evangilesFieldTemplate(index) {
        const config = getEvangilesConfig();
        const inputs = config.defaultItems.map(function (line, i) {
            return evangilesRefItemTemplate(index, i, line, config.classChoices, false);
        }).join('');

        return `
            <input type="hidden" name="schilo_sections[${index}][content]" value="">
            <input type="hidden" name="schilo_sections[${index}][data][versets_present]" value="1">

            <div class="schilo-bible-ref-field">
                <strong>${escapeHtml(config.label)}</strong>
                <p class="description">${escapeHtml(config.help)}</p>

                <div class="schilo-bible-ref-items">${inputs}</div>

                <button type="button" class="button schilo-add-bible-ref-item">+ Ajouter une référence</button>

                <script type="text/template" class="schilo-bible-ref-template">
                    ${evangilesRefItemTemplate(index, '__ITEM_INDEX__', { label: '', class: getFirstClassChoice(config.classChoices), reference: '' }, config.classChoices, true)}
                </script>
            </div>
        `;
    }

    function detailBlockItemTemplate(groupKey, index, itemIndexValue, content, isTemplate) {
        const itemIndex = isTemplate ? '__ITEM_INDEX__' : itemIndexValue;
        const editorId = 'schilo_detail_' + groupKey + '_' + index + '_' + itemIndex + '_' + Date.now();

        return `
            <div class="schilo-detail-block-item">
                <div class="schilo-editor-wrapper schilo-detail-editor-wrapper" data-editor-id="${editorId}">
                    <textarea id="${editorId}"
                              class="widefat schilo-detail-block-editor"
                              rows="4"
                              name="schilo_sections[${index}][data][${groupKey}][${itemIndex}][content]">${escapeHtml(content)}</textarea>
                </div>
                <button type="button" class="button schilo-remove-detail-block">Retirer</button>
            </div>
        `;
    }

    function detailBlocksGroupTemplate(index, groupKey, groupLabel, helpText, defaultItems) {
        defaultItems = defaultItems || [];

        const items = defaultItems.map(function (content, i) {
            return detailBlockItemTemplate(groupKey, index, i, content, false);
        }).join('');

        return `
            <div class="schilo-detail-blocks-field" data-group="${groupKey}">
                <strong>${escapeHtml(groupLabel)}</strong>
                <p class="description">${escapeHtml(helpText)}</p>

                <div class="schilo-detail-blocks-items">${items}</div>

                <button type="button" class="button schilo-add-detail-block" data-group="${groupKey}">+ Ajouter un encart</button>

                <script type="text/template" class="schilo-detail-block-template" data-group="${groupKey}">
                    ${detailBlockItemTemplate(groupKey, index, '__ITEM_INDEX__', '', true)}
                </script>
            </div>
        `;
    }

    function imagePositionFieldTemplate(index) {
        return `
            <div class="schilo-field-row">
                <label>
                    <strong>Position de l’image</strong>
                    <select class="widefat" name="schilo_sections[${index}][data][image_position]">
                        <option value="right">Droite (texte à gauche)</option>
                        <option value="left">Gauche (texte à droite)</option>
                    </select>
                </label>
            </div>
        `;
    }

    function detailsColonnesTemplate(index) {
        return `
            ${imagePositionFieldTemplate(index)}

            ${imageFieldTemplate(index)}

            ${detailBlocksGroupTemplate(index, 'blocks', 'Encarts de texte (colonne opposée à l’image)', 'Un encart par information. Il sera affiché dans la colonne opposée à l’image.')}
        `;
    }

    function optionalImageButtonTemplate(fieldKey, label) {
        return `<button type="button" class="button schilo-add-optional-image" data-field="${fieldKey}" data-label="${escapeHtml(label)}">+ Ajouter une image</button>`;
    }

    function optionalImageFieldTemplate(index, fieldKey, label) {
        return `
            ${imageFieldTemplate(index, fieldKey, label)}
            <button type="button" class="button-link schilo-remove-image-field">Retirer ce bloc image</button>
        `;
    }

    function optionalImageWrapperTemplate(index, fieldKey, label, hasImage) {
        const inner = hasImage
            ? optionalImageFieldTemplate(index, fieldKey, label)
            : optionalImageButtonTemplate(fieldKey, label);

        const stateClass = hasImage ? 'schilo-optional-expanded' : 'schilo-optional-collapsed';
        return `<div class="schilo-optional-image ${stateClass}" data-field="${fieldKey}" data-label="${escapeHtml(label)}">${inner}</div>`;
    }

    function optionalTextFieldTemplate(index, fieldKey, label, placeholder, value) {
        value = value || '';
        placeholder = placeholder || '';

        return `
            <div class="schilo-field-row">
                <label>
                    <strong>${escapeHtml(label)}</strong>
                    <input type="text" class="widefat" name="schilo_sections[${index}][data][${fieldKey}]" value="${escapeHtml(value)}" placeholder="${escapeHtml(placeholder)}">
                </label>
            </div>
            <button type="button" class="button-link schilo-remove-text-field">Retirer ce texte</button>
        `;
    }

    function optionalTextButtonTemplate(fieldKey, label, placeholder) {
        return `<button type="button" class="button schilo-add-optional-text" data-field="${fieldKey}" data-label="${escapeHtml(label)}" data-placeholder="${escapeHtml(placeholder)}">+ Ajouter un texte</button>`;
    }

    function optionalTextWrapperTemplate(index, fieldKey, label, placeholder, hasValue, value) {
        const inner = hasValue
            ? optionalTextFieldTemplate(index, fieldKey, label, placeholder, value)
            : optionalTextButtonTemplate(fieldKey, label, placeholder);

        const stateClass = hasValue ? 'schilo-optional-expanded' : 'schilo-optional-collapsed';
        return `<div class="schilo-optional-text ${stateClass}" data-field="${fieldKey}" data-label="${escapeHtml(label)}" data-placeholder="${escapeHtml(placeholder)}">${inner}</div>`;
    }

    function linkItemTemplate(index, itemIndex, label, url) {
        label = label || '';
        url = url || '';

        return `
            <div class="schilo-link-item">
                <input type="text" class="widefat schilo-link-label" name="schilo_sections[${index}][data][links][${itemIndex}][label]" value="${escapeHtml(label)}" placeholder="Texte affiché, ex : Voir la péricope suivante">
                <div class="schilo-combobox">
                    <input type="text" class="widefat schilo-link-article-search" autocomplete="off" value="${escapeHtml(url)}" placeholder="Rechercher un article, ou coller une URL">
                    <ul class="schilo-combobox-list"></ul>
                </div>
                <input type="url" class="widefat schilo-link-url" name="schilo_sections[${index}][data][links][${itemIndex}][url]" value="${escapeHtml(url)}" placeholder="https://...">
                <input type="hidden" class="schilo-link-post-id" name="schilo_sections[${index}][data][links][${itemIndex}][post_id]" value="">
                <button type="button" class="button schilo-remove-link">Retirer</button>
            </div>
        `;
    }

    function liensArticlesTemplate(index) {
        return `
            <div class="schilo-field-row">
                <label>
                    <strong>Texte d'introduction</strong>
                    <input type="text" class="widefat" name="schilo_sections[${index}][data][intro]" value="Vous pouvez consulter les annexes suivantes : " placeholder="Vous pouvez consulter les annexes suivantes : ">
                </label>
            </div>

            ${optionalTextWrapperTemplate(index, 'texte_libre', 'Texte libre', 'Texte libre affiché au-dessus des liens', false)}

            <div class="schilo-links-field">
                <strong>Liens</strong>
                <p class="description">Un lien par ligne : texte affiché + URL (vers un autre article, péricope, page...).</p>

                <div class="schilo-links-items"></div>

                <button type="button" class="button schilo-add-link">+ Ajouter un lien</button>
            </div>
        `;
    }

    function detailTechniqueImgDroiteTemplate(index) {
        return `
            ${optionalTextWrapperTemplate(index, 'texte_avant', 'Texte libre au-dessus', 'Texte libre affiché en haut de la section', false)}

            ${optionalImageWrapperTemplate(index, 'image_haut_id', 'Image au-dessus de « Lieu : »', false)}

            <div class="schilo-detail-fixed-fields">
                <div class="schilo-field-row">
                    <label>
                        <strong>Lieu :</strong>
                        <input type="text" class="widefat" name="schilo_sections[${index}][data][lieu]" value="" placeholder="Ex : Le mont Golgotha">
                    </label>
                </div>

                <div class="schilo-field-row">
                    <label>
                        <strong>Date :</strong>
                        <input type="text" class="widefat" name="schilo_sections[${index}][data][date]" value="" placeholder="Ex : le vendredi 1er avril, le matin">
                    </label>
                </div>

                <div class="schilo-field-row">
                    <label>
                        <strong>Mode opératoire :</strong>
                        <input type="text" class="widefat" name="schilo_sections[${index}][data][mode_operatoire]" value="" placeholder="Ex : Nous suivons maintenant Luc">
                    </label>
                </div>

                <div class="schilo-field-row">
                    <label>
                        <strong>Note sur le mode opératoire :</strong>
                        <input type="text" class="widefat" name="schilo_sections[${index}][data][note_mode_operatoire]" value="" placeholder="Ex : Matthieu, Marc et Jean rapportent aussi ce fait">
                    </label>
                </div>
            </div>

            ${optionalTextWrapperTemplate(index, 'texte_milieu', 'Texte libre avant l’image principale', 'Texte libre affiché avant l’image principale', false)}

            ${imageFieldTemplate(index, 'image_id', 'Image principale')}

            <div class="schilo-field-row">
                <label>
                    <strong>Texte sous l’image</strong>
                    <input type="text" class="widefat" name="schilo_sections[${index}][data][texte_dessous]" value="" placeholder="Ex : Arrivé au mont Golgotha, Jésus est cloué sur la croix par les mains et les pieds.">
                </label>
            </div>

            ${optionalImageWrapperTemplate(index, 'image_bas_id', 'Image sous le texte', false)}

            ${optionalTextWrapperTemplate(index, 'texte_apres', 'Texte libre en dessous', 'Texte libre affiché en bas de la section', false)}
        `;
    }

    function detailsTechniquesTemplate(index) {
        return `
            ${detailBlocksGroupTemplate(index, 'blocks_before', 'Encarts avant l’image (lieu, date, mode opératoire...)', 'Un encart par information. Utilisez <strong>texte</strong> pour mettre du texte en gras.')}

            ${imageFieldTemplate(index)}

            ${detailBlocksGroupTemplate(index, 'blocks_after', 'Encarts après l’image', 'Un encart par information complémentaire affichée sous l’image.')}
        `;
    }

    function editorTemplate(index) {
        const editorId = 'schilo_dynamic_editor_' + index + '_' + Date.now();

        return `
            <div class="schilo-editor-wrapper schilo-dynamic-editor-wrapper" data-editor-id="${editorId}">
                <strong>Contenu</strong>
                <textarea id="${editorId}"
                          class="widefat schilo-dynamic-editor"
                          rows="8"
                          name="schilo_sections[${index}][content]"></textarea>
            </div>
        `;
    }

    function createSection(type) {
        const index = sectionIndex++;
        const label = labelFromType(type);

        let extraFields = '';
        let contentEditorHtml = editorTemplate(index);

        if (type === 'image-textes') {
            extraFields = imageFieldTemplate(index);
        }

        if (type === 'details-techniques') {
            contentEditorHtml = '<input type="hidden" name="schilo_sections[' + index + '][content]" value="">';
            extraFields = detailsTechniquesTemplate(index);
        }

        if (type === 'details-colonnes') {
            contentEditorHtml = '<input type="hidden" name="schilo_sections[' + index + '][content]" value="">';
            extraFields = detailsColonnesTemplate(index);
        }

        if (type === 'detail-technique-img-droite') {
            contentEditorHtml = '<input type="hidden" name="schilo_sections[' + index + '][content]" value="">';
            extraFields = detailTechniqueImgDroiteTemplate(index);
        }

        if (type === 'liens-articles') {
            contentEditorHtml = '<input type="hidden" name="schilo_sections[' + index + '][content]" value="">';
            extraFields = liensArticlesTemplate(index);
        }

        if (type === 'titre-simple') {
            contentEditorHtml = '<input type="hidden" name="schilo_sections[' + index + '][content]" value="">';
            extraFields = '';
        }

        if (type === 'evangiles') {
            contentEditorHtml = evangilesFieldTemplate(index);
        }

        let defaultTitle = '';

        if (type === 'detail-technique-img-droite') {
            defaultTitle = 'Détails techniques';
        }

        return `
            <div class="schilo-section-item schilo-section-card" data-index="${index}" data-section-index="${index}">
                <div class="schilo-section-item-header schilo-section-header">
                    <span class="schilo-drag-handle" title="Déplacer">☰</span>

                    <div class="schilo-section-heading">
                        <span class="schilo-section-number">${index + 1}</span>
                        <strong class="schilo-section-label">${label}</strong>
                    </div>

                    <div class="schilo-section-actions">
                        <button type="button" class="button-link schilo-toggle-section">Replier</button>
                        <button type="button" class="button-link schilo-duplicate-section">Dupliquer</button>
                        <button type="button" class="button-link-delete schilo-remove-section">Supprimer</button>
                    </div>
                </div>

                <div class="schilo-section-item-body schilo-section-body">
                    <input type="hidden" class="schilo-section-type-input" name="schilo_sections[${index}][type]" value="${type}">

                    <div class="schilo-form-grid">
                        <div class="schilo-field-row">
                            <label>
                                <strong>Titre</strong>
                                <input type="text" class="widefat schilo-title-input" name="schilo_sections[${index}][title]" value="${escapeHtml(defaultTitle)}">
                            </label>
                        </div>

                        <div class="schilo-field-row">
                            <label>
                                <strong>Classe CSS personnalisée</strong>
                                <input type="text" class="widefat" name="schilo_sections[${index}][custom_class]" value="" placeholder="ex: bloc-important">
                            </label>
                        </div>
                    </div>

                    <p class="description schilo-auto-class">Classe automatique : <code>schilo-section-${type}</code></p>

                    ${contentEditorHtml}

                    ${extraFields}
                </div>
            </div>
        `;
    }

    function initDetailBlockEditor(editorId) {
        if (typeof wp === 'undefined' || !wp.editor || !wp.editor.initialize) {
            return;
        }

        wp.editor.initialize(editorId, {
            tinymce: {
                wpautop: true,
                toolbar1: 'schilo_h1,schilo_h2,schilo_h3,schilo_h4,schilo_h5,schilo_h6,schilo_p,bold,italic,underline,bullist,numlist,blockquote,alignleft,aligncenter,alignright,link,unlink,pastetext,undo,redo',
                toolbar2: 'schilo_shortcodes',
                height: 130
            },
            quicktags: true,
            mediaButtons: false
        });
    }

    function initEditorsIn(container) {
        container.find('.schilo-dynamic-editor').each(function () {
            initEditor($(this).attr('id'));
        });

        container.find('.schilo-detail-block-editor').each(function () {
            initDetailBlockEditor($(this).attr('id'));
        });
    }

    $(document).on('click', '.schilo-add-section', function () {
        const type = $(this).data('type');
        $('.schilo-empty-message').remove();

        const html = createSection(type);
        const item = $(html);

        $('#schilo-sections-list').append(item);
        initEditorsIn(item);
        refreshIndexes();
    });

    $(document).on('click', '.schilo-remove-section', function () {
        if (confirm(SchiloBuilderAdmin.confirmDelete || 'Supprimer cette section ?')) {
            const item = $(this).closest('.schilo-section-item');

            item.find('textarea[id]').each(function () {
                removeEditor($(this).attr('id'));
            });

            item.remove();

            if ($('#schilo-sections-list .schilo-section-item').length === 0) {
                $('#schilo-sections-list').append('<p class="schilo-empty-message">Aucune section pour le moment.</p>');
            }

            refreshIndexes();
        }
    });

    $(document).on('click', '.schilo-duplicate-section', function () {
        if (!confirm(SchiloBuilderAdmin.confirmDuplicate || 'Dupliquer cette section ?')) return;

        const item = $(this).closest('.schilo-section-item');
        const type = item.find('.schilo-section-type-input').val() || 'paragraphe';
        const title = item.find('.schilo-title-input').val() || '';
        const customClass = item.find('input[name$="[custom_class]"]').val() || '';
        let content = '';

        const existingTextarea = item.find('textarea[name$="[content]"]').first();
        if (existingTextarea.length) {
            content = getEditorContent(existingTextarea.attr('id'));
        }

        const html = createSection(type);
        const clone = $(html);

        clone.find('.schilo-title-input').val(title + ' copie');
        clone.find('input[name$="[custom_class]"]').val(customClass);

        item.after(clone);
        initEditorsIn(clone);

        const newTextarea = clone.find('textarea[name$="[content]"]').first();
        const newEditorId = newTextarea.attr('id');

        setTimeout(function () {
            if (typeof tinymce !== 'undefined' && tinymce.get(newEditorId)) {
                tinymce.get(newEditorId).setContent(content);
            } else {
                newTextarea.val(content);
            }
        }, 300);

        const imageField = item.find('.schilo-image-field');
        if (imageField.length) {
            const imageId = imageField.find('.schilo-image-id').val();
            const imageHtml = imageField.find('.schilo-image-preview').html();
            clone.find('.schilo-image-id').val(imageId);
            clone.find('.schilo-image-preview').html(imageHtml);
            if (imageId) {
                clone.find('.schilo-image-preview').addClass('has-image');
            }
        }

        refreshIndexes();
    });

    $(document).on('click', '.schilo-toggle-section', function () {
        const button = $(this);
        const item = button.closest('.schilo-section-item');

        item.toggleClass('is-collapsed');
        item.find('.schilo-section-item-body').slideToggle(150);

        button.text(item.hasClass('is-collapsed') ? 'Ouvrir' : 'Replier');
    });

    $(document).on('click', '.schilo-collapse-all', function () {
        $('.schilo-section-item').each(function () {
            const item = $(this);
            item.addClass('is-collapsed');
            item.find('.schilo-section-item-body').hide();
            item.find('.schilo-toggle-section').text('Ouvrir');
        });
    });

    $(document).on('click', '.schilo-expand-all', function () {
        $('.schilo-section-item').each(function () {
            const item = $(this);
            item.removeClass('is-collapsed');
            item.find('.schilo-section-item-body').show();
            item.find('.schilo-toggle-section').text('Replier');
        });
    });

    $(document).on('input', '.schilo-title-input', function () {
        const input = $(this);
        const item = input.closest('.schilo-section-item');
        const title = input.val();

        item.find('.schilo-section-preview-title').remove();

        if (title.length > 0) {
            item.find('.schilo-section-label').after(`<span class="schilo-section-preview-title"> — ${title}</span>`);
        }
    });

    let schiloArticlesCache = null;

    function getSchiloArticles() {
        if (schiloArticlesCache === null) {
            schiloArticlesCache = [];
            const dataEl = document.getElementById('schilo-articles-data');

            if (dataEl) {
                try {
                    schiloArticlesCache = JSON.parse(dataEl.textContent);
                } catch (e) {
                    schiloArticlesCache = [];
                }
            }
        }

        return schiloArticlesCache;
    }

    function renderComboboxList(input) {
        const list = input.closest('.schilo-combobox').find('.schilo-combobox-list');
        const query = input.val().trim().toLowerCase();
        const articles = getSchiloArticles();

        let results = articles;

        if (query !== '') {
            results = articles.filter(function (article) {
                return article.title.toLowerCase().indexOf(query) !== -1;
            });
        }

        results = results.slice(0, 15);

        if (!results.length) {
            list.empty().hide();
            return;
        }

        list.empty();

        results.forEach(function (article) {
            const item = $('<li></li>')
                .text(article.title)
                .attr('data-url', article.url)
                .attr('data-title', article.title)
                .attr('data-id', article.id);
            list.append(item);
        });

        list.show();
    }

    $(document).on('focus input', '.schilo-link-article-search', function () {
        renderComboboxList($(this));
    });

    $(document).on('click', '.schilo-combobox-list li', function () {
        const item = $(this);
        const combobox = item.closest('.schilo-combobox');
        const linkItem = combobox.closest('.schilo-link-item');
        const title = item.data('title');
        const url = item.data('url');
        const id = item.data('id');

        combobox.find('.schilo-link-article-search').val(title);
        linkItem.find('.schilo-link-url').val(url);
        linkItem.find('.schilo-link-label').val(title);
        linkItem.find('.schilo-link-post-id').val(id);

        combobox.find('.schilo-combobox-list').empty().hide();
    });

    $(document).on('click', function (e) {
        if (!$(e.target).closest('.schilo-combobox').length) {
            $('.schilo-combobox-list').empty().hide();
        }
    });

    $(document).on('blur', '.schilo-link-article-search', function () {
        const input = $(this);
        const value = input.val().trim();
        const linkItem = input.closest('.schilo-link-item');
        const urlInput = linkItem.find('.schilo-link-url');
        const postIdInput = linkItem.find('.schilo-link-post-id');

        if (value === '') {
            urlInput.val('');
            postIdInput.val('');
            return;
        }

        const articles = getSchiloArticles();
        const exactMatch = articles.find(function (article) {
            return article.title === value;
        });

        if (exactMatch) {
            urlInput.val(exactMatch.url);
            postIdInput.val(exactMatch.id);
        } else if (/^https?:\/\//i.test(value) || value.charAt(0) === '/') {
            // URL collee a la main (pas un article du site) : pas d'ID a resoudre,
            // le lien restera enregistre en URL figee.
            urlInput.val(value);
            postIdInput.val('');
        }
    });

    $(document).on('click', '.schilo-add-link', function () {
        const button = $(this);
        const field = button.closest('.schilo-links-field');
        const itemsContainer = field.find('.schilo-links-items');
        const sectionItem = button.closest('.schilo-section-item');
        const sectionIndex = sectionItem.attr('data-section-index') || sectionItem.attr('data-index') || 0;
        const itemCount = itemsContainer.find('.schilo-link-item').length;

        itemsContainer.append(linkItemTemplate(sectionIndex, itemCount, '', ''));
    });

    $(document).on('click', '.schilo-remove-link', function () {
        const field = $(this).closest('.schilo-links-field');
        $(this).closest('.schilo-link-item').remove();

        field.find('.schilo-links-items .schilo-link-item').each(function (i) {
            $(this).find('input[name]').each(function () {
                const input = $(this);
                const name = input.attr('name');
                if (!name) return;
                input.attr('name', name.replace(/\[links\]\[\d+\]/, '[links][' + i + ']'));
            });
        });
    });

    $(document).on('click', '.schilo-add-optional-text', function () {
        const button = $(this);
        const wrapper = button.closest('.schilo-optional-text');
        const sectionItem = button.closest('.schilo-section-item');
        const sectionIndex = sectionItem.attr('data-section-index') || sectionItem.attr('data-index') || 0;
        const fieldKey = wrapper.data('field') || button.data('field');
        const label = wrapper.data('label') || button.data('label');
        const placeholder = wrapper.data('placeholder') || button.data('placeholder');

        wrapper.removeClass('schilo-optional-collapsed').addClass('schilo-optional-expanded');
        wrapper.html(optionalTextFieldTemplate(sectionIndex, fieldKey, label, placeholder, ''));
    });

    $(document).on('click', '.schilo-remove-text-field', function () {
        const wrapper = $(this).closest('.schilo-optional-text');
        const fieldKey = wrapper.data('field');
        const label = wrapper.data('label');
        const placeholder = wrapper.data('placeholder');

        wrapper.removeClass('schilo-optional-expanded').addClass('schilo-optional-collapsed');
        wrapper.html(optionalTextButtonTemplate(fieldKey, label, placeholder));
    });

    $(document).on('click', '.schilo-add-optional-image', function () {
        const button = $(this);
        const wrapper = button.closest('.schilo-optional-image');
        const sectionItem = button.closest('.schilo-section-item');
        const sectionIndex = sectionItem.attr('data-section-index') || sectionItem.attr('data-index') || 0;
        const fieldKey = wrapper.data('field') || button.data('field');
        const label = wrapper.data('label') || button.data('label');

        wrapper.removeClass('schilo-optional-collapsed').addClass('schilo-optional-expanded');
        wrapper.html(optionalImageFieldTemplate(sectionIndex, fieldKey, label));
    });

    $(document).on('click', '.schilo-remove-image-field', function () {
        const wrapper = $(this).closest('.schilo-optional-image');
        const fieldKey = wrapper.data('field');
        const label = wrapper.data('label');

        wrapper.removeClass('schilo-optional-expanded').addClass('schilo-optional-collapsed');
        wrapper.html(optionalImageButtonTemplate(fieldKey, label));
    });

    $(document).on('click', '.schilo-add-detail-block', function () {
        const button = $(this);
        const group = button.closest('.schilo-detail-blocks-field');
        const itemsContainer = group.find('.schilo-detail-blocks-items');
        const sectionItem = button.closest('.schilo-section-item');
        const sectionIndex = sectionItem.attr('data-section-index') || sectionItem.attr('data-index') || 0;
        const groupKey = group.data('group') || button.data('group') || 'blocks_before';
        const itemCount = itemsContainer.find('.schilo-detail-block-item').length;

        const html = detailBlockItemTemplate(groupKey, sectionIndex, itemCount, '', false);
        const item = $(html);

        itemsContainer.append(item);

        const editorId = item.find('.schilo-detail-block-editor').attr('id');
        initDetailBlockEditor(editorId);
    });


    $(document).on('click', '.schilo-remove-detail-block', function () {
        const group = $(this).closest('.schilo-detail-blocks-field');
        const item = $(this).closest('.schilo-detail-block-item');

        item.find('.schilo-detail-block-editor').each(function () {
            removeEditor($(this).attr('id'));
        });

        item.remove();

        group.find('.schilo-detail-blocks-items .schilo-detail-block-item').each(function (i) {
            $(this).find('.schilo-detail-block-editor[name]').each(function () {
                const input = $(this);
                const name = input.attr('name');
                if (!name) return;
                input.attr('name', name.replace(/\[(blocks_before|blocks_after)\]\[\d+\]/, '[$1][' + i + ']'));
            });
        });
    });

    $(document).on('click', '.schilo-select-image', function (e) {
        e.preventDefault();

        currentImageField = $(this).closest('.schilo-image-field');

        if (mediaFrame) {
            mediaFrame.open();
            return;
        }

        mediaFrame = wp.media({
            title: SchiloBuilderAdmin.mediaTitle || 'Choisir une image',
            button: {
                text: SchiloBuilderAdmin.mediaButton || 'Utiliser cette image'
            },
            multiple: false,
            library: {
                type: 'image'
            }
        });

        mediaFrame.on('select', function () {
            const attachment = mediaFrame.state().get('selection').first().toJSON();
            const previewUrl = attachment.sizes && attachment.sizes.medium
                ? attachment.sizes.medium.url
                : attachment.url;

            currentImageField.find('.schilo-image-id').val(attachment.id);
            currentImageField.find('.schilo-image-preview')
                .addClass('has-image')
                .html('<img src="' + previewUrl + '" alt="">');
        });

        mediaFrame.open();
    });

    $(document).on('click', '.schilo-remove-image', function (e) {
        e.preventDefault();

        const field = $(this).closest('.schilo-image-field');
        field.find('.schilo-image-id').val('');
        field.find('.schilo-image-preview')
            .removeClass('has-image')
            .html('<span>Aucune image sélectionnée</span>');
    });

    $('form#post').on('submit', function () {
        if (typeof tinymce !== 'undefined') {
            tinymce.triggerSave();
        }
    });

    $('#schilo-sections-list').sortable({
        handle: '.schilo-section-item-header',
        placeholder: 'schilo-section-placeholder',
        start: function (event, ui) {
            ui.item.find('textarea[id]').each(function () {
                const editorId = $(this).attr('id');
                const content = getEditorContent(editorId);
                $(this).val(content);
                removeEditor(editorId);
            });
        },
        stop: function (event, ui) {
            ui.item.find('.schilo-dynamic-editor').each(function () {
                initEditor($(this).attr('id'));
            });
            ui.item.find('.schilo-detail-block-editor').each(function () {
                initDetailBlockEditor($(this).attr('id'));
            });
            refreshIndexes();
        },
        update: refreshIndexes
    });

    refreshIndexes();

})(jQuery);


(function ($) {
    'use strict';

    function updateApplyTemplateLink() {
        const select = $('#schilo_builder_type');
        const button = $('.schilo-apply-template-button');

        if (!select.length || !button.length) {
            return;
        }

        const baseUrl = button.data('base-url');
        const nonce = button.data('nonce');

        if (!baseUrl || !nonce) {
            return;
        }

        const template = encodeURIComponent(select.val() || 'AUTO');
        button.attr('href', baseUrl + '&template=' + template + '&_wpnonce=' + encodeURIComponent(nonce));
    }

    $(document).on('change', '#schilo_builder_type', updateApplyTemplateLink);

    $(document).on('click', '.schilo-apply-template-button', function () {
        return confirm('Appliquer ce template ? Les sections manquantes seront ajoutées sans écraser le contenu existant.');
    });

    updateApplyTemplateLink();
})(jQuery);

(function ($) {
    'use strict';

    function refreshRepeatableIndexes(container) {
        const sectionItem = container.closest('.schilo-section-item');
        const sectionIndex = sectionItem.attr('data-section-index') || 0;

        container.find('.schilo-repeatable-item').each(function (i) {
            const input = $(this).find('input[name]').first();
            const name = input.attr('name') || '';
            input.attr('name', name.replace(/schilo_sections\[\d+\]/, 'schilo_sections[' + sectionIndex + ']').replace(/\[data\]\[([^\]]+)\]\[\d+\]/, '[data][$1][' + i + ']'));
        });
    }

    $(document).on('click', '.schilo-add-repeatable-item', function () {
        const button = $(this);
        const field = button.closest('.schilo-repeatable-field');
        const items = field.find('.schilo-repeatable-items');
        const itemCount = items.find('.schilo-repeatable-item').length;
        const sectionItem = button.closest('.schilo-section-item');
        const sectionIndex = sectionItem.attr('data-section-index') || 0;
        const fieldKey = field.data('field-key') || 'items';
        const placeholder = button.data('placeholder') || '';

        const html = `
            <div class="schilo-repeatable-item">
                <input type="text"
                       class="widefat"
                       name="schilo_sections[${sectionIndex}][data][${fieldKey}][${itemCount}]"
                       value=""
                       placeholder="${placeholder}">
                <button type="button" class="button schilo-remove-repeatable-item">Retirer</button>
            </div>
        `;

        items.append(html);
        refreshRepeatableIndexes(field);
    });

    $(document).on('click', '.schilo-remove-repeatable-item', function () {
        const field = $(this).closest('.schilo-repeatable-field');
        $(this).closest('.schilo-repeatable-item').remove();
        refreshRepeatableIndexes(field);
    });

})(jQuery);


(function ($) {
    'use strict';

    function refreshBibleRefIndexes(field) {
        const sectionItem = field.closest('.schilo-section-item');
        const sectionIndex = sectionItem.attr('data-section-index') || 0;

        field.find('.schilo-bible-ref-item').each(function (i) {
            $(this).find('[name]').each(function () {
                const input = $(this);
                const name = input.attr('name') || '';
                input.attr('name', name
                    .replace(/schilo_sections\[\d+\]/, 'schilo_sections[' + sectionIndex + ']')
                    .replace(/\[data\]\[versets\]\[(?:\d+|__ITEM_INDEX__)\]/, '[data][versets][' + i + ']'));
            });
        });
    }

    $(document).on('click', '.schilo-add-bible-ref-item', function () {
        const wrapper = $(this).closest('.schilo-bible-ref-field');
        const items = wrapper.find('.schilo-bible-ref-items');
        const template = wrapper.find('.schilo-bible-ref-template').html();

        if (!template) {
            return;
        }

        const nextIndex = items.find('.schilo-bible-ref-item').length;
        items.append(template.replace(/__ITEM_INDEX__/g, nextIndex));
        refreshBibleRefIndexes(wrapper);
    });

    $(document).on('click', '.schilo-remove-bible-ref-item', function () {
        const wrapper = $(this).closest('.schilo-bible-ref-field');
        $(this).closest('.schilo-bible-ref-item').remove();
        refreshBibleRefIndexes(wrapper);
    });

})(jQuery);


/* v0.8.0 — Empêche le retour en haut lors du clic Replier/Ouvrir */
(function ($) {
    'use strict';

    $(document).off('click.schiloToggleStable', '.schilo-toggle-section');
    $(document).on('click.schiloToggleStable', '.schilo-toggle-section', function (event) {
        event.preventDefault();
        event.stopPropagation();

        const scrollTop = $(window).scrollTop();
        const button = $(this);
        const item = button.closest('.schilo-section-item, .schilo-section-card');
        const body = item.find('.schilo-section-body, .schilo-section-item-body').first();

        if (!body.length) {
            return false;
        }

        body.toggle();

        const isHidden = !body.is(':visible');
        button.text(isHidden ? 'Ouvrir' : 'Replier');

        window.requestAnimationFrame(function () {
            $(window).scrollTop(scrollTop);
        });

        return false;
    });

    $(document).off('click.schiloCollapseAllStable', '.schilo-collapse-all');
    $(document).on('click.schiloCollapseAllStable', '.schilo-collapse-all', function (event) {
        event.preventDefault();

        const scrollTop = $(window).scrollTop();

        $('.schilo-section-body, .schilo-section-item-body').hide();
        $('.schilo-toggle-section').text('Ouvrir');

        window.requestAnimationFrame(function () {
            $(window).scrollTop(scrollTop);
        });

        return false;
    });

    $(document).off('click.schiloExpandAllStable', '.schilo-expand-all');
    $(document).on('click.schiloExpandAllStable', '.schilo-expand-all', function (event) {
        event.preventDefault();

        const scrollTop = $(window).scrollTop();

        $('.schilo-section-body, .schilo-section-item-body').show();
        $('.schilo-toggle-section').text('Replier');

        window.requestAnimationFrame(function () {
            $(window).scrollTop(scrollTop);
        });

        return false;
    });

})(jQuery);


/* v0.8.2 — correctif définitif Ouvrir/Replier */
(function ($) {
    'use strict';

    function getSectionBody(button) {
        const item = button.closest('.schilo-section-item, .schilo-section-card');

        let body = item.children('.schilo-section-body, .schilo-section-item-body').first();

        if (!body.length) {
            body = item.find('.schilo-section-body, .schilo-section-item-body').first();
        }

        return body;
    }

    // Retire tous les anciens handlers délégués liés aux boutons de pliage.
    $(document).off('click', '.schilo-toggle-section');
    $(document).off('click.schiloToggleStable', '.schilo-toggle-section');

    $(document).on('click.schiloToggleFinal', '.schilo-toggle-section', function (event) {
        event.preventDefault();
        event.stopImmediatePropagation();

        const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
        const button = $(this);
        const body = getSectionBody(button);

        if (!body.length) {
            return false;
        }

        if (body.is(':visible')) {
            body.hide();
            button.text('Ouvrir');
        } else {
            body.show();
            button.text('Replier');
        }

        window.requestAnimationFrame(function () {
            window.scrollTo(window.pageXOffset || document.documentElement.scrollLeft, scrollTop);
        });

        return false;
    });

    $(document).off('click', '.schilo-collapse-all');
    $(document).off('click.schiloCollapseAllStable', '.schilo-collapse-all');

    $(document).on('click.schiloCollapseAllFinal', '.schilo-collapse-all', function (event) {
        event.preventDefault();
        event.stopImmediatePropagation();

        const scrollTop = window.pageYOffset || document.documentElement.scrollTop;

        $('.schilo-section-body, .schilo-section-item-body').hide();
        $('.schilo-toggle-section').text('Ouvrir');

        window.requestAnimationFrame(function () {
            window.scrollTo(window.pageXOffset || document.documentElement.scrollLeft, scrollTop);
        });

        return false;
    });

    $(document).off('click', '.schilo-expand-all');
    $(document).off('click.schiloExpandAllStable', '.schilo-expand-all');

    $(document).on('click.schiloExpandAllFinal', '.schilo-expand-all', function (event) {
        event.preventDefault();
        event.stopImmediatePropagation();

        const scrollTop = window.pageYOffset || document.documentElement.scrollTop;

        $('.schilo-section-body, .schilo-section-item-body').show();
        $('.schilo-toggle-section').text('Replier');

        window.requestAnimationFrame(function () {
            window.scrollTo(window.pageXOffset || document.documentElement.scrollLeft, scrollTop);
        });

        return false;
    });

    /* ── Toggle panel "Ajouter une section" ─────────────────── */
    $(document).on('click', '#schilo-btn-add-section', function () {
        $('#schilo-add-panel').slideToggle(150);
    });

})(jQuery);

/* ══════════════════════════════════════════════════════════════
   Navigation par sections — ordre template + couleurs
══════════════════════════════════════════════════════════════ */
(function ($) {
    'use strict';

    var admin = (typeof SchiloBuilderAdmin !== 'undefined') ? SchiloBuilderAdmin : {};
    var templateOrder = Array.isArray(admin.templateSectionOrder) ? admin.templateSectionOrder : [];
    var typeLabels    = admin.sectionTypeLabels || {};
    var currentIndex  = null;
    var navMode       = false;

    function isFilled(item) {
        var filled = false;
        /* Exclure : custom_class (contient "schilo-migrated"), type hidden */
        var exclude = '[name$="[custom_class]"], [name$="[type]"]';
        item.find('input[type="text"], input[type="url"]').not(exclude).each(function () {
            if ($.trim($(this).val()) !== '') { filled = true; return false; }
        });
        if (!filled) {
            item.find('textarea').not(exclude).each(function () {
                if ($.trim($(this).val()) !== '') { filled = true; return false; }
            });
        }
        if (!filled) {
            item.find('select').not(exclude).each(function () {
                var v = $(this).val();
                if (v !== '' && v !== null && v !== '0') { filled = true; return false; }
            });
        }
        if (!filled && typeof tinymce !== 'undefined') {
            item.find('textarea[id]').each(function () {
                var ed = tinymce.get($(this).attr('id'));
                if (ed && $.trim(ed.getContent()) !== '') { filled = true; return false; }
            });
        }
        return filled;
    }

    function buildNavButtons() {
        var $nav = $('#schilo-nav-buttons');
        $nav.empty();

        var items = [];
        $('#schilo-sections-list .schilo-section-item').each(function () {
            var $item = $(this);
            var type  = $item.find('.schilo-section-type-input').val() || '';
            var label = typeLabels[type] || type;
            var idx   = parseInt($item.attr('data-index') || $item.attr('data-section-index'), 10);
            items.push({ type: type, label: label, idx: idx, $item: $item });
        });

        var inTemplate  = [];
        var outTemplate = [];

        templateOrder.forEach(function (type, pos) {
            var found = items.filter(function (it) { return it.type === type; });
            if (found.length) {
                found.forEach(function (it) { it.templatePos = pos; inTemplate.push(it); });
            } else {
                inTemplate.push({ type: type, label: typeLabels[type] || type, idx: null, $item: null, templatePos: pos, ghost: true });
            }
        });

        items.forEach(function (it) {
            if (templateOrder.indexOf(it.type) === -1) outTemplate.push(it);
        });

        var allNav = inTemplate.concat(outTemplate);

        allNav.forEach(function (it, navPos) {
            var filled   = it.$item ? isFilled(it.$item) : false;
            var inTpl    = templateOrder.indexOf(it.type) !== -1;
            var isActive = currentIndex !== null && it.idx === currentIndex;

            var statusClass = !inTpl ? 'snb-gray' : (filled ? 'snb-green' : 'snb-red');
            var $btn = $('<button type="button" class="snb-btn ' + statusClass + (isActive ? ' snb-active' : '') + (it.ghost ? ' snb-ghost' : '') + '">' +
                '<span class="snb-num">' + (navPos + 1) + '</span>' +
                '<span class="snb-label">' + escHtml(it.label) + '</span>' +
                '</button>');

            $btn.data('section-idx', it.idx);
            $btn.data('section-type', it.type);
            $btn.data('is-ghost', !!it.ghost);
            $nav.append($btn);
        });
    }

    function focusSection(domIndex) {
        navMode = true;
        currentIndex = domIndex;

        $('#schilo-sections-list .schilo-section-item').each(function () {
            var idx = parseInt($(this).attr('data-index') || $(this).attr('data-section-index'), 10);
            if (idx === domIndex) {
                $(this).show();
                $(this).removeClass('is-collapsed');
                $(this).find('.schilo-section-item-body').show();
                $(this).find('.schilo-toggle-section').text('Replier');
            } else {
                $(this).hide();
            }
        });

        if (!$('#schilo-show-all-btn').length) {
            $('#schilo-section-toolbar').prepend(
                '<button type="button" class="button" id="schilo-show-all-btn" style="margin-right:8px">← Toutes les sections</button>'
            );
        }

        buildNavButtons();
    }

    function showAll() {
        navMode = false;
        currentIndex = null;
        $('#schilo-sections-list .schilo-section-item').show();
        $('#schilo-show-all-btn').remove();
        $('#schilo-nav-info').text('');
        buildNavButtons();
    }

    $(document).on('click', '.snb-btn', function () {
        var idx   = $(this).data('section-idx');
        var ghost = $(this).data('is-ghost');
        var type  = $(this).data('section-type');

        if (ghost) {
            if (confirm('La section "' + (typeLabels[type] || type) + '" n\'existe pas encore. L\'ajouter ?')) {
                showAll();
                var $addBtn = $('.schilo-add-section[data-type="' + type + '"]');
                if ($addBtn.length) $addBtn.first().trigger('click');
                setTimeout(buildNavButtons, 300);
            }
            return;
        }

        if (idx === null) return;
        focusSection(idx);
    });

    $(document).on('click', '#schilo-show-all-btn', showAll);

    $(document).on('input change', '#schilo-sections-list input, #schilo-sections-list textarea, #schilo-sections-list select', function () {
        setTimeout(buildNavButtons, 100);
    });

    var navObserver = new MutationObserver(function () {
        setTimeout(buildNavButtons, 150);
    });
    var sectionsList = document.getElementById('schilo-sections-list');
    if (sectionsList) {
        navObserver.observe(sectionsList, { childList: true });
    }

    $(function () {
        if (!$('#schilo-nav-sidebar').length) return;
        buildNavButtons();
        /* Ouvrir automatiquement la première section au chargement */
        var $first = $('#schilo-sections-list .schilo-section-item').first();
        if ($first.length) {
            var firstIdx = parseInt($first.attr('data-index') || $first.attr('data-section-index'), 10);
            if (!isNaN(firstIdx)) focusSection(firstIdx);
        }
    });

    function escHtml(str) {
        return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

})(jQuery);

/* ── Image mise en avant custom (médiathèque WP) ────────────── */
(function ($) {
    'use strict';

    var featuredFrame = null;

    function openFeaturedFrame() {
        if (featuredFrame) {
            featuredFrame.open();
            return;
        }
        featuredFrame = wp.media({
            title: 'Choisir l\'image mise en avant',
            button: { text: 'Définir comme image mise en avant' },
            multiple: false,
            library: { type: 'image' }
        });
        featuredFrame.on('select', function () {
            var attachment = featuredFrame.state().get('selection').first().toJSON();
            var postId     = $('#schilo-set-thumbnail').data('post-id');
            var nonce      = $('#schilo-set-thumbnail').data('nonce');

            $.post(ajaxurl, {
                action:        'set-post-thumbnail',
                post_id:       postId,
                thumbnail_id:  attachment.id,
                _ajax_nonce:   nonce,
                cookie:        encodeURIComponent(document.cookie)
            }, function (html) {
                /* Mettre à jour l'aperçu */
                var src = attachment.sizes && attachment.sizes.medium
                        ? attachment.sizes.medium.url
                        : attachment.url;
                var $wrap = $('#schilo-thumbnail-wrap');
                $wrap.removeClass('stc-thumb--empty').addClass('stc-thumb--set');
                $wrap.html(
                    '<img src="' + src + '" id="schilo-thumbnail-img">' +
                    '<div class="stc-thumb-btns">' +
                    '<button type="button" id="schilo-set-thumbnail" data-post-id="' + postId + '" data-nonce="' + nonce + '" class="stc-img-btn">Changer</button>' +
                    '<button type="button" id="schilo-remove-thumbnail" class="stc-img-btn stc-img-btn--danger">Supprimer</button>' +
                    '</div>'
                );
                $('#schilo_thumbnail_id').val(attachment.id);
                featuredFrame = null;
            });
        });
        featuredFrame.open();
    }

    $(document).on('click', '#schilo-set-thumbnail, #schilo-thumbnail-img, .stc-thumb-placeholder, .stc-thumb--empty', function (e) {
        e.preventDefault();
        openFeaturedFrame();
    });

    $(document).on('click', '#schilo-remove-thumbnail', function (e) {
        e.preventDefault();
        var postId = $('#schilo-set-thumbnail').data('post-id');
        var nonce  = $('#schilo-set-thumbnail').data('nonce');
        $.post(ajaxurl, {
            action:       'set-post-thumbnail',
            post_id:      postId,
            thumbnail_id: -1,
            _ajax_nonce:  nonce,
            cookie:       encodeURIComponent(document.cookie)
        }, function () {
            var postId2 = $('#schilo-set-thumbnail').data('post-id') || '';
            var nonce2  = $('#schilo-set-thumbnail').data('nonce') || '';
            var $wrap = $('#schilo-thumbnail-wrap');
            $wrap.removeClass('stc-thumb--set').addClass('stc-thumb--empty');
            $wrap.html(
                '<button type="button" id="schilo-set-thumbnail" data-post-id="' + postId2 + '" data-nonce="' + nonce2 + '" class="stc-thumb-placeholder">' +
                '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>' +
                '<span>Définir l\'image</span></button>'
            );
            $('#schilo_thumbnail_id').val(-1);
        });
    });

})(jQuery);
