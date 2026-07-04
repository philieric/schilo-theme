<?php
if (!defined('ABSPATH')) exit;

$ia_config   = get_option('schilo_ia_config', []);
$saved       = false;
$ia_providers = ['claude' => 'Claude Anthropic', 'openai' => 'ChatGPT OpenAI'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sia_config_nonce'])) {
    if (wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['sia_config_nonce'])), 'schilo_indexation_config')
        && current_user_can('manage_options')) {

        $provider = sanitize_key($_POST['sia_default_provider'] ?? 'claude');
        update_option('schilo_indexation_default_provider', in_array($provider, array_keys($ia_providers)) ? $provider : 'claude', false);

        $validation_mode = sanitize_key($_POST['sia_validation_mode'] ?? 'manuel');
        update_option('schilo_indexation_validation_mode', in_array($validation_mode, ['manuel', 'auto'], true) ? $validation_mode : 'manuel', false);

        $saved = true;
    }
}

$default_provider = get_option('schilo_indexation_default_provider', 'claude');
$validation_mode  = get_option('schilo_indexation_validation_mode', 'manuel');
$back_url = admin_url('admin.php?page=schilo-builder-indexation');
?>
<div class="wrap schilo-builder-settings">
    <h1 class="sia-page-title">
        <span class="dashicons dashicons-admin-settings" style="color:#6d3fc0;font-size:26px;height:26px;width:26px;vertical-align:middle;margin-right:8px;"></span>
        Configuration de l'indexation
    </h1>

    <!-- Onglets -->
    <nav class="sia-tabs">
        <a href="<?php echo esc_url($back_url); ?>" class="sia-tab">Liste</a>
        <a href="<?php echo esc_url(admin_url('admin.php?page=schilo-builder-indexation&tab=config')); ?>" class="sia-tab sia-tab-active">Configuration</a>
    </nav>

    <?php if ($saved) : ?>
    <div class="notice notice-success is-dismissible"><p>Configuration enregistree.</p></div>
    <?php endif; ?>

    <form method="post">
        <?php wp_nonce_field('schilo_indexation_config', 'sia_config_nonce'); ?>

        <!-- Provider IA par defaut -->
        <div class="sia-val-bloc" style="display:block;">
            <div class="sia-val-bloc-title">Provider IA par defaut</div>
            <p>Choisissez le provider utilise par defaut pour l'indexation automatique.</p>
            <?php foreach ($ia_providers as $k => $label) : ?>
            <label style="display:flex;align-items:center;gap:8px;margin-bottom:8px;font-size:14px;">
                <input type="radio" name="sia_default_provider" value="<?php echo esc_attr($k); ?>" <?php checked($default_provider, $k); ?>>
                <?php echo esc_html($label); ?>
                <?php if (empty($ia_config[$k]['api_key'])) : ?>
                <span style="color:#dc2626;font-size:12px;">(cle non configuree)</span>
                <?php else : ?>
                <span style="color:#059669;font-size:12px;">(cle configuree)</span>
                <?php endif; ?>
            </label>
            <?php endforeach; ?>
            <p>
                <a href="<?php echo esc_url(admin_url('admin.php?page=schilo-builder-ia')); ?>" class="button">
                    Configurer les cles API IA
                </a>
            </p>
        </div>

        <!-- Mode de validation -->
        <div class="sia-val-bloc" style="display:block;margin-top:16px;">
            <div class="sia-val-bloc-title">Mode de validation de l'indexation IA</div>
            <label style="display:flex;align-items:flex-start;gap:8px;margin-bottom:10px;font-size:14px;">
                <input type="radio" name="sia_validation_mode" value="manuel" <?php checked($validation_mode, 'manuel'); ?> style="margin-top:3px;">
                <span>
                    <strong>Manuelle (recommande)</strong><br>
                    <span style="color:#64748b;font-size:13px;">Les propositions de l'IA sont enregistrees en statut « en attente ». Rien ne passe en « valide » sans relecture et validation humaine.</span>
                </span>
            </label>
            <label style="display:flex;align-items:flex-start;gap:8px;font-size:14px;">
                <input type="radio" name="sia_validation_mode" value="auto" <?php checked($validation_mode, 'auto'); ?> style="margin-top:3px;">
                <span>
                    <strong>Automatique</strong><br>
                    <span style="color:#dc2626;font-size:13px;">Les propositions de l'IA sont enregistrees directement en statut « valide », sans relecture humaine. A n'utiliser que si vous faites confiance au contenu genere pour les prefixes concernes.</span>
                </span>
            </label>
        </div>

        <!-- Infos table SQL -->
        <div class="sia-val-bloc" style="display:block;margin-top:16px;">
            <div class="sia-val-bloc-title">Etat de la table SQL</div>
            <?php
            global $wpdb;
            $table = $wpdb->prefix . 'schilo_indexation';
            $exists = $wpdb->get_var("SHOW TABLES LIKE '$table'") === $table;
            $count  = $exists ? (int) $wpdb->get_var("SELECT COUNT(*) FROM $table") : 0;
            ?>
            <?php if ($exists) : ?>
            <p style="color:#059669;font-weight:600;">
                <span class="dashicons dashicons-yes-alt" style="vertical-align:middle;"></span>
                Table <code><?php echo esc_html($table); ?></code> presente — <?php echo $count; ?> entree(s).
            </p>
            <?php else : ?>
            <p style="color:#dc2626;font-weight:600;">
                <span class="dashicons dashicons-dismiss" style="vertical-align:middle;"></span>
                Table absente. Elle sera creee automatiquement au prochain chargement de la page Indexation.
            </p>
            <?php endif; ?>
        </div>

        <p><button type="submit" class="button button-primary">Enregistrer la configuration</button></p>
    </form>
</div>