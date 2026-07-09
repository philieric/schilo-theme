<?php
/**
 * Vue : Réglages de traduction (Google / Microsoft Translator / Google Cloud Translation)
 * Variables : $config, $saved
 */
if ( ! defined( 'ABSPATH' ) ) exit;

$active_provider  = $config['active_provider'] ?? 'google';
$selector_enabled = ! empty( $config['selector_enabled'] );
$ms_key          = $config['microsoft']['api_key'] ?? '';
$ms_region       = $config['microsoft']['region']  ?? '';
$ms_enabled      = ! empty( $config['microsoft']['enabled'] );
$gc_key          = $config['google_cloud']['api_key'] ?? '';
$gc_enabled      = ! empty( $config['google_cloud']['enabled'] );
?>

<div class="wrap sia-wrap">
    <h1>
        <span class="dashicons dashicons-translation" style="font-size:24px"></span>
        Traduction
    </h1>

    <?php if ( $saved ) : ?>
        <div class="notice notice-success inline" style="margin:16px 0"><p><strong>Configuration enregistrée.</strong></p></div>
    <?php endif; ?>

    <form method="post">
        <?php wp_nonce_field( 'schilo_save_translator_config', 'schilo_translator_nonce' ); ?>

        <div class="sia-selector-toggle">
            <div>
                <strong>Sélecteur de langue sur le site</strong>
                <p class="description" style="margin:2px 0 0">
                    Désactivé, le bouton de langue n'apparaît plus du tout sur le site public
                    (utile tant qu'aucun fournisseur n'est prêt).
                </p>
            </div>
            <input type="hidden" name="st_selector_enabled" id="st_selector_enabled_input" value="<?php echo $selector_enabled ? '1' : '0'; ?>">
            <button type="submit" class="button <?php echo $selector_enabled ? 'button-primary' : ''; ?>" id="sia-toggle-selector-btn">
                <?php echo $selector_enabled ? '● Activé — cliquer pour désactiver' : '○ Désactivé — cliquer pour activer'; ?>
            </button>
        </div>

        <table class="form-table" style="max-width:900px;margin-bottom:20px">
            <tr>
                <th style="width:200px">Fournisseur actif</th>
                <td>
                    <label style="margin-right:20px">
                        <input type="radio" name="st_active_provider" value="google" <?php checked( $active_provider, 'google' ); ?>> Google (redirection)
                    </label>
                    <label style="margin-right:20px">
                        <input type="radio" name="st_active_provider" value="microsoft" <?php checked( $active_provider, 'microsoft' ); ?>> Microsoft Translator (sur place)
                    </label>
                    <label>
                        <input type="radio" name="st_active_provider" value="google_cloud" <?php checked( $active_provider, 'google_cloud' ); ?>> Google Cloud Translation (sur place)
                    </label>
                </td>
            </tr>
        </table>

        <div class="sia-grid">

            <!-- ══ GOOGLE (redirection) ══ -->
            <div class="sia-card">
                <div class="sia-card-header" style="border-left:4px solid #4285F4">
                    <strong>Google Translate (redirection)</strong>
                </div>
                <div class="sia-card-body">
                    <p class="description">
                        Redirige vers le proxy public <code>translate.google.com</code>. Gratuit,
                        sans clé, fonctionne immédiatement — mais l'URL affichée devient
                        temporairement un sous-domaine <code>*.translate.goog</code> pendant la
                        consultation traduite (on quitte schilo.org).
                    </p>
                </div>
            </div>

            <!-- ══ MICROSOFT ══ -->
            <div class="sia-card" id="sia-card-microsoft">
                <div class="sia-card-header" style="border-left:4px solid #00a4ef">
                    <strong>Microsoft Translator (Azure)</strong>
                    <span class="sia-dot" id="sia-dot-microsoft"></span>
                </div>
                <div class="sia-card-body">
                    <div class="sia-field">
                        <label>Clé API (Azure Translator)</label>
                        <div class="sia-key-row">
                            <input type="password" id="st_microsoft_key" name="st_microsoft_key"
                                   class="regular-text sia-key-input"
                                   placeholder="Clé Azure Translator"
                                   autocomplete="new-password"
                                   value="<?php echo esc_attr( $ms_key ? schilo_mask_key( $ms_key ) : '' ); ?>"
                                   data-changed="0">
                            <button type="button" class="button sia-eye" data-target="st_microsoft_key"><span class="dashicons dashicons-visibility"></span></button>
                        </div>
                        <p class="description">Créée dans le portail Azure (ressource "Translator").</p>
                    </div>
                    <div class="sia-field">
                        <label>Région Azure</label>
                        <input type="text" id="st_microsoft_region" name="st_microsoft_region" class="regular-text"
                               placeholder="ex: westeurope" value="<?php echo esc_attr( $ms_region ); ?>">
                    </div>
                    <div class="sia-field">
                        <label>
                            <input type="checkbox" name="st_microsoft_enabled" value="1" <?php checked( $ms_enabled ); ?>>
                            Activer Microsoft Translator
                        </label>
                    </div>
                    <div class="sia-test-row">
                        <button type="button" class="button sia-test" data-provider="microsoft" data-kf="st_microsoft_key" data-rf="st_microsoft_region">
                            <span class="dashicons dashicons-update sia-spin" style="display:none"></span>
                            Tester la connexion
                        </button>
                        <span class="sia-result" id="sia-res-microsoft"></span>
                    </div>
                    <p class="description" style="margin-top:10px">
                        Ce test vérifie uniquement la clé et la région — le quota réel
                        (2 000 000 caractères/mois en offre gratuite) reste visible dans le
                        <a href="https://portal.azure.com/" target="_blank" rel="noopener">portail Azure</a>.
                    </p>
                </div>
            </div>

            <!-- ══ GOOGLE CLOUD TRANSLATION ══ -->
            <div class="sia-card" id="sia-card-google_cloud">
                <div class="sia-card-header" style="border-left:4px solid #34a853">
                    <strong>Google Cloud Translation</strong>
                    <span class="sia-dot" id="sia-dot-google_cloud"></span>
                </div>
                <div class="sia-card-body">
                    <div class="sia-field">
                        <label>Clé API (Google Cloud)</label>
                        <div class="sia-key-row">
                            <input type="password" id="st_google_cloud_key" name="st_google_cloud_key"
                                   class="regular-text sia-key-input"
                                   placeholder="Clé API Google Cloud"
                                   autocomplete="new-password"
                                   value="<?php echo esc_attr( $gc_key ? schilo_mask_key( $gc_key ) : '' ); ?>"
                                   data-changed="0">
                            <button type="button" class="button sia-eye" data-target="st_google_cloud_key"><span class="dashicons dashicons-visibility"></span></button>
                        </div>
                        <p class="description">
                            Créée dans la <a href="https://console.cloud.google.com/" target="_blank" rel="noopener">console Google Cloud</a>
                            (API "Cloud Translation" activée, clé restreinte à cette API). Pas de région à renseigner.
                        </p>
                    </div>
                    <div class="sia-field">
                        <label>
                            <input type="checkbox" name="st_google_cloud_enabled" value="1" <?php checked( $gc_enabled ); ?>>
                            Activer Google Cloud Translation
                        </label>
                    </div>
                    <div class="sia-test-row">
                        <button type="button" class="button sia-test" data-provider="google_cloud" data-kf="st_google_cloud_key" data-rf="">
                            <span class="dashicons dashicons-update sia-spin" style="display:none"></span>
                            Tester la connexion
                        </button>
                        <span class="sia-result" id="sia-res-google_cloud"></span>
                    </div>
                    <p class="description" style="margin-top:10px">
                        Facturation activée requise côté Google Cloud (offre gratuite mensuelle
                        disponible) — le quota réel reste visible dans la console.
                    </p>
                </div>
            </div>

        </div><!-- .sia-grid -->

        <p class="submit">
            <button type="submit" class="button button-primary">Enregistrer</button>
        </p>
    </form>
