<?php
if (!defined('ABSPATH')) exit;

use Schilo\Builder\Service\ClassementService;

$service = new ClassementService();
$saved   = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['scl_config_nonce'])) {
    if (wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['scl_config_nonce'])), 'schilo_classement_config')
        && current_user_can('manage_options')) {

        $validation_mode = sanitize_key($_POST['scl_validation_mode'] ?? 'manuel');
        update_option('schilo_classement_validation_mode', in_array($validation_mode, ['manuel', 'auto'], true) ? $validation_mode : 'manuel', false);

        $words_min = max(20, absint($_POST['scl_desc_words_min'] ?? 150));
        $words_max = max($words_min, absint($_POST['scl_desc_words_max'] ?? 250));
        update_option('schilo_classement_desc_words', ['min' => $words_min, 'max' => $words_max], false);

        $saved = true;
    }
}

$validation_mode = get_option('schilo_classement_validation_mode', 'manuel');
$word_range = $service->getDescriptionWordRange();
$back_url = admin_url('admin.php?page=schilo-builder-classement');
?>
<div class="wrap schilo-builder-settings">
    <h1 class="scl-page-title">
        <span class="dashicons dashicons-admin-settings" style="color:#2872d4;font-size:24px;height:24px;width:24px;vertical-align:middle;margin-right:8px;"></span>
        Configuration du classement
    </h1>

    <nav class="scl-tabs">
        <a href="<?php echo esc_url($back_url); ?>" class="scl-tab">Classement</a>
        <a href="<?php echo esc_url(admin_url('admin.php?page=schilo-builder-classement&tab=termes')); ?>" class="scl-tab">Termes</a>
        <a href="<?php echo esc_url(admin_url('admin.php?page=schilo-builder-classement&tab=config')); ?>" class="scl-tab scl-tab-active">Configuration</a>
    </nav>

    <?php if ($saved) : ?>
    <div class="notice notice-success is-dismissible"><p>Configuration enregistrée.</p></div>
    <?php endif; ?>

    <form method="post">
        <?php wp_nonce_field('schilo_classement_config', 'scl_config_nonce'); ?>

        <div class="scl-val-bloc" style="display:block;">
            <div class="scl-val-bloc-title">Mode de validation du classement IA</div>
            <p style="color:#64748b;font-size:13px;">
                Note : contrairement à l'indexation, le classement modifie toujours en amont une proposition
                de l'IA que vous devez cocher/valider manuellement dans l'écran de classement — ce réglage
                ne s'applique qu'à un futur traitement en lot automatique.
            </p>
            <label style="display:flex;align-items:flex-start;gap:8px;margin-bottom:10px;font-size:14px;">
                <input type="radio" name="scl_validation_mode" value="manuel" <?php checked($validation_mode, 'manuel'); ?> style="margin-top:3px;">
                <span>
                    <strong>Manuelle (recommandé)</strong><br>
                    <span style="color:#64748b;font-size:13px;">Chaque suggestion IA doit être relue et cochée manuellement avant enregistrement.</span>
                </span>
            </label>
            <label style="display:flex;align-items:flex-start;gap:8px;font-size:14px;">
                <input type="radio" name="scl_validation_mode" value="auto" <?php checked($validation_mode, 'auto'); ?> style="margin-top:3px;">
                <span>
                    <strong>Automatique</strong><br>
                    <span style="color:#dc2626;font-size:13px;">Réservé à un futur traitement en lot — non utilisé par l'écran de classement actuel.</span>
                </span>
            </label>
        </div>

        <div class="scl-val-bloc" style="display:block;margin-top:16px;">
            <div class="scl-val-bloc-title">Longueur des descriptions générées via IA</div>
            <p style="color:#64748b;font-size:13px;">
                S'applique à la fois à la suggestion en lot (« Suggérer via IA ») et au bouton individuel
                « Générer via IA » de l'écran Termes.
            </p>
            <label style="display:inline-flex;align-items:center;gap:6px;margin-right:20px;font-size:14px;">
                De
                <input type="number" name="scl_desc_words_min" min="20" step="10" value="<?php echo esc_attr($word_range['min']); ?>" style="width:80px;">
                mots
            </label>
            <label style="display:inline-flex;align-items:center;gap:6px;font-size:14px;">
                à
                <input type="number" name="scl_desc_words_max" min="20" step="10" value="<?php echo esc_attr($word_range['max']); ?>" style="width:80px;">
                mots
            </label>
        </div>

        <div class="scl-val-bloc" style="display:block;margin-top:16px;">
            <div class="scl-val-bloc-title">État des taxonomies</div>
            <?php foreach (['schilo_parcours' => 'Parcours', 'schilo_theme' => 'Thèmes', 'schilo_serie' => 'Séries'] as $tax => $label) :
                $count = wp_count_terms(['taxonomy' => $tax, 'hide_empty' => false]);
                $count = is_wp_error($count) ? 0 : (int) $count;
            ?>
                <p><strong><?php echo esc_html($label); ?></strong> : <?php echo esc_html($count); ?> terme(s) — <a href="<?php echo esc_url(admin_url('admin.php?page=schilo-builder-classement&tab=termes&taxonomy=' . $tax)); ?>">gérer</a></p>
            <?php endforeach; ?>
        </div>

        <p><button type="submit" class="button button-primary">Enregistrer la configuration</button></p>
    </form>
</div>
