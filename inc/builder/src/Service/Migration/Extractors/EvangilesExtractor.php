<?php

namespace Schilo\Builder\Service\Migration\Extractors;

use Schilo\Builder\Service\Migration\MigrationSourceContent;

/**
 * Extrait le bloc "Textes bibliques" d'un article WPBakery.
 *
 * Travaille sur le post_content BRUT (pas le HTML rendu) car :
 * - les shortcodes [bvc]/[bnv] ne contiennent que des références (ex :
 *   "Matt 26.1-5"), sans guillemets ni apostrophes susceptibles d'être
 *   corrompus par wptexturize ;
 * - le rendu produirait le texte biblique complet (HTML long) alors qu'on
 *   n'a besoin que de la référence brute, stockée telle quelle dans
 *   data.versets[].reference (la vue evangiles.php réapplique [bvc] au rendu).
 *
 * Retourne un élément par référence trouvée.  Les évangélistes "non cité dans
 * le livre" (aucun shortcode [bvc]/[bnv]) ne génèrent aucun élément.
 *
 * Format de chaque élément :
 *   id      : "evangile_verset" pour le 1er, "evangile_verset_1" pour le 2e…
 *   content : référence brute (ex : "Matt 26.1-5"), sans les balises shortcode
 *   meta    : ['class' => 'citation-matthieu', 'evangelist_label' => 'Matthieu']
 */
class EvangilesExtractor implements ExtractorInterface
{
    /** Correspondance nom d'évangéliste → classe CSS */
    private static $evangelistClasses = array(
        'matthieu' => 'citation-matthieu',
        'marc'     => 'citation-marc',
        'luc'      => 'citation-luc',
        'jean'     => 'citation-jean',
    );

    public function getKey()
    {
        return 'evangile';
    }

    public function getLabel()
    {
        return 'Textes bibliques';
    }

    public function extract(MigrationSourceContent $source)
    {
        $rawContent = $source->getRawContent();

        if (trim($rawContent) === '') {
            return array();
        }

        $versets = $this->findVersets($rawContent);

        if (empty($versets)) {
            return array();
        }

        $elements = array();

        $headingText = $this->findSectionHeading($rawContent);
        if ($headingText !== '') {
            $elements[] = array(
                'id'      => $this->getKey() . '_heading',
                'label'   => 'Textes bibliques — titre',
                'content' => $headingText,
                'meta'    => array(),
            );
        }

        foreach ($versets as $index => $verset) {
            $suffix = $index === 0 ? '' : '_' . $index;

            $elements[] = array(
                'id'      => $this->getKey() . '_verset' . $suffix,
                'label'   => 'Textes bibliques — ' . $verset['label'],
                'content' => $verset['reference'],
                'meta'    => array(
                    'class'           => $verset['class'],
                    'evangelist_label' => $verset['label'],
                ),
            );
        }

        return $elements;
    }

    private function findSectionHeading($rawContent)
    {
        if (preg_match('/<h[1-6][^>]*>([^<]*Textes\s+bibliques[^<]*)<\/h[1-6]>/iu', $rawContent, $m)) {
            return trim($m[1]);
        }
        return '';
    }

