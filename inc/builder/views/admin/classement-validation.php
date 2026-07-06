<?php
if (!defined('ABSPATH')) exit;

use Schilo\Builder\Service\ClassementService;

$post_id = absint($_GET['post_id'] ?? 0);
$service = new ClassementService();
$indexed = $post_id ? $service->getByPostId($post_id) : null;
$post    = $post_id ? get_post($post_id) : null;
$back_url = admin_url('admin.php?page=schilo-builder-classement');

if (!$post || !$indexed || ($indexed['statut_indexation'] ?? '') !== 'valide') {
    echo '<div class="wrap"><div class="notice notice-error"><p>Article introuvable ou non indexé (validé).</p></div>';
    echo '<a href="' . esc_url($back_url) . '" class="button">Retour</a></div>';
    return;
}

$current_terms = $service->getIndexedTermsForPost($post_id);
$current_ids = [
    'schilo_theme'    => array_map(fn($t) => (int) $t->term_id, $current_terms['schilo_theme'] ?? []),
    'schilo_parcours' => array_map(fn($t) => (int) $t->term_id, $current_terms['schilo_parcours'] ?? []),
    'schilo_serie'    => array_map(fn($t) => (int) $t->term_id, $current_terms['schilo_serie'] ?? []),
];

// Suggestion IA en attente (issue d'un classement en lot non encore validé) :
// pré-coche les cases correspondantes en plus des termes déjà assignés.
$pending_suggestion = $service->getSuggestion($post_id);
$suggestion_ordres  = [];
if ($pending_suggestion) {
    $current_ids['schilo_theme']    = array_unique(array_merge($current_ids['schilo_theme'], $pending_suggestion['theme_term_ids'] ?? []));
    $current_ids['schilo_parcours'] = array_unique(array_merge($current_ids['schilo_parcours'], $pending_suggestion['parcours_term_ids'] ?? []));
    $current_ids['schilo_serie']    = array_unique(array_merge($current_ids['schilo_serie'], $pending_suggestion['serie_term_ids'] ?? []));
    $suggestion_ordres = (array) ($pending_suggestion['ordres'] ?? []);
}

$trees = [
    'schilo_theme'    => $service->getTermsTree('schilo_theme'),
    'schilo_parcours' => $service->getTermsTree('schilo_parcours'),
    'schilo_serie'    => $service->getTermsTree('schilo_serie'),
];

$render_checklist = function (array $tree, string $field, array $checked_ids, bool $with_ordre) use ($service, $post_id, $suggestion_ordres) {
    foreach ($tree as $term) {
        $tid = (int) $term->term_id;
        $ordre = $service->getPostOrderInTerm($post_id, $tid) ?: ($suggestion_ordres[$tid] ?? 0);
        echo '<label class="scl-term-row">';
        echo '<input type="checkbox" name="' . esc_attr($field) . '[]" value="' . esc_attr($tid) . '"' . (in_array($tid, $checked_ids, true) ? ' checked' : '') . '>';
        echo ' <strong>' . esc_html($term->name) . '</strong>';
        if ($with_ordre) {
            echo ' <input type="number" min="0" class="scl-ordre-input" name="ordres[' . esc_attr($tid) . ']" value="' . esc_attr($ordre) . '" title="Ordre">';
        }
        echo '</label>';
        foreach ($term->children as $child) {
            $cid = (int) $child->term_id;
            $cordre = $service->getPostOrderInTerm($post_id, $cid) ?: ($suggestion_ordres[$cid] ?? 0);
            echo '<label class="scl-term-row scl-term-child">';
            echo '<input type="checkbox" name="' . esc_attr($field) . '[]" value="' . esc_attr($cid) . '"' . (in_array($cid, $checked_ids, true) ? ' checked' : '') . '>';
            echo ' ' . esc_html($child->name);
            if ($with_ordre) {
                echo ' <input type="number" min="0" class="scl-ordre-input" name="ordres[' . esc_attr($cid) . ']" value="' . esc_attr($cordre) . '" title="Ordre">';
            }
            echo '</label>';
        }
    }
};

