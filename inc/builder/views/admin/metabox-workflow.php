<?php
/** @var int $postId */
/** @var array|null $indexationRecord */
/** @var array|null $classementRecord */
/** @var array $classementTerms */

if (!defined('ABSPATH')) {
    exit;
}

$indexationBadges = array(
    'valide'     => array('Valide', 'sia-badge-green'),
    'en_attente' => array('En attente', 'sia-badge-orange'),
    'brouillon'  => array('Brouillon', 'sia-badge-grey'),
    'rejete'     => array('Rejete', 'sia-badge-red'),
);
$indexationStatut = $indexationRecord['statut_indexation'] ?? '';
list($indexationLabel, $indexationClass) = $indexationBadges[$indexationStatut] ?? array('Non indexe', 'sia-badge-grey');

$classementStatut = $classementRecord['statut_classement'] ?? 'non_classe';
if ($classementStatut === 'classe') {
    $classementLabel = 'Classe';
    $classementClass = 'scl-badge-green';
} else {
    $classementLabel = 'Non classe';
    $classementClass = 'scl-badge-grey';
}

$isUnsaved = !$postId || get_post_status($postId) !== 'publish';

$decodeList = function ($json) {
    $arr = json_decode((string) $json, true);
    return is_array($arr) ? implode(', ', $arr) : '';
};
?>
<div class="schilo-workflow-metabox">

    <div class="schilo-workflow-block" data-workflow="indexation">
        <div class="schilo-workflow-block__head">
            <strong>Indexation</strong>
            <span class="sia-badge <?php echo esc_attr($indexationClass); ?>" data-role="badge"><?php echo esc_html($indexationLabel); ?></span>
        </div>

        <p class="schilo-workflow-actions">
            <button type="button" class="button button-small schilo-workflow-btn-ia" data-target="indexation" <?php disabled($isUnsaved); ?>>
                Indexer via IA
            </button>
            <a href="<?php echo esc_url(admin_url('admin.php?page=schilo-builder-indexation&tab=validation&post_id=' . $postId)); ?>"
               class="button button-small <?php echo $isUnsaved ? 'disabled' : ''; ?>" target="_blank" rel="noopener"
               <?php if ($isUnsaved) : ?>onclick="return false;" aria-disabled="true"<?php endif; ?>>
                Ecran complet
            </a>
        </p>

        <p class="schilo-workflow-feedback" data-feedback="indexation" style="display:none;"></p>

        <div class="schilo-workflow-zone" data-zone="indexation">
            <p class="schilo-workflow-zone__intro">Metadonnees descriptives de l'article (texte libre, pour la recherche et le SEO).</p>

            <p class="schilo-workflow-field">
                <label><span class="dashicons dashicons-editor-alignleft"></span>Resume court</label>
                <textarea name="resume_court" rows="3" maxlength="500" placeholder="Resume en une ou deux phrases…" <?php disabled($isUnsaved); ?>><?php echo esc_textarea($indexationRecord['resume_court'] ?? ''); ?></textarea>
                <span class="schilo-workflow-counter" data-counter-for="resume_court">0/500</span>
            </p>
            <p class="schilo-workflow-field">
                <label><span class="dashicons dashicons-tag"></span>Mots-cles</label>
                <input type="text" name="mots_cles" value="<?php echo esc_attr($decodeList($indexationRecord['mots_cles'] ?? '[]')); ?>" placeholder="mot1, mot2, mot3…" <?php disabled($isUnsaved); ?>>
            </p>
            <p class="schilo-workflow-field">
                <label><span class="dashicons dashicons-category"></span>Theme principal</label>
                <input type="text" name="theme_principal" value="<?php echo esc_attr($indexationRecord['theme_principal'] ?? ''); ?>" placeholder="ex. La vie de Jesus" <?php disabled($isUnsaved); ?>>
            </p>
            <p class="schilo-workflow-field">
                <label><span class="dashicons dashicons-networking"></span>Parcours</label>
                <input type="text" name="parcours" value="<?php echo esc_attr($indexationRecord['parcours'] ?? ''); ?>" placeholder="ex. Synopse des Evangiles" <?php disabled($isUnsaved); ?>>
            </p>
            <p class="schilo-workflow-field">
                <label><span class="dashicons dashicons-playlist-audio"></span>Serie</label>
                <input type="text" name="serie" value="<?php echo esc_attr($indexationRecord['serie'] ?? ''); ?>" <?php disabled($isUnsaved); ?>>
            </p>
            <button type="button" class="button button-primary button-small schilo-workflow-btn-save" data-target="indexation" <?php disabled($isUnsaved); ?>>
                <span class="dashicons dashicons-saved"></span> Enregistrer
            </button>
        </div>
    </div>

    <div class="schilo-workflow-block" data-workflow="classement">
        <div class="schilo-workflow-block__head">
            <strong>Classement</strong>
            <span class="scl-badge <?php echo esc_attr($classementClass); ?>" data-role="badge"><?php echo esc_html($classementLabel); ?></span>
        </div>

        <p class="schilo-workflow-actions">
            <button type="button" class="button button-small schilo-workflow-btn-ia" data-target="classement" <?php disabled($isUnsaved); ?>>
                Classer via IA
            </button>
            <a href="<?php echo esc_url(admin_url('admin.php?page=schilo-builder-classement&tab=validation&post_id=' . $postId)); ?>"
               class="button button-small <?php echo $isUnsaved ? 'disabled' : ''; ?>" target="_blank" rel="noopener"
               <?php if ($isUnsaved) : ?>onclick="return false;" aria-disabled="true"<?php endif; ?>>
                Ecran complet
            </a>
        </p>

        <p class="schilo-workflow-feedback" data-feedback="classement" style="display:none;"></p>

        <div class="schilo-workflow-zone" data-zone="classement">
            <p class="schilo-workflow-zone__intro">Taxonomies reelles rattachees a l'article (creees a la volee si besoin).</p>

            <p class="schilo-workflow-field">
                <label><span class="dashicons dashicons-category"></span>Theme</label>
                <input type="text" name="theme" value="<?php echo esc_attr($classementTerms['schilo_theme'] ?? ''); ?>" placeholder="ex. La vie de Jesus" <?php disabled($isUnsaved); ?>>
            </p>
            <p class="schilo-workflow-field">
                <label><span class="dashicons dashicons-networking"></span>Parcours</label>
                <input type="text" name="parcours" value="<?php echo esc_attr($classementTerms['schilo_parcours'] ?? ''); ?>" placeholder="ex. Synopse des Evangiles" <?php disabled($isUnsaved); ?>>
            </p>
            <p class="schilo-workflow-field">
                <label><span class="dashicons dashicons-playlist-audio"></span>Serie</label>
                <input type="text" name="serie" value="<?php echo esc_attr($classementTerms['schilo_serie'] ?? ''); ?>" <?php disabled($isUnsaved); ?>>
            </p>
            <span class="description">Un seul terme par champ ici (cree si besoin). Pour choisir parmi les termes existants ou en ajouter plusieurs, utilisez l'ecran complet.</span>
            <button type="button" class="button button-primary button-small schilo-workflow-btn-save" data-target="classement" <?php disabled($isUnsaved); ?>>
                <span class="dashicons dashicons-saved"></span> Enregistrer
            </button>
        </div>
    </div>

    <?php if ($isUnsaved) : ?>
        <p class="description" style="margin-top:8px;">
            Publiez d'abord cet article pour pouvoir l'indexer et le classer.
        </p>
    <?php endif; ?>

</div>