    /**
     * Localise la zone "Textes bibliques" dans le raw content et retourne
     * la liste ordonnée des versets trouvés.
     *
     * @return array<int, array{reference:string, label:string, class:string}>
     */
    private function findVersets($rawContent)
    {
        // Délimite la zone "Textes bibliques" : de son titre jusqu'au
        // prochain titre de section WPBakery (h1-h6 dans un vc_column_text)
        // ou jusqu'à la fin du contenu.
        if (!preg_match('/Textes\s+bibliques/iu', $rawContent, $headingMatch, PREG_OFFSET_CAPTURE)) {
            return array();
        }

        $startOffset = $headingMatch[0][1] + strlen($headingMatch[0][0]);
        $remaining   = substr($rawContent, $startOffset);

        // Borne de fin : prochain [vc_column_text] contenant un <h1>…<h6>
        // (indicateur d'un nouveau titre de section), ou fin du contenu.
        $zoneEnd = null;

        if (preg_match('/\[vc_column_text[^\]]*\]\s*<h[1-6][^>]*>/iu', $remaining, $nextSection, PREG_OFFSET_CAPTURE)) {
            $zoneEnd = $nextSection[0][1];
        }

        $zone = $zoneEnd !== null ? substr($remaining, 0, $zoneEnd) : $remaining;

        // Collecte tous les shortcodes [bvc]...[/bvc] et [bnv]...[/bnv]
        // présents dans cette zone.
        if (!preg_match_all('/\[b(?:vc|nv)\](.*?)\[\/b(?:vc|nv)\]/isu', $zone, $shortcodeMatches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
            return array();
        }

        $versets = array();

        foreach ($shortcodeMatches as $match) {
            $reference    = trim($match[1][0]);
            $matchOffset  = $match[0][1]; // position du shortcode dans $zone

            // Cherche le nom d'évangéliste le plus proche AVANT ce shortcode.
            $precedingText = substr($zone, 0, $matchOffset);
            $label         = $this->findNearestEvangelist($precedingText);
            $class         = $this->classForLabel($label);

            // Valide le format de la référence : doit ressembler à "Abrév Chap.Verset"
            // (ex : "Matt 26.1", "Mc 1.26-38"). Si invalide, on vide la référence :
            // la vue affichera uniquement le label (ex: "Marc") sans shortcode.
            if (!$this->isValidReference($reference)) {
                if ($label !== '') {
                    $reference = '';
                } else {
                    continue;
                }
            }

            $versets[] = array(
                'reference' => $reference,
                'label'     => $label,
                'class'     => $class,
            );
        }

        return $versets;
    }

    /**
     * Cherche le dernier nom d'évangéliste mentionné dans $text.
     * Retourne le nom affiché (ex: "Matthieu") ou une chaîne vide si aucun
     * évangéliste n'est trouvé (la référence sera classée "citation-bible").
     */
    private function findNearestEvangelist($text)
    {
        $found        = '';
        $foundOffset  = -1;

        $evangelists = array(
            'Matthieu' => 'matthieu',
            'Marc'     => 'marc',
            'Luc'      => 'luc',
            'Jean'     => 'jean',
        );

        foreach ($evangelists as $displayName => $key) {
            // Cherche le dernier emplacement du nom dans le texte précédent,
            // comme mot entier (insensible à la casse).
            if (preg_match_all('/\b' . preg_quote($displayName, '/') . '\b/iu', $text, $m, PREG_OFFSET_CAPTURE)) {
                $lastOffset = end($m[0])[1];

                if ($lastOffset > $foundOffset) {
                    $foundOffset = $lastOffset;
                    $found       = $displayName;
                }
            }
        }

        return $found;
    }

    /**
     * Valide qu'une référence biblique a un format acceptable pour le shortcode [bvc].
     * Accepte : "Abréviation Chap.Verset", "Abréviation Chap:Verset", avec tiret optionnel.
     * Exemples valides : "Matt 26.1-5", "Mc 1.26-38", "Jn 3.16", "Gn 4:1-10"
     */
    private function isValidReference($reference)
    {
        if ($reference === '') {
            return false;
        }
        // Doit contenir au moins une lettre suivie d'un chiffre (abréviation + chapitre)
        // et un séparateur point ou deux-points avant le numéro de verset.
        return (bool) preg_match('/^[A-Za-zÀ-ÿ]{1,10}\.?\s+\d+[.:]\d+/u', $reference);
    }

    /**
     * Retourne la classe CSS correspondant à un nom d'évangéliste.
     */
    private function classForLabel($label)
    {
        $key = mb_strtolower(trim($label), 'UTF-8');

        return isset(self::$evangelistClasses[$key])
            ? self::$evangelistClasses[$key]
            : 'citation-bible';
    }
}
