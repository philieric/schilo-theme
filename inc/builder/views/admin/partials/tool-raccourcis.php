<?php if (!defined('ABSPATH')) exit; ?>
<div class="schilo-tool-card">
    <h2>
        <span class="dashicons dashicons-editor-code" style="vertical-align:middle;margin-right:6px;"></span>
        Raccourcis Live WP
    </h2>
    <p>
        Configurez les raccourcis clavier dans l'éditeur TinyMCE (classique).
        Tapez un <strong>token</strong> suivi d'un espace, tabulation ou entrée → le snippet est inséré.
        <code>placeCaret: between</code> place le curseur entre les balises ouvrante et fermante.
    </p>

    <?php if (!empty($result_raccourcis)) : ?>
    <div class="notice notice-<?php echo $result_raccourcis['type'] === 'error' ? 'error' : 'success'; ?> inline" style="margin:0 0 16px;">
        <p><?php echo esc_html($result_raccourcis['message']); ?></p>
    </div>
    <?php endif; ?>

    <form method="post" id="schilo-raccourcis-form">
        <?php wp_nonce_field('schilo_save_raccourcis', 'schilo_raccourcis_nonce'); ?>
        <input type="hidden" name="schilo_tool_action" value="save_raccourcis">

        <table class="widefat fixed" id="schilo-raccourcis-table" style="max-width:900px;margin-bottom:12px;">
            <thead>
                <tr>
                    <th style="width:120px;">Token</th>
                    <th>Snippet inséré</th>
                    <th style="width:150px;">Placement curseur</th>
                    <th style="width:80px;">Inclure dans TinyMCE</th>
                    <th style="width:150px;">Libellé du bouton</th>
                    <th style="width:60px;"></th>
                </tr>
            </thead>
            <tbody id="schilo-raccourcis-rows">
                <?php foreach ($raccourcis_map as $i => $entry) : ?>
                <tr>
                    <td>
                        <input type="text" name="raccourcis_map[<?php echo (int) $i; ?>][token]"
                               value="<?php echo esc_attr($entry['token']); ?>"
                               style="width:100%;font-family:monospace;" placeholder=";token">
                    </td>
                    <td>
                        <input type="text" name="raccourcis_map[<?php echo (int) $i; ?>][snippet]"
                               value="<?php echo esc_attr($entry['snippet']); ?>"
                               style="width:100%;font-family:monospace;" placeholder="[balise][/balise]">
                    </td>
                    <td>
                        <select name="raccourcis_map[<?php echo (int) $i; ?>][place_caret]" style="width:100%;">
                            <option value="none"   <?php selected($entry['place_caret'], 'none'); ?>>Aucun</option>
                            <option value="between"<?php selected($entry['place_caret'], 'between'); ?>>Entre les balises</option>
                        </select>
                    </td>
                    <td style="text-align:center;">
                        <input type="checkbox" name="raccourcis_map[<?php echo (int) $i; ?>][in_tinymce]" value="1"
                               <?php checked(!empty($entry['in_tinymce'])); ?>>
                    </td>
                    <td>
                        <input type="text" name="raccourcis_map[<?php echo (int) $i; ?>][label]"
                               value="<?php echo esc_attr($entry['label'] ?? ''); ?>"
                               style="width:100%;" placeholder="Libellé affiché dans le menu">
                    </td>
                    <td style="text-align:center;">
                        <button type="button" class="button schilo-remove-raccourci" title="Supprimer">✕</button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <p>
            <button type="button" id="schilo-add-raccourci" class="button">+ Ajouter un raccourci</button>
            &nbsp;
            <?php submit_button('Enregistrer', 'primary', 'schilo_raccourcis_submit', false); ?>
        </p>
    </form>
</div>

<script>
(function($){
    var idx = <?php echo count($raccourcis_map); ?>;

    $('#schilo-add-raccourci').on('click', function(){
        var row = '<tr>' +
            '<td><input type="text" name="raccourcis_map[' + idx + '][token]" style="width:100%;font-family:monospace;" placeholder=";token"></td>' +
            '<td><input type="text" name="raccourcis_map[' + idx + '][snippet]" style="width:100%;font-family:monospace;" placeholder="[balise][/balise]"></td>' +
            '<td><select name="raccourcis_map[' + idx + '][place_caret]" style="width:100%;">' +
                '<option value="none">Aucun</option>' +
                '<option value="between">Entre les balises</option>' +
            '</select></td>' +
            '<td style="text-align:center;"><input type="checkbox" name="raccourcis_map[' + idx + '][in_tinymce]" value="1"></td>' +
            '<td><input type="text" name="raccourcis_map[' + idx + '][label]" style="width:100%;" placeholder="Libellé affiché dans le menu"></td>' +
            '<td style="text-align:center;"><button type="button" class="button schilo-remove-raccourci" title="Supprimer">✕</button></td>' +
        '</tr>';
        $('#schilo-raccourcis-rows').append(row);
        idx++;
    });

    $(document).on('click', '.schilo-remove-raccourci', function(){
        $(this).closest('tr').remove();
    });
})(jQuery);
</script>
