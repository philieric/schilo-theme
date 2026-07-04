<?php

namespace Schilo\Builder\Service\Migration;

/**
 * Regroupe les deux sources de contenu disponibles pour la migration d'un
 * article :
 * - le HTML rendu (après exécution des shortcodes via les filtres
 *   WordPress standards) : fiable pour les éléments générés par des
 *   shortcodes qui s'exécutent correctement (ex: titre Wikilogy) ;
 * - le post_content brut (tel qu'enregistré en base) : nécessaire pour
 *   les shortcodes WPBakery dont les attributs peuvent être corrompus par
 *   wptexturize au moment du rendu (guillemets droits transformés en
 *   guillemets typographiques, ce qui casse leur exécution).
 *
 * Chaque extracteur choisit la source la plus fiable pour l'élément qu'il
 * recherche.
 */
class MigrationSourceContent
{
    /** @var int */
    private $postId;

    /** @var string */
    private $renderedHtml;

    /** @var string */
    private $rawContent;

    public function __construct($postId, $renderedHtml, $rawContent)
    {
        $this->postId = (int) $postId;
        $this->renderedHtml = (string) $renderedHtml;
        $this->rawContent = (string) $rawContent;
    }

    public function getPostId()
    {
        return $this->postId;
    }

    public function getRenderedHtml()
    {
        return $this->renderedHtml;
    }

    public function getRawContent()
    {
        return $this->rawContent;
    }
}
