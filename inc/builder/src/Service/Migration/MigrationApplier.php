<?php

namespace Schilo\Builder\Service\Migration;

use Schilo\Builder\Entity\Section;
use Schilo\Builder\Repository\SectionRepository;
use Schilo\Builder\Service\SectionStructureService;
use Schilo\Builder\Service\TemplateService;

/**
 * Applique un mapping de migration (éléments extraits + correspondances
 * section/champ) à un article réel : construit les sections Schilo Builder
 * correspondantes et les enregistre.
 *
 * Respecte l'ordre du template : les sections du template apparaissent
 * dans l'ordre défini, qu'elles aient reçu du contenu migré ou non
 * (auquel cas elles sont créées vides, prêtes à être complétées).
 */
class MigrationApplier
{
    /** @var SectionRepository */
    private $sectionRepository;

    /** @var TemplateService */
    private $templateService;

    public function __construct(SectionRepository $sectionRepository = null, TemplateService $templateService = null)
    {
        $this->sectionRepository = $sectionRepository ?: new SectionRepository();
        $this->templateService = $templateService ?: new TemplateService();
    }

    /**
     * @param int    $postId
     * @param string $prefix      Préfixe du template à appliquer (ex: "PER").
     * @param array  $elements    Liste des éléments extraits (id => ..., content => ..., meta => ...).
     * @param array  $assignments [element_id => ['section_type' => ..., 'field' => ...]]
     * @param bool   $replaceExisting Si vrai, remplace les sections existantes de l'article.
     * @return array Les sections Schilo Builder créées (Section[]).
     */
    public function apply($postId, $prefix, array $elements, array $assignments, $replaceExisting = true)
    {
        $postId = (int) $postId;
        $template = $this->templateService->getTemplateForPrefix($prefix);
        $templateSections = !empty($template['sections']) ? (array) $template['sections'] : array();

        $elementsById = array();
        foreach ($elements as $element) {
            if (isset($element['id'])) {
                $elementsById[$element['id']] = $element;
            }
        }

        // Regroupe les éléments assignés par [section_type => [field => [elements...]]].
        $grouped = array();

        foreach ($assignments as $elementId => $assignment) {
            if (!isset($elementsById[$elementId])) {
                continue;
            }

            $sectionType = isset($assignment['section_type']) ? $assignment['section_type'] : '';
            $field = isset($assignment['field']) ? $assignment['field'] : '';

            if ($sectionType === '' || $sectionType === 'ignore' || $field === '') {
                continue;
            }

            $grouped[$sectionType][$field][] = $elementsById[$elementId];
        }

        $structureService = new SectionStructureService();
        $sections = array();
        $order = 0;

        // 1. Sections du template, dans l'ordre, avec contenu assigné ou vides.
        foreach ($templateSections as $sectionType) {
            $sectionFields = isset($grouped[$sectionType]) ? $grouped[$sectionType] : array();
            unset($grouped[$sectionType]);

            $section = $this->buildSection($sectionType, $sectionFields, $structureService);
            $section->setOrder($order++);
            $sections[] = $section;
        }

        // 2. Éléments assignés vers des types de section absents du template
        //    (cas marginal, mais on ne perd pas le contenu) : ajoutés à la suite.
        foreach ($grouped as $sectionType => $sectionFields) {
            $section = $this->buildSection($sectionType, $sectionFields, $structureService);
            $section->setOrder($order++);
            $sections[] = $section;
        }

        if ($replaceExisting) {
            $this->sectionRepository->save($postId, $sections);
        } else {
            $existing = $this->sectionRepository->findByPostId($postId);
            $this->sectionRepository->save($postId, array_merge($existing, $sections));
        }

        return $sections;
    }

