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

        $paragraphs_min = max(1, absint($_POST['scl_desc_paragraphs_min'] ?? 2));
        $paragraphs_max = max($paragraphs_min, absint($_POST['scl_desc_paragraphs_max'] ?? 4));
        update_option('schilo_classement_desc_paragraphs', ['min' => $paragraphs_min, 'max' => $paragraphs_max], false);

        $prefix_rules_raw = (array) ($_POST['scl_prefix_rules'] ?? []);
        $prefix_rules = [];
        foreach ($prefix_rules_raw as $prefix => $rule) {
            $prefix_rules[$prefix] = [
                'role'   => sanitize_key($rule['role'] ?? 'principal'),
                'poids'  => absint($rule['poids'] ?? 50),
                'limite' => absint($rule['limite'] ?? 0),
            ];
        }
        $service->savePrefixRules($prefix_rules);

        $saved = true;
    }
}

$validation_mode = get_option('schilo_classement_validation_mode', 'manuel');
$word_range = $service->getDescriptionWordRange();
$paragraph_range = $service->getDescriptionParagraphRange();
$prefix_rules = $service->getPrefixRules();
$prefix_categories = (array) get_option(\Schilo\Builder\Admin\SettingsPage::OPTION_PREFIX_CATEGORIES, []);
$back_url = admin_url('admin.php?page=schilo-builder-classement');

$role_labels = [
    'principal'  => 'Principal',
    'complement' => 'Complément',
    'exclu'      => 'Exclu',
];
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
            <p style="color:#64748b;font-size:13px;margin-bottom:6px;">
                Le texte est structuré en plusieurs paragraphes courts plutôt qu'un seul bloc compact
                (affichage réel sur la page publique, pas seulement dans l'éditeur).
            </p>
            <label style="display:inline-flex;align-items:center;gap:6px;margin-right:20px;font-size:14px;">
                De
                <input type="number" name="scl_desc_paragraphs_min" min="1" step="1" value="<?php echo esc_attr($paragraph_range['min']); ?>" style="width:80px;">
                paragraphe(s)
            </label>
            <label style="display:inline-flex;align-items:center;gap:6px;font-size:14px;">
                à
                <input type="number" name="scl_desc_paragraphs_max" min="1" step="1" value="<?php echo esc_attr($paragraph_range['max']); ?>" style="width:80px;">
                paragraphe(s)
            </label>
        </div>

        <div class="scl-val-bloc" style="display:block;margin-top:16px;">
            <div class="scl-val-bloc-title">Règles de classement par préfixe d'article</div>
            <p style="color:#64748b;font-size:13px;">
                Tous les préfixes n'ont pas la même vocation : une Annexe (ANN) complète un PER,
                elle ne devrait pas devenir un élément de premier plan au même titre. <strong>Rôle</strong> :
                Principal (peut être un élément de premier plan), Complément (classable mais jamais en premier plan —
                s'affiche rattaché à l'article qui le référence, via les articles liés déjà indexés),
                ou Exclu (non classable). <strong>Poids</strong> : départage l'ordre entre
                plusieurs compléments d'un même article. <strong>Limite</strong> : nombre max de ce
                préfixe autorisé dans un même terme (0 = illimité). S'applique aux
                <strong>parcours, thèmes et séries</strong>.
            </p>
            <?php if (empty($prefix_rules)) : ?>
                <p style="color:#94a3b8;">Aucun préfixe détecté pour le moment (aucun article indexé ou mappé dans Préfixes &amp; catégories).</p>
            <?php else : ?>
            <table class="widefat scl-table">
                <thead><tr>
                    <th style="width:90px;">Préfixe</th>
                    <th>Catégorie WP</th>
                    <th style="width:160px;">Rôle</th>
                    <th style="width:110px;">Poids</th>
                    <th style="width:110px;">Limite/terme</th>
                </tr></thead>
                <tbody>
                    <?php foreach ($prefix_rules as $prefix => $rule) :
                        $cat_id   = (int) ($prefix_categories[$prefix] ?? 0);
                        $cat_term = $cat_id ? get_term($cat_id, 'category') : null;
                        $cat_name = ($cat_term && !is_wp_error($cat_term)) ? $cat_term->name : '—';
                    ?>
                    <tr>
                        <td><strong><?php echo esc_html($prefix); ?></strong></td>
                        <td><?php echo esc_html($cat_name); ?></td>
                        <td>
                            <select name="scl_prefix_rules[<?php echo esc_attr($prefix); ?>][role]">
                                <?php foreach ($role_labels as $val => $label) : ?>
                                    <option value="<?php echo esc_attr($val); ?>" <?php selected($rule['role'], $val); ?>><?php echo esc_html($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td>
                            <div style="display:flex;align-items:center;gap:8px;">
                                <input type="range" min="0" max="100" step="5"
                                    name="scl_prefix_rules[<?php echo esc_attr($prefix); ?>][poids]"
                                    value="<?php echo esc_attr($rule['poids']); ?>" style="width:90px;"
                                    oninput="this.nextElementSibling.textContent=this.value">
                                <span style="min-width:26px;display:inline-block;font-variant-numeric:tabular-nums;font-weight:600;color:#334155;"><?php echo esc_html($rule['poids']); ?></span>
                            </div>
                        </td>
                        <td><input type="number" min="0" name="scl_prefix_rules[<?php echo esc_attr($prefix); ?>][limite]" value="<?php echo esc_attr($rule['limite']); ?>" style="width:70px;" placeholder="0 = illimité"></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
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
