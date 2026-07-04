<?php
if (!defined('ABSPATH')) exit;

use Schilo\Builder\Service\IndexationService;

$post_id = absint($_GET['post_id'] ?? 0);
$service  = new IndexationService();
$existing = $post_id ? $service->getByPostId($post_id) : null;
$post     = $post_id ? get_post($post_id) : null;

if (!$post) {
    echo '<div class="wrap"><div class="notice notice-error"><p>Article introuvable.</p></div></div>';
    return;
}

$back_url = admin_url('admin.php?page=schilo-builder-indexation');
?>
<div class="wrap schilo-builder-settings">
    <h1 class="sia-page-title">
        <span class="dashicons dashicons-yes-alt" style="color:#6d3fc0;font-size:26px;height:26px;width:26px;vertical-align:middle;margin-right:8px;"></span>
        Validation de l'indexation
    </h1>
    <a href="<?php echo esc_url($back_url); ?>" class="button" style="margin-bottom:16px;">
        &larr; Retour a la liste
    </a>

    <div class="sia-val-header">
        <strong><?php echo esc_html($post->post_title); ?></strong>
        <span style="color:#64748b;font-size:13px;">ID <?php echo $post_id; ?></span>
        <?php if ($existing) : ?>
        <span class="sia-badge sia-badge-orange" style="margin-left:8px;">En attente de validation</span>
        <?php endif; ?>
    </div>

    <?php if ($existing && $existing['donnees_ia_brutes']) : ?>
    <details class="sia-raw-section" style="margin-bottom:16px;">
        <summary class="sia-raw-toggle">Donnees brutes IA (audit)</summary>
        <pre class="sia-raw-pre"><?php echo esc_html(substr($existing['donnees_ia_brutes'], 0, 3000)); ?></pre>
    </details>
    <?php endif; ?>

    <form id="sia-validation-form" method="post">
        <input type="hidden" name="post_id" value="<?php echo $post_id; ?>">

        <?php
        $v = function($key) use ($existing) {
            return esc_attr($existing[$key] ?? '');
        };
        $vt = function($key) use ($existing) {
            return esc_textarea($existing[$key] ?? '');
        };
        $vj = function($key) use ($existing) {
            $raw = $existing[$key] ?? '[]';
            $arr = json_decode($raw, true);
            return is_array($arr) ? implode(', ', $arr) : '';
        };
        ?>

        <!-- BLOC 1 : Contenu principal -->
        <div class="sia-val-bloc">
            <div class="sia-val-bloc-title">Analyse de contenu</div>

            <div class="sia-val-field sia-val-field-full">
                <label>Resume (500-800 mots)</label>
                <textarea name="resume" rows="8" class="large-text"><?php echo $vt('resume'); ?></textarea>
                <span class="sia-field-hint">Compte de mots : <span class="sia-word-count">0</span></span>
            </div>

            <div class="sia-val-field">
                <label>Resume court (max 150 mots)</label>
                <textarea name="resume_court" rows="3" class="large-text"><?php echo $vt('resume_court'); ?></textarea>
            </div>

            <div class="sia-val-field">
                <label>Mots-cles (separes par des virgules)</label>
                <input type="text" name="mots_cles_raw" value="<?php echo esc_attr($vj('mots_cles')); ?>" class="large-text">
            </div>

            <div class="sia-val-field">
                <label>Concepts theologiques</label>
                <input type="text" name="concepts_raw" value="<?php echo esc_attr($vj('concepts')); ?>" class="large-text">
            </div>

            <div class="sia-val-field">
                <label>Personnages</label>
                <input type="text" name="personnages_raw" value="<?php echo esc_attr($vj('personnages')); ?>" class="large-text">
            </div>

            <div class="sia-val-field">
                <label>Lieux</label>
                <input type="text" name="lieux_raw" value="<?php echo esc_attr($vj('lieux')); ?>" class="large-text">
            </div>

            <div class="sia-val-field">
                <label>Periodes / epoques</label>
                <input type="text" name="periodes_raw" value="<?php echo esc_attr($vj('periodes')); ?>" class="large-text">
            </div>

            <div class="sia-val-field">
                <label>References bibliques</label>
                <input type="text" name="references_bibliques_raw" value="<?php echo esc_attr($vj('references_bibliques')); ?>" class="large-text">
            </div>

            <div class="sia-val-field sia-val-field-full">
                <label>Citations cles</label>
                <textarea name="citations_cles_raw" rows="3" class="large-text"><?php echo esc_textarea(implode("\n", json_decode($existing['citations_cles'] ?? '[]', true) ?: [])); ?></textarea>
                <span class="sia-field-hint">Une citation par ligne</span>
            </div>

            <div class="sia-val-field">
                <label>Public cible</label>
                <select name="public_cible">
                    <?php foreach (['Debutant', 'Intermediaire', 'Expert'] as $opt) : ?>
                    <option value="<?php echo esc_attr($opt); ?>" <?php selected($existing['public_cible'] ?? '', $opt); ?>><?php echo esc_html($opt); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="sia-val-field">
                <label>Niveau de lecture</label>
                <select name="niveau_lecture">
                    <?php foreach (['Simple', 'Moyen', 'Avance'] as $opt) : ?>
                    <option value="<?php echo esc_attr($opt); ?>" <?php selected($existing['niveau_lecture'] ?? '', $opt); ?>><?php echo esc_html($opt); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <!-- BLOC 2 : Classification -->
        <div class="sia-val-bloc">
            <div class="sia-val-bloc-title">Classification thematique</div>

            <div class="sia-val-field">
                <label>Theme principal</label>
                <input type="text" name="theme_principal" value="<?php echo $v('theme_principal'); ?>" class="large-text">
            </div>
            <div class="sia-val-field">
                <label>Sous-theme</label>
                <input type="text" name="sous_theme" value="<?php echo $v('sous_theme'); ?>" class="large-text">
            </div>
            <div class="sia-val-field">
                <label>Parcours</label>
                <input type="text" name="parcours" value="<?php echo $v('parcours'); ?>" class="large-text">
            </div>
            <div class="sia-val-field">
                <label>Serie</label>
                <input type="text" name="serie" value="<?php echo $v('serie'); ?>" class="large-text">
            </div>
            <div class="sia-val-field" style="max-width:200px;">
                <label>Ordre dans la serie</label>
                <input type="number" name="ordre_serie" value="<?php echo $v('ordre_serie'); ?>" min="0">
            </div>
        </div>

        <!-- BLOC 3 : SEO -->
        <div class="sia-val-bloc">
            <div class="sia-val-bloc-title">SEO</div>

            <div class="sia-val-field sia-val-field-full">
                <label>Meta titre <span class="sia-field-hint">(max 70 cars)</span></label>
                <input type="text" name="seo_titre" value="<?php echo $v('seo_titre'); ?>" maxlength="70" class="large-text">
                <span class="sia-char-count"><span class="sia-count-num"><?php echo mb_strlen($existing['seo_titre'] ?? ''); ?></span>/70</span>
            </div>
            <div class="sia-val-field sia-val-field-full">
                <label>Meta description <span class="sia-field-hint">(max 160 cars)</span></label>
                <textarea name="seo_description" rows="2" maxlength="160" class="large-text"><?php echo $vt('seo_description'); ?></textarea>
                <span class="sia-char-count"><span class="sia-count-num"><?php echo mb_strlen($existing['seo_description'] ?? ''); ?></span>/160</span>
            </div>
            <div class="sia-val-field">
                <label>Keywords SEO</label>
                <input type="text" name="seo_mots_cles_raw" value="<?php echo esc_attr($vj('seo_mots_cles')); ?>" class="large-text">
            </div>
            <div class="sia-val-field">
                <label>Open Graph titre</label>
                <input type="text" name="og_titre" value="<?php echo $v('og_titre'); ?>" class="large-text">
            </div>
            <div class="sia-val-field">
                <label>Open Graph description</label>
                <input type="text" name="og_description" value="<?php echo $v('og_description'); ?>" class="large-text">
            </div>
            <div class="sia-val-field">
                <label>Schema.org type</label>
                <input type="text" name="schema_type" value="<?php echo $v('schema_type') ?: 'Article'; ?>" class="regular-text">
            </div>
            <div class="sia-val-field">
                <label>Robots</label>
                <input type="text" name="robots" value="<?php echo $v('robots') ?: 'index,follow'; ?>" class="regular-text">
            </div>
        </div>

        <!-- BLOC 4 : Notes validateur -->
        <div class="sia-val-bloc">
            <div class="sia-val-bloc-title">Notes de validation</div>
            <div class="sia-val-field sia-val-field-full">
                <textarea name="notes_validation" rows="3" class="large-text" placeholder="Notes internes sur cette indexation..."><?php echo $vt('notes_validation'); ?></textarea>
            </div>
        </div>

        <!-- Boutons -->
        <div class="sia-val-footer">
            <button type="submit" id="sia-btn-save-validated" class="button button-primary button-large">
                <span class="dashicons dashicons-saved" style="vertical-align:middle;"></span>
                Valider et enregistrer
            </button>
            <button type="button" id="sia-btn-reject-article" class="button button-large" data-post-id="<?php echo $post_id; ?>">
                Rejeter
            </button>
            <a href="<?php echo esc_url($back_url); ?>" class="button button-large">Annuler</a>
            <span id="sia-val-feedback" style="margin-left:12px;display:none;color:#059669;font-weight:600;"></span>
        </div>
    </form>
</div>