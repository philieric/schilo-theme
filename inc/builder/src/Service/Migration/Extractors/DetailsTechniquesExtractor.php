<?php

namespace Schilo\Builder\Service\Migration\Extractors;

use Schilo\Builder\Service\Migration\MigrationSourceContent;

/**
 * Extrait le bloc "Détails techniques" d'un article WPBakery.
 *
 * Travaille sur le post_content BRUT pour éviter toute corruption par
 * wptexturize (les libellés "Lieu :", "Date :" etc. peuvent être en titre
 * ou en gras dans des [vc_column_text]).
 *
 * Éléments extraits (chacun est un élément indépendant dans l'assistant) :
 *   details_lieu              → champ "lieu"
 *   details_date              → champ "date"
 *   details_mode_operatoire   → champ "mode_operatoire"
 *   details_note              → champ "note_mode_operatoire"
 *   details_image             → champ "image_id"  (content = ID attachment WP)
 *   details_texte_dessous     → champ "texte_dessous"
 *
 * Pour l'image : on lit directement l'attribut image="N" du shortcode
 * [vc_single_image] (disponible dans le raw content), ce qui donne l'ID
 * WordPress de l'attachment sans nécessiter de résolution URL. Cet ID est
 * stocké comme content de l'élément et sera transmis tel quel au champ
 * image_id via le case 'default' de MigrationApplier::buildSection().
 */
class DetailsTechniquesExtractor implements ExtractorInterface
{
    public function getKey()
    {
        return 'details_techniques';
    }

    public function getLabel()
    {
        return 'Détails techniques';
    }

    public function extract(MigrationSourceContent $source)
    {
        $rawContent = $source->getRawContent();

        if (trim($rawContent) === '') {
            return array();
        }

        $zone = $this->findDetailsTechniquesZone($rawContent);

        if ($zone === '') {
            return array();
        }

        $elements = array();

        $headingText = $this->findSectionHeading($rawContent);
        if ($headingText !== '') {
            $elements[] = array(
                'id'      => $this->getKey() . '_heading',
                'label'   => 'Détails techniques — titre',
                'content' => $headingText,
                'meta'    => array(),
            );
        }

        // ── Champs textuels ────────────────────────────────────────────────
        $textFields = array(
            'details_lieu'            => array('Lieu', 'lieu'),
            'details_date'            => array('Date', 'date'),
            'details_mode_operatoire' => array('Mode opératoire', 'mode_operatoire'),
            'details_note'            => array('Note sur le mode opératoire', 'note'),
        );

        $labels = array(
            'details_lieu'            => 'Détails techniques — Lieu',
            'details_date'            => 'Détails techniques — Date',
            'details_mode_operatoire' => 'Détails techniques — Mode opératoire',
            'details_note'            => 'Détails techniques — Note sur le mode opératoire',
        );

        foreach ($textFields as $id => $fieldInfo) {
            $value = $this->extractTextField($zone, $fieldInfo[0]);

            if ($value !== '') {
                $elements[] = array(
                    'id'      => $id,
                    'label'   => $labels[$id],
                    'content' => $value,
                    'meta'    => array(),
                );
            }
        }

        // ── Image (attachment ID depuis [vc_single_image image="N"]) ───────
        $imageId = $this->extractSingleImageId($zone);

        if ($imageId > 0) {
            $elements[] = array(
                'id'      => 'details_image',
                'label'   => 'Détails techniques — Image',
                'content' => (string) $imageId,
                'meta'    => array('image_id' => $imageId),
            );
        }

        // ── Texte sous l'image ─────────────────────────────────────────────
        $texteApresImage = $this->extractTexteApresImage($zone, $imageId);

        if ($texteApresImage !== '') {
            $elements[] = array(
                'id'      => 'details_texte_dessous',
                'label'   => "Détails techniques — Texte sous l'image",
                'content' => $texteApresImage,
                'meta'    => array(),
            );
        }

        return $elements;
    }

    private function findSectionHeading($rawContent)
    {
        if (preg_match('/<h[1-6][^>]*>([^<]*D[eé]tails?\s+techniques?[^<]*)<\/h[1-6]>/iu', $rawContent, $m)) {
            return trim($m[1]);
        }
        return '';
    }

