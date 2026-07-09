<?php
if (!defined('ABSPATH')) exit;

use Schilo\Builder\Service\ClassementService;

$service  = new ClassementService();
$base_url = admin_url('admin.php?page=schilo-builder-classement');

$taxonomy_labels = [
    'schilo_parcours' => 'Parcours',
    'schilo_theme'    => 'Thèmes',
    'schilo_serie'    => 'Séries',
];

$groups_by_taxonomy = [];
$total_groups = 0;
foreach (ClassementService::TAXONOMIES as $taxonomy) {
    $groups = $service->findDuplicateGroups($taxonomy);
    $groups_by_taxonomy[$taxonomy] = $groups;
    $total_groups += count($groups);
}
?>
<div class="wrap schilo-builder-settings">
    <h1 class="scl-page-title">
        <span class="dashicons dashicons-networking" style="color:#2872d4;font-size:24px;height:24px;width:24px;vertical-align:middle;margin-right:8px;"></span>
        Parcours &amp; Thèmes — Doublons
    </h1>

    <nav class="scl-tabs">
        <a href="<?php echo esc_url($base_url); ?>" class="scl-tab">Classement</a>
        <a href="<?php echo esc_url(add_query_arg('tab', 'termes', $base_url)); ?>" class="scl-tab">Termes</a>
        <a href="<?php echo esc_url(add_query_arg('tab', 'config', $base_url)); ?>" class="scl-tab">Configuration</a>
        <a href="<?php echo esc_url(add_query_arg('tab', 'audit', $base_url)); ?>" class="scl-tab">Audit</a>
        <a href="<?php echo esc_url(add_query_arg('tab', 'doublons', $base_url)); ?>" class="scl-tab scl-tab-active">Doublons</a>
    </nav>

    <div class="scl-val-bloc" style="display:block;">
        <p style="color:#64748b;font-size:13px;margin:0;">
            Détecte les termes probablement en double au sein d'une même taxonomie (même nom une fois
            l'article "le/la/les" et les accents/casse ignorés — ex. « Sermon sur la montagne » et
            « Le sermon sur la montagne »). Pour les taxonomies hiérarchiques (Parcours, Thèmes), seuls
            les termes de même parent sont comparés. Choisissez le terme à conserver puis fusionnez :
            les articles des autres termes du groupe lui sont réaffectés (sans toucher à leurs autres
            classements), puis les termes en trop sont supprimés.
        </p>
    </div>

    <?php if ($total_groups === 0) : ?>
        <div class="notice notice-success" style="padding:10px 12px;margin-top:16px;">
            <p style="margin:0;"><strong>Aucun doublon détecté</strong> dans les 3 taxonomies.</p>
        </div>
    <?php endif; ?>

    <?php foreach ($groups_by_taxonomy as $taxonomy => $groups) :
        if (empty($groups)) continue;
    ?>
        <div class="scl-val-bloc" style="display:block;margin-top:16px;">
            <div class="scl-val-bloc-title"><?php echo esc_html($taxonomy_labels[$taxonomy] ?? $taxonomy); ?></div>

            <?php foreach ($groups as $gi => $group) : ?>
                <div class="scl-dup-group" data-taxonomy="<?php echo esc_attr($taxonomy); ?>" style="border:1px solid #e2e8f0;border-radius:8px;padding:12px 16px;margin-bottom:12px;">
                    <table class="widefat scl-table" style="margin-bottom:8px;">
                        <thead><tr>
                            <th style="width:40px;">Garder</th>
                            <th>Terme</th>
                            <th style="width:100px;">Articles</th>
                        </tr></thead>
                        <tbody>
                            <?php foreach ($group as $ti => $term) : ?>
                            <tr>
                                <td>
                                    <input type="radio" name="scl-dup-keep-<?php echo esc_attr($taxonomy . '-' . $gi); ?>"
                                           class="scl-dup-keep" value="<?php echo esc_attr($term->term_id); ?>"
                                           <?php checked($ti === 0); ?>>
                                </td>
                                <td><?php echo esc_html($term->name); ?></td>
                                <td><?php echo (int) $term->count; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <button type="button" class="button button-primary scl-dup-merge-btn">Fusionner ce groupe</button>
                    <span class="scl-dup-result" style="margin-left:8px;font-weight:600;"></span>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endforeach; ?>
</div>

<script>
(function($){
    var ajaxUrl = '<?php echo esc_js(admin_url('admin-ajax.php')); ?>';
    var nonce   = '<?php echo esc_js(wp_create_nonce('schilo_classement')); ?>';

    $(document).on('click', '.scl-dup-merge-btn', function(){
        var btn    = $(this);
        var group  = btn.closest('.scl-dup-group');
        var taxonomy = group.data('taxonomy');
        var keepRadio = group.find('.scl-dup-keep:checked');
        var result = group.find('.scl-dup-result');

        if (!keepRadio.length) {
            result.text('Choisissez le terme à conserver.').css('color', '#dc2626');
            return;
        }
        var targetId = keepRadio.val();
        var sourceIds = [];
        group.find('.scl-dup-keep').each(function(){
            if ($(this).val() !== targetId) sourceIds.push($(this).val());
        });

        btn.prop('disabled', true);
        result.text('Fusion en cours...').css('color', '#64748b');

        var totalMoved = 0;
        function mergeNext(i){
            if (i >= sourceIds.length) {
                result.text('✓ Fusionné (' + totalMoved + ' article(s) réaffecté(s)).').css('color', '#16a34a');
                group.find('table tr').not(':first').each(function(){
                    var radio = $(this).find('.scl-dup-keep');
                    if (radio.length && radio.val() !== targetId) $(this).remove();
                });
                return;
            }
            $.post(ajaxUrl, {
                action:    'schilo_classement_merge_term',
                nonce:     nonce,
                taxonomy:  taxonomy,
                source_id: sourceIds[i],
                target_id: targetId
            }, function(r){
                if (r && r.success) {
                    totalMoved += (r.data.moved || 0);
                    mergeNext(i + 1);
                } else {
                    result.text('✗ ' + ((r && r.data && r.data.message) ? r.data.message : 'Erreur')).css('color', '#dc2626');
                    btn.prop('disabled', false);
                }
            }).fail(function(){
                result.text('✗ Erreur réseau').css('color', '#dc2626');
                btn.prop('disabled', false);
            });
        }
        mergeNext(0);
    });
})(jQuery);
</script>
