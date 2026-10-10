<?php
/** @var array<int, array{post_id:int,type:string,type_label:string,note:string,status:string,status_label:string,title:string,exists:bool,edit_url:string,view_url:string,user:string,created_at:string}> $reports */
/** @var array<string, string> $typeLabels */
/** @var array<string, int> $typeCounts */
/** @var string $typeFilter */
/** @var array<string, string> $statusLabels */
/** @var array<string, int> $statusCounts */
/** @var string $statusFilter */
/** @var string $pageUrl */
/** @var int $total */
/** @var bool $removed */
/** @var bool $statusError */
/** @var string $statusUpdated */

use Schilo\Builder\Admin\ArticleReportsPage;
use Schilo\Builder\Service\ArticleReportService;

// Chaque filtre conserve l'autre : on peut croiser type et statut.
$typeUrl = static function (string $type) use ($pageUrl, $statusFilter): string {
    $args = array_filter(array('type_filter' => $type, 'status_filter' => $statusFilter));

    return add_query_arg($args, $pageUrl);
};
$statusUrl = static function (string $status) use ($pageUrl, $typeFilter): string {
    $args = array_filter(array('type_filter' => $typeFilter, 'status_filter' => $status));

    return add_query_arg($args, $pageUrl);
};
$filtered = $typeFilter !== '' || $statusFilter !== '';
?>
<div class="wrap schilo-builder-settings">
    <h1>Schilo Builder — Articles signalés</h1>
    <p class="schilo-admin-back"><a href="<?php echo esc_url(admin_url('admin.php?page=schilo-builder')); ?>" class="button">← Retour au tableau de bord</a></p>

    <?php if ($removed) : ?>
        <div class="updated schilo-keep"><p>Signalement retiré.</p></div>
    <?php endif; ?>
    <?php if ($statusUpdated !== '') : ?>
        <div class="updated schilo-keep"><p>Statut du signalement mis à jour : <?php echo esc_html($statusLabels[$statusUpdated]); ?>.</p></div>
    <?php endif; ?>
    <?php if ($statusError) : ?>
        <div class="error schilo-keep"><p>Le statut n’a pas pu être modifié : signalement introuvable ou statut invalide.</p></div>
    <?php endif; ?>

    <div class="schilo-settings-card">
        <h2><?php echo esc_html(sprintf(_n('%d signalement', '%d signalements', count($reports), 'schilo'), count($reports))); ?></h2>
        <p class="description">Problèmes marqués depuis le site par un administrateur (boutons « Signaler » dans l’en-tête de l’article) : versets à revoir, ou migration à reprendre. Un même article peut porter les deux, chacun avec une note facultative. Une fois le problème corrigé, passez le signalement « en attente de vérification » ; retirez-le seulement après avoir vérifié la correction.</p>

        <ul class="subsubsub" style="float:none;margin:12px 0 4px;">
            <li>
                <span>Type :</span>
                <a href="<?php echo esc_url($typeUrl('')); ?>"<?php echo $typeFilter === '' ? ' class="current" aria-current="page"' : ''; ?>>Tous <span class="count">(<?php echo esc_html((string) $total); ?>)</span></a> |
            </li>
            <?php $lastType = array_key_last($typeLabels); ?>
            <?php foreach ($typeLabels as $type => $label) : ?>
                <li>
                    <a href="<?php echo esc_url($typeUrl($type)); ?>"<?php echo $typeFilter === $type ? ' class="current" aria-current="page"' : ''; ?>><?php echo esc_html($label); ?> <span class="count">(<?php echo esc_html((string) $typeCounts[$type]); ?>)</span></a><?php echo $type !== $lastType ? ' |' : ''; ?>
                </li>
            <?php endforeach; ?>
        </ul>

        <ul class="subsubsub" style="float:none;margin:4px 0 12px;">
            <li>
                <span>Statut :</span>
                <a href="<?php echo esc_url($statusUrl('')); ?>"<?php echo $statusFilter === '' ? ' class="current" aria-current="page"' : ''; ?>>Tous <span class="count">(<?php echo esc_html((string) $total); ?>)</span></a> |
            </li>
            <?php $lastStatus = array_key_last($statusLabels); ?>
            <?php foreach ($statusLabels as $status => $label) : ?>
                <li>
                    <a href="<?php echo esc_url($statusUrl($status)); ?>"<?php echo $statusFilter === $status ? ' class="current" aria-current="page"' : ''; ?>><?php echo esc_html($label); ?> <span class="count">(<?php echo esc_html((string) $statusCounts[$status]); ?>)</span></a><?php echo $status !== $lastStatus ? ' |' : ''; ?>
                </li>
            <?php endforeach; ?>
        </ul>

        <?php if (empty($reports)) : ?>
            <p>Aucun signalement<?php echo $filtered ? ' pour ce filtre' : ''; ?> pour le moment.</p>
        <?php else : ?>
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th scope="col">ID</th>
                        <th scope="col">Article</th>
                        <th scope="col">Type</th>
                        <th scope="col">Statut</th>
                        <th scope="col">Note</th>
                        <th scope="col">Signalé par</th>
                        <th scope="col">Date</th>
                        <th scope="col">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($reports as $report) : ?>
                        <?php
                        $isFixed    = $report['status'] === ArticleReportService::STATUS_FIXED;
                        $nextStatus = $isFixed ? ArticleReportService::STATUS_OPEN : ArticleReportService::STATUS_FIXED;
                        ?>
                        <tr>
                            <td><?php echo esc_html((string) $report['post_id']); ?></td>
                            <td>
                                <?php if ($report['exists']) : ?>
                                    <strong><?php echo esc_html($report['title']); ?></strong>
                                <?php else : ?>
                                    <em>Article supprimé</em>
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html($report['type_label']); ?></td>
                            <td>
                                <span style="display:inline-block;padding:2px 8px;border-radius:20px;font-size:12px;font-weight:600;<?php echo $isFixed ? 'background:#dcfce7;color:#166534;' : 'background:#fef3c7;color:#92400e;'; ?>">
                                    <?php echo esc_html($report['status_label']); ?>
                                </span>
                            </td>
                            <td><?php echo esc_html($report['note'] !== '' ? $report['note'] : '—'); ?></td>
                            <td><?php echo esc_html($report['user'] !== '' ? $report['user'] : '—'); ?></td>
                            <td><?php echo esc_html(mysql2date('d/m/Y H:i', $report['created_at'])); ?></td>
                            <td>
                                <?php if ($report['exists']) : ?>
                                    <a href="<?php echo esc_url($report['edit_url']); ?>" class="button button-small">Modifier</a>
                                    <a href="<?php echo esc_url($report['view_url']); ?>" class="button button-small" target="_blank" rel="noopener noreferrer">Voir</a>
                                <?php endif; ?>
                                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline">
                                    <input type="hidden" name="action" value="<?php echo esc_attr(ArticleReportsPage::STATUS_ACTION); ?>">
                                    <input type="hidden" name="post_id" value="<?php echo esc_attr((string) $report['post_id']); ?>">
                                    <input type="hidden" name="type" value="<?php echo esc_attr($report['type']); ?>">
                                    <input type="hidden" name="status" value="<?php echo esc_attr($nextStatus); ?>">
                                    <input type="hidden" name="type_filter" value="<?php echo esc_attr($typeFilter); ?>">
                                    <input type="hidden" name="status_filter" value="<?php echo esc_attr($statusFilter); ?>">
                                    <?php wp_nonce_field(ArticleReportsPage::STATUS_ACTION); ?>
                                    <button type="submit" class="button button-small"><?php echo $isFixed ? 'Rouvrir (à corriger)' : 'Marquer corrigé (à vérifier)'; ?></button>
                                </form>
                                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline">
                                    <input type="hidden" name="action" value="<?php echo esc_attr(ArticleReportsPage::REMOVE_ACTION); ?>">
                                    <input type="hidden" name="post_id" value="<?php echo esc_attr((string) $report['post_id']); ?>">
                                    <input type="hidden" name="type" value="<?php echo esc_attr($report['type']); ?>">
                                    <input type="hidden" name="type_filter" value="<?php echo esc_attr($typeFilter); ?>">
                                    <input type="hidden" name="status_filter" value="<?php echo esc_attr($statusFilter); ?>">
                                    <?php wp_nonce_field(ArticleReportsPage::REMOVE_ACTION); ?>
                                    <button type="submit" class="button button-small">Retirer le signalement</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>
