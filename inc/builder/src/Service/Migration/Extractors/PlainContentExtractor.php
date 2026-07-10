<?php

namespace Schilo\Builder\Service\Migration\Extractors;

use Schilo\Builder\Service\Migration\MigrationSourceContent;

/**
 * Extrait le contenu principal d'un article sans structure WPBakery définie.
 *
 * Conçu pour les articles INF (Notes d'information) dont le post_content
 * est soit du HTML brut, soit du HTML enveloppé dans des colonnes vc_row/
 * vc_column sans extracteur sémantique (pas de [consultation], pas de
 * [section_textes] avec titres h2).
 *
 * Stratégie :
 *  1. Si le post_content contient des [vc_column_text], concatène leur contenu.
 *  2. Sinon, prend l'intégralité du post_content.
 *  3. Dans les deux cas : supprime les shortcodes WPBakery structurels,
 *     retire le <h1> initial s'il duplique le titre de l'article,
 *     nettoie les shortcodes TTS et les espaces superflus.
 *
 * Retourne un unique élément 'plain_content' destiné à un champ 'content'
 * de section (type paragraphe, contexte, intro, etc.).
 */
class PlainContentExtractor implements ExtractorInterface
{
    public function getKey()
    {
        return 'plain_content';
    }

    public function getLabel()
    {
        return 'Contenu principal (HTML brut ou WPBakery simple)';
    }

    public function extract(MigrationSourceContent $source)
    {
        $raw = $source->getRawContent();

        if (trim($raw) === '') {
            return array();
        }

        $content = $this->extractContent($raw);

        if (strip_tags($content) === '') {
            return array();
        }

        return array(
            array(
                'id'      => 'plain_content',
                'label'   => 'Contenu principal',
                'content' => $content,
                'meta'    => array(),
            ),
        );
    }

    private function extractContent($raw)
    {
        // Si des blocs vc_column_text sont présents, les concaténer
        if (preg_match('/\[vc_column_text/i', $raw)) {
            $parts = array();
            preg_match_all('/\[vc_column_text[^\]]*\](.*?)\[\/vc_column_text\]/isu', $raw, $matches);
            foreach ($matches[1] as $block) {
                $clean = $this->clean($block);
                if (strlen(strip_tags($clean)) > 10) {
                    $parts[] = $clean;
                }
            }
            if (!empty($parts)) {
                return implode("\n\n", $parts);
            }
        }

        // Sinon prendre tout le contenu brut
        return $this->clean($raw);
    }

    private function clean($html)
    {
        // Shortcodes WPBakery structurels
        $html = preg_replace('/\[\/?vc_(?:row|column|section)[^\]]*\]/iu', '', $html);

        // Shortcodes TTS
        $html = preg_replace(
            '/\[\/?(?:audio_player|text_to_speech|lire_texte|listen_btn|read_text|wp_audio)[^\]]*\]/iu',
            '',
            $html
        );

        // Supprimer le <h1> initial (duplique le titre de l'article)
        $html = preg_replace('/^\s*<h1[^>]*>.*?<\/h1>\s*/isu', '', $html);

        // Protéger les shortcodes bibliques inline ([b], [bib], [bvc], [bnv], [brc])
        // avant le nettoyage générique : ContentFilter::render() les interprète au
        // rendu, il ne faut pas les supprimer ici (cf. inc/builder/src/Service/ContentFilter.php).
        $biblicalPlaceholders = [];
        $html = preg_replace_callback(
            '/\[b(?:ib|vc|nv|rc)?\b[^\]]*\].*?\[\/b(?:ib|vc|nv|rc)?\]/is',
            function ($m) use (&$biblicalPlaceholders) {
                $key = "\x02BIB" . count($biblicalPlaceholders) . "\x03";
                $biblicalPlaceholders[$key] = $m[0];
                return $key;
            },
            $html
        );

        // Supprimer les shortcodes WP restants [xxx]
        $html = preg_replace('/\[\/?\w[\w-]*[^\]]*\]/u', '', $html);

        if (!empty($biblicalPlaceholders)) {
            $html = str_replace(array_keys($biblicalPlaceholders), array_values($biblicalPlaceholders), $html);
        }

        // Normaliser les espaces
        $html = preg_replace('/[ \t]+/u', ' ', $html);
        $html = preg_replace('/\n{3,}/u', "\n\n", $html);

        return trim($html);
    }
}
