<?php

namespace Schilo\Builder\Front;

use Schilo\Builder\Repository\SectionRepository;
use Schilo\Builder\Service\ArticleTypeService;
use Schilo\Builder\Service\SectionRenderer;

class ContentRenderer
{
    public function register()
    {
        add_filter('the_content', array($this, 'render'), 20);
        add_action('wp_enqueue_scripts', array($this, 'enqueueAssets'));
    }

    public function enqueueAssets()
    {
        wp_enqueue_style(
            'schilo-builder-front',
            SCHILO_BUILDER_URL . 'assets/front/builder-front.css',
            array(),
            SCHILO_BUILDER_VERSION
        );
    }

    public function render($content)
    {
        if (!is_singular('post') || !in_the_loop() || !is_main_query()) {
            return $content;
        }

        $postId = (int) get_the_ID();
        $repository = new SectionRepository();
        $sections = $repository->findByPostId($postId);

        if (empty($sections)) {
            return $content;
        }

        $prefix = (new ArticleTypeService())->resolveType($postId);

        ob_start();

        echo '<div class="schilo-post-sections schilo-post-' . esc_attr(strtolower($prefix)) . '">';

        $rendered = 0;
        foreach ($sections as $index => $section) {
            if ($this->isSectionEmpty($section)) {
                continue;
            }
            (new SectionRenderer())->render($section, $prefix, $index);
            $rendered++;
        }

        echo '</div>';

        $output = (string) ob_get_clean();

        // Si aucune section n'avait de contenu, retourner le contenu original
        // (article non migré ou migration incomplète) plutôt qu'un div vide.
        if ($rendered === 0) {
            return $content;
        }

        return $output;
    }

    private function isSectionEmpty(\Schilo\Builder\Entity\Section $section): bool
    {
        if (trim($section->getContent()) !== '') {
            return false;
        }
        $data = $section->getData();
        if (empty($data)) {
            return true;
        }
        $hasValue = false;
        array_walk_recursive($data, function ($val) use (&$hasValue) {
            if (trim((string) $val) !== '') {
                $hasValue = true;
            }
        });
        return !$hasValue;
    }
}
