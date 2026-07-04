<?php

namespace Schilo\Builder\Service\Migration\Extractors;

use Schilo\Builder\Service\Migration\MigrationDomHelper;
use Schilo\Builder\Service\Migration\MigrationSourceContent;

/**
 * Extrait les images "libres" d'un article depuis le HTML rendu.
 *
 * Ne capte pas les images déjà extraites par DetailsTechniquesExtractor
 * (dont l'attachment ID est lu directement dans le raw content) : cet
 * extracteur est réservé aux images WPBakery dont l'identifiant ne peut
 * pas être obtenu simplement depuis le raw content, et qui sont destinées
 * à des sections ayant un champ image_id (image-textes, details-colonnes…).
 *
 * Chaque élément retourné :
 *   id      : "image" pour la 1re, "image_1", "image_2"…
 *   content : URL brute de l'image (pour aperçu dans l'assistant)
 *   meta    : ['image_url' => url, 'alt_text' => alt]
 *
 * La résolution URL → attachment ID WordPress (via attachment_url_to_postid())
 * est délégée à MigrationApplier::buildSection() — case 'image_url_auto' —
 * pour maintenir les extracteurs découplés de WordPress.
 *
 * Exclut les images qui font partie d'une .wikilogy-title ou d'une
 * navigation/pagination (généralement des icônes ou décorations).
 */
class ImageExtractor implements ExtractorInterface
{
    /** @var MigrationDomHelper */
    private $domHelper;

    public function __construct(MigrationDomHelper $domHelper = null)
    {
        $this->domHelper = $domHelper ?: new MigrationDomHelper();
    }

    public function getKey()
    {
        return 'image';
    }

    public function getLabel()
    {
        return 'Images';
    }

    public function extract(MigrationSourceContent $source)
    {
        $xpath = $this->domHelper->buildXPath($source->getRenderedHtml());

        if (!$xpath) {
            return array();
        }

        // Tous les <img> présents dans le rendu
        $imgNodes = $xpath->query('//img');

        if (!$imgNodes || $imgNodes->length === 0) {
            return array();
        }

        $elements = array();
        $index    = 0;

        foreach ($imgNodes as $imgNode) {
            $src = $this->domHelper->getAttribute($imgNode, 'src');
            $alt = $this->domHelper->getAttribute($imgNode, 'alt');

            if ($src === '') {
                continue;
            }

            // Exclure les petites icônes (largeur ou hauteur <= 30 px déclarées
            // dans l'attribut HTML — indique navigation, flèche, avatar, etc.)
            if ($this->isDecorative($imgNode)) {
                continue;
            }

            // Exclure les images à l'intérieur d'un bloc de navigation/pagination
            if ($this->isInsideNavigation($xpath, $imgNode)) {
                continue;
            }

            $suffix = $index === 0 ? '' : '_' . $index;

            $elements[] = array(
                'id'      => $this->getKey() . $suffix,
                'label'   => "Image " . ($index + 1) . ($alt !== '' ? ' — ' . mb_substr($alt, 0, 60, 'UTF-8') : ''),
                'content' => $src,
                'meta'    => array(
                    'image_url' => $src,
                    'alt_text'  => $alt,
                ),
            );

            $index++;
        }

        return $elements;
    }

    /**
     * Retourne true si le noeud <img> a des dimensions déclarées qui indiquent
     * une icône ou un élément décoratif (largeur ou hauteur <= 30 px).
     */
    private function isDecorative(\DOMNode $imgNode)
    {
        $width  = (int) $this->domHelper->getAttribute($imgNode, 'width');
        $height = (int) $this->domHelper->getAttribute($imgNode, 'height');

        if ($width > 0 && $width <= 30) {
            return true;
        }

        if ($height > 0 && $height <= 30) {
            return true;
        }

        return false;
    }

    /**
     * Retourne true si le noeud <img> est à l'intérieur d'un conteneur de
     * navigation (pagination, menu, barre d'outils).
     */
    private function isInsideNavigation(\DOMXPath $xpath, \DOMNode $imgNode)
    {
        $navClasses = array('nav', 'navigation', 'pagination', 'menu', 'wikilogy-nav', 'wikilogy-pagination');

        $ancestor = $imgNode->parentNode;

        while ($ancestor && $ancestor->nodeType === XML_ELEMENT_NODE) {
            $class = $this->domHelper->getAttribute($ancestor, 'class');

            foreach ($navClasses as $navClass) {
                if (strpos(' ' . $class . ' ', ' ' . $navClass . ' ') !== false) {
                    return true;
                }
            }

            $ancestor = $ancestor->parentNode;
        }

        return false;
    }
}
