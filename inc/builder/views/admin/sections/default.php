<?php
/** Variables : $section, $index, $data */
$editorId = 'schilo_section_editor_' . (int) $index . '_' . md5($section->getType() . '_' . $index);
?>

<div class="schilo-form-grid">
    <div class="schilo-field-row">
        <label>
            <strong>Titre</strong>
            <input type="text"
                   class="widefat schilo-title-input"
                   name="schilo_sections[<?php echo esc_attr($index); ?>][title]"
                   value="<?php echo esc_attr($section->getTitle()); ?>">
        </label>
    </div>

    <div class="schilo-field-row">
        <label>
            <strong>Classe CSS personnalisée</strong>
            <input type="text"
                   class="widefat"
                   name="schilo_sections[<?php echo esc_attr($index); ?>][custom_class]"
                   value="<?php echo esc_attr($section->getCustomClass()); ?>"
                   placeholder="ex: bloc-important">
        </label>
    </div>
</div>

<p class="description schilo-auto-class">
    Classe automatique : <code>schilo-section-<?php echo esc_html($section->getType()); ?></code>
    <?php if (!empty($prefix)) : ?>
        &nbsp;&mdash;&nbsp;Classe spécifique au template : <code>schilo-section-<?php echo esc_html($section->getType()); ?>-<?php echo esc_html(sanitize_html_class(strtolower($prefix))); ?></code>
    <?php endif; ?>
</p>

<div class="schilo-editor-wrapper">
    <strong>Contenu</strong>
    <?php
    wp_editor(
        $section->getContent(),
        $editorId,
        array(
            'textarea_name' => 'schilo_sections[' . (int) $index . '][content]',
            'textarea_rows' => 30,
            'media_buttons' => false,
            'teeny' => false,
            'quicktags' => true,
            'tinymce' => array(
                'toolbar1' => 'schilo_h1,schilo_h2,schilo_h3,schilo_h4,schilo_h5,schilo_h6,schilo_p,bold,italic,underline,bullist,numlist,blockquote,alignleft,aligncenter,alignright,link,unlink,pastetext,undo,redo',
                'toolbar2' => 'schilo_shortcodes',
                'resize'   => true,
                'height'   => 400,
            ),
        )
    );
    ?>
</div>
