<?php
/**
 * Vue : Outils — navigation par cartes + chargement AJAX des panneaux
 *
 * Variables disponibles :
 *   $active_tool         : string — outil actif après un POST
 *   $result              : array|null
 *   $result_empty_cats   : array|null
 *   $result_orphan_media : array|null
 *   $result_raccourcis   : array|null
 *   $result_doublons_prefixe : array|null
 *   $selected_parent_id  : int
 *   $parent_categories   : WP_Term[]
 *   $raccourcis_map      : array
 */
if (!defined('ABSPATH')) exit;

$tools = array(
    'inherit_cat'  => array(
        'icon'  => 'dashicons-category',
        'label' => 'Héritage catégorie parente',
        'desc'  => 'Associer les sous-catégories à leur parent',
    ),
    'delete_cats'  => array(
        'icon'  => 'dashicons-trash',
        'label' => 'Catégories vides',
        'desc'  => 'Supprimer les catégories sans articles',
    ),
    'delete_media' => array(
        'icon'  => 'dashicons-images-alt2',
        'label' => 'Médias orphelins',
        'desc'  => 'Supprimer les médias non attachés',
    ),
    'raccourcis'   => array(
        'icon'  => 'dashicons-editor-code',
        'label' => 'Raccourcis Live WP',
        'desc'  => 'Configurer les raccourcis TinyMCE',
    ),
    'ia_config'    => array(
        'icon'  => 'dashicons-superhero',
        'label' => 'Intelligence Artificielle',
        'desc'  => 'Configurer les APIs Claude et ChatGPT',
        'color' => '#6d3fc0',
    ),
    'doublons_prefixe' => array(
        'icon'  => 'dashicons-search',
        'label' => 'Doublons de préfixe',
        'desc'  => 'Trouver et corriger les numéros en double',
    ),
);
?>

<div class="wrap schilo-builder-settings">
    <h1>Outils</h1>
    <p class="schilo-dashboard-intro">Sélectionnez un outil pour l'ouvrir.</p>

    <div class="schilo-outils-grid">
        <?php foreach ($tools as $tool_id => $tool) : ?>
        <button type="button"
                class="schilo-outil-card<?php echo $active_tool === $tool_id ? ' is-active' : ''; ?>"
                data-tool="<?php echo esc_attr($tool_id); ?>">
            <span class="dashicons <?php echo esc_attr($tool['icon']); ?> schilo-outil-icon"></span>
            <strong><?php echo esc_html($tool['label']); ?></strong>
            <span class="schilo-outil-desc"><?php echo esc_html($tool['desc']); ?></span>
        </button>
        <?php endforeach; ?>
    </div>

    <div id="schilo-tool-panel">
        <?php
        if ($active_tool && isset($tools[$active_tool])) {
            $partial = SCHILO_BUILDER_PATH . 'views/admin/partials/tool-' . $active_tool . '.php';
            if (file_exists($partial)) {
                include $partial;
            }
        }
        ?>
    </div>
</div>

<script>
var schiloOutilsActive = <?php echo wp_json_encode($active_tool ?: ''); ?>;
</script>

<style>
.schilo-outils-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(155px, 1fr));
    gap: 12px;
    max-width: 1040px;
    margin: 20px 0;
}
.schilo-outil-card {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 6px;
    padding: 18px 14px 14px;
    background: #fff;
    border: 2px solid #e0e0e0;
    border-radius: 8px;
    cursor: pointer;
    text-align: center;
    transition: border-color .15s, box-shadow .15s, background .15s;
    font-family: inherit;
    font-size: 13px;
    color: #1d2327;
    line-height: 1.35;
}
.schilo-outil-card:hover {
    border-color: var(--schilo-accent, #2872d4);
    box-shadow: 0 0 0 3px rgba(40,114,212,.12);
}
.schilo-outil-card.is-active {
    border-color: var(--schilo-accent, #2872d4);
    background: #eff6ff;
}
.schilo-outil-icon {
    font-size: 28px !important;
    width: 28px !important;
    height: 28px !important;
    color: var(--schilo-accent, #2872d4);
}
.schilo-outil-desc {
    font-size: .78rem;
    color: #6b7280;
    margin-top: 2px;
}
#schilo-tool-panel { max-width: 820px; }
#schilo-tool-panel .schilo-tool-card {
    background: #fff;
    border: 1px solid #e0e0e0;
    border-radius: 6px;
    padding: 24px 28px;
    margin-top: 16px;
}
#schilo-tool-panel .schilo-tool-card h2 { margin-top: 0; font-size: 15px; color: #1d2327; }
#schilo-tool-panel .schilo-tool-result { margin-top: 20px; }
</style>