    /**
     * Délimite la zone "Détails techniques" dans le raw content.
     */
    private function findDetailsTechniquesZone($rawContent)
    {
        if (!preg_match('/Détails?\s+techniques?/iu', $rawContent, $m, PREG_OFFSET_CAPTURE)) {
            return '';
        }

        $startOffset = $m[0][1] + strlen($m[0][0]);
        $remaining   = substr($rawContent, $startOffset);

        // Fin de zone : prochain <h1-6> (brut ou dans vc_column_text) avec titre différent
        $zoneEnd = null;

        if (preg_match('/(?:\[vc_column_text[^\]]*\]\s*)?<h[1-6][^>]*>(?!D[eé]tails?\s+techniques?)/iu', $remaining, $next, PREG_OFFSET_CAPTURE)) {
            $zoneEnd = $next[0][1];
        }

        return $zoneEnd !== null ? substr($remaining, 0, $zoneEnd) : $remaining;
    }

    /**
     * Extrait la valeur textuelle associée à un libellé (ex: "Lieu :").
     *
     * Structure réelle : [vc_message]Lieu : <strong>valeur</strong>[/vc_message]
     */
    private function extractTextField($zone, $labelName)
    {
        $labelPattern = preg_quote($labelName, '/');

        // Cherche dans [vc_message] (structure réelle observée)
        if (preg_match_all('/\[vc_message\b[^\]]*\](.*?)\[\/vc_message\]/isu', $zone, $msgMatches)) {
            foreach ($msgMatches[1] as $blockContent) {
                if (!preg_match('/\b' . $labelPattern . '\s*:/iu', $blockContent)) {
                    continue;
                }
                // Valeur dans <strong>...</strong>
                if (preg_match('/<(?:strong|b)[^>]*>(.*?)<\/(?:strong|b)>/isu', $blockContent, $m)) {
                    return $this->cleanText($m[1]);
                }
                // Fallback : texte après le ":"
                if (preg_match('/:\s*(.+)/isu', strip_tags($blockContent), $m)) {
                    return $this->cleanText($m[1]);
                }
            }
        }

        // Fallback ancien format [vc_column_text] ou HTML direct
        $patterns = array(
            '/<(?:strong|b)[^>]*>' . $labelPattern . '\s*:?\s*<\/(?:strong|b)>\s*:?\s*([^<\[\r\n]+)/iu',
            '/\b' . $labelPattern . '\s*:\s*([^<\[\r\n]+)/iu',
        );

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $zone, $match)) {
                return $this->cleanText($match[1]);
            }
        }

        return '';
    }

    /**
     * Extrait l'ID de l'attachment depuis [vc_single_image image="N" ...].
     *
     * wptexturize corrompt image="N" en image= »N&Prime; donc on
     * saute tous les caractères non-numériques entre = et l'ID.
     */
    private function extractSingleImageId($zone)
    {
        if (preg_match('/\[vc_single_image\b[^\]]*\bimage=\s*[^0-9\]]*(\d+)/iu', $zone, $match)) {
            return (int) $match[1];
        }

        return 0;
    }

    /**
     * Extrait le texte qui apparaît dans le raw content APRÈS le shortcode
     * [vc_single_image] (le "texte sous l'image").
     *
     * Si aucune image n'est trouvée, cherche un bloc de texte long qui ne
     * correspond pas aux libellés structurés (Lieu/Date/Mode/Note).
     */
    private function extractTexteApresImage($zone, $imageId)
    {
        if ($imageId > 0) {
            // Coupe la zone après le premier [vc_single_image ...]
            if (!preg_match('/\[vc_single_image\b[^\]]*\]/iu', $zone, $m, PREG_OFFSET_CAPTURE)) {
                return '';
            }

            $afterImage = substr($zone, $m[0][1] + strlen($m[0][0]));
        } else {
            $afterImage = $zone;
        }

        $labelPat = '/^\s*(?:Lieu|Date|Mode\s+op.ratoire|Note)/iu';

        // Structure réelle : [vc_message] sans libellé structuré après l'image
        if (preg_match_all('/\[vc_message\b[^\]]*\](.*?)\[\/vc_message\]/isu', $afterImage, $msgBlocks)) {
            foreach ($msgBlocks[1] as $block) {
                $text = $this->cleanText($block);
                if (!preg_match($labelPat, $text) && strlen($text) > 20) {
                    return trim($block);
                }
            }
        }

        // Fallback [vc_column_text]
        if (preg_match_all('/\[vc_column_text[^\]]*\](.*?)\[\/vc_column_text\]/isu', $afterImage, $blocks)) {
            foreach ($blocks[1] as $block) {
                $text = $this->cleanText($block);
                if (strlen($text) > 40) {
                    return $text;
                }
            }
        }

        return '';
    }

    private function cleanText($text)
    {
        $text = preg_replace('/<[^>]+>/u', ' ', (string) $text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/u', ' ', $text);

        return trim($text);
    }
}
