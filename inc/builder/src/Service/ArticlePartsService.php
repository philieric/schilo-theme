<?php

namespace Schilo\Builder\Service;

class ArticlePartsService
{
    /**
     * Découpe les sections brutes d'un article en parties, à partir des
     * sections de type "titre-partie" (une par début de partie). Le titre
     * de la section sert de libellé et son ancre reprend exactement celle
     * générée par views/sections/titre-partie.php (sanitize_title du titre),
     * pour que les liens du sommaire pointent vers le bon endroit de la page.
     *
     * @return array<int, array{label: string, anchor: string}>
     */
    public function getParts(array $sectionsRaw)
    {
        $parts = array();

        foreach ($sectionsRaw as $sec) {
            if (!is_array($sec)) {
                continue;
            }

            $type = isset($sec['type']) ? $sec['type'] : '';

            if ($type !== 'titre-partie') {
                continue;
            }

            $label = isset($sec['title']) ? trim((string) $sec['title']) : '';

            if ($label === '') {
                continue;
            }

            $parts[] = array(
                'label' => $label,
                'anchor' => sanitize_title($label),
            );
        }

        return $parts;
    }

    /**
     * Un sommaire n'a de sens qu'à partir de 2 parties déclarées.
     */
    public function hasParts(array $sectionsRaw)
    {
        return count($this->getParts($sectionsRaw)) > 1;
    }
}
