(function ($) {
    'use strict';

    /* Overlay de traitement IA partagé (grisement + spinner). Gardes tolérantes. */
    function aiOverlayShow(msg) { if (window.SchiloAiOverlay) window.SchiloAiOverlay.show(msg); }
    function aiOverlayUpdate(msg) { if (window.SchiloAiOverlay) window.SchiloAiOverlay.update(msg); }
    function aiOverlayHide() { if (window.SchiloAiOverlay) window.SchiloAiOverlay.hide(); }

    // manageOverlay : false quand appelé depuis « tout générer » (l'overlay est
    // alors piloté en continu par le bouton global, avec sa propre progression).
    function suggestRow($button, manageOverlay) {
        var $cell = $button.closest('td');
        var $textarea = $cell.find('textarea');
        var $feedback = $cell.find('.schilo-definition-ia-feedback');
        var deferred = $.Deferred();

        $button.prop('disabled', true);
        $feedback.text('Génération en cours…').css('color', '#2872d4');
        if (manageOverlay !== false) aiOverlayShow('Génération des déclencheurs via IA…');

        $.ajax({
            url: schiloDefinitions.ajaxUrl,
            type: 'POST',
            timeout: 120000,
            data: {
                action: 'schilo_definition_suggest_terms',
                nonce: schiloDefinitions.nonce,
                provider: $('#schilo-definition-provider').val() || 'claude',
                post_id: $button.data('post-id'),
                existing_terms: $textarea.val()
            }
        }).done(function (response) {
            if (manageOverlay !== false) aiOverlayHide();
            $button.prop('disabled', false);
            if (!response.success) {
                $feedback.text((response.data && response.data.message) || 'Erreur IA.').css('color', '#dc2626');
                deferred.reject((response.data && response.data.message) || 'Erreur IA.');
                return;
            }

            var existing = $textarea.val().split(/\r?\n/).map(function (term) { return term.trim(); }).filter(Boolean);
            var seen = {};
            var merged = [];
            existing.concat(response.data.terms || []).forEach(function (term) {
                var key = term.toLocaleLowerCase('fr');
                if (seen[key]) return;
                seen[key] = true;
                merged.push(term);
            });
            $textarea.val(merged.join('\n')).trigger('change');
            $feedback.text('Suggestions ajoutées — enregistrez pour les conserver.').css('color', '#15803d');
            deferred.resolve();
        }).fail(function () {
            if (manageOverlay !== false) aiOverlayHide();
            $button.prop('disabled', false);
            $feedback.text('Erreur réseau.').css('color', '#dc2626');
            deferred.reject('Erreur réseau.');
        });

        return deferred.promise();
    }

    $(document).on('click', '.schilo-definition-suggest-ia', function () {
        suggestRow($(this));
    });

    $('#schilo-definition-suggest-all').on('click', function () {
        var $globalButton = $(this);
        var $feedback = $('#schilo-definition-global-feedback');
        var $buttons = $('.schilo-definition-suggest-ia');
        var total = $buttons.length;
        var completed = 0;
        var errors = 0;

        if (!total || !window.confirm('Générer des déclencheurs IA pour ' + total + ' fiche(s) ? Chaque fiche nécessite un appel IA.')) return;

        $globalButton.prop('disabled', true);
        $buttons.prop('disabled', true);
        aiOverlayShow('Génération des déclencheurs IA… 1/' + total);

        function next(index) {
            if (index >= total) {
                aiOverlayHide();
                $globalButton.prop('disabled', false);
                $buttons.prop('disabled', false);
                $feedback
                    .text(completed + ' fiche(s) traitée(s)' + (errors ? ', ' + errors + ' erreur(s)' : '') + ' — enregistrez pour conserver les suggestions.')
                    .css('color', errors ? '#b45309' : '#15803d');
                return;
            }

            $buttons.prop('disabled', true);
            $feedback.text('Génération ' + (index + 1) + '/' + total + '…').css('color', '#2872d4');
            aiOverlayUpdate('Génération des déclencheurs IA… ' + (index + 1) + '/' + total);
            suggestRow($buttons.eq(index), false).then(function () {
                completed++;
                next(index + 1);
            }, function () {
                errors++;
                next(index + 1);
            });
        }

        next(0);
    });
})(jQuery);
