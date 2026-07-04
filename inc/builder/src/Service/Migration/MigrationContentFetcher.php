<?php

namespace Schilo\Builder\Service\Migration;

/**
 * Récupère le rendu HTML complet d'un article (post_content après application
 * des filtres standards WordPress : shortcodes, wpautop, filtres tiers...),
 * exactement comme il serait affiché sur le site.
 *
 * C'est la brique commune utilisée par tous les extracteurs de migration,
 * car certains éléments (ex: le titre Wikilogy) ne sont visibles qu'après
 * exécution du shortcode et ne sont pas exploitables directement depuis
 * le post_content brut stocké en base.
 */
class MigrationContentFetcher
{
    /**
     * @var array<int, string> Cache mémoire par post ID, pour éviter de relancer
     *                         plusieurs fois le rendu pour un même article au
     *                         cours d'une même requête.
     */
    private $cache = array();

    /**
     * Retourne le HTML rendu de l'article (équivalent à ce que `the_content()`
     * afficherait sur la page de l'article).
     */
    public function getRenderedContent($postId)
    {
        $postId = (int) $postId;

        if ($postId <= 0) {
            return '';
        }

        if (isset($this->cache[$postId])) {
            return $this->cache[$postId];
        }

        $post = get_post($postId);

        if (!$post) {
            return '';
        }

        $rendered = $this->renderPostContent($post);

        $this->cache[$postId] = $rendered;

        return $rendered;
    }

    /**
     * Vide le cache mémoire (utile si l'article a été modifié entre deux appels
     * au cours de la même requête, par exemple après un enregistrement).
     */
    public function clearCache($postId = null)
    {
        if ($postId === null) {
            $this->cache = array();
            return;
        }

        unset($this->cache[(int) $postId]);
    }

    private function renderPostContent(\WP_Post $post)
    {
        // Certains shortcodes/plugins tiers dépendent du contexte global $post
        // et des fonctions de boucle WordPress (get_the_ID, get_the_title...).
        // On bascule donc temporairement le contexte global le temps du rendu,
        // pour que le shortcode Wikilogy (et d'autres) se comporte exactement
        // comme lors d'un affichage normal de la page.
        $previousPost = isset($GLOBALS['post']) ? $GLOBALS['post'] : null;

        $GLOBALS['post'] = $post;
        setup_postdata($post);

        $content = $post->post_content;

        /**
         * Filtre 'the_content' : exécute les shortcodes (dont ceux de Wikilogy/
         * WPBakery), wpautop, embeds, etc. — exactement le pipeline utilisé par
         * le thème pour afficher l'article.
         */
        $rendered = apply_filters('the_content', $content);

        // Restauration du contexte global pour ne pas perturber le reste
        // de la requête admin (listing d'articles, autres rendus, etc.).
        if ($previousPost instanceof \WP_Post) {
            $GLOBALS['post'] = $previousPost;
            setup_postdata($previousPost);
        } else {
            unset($GLOBALS['post']);
            wp_reset_postdata();
        }

        return (string) $rendered;
    }
}
