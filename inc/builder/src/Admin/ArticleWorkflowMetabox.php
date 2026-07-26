<?php

namespace Schilo\Builder\Admin;

use Schilo\Builder\Service\IndexationService;
use Schilo\Builder\Service\ClassementService;

/**
 * Meta box "Indexation & Classement" dans la colonne latérale de l'écran
 * d'édition d'article (post.php et post-new.php). Ne réimplémente aucune
 * logique : réutilise les AJAX déjà enregistrés par IndexationPage et
 * ClassementPage (schilo_ia_index_article, schilo_classement_classify),
 * et renvoie vers les pages de validation existantes pour l'édition
 * manuelle complète.
 */
class ArticleWorkflowMetabox
{
    public function register()
    {
        add_action('add_meta_boxes', array($this, 'addBox'));
        add_action('admin_enqueue_scripts', array($this, 'enqueueAssets'));
    }

    public function addBox()
    {
        add_meta_box(
            'schilo_workflow',
            'Indexation & Classement',
            array($this, 'render'),
            'post',
            'side',
            'high'
        );
    }

    public function enqueueAssets($hook)
    {
        if (!in_array($hook, array('post.php', 'post-new.php'), true)) {
            return;
        }

        global $post;
        $postId = $post ? (int) $post->ID : 0;

        wp_enqueue_style(
            'schilo-builder-admin',
            SCHILO_BUILDER_URL . 'assets/admin/builder-admin.css',
            array(),
            SCHILO_BUILDER_VERSION
        );
        wp_enqueue_style(
            'schilo-indexation-admin',
            SCHILO_BUILDER_URL . 'assets/admin/indexation-admin.css',
            array(),
            SCHILO_BUILDER_VERSION
        );
        wp_enqueue_style(
            'schilo-classement-admin',
            SCHILO_BUILDER_URL . 'assets/admin/classement-admin.css',
            array(),
            SCHILO_BUILDER_VERSION
        );

        wp_enqueue_script(
            'schilo-workflow-metabox',
            SCHILO_BUILDER_URL . 'assets/admin/workflow-metabox.js',
            array('jquery'),
            SCHILO_BUILDER_VERSION,
            true
        );

        wp_localize_script('schilo-workflow-metabox', 'schiloWorkflow', array(
            'ajaxUrl'          => admin_url('admin-ajax.php'),
            'postId'           => $postId,
            'indexationNonce'  => wp_create_nonce('schilo_indexation'),
            'classementNonce'  => wp_create_nonce('schilo_classement'),
            'provider'         => $this->defaultProvider(),
            'indexationUrl'    => admin_url('admin.php?page=schilo-builder-indexation&tab=validation&post_id=' . $postId),
            'classementUrl'    => admin_url('admin.php?page=schilo-builder-classement&tab=validation&post_id=' . $postId),
        ));
    }

    private function defaultProvider()
    {
        $config = get_option('schilo_ia_config', array());
        return isset($config['default_provider']) ? sanitize_key($config['default_provider']) : 'claude';
    }

    public function render($post)
    {
        $postId = (int) $post->ID;

        $indexationService = new IndexationService();
        $classementService = new ClassementService();

        $indexationRecord = $postId ? $indexationService->getByPostId($postId) : null;
        // Classement partage la meme table (colonne statut_classement) —
        // getByPostId() de l'un ou l'autre service renvoie la meme ligne,
        // on relit via ClassementService pour rester explicite sur l'origine.
        $classementRecord = $postId ? $classementService->getByPostId($postId) : null;

        // Noms de termes actuels (schilo_theme/parcours/serie), pour pre-remplir
        // la zone manuelle compacte — getByPostId() ne donne que le statut, pas
        // les termes reels (relation many-to-many separee).
        $classementTerms = array('schilo_theme' => '', 'schilo_parcours' => '', 'schilo_serie' => '');
        if ($postId) {
            $indexedTerms = $classementService->getIndexedTermsForPost($postId);
            foreach ($classementTerms as $taxonomy => &$namesJoined) {
                $terms = $indexedTerms[$taxonomy] ?? array();
                $namesJoined = implode(', ', wp_list_pluck($terms, 'name'));
            }
            unset($namesJoined);
        }

        include SCHILO_BUILDER_PATH . 'views/admin/metabox-workflow.php';
    }
}
