<?php
/**
 * Outil : Configuration Intelligence Artificielle
 * Variables : $ia_config, $ia_saved, $ia_save_error
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$ia_config  = get_option( 'schilo_ia_config', array() );
$ia_saved   = false;
$ia_error   = '';

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset( $_POST['schilo_ia_nonce'] )
    && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['schilo_ia_nonce'] ) ), 'schilo_save_ia_config' )
    && current_user_can( 'manage_options' )
    && isset( $_POST['schilo_tool_action'] )
    && $_POST['schilo_tool_action'] === 'save_ia_config'
) {
    $prev = $ia_config;

    $claude_raw = isset( $_POST['sia_claude_key'] ) ? sanitize_text_field( wp_unslash( $_POST['sia_claude_key'] ) ) : '';
    $claude_key = ( $claude_raw !== '' && strpos( $claude_raw, '*' ) === false )
        ? $claude_raw
        : ( $prev['claude']['api_key'] ?? '' );

    $openai_raw = isset( $_POST['sia_openai_key'] ) ? sanitize_text_field( wp_unslash( $_POST['sia_openai_key'] ) ) : '';
    $openai_key = ( $openai_raw !== '' && strpos( $openai_raw, '*' ) === false )
        ? $openai_raw
        : ( $prev['openai']['api_key'] ?? '' );

    $ok_claude  = array( 'claude-sonnet-4-6', 'claude-haiku-4-5-20251001', 'claude-opus-4-8' );
    $ok_openai  = array( 'gpt-4o', 'gpt-4o-mini', 'gpt-4-turbo', 'gpt-3.5-turbo' );
    $ok_provs   = array( 'claude', 'openai' );

    $c_model  = in_array( $_POST['sia_claude_model'] ?? '', $ok_claude, true ) ? $_POST['sia_claude_model'] : 'claude-sonnet-4-6';
    $o_model  = in_array( $_POST['sia_openai_model'] ?? '', $ok_openai, true ) ? $_POST['sia_openai_model'] : 'gpt-4o';
    $def_prov = in_array( $_POST['sia_default_provider'] ?? '', $ok_provs, true ) ? $_POST['sia_default_provider'] : 'claude';
    $temp     = min( 1.0, max( 0.0, (float) ( $_POST['sia_temperature'] ?? 0.7 ) ) );

    $ia_config = array(
        'claude' => array( 'api_key' => $claude_key, 'model' => $c_model ),
        'openai' => array( 'api_key' => $openai_key, 'model' => $o_model ),
        'default_provider' => $def_prov,
        'temperature'      => $temp,
    );
    update_option( 'schilo_ia_config', $ia_config, false );
    $ia_saved = true;
}

$claude_key   = $ia_config['claude']['api_key'] ?? '';
$claude_model = $ia_config['claude']['model']   ?? 'claude-sonnet-4-6';
$openai_key   = $ia_config['openai']['api_key'] ?? '';
$openai_model = $ia_config['openai']['model']   ?? 'gpt-4o';
$def_prov     = $ia_config['default_provider']  ?? 'claude';
$temperature  = $ia_config['temperature']       ?? 0.7;

$claude_models = array(
    'claude-sonnet-4-6'         => 'Claude Sonnet 4.6 (recommandé)',
    'claude-haiku-4-5-20251001' => 'Claude Haiku 4.5 (rapide / économique)',
    'claude-opus-4-8'           => 'Claude Opus 4.8 (le plus puissant)',
);
$openai_models = array(
    'gpt-4o'        => 'GPT-4o (recommandé)',
    'gpt-4o-mini'   => 'GPT-4o Mini (rapide / économique)',
    'gpt-4-turbo'   => 'GPT-4 Turbo',
    'gpt-3.5-turbo' => 'GPT-3.5 Turbo (économique)',
);
?>

<div class="schilo-tool-card sia-wrap">

    <?php if ( $ia_saved ) : ?>
        <div class="notice notice-success inline schilo-keep" style="margin:0 0 16px"><p><strong>Configuration enregistrée.</strong></p></div>
    <?php endif; ?>

    <h2 style="margin-top:0;display:flex;align-items:center;gap:8px">
        <span class="dashicons dashicons-superhero" style="color:#6d3fc0;font-size:22px"></span>
        Configuration Intelligence Artificielle
    </h2>
    <p style="color:#6b7280;margin-top:0">
        Connectez vos APIs IA. Les clés sont stockées en base de données WordPress, uniquement accessibles depuis l'administration.
    </p>

    <form method="post">
        <?php wp_nonce_field( 'schilo_save_ia_config', 'schilo_ia_nonce' ); ?>
        <input type="hidden" name="schilo_tool_action" value="save_ia_config">

        <div class="sia-grid">

            <!-- ══ CLAUDE ══ -->
            <div class="sia-card" id="sia-card-claude">
                <div class="sia-card-header sia-h-claude">
                    <svg width="20" height="20" viewBox="0 0 48 48"><circle cx="24" cy="24" r="24" fill="#D97706"/><text x="24" y="31" text-anchor="middle" font-size="20" fill="white" font-family="Georgia,serif">C</text></svg>
                    <div style="flex:1">
                        <strong>Claude — Anthropic</strong>
                    </div>
                    <span class="sia-dot" id="sia-dot-claude"></span>
                </div>
                <div class="sia-card-body">
                    <div class="sia-field">
                        <label>Clé API</label>
                        <div class="sia-key-row">
                            <input type="password" id="sia_claude_key" name="sia_claude_key"
                                   class="regular-text sia-key-input"
                                   placeholder="sk-ant-api03-..."
                                   autocomplete="new-password"
                                   value="<?php echo esc_attr( $claude_key ? schilo_mask_key( $claude_key ) : '' ); ?>"
                                   data-changed="0">
                            <button type="button" class="button sia-eye" data-target="sia_claude_key"><span class="dashicons dashicons-visibility"></span></button>
                        </div>
                        <p class="description">Clé disponible sur <a href="https://console.anthropic.com/" target="_blank" rel="noopener">console.anthropic.com</a></p>
                    </div>
                    <div class="sia-field">
                        <label>Modèle</label>
                        <select name="sia_claude_model" id="sia_claude_model" class="sia-select">
                            <?php foreach ( $claude_models as $v => $l ) : ?>
                                <option value="<?php echo esc_attr($v); ?>" <?php selected($claude_model,$v); ?>><?php echo esc_html($l); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="sia-test-row">
                        <button type="button" class="button sia-test" data-provider="claude" data-kf="sia_claude_key" data-mf="sia_claude_model">
                            <span class="dashicons dashicons-update sia-spin" style="display:none"></span>
                            Tester la connexion
                        </button>
                        <span class="sia-result" id="sia-res-claude"></span>
                    </div>
                </div>
            </div>

            <!-- ══ OPENAI ══ -->
            <div class="sia-card" id="sia-card-openai">
                <div class="sia-card-header sia-h-openai">
                    <svg width="20" height="20" viewBox="0 0 48 48"><circle cx="24" cy="24" r="24" fill="#10a37f"/><text x="24" y="31" text-anchor="middle" font-size="20" fill="white" font-family="Arial,sans-serif">G</text></svg>
                    <div style="flex:1">
                        <strong>ChatGPT — OpenAI</strong>
                    </div>
                    <span class="sia-dot" id="sia-dot-openai"></span>
                </div>
                <div class="sia-card-body">
                    <div class="sia-field">
                        <label>Clé API</label>
                        <div class="sia-key-row">
                            <input type="password" id="sia_openai_key" name="sia_openai_key"
                                   class="regular-text sia-key-input"
                                   placeholder="sk-..."
                                   autocomplete="new-password"
                                   value="<?php echo esc_attr( $openai_key ? schilo_mask_key( $openai_key ) : '' ); ?>"
                                   data-changed="0">
                            <button type="button" class="button sia-eye" data-target="sia_openai_key"><span class="dashicons dashicons-visibility"></span></button>
                        </div>
                        <p class="description">Clé disponible sur <a href="https://platform.openai.com/api-keys" target="_blank" rel="noopener">platform.openai.com</a></p>
                    </div>
                    <div class="sia-field">
                        <label>Modèle</label>
                        <select name="sia_openai_model" id="sia_openai_model" class="sia-select">
                            <?php foreach ( $openai_models as $v => $l ) : ?>
                                <option value="<?php echo esc_attr($v); ?>" <?php selected($openai_model,$v); ?>><?php echo esc_html($l); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="sia-test-row">
                        <button type="button" class="button sia-test" data-provider="openai" data-kf="sia_openai_key" data-mf="sia_openai_model">
                            <span class="dashicons dashicons-update sia-spin" style="display:none"></span>
                            Tester la connexion
                        </button>
                        <span class="sia-result" id="sia-res-openai"></span>
                    </div>
                </div>
            </div>

        </div><!-- .sia-grid -->

        <!-- ══ PARAMÈTRES GÉNÉRAUX ══ -->
        <div class="sia-general">
            <h3>Paramètres généraux</h3>
            <table class="form-table" style="max-width:600px">
                <tr>
                    <th style="width:200px">Provider par défaut</th>
                    <td>
                        <label style="margin-right:20px">
                            <input type="radio" name="sia_default_provider" value="claude" <?php checked($def_prov,'claude'); ?>> Claude (Anthropic)
                        </label>
                        <label>
                            <input type="radio" name="sia_default_provider" value="openai" <?php checked($def_prov,'openai'); ?>> ChatGPT (OpenAI)
                        </label>
                    </td>
                </tr>
                <tr>
                    <th>Température <strong id="sia-temp-val" style="color:#6d3fc0"><?php echo esc_html( number_format($temperature,1) ); ?></strong></th>
                    <td>
                        <input type="range" name="sia_temperature" id="sia_temperature"
                               min="0" max="1" step="0.1" value="<?php echo esc_attr($temperature); ?>"
                               style="width:260px;accent-color:#6d3fc0">
                        <div style="display:flex;justify-content:space-between;width:260px;font-size:11px;color:#94a3b8">
                            <span>Précis (0)</span><span>Créatif (1)</span>
                        </div>
                        <p class="description">Valeur recommandée&nbsp;: 0.7 pour les articles.</p>
                    </td>
                </tr>
            </table>
        </div>

        <p class="submit">
            <button type="submit" class="button button-primary">
                <span class="dashicons dashicons-saved" style="vertical-align:middle;margin-right:4px"></span>
                Enregistrer
            </button>
        </p>

    </form>
</div>

<style>
.sia-wrap { max-width:860px; }
.sia-grid { display:grid; grid-template-columns:1fr 1fr; gap:16px; margin:16px 0; }
.sia-card { background:#fff; border:1px solid #e2e8f0; border-radius:10px; overflow:hidden; box-shadow:0 1px 4px rgba(0,0,0,.06); }
.sia-card-header { display:flex; align-items:center; gap:10px; padding:12px 16px; background:#f8fafc; border-bottom:1px solid #e2e8f0; }
.sia-h-claude { border-left:4px solid #D97706; }
.sia-h-openai { border-left:4px solid #10a37f; }
.sia-card-header strong { font-size:13px; }
.sia-dot { width:10px; height:10px; border-radius:50%; background:#d1d5db; margin-left:auto; flex-shrink:0; transition:background .3s; }
.sia-dot.ok  { background:#22c55e; box-shadow:0 0 0 3px rgba(34,197,94,.2); }
.sia-dot.err { background:#ef4444; box-shadow:0 0 0 3px rgba(239,68,68,.2); }
.sia-card-body { padding:16px; }
.sia-field { margin-bottom:14px; }
.sia-field > label { display:block; font-size:11px; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:.05em; margin-bottom:5px; }
.sia-key-row { display:flex; gap:5px; }
.sia-key-input { flex:1; font-family:monospace; font-size:12px; }
.sia-eye { flex-shrink:0; padding:0 8px!important; }
.sia-select { width:100%; }
.sia-test-row { display:flex; align-items:center; gap:10px; margin-top:4px; }
.sia-result { font-size:12px; font-weight:600; }
.sia-result.ok  { color:#16a34a; }
.sia-result.err { color:#dc2626; }
.sia-general { margin-top:8px; padding-top:16px; border-top:1px solid #e2e8f0; }
.sia-general h3 { font-size:13px; margin-top:0; color:#1e293b; }
@keyframes sia-spin { to { transform:rotate(360deg); } }
.sia-spinning { animation:sia-spin .8s linear infinite; }
@media (max-width:700px) { .sia-grid { grid-template-columns:1fr; } }
</style>

<script>
(function($){
    var ajaxUrl = (typeof schiloBuilder !== 'undefined') ? schiloBuilder.ajaxUrl : ajaxurl;
    var iaNonce = (typeof schiloBuilder !== 'undefined' && schiloBuilder.iaNonce) ? schiloBuilder.iaNonce : '';

    /* Afficher/masquer la clé */
    $(document).on('click', '.sia-eye', function(){
        var id = $(this).data('target');
        var inp = $('#' + id);
        var isPass = inp.attr('type') === 'password';
        inp.attr('type', isPass ? 'text' : 'password');
        $(this).find('.dashicons').toggleClass('dashicons-visibility', !isPass).toggleClass('dashicons-hidden', isPass);
    });

    /* Marquer clé modifiée */
    $(document).on('input', '.sia-key-input', function(){
        $(this).attr('data-changed', '1');
    });

    /* Slider température */
    $(document).on('input', '#sia_temperature', function(){
        $('#sia-temp-val').text(parseFloat($(this).val()).toFixed(1));
    });

    /* Test connexion */
    $(document).on('click', '.sia-test', function(){
        var btn      = $(this);
        var provider = btn.data('provider');
        var keyInp   = $('#' + btn.data('kf'));
        var keyVal   = keyInp.attr('data-changed') === '1' ? keyInp.val() : '__USE_SAVED__';
        var spinner  = btn.find('.sia-spin');
        var result   = $('#sia-res-' + provider);
        var dot      = $('#sia-dot-' + provider);

        btn.prop('disabled', true);
        spinner.show().addClass('sia-spinning');
        result.text('Test en cours...').removeClass('ok err');
        dot.removeClass('ok err');

        /* Lu au moment du clic : schiloBuilder est localise sur un script
           charge en pied de page, pas encore disponible si lu trop tot. */
        var liveNonce = (typeof schiloBuilder !== 'undefined') ? schiloBuilder.iaNonce : iaNonce;

        $.post(ajaxUrl, {
            action:   'schilo_test_ia',
            nonce:    liveNonce,
            provider: provider,
            api_key:  keyVal,
            model:    $('#' + btn.data('mf')).val()
        }, function(r){
            if (r && r.success) {
                result.text('✓ ' + (r.data.message || 'Connexion OK')).addClass('ok');
                dot.addClass('ok');
            } else {
                result.text('✗ ' + ((r && r.data && r.data.message) ? r.data.message : 'Erreur')).addClass('err');
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