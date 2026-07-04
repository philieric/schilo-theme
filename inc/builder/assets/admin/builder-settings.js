(function ($) {
    'use strict';

    function getNextIndex() {
        return $('#schilo-prefix-category-rows tr').length;
    }

    $('#schilo-add-prefix-row').on('click', function () {
        const template = $('#schilo-prefix-row-template').html();
        if (!template) return;
        const html = template.replace(/__INDEX__/g, getNextIndex());
        $('#schilo-prefix-category-rows').append(html);
    });

    $(document).on('click', '.schilo-remove-prefix-row', function () {
        $(this).closest('tr').remove();
    });

    $(document).on('input', '.schilo-prefix-input', function () {
        this.value = this.value.toUpperCase().replace(/[^A-Z]/g, '').substring(0, 3);
    });

    function getNextSectionTypeIndex() {
        return $('#schilo-section-type-rows tr').length;
    }

    $('#schilo-add-section-type-row').on('click', function () {
        const template = $('#schilo-section-type-row-template').html();
        if (!template) return;
        const html = template.replace(/__INDEX__/g, getNextSectionTypeIndex());
        $('#schilo-section-type-rows').append(html);
    });

    $(document).on('click', '.schilo-remove-section-type-row', function () {
        $(this).closest('tr').remove();
    });

    $(document).on('input', '.schilo-section-key-input', function () {
        this.value = this.value.toLowerCase()
            .replace(/[^a-z0-9\-]/g, '')
            .replace(/\s+/g, '-');
    });

})(jQuery);

(function ($) {
    'use strict';
    function getNextTemplateIndex() { return $('#schilo-template-rows tr.schilo-template-row-main').length; }
    $('#schilo-add-template-row').on('click', function () {
        const template = $('#schilo-template-row-template').html();
        if (!template) return;
        $('#schilo-template-rows').append(template.replace(/__INDEX__/g, getNextTemplateIndex()));
    });
    $(document).on('click', '.schilo-remove-template-row', function () {
        $(this).closest('tr').next('.schilo-template-row-sections').remove();
        $(this).closest('tr').remove();
    });
    $(document).on('input', '.schilo-template-key-input', function () {
        this.value = this.value.toUpperCase().replace(/[^A-Z]/g, '').substring(0, 8);
    });

    function toggleTemplateSections(mainRow, collapse) {
        const sectionsRow = mainRow.next('.schilo-template-row-sections');
        const button = mainRow.find('.schilo-toggle-template-sections');

        sectionsRow.toggle(!collapse);
        button.text(collapse ? 'Déplier' : 'Replier');
    }

    $(document).on('click', '.schilo-toggle-template-sections', function () {
        const mainRow = $(this).closest('tr');
        const sectionsRow = mainRow.next('.schilo-template-row-sections');
        const isCollapsed = sectionsRow.is(':hidden');

        toggleTemplateSections(mainRow, !isCollapsed);
    });

    $('#schilo-collapse-all-templates').on('click', function () {
        $('#schilo-template-rows tr.schilo-template-row-main').each(function () {
            toggleTemplateSections($(this), true);
        });
    });

    $('#schilo-expand-all-templates').on('click', function () {
        $('#schilo-template-rows tr.schilo-template-row-main').each(function () {
            toggleTemplateSections($(this), false);
        });
    });

    $('#schilo-sections-view-toggle').on('click', function () {
        const button = $(this);
        const table = $('#schilo-templates-table');
        const isGrid = table.hasClass('schilo-sections-grid-mode');

        table.toggleClass('schilo-sections-grid-mode', !isGrid);
        button.text(isGrid ? 'Mode grille' : 'Mode liste');
        button.attr('data-mode', isGrid ? 'list' : 'grid');
    });
})(jQuery);

/* ── Outils : chargement AJAX des panneaux ──────────────────────── */
(function ($) {
    'use strict';

    if (!$('.schilo-outils-grid').length) return;

    var $panel  = $('#schilo-tool-panel');
    var current = (typeof schiloOutilsActive !== 'undefined') ? schiloOutilsActive : '';
    var loading = false;

    // Après POST : carte déjà active côté serveur, pas de rechargement AJAX
    if (current) {
        $('.schilo-outil-card[data-tool="' + current + '"]').addClass('is-active');
    }

    function loadTool(tool) {
        if (loading) return;
        loading = true;

        $('.schilo-outil-card').removeClass('is-active');
        $('.schilo-outil-card[data-tool="' + tool + '"]').addClass('is-active');
        $panel.html('<p style="padding:20px;color:#6b7280;font-style:italic;">Chargement…</p>');

        $.post(
            schiloBuilder.ajaxUrl,
            { action: 'schilo_load_tool', nonce: schiloBuilder.loadToolNonce, tool: tool }
        ).done(function (html) {
            $panel.html(html);
            current = tool;
        }).fail(function () {
            $panel.html('<p style="padding:20px;color:#b32d2e;">Erreur lors du chargement.</p>');
        }).always(function () {
            loading = false;
        });
    }

    $(document).on('click', '.schilo-outil-card', function () {
        var tool = $(this).data('tool');
        if (!tool) return;

        // Deuxième clic sur l'outil actif → ferme le panel
        if ($(this).hasClass('is-active') && current === tool) {
            $(this).removeClass('is-active');
            $panel.empty();
            current = '';
            return;
        }

        loadTool(tool);
    });

})(jQuery);
