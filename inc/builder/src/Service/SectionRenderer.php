<?php

namespace Schilo\Builder\Service;

use Schilo\Builder\Entity\Section;

class SectionRenderer
{
    public function render(Section $section, $prefix, $index)
    {
        $type = sanitize_key($section->getType());
        $viewFile = $this->resolveViewFile($type);
        $view = SCHILO_BUILDER_PATH . 'views/sections/' . $viewFile;

        if (!file_exists($view)) {
            $view = SCHILO_BUILDER_PATH . 'views/sections/paragraphe.php';
        }

        $sectionClass = $this->generateCssClass($section, $prefix, $index);
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

    private function generateCssClass(Section $section, $prefix, $index)
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

        if ($section->getCustomClass() !== '') {
            $classes = array_merge($classes, explode(' ', $section->getCustomClass()));
        }

        return implode(' ', array_filter(array_map('sanitize_html_class', $classes)));
    }
}
