<?php

namespace Schilo\Builder\Service\Migration\Extractors;

use Schilo\Builder\Service\Migration\MigrationDomHelper;
use Schilo\Builder\Service\Migration\MigrationSourceContent;

/**
 * Extrait le titre Wikilogy d'un article, tel qu'affiché dans le bloc
 * `<div class="wikilogy-title">...<div class="title">...</div></div>`
 * généré par le shortcode Wikilogy au rendu de la page.
 *
 * Cet extracteur est générique : il ne dépend d'aucun préfixe particulier
 * (PER, ANN, CTD...) et peut donc être réutilisé tel quel pour tous les
 * types d'articles qui utilisent le même bloc de titre Wikilogy.
 */
class TitleExtractor implements ExtractorInterface
{
    /** @var MigrationDomHelper */
    private $domHelper;

    public function __construct(MigrationDomHelper $domHelper = null)
    {
        $this->domHelper = $domHelper ?: new MigrationDomHelper();
    }

    public function getKey()
    {
        return 'wikilogy_title';
    }

    public function getLabel()
    {
        return 'Titre Wikilogy';
    }

    public function extract(MigrationSourceContent $source)
    {
        $xpath = $this->domHelper->buildXPath($source->getRenderedHtml());

        if (!$xpath) {
            return array();
        }

        $titleBlocks = $this->domHelper->queryByClass($xpath, 'wikilogy-title');

        if (!$titleBlocks || $titleBlocks->length === 0) {
            return array();
        }

        $elements = array();

        foreach ($titleBlocks as $index => $titleBlock) {
            $titleNode = $this->domHelper->queryFirstByClass($xpath, 'title', $titleBlock);
            $shadowNode = $this->domHelper->queryFirstByClass($xpath, 'shadow-title', $titleBlock);
            $pericopeTextNode = $this->domHelper->queryFirstByClass($xpath, 'text', $titleBlock);

            $titleText = $this->domHelper->getText($titleNode);

            if ($titleText === '') {
                continue;
            }

            $elements[] = array(
                'id' => $index === 0 ? $this->getKey() : $this->getKey() . '_' . $index,
                'label' => $this->getLabel(),
                'content' => $titleText,
                'meta' => array(
                    'shadow_text' => $this->domHelper->getText($shadowNode),
                    'pericope_text' => $this->domHelper->getText($pericopeTextNode),
                ),
            );
        }

        return $elements;
    }
}
