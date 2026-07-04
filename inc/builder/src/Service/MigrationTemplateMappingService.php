<?php

namespace Schilo\Builder\Service;

class MigrationTemplateMappingService
{
    const OPTION_KEY = 'schilo_builder_migration_template_mapping';

    /**
     * Retourne le mapping enregistré pour un template donné.
     *
     * @return array [element_id => ['type' => section_type, 'field' => field_key]]
     */
    public function getMappingForTemplate($templateKey)
    {
        $all = $this->getAllMappings();
        $templateKey = strtoupper(sanitize_key((string) $templateKey));

        return isset($all[$templateKey]) ? $all[$templateKey] : array();
    }

    public function getAllMappings()
    {
        $saved = get_option(self::OPTION_KEY, array());

        return is_array($saved) ? $saved : array();
    }

    /**
     * Enregistre (ou met à jour) le mapping pour un template donné.
     *
     * @param string $templateKey
     * @param array  $mapping [element_id => section_type|'ignore']
     * @param array  $fieldMapping [element_id => field_key]
     */
    public function saveMappingForTemplate($templateKey, $mapping, $fieldMapping = array())
    {
        $templateKey = strtoupper(sanitize_key((string) $templateKey));

        if ($templateKey === '') {
            return;
        }

        $all = $this->getAllMappings();
        $clean = array();

        if (is_array($mapping)) {
            foreach ($mapping as $elementId => $sectionType) {
                $elementId = sanitize_key((string) $elementId);
                $sectionType = sanitize_key((string) $sectionType);

                if ($elementId === '' || $sectionType === '' || $sectionType === 'ignore') {
                    continue;
                }

                $fieldKey = isset($fieldMapping[$elementId]) ? sanitize_key((string) $fieldMapping[$elementId]) : 'content';

                if ($fieldKey === '') {
                    $fieldKey = 'content';
                }

                $clean[$elementId] = array(
                    'type' => $sectionType,
                    'field' => $fieldKey,
                );
            }
        }

        $all[$templateKey] = $clean;

        update_option(self::OPTION_KEY, $all, false);
    }

    public function resetTemplate($templateKey)
    {
        $templateKey = strtoupper(sanitize_key((string) $templateKey));

        $all = $this->getAllMappings();

        if (isset($all[$templateKey])) {
            unset($all[$templateKey]);
            update_option(self::OPTION_KEY, $all, false);
        }
    }
}
