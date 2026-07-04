<?php

namespace Schilo\Builder\Service\Migration\Extractors;

use Schilo\Builder\Service\Migration\MigrationSourceContent;

/**
 * Extrait le bloc "Consultation" d'un article, en parsant le post_content
 * BRUT (pas le HTML rendu), car le shortcode `[vc_message ...]` peut voir
 * ses attributs corrompus par wptexturize au moment du rendu (guillemets
 * droits " transformés en guillemets typographiques », ce qui empêche le
 * shortcode WPBakery de s'exécuter correctement et le laisse affiché tel
 * quel en texte au lieu d'être transformé en `.vc_message_box`).
 *
 * Format attendu dans post_content, pour chaque lien :
 *   [vc_message ...][Texte du lien](url)[/vc_message]
 * ou, selon l'éditeur ayant enregistré le contenu :
 *   [vc_message ...]<a href="url"><strong>Texte du lien</strong></a>[/vc_message]
 *
 * Un article peut contenir de 1 à n liens de ce type. Pour chaque lien
 * trouvé, cet extracteur retourne deux éléments séparés :
 * - le lien lui-même (texte affiché + URL), destiné au champ "Liste de
 *   liens (extraction automatique)" de la section "Liens / Articles liés" ;
 * - le texte libre qui précède le lien (ex: "Vous pouvez consulter
 *   l'annexe"), destiné au champ "Texte libre".
 *
 * Générique : ne dépend d'aucun préfixe particulier, réutilisable pour
 * PER, ANN, CTD, etc.
 */
class ConsultationExtractor implements ExtractorInterface
{
    public function getKey()
    {
        return 'consultation';
    }

    public function getLabel()
    {
        return 'Consultation';
    }

    public function extract(MigrationSourceContent $source)
    {
        $rawContent = $source->getRawContent();

        if (trim($rawContent) === '') {
            return array();
        }

        $messages = $this->findConsultationMessages($rawContent);

        if (empty($messages)) {
            return array();
        }

        $elements = array();

        $elements[] = array(
            'id'      => $this->getKey() . '_heading',
            'label'   => 'Consultation — titre',
            'content' => $this->findHeadingText($rawContent),
            'meta'    => array(),
        );

        $index = 0;
        $linkEntries = array();

        foreach ($messages as $messageInner) {
            $links = $this->extractLinksFromMessage($messageInner);

            foreach ($links as $linkData) {
                if ($linkData['label'] === '' && $linkData['url'] === '') {
                    continue;
                }

                $linkEntries[] = $linkData;
            }
        }

        if (empty($linkEntries)) {
            return $elements;
        }

        // Texte par défaut utilisé pour tous les liens, choisi parmi le
        // premier texte libre réellement trouvé sur l'article (s'il y en a
        // un), sinon la formule standard "Vous pouvez consulter l'annexe".
        $defaultLeadText = "Vous pouvez consulter l'annexe";

        foreach ($linkEntries as $linkData) {
            if ($linkData['lead_text'] !== '') {
                $defaultLeadText = $linkData['lead_text'];
                break;
            }
        }

        foreach ($linkEntries as $linkData) {
            $label = $linkData['label'];
            $url = $linkData['url'];
            $leadText = $linkData['lead_text'] !== '' ? $linkData['lead_text'] : $defaultLeadText;

            $suffix = $index === 0 ? '' : '_' . $index;

            $elements[] = array(
                'id' => $this->getKey() . '_link' . $suffix,
                'label' => 'Consultation — lien ' . ($index + 1),
                'content' => $label,
                'meta' => array(
                    'url' => $url,
                    'lead_text' => $leadText,
                ),
            );

            $elements[] = array(
                'id' => $this->getKey() . '_text' . $suffix,
                'label' => 'Consultation — texte ' . ($index + 1),
                'content' => $leadText,
                'meta' => array(
                    'editable' => true,
                ),
            );

            $index++;
        }

        return $elements;
    }

    /**
     * Mots-clés de titres reconnus comme blocs de liens.
     * Communs à tous les types d'articles (PER, ANN, etc.).
     */
    private static $headingKeywords = array(
        'Consultation',
        'Pour\s+plus\s+d.informations?',
        'Pour\s+en\s+savoir\s+plus',
        'En\s+savoir\s+plus',
        'Informations?\s+compl[eé]mentaires?',
        'Articles?\s+li[eé]s?',
        'Liens?\s+utiles?',
    );