    /**
     * Construit une Section à partir des champs assignés pour un type donné.
     *
     * @param array $sectionFields [field => [elements...]]
     */
    private function buildSection($sectionType, array $sectionFields, SectionStructureService $structureService)
    {
        $section = new Section();
        $section->setType($sectionType);
        $section->setCustomClass('schilo-migrated');

        $title = '';
        $content = '';
        $rawData = array();
        $linksAuto = array();
        $versetsAuto = array();

        foreach ($sectionFields as $field => $fieldElements) {
            switch ($field) {
                case 'section_title':
                    if ($title === '' && !empty($fieldElements)) {
                        $title = trim((string) $fieldElements[0]['content']);
                    }
                    break;

                case 'content':
                    $parts = array();
                    foreach ($fieldElements as $fieldElement) {
                        $parts[] = (string) $fieldElement['content'];
                    }
                    $content = implode("\n\n", array_filter($parts, function ($part) {
                        return trim($part) !== '';
                    }));
                    break;

                case 'links_auto':
                    foreach ($fieldElements as $fieldElement) {
                        $url = isset($fieldElement['meta']['url']) ? $fieldElement['meta']['url'] : '';
                        $label = trim((string) $fieldElement['content']);

                        if ($label === '' && $url === '') {
                            continue;
                        }

                        $linksAuto[] = array('label' => $label, 'url' => $url);
                    }
                    break;

                case 'versets_auto':
                    foreach ($fieldElements as $fieldElement) {
                        $reference = trim((string) $fieldElement['content']);
                        $evLabel   = isset($fieldElement['meta']['evangelist_label']) ? (string) $fieldElement['meta']['evangelist_label'] : '';
                        $evClass   = isset($fieldElement['meta']['class']) ? (string) $fieldElement['meta']['class'] : 'citation-bible';

                        // Si le contenu est un shortcode [bnv]label[/bnv], vider la référence :
                        // la vue construira elle-même [bnv]label[/bnv] depuis le champ label.
                        if (preg_match('/^\[bnv\](.*?)\[\/bnv\]$/is', $reference)) {
                            $reference = '';
                        }

                        if ($reference === '' && $evLabel === '') {
                            continue;
                        }

                        $versetsAuto[] = array(
                            'reference' => $reference,
                            'label'     => $evLabel,
                            'class'     => $evClass,
                        );
                    }
                    break;

                case 'image_id':
                    if (!empty($fieldElements)) {
                        // Priorité : meta['image_id'] (déjà résolu par l'extracteur)
                        // Fallback : content (string numérique ou URL)
                        $el = $fieldElements[0];
                        if (!empty($el['meta']['image_id'])) {
                            $rawData['image_id'] = absint($el['meta']['image_id']);
                        } elseif (ctype_digit(trim((string) $el['content']))) {
                            $rawData['image_id'] = absint($el['content']);
                        } elseif (!empty($el['content'])) {
                            $resolved = $this->resolveImageUrl((string) $el['content']);
                            if ($resolved > 0) {
                                $rawData['image_id'] = $resolved;
                            }
                        }
                    }
                    break;

                case 'image_url_auto':
                    if (!empty($fieldElements)) {
                        $el = $fieldElements[0];
                        $imageUrl = isset($el['meta']['image_url']) ? $el['meta']['image_url'] : (string) $el['content'];
                        // Si c'est un ID numérique (extracteur qui donne déjà l'ID), on l'utilise directement
                        if (ctype_digit(trim($imageUrl))) {
                            $rawData['image_id'] = absint($imageUrl);
                        } elseif ($imageUrl !== '') {
                            $attachmentId = $this->resolveImageUrl($imageUrl);
                            if ($attachmentId > 0) {
                                $rawData['image_id'] = $attachmentId;
                            }
                        }
                    }
                    break;

                case 'intro':
                case 'texte_libre':
                    $parts = array();
                    foreach ($fieldElements as $fieldElement) {
                        $parts[] = trim((string) $fieldElement['content']);
                    }
                    $rawData[$field] = implode(' ', array_filter($parts));
                    break;

                default:
                    // Champs spécifiques à un type de section (image_id, lieu, date...) :
                    // on prend la première valeur assignée.
                    if (!empty($fieldElements)) {
                        $rawData[$field] = (string) $fieldElements[0]['content'];
                    }
                    break;
            }
        }

        if (!empty($linksAuto)) {
            $rawData['links'] = $linksAuto;
        }

        if (!empty($versetsAuto)) {
            $rawData['versets'] = $versetsAuto;
            $rawData['versets_present'] = 1;
        }

        $section->setTitle($title);
        $section->setContent($content);

        $normalizedData = $structureService->normalizeSectionData($sectionType, $rawData);
        $section->setData($normalizedData);

        return $section;
    }
    /**
     * Resout une URL d'image en attachment ID WordPress.
     * Factorisee ici pour etre partagee par image_url_auto et toute
     * extension future sans dupliquer l'appel a attachment_url_to_postid().
     *
     * @return int 0 si non trouve
     */
    private function resolveImageUrl($imageUrl)
    {
        $imageUrl = (string) $imageUrl;

        if ($imageUrl === '') {
            return 0;
        }

        return (int) attachment_url_to_postid($imageUrl);
    }
}