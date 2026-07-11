<?php

namespace Schilo\Builder\Front;

use Schilo\Builder\Repository\SectionRepository;
use Schilo\Builder\Service\ArticleTypeService;
use Schilo\Builder\Service\SectionRenderer;
use Schilo\Builder\Service\TemplateService;

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

        // Ordre d'affichage : suit le template du prefixe (Schilo Builder >
        // Types & templates) plutot que l'ordre stocke en base, pour que les
        // sections apparaissent toujours dans l'ordre voulu meme si la meta a
        // ete migree/enregistree dans un autre ordre (ex: bloc "Détails" en fin
        // d'article). Tri par ancrage positionnel : ne desordonne jamais un
        // article dont le template est incomplet.
        $sectionTypes = array();
        foreach ($sections as $section) {
            $sectionTypes[] = $section->getType();
        }
        $order = (new TemplateService())->orderIndexesByTemplate($sectionTypes, $prefix);
        $ordered = array();
        foreach ($order as $origIndex) {
            if (isset($sections[$origIndex])) {
                $ordered[] = $sections[$origIndex];
            }
        }
        if (!empty($ordered)) {
            $sections = $ordered;
        }

        // Couleur d'accent unique pour tout l'article : agrege les references
        // bibliques de toutes les sections, puis determine l'evangile dominant
        // (Matthieu > Marc > Luc > Jean > Bible en cas d'egalite), applique a
        // chaque carte de section pour un rendu homogene sur toute la fiche.
        $totalGospelCounts = array('matthieu' => 0, 'marc' => 0, 'luc' => 0, 'jean' => 0, 'bible' => 0);
        foreach ($sections as $section) {
            if ($this->isSectionEmpty($section)) {
                continue;
            }
            foreach (SectionRenderer::countGospels($section) as $gospel => $count) {
                $totalGospelCounts[$gospel] += $count;
            }
        }
        $dominantGospel = SectionRenderer::pickDominantGospel($totalGospelCounts);

        ob_start();

        echo '<div class="schilo-post-sections schilo-post-' . esc_attr(strtolower($prefix)) . '">';

        $rendered = 0;
        foreach ($sections as $index => $section) {
            if ($this->isSectionEmpty($section)) {
                continue;
            }
            (new SectionRenderer())->render($section, $prefix, $index, $dominantGospel);
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
        return SectionRenderer::isSectionEmpty($section);
    }
}
