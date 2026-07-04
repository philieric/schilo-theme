<?php

namespace Schilo\Builder\Service\Migration\Extractors;

use Schilo\Builder\Service\Migration\MigrationDomHelper;
use Schilo\Builder\Service\Migration\MigrationSourceContent;

/**
 * Extrait le bloc "Articles lies" genere par Wikilogy (typiquement intitule
 * "Voici quelques titres qui peuvent vous interesser") depuis le HTML rendu.
 *
 * Ce bloc est produit par un shortcode Wikilogy qui s'execute correctement
 * et n'est pas affecte par wptexturize => on utilise le HTML rendu.
 *
 * Retourne les liens dans le meme format que ConsultationExtractor (id
 * references_link, references_link_1, references_link_2...) vers le champ
 * links_auto, ce qui permet de reutiliser le case 'links_auto' existant
 * dans MigrationApplier::buildSection() sans modification.
 */
class ReferencesExtractor implements ExtractorInterface
{
    /** @var MigrationDomHelper */
    private $domHelper;

    public function __construct(MigrationDomHelper $domHelper = null)
    {
        $this->domHelper = $domHelper ?: new MigrationDomHelper();
    }

    public function getKey()
    {
        return 'references';
    }

    public function getLabel()
    {
        return 'Articles lies (Wikilogy)';
    }

    public function extract(MigrationSourceContent $source)
    {
        $xpath = $this->domHelper->buildXPath($source->getRenderedHtml());

        if (!$xpath) {
            return array();
        }

        $result = $this->findRelatedLinks($xpath);

        if (empty($result['links'])) {
            return array();
        }

        $elements = array();

        if ($result['heading'] !== '') {
            $elements[] = array(
                'id'      => $this->getKey() . '_heading',
                'label'   => 'Articles lies — titre',
                'content' => $result['heading'],
                'meta'    => array(),
            );
        }

        foreach ($result['links'] as $index => $linkData) {
            $suffix = $index === 0 ? '' : '_' . $index;

            $elements[] = array(
                'id'      => $this->getKey() . '_link' . $suffix,
                'label'   => 'Articles lies — lien ' . ($index + 1),
                'content' => $linkData['label'],
                'meta'    => array(
                    'url' => $linkData['url'],
                ),
            );
        }

        return $elements;
    }

    /**
     * Cherche la grille d'articles lies dans le HTML rendu.
     *
     * Strategies par ordre de preference :
     * 1. Bloc dont le titre contient "Voici quelques titres" ou
     *    "Articles qui peuvent vous interesser" (insensible a la casse).
     * 2. Classe CSS wikilogy-blog-list, wikilogy-related-posts ou equivalents.
     * 3. Repli : cherche un <ul>/<div> contenant plusieurs <li> avec <a> apres
     *    le dernier titre de section de contenu.
     *
     * @return array{heading:string, links:array<int, array{label:string, url:string}>}
     */
    private function findRelatedLinks(\DOMXPath $xpath)
    {
        $empty = array('heading' => '', 'links' => array());

        // Strategie 1 : titre explicite
        $headingNodes = $xpath->query(
            '//*[contains(translate(., '
            . '"ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz"), '
            . '"voici quelques") '
            . 'or contains(translate(., "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz"), "peuvent vous int")]'
        );

        if ($headingNodes && $headingNodes->length > 0) {
            $headingNode = $headingNodes->item(0);
            $links       = $this->extractLinksAfterNode($xpath, $headingNode);

            if (!empty($links)) {
                return array('heading' => trim($this->domHelper->getText($headingNode)), 'links' => $links);
            }
        }

        // Strategie 2 : classe CSS Wikilogy connue
        $knownClasses = array('wikilogy-blog-list', 'wikilogy-related', 'wikilogy-related-posts', 'related-posts');

        foreach ($knownClasses as $class) {
            $containers = $this->domHelper->queryByClass($xpath, $class);

            if ($containers && $containers->length > 0) {
                $links = $this->extractLinksFromContainer($xpath, $containers->item(0));

                if (!empty($links)) {
                    return array('heading' => '', 'links' => $links);
                }
            }
        }

        return $empty;
    }

    /**
     * Collecte les liens <a> significatifs dans le premier conteneur
     * (div, ul, section…) qui suit immediatement $node dans le DOM.
     */
    private function extractLinksAfterNode(\DOMXPath $xpath, \DOMNode $node)
    {
        $sibling = $node->nextSibling;

        while ($sibling && $sibling->nodeType !== XML_ELEMENT_NODE) {
            $sibling = $sibling->nextSibling;
        }

        if (!$sibling) {
            // Si pas de frere, essaie le parent->nextSibling
            $parent = $node->parentNode;

            if ($parent) {
                $sibling = $parent->nextSibling;

                while ($sibling && $sibling->nodeType !== XML_ELEMENT_NODE) {
                    $sibling = $sibling->nextSibling;
                }
            }
        }

        if (!$sibling) {
            return array();
        }

        return $this->extractLinksFromContainer($xpath, $sibling);
    }

    /**
     * Extrait tous les liens <a href="...">Texte</a> a l'interieur d'un
     * conteneur donne, en filtrant les doublons d'URL.
     *
     * @return array<int, array{label:string, url:string}>
     */
    private function extractLinksFromContainer(\DOMXPath $xpath, \DOMNode $container)
    {
        $aNodes = $xpath->query('.//a[@href]', $container);

        if (!$aNodes || $aNodes->length === 0) {
            return array();
        }

        $links = array();
        $seenUrls = array();

        foreach ($aNodes as $aNode) {
            $url   = trim($this->domHelper->getAttribute($aNode, 'href'));
            $label = $this->domHelper->getText($aNode);

            if ($url === '' || $label === '') {
                continue;
            }

            // Deduplique par URL
            if (isset($seenUrls[$url])) {
                continue;
            }

            $seenUrls[$url] = true;

            $links[] = array(
                'label' => $label,
                'url'   => $url,
            );
        }

        return $links;
    }
}
