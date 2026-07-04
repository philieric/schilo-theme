<?php
/** @var int $prefixCount */
/** @var int $activeSectionCount */
/** @var int $sectionCount */
?>

<div class="wrap schilo-builder-settings schilo-dashboard">
    <h1>Schilo Builder</h1>
    <p class="schilo-dashboard-intro">
        Centre de configuration du builder maison&nbsp;: types, cat&eacute;gories, migration, sections et futures options.
    </p>

    <div class="schilo-dashboard-grid">
        <a class="schilo-dashboard-card" href="<?php echo esc_url(admin_url('admin.php?page=schilo-builder-prefix-categories')); ?>">
            <span class="dashicons dashicons-category"></span>
            <h2>Pr&eacute;fixes &amp; cat&eacute;gories</h2>
            <p>Associer automatiquement un pr&eacute;fixe comme <code>CTD</code> ou <code>PER</code> &agrave; une cat&eacute;gorie WordPress.</p>
            <strong><?php echo esc_html($prefixCount); ?> correspondance(s)</strong>
        </a>

        <a class="schilo-dashboard-card" href="<?php echo esc_url(admin_url('admin.php?page=schilo-builder-types')); ?>">
            <span class="dashicons dashicons-layout"></span>
            <h2>Types &amp; templates</h2>
            <p>G&eacute;rer les types comme PER, CTD, ANN, DEFAULT et leurs templates associ&eacute;s.</p>
            <strong>Disponible</strong>
        </a>

        <a class="schilo-dashboard-card" href="<?php echo esc_url(admin_url('admin.php?page=schilo-builder-sections')); ?>">
            <span class="dashicons dashicons-editor-table"></span>
            <h2>Sections</h2>
            <p>Configurer les sections disponibles, les champs, les vues et les r&egrave;gles d&rsquo;affichage.</p>
            <strong><?php echo esc_html($activeSectionCount); ?> / <?php echo esc_html($sectionCount); ?> active(s)</strong>
        </a>

        <a class="schilo-dashboard-card" href="<?php echo esc_url(admin_url('admin.php?page=schilo-builder-migration-test')); ?>">
            <span class="dashicons dashicons-migrate"></span>
            <h2>Migration WPBakery</h2>
            <p>Nouveau module en construction&nbsp;: extraction du contenu rendu et test des extracteurs par article.</p>
            <strong>En construction</strong>
        </a>

        <a class="schilo-dashboard-card" href="<?php echo esc_url(admin_url('admin.php?page=schilo-builder-indexation')); ?>">
            <span class="dashicons dashicons-search"></span>
            <h2>Indexation</h2>
            <p>Indexation IA des articles &mdash; validation humaine, export XML, connexion Claude/ChatGPT.</p>
            <strong>Disponible</strong>
        </a>

        <a class="schilo-dashboard-card" href="<?php echo esc_url(admin_url('admin.php?page=schilo-builder-outils')); ?>">
            <span class="dashicons dashicons-admin-tools"></span>
            <h2>Outils</h2>
            <p>H&eacute;ritage cat&eacute;gorie parent, maintenance des associations, nettoyage.</p>
            <strong>Disponible</strong>
        </a>

        <a class="schilo-dashboard-card" href="<?php echo esc_url(admin_url('admin.php?page=schilo-builder-sitemap')); ?>">
            <span class="dashicons dashicons-list-view"></span>
            <h2>Sitemap</h2>
            <p>Sitemap HTML par cat&eacute;gorie et sitemap XML &mdash; exclusions, fr&eacute;quences, statistiques.</p>
            <strong>Disponible</strong>
        </a>

        <a class="schilo-dashboard-card" href="<?php echo esc_url(admin_url('admin.php?page=schilo-builder-mcg')); ?>">
            <span class="dashicons dashicons-grid-view"></span>
            <h2>Grille cat&eacute;gories</h2>
            <p>Shortcode <code>[mcg_grid]</code> &mdash; grille d&rsquo;articles avec filtres, tri et pagination AJAX.</p>
            <strong>Disponible</strong>
        </a>
    </div>
</div>