$render_parent_options = function (array $tree) {
    echo '<option value="0">— Terme de premier niveau —</option>';
    foreach ($tree as $term) {
        echo '<option value="' . esc_attr((int) $term->term_id) . '">' . esc_html($term->name) . '</option>';
    }
};
?>
<div class="wrap schilo-builder-settings" id="scl-val-wrap">
    <h1 class="scl-page-title">
        <span class="dashicons dashicons-networking" style="color:#2872d4;font-size:24px;height:24px;width:24px;vertical-align:middle;margin-right:8px;"></span>
        Classer : <?php echo esc_html($post->post_title); ?>
    </h1>

    <?php if ($pending_suggestion) : ?>
    <div class="notice notice-info" style="padding:10px 12px;">
        <p style="margin:0;"><strong>Suggestion IA en attente</strong> (générée via un classement en lot) — les cases ci-dessous sont pré-cochées d'après cette suggestion. Vérifiez avant d'enregistrer.</p>
    </div>
    <?php endif; ?>

    <div class="scl-val-header">
        <strong>Indices déjà indexés (lecture seule)</strong> —
        Thème : <?php echo esc_html($indexed['theme_principal'] ?: '—'); ?> /
        Sous-thème : <?php echo esc_html($indexed['sous_theme'] ?: '—'); ?> /
        Parcours : <?php echo esc_html($indexed['parcours'] ?: '—'); ?> /
        Série : <?php echo esc_html($indexed['serie'] ?: '—'); ?> /
        Ordre : <?php echo esc_html((string) $indexed['ordre_serie']); ?>
    </div>

    <div class="scl-val-bloc">
        <button type="button" id="scl-btn-classify" class="button button-primary" data-post-id="<?php echo esc_attr($post_id); ?>">
            <span class="dashicons dashicons-superhero" style="vertical-align:middle;margin-top:-2px;"></span>
            Classer via IA (suggestion)
        </button>
        <select id="scl-provider-select">
            <option value="claude">Claude AI</option>
            <option value="openai">ChatGPT</option>
        </select>
        <div id="scl-ia-suggestion" style="display:none;margin-top:12px;"></div>
    </div>

    <form id="scl-classement-form">
        <input type="hidden" name="post_id" value="<?php echo esc_attr($post_id); ?>">

        <div class="scl-val-bloc">
            <div class="scl-val-bloc-title">Thème / sous-thème</div>
            <?php $render_checklist($trees['schilo_theme'], 'theme_term_ids', $current_ids['schilo_theme'], false); ?>
            <div class="scl-new-term">
                <input type="text" name="new_theme" placeholder="Nouveau thème ou sous-thème...">
                <select name="new_theme_parent"><?php $render_parent_options($trees['schilo_theme']); ?></select>
            </div>
        </div>

        <div class="scl-val-bloc">
            <div class="scl-val-bloc-title">Parcours / étape (un article peut appartenir à plusieurs)</div>
            <?php $render_checklist($trees['schilo_parcours'], 'parcours_term_ids', $current_ids['schilo_parcours'], true); ?>
            <div class="scl-new-term">
                <input type="text" name="new_parcours" placeholder="Nouveau parcours ou étape...">
                <select name="new_parcours_parent"><?php $render_parent_options($trees['schilo_parcours']); ?></select>
            </div>
        </div>

        <div class="scl-val-bloc">
            <div class="scl-val-bloc-title">Série</div>
            <?php $render_checklist($trees['schilo_serie'], 'serie_term_ids', $current_ids['schilo_serie'], true); ?>
            <div class="scl-new-term">
                <input type="text" name="new_serie" placeholder="Nouvelle série...">
            </div>
        </div>

        <div class="scl-val-footer">
            <button type="submit" class="button button-primary button-large">
                <span class="dashicons dashicons-saved" style="vertical-align:middle;"></span>
                Enregistrer le classement
            </button>
            <a href="<?php echo esc_url($back_url); ?>" class="button button-large">Annuler</a>
            <span id="scl-val-feedback" style="margin-left:12px;display:none;font-weight:600;"></span>
        </div>
    </form>
</div>

<script>
window.sclData = {
    nonce:   '<?php echo esc_js(wp_create_nonce('schilo_classement')); ?>',
    ajaxUrl: '<?php echo esc_js(admin_url('admin-ajax.php')); ?>',
    backUrl: '<?php echo esc_js($back_url); ?>',
};
</script>
