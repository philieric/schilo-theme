<?php

namespace Schilo\Builder\Service;

class ContentFilter
{
    public function render($content)
    {
        $content = (string) $content;

        // Protéger les shortcodes bibliques inline ([b], [bib], [bvc], [bnv], [brc]) avant wpautop :
        // leur rendu HTML est multi-lignes (spans imbriqués) et wpautop verrait les \n
        // internes comme des sauts de paragraphe, brisant le flux du texte.
        $placeholders = [];
        $content = preg_replace_callback(
            '/\[b(?:ib|vc|nv|rc)?\b[^\]]*\].*?\[\/b(?:ib|vc|nv|rc)?\]/is',
            function ($m) use (&$placeholders) {
                $key = "\x02SC" . count($placeholders) . "\x03";
                $placeholders[$key] = do_shortcode($m[0]);
                return $key;
            },
            $content
        );

        $content = wpautop($content);

        // Restaurer les shortcodes rendus après wpautop
        if (!empty($placeholders)) {
            $content = str_replace(array_keys($placeholders), array_values($placeholders), $content);
        }

        return wp_kses_post($content);
    }
}
