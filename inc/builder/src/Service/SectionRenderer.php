<?php

namespace Schilo\Builder\Service;

use Schilo\Builder\Entity\Section;

class SectionRenderer
{
    public function render(Section $section, $prefix, $index, $dominantGospel = '')
    {
        $type = sanitize_key($section->getType());
        $viewFile = $this->resolveViewFile($type);
        $view = SCHILO_BUILDER_PATH . 'views/sections/' . $viewFile;

        if (!file_exists($view)) {
            $view = SCHILO_BUILDER_PATH . 'views/sections/paragraphe.php';
        }

        $sectionClass = $this->generateCssClass($section, $prefix, $index, $dominantGospel);
        $contentFilter = new ContentFilter();

        include $view;
    }

    private function resolveViewFile($type)
    {
        $types = (new SectionTypeService())->getAllTypes();

        if (isset($types[$type]['view']) && $types[$type]['view'] !== '') {
            return sanitize_file_name($types[$type]['view']);
        }

        return $type . '.php';
    }

    private function generateCssClass(Section $section, $prefix, $index, $dominantGospel = '')
    {
        $type = sanitize_key($section->getType());
        $prefix = sanitize_html_class(strtolower((string) $prefix));
        $number = sprintf('%03d', ((int) $index) + 1);

        $classes = array(
            'schilo-section',
            'schilo-section-' . $type,
            'schilo-section-' . $type . '-' . $prefix,
            'schilo-' . $prefix,
            'schilo-' . $prefix . '-' . $type,
            'schilo-' . $prefix . '-' . $type . '-' . $number,
        );

        if ($dominantGospel !== '') {
            $classes[] = 'schilo-section--' . $dominantGospel;
        }

        if ($section->getCustomClass() !== '') {
            $classes = array_merge($classes, explode(' ', $section->getCustomClass()));
        }

        return implode(' ', array_filter(array_map('sanitize_html_class', $classes)));
    }

    /**
     * Compte les references bibliques d'une section par evangile (ou "bible"
     * pour les autres livres). Utilise par ContentRenderer pour agreger sur
     * l'ensemble des sections d'un article et determiner une couleur d'accent
     * unique, appliquee uniformement a toutes les cartes de l'article.
     */
    public static function countGospels(Section $section)
    {
        $counts = array('matthieu' => 0, 'marc' => 0, 'luc' => 0, 'jean' => 0, 'bible' => 0);

        $data = $section->getData();
        if (!empty($data['versets']) && is_array($data['versets'])) {
            foreach ($data['versets'] as $verset) {
                if (empty($verset['reference'])) {
                    continue;
                }
                $class = isset($verset['class']) ? (string) $verset['class'] : 'citation-bible';
                $gospel = str_replace('citation-', '', $class);
                $counts[isset($counts[$gospel]) ? $gospel : 'bible']++;
            }
        }

        $content = $section->getContent();
        if ($content !== '') {
            if (preg_match_all('/\[b(?:ib)?\](.*?)\[\/b(?:ib)?\]/is', $content, $matches)) {
                foreach ($matches[1] as $refStr) {
                    $refStr = trim($refStr);
                    if ($refStr === '') {
                        continue;
                    }
                    $abbr = preg_split('/\s+/', $refStr)[0];
                    $counts[self::gospelFromAbbr($abbr)]++;
                }
            }
            if (preg_match_all('/\[brc\](.*?)\[\/brc\]/is', $content, $matches)) {
                foreach ($matches[1] as $refStr) {
                    $refStr = trim($refStr);
                    if ($refStr === '') {
                        continue;
                    }
                    $parts = preg_split('/\s+/', $refStr);
                    // "2 Timothée 3.16" → abbr = "2 Timothée", pas juste "2"
                    $abbr = (isset($parts[1]) && ctype_digit($parts[0]))
                        ? $parts[0] . ' ' . $parts[1]
                        : $parts[0];
                    $counts[self::gospelFromAbbr($abbr)]++;
                }
            }
        }

        return $counts;
    }

    /**
     * Determine l'evangile dominant a partir de compteurs agreges (voir
     * countGospels). En cas d'egalite : Matthieu > Marc > Luc > Jean > Bible.
     * Retourne '' si aucune reference biblique n'a ete comptee.
     */
    public static function pickDominantGospel(array $counts)
    {
        if (array_sum($counts) === 0) {
            return '';
        }

        $max = max($counts);
        foreach (array('matthieu', 'marc', 'luc', 'jean', 'bible') as $gospel) {
            if (isset($counts[$gospel]) && $counts[$gospel] === $max) {
                return $gospel;
            }
        }

        return '';
    }

    private static function gospelFromAbbr($abbr)
    {
        static $map = array(
            'matt' => 'matthieu', 'mt' => 'matthieu', 'mat' => 'matthieu', 'matthieu' => 'matthieu', 'matthew' => 'matthieu',
            'mc' => 'marc', 'mr' => 'marc', 'mrk' => 'marc', 'marc' => 'marc', 'mark' => 'marc',
            'lc' => 'luc', 'luc' => 'luc', 'lu' => 'luc', 'luk' => 'luc', 'luke' => 'luc',
            'jn' => 'jean', 'jean' => 'jean', 'jo' => 'jean', 'jhn' => 'jean', 'john' => 'jean',
        );
        $key = strtolower((string) $abbr);
        return isset($map[$key]) ? $map[$key] : 'bible';
    }
}
