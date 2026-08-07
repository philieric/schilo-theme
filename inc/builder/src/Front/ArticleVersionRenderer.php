<?php

namespace Schilo\Builder\Front;

use Schilo\Builder\Service\ArticleVersionService;

class ArticleVersionRenderer
{
    public function register()
    {
        add_action('pre_get_posts', array($this, 'excludeNonPrimaryFromListings'));
        add_action('wp_ajax_schilo_switch_version', array($this, 'ajaxSwitchVersion'));
        add_action('wp_ajax_nopriv_schilo_switch_version', array($this, 'ajaxSwitchVersion'));
        add_action('wp_enqueue_scripts', array($this, 'enqueueAssets'));
    }

    /**
     * Seul l'article primaire d'un groupe de versions apparait dans les
     * archives/recherche ; les autres restent accessibles par URL directe.
     */
    public function excludeNonPrimaryFromListings(\WP_Query $query)
    {
        if (is_admin() || !$query->is_main_query()) {
            return;
        }

        if (!$query->is_archive() && !$query->is_search() && !$query->is_home()) {
            return;
        }

        $excluded = (new ArticleVersionService())->getExcludedFromListingsIds();

        if (empty($excluded)) {
            return;
        }

        $existing = $query->get('post__not_in');
        $existing = is_array($existing) ? $existing : array();

        $query->set('post__not_in', array_values(array_unique(array_merge($existing, $excluded))));
    }

    public function enqueueAssets()
    {
        if (!is_singular('post')) {
            return;
        }

        $service = new ArticleVersionService();

        if (!$service->isEnabled(get_the_ID())) {
            return;
        }

        wp_enqueue_script(
            'schilo-article-version',
            SCHILO_BUILDER_URL . 'assets/front/article-version.js',
            array(),
            SCHILO_BUILDER_VERSION,
            true
        );

        wp_localize_script('schilo-article-version', 'schiloVersionData', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('schilo_switch_version'),
        ));
    }

    /**
     * Retourne le titre, l'extrait et le contenu rendu de l'article cible,
     * pour un remplacement en AJAX sans rechargement de page. Le reste de la
     * mise en page (hero, sidebar) n'est pas régénéré — seuls titre et corps
     * de l'article changent réellement d'une version à l'autre.
     */
    public function ajaxSwitchVersion()
    {
        check_ajax_referer('schilo_switch_version', 'nonce');

        $fromId = isset($_POST['from_id']) ? (int) $_POST['from_id'] : 0;
        $toId   = isset($_POST['to_id']) ? (int) $_POST['to_id'] : 0;

        $service = new ArticleVersionService();

        if ($fromId <= 0 || $toId <= 0 || !in_array($toId, $service->getGroupIds($fromId), true)) {
            wp_send_json_error(array('message' => "Version introuvable ou non liée à cet article."), 400);
        }

        $target = get_post($toId);
        if (!$target || $target->post_status !== 'publish') {
            wp_send_json_error(array('message' => 'Article cible indisponible.'), 404);
        }

        global $post;
        $originalPost = $post;
        $post = $target; // phpcs:ignore
        setup_postdata($post);

        $rawTitle = get_post_field('post_title', $toId);
        $cleanTitle = $rawTitle;
        if (preg_match('/^([A-Z]+\d+)\s*[\x{2013}\x{2014}\-]+\s*/u', $rawTitle, $m)) {
            $cleanTitle = preg_replace('/^[A-Z]+\d+\s*[\x{2013}\x{2014}\-]+\s*/u', '', $rawTitle);
        }

        // Rendu direct des sections (pas the_content()) : le filtre standard
        // de ContentRenderer se protège avec is_singular()/in_the_loop(), qui
        // ne sont pas vrais dans ce contexte AJAX même après le swap manuel
        // du post global ci-dessus. Ce sont ces sections deja assemblees qui
        // remplacent integralement le contenu sur un chargement normal, donc
        // rejouer les filtres the_content generiques ici n'apporterait rien.
        $content = (new \Schilo\Builder\Front\ContentRenderer())->renderPostContent($toId, $target->post_content);

        $switcher = $this->buildSwitcherData($toId);

        wp_reset_postdata();
        $post = $originalPost; // phpcs:ignore

        wp_send_json_success(array(
            'title'      => esc_html($cleanTitle),
            'excerpt'    => esc_html(get_the_excerpt($toId)),
            'content'    => $content,
            'permalink'  => get_permalink($toId),
            'postId'     => $toId,
            'switcher'   => $switcher,
        ));
    }

    /**
     * Données du switcher (une entrée par version du groupe) pour un post
     * donné, utilisées à la fois par single.php et par la réponse AJAX (pour
     * mettre à jour l'état actif après un switch).
     */
    public function buildSwitcherData($currentPostId)
    {
        $service = new ArticleVersionService();
        $groupIds = $service->getGroupIds($currentPostId);

        if (count($groupIds) < 2) {
            return array();
        }

        // Ordre stable (par ID croissant), indépendant de l'article "courant" :
        // sinon la position des pastilles change à chaque switch (le post
        // courant passait toujours en tête), ce qui est déroutant côté
        // utilisateur (les boutons semblent s'inverser à chaque clic).
        sort($groupIds);

        $items = array();
        foreach ($groupIds as $id) {
            $label = $service->getLabel($id);
            $items[] = array(
                'id'        => $id,
                'label'     => $label !== '' ? $label : ('Version ' . $id),
                'permalink' => get_permalink($id),
                'isCurrent' => ((int) $id === (int) $currentPostId),
                'isPrimary' => $service->isPrimary($id),
            );
        }

        return $items;
    }
}
