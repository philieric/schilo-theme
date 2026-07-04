<?php

namespace Schilo\Builder\Service\Migration;

use Schilo\Builder\Service\Migration\Extractors\ExtractorInterface;
use Schilo\Builder\Service\Migration\Extractors\TitleExtractor;
use Schilo\Builder\Service\Migration\Extractors\ConsultationExtractor;
use Schilo\Builder\Service\Migration\Extractors\EvangilesExtractor;
use Schilo\Builder\Service\Migration\Extractors\DetailsTechniquesExtractor;
use Schilo\Builder\Service\Migration\Extractors\ImageExtractor;
use Schilo\Builder\Service\Migration\Extractors\ReferencesExtractor;
use Schilo\Builder\Service\Migration\Extractors\CommentaireExtractor;
use Schilo\Builder\Service\Migration\Extractors\ImageTextesExtractor;
use Schilo\Builder\Service\Migration\Extractors\SectionTextesExtractor;
use Schilo\Builder\Service\Migration\Extractors\PlainContentExtractor;

/**
 * Registre central des extracteurs de migration disponibles.
 *
 * Permet d'ajouter facilement de nouveaux extracteurs (Consultation,
 * Textes bibliques, Détails techniques...) sans toucher au reste du
 * code de migration : il suffit de les enregistrer ici.
 */
class ExtractorRegistry
{
    /** @var ExtractorInterface[] */
    private $extractors = array();

    public function __construct()
    {
        $this->registerDefaults();
    }

    private function registerDefaults()
    {
        $this->register(new TitleExtractor());
        $this->register(new ConsultationExtractor());
        $this->register(new EvangilesExtractor());
        $this->register(new DetailsTechniquesExtractor());
        $this->register(new ImageExtractor());
        $this->register(new ReferencesExtractor());
        $this->register(new CommentaireExtractor());
        $this->register(new ImageTextesExtractor());
        $this->register(new SectionTextesExtractor());
        $this->register(new PlainContentExtractor());
    }

    public function register(ExtractorInterface $extractor)
    {
        $this->extractors[$extractor->getKey()] = $extractor;
        return $this;
    }

    /**
     * @return ExtractorInterface[]
     */
    public function getAll()
    {
        return $this->extractors;
    }

    public function get($key)
    {
        return isset($this->extractors[$key]) ? $this->extractors[$key] : null;
    }

    /**
     * Exécute tous les extracteurs enregistrés sur le contenu d'un article
     * (rendu HTML + post_content brut), et retourne la liste fusionnée de
     * tous les éléments trouvés.
     *
     * @param MigrationSourceContent $source
     * @return array<int, array> Chaque élément inclut en plus 'extractor_key'.
     */
    public function extractAll(MigrationSourceContent $source)
    {
        $allElements = array();

        foreach ($this->extractors as $extractor) {
            $elements = $extractor->extract($source);

            foreach ($elements as $element) {
                $element['extractor_key'] = $extractor->getKey();
                $allElements[] = $element;
            }
        }

        return $allElements;
    }
}
