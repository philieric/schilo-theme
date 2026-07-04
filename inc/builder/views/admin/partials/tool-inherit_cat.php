<?php if (!defined('ABSPATH')) exit; ?>
<div class="schilo-tool-card">
    <h2>
        <span class="dashicons dashicons-category" style="vertical-align:middle;margin-right:6px;"></span>
        Associer les articles des sous-catégories à leur catégorie parente
    </h2>
    <p>
        Pour chaque article appartenant à une <strong>sous-catégorie</strong>, ajoute automatiquement
        la <strong>catégorie parente</strong> à cet article si elle n'y est pas déjà associée.
    </p>

    <?php if (!empty($result)) : ?>
    <div class="notice notice-<?php echo $result['type'] === 'error' ? 'error' : 'success'; ?> inline" style="margin:0 0 16px;">
        <p><?php echo wp_kses_post($result['message']); ?></p>
    </div>
    <?php endif; ?>

    <?php if (empty($parent_categories)) : ?>
        <div class="notice notice-info inline" style="margin:0;">
            <p>Aucune catégorie avec des sous-catégories trouvée.</p>
        </div>
    <?php else : ?>

    <form method="post">
        <?php wp_nonce_field('schilo_inherit_parent_cat', 'schilo_inherit_nonce'); ?>
        <input type="hidden" name="schilo_tool_action" value="inherit_parent_category">

        <table class="form-table" style="max-width:600px;">
            <tr>
                <th scope="row"><label for="schilo_parent_cat_id">Catégorie parente</label></th>
                <td>
                    <select name="schilo_parent_cat_id" id="schilo_parent_cat_id" style="width:100%;max-width:400px;">
                        <option value="0">— Toutes les catégories parentes —</option>
                        <?php foreach ($parent_categories as $cat) : ?>
                            <option value="<?php echo esc_attr($cat->term_id); ?>"
                                <?php selected($selected_parent_id, $cat->term_id); ?>>
                                <?php echo esc_html($cat->name); ?>
                                (<?php echo (int) count(get_term_children($cat->term_id, 'category')); ?> sous-cat.)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th scope="row">Mode</th>
                <td>
                    <label><input type="radio" name="schilo_inherit_dry" value="1" checked>
                        <strong>Simulation</strong> — aperçu sans modification</label><br><br>
                    <label><input type="radio" name="schilo_inherit_dry" value="0">
                        <strong>Réel</strong> — applique les associations</label>
                </td>
            </tr>
        </table>

        <?php submit_button('Lancer', 'primary', 'schilo_inherit_submit', false); ?>
    </form>

    <?php endif; ?>

    <?php if (!empty($result['details'])) : ?>
    <div class="schilo-tool-result">
        <h3>Résultat</h3>
        <table class="widefat fixed striped" style="max-width:700px;">
            <thead>
                <tr>
                    <th>Catégorie parente</th>
                    <th>Sous-catégorie</th>
                    <th style="text-align:right">Articles</th>
                    <?php if (isset($result['details'][0]['updated'])) : ?>
                    <th style="text-align:right">Mis à jour</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($result['details'] as $row) : ?>
                <tr>
                    <td><?php echo esc_html($row['parent_name']); ?></td>
                    <td><?php echo esc_html($row['child_name']); ?></td>
                    <td style="text-align:right"><?php echo (int) $row['post_count']; ?></td>
                    <?php if (isset($row['updated'])) : ?>
                    <td style="text-align:right">
                        <?php if ($row['updated'] > 0) : ?>
                            <span style="color:#16a34a;font-weight:600;">+<?php echo (int) $row['updated']; ?></span>
                        <?php else : ?>
                            <span style="color:#888">déjà liés</span>
                        <?php endif; ?>
                    </td>
                    <?php endif; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr style="font-weight:700;">
                    <td colspan="2">Total</td>
                    <td style="text-align:right"><?php echo array_sum(array_column($result['details'], 'post_count')); ?></td>
                    <?php if (isset($result['details'][0]['updated'])) : ?>
                    <td style="text-align:right;color:#16a34a;">
                        +<?php echo array_sum(array_column($result['details'], 'updated')); ?>
                    </td>
                    <?php endif; ?>
                </tr>
            </tfoot>
        </table>
    </div>
    <?php endif; ?>
</div>
