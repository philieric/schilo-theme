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
     * Le titre porte-t-il déjà sa propre numérotation (« I. … », « 2 - … »,
     * « 3) … ») ? Chiffres romains ou arabes suivis d'un séparateur.
     */
    public function isSelfNumbered($label)
    {
        return (bool) preg_match('/^\s*(?:[IVXLCDM]+|\d+)\s*[\.\)\-\x{2013}\x{2014}:]\s+\S/u', (string) $label);
    }

    /**
     * Vrai quand la majorité des parties sont déjà numérotées par l'auteur :
     * le sommaire ne doit alors pas ajouter son propre compteur, sinon il
     * affiche une double numérotation (« 1 I. … »). La majorité, plutôt que
     * l'unanimité, tolère une partie finale non numérotée (« Annexe ») sans
     * se laisser déclencher par un titre isolé commençant par une initiale.
     *
     * @param array<int, array{label: string, anchor: string}> $parts
     */
    public function usesOwnNumbering(array $parts)
    {
        if (empty($parts)) {
            return false;
        }

        $numbered = 0;
        foreach ($parts as $part) {
            if ($this->isSelfNumbered(isset($part['label']) ? $part['label'] : '')) {
                $numbered++;
            }
        }

        return $numbered * 2 > count($parts);
    }

    /**
     * Un sommaire n'a de sens qu'à partir de 2 parties déclarées.
     */
    public function hasParts(array $sectionsRaw)
    {
        return count($this->getParts($sectionsRaw)) > 1;
    }
}
