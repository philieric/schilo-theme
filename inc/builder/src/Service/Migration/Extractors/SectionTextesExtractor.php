<?php

namespace Schilo\Builder\Service\Migration\Extractors;

use Schilo\Builder\Service\Migration\MigrationSourceContent;

/**
 * Extrait toutes les sections de texte generiques d'un article WPBakery,
 * identifiees par un titre <h2> (ou h1-h6) suivi de blocs [vc_column_text].
 *
 * Couvre les structures qui varient selon le type d'article :
 *   - PER : "Commentaire" (mais deja capte par CommentaireExtractor)
 *   - ANN : "Introduction", "I. ...", "II. ...", "Conclusion"
 *   - Tout autre prefixe avec des sections de texte libre.
 *
 * Les titres deja geres par des extracteurs dedies sont EXCLUS pour eviter
 * les doublons (Commentaires, Textes bibliques, Details techniques).
 *
 * Elements retournes par section trouvee (index 0 = pas de suffixe) :
 *   section_texte_heading[_N] : titre de la section
 *   section_texte_content[_N] : contenu HTML de la section
 *
 * Destination : section de type "paragraphe", champs section_title + content.
 */
class SectionTextesExtractor implements ExtractorInterface
{
    /**
     * Patterns de titres deja traites par d'autres extracteurs.
     * Ces titres sont exclus de l'extraction generique.
     * Doit rester synchronise avec ConsultationExtractor::$headingKeywords.
     */
    private static $excludedPatterns = array(
        'Commentaires?',
        'Textes\s+bibliques',
        'D[eé]tails?\s+techniques?',
        'Consultation',
        'Pour\s+plus\s+d.informations?',
        'Pour\s+en\s+savoir\s+plus',
        'En\s+savoir\s+plus',
        'Informations?\s+compl[eé]mentaires?',
        'Articles?\s+li[eé]s?',
        'Liens?\s+utiles?',
    );

    public function getKey()
    {
        return 'section_texte';
    }

    public function getLabel()
    {
        return 'Sections de texte (Introduction, parties, Conclusion...)';
    }

    public function extract(MigrationSourceContent $source)
    {
        $rawContent = $source->getRawContent();

        if (trim($rawContent) === '') {
            return array();
        }

        $sections = $this->findTextSections($rawContent);

        if (empty($sections)) {
            return array();
        }

        $elements = array();
        $index    = 0;

        foreach ($sections as $section) {
            $suffix = $index === 0 ? '' : '_' . $index;
            $num    = $index + 1;
            $short  = mb_substr($section['heading'], 0, 50, 'UTF-8');

            $elements[] = array(
                'id'      => $this->getKey() . '_heading' . $suffix,
                'label'   => 'Section ' . $num . ' — titre : ' . $short,
                'content' => $section['heading'],
                'meta'    => array(),
            );

            if ($section['content'] !== '') {
                $elements[] = array(
                    'id'      => $this->getKey() . '_content' . $suffix,
                    'label'   => 'Section ' . $num . ' — contenu : ' . $short,
                    'content' => $section['content'],
                    'meta'    => array(),
                );
            }

            $index++;
        }

        return $elements;
    }

    /**
     * Identifie toutes les sections texte dans le raw content.
     *
     * Algorithme :
     * 1. Trouve tous les <h1-6> dans le raw content (balises HTML directes).
     * 2. Exclut ceux deja geres par un extracteur dedie.
     * 3. Pour chaque titre restant, extrait les blocs [vc_column_text] qui
     *    se trouvent entre ce titre et le titre suivant.
     *
     * @return array<int, array{heading:string, content:string}>
     */
    private function findTextSections($rawContent)
    {
        // Trouve tous les titres <h1-6> avec leur position
        if (!preg_match_all(
            '/<h[1-6][^>]*>(.*?)<\/h[1-6]>/isu',
            $rawContent,
            $allHeadings,
            PREG_SET_ORDER | PREG_OFFSET_CAPTURE
        )) {
            return array();
        }

        $excludePattern = '/^(?:' . implode('|', self::$excludedPatterns) . ')$/iu';

        // Liste des titres valides (non exclus) avec position
        $validHeadings = array();

        foreach ($allHeadings as $hMatch) {
            $headingText = trim(strip_tags($hMatch[1][0]));

            if ($headingText === '') {
                continue;
            }

            if (preg_match($excludePattern, $headingText)) {
                continue;
            }

            $validHeadings[] = array(
                'text'   => $headingText,
                'end'    => $hMatch[0][1] + strlen($hMatch[0][0]),
                'start'  => $hMatch[0][1],
            );
        }

        if (empty($validHeadings)) {
            return array();
        }

        $sections = array();
        $count    = count($validHeadings);

        foreach ($validHeadings as $i => $heading) {
            // Zone : de la fin du titre jusqu'au debut du titre suivant (ou fin du contenu)
            $zoneStart = $heading['end'];
            $zoneEnd   = $i + 1 < $count ? $validHeadings[$i + 1]['start'] : strlen($rawContent);
            $zone      = substr($rawContent, $zoneStart, $zoneEnd - $zoneStart);

            $content = $this->extractContentFromZone($zone);

            $sections[] = array(
                'heading' => $heading['text'],
                'content' => $content,
            );
        }

        return $sections;
    }

    /**
     * Extrait le texte des blocs [vc_column_text] dans une zone donnee.
     */
    private function extractContentFromZone($zone)
    {
        $parts = array();

        if (!preg_match_all('/\[vc_column_text[^\]]*\](.*?)\[\/vc_column_text\]/isu', $zone, $blockMatches)) {
            return '';
        }

        foreach ($blockMatches[1] as $block) {
            $clean = $this->cleanBlock($block);

            if (strlen(strip_tags($clean)) > 20) {
                $parts[] = $clean;
            }
        }

        return implode("\n\n", $parts);
    }

    /**
     * Nettoie un bloc [vc_column_text] : retire les shortcodes TTS et
     * WPBakery structurels, normalise les espaces.
     */
    private function cleanBlock($html)
    {
        // Shortcodes TTS
        $html = preg_replace(
            '/\[\/?(?:audio_player|text_to_speech|lire_texte|listen_btn|read_text|wp_audio)[^\]]*\]/iu',
            '',
            $html
        );

        // Shortcodes WPBakery structurels
        $html = preg_replace('/\[\/?vc_(?:row|column|section)[^\]]*\]/iu', '', $html);

        // Lignes contenant "Ecouter ce texte"
        $html = preg_replace('/^.*[Ee]couter\s+ce\s+texte.*$/mu', '', $html);

        // Normalisation
        $html = preg_replace('/[ \t]+/u', ' ', $html);
        $html = preg_replace('/\n{3,}/u', "\n\n", $html);

        return trim($html);
    }
}