    /**
     * Retourne le texte réel du titre de la section liens trouvé.
     */
    private function findHeadingText($rawContent)
    {
        $kw = implode('|', self::$headingKeywords);

        if (preg_match('/<h[1-6][^>]*>([^<]*(?:' . $kw . ')[^<]*)<\/h[1-6]>/iu', $rawContent, $m)) {
            return trim($m[1]);
        }

        return 'Consultation';
    }

    /**
     * Repère le bloc de liens dans post_content (Consultation, Pour plus
     * d'informations, etc.) suivi de shortcodes [vc_message] avec des liens.
     *
     * @return string[] Le contenu intérieur de chaque [vc_message] trouvé.
     */
    private function findConsultationMessages($rawContent)
    {
        $kw = implode('|', self::$headingKeywords);

        // Cherche le titre de section (variantes multiples)
        if (!preg_match('/(?:' . $kw . ')\s*(?:<\/h[1-6]>|\[\/vc_column_text\])/iu', $rawContent, $headingMatch, PREG_OFFSET_CAPTURE)) {
            // Repli : mot-clé seul sur sa ligne
            if (!preg_match('/^\s*(?:' . $kw . ')\s*$/miu', $rawContent, $headingMatch, PREG_OFFSET_CAPTURE)) {
                return array();
            }
        }

        $startOffset = $headingMatch[0][1] + strlen($headingMatch[0][0]);

        // Cherche le prochain titre de section après "Consultation", pour
        // borner la zone de recherche des [vc_message].
        $remaining = substr($rawContent, $startOffset);

        $nextHeadingOffset = null;

        if (preg_match('/\[vc_column_text[^\]]*\]\s*<h[1-6][^>]*>/iu', $remaining, $nextHeadingMatch, PREG_OFFSET_CAPTURE)) {
            $nextHeadingOffset = $nextHeadingMatch[0][1];
        }

        $zone = $nextHeadingOffset !== null ? substr($remaining, 0, $nextHeadingOffset) : $remaining;

        // Récupère le contenu de chaque [vc_message ...]...[/vc_message] dans cette zone.
        if (!preg_match_all('/\[vc_message\b[^\]]*\](.*?)\[\/vc_message\]/isu', $zone, $messageMatches)) {
            return array();
        }

        return $messageMatches[1];
    }

    /**
     * Extrait, dans le contenu intérieur d'un [vc_message], les liens
     * trouvés (format Markdown `[Texte](url)` ou HTML `<a href="url">Texte</a>`),
     * ainsi que le texte libre qui précède chaque lien.
     *
     * @return array<int, array{label:string,url:string,lead_text:string}>
     */
    private function extractLinksFromMessage($messageInner)
    {
        $links = array();

        // Format Markdown : [Texte du lien](url)
        if (preg_match_all('/(.*?)\[([^\]]+)\]\(([^)]+)\)/su', $messageInner, $markdownMatches, PREG_SET_ORDER)) {
            foreach ($markdownMatches as $match) {
                $links[] = array(
                    'lead_text' => $this->cleanLeadText($match[1]),
                    'label' => $this->cleanLabel($match[2]),
                    'url' => trim($match[3]),
                );
            }
        }

        if (!empty($links)) {
            return $links;
        }

        // Format HTML : <a href="url">...Texte...</a>, avec le texte libre
        // éventuel avant le lien dans le même paragraphe.
        if (preg_match_all('/(.*?)<a\s+[^>]*href=["\']([^"\']+)["\'][^>]*>(.*?)<\/a>/isu', $messageInner, $htmlMatches, PREG_SET_ORDER)) {
            foreach ($htmlMatches as $match) {
                $links[] = array(
                    'lead_text' => $this->cleanLeadText($match[1]),
                    'label' => $this->cleanLabel($match[3]),
                    'url' => trim($match[2]),
                );
            }
        }

        return $links;
    }

    private function cleanLabel($label)
    {
        $label = wp_strip_all_tags((string) $label);
        $label = html_entity_decode($label, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace('/\s+/u', ' ', $label));
    }

    private function cleanLeadText($text)
    {
        $text = wp_strip_all_tags((string) $text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Retire les résidus de shortcode WPBakery (id de paragraphe, etc.)
        // qui peuvent précéder le texte utile.
        $text = preg_replace('/^\s*<p[^>]*>/iu', '', $text);

        return trim(preg_replace('/\s+/u', ' ', $text));
    }
}
