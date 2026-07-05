<?php
if (!defined('ABSPATH')) exit;

use Schilo\Builder\Service\ClassementService;

$service = new ClassementService();

$taxonomies = [
    'schilo_parcours' => 'Parcours',
    'schilo_theme'    => 'Thèmes',
    'schilo_serie'    => 'Séries',
];

$current_tax = isset($_GET['taxonomy']) && array_key_exists($_GET['taxonomy'], $taxonomies)
    ? sanitize_key($_GET['taxonomy'])
    : 'schilo_parcours';

$tree = $service->getTermsTree($current_tax);
$base_url = admin_url('admin.php?page=schilo-builder-classement&tab=termes');
?>
<div class="wrap schilo-builder-settings">
    <h1 class="scl-page-title">
        <span class="dashicons dashicons-networking" style="color:#2872d4;font-size:24px;height:24px;width:24px;vertical-align:middle;margin-right:8px;"></span>
        Parcours &amp; Thèmes — Gestion des termes
    </h1>

    <nav class="scl-tabs">
        <a href="<?php echo esc_url(admin_url('admin.php?page=schilo-builder-classement')); ?>" class="scl-tab">Classement</a>
        <a href="<?php echo esc_url($base_url); ?>" class="scl-tab scl-tab-active">Termes</a>
        <a href="<?php echo esc_url(admin_url('admin.php?page=schilo-builder-classement&tab=config')); ?>" class="scl-tab">Configuration</a>
    </nav>

    <div class="scl-val-bloc" style="display:block;">
        <div class="scl-val-bloc-title">Suggestion de vocabulaire via IA</div>
        <p style="color:#64748b;font-size:13px;margin-top:0;">
            Analyse les valeurs indexées en texte libre (thème, sous-thème, parcours, série sur les
            articles déjà indexés) et propose une hiérarchie de termes propre pour les 3 taxonomies,
            en tenant compte des termes déjà créés (jamais renommés ni supprimés automatiquement).
        </p>
        <select id="scl-curation-provider">
            <option value="claude">Claude AI</option>
            <option value="openai">ChatGPT</option>
        </select>
        <button type="button" id="scl-btn-propose-terms" class="button button-primary">
            <span class="dashicons dashicons-superhero" style="vertical-align:middle;margin-top:-2px;"></span>
            Suggérer via IA
        </button>
        <span id="scl-curation-feedback" style="margin-left:8px;display:none;font-weight:600;"></span>
        <div id="scl-curation-preview" style="display:none;margin-top:16px;"></div>
    </div>

    <div class="scl-prefix-pills">
        <?php foreach ($taxonomies as $tax => $label) : ?>
        <a href="<?php echo esc_url(add_query_arg('taxonomy', $tax, $base_url)); ?>" class="scl-prefix-tab<?php echo $current_tax === $tax ? ' current' : ''; ?>">
            <?php echo esc_html($label); ?>
        </a>
        <?php endforeach; ?>
    </div>

    <div class="scl-val-bloc" style="display:block;">
        <div class="scl-val-bloc-title">Termes existants — <?php echo esc_html($taxonomies[$current_tax]); ?></div>

        <?php if (empty($tree)) : ?>
            <p class="scl-empty">Aucun terme pour cette taxonomie. Créez le premier ci-dessous.</p>
        <?php else : ?>
        <table class="widefat scl-table" id="scl-terms-table">
            <thead><tr><th style="width:22%;">Nom</th><th>Description</th><th style="width:80px;">Ordre</th><th style="width:80px;">Actions</th></tr></thead>
            <tbody>
                <?php foreach ($tree as $term) : ?>
                <tr data-term-id="<?php echo esc_attr((int) $term->term_id); ?>">
                    <td><strong><?php echo esc_html($term->name); ?></strong></td>
                    <td><textarea class="scl-term-description" rows="2" placeholder="Description affichée sur la page publique..." data-term-id="<?php echo esc_attr((int) $term->term_id); ?>" data-taxonomy="<?php echo esc_attr($current_tax); ?>"><?php echo esc_textarea($term->description); ?></textarea></td>
                    <td><input type="number" min="0" class="scl-term-ordre" value="<?php echo esc_attr(get_term_meta((int) $term->term_id, 'schilo_ordre', true) ?: 0); ?>" data-term-id="<?php echo esc_attr((int) $term->term_id); ?>"></td>
                    <td><button type="button" class="button button-small scl-btn-delete-term" data-term-id="<?php echo esc_attr((int) $term->term_id); ?>" data-taxonomy="<?php echo esc_attr($current_tax); ?>">Supprimer</button></td>
                </tr>
                <?php foreach ($term->children as $child) : ?>
                <tr data-term-id="<?php echo esc_attr((int) $child->term_id); ?>">
                    <td style="padding-left:28px;">— <?php echo esc_html($child->name); ?></td>
                    <td><textarea class="scl-term-description" rows="2" placeholder="Description affichée sur la page publique..." data-term-id="<?php echo esc_attr((int) $child->term_id); ?>" data-taxonomy="<?php echo esc_attr($current_tax); ?>"><?php echo esc_textarea($child->description); ?></textarea></td>
                    <td><input type="number" min="0" class="scl-term-ordre" value="<?php echo esc_attr(get_term_meta((int) $child->term_id, 'schilo_ordre', true) ?: 0); ?>" data-term-id="<?php echo esc_attr((int) $child->term_id); ?>"></td>
                    <td><button type="button" class="button button-small scl-btn-delete-term" data-term-id="<?php echo esc_attr((int) $child->term_id); ?>" data-taxonomy="<?php echo esc_attr($current_tax); ?>">Supprimer</button></td>
                </tr>
                <?php endforeach; ?>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>

        <h3 style="margin-top:20px;">Ajouter un terme</h3>
        <div class="scl-new-term">
            <input type="text" id="scl-new-term-name" placeholder="Nom du terme...">
            <?php if ($current_tax !== 'schilo_serie') : ?>
            <select id="scl-new-term-parent">
                <option value="0">— Terme de premier niveau —</option>
                <?php foreach ($tree as $term) : ?>
                <option value="<?php echo esc_attr((int) $term->term_id); ?>"><?php echo esc_html($term->name); ?></option>
                <?php endforeach; ?>
            </select>
            <?php endif; ?>
            <button type="button" id="scl-btn-add-term" class="button button-primary" data-taxonomy="<?php echo esc_attr($current_tax); ?>">Ajouter</button>
        </div>
        <textarea id="scl-new-term-description" rows="2" placeholder="Description (optionnelle, affichée sur la page publique)..." style="width:100%;max-width:520px;margin-top:8px;"></textarea>
        <br>
        <span id="scl-terms-feedback" style="margin-left:8px;display:none;font-weight:600;"></span>
    </div>
</div>

<script>
window.sclData = {
    nonce:   '<?php echo esc_js(wp_create_nonce('schilo_classement')); ?>',
    ajaxUrl: '<?php echo esc_js(admin_url('admin-ajax.php')); ?>',
};
</script>
