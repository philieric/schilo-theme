<?php

namespace Schilo\Builder\Service\Migration\Extractors;

use Schilo\Builder\Service\Migration\MigrationSourceContent;

/**
 * Extrait le bloc "Commentaire" d'un article WPBakery.
 *
 * Travaille sur le post_content BRUT pour conserver le HTML interne
 * des paragraphes sans risque de mutation par wptexturize.
 *
 * Retourne un seul element :
 *   id      : 'commentaire_content'
 *   content : HTML interne du bloc (paragraphes, liens, gras...)
 *             avec le widget "Ecouter ce texte" retire.
 *   meta    : []
 *
 * Destination : champ 'content' (editeur) d'une section 'paragraphe'
 * ou 'contexte' selon le template.
 */
class CommentaireExtractor implements ExtractorInterface
{
    public function getKey()
    {
        return 'commentaire';
    }

    public function getLabel()
    {
        return 'Commentaire';
    }

    public function extract(MigrationSourceContent $source)
    {
        $rawContent = $source->getRawContent();

        if (trim($rawContent) === '') {
            return array();
        }

        $content = $this->findCommentaireContent($rawContent);

        if ($content === '') {
            return array();
        }

        $elements = array();

        $headingText = $this->findSectionHeading($rawContent);
        if ($headingText !== '') {
            $elements[] = array(
                'id'      => $this->getKey() . '_heading',
                'label'   => 'Commentaire — titre',
                'content' => $headingText,
                'meta'    => array(),
            );
        }

        $elements[] = array(
            'id'      => $this->getKey() . '_content',
            'label'   => 'Commentaire',
            'content' => $content,
            'meta'    => array(),
        );

        return $elements;
    }

    private function findSectionHeading($rawContent)
    {
        if (preg_match('/<h[1-6][^>]*>([^<]*Commentaires?[^<]*)<\/h[1-6]>/iu', $rawContent, $m)) {
            return trim($m[1]);
        }
        return '';
    }

    /**
     * Localise et retourne le contenu HTML du bloc "Commentaire".
     *
     * Collecte les [vc_column_text] entre le titre "Commentaire" et le
     * prochain titre de section, en excluant le widget TTS.
     */
    private function findCommentaireContent($rawContent)
    {
        if (!preg_match('/\bCommentaires?\b/iu', $rawContent, $m, PREG_OFFSET_CAPTURE)) {
            return '';
        }

        $startOffset = $m[0][1] + strlen($m[0][0]);
        $remaining   = substr($rawContent, $startOffset);

        $zoneEnd = null;

        // Fin de zone : soit un [vc_column_text...<h1-6] (titre de section WPBakery)
        // soit un <h1-6> en HTML brut en dehors de tout shortcode
        if (preg_match('/(?:\[vc_column_text[^\]]*\]\s*)?<h[1-6][^>]*>(?!Commentaires?)/iu', $remaining, $next, PREG_OFFSET_CAPTURE)) {
            $zoneEnd = $next[0][1];
        }

        $zone = $zoneEnd !== null ? substr($remaining, 0, $zoneEnd) : $remaining;

        $parts = array();

        if (preg_match_all('/\[vc_column_text[^\]]*\](.*?)\[\/vc_column_text\]/isu', $zone, $blockMatches)) {
            foreach ($blockMatches[1] as $block) {
                $clean = $this->cleanBlock($block);

                if (strlen(strip_tags($clean)) > 20) {
                    $parts[] = $clean;
                }
            }
        }

        return implode("\n\n", $parts);
    }

    /**
     * Nettoie un bloc de contenu extrait d'un [vc_column_text] :
     * - Retire les shortcodes TTS connus ([audio_player], etc.)
     * - Retire les <div class="tts-..."> et variantes TTS (WordPress
     *   genere toujours class="..." avec des guillemets doubles).
     * - Retire les balises fermantes </div> isolees (residus de suppression).
     * - Retire les lignes contenant le texte "Ecouter ce texte".
     * - Retire les shortcodes WPBakery structurels residuels.
     * - Preserve <p>, <strong>, <em>, <a>, <br> et leur contenu.
     */
    private function cleanBlock($html)
    {
        // Shortcodes TTS
        $html = preg_replace(
            '/\[\/?(?:audio_player|text_to_speech|lire_texte|listen_btn|read_text|wp_audio)[^\]]*\]/iu',
            '',
            $html
        );

        // <div class="tts-...">...</div> — WordPress utilise toujours des
        // guillemets doubles pour les attributs, donc pas besoin de ["']
        $ttsClasses = 'tts|lire-texte|listen|audio-player|listen-btn|read-btn|wp-audio';
        $html = preg_replace(
            '/<div[^>]*class="[^"]*(?:' . $ttsClasses . ')[^"]*"[^>]*>.*?<\/div>/isu',
            '',
            $html
        );

        // <button> et <a> avec classe TTS
        $html = preg_replace(
            '/<(?:button|a)[^>]*class="[^"]*(?:' . $ttsClasses . ')[^"]*"[^>]*>.*?<\/(?:button|a)>/isu',
            '',
            $html
        );

        // Balises </div> seules sur une ligne (residus de suppression)
        $html = preg_replace('/^\s*<\/div>\s*$/mu', '', $html);

        // Lignes contenant "Ecouter ce texte" (filet de securite)
        $html = preg_replace('/^.*[Ee]couter\s+ce\s+texte.*$/mu', '', $html);

        // Shortcodes WPBakery structurels residuels
        $html = preg_replace('/\[\/?vc_(?:row|column|section)[^\]]*\]/iu', '', $html);

        // Coller les shortcodes [bvc]/[bnv] à leur contexte inline :
        // supprime les sauts de ligne directement avant et après ces shortcodes
        // pour qu'ils restent dans le flux du paragraphe sans créer de <p> séparés.
        $html = preg_replace('/\n+(\[b(?:vc|nv)\])/iu', ' $1', $html);
        $html = preg_replace('/(\[\/b(?:vc|nv)\])\n+/iu', '$1 ', $html);

        // Normalisation des espaces
        $html = preg_replace('/[ \t]+/u', ' ', $html);
        $html = preg_replace('/\n{3,}/u', "\n\n", $html);

        return trim($html);
    }
}
