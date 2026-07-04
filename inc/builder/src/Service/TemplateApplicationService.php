<?php

namespace Schilo\Builder\Service;

use Schilo\Builder\Entity\Section;
use Schilo\Builder\Repository\SectionRepository;

class TemplateApplicationService
{
    const LAST_TEMPLATE_META_KEY = '_schilo_builder_last_template';

    public function applyTemplateToPost($postId, $selectedType)
    {
        $repository = new SectionRepository();
        $sections = $repository->findByPostId((int) $postId);
        $sections = $this->completeSectionsForTemplate((int) $postId, $selectedType, $sections);
        $repository->save((int) $postId, $sections);
        return $sections;
    }

    public function completeSectionsForTemplate($postId, $selectedType, $sections)
    {
        $selectedType = strtoupper(sanitize_key((string) $selectedType));
        if ($selectedType === '' || $selectedType === 'AUTO') {
            $selectedType = (new ArticleTypeService())->resolveType((int) $postId);
        }

        $template = (new TemplateService())->getTemplateForPrefix($selectedType);
        if (empty($template['sections']) || !is_array($template['sections'])) {
            return $sections;
        }

        $existingTypes = array();
        foreach ($sections as $section) {
            if ($section instanceof Section) {
                $existingTypes[] = $section->getType();
            }
        }

        foreach ($template['sections'] as $sectionType) {
            $sectionType = sanitize_key($sectionType);
            if ($sectionType === '' || in_array($sectionType, $existingTypes, true)) {
                continue;
            }

            $section = new Section();
            $section->setType($sectionType)
                ->setTitle($this->labelFromSectionType($sectionType))
                ->setContent('')
                ->setCustomClass('schilo-added-from-template')
                ->setData(array(
                    'source' => 'template',
                    'template' => isset($template['key']) ? $template['key'] : $selectedType,
                ));

            $sections[] = $section;
            $existingTypes[] = $sectionType;
        }

        update_post_meta((int) $postId, self::LAST_TEMPLATE_META_KEY, $selectedType);
        return $sections;
    }

    public function getLastAppliedTemplate($postId)
    {
        return (string) get_post_meta((int) $postId, self::LAST_TEMPLATE_META_KEY, true);
    }

    private function labelFromSectionType($sectionType)
    {
        $types = (new SectionTypeService())->getAllTypes();
        if (isset($types[$sectionType]['label'])) {
            return $types[$sectionType]['label'];
        }
        return ucfirst(str_replace('-', ' ', $sectionType));
    }
}
