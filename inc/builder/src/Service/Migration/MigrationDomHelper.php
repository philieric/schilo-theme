<?php

namespace Schilo\Builder\Service\Migration;

/**
 * Aide au parsing DOM du HTML rendu d'un article, partagée par les
 * extracteurs de migration. Centralise la gestion des particularités
 * de DOMDocument (encodage UTF-8, HTML partiel/malformé, etc.) pour
 * que chaque extracteur n'ait pas à la réimplémenter.
 */
class MigrationDomHelper
{
    /**
     * Construit un DOMXPath utilisable pour interroger le HTML rendu
     * via des sélecteurs CSS simples (voir cssToXpath ci-dessous) ou
     * directement en XPath.
     */
    public function buildXPath($html)
    {
        $html = (string) $html;

        if (trim($html) === '') {
            return null;
        }

        $document = new \DOMDocument('1.0', 'UTF-8');

        $internalErrors = libxml_use_internal_errors(true);

        // On encapsule dans un wrapper UTF-8 pour éviter que DOMDocument
        // ne réinterprète mal les caractères accentués.
        $wrapped = '<?xml encoding="UTF-8"><div id="schilo-migration-root">' . $html . '</div>';

        $document->loadHTML($wrapped, LIBXML_NOERROR | LIBXML_NOWARNING);

        libxml_clear_errors();
        libxml_use_internal_errors($internalErrors);

        return new \DOMXPath($document);
    }

    /**
     * Sélectionne les noeuds correspondant à une classe CSS donnée,
     * optionnellement restreints à un nom de balise.
     */
    public function queryByClass(\DOMXPath $xpath, $className, $tag = '*')
    {
        $className = trim((string) $className);

        $expression = sprintf(
            '//%s[contains(concat(" ", normalize-space(@class), " "), " %s ")]',
            $tag,
            $className
        );

        return $xpath->query($expression);
    }

    /**
     * Retourne le premier noeud correspondant à une classe CSS donnée,
     * éventuellement à l'intérieur d'un noeud contexte donné.
     */
    public function queryFirstByClass(\DOMXPath $xpath, $className, \DOMNode $context = null, $tag = '*')
    {
        $className = trim((string) $className);

        $expression = sprintf(
            './/%s[contains(concat(" ", normalize-space(@class), " "), " %s ")]',
            $tag,
            $className
        );

        if ($context === null) {
            $expression = '/' . ltrim($expression, '.');
        }

        $nodes = $context !== null ? $xpath->query($expression, $context) : $xpath->query($expression);

        return ($nodes && $nodes->length > 0) ? $nodes->item(0) : null;
    }

    /**
     * Retourne le texte (trimmé) d'un noeud, ou chaîne vide si le noeud est nul.
     */
    public function getText(\DOMNode $node = null)
    {
        if ($node === null) {
            return '';
        }

        return trim(preg_replace('/\s+/u', ' ', $node->textContent));
    }

    /**
     * Retourne le HTML interne (innerHTML) d'un noeud.
     */
    public function getInnerHtml(\DOMNode $node = null)
    {
        if ($node === null) {
            return '';
        }

        $html = '';
        $document = $node->ownerDocument;

        foreach ($node->childNodes as $child) {
            $html .= $document->saveHTML($child);
        }

        return trim($html);
    }

    /**
     * Retourne le premier noeud correspondant à un id donné.
     */
    public function queryById(\DOMXPath $xpath, $id)
    {
        $id = trim((string) $id);

        $nodes = $xpath->query(sprintf('//*[@id="%s"]', $id));

        return ($nodes && $nodes->length > 0) ? $nodes->item(0) : null;
    }

    /**
     * Retourne tous les liens `<a>` contenus dans un noeud donné.
     */
    public function queryLinks(\DOMXPath $xpath, \DOMNode $context)
    {
        return $xpath->query('.//a', $context);
    }

    /**
     * Retourne la valeur d'un attribut d'un noeud, ou chaîne vide si absent.
     */
    public function getAttribute(\DOMNode $node = null, $attribute = 'href')
    {
        if ($node === null || !($node instanceof \DOMElement)) {
            return '';
        }

        return trim((string) $node->getAttribute($attribute));
    }
}
