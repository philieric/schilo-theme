<?php
/**
 * Vue : Configuration Intelligence Artificielle
 * Variables : $ia_config, $saved, $save_error
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$claude_key   = isset( $ia_config['claude']['api_key'] ) ? $ia_config['claude']['api_key'] : '';
$claude_model = isset( $ia_config['claude']['model']   ) ? $ia_config['claude']['model']   : 'claude-sonnet-4-6';
$openai_key   = isset( $ia_config['openai']['api_key'] ) ? $ia_config['openai']['api_key'] : '';
$openai_model = isset( $ia_config['openai']['model']   ) ? $ia_config['openai']['model']   : 'gpt-4o';
$default_prov = isset( $ia_config['default_provider']  ) ? $ia_config['default_provider']  : 'claude';
$temperature  = isset( $ia_config['temperature']       ) ? (float) $ia_config['temperature'] : 0.7;

/* Masquage partiel de la clé */
function schilo_mask_key( $key ) {
    if ( strlen( $key ) < 8 ) return $key ? str_repeat( '*', strlen( $key ) ) : '';
    return str_repeat( '*', strlen( $key ) - 6 ) . substr( $key, -6 );
}

$claude_models = array(
    'claude-sonnet-4-6'           => 'Claude Sonnet 4.6 (recommandé)',
    'claude-haiku-4-5-20251001'   => 'Claude Haiku 4.5 (rapide/économique)',
    'claude-opus-4-8'             => 'Claude Opus 4.8 (le plus puissant)',
);
$openai_models = array(
    'gpt-4o'        => 'GPT-4o (recommandé)',
    'gpt-4o-mini'   => 'GPT-4o Mini (rapide/économique)',
    'gpt-4-turbo'   => 'GPT-4 Turbo',
    'gpt-3.5-turbo' => 'GPT-3.5 Turbo (économique)',
);
?>

