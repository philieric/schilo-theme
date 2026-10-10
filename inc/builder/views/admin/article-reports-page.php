<?php
/** @var array<int, array{post_id:int,type:string,type_label:string,title:string,exists:bool,edit_url:string,view_url:string,user:string,created_at:string}> $reports */
/** @var array<string, string> $typeLabels */
/** @var array<string, int> $typeCounts */
/** @var string $typeFilter */
/** @var string $pageUrl */
/** @var int $total */
/** @var bool $removed */

use Schilo\Builder\Admin\ArticleReportsPage;
?>
<div class="wrap schilo-builder-settings">
    <h1>Schilo Builder — Articles signalés</h1>
    <p class="schilo-admin-back"><a href="<?php echo esc_url(admin_url('admin.php?page=schilo-builder')); ?>" class="button">← Retour au tableau de bord</a></p>

    <?php if ($removed) : ?>
        <div class="updated schilo-keep"><p>Signalement retiré.</p></div>
    <?php endif; ?>

    <div class="schilo-settings-card">
        <h2><?php echo esc_html(sprintf(_n('%d signalement', '%d signalements', count($reports), 'schilo'), count($reports))); ?></h2>
        <p class="description">Problèmes marqués depuis le site par un administrateur (boutons « Signaler » dans l’en-tête de l’article) : versets à revoir, ou migration à reprendre. Un même article peut porter les deux. Retirez le signalement une fois le problème corrigé.</p>

        <ul class="subsubsub" style="float:none;margin:12px 0;">
            <li>
                <a href="<?php echo esc_url($pageUrl); ?>"<?php echo $typeFilter === '' ? ' class="current" aria-current="page"' : ''; ?>>Tous <span class="count">(<?php echo esc_html((string) $total); ?>)</span></a> |
            </li>
            <?php $lastType = array_key_last($typeLabels); ?>
            <?php foreach ($typeLabels as $type => $label) : ?>
                <li>
                    <a href="<?php echo esc_url(add_query_arg('type_filter', $type, $pageUrl)); ?>"<?php echo $typeFilter === $type ? ' class="current" aria-current="page"' : ''; ?>><?php echo esc_html($label); ?> <span class="count">(<?php echo esc_html((string) $typeCounts[$type]); ?>)</span></a><?php echo $type !== $lastType ? ' |' : ''; ?>
                </li>
            <?php endforeach; ?>
        </ul>

        <?php if (empty($reports)) : ?>
            <p>Aucun signalement<?php echo $typeFilter !== '' ? ' de ce type' : ''; ?> pour le moment.</p>
        <?php else : ?>
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th scope="col">ID</th>
                        <th scope="col">Article</th>
                        <th scope="col">Type</th>
                        <th scope="col">Signalé par</th>
                        <th scope="col">Date</th>
                        <th scope="col">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($reports as $report) : ?>
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
                            <td><?php echo esc_html($report['user'] !== '' ? $report['user'] : '—'); ?></td>
                            <td><?php echo esc_html(mysql2date('d/m/Y H:i', $report['created_at'])); ?></td>
                            <td>
                                <?php if ($report['exists']) : ?>
                                    <a href="<?php echo esc_url($report['edit_url']); ?>" class="button button-small">Modifier</a>
                                    <a href="<?php echo esc_url($report['view_url']); ?>" class="button button-small" target="_blank" rel="noopener noreferrer">Voir</a>
                                <?php endif; ?>
                                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline">
                                    <input type="hidden" name="action" value="<?php echo esc_attr(ArticleReportsPage::REMOVE_ACTION); ?>">
                                    <input type="hidden" name="post_id" value="<?php echo esc_attr((string) $report['post_id']); ?>">
                                    <input type="hidden" name="type" value="<?php echo esc_attr($report['type']); ?>">
                                    <input type="hidden" name="type_filter" value="<?php echo esc_attr($typeFilter); ?>">
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
