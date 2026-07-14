/**
 * Bouton "Générer via IA" sur l'écran d'édition d'une catégorie WP
 * (Articles > Catégories > modifier). Remplit le champ Description
 * sans l'enregistrer — l'admin garde la main pour relire/ajuster avant
 * de cliquer sur "Mettre à jour".
 */
(function ($) {
    'use strict';

    $(function () {
        var $button = $('#schilo-category-ia-generate');
        if (!$button.length || typeof schiloCategoryIa === 'undefined') {
            return;
        }

        var $status = $('#schilo-category-ia-status');
        var $description = $('#description');

        $button.on('click', function () {
            $button.prop('disabled', true);
            $status.text('Génération en cours…');
            if (window.SchiloAiOverlay) window.SchiloAiOverlay.show('Génération de la description via IA…');

            $.ajax({
                url: schiloCategoryIa.ajaxUrl,
                type: 'POST',
                timeout: 90000,
                data: {
                    action: 'schilo_generate_category_description',
                    nonce: schiloCategoryIa.nonce,
                    term_id: schiloCategoryIa.termId
                }
            }).done(function (res) {
                if (res && res.success && res.data && res.data.description) {
                    $description.val(res.data.description);
                    $status.text('✓ Description générée — vérifiez puis cliquez sur "Mettre à jour".');
                } else {
                    var message = (res && res.data && res.data.message) ? res.data.message : 'Erreur inconnue.';
                    $status.text('Erreur : ' + message);
                }
            }).fail(function () {
                $status.text('Erreur réseau, réessayez.');
            }).always(function () {
                if (window.SchiloAiOverlay) window.SchiloAiOverlay.hide();
                $button.prop('disabled', false);
            });
        });
    });
})(jQuery);