<div class="wrap schilo-builder-settings">
    <h1>
        <span class="dashicons dashicons-superhero" style="font-size:26px;vertical-align:middle;margin-right:8px;color:#6d3fc0"></span>
        Intelligence Artificielle
    </h1>
    <p class="schilo-dashboard-intro">
        Configurez vos clés API pour utiliser Claude (Anthropic) et/ou ChatGPT (OpenAI) dans l'administration Schilo.
        Les clés sont stockées de façon sécurisée dans la base de données WordPress et ne sont jamais exposées en front-end.
    </p>

    <?php if ( $saved ) : ?>
        <div class="notice notice-success is-dismissible schilo-keep"><p><strong>Configuration enregistrée.</strong></p></div>
    <?php endif; ?>
    <?php if ( $save_error ) : ?>
        <div class="notice notice-error is-dismissible schilo-keep"><p><?php echo esc_html( $save_error ); ?></p></div>
    <?php endif; ?>

    <form method="post" id="schilo-ia-form">
        <?php wp_nonce_field( 'schilo_save_ia_config', 'schilo_ia_nonce' ); ?>

        <div class="sia-grid">

            <!-- ══ CLAUDE ══════════════════════════════════════════ -->
            <div class="sia-card" id="sia-card-claude">
                <div class="sia-card-header sia-header-claude">
                    <span class="sia-logo">
                        <svg width="22" height="22" viewBox="0 0 48 48" fill="none"><circle cx="24" cy="24" r="24" fill="#D97706"/><text x="24" y="30" text-anchor="middle" font-size="18" fill="white" font-family="Georgia,serif">C</text></svg>
                    </span>
                    <div>
                        <strong>Claude — Anthropic</strong>
                        <span class="sia-provider-badge">Anthropic</span>
                    </div>
                    <span class="sia-status-dot" id="sia-status-claude" title="Non testé"></span>
                </div>

                <div class="sia-card-body">
                    <div class="sia-field">
                        <label for="sia_claude_key">Clé API</label>
                        <div class="sia-key-row">
                            <input type="password"
                                   id="sia_claude_key"
                                   name="sia_claude_key"
                                   class="regular-text sia-key-input"
                                   placeholder="sk-ant-api03-..."
                                   autocomplete="new-password"
                                   value="<?php echo esc_attr( $claude_key ? schilo_mask_key( $claude_key ) : '' ); ?>"
                                   data-has-value="<?php echo $claude_key ? '1' : '0'; ?>">
                            <button type="button" class="button sia-toggle-key" data-target="sia_claude_key" title="Afficher/masquer">
                                <span class="dashicons dashicons-visibility"></span>
                            </button>
                        </div>
                        <p class="description">Disponible sur <a href="https://console.anthropic.com/" target="_blank" rel="noopener">console.anthropic.com</a></p>
                    </div>

                    <div class="sia-field">
                        <label for="sia_claude_model">Modèle par défaut</label>
                        <select id="sia_claude_model" name="sia_claude_model" class="sia-select">
                            <?php foreach ( $claude_models as $val => $label ) : ?>
                                <option value="<?php echo esc_attr( $val ); ?>" <?php selected( $claude_model, $val ); ?>>
                                    <?php echo esc_html( $label ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="sia-actions">
                        <button type="button"
                                class="button sia-test-btn"
                                data-provider="claude"
                                data-key-field="sia_claude_key"
                                data-model-field="sia_claude_model">
                            <span class="dashicons dashicons-update sia-spin-icon" style="display:none"></span>
                            Tester la connexion
                        </button>
                        <span class="sia-test-result" id="sia-result-claude"></span>
                    </div>
                </div>
            </div>

            <!-- ══ CHATGPT / OPENAI ════════════════════════════════ -->
            <div class="sia-card" id="sia-card-openai">
                <div class="sia-card-header sia-header-openai">
                    <span class="sia-logo">
                        <svg width="22" height="22" viewBox="0 0 48 48" fill="none"><circle cx="24" cy="24" r="24" fill="#10a37f"/><text x="24" y="30" text-anchor="middle" font-size="18" fill="white" font-family="Arial,sans-serif">G</text></svg>
                    </span>
                    <div>
                        <strong>ChatGPT — OpenAI</strong>
                        <span class="sia-provider-badge">OpenAI</span>
                    </div>
                    <span class="sia-status-dot" id="sia-status-openai" title="Non testé"></span>
                </div>

                <div class="sia-card-body">
                    <div class="sia-field">
                        <label for="sia_openai_key">Clé API</label>
                        <div class="sia-key-row">
                            <input type="password"
                                   id="sia_openai_key"
                                   name="sia_openai_key"
                                   class="regular-text sia-key-input"
                                   placeholder="sk-..."
                                   autocomplete="new-password"
                                   value="<?php echo esc_attr( $openai_key ? schilo_mask_key( $openai_key ) : '' ); ?>"
                                   data-has-value="<?php echo $openai_key ? '1' : '0'; ?>">
                            <button type="button" class="button sia-toggle-key" data-target="sia_openai_key" title="Afficher/masquer">
                                <span class="dashicons dashicons-visibility"></span>
                            </button>
                        </div>
                        <p class="description">Disponible sur <a href="https://platform.openai.com/api-keys" target="_blank" rel="noopener">platform.openai.com</a></p>
                    </div>

                    <div class="sia-field">
                        <label for="sia_openai_model">Modèle par défaut</label>
                        <select id="sia_openai_model" name="sia_openai_model" class="sia-select">
                            <?php foreach ( $openai_models as $val => $label ) : ?>
                                <option value="<?php echo esc_attr( $val ); ?>" <?php selected( $openai_model, $val ); ?>>
                                    <?php echo esc_html( $label ); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="sia-actions">
                        <button type="button"
                                class="button sia-test-btn"
                                data-provider="openai"
                                data-key-field="sia_openai_key"
                                data-model-field="sia_openai_model">
                            <span class="dashicons dashicons-update sia-spin-icon" style="display:none"></span>
                            Tester la connexion
                        </button>
                        <span class="sia-test-result" id="sia-result-openai"></span>
                    </div>
                </div>
            </div>

        </div><!-- .sia-grid -->

        <!-- ══ PARAMÈTRES GÉNÉRAUX ════════════════════════════════ -->
        <div class="sia-general-card">
            <h2>Paramètres généraux</h2>

            <div class="sia-field-inline">
                <label>Provider par défaut</label>
                <div class="sia-radio-group">
                    <label class="sia-radio-label">
                        <input type="radio" name="sia_default_provider" value="claude" <?php checked( $default_prov, 'claude' ); ?>>
                        Claude (Anthropic)
                    </label>
                    <label class="sia-radio-label">
                        <input type="radio" name="sia_default_provider" value="openai" <?php checked( $default_prov, 'openai' ); ?>>
                        ChatGPT (OpenAI)
                    </label>
                </div>
            </div>

            <div class="sia-field-inline">
                <label for="sia_temperature">Température <span class="sia-temp-val"><?php echo esc_html( $temperature ); ?></span></label>
                <div class="sia-range-wrap">
                    <input type="range" id="sia_temperature" name="sia_temperature"
                           min="0" max="1" step="0.1"
                           value="<?php echo esc_attr( $temperature ); ?>"
                           class="sia-range">
                    <div class="sia-range-labels">
                        <span>Précis (0)</span>
                        <span>Créatif (1)</span>
                    </div>
                </div>
                <p class="description">Contrôle la créativité des réponses. Valeur recommandée : 0.7 pour les articles.</p>
            </div>
        </div>

        <div class="sia-submit-bar">
            <button type="submit" name="schilo_ia_save" class="button button-primary sia-save-btn">
                <span class="dashicons dashicons-saved" style="vertical-align:middle;margin-right:4px"></span>
                Enregistrer la configuration
            </button>
        </div>

    </form>
</div>

<style>
:root { --sia-claude: #D97706; --sia-openai: #10a37f; --sia-border: #e2e8f0; --sia-bg: #f8fafc; }

.sia-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    max-width: 900px;
    margin: 24px 0 20px;
}
.sia-card {
    background: #fff;
    border: 1px solid var(--sia-border);
    border-radius: 10px;
    overflow: hidden;
    box-shadow: 0 1px 4px rgba(0,0,0,.06);
}
.sia-card-header {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 14px 18px;
    border-bottom: 1px solid var(--sia-border);
    background: var(--sia-bg);
}
.sia-header-claude { border-left: 4px solid var(--sia-claude); }
.sia-header-openai { border-left: 4px solid var(--sia-openai); }
.sia-logo { flex-shrink: 0; }
.sia-card-header strong { display: block; font-size: 14px; color: #1e293b; }
.sia-provider-badge {
    display: inline-block;
    font-size: 10px;
    background: #e2e8f0;
    color: #64748b;
    border-radius: 20px;
    padding: 1px 8px;
    margin-top: 2px;
}
.sia-status-dot {
    width: 10px; height: 10px;
    border-radius: 50%;
    background: #d1d5db;
    margin-left: auto;
    flex-shrink: 0;
    transition: background .3s;
}
.sia-status-dot.ok  { background: #22c55e; box-shadow: 0 0 0 3px rgba(34,197,94,.2); }
.sia-status-dot.err { background: #ef4444; box-shadow: 0 0 0 3px rgba(239,68,68,.2); }

.sia-card-body { padding: 18px; }
.sia-field { margin-bottom: 16px; }
.sia-field label { display: block; font-size: 12px; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: .04em; margin-bottom: 6px; }
.sia-key-row { display: flex; gap: 6px; }
.sia-key-input { flex: 1; font-family: monospace; font-size: 12px; }
.sia-toggle-key { flex-shrink: 0; padding: 0 8px; }
.sia-select { width: 100%; }
.sia-actions { display: flex; align-items: center; gap: 10px; margin-top: 6px; }
.sia-test-result { font-size: 12px; font-weight: 500; }
.sia-test-result.ok  { color: #16a34a; }
.sia-test-result.err { color: #dc2626; }

.sia-general-card {
    max-width: 900px;
    background: #fff;
    border: 1px solid var(--sia-border);
    border-radius: 10px;
    padding: 20px 22px;
    margin-bottom: 20px;
}
.sia-general-card h2 { margin-top: 0; font-size: 14px; color: #1e293b; border-bottom: 1px solid var(--sia-border); padding-bottom: 10px; margin-bottom: 16px; }
.sia-field-inline { margin-bottom: 18px; }
.sia-field-inline > label { display: block; font-size: 12px; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: .04em; margin-bottom: 8px; }
.sia-radio-group { display: flex; gap: 20px; }
.sia-radio-label { display: flex; align-items: center; gap: 6px; font-size: 13px; cursor: pointer; }
.sia-range-wrap { margin-bottom: 4px; }
.sia-range { width: 320px; max-width: 100%; accent-color: #6d3fc0; }
.sia-range-labels { display: flex; justify-content: space-between; width: 320px; max-width: 100%; font-size: 11px; color: #94a3b8; }
.sia-temp-val { font-weight: 700; color: #6d3fc0; margin-left: 6px; font-style: normal; }

.sia-submit-bar { max-width: 900px; padding-top: 6px; }
.sia-save-btn { font-size: 13px !important; padding: 6px 20px !important; }

@keyframes sia-spin { to { transform: rotate(360deg); } }
.sia-spinning { animation: sia-spin .8s linear infinite; }

@media (max-width: 780px) {
    .sia-grid { grid-template-columns: 1fr; }
}
</style>

<script>
(function($){
    var ajaxUrl  = (typeof schiloBuilder !== 'undefined') ? schiloBuilder.ajaxUrl : ajaxurl;
    var iaNonce  = (typeof schiloBuilder !== 'undefined') ? schiloBuilder.iaNonce : '';

    /* ── Toggle visibilité clé ── */
    $('.sia-toggle-key').on('click', function(){
        var targetId = $(this).data('target');
        var input = $('#' + targetId);
        var isPass = input.attr('type') === 'password';
        input.attr('type', isPass ? 'text' : 'password');
        $(this).find('.dashicons')
               .toggleClass('dashicons-visibility', !isPass)
               .toggleClass('dashicons-hidden',     isPass);
    });

    /* ── Slider température ── */
    $('#sia_temperature').on('input', function(){
        $('.sia-temp-val').text($(this).val());
    });

    /* ── Marquer champ modifié (pour ne pas écraser la clé masquée) ── */
    $('.sia-key-input').on('input', function(){
        $(this).attr('data-has-value', '0').attr('data-changed', '1');
    });

    /* ── Test de connexion ── */
    $('.sia-test-btn').on('click', function(){
        var btn      = $(this);
        var provider = btn.data('provider');
        var keyField = btn.data('key-field');
        var modField = btn.data('model-field');
        var keyInput = $('#' + keyField);
        var keyVal   = keyInput.val();

        /* Si la clé est masquée (non modifiée), envoyer un marqueur spécial */
        var keyToSend = keyInput.attr('data-changed') === '1' ? keyVal : '__USE_SAVED__';

        var spinner = btn.find('.sia-spin-icon');
        var result  = $('#sia-result-' + provider);
        var dot     = $('#sia-status-' + provider);

        btn.prop('disabled', true);
        spinner.show().addClass('sia-spinning');
        result.text('Test en cours...').removeClass('ok err');
        dot.removeClass('ok err');

        /* Lu au moment du clic (pas a l'analyse du script) : schiloBuilder est
           localise sur un script charge en pied de page, donc pas encore
           disponible si on le lit trop tot. */
        var liveNonce = (typeof schiloBuilder !== 'undefined') ? schiloBuilder.iaNonce : iaNonce;

        $.post(ajaxUrl, {
            action:   'schilo_test_ia',
            nonce:    liveNonce,
            provider: provider,
            api_key:  keyToSend,
            model:    $('#' + modField).val()
        }, function(resp){
            if (resp && resp.success) {
                result.text('✓ ' + (resp.data.message || 'Connexion OK')).addClass('ok');
                dot.addClass('ok');
            } else {
                var msg = (resp && resp.data && resp.data.message) ? resp.data.message : 'Erreur inconnue';
                result.text('✗ ' + msg).addClass('err');
                dot.addClass('err');
            }
        }).fail(function(){
            result.text('✗ Erreur réseau').addClass('err');
            dot.addClass('err');
        }).always(function(){
            btn.prop('disabled', false);
            spinner.hide().removeClass('sia-spinning');
        });
    });

})(jQuery);
</script>