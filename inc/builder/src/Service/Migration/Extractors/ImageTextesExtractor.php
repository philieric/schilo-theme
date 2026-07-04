<?php

namespace Schilo\Builder\Service\Migration\Extractors;

use Schilo\Builder\Service\Migration\MigrationSourceContent;

/**
 * Extrait les sections "image-textes" depuis le post_content BRUT.
 *
 * Structure WPBakery apres wptexturize :
 *   [vc_single_image image= BB N PP title= BB Titre BB ...]
 * BB = guillemet ouvrant U+00BB, PP = double prime U+2033.
 *
 * L'attribut title= contient le titre de la section.
 * L'attribut image= contient l'ID attachment directement.
 *
 * Distinction avec DetailsTechniquesExtractor : les [vc_single_image] de
 * "details techniques" n'ont PAS d'attribut title=.
 *
 * Elements retournes par occurrence :
 *   image_textes_heading[_N] : titre de section (depuis title=)
 *   image_textes_image[_N]   : ID attachment    (depuis image=)
 */
class ImageTextesExtractor implements ExtractorInterface
{
    public function getKey()
    {
        return 'image_textes';
    }

    public function getLabel()
    {
        return 'Image-textes';
    }

    public function extract(MigrationSourceContent $source)
    {
        $rawContent = $source->getRawContent();

        if (trim($rawContent) === '') {
            return array();
        }

        $blocks = $this->findImageTextesBlocks($rawContent);

        if (empty($blocks)) {
            return array();
        }

        $elements = array();
        $index    = 0;

        foreach ($blocks as $block) {
            $suffix = $index === 0 ? '' : '_' . $index;
            $num    = $index + 1;

            if ($block['title'] !== '') {
                $elements[] = array(
                    'id'      => $this->getKey() . '_heading' . $suffix,
                    'label'   => 'Image-textes — titre' . ($index > 0 ? ' ' . $num : ''),
                    'content' => $block['title'],
                    'meta'    => array(),
                );
            }

            if ($block['image_id'] > 0) {
                $elements[] = array(
                    'id'      => $this->getKey() . '_image' . $suffix,
                    'label'   => 'Image-textes — image' . ($index > 0 ? ' ' . $num : ''),
                    'content' => (string) $block['image_id'],
                    'meta'    => array('image_id' => $block['image_id']),
                );
            }

            $index++;
        }

        return $elements;
    }

    /**
     * Trouve tous les [vc_single_image] qui ont un attribut title=.
     *
     * @return array<int, array{title:string, image_id:int}>
     */
    private function findImageTextesBlocks($rawContent)
    {
        if (!preg_match_all('/\[vc_single_image\b([^\]]*)\]/iu', $rawContent, $matches, PREG_SET_ORDER)) {
            return array();
        }

        $blocks = array();

        foreach ($matches as $match) {
            $attrs = $match[1];
            $title = $this->extractTitle($attrs);

            if ($title === '') {
                continue;
            }

            $blocks[] = array(
                'title'    => $title,
                'image_id' => $this->extractImageId($attrs),
            );
        }

        return $blocks;
    }

    /**
     * Extrait la valeur de title= en gerant la corruption wptexturize.
     *
     * Forme wptexturize : title= U+00BB valeur U+00BB
     * Forme normale     : title="valeur"
     *
     * \x{BB} est l'escape PCRE unicode pour U+00BB (guillemet BB).
     */
    private function extractTitle($attrs)
    {
        // Capture le contenu entre title= et le prochain attribut ou fin
        // \x{BB} = U+00BB (guillemet typographique wptexturize)
        if (!preg_match('/\btitle=\s*[\x{BB}"\']*(.+?)[\x{BB}"\']\s*(?:\w+=|$)/iu', $attrs, $m)) {
            // Fallback : dernier attribut, rien apres le guillemet fermant
            if (!preg_match('/\btitle=\s*[\x{BB}"\']+(.+?)[\x{BB}"\']+\s*$/iu', $attrs, $m)) {
                return '';
            }
        }

        return html_entity_decode(trim($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * Extrait l'ID depuis image= en gerant la corruption wptexturize.
     * (image= U+00BB 44695 U+2033  au lieu de image="44695")
     */
    private function extractImageId($attrs)
    {
        if (preg_match('/\bimage=\s*[^0-9\]]*(\d+)/iu', $attrs, $m)) {
            return (int) $m[1];
        }

        return 0;
    }
}