</div>

<style>
.sia-wrap .sia-grid { display:grid; grid-template-columns:1fr 1fr 1fr; gap:16px; margin:16px 0; }
.sia-wrap .sia-card { background:#fff; border:1px solid #e2e8f0; border-radius:10px; overflow:hidden; box-shadow:0 1px 4px rgba(0,0,0,.06); }
.sia-wrap .sia-card-header { display:flex; align-items:center; gap:10px; padding:12px 16px; background:#f8fafc; border-bottom:1px solid #e2e8f0; }
.sia-wrap .sia-dot { width:10px; height:10px; border-radius:50%; background:#d1d5db; margin-left:auto; flex-shrink:0; transition:background .3s; }
.sia-wrap .sia-dot.ok  { background:#22c55e; box-shadow:0 0 0 3px rgba(34,197,94,.2); }
.sia-wrap .sia-dot.err { background:#ef4444; box-shadow:0 0 0 3px rgba(239,68,68,.2); }
.sia-wrap .sia-card-body { padding:16px; }
.sia-wrap .sia-field { margin-bottom:14px; }
.sia-wrap .sia-field > label { display:block; font-size:11px; font-weight:700; color:#64748b; text-transform:uppercase; letter-spacing:.05em; margin-bottom:5px; }
.sia-wrap .sia-key-row { display:flex; gap:5px; }
.sia-wrap .sia-key-input { flex:1; font-family:monospace; font-size:12px; }
.sia-wrap .sia-eye { flex-shrink:0; padding:0 8px!important; }
.sia-wrap .sia-test-row { display:flex; align-items:center; gap:10px; margin-top:4px; }
.sia-wrap .sia-result { font-size:12px; font-weight:600; }
.sia-wrap .sia-result.ok  { color:#16a34a; }
.sia-wrap .sia-result.err { color:#dc2626; }
@keyframes sia-spin { to { transform:rotate(360deg); } }
.sia-spinning { animation:sia-spin .8s linear infinite; }
@media (max-width:1000px) { .sia-wrap .sia-grid { grid-template-columns:1fr; } }
.sia-selector-toggle {
    display:flex; align-items:center; justify-content:space-between; gap:20px;
    background:#fff; border:1px solid #e2e8f0; border-radius:10px; padding:16px 20px;
    margin:16px 0 24px; max-width:900px;
}
</style>

<script>
(function($){
    var ajaxUrl = ajaxurl;
    var testNonce = '<?php echo esc_js( wp_create_nonce( 'schilo_test_translator' ) ); ?>';

    $(document).on('click', '.sia-eye', function(){
        var id  = $(this).data('target');
        var inp = $('#' + id);
        var isPass = inp.attr('type') === 'password';
        inp.attr('type', isPass ? 'text' : 'password');
        $(this).find('.dashicons').toggleClass('dashicons-visibility', !isPass).toggleClass('dashicons-hidden', isPass);
    });

    $(document).on('input', '.sia-key-input', function(){
        $(this).attr('data-changed', '1');
    });

    /* Bouton bascule : inverse la valeur cachée puis laisse le formulaire
       se soumettre normalement (active/desactive en un clic) */
    $('#sia-toggle-selector-btn').on('click', function(){
        var input = $('#st_selector_enabled_input');
        input.val(input.val() === '1' ? '0' : '1');
    });

    $(document).on('click', '.sia-test', function(){
        var btn      = $(this);
        var provider = btn.data('provider');
        var keyInp   = $('#' + btn.data('kf'));
        var keyVal   = keyInp.attr('data-changed') === '1' ? keyInp.val() : '__USE_SAVED__';
        var region   = btn.data('rf') ? $('#' + btn.data('rf')).val() : '';
        var spinner  = btn.find('.sia-spin');
        var result   = $('#sia-res-' + provider);
        var dot      = $('#sia-dot-' + provider);

        btn.prop('disabled', true);
        spinner.show().addClass('sia-spinning');
        result.text('Test en cours...').removeClass('ok err');
        dot.removeClass('ok err');

        $.post(ajaxUrl, {
            action:   'schilo_test_translator',
            nonce:    testNonce,
            provider: provider,
            api_key:  keyVal,
            region:   region
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
