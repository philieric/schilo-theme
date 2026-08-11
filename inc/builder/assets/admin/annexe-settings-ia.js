/**
 * Bouton "Générer via IA" du texte d'introduction du bloc "annexes"
 * (Schilo Builder > Versions d'article). Remplit le champ texte sans
 * l'enregistrer — l'admin garde la main pour relire/ajuster avant de
 * cliquer sur "Enregistrer".
 */
(function ($) {
    'use strict';

    $(function () {
        var $button = $('#schilo-annexe-ia-generate');
        if (!$button.length || typeof schiloAnnexeIa === 'undefined') {
            return;
        }

        var $status = $('#schilo-annexe-ia-status');
        var $text = $('#schilo-annexe-text');
        var $label = $('#schilo-annexe-label');

        $button.on('click', function () {
            $button.prop('disabled', true);
            $status.text('Génération en cours…');
            if (window.SchiloAiOverlay) window.SchiloAiOverlay.show('Génération du texte via IA…');

            $.ajax({
                url: schiloAnnexeIa.ajaxUrl,
                type: 'POST',
                timeout: 90000,
                data: {
                    action: 'schilo_generate_annexe_text',
                    nonce: schiloAnnexeIa.nonce,
                    label: $label.val()
                }
            }).done(function (res) {
                if (res && res.success && res.data && res.data.text) {
                    $text.val(res.data.text);
                    $status.text('✓ Texte généré — vérifiez puis cliquez sur "Enregistrer".');
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
