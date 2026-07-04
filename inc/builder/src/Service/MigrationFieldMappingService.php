<?php

namespace Schilo\Builder\Service;

class MigrationFieldMappingService
{
    const OPTION_KEY = 'schilo_builder_migration_field_mapping';

    public function getDefaultMapping()
    {
        return array(
            '__default__' => array(
                array('value' => 'content', 'label' => 'Contenu (éditeur)'),
                array('value' => 'section_title', 'label' => 'Titre de la section'),
            ),
            'liens-articles' => array(
                array('value' => 'section_title', 'label' => 'Titre de la section'),
                array('value' => 'intro', 'label' => 'Texte d’introduction'),
                array('value' => 'texte_libre', 'label' => 'Texte libre'),
                array('value' => 'links_auto', 'label' => 'Liste de liens (extraction "Vous pouvez consulter l’annexe...")'),
            ),
            'titre-simple' => array(
                array('value' => 'section_title', 'label' => 'Titre de la section'),
            ),
        );
    }

    public function getAllMappings()
    {
        $saved = get_option(self::OPTION_KEY, array());
        $defaults = $this->getDefaultMapping();

        if (!is_array($saved) || empty($saved)) {
            return $defaults;
        }

        $clean = $this->sanitizeMappings($saved);

        if (!isset($clean['__default__']) || empty($clean['__default__'])) {
            $clean['__default__'] = $defaults['__default__'];
        }

        return !empty($clean) ? $clean : $defaults;
    }

    public function saveMappings($rawMappings)
    {
        $clean = $this->sanitizeMappings($rawMappings);

        if (!isset($clean['__default__']) || empty($clean['__default__'])) {
            $clean['__default__'] = $this->getDefaultMapping()['__default__'];
        }

        update_option(self::OPTION_KEY, $clean, false);
    }

    public function resetDefaults()
    {
        update_option(self::OPTION_KEY, $this->getDefaultMapping(), false);
    }

    private function sanitizeMappings($rawMappings)
    {
        $clean = array();

        if (!is_array($rawMappings)) {
            return $clean;
        }

        foreach ($rawMappings as $row) {
            if (!is_array($row)) {
                continue;
            }

            $typeKey = isset($row['type']) ? sanitize_key((string) $row['type']) : '';

            if ($typeKey === '') {
                continue;
            }

            $fields = array();

            if (isset($row['fields']) && is_array($row['fields'])) {
                foreach ($row['fields'] as $field) {
                    if (!is_array($field)) {
                        continue;
                    }

                    $value = isset($field['value']) ? sanitize_key((string) $field['value']) : '';
                    $label = isset($field['label']) ? sanitize_text_field((string) $field['label']) : '';

                    if ($value === '' || $label === '') {
                        continue;
                    }

                    $fields[] = array('value' => $value, 'label' => $label);
                }
            }

            if (!empty($fields)) {
                $clean[$typeKey] = $fields;
            }
        }

        return $clean;
    }
}
