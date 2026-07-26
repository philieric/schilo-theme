(function ($) {
    'use strict';

    if (typeof schiloWorkflow === 'undefined') {
        return;
    }

    var $root = $('.schilo-workflow-metabox');
    if (!$root.length) {
        return;
    }

    function block(target) {
        return $root.find('.schilo-workflow-block[data-workflow="' + target + '"]');
    }

    function feedback(target, message, isError) {
        var $f = block(target).find('[data-feedback="' + target + '"]');
        $f.text(message).css('color', isError ? '#b32d2e' : '#2271b1').show();
    }

    function updateCounter($field) {
        var $counter = $field.closest('.schilo-workflow-field').find('[data-counter-for="' + $field.attr('name') + '"]');
        if (!$counter.length) {
            return;
        }
        var max = parseInt($field.attr('maxlength'), 10) || 0;
        var len = ($field.val() || '').length;
        $counter.text(len + '/' + max).toggleClass('is-near-limit', max > 0 && len >= max * 0.9);
    }

    $root.find('[data-counter-for]').each(function () {
        var name = $(this).data('counter-for');
        updateCounter($root.find('[name="' + name + '"]'));
    });

    $root.on('input', 'textarea[maxlength]', function () {
        updateCounter($(this));
    });

    function setBadge(target, label, cssClass, prefix) {
        var $badge = block(target).find('[data-role="badge"]');
        var keep = $badge.attr('class').split(/\s+/).filter(function (c) {
            return c === prefix + '-badge';
        });
        keep.push(cssClass);
        $badge.attr('class', keep.join(' ')).text(label);
    }

    function field($zone, name) {
        return $zone.find('[name="' + name + '"]');
    }

    $root.on('click', '.schilo-workflow-btn-ia', function () {
        var $btn = $(this);
        var target = $btn.data('target');
        var $zone = block(target).find('.schilo-workflow-zone');

        $btn.prop('disabled', true).attr('aria-busy', 'true');
        feedback(target, 'Appel IA en cours…', false);

        if (target === 'indexation') {
            $.post(schiloWorkflow.ajaxUrl, {
                action: 'schilo_ia_index_article',
                nonce: schiloWorkflow.indexationNonce,
                post_id: schiloWorkflow.postId,
                provider: schiloWorkflow.provider
            }).done(function (res) {
                if (!res.success) {
                    feedback(target, (res.data && res.data.message) || 'Erreur IA.', true);
                    return;
                }
                var fields = res.data.fields || {};
                var $resume = field($zone, 'resume_court').val(fields.resume_court || '');
                updateCounter($resume);
                field($zone, 'mots_cles').val(Array.isArray(fields.mots_cles) ? fields.mots_cles.join(', ') : (fields.mots_cles || ''));
                field($zone, 'theme_principal').val(fields.theme_principal || '');
                field($zone, 'parcours').val(fields.parcours || '');
                field($zone, 'serie').val(fields.serie || '');

                if (res.data.auto_saved) {
                    setBadge(target, 'Valide', 'sia-badge-green', 'sia');
                    feedback(target, 'Indexe et enregistre automatiquement.', false);
                } else {
                    setBadge(target, 'Brouillon', 'sia-badge-grey', 'sia');
                    feedback(target, 'Proposition IA chargee — relisez puis Enregistrer.', false);
                }
                if (res.data.fallback_msg) {
                    feedback(target, res.data.fallback_msg, false);
                }
            }).fail(function () {
                feedback(target, 'Erreur reseau.', true);
            }).always(function () {
                $btn.prop('disabled', false).attr('aria-busy', 'false');
            });
        } else {
            $.post(schiloWorkflow.ajaxUrl, {
                action: 'schilo_classement_classify',
                nonce: schiloWorkflow.classementNonce,
                post_id: schiloWorkflow.postId,
                provider: schiloWorkflow.provider
            }).done(function (res) {
                if (!res.success) {
                    feedback(target, (res.data && res.data.message) || 'Erreur IA.', true);
                    return;
                }
                var s = res.data.suggestion || {};
                field($zone, 'theme').val(s.theme || '');
                field($zone, 'parcours').val(Array.isArray(s.parcours) ? s.parcours.join(', ') : (s.parcours || ''));
                field($zone, 'serie').val(s.serie || '');
                feedback(target, 'Suggestion IA chargee — relisez puis Enregistrer.', false);
            }).fail(function () {
                feedback(target, 'Erreur reseau.', true);
            }).always(function () {
                $btn.prop('disabled', false).attr('aria-busy', 'false');
            });
        }
    });

    $root.on('click', '.schilo-workflow-btn-save', function () {
        var $btn = $(this);
        var target = $btn.data('target');
        var $zone = block(target).find('.schilo-workflow-zone');

        $btn.prop('disabled', true).attr('aria-busy', 'true');
        feedback(target, 'Enregistrement…', false);

        if (target === 'indexation') {
            var motsCles = (field($zone, 'mots_cles').val() || '').split(',').map(function (s) { return s.trim(); }).filter(Boolean);
            $.post(schiloWorkflow.ajaxUrl, {
                action: 'schilo_save_indexation_validated',
                nonce: schiloWorkflow.indexationNonce,
                data: {
                    post_id: schiloWorkflow.postId,
                    resume_court: field($zone, 'resume_court').val(),
                    mots_cles: motsCles,
                    theme_principal: field($zone, 'theme_principal').val(),
                    parcours: field($zone, 'parcours').val(),
                    serie: field($zone, 'serie').val()
                }
            }).done(function (res) {
                if (!res.success) {
                    feedback(target, (res.data && res.data.message) || 'Erreur enregistrement.', true);
                    return;
                }
                setBadge(target, 'Valide', 'sia-badge-green', 'sia');
                feedback(target, 'Enregistre.', false);
            }).fail(function () {
                feedback(target, 'Erreur reseau.', true);
            }).always(function () {
                $btn.prop('disabled', false).attr('aria-busy', 'false');
            });
        } else {
            $.post(schiloWorkflow.ajaxUrl, {
                action: 'schilo_classement_save',
                nonce: schiloWorkflow.classementNonce,
                post_id: schiloWorkflow.postId,
                new_theme: field($zone, 'theme').val(),
                new_parcours: field($zone, 'parcours').val(),
                new_serie: field($zone, 'serie').val()
            }).done(function (res) {
                if (!res.success) {
                    feedback(target, (res.data && res.data.message) || 'Erreur enregistrement.', true);
                    return;
                }
                setBadge(target, 'Classe', 'scl-badge-green', 'scl');
                feedback(target, 'Enregistre.', false);
            }).fail(function () {
                feedback(target, 'Erreur reseau.', true);
            }).always(function () {
                $btn.prop('disabled', false).attr('aria-busy', 'false');
            });
        }
    });

})(jQuery);
