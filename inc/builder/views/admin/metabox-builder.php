<?php
$totalSections   = is_array( $sections ) ? count( $sections ) : 0;
$totalTemplates  = is_array( $availableTypes ) ? max( 0, count( $availableTypes ) - 1 ) : 0;
$urlPrefixCats      = admin_url( 'admin.php' ) . '?page=schilo-builder-prefix-categories';
$urlBuilderSections = admin_url( 'admin.php' ) . '?page=schilo-builder-sections';
$thumbnailId     = get_post_thumbnail_id( $postId );
$thumbnailSrc    = '';
if ( $thumbnailId ) {
    $imgSrcArr    = wp_get_attachment_image_src( $thumbnailId, 'medium' );
    $thumbnailSrc = $imgSrcArr ? esc_url( $imgSrcArr[0] ) : '';
}
$nonceFeatured = wp_create_nonce( 'set_post_thumbnail-' . $postId );

$schiloLinkPosts = get_posts( array(
    'post_type'      => array( 'post', 'page' ),
    'post_status'    => 'publish',
    'posts_per_page' => 300,
    'orderby'        => 'title',
    'order'          => 'ASC',
    'exclude'        => isset( $postId ) ? array( (int) $postId ) : array(),
    'no_found_rows'  => true,
) );
?>

<script type="application/json" id="schilo-articles-data">
<?php
$schiloArticlesPayload = array();
foreach ( $schiloLinkPosts as $schiloLinkPost ) {
    $schiloArticlesPayload[] = array(
        'title' => html_entity_decode( get_the_title( $schiloLinkPost ), ENT_QUOTES, 'UTF-8' ),
        'url'   => get_permalink( $schiloLinkPost ),
    );
}
echo wp_json_encode( $schiloArticlesPayload );
?>
</script>

<div class="schilo-builder-admin schilo-builder-v078">
    <div class="schilo-builder-top-grid">

        <div class="schilo-top-card schilo-top-card--info">
            <div class="stc-label">Type actif</div>
            <div class="stc-value"><?php echo esc_html( $prefix ); ?></div>
            <div class="stc-meta"><?php echo esc_html( $totalSections ); ?> section(s) &middot; <?php echo esc_html( $totalTemplates ); ?> template(s)</div>
            <a href="<?php echo esc_url( $urlPrefixCats ); ?>" target="_blank" rel="noopener noreferrer" class="stc-link">Pr&eacute;fixes &amp; cat&eacute;gories &rarr;</a>
        </div>

        <div class="schilo-top-card schilo-top-card--template">
            <div class="stc-field">
                <label for="schilo_builder_type" class="stc-field-label">Template</label>
                <select name="schilo_builder_type" id="schilo_builder_type" class="stc-select">
                    <?php foreach ( $availableTypes as $typeKey => $typeConfig ) : ?>
                        <option value="<?php echo esc_attr( $typeKey ); ?>" <?php selected( $selectedType, $typeKey ); ?>>
                            <?php echo esc_html( isset( $typeConfig['label'] ) ? $typeConfig['label'] : $typeKey ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="stc-template-footer">
                <label class="stc-checkbox">
                    <input type="checkbox" name="schilo_apply_template_sections" value="1">
                    Compl&eacute;ter les sections manquantes &agrave; l&apos;enregistrement
                </label>
                <a href="<?php echo esc_url( $applyTemplateUrl ); ?>"
                   class="button button-primary schilo-apply-template-button stc-apply-btn"
                   data-base-url="<?php echo esc_url( $applyTemplateUrlBase ); ?>"
                   data-nonce="<?php echo esc_attr( $applyTemplateNonce ); ?>">
                    Appliquer le template
                </a>
            </div>
            <?php if ( ! empty( $lastAppliedTemplate ) ) : ?>
                <div class="stc-last">Dernier : <strong><?php echo esc_html( $lastAppliedTemplate ); ?></strong></div>
            <?php endif; ?>
        </div>

        <div class="schilo-top-card schilo-top-card--image" id="schilo-featured-image-slot">
            <div class="stc-label">Image mise en avant</div>
            <?php if ( $thumbnailId && $thumbnailSrc ) : ?>
                <div id="schilo-thumbnail-wrap" class="stc-thumb stc-thumb--set">
                    <img src="<?php echo $thumbnailSrc; ?>" id="schilo-thumbnail-img">
                    <div class="stc-thumb-btns">
                        <button type="button" id="schilo-set-thumbnail" data-post-id="<?php echo esc_attr( $postId ); ?>" data-nonce="<?php echo esc_attr( $nonceFeatured ); ?>" class="stc-img-btn">Changer</button>
                        <button type="button" id="schilo-remove-thumbnail" class="stc-img-btn stc-img-btn--danger">Supprimer</button>
                    </div>
                </div>
            <?php else : ?>
                <div id="schilo-thumbnail-wrap" class="stc-thumb stc-thumb--empty">
                    <button type="button" id="schilo-set-thumbnail" data-post-id="<?php echo esc_attr( $postId ); ?>" data-nonce="<?php echo esc_attr( $nonceFeatured ); ?>" class="stc-thumb-placeholder">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                        <span>D&eacute;finir l&apos;image</span>
                    </button>
                </div>
            <?php endif; ?>
            <input type="hidden" id="schilo_thumbnail_id" name="_thumbnail_id" value="<?php echo esc_attr( $thumbnailId ? $thumbnailId : -1 ); ?>">
        </div>
    </div>

    <div class="schilo-builder-layout">
        <aside class="schilo-builder-sidebar" id="schilo-nav-sidebar">
            <div id="schilo-nav-buttons"></div>

            <hr style="margin:10px 0">

            <button type="button" class="button schilo-add-section-new" id="schilo-btn-add-section" style="width:100%;margin-bottom:6px">
                + Ajouter une section
            </button>

            <div id="schilo-add-panel" style="display:none">
                <?php if ( ! empty( $sectionTypes ) ) : ?>
                    <?php foreach ( $sectionTypes as $typeKey => $typeConfig ) : ?>
                        <button type="button"
                                class="button schilo-add-section"
                                data-type="<?php echo esc_attr( $typeKey ); ?>"
                                style="width:100%;margin-bottom:4px;text-align:left">
                            + <?php echo esc_html( isset( $typeConfig['label'] ) ? $typeConfig['label'] : $typeKey ); ?>
                        </button>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <hr style="margin:10px 0">
            <a href="<?php echo esc_url( $urlBuilderSections ); ?>"
               target="_blank" rel="noopener noreferrer"
               class="button schilo-admin-link-button schilo-admin-link-button-secondary"
               style="width:100%;text-align:center">
                Configurer les sections
            </a>
        </aside>

        <main class="schilo-builder-sections">
            <div class="schilo-builder-toolbar" id="schilo-section-toolbar">
                <button type="button" class="button schilo-collapse-all">Tout replier</button>
                <button type="button" class="button schilo-expand-all">Tout ouvrir</button>
                <span id="schilo-nav-info" style="margin-left:auto;font-size:12px;color:#6b7280"></span>
            </div>

            <div id="schilo-sections-list">
                <?php foreach ( $sections as $index => $section ) : ?>
                    <?php include SCHILO_BUILDER_PATH . 'views/admin/partials/section-item.php'; ?>
                <?php endforeach; ?>
            </div>
        </main>
    </div>
</div>