<?php

namespace Schilo\Builder\Service;

class ArticleTypeService
{
    const META_KEY = '_schilo_builder_type';

    public function getAvailableTypes()
    {
        $types = array(
            'AUTO' => array(
                'label' => 'Automatique',
                'description' => 'Détection automatique depuis le préfixe du titre',
            ),
        );

        $templateService = new TemplateService();

        $templates = method_exists($templateService, 'getActiveTemplates')
            ? $templateService->getActiveTemplates()
            : $templateService->getAllTemplates();

        if (!is_array($templates)) {
            $templates = array();
        }

        foreach ($templates as $templateKey => $templateConfig) {
            if (empty($templateConfig['active'])) {
                continue;
            }

            $templateKey = strtoupper((string) $templateKey);

            $types[$templateKey] = array(
                'label' => $templateKey . ' - ' . (isset($templateConfig['label']) ? $templateConfig['label'] : $templateKey),
                'description' => isset($templateConfig['description']) ? $templateConfig['description'] : '',
            );
        }

        return $types;
    }

    public function getSelectedType($postId)
    {
        $type = get_post_meta((int) $postId, self::META_KEY, true);
        $type = strtoupper(sanitize_key((string) $type));

        return $type ? $type : 'AUTO';
    }

    public function saveSelectedType($postId, $type)
    {
        $type = strtoupper(sanitize_key((string) $type));

        if ($type === '') {
            $type = 'AUTO';
        }

        update_post_meta((int) $postId, self::META_KEY, $type);
    }

    public function resolveType($postId)
    {
        $selected = $this->getSelectedType($postId);

        if ($selected !== 'AUTO') {
            return $selected;
        }

        return (new PrefixDetector())->detectFromPostId((int) $postId);
    }
}
