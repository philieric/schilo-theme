<?php

namespace Schilo\Builder\Front;

use Schilo\Builder\Service\AnnexeService;
use Schilo\Builder\Service\ContentFilter;

class AnnexeRenderer
{
    public function register(): void
    {
        // Pas de is_main_query() : couvre aussi les requetes secondaires (pages
        // taxonomies schilo_*, sitemap, [schilo_articles_lies]...), pas seulement
        // la boucle principale. is_admin() protege les recherches d'articles a
        // lier depuis l'editeur (AJAX schilo_search_version_articles).
        add_action('pre_get_posts', array($this, 'excludeFromListings'));
    }

    public function excludeFromListings(\WP_Query $query): void
    {
        if (is_admin()) {
            return;
        }

        $excluded = (new AnnexeService())->getAllAnxPostIds();
        if (empty($excluded)) {
            return;
        }

        $existing = $query->get('post__not_in');
        $existing = is_array($existing) ? $existing : array();
        $query->set('post__not_in', array_values(array_unique(array_merge($existing, $excluded))));
    }

    /**
     * Affiche, en fin d'article, la liste des annexes liees (boutons ouvrant
     * chacun une popup) — appele directement depuis single.php.
     */
    public function renderAnnexeBlock(int $postId): void
    {
        $service = new AnnexeService();
        if (!$service->isEnabled($postId)) {
            return;
        }

        $linked = array();
        foreach ($service->getLinkedIds($postId) as $linkedId) {
            $post = get_post($linkedId);
            if ($post && $post->post_status === 'publish') {
                $linked[] = $post;
            }
        }

        if (empty($linked)) {
            return;
        }
        ?>
        <section class="schilo-annexe-block" aria-labelledby="schilo-annexe-block-title">
            <h2 class="schilo-annexe-block__title" id="schilo-annexe-block-title">Annexes</h2>
            <div class="schilo-annexe-list">
                <?php foreach ($linked as $annexe) :
                    $modalId = 'schilo-annexe-modal-' . $annexe->ID;
                    $code = $this->extractCode($annexe->post_title);
                    $title = $this->titleWithoutPrefix($annexe->post_title);
                ?>
                    <button type="button" class="schilo-annexe-item"
                            data-schilo-definition-open="<?php echo esc_attr($modalId); ?>"
                            aria-haspopup="dialog" aria-expanded="false" aria-controls="<?php echo esc_attr($modalId); ?>">
                        <?php if ($code !== '') : ?>
                            <span class="schilo-annexe-item__code"><?php echo esc_html($code); ?></span>
                        <?php endif; ?>
                        <span class="schilo-annexe-item__title"><?php echo esc_html($title); ?></span>
                        <i class="ti ti-chevron-right" aria-hidden="true"></i>
                    </button>
                <?php endforeach; ?>
            </div>
        </section>
        <?php
        foreach ($linked as $annexe) {
            $this->renderModal($annexe);
        }
    }

    private function renderModal(\WP_Post $annexe): void
    {
        $modalId = 'schilo-annexe-modal-' . $annexe->ID;
        $code = $this->extractCode($annexe->post_title);
        $title = $this->titleWithoutPrefix($annexe->post_title);
        $body = $this->getBody($annexe);
        ?>
        <div class="schilo-definition-modal" id="<?php echo esc_attr($modalId); ?>" aria-hidden="true">
            <div class="schilo-definition-modal__overlay" data-schilo-definition-close></div>
            <section class="schilo-definition-modal__panel" role="dialog" aria-modal="true" aria-labelledby="<?php echo esc_attr($modalId); ?>-title" tabindex="-1">
                <header class="schilo-definition-modal__header">
                    <?php if ($code !== '') : ?>
                        <span class="schilo-definition-modal__code"><?php echo esc_html($code); ?></span>
                    <?php endif; ?>
                    <div>
                        <span class="schilo-definition-modal__eyebrow">Annexe</span>
                        <h2 id="<?php echo esc_attr($modalId); ?>-title"><?php echo esc_html($title); ?></h2>
                    </div>
                    <button type="button" class="schilo-definition-modal__close" data-schilo-definition-close aria-label="<?php esc_attr_e('Fermer', 'schilo'); ?>">&times;</button>
                </header>
                <div class="schilo-definition-modal__body"><?php echo $body; // phpcs:ignore ?></div>
                <footer class="schilo-definition-modal__footer">
                    <span>Annexe liee a cet article</span>
                    <a href="<?php echo esc_url(get_permalink($annexe)); ?>">Approfondir <i class="ti ti-arrow-right" aria-hidden="true"></i></a>
                </footer>
            </section>
        </div>
        <?php
    }

    private function getBody(\WP_Post $source): string
    {
        $sections = get_post_meta($source->ID, '_schilo_builder_sections', true);
        $contentFilter = new ContentFilter();
        $body = '';
        if (is_array($sections)) {
            foreach ($sections as $section) {
                if (empty($section['content'])) continue;
                if (!empty($section['title'])) $body .= '<h3>' . esc_html($section['title']) . '</h3>';
                $body .= $contentFilter->render($section['content']);
            }
        }
        return $body !== '' ? $body : $contentFilter->render($source->post_content);
    }

    private function extractCode(string $title): string
    {
        return preg_match('/^([A-Z]{2,4}\d+)/u', trim($title), $m) ? $m[1] : '';
    }

    private function titleWithoutPrefix(string $title): string
    {
        $title = trim($title);
        $stripped = preg_replace('/^[A-Z]{2,4}\d+\s*[-–—]\s*/u', '', $title);
        return $stripped !== '' ? $stripped : $title;
    }
}
