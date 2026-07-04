<?php

namespace Schilo\Builder\Service;

class TemplateService
{
    const OPTION_TEMPLATES = 'schilo_builder_templates';

    public function getDefaultTemplates()
    {
        return array(
            'DEFAULT' => array(
                'key' => 'DEFAULT',
                'label' => 'Template standard',
                'description' => "Modele generique utilise si aucun type specifique n'est trouve.",
                'active' => 1,
                'sections' => array('liens-articles', 'intro', 'paragraphe', 'references', 'conclusion'),
            ),
            'PER' => array(
                'key' => 'PER',
                'label' => 'Présentation',
                'description' => 'Modèle conseillé pour les articles PER.',
                'active' => 1,
                'sections' => array('liens-articles', 'intro', 'contexte', 'detail-technique-img-droite', 'paragraphe', 'evangiles', 'references', 'conclusion'),
            ),
            'CTD' => array(
                'key' => 'CTD',
                'label' => 'Contradiction',
                'description' => 'Modèle conseillé pour les articles CTD.',
                'active' => 1,
                'sections' => array('liens-articles', 'intro', 'contexte', 'paragraphe', 'references', 'conclusion'),
            ),
            'ANN' => array(
                'key' => 'ANN',
                'label' => 'Annonce',
                'description' => 'Modèle conseillé pour les annonces.',
                'active' => 1,
                'sections' => array('liens-articles', 'intro', 'paragraphe', 'image-textes', 'conclusion'),
            ),
        );
    }

    public function getAllTemplates()
    {
        $saved = get_option(self::OPTION_TEMPLATES, array());

        if (!is_array($saved) || empty($saved)) {
            return $this->getDefaultTemplates();
        }

        $clean = array();

        foreach ($saved as $key => $template) {
            if (!is_array($template)) {
                continue;
            }

            $templateKey = isset($template['key'])
                ? strtoupper(preg_replace('/[^A-Za-z]/', '', (string) $template['key']))
                : strtoupper(preg_replace('/[^A-Za-z]/', '', (string) $key));

            $templateKey = substr($templateKey, 0, 8);

            if ($templateKey === '') {
                continue;
            }

            $sections = array();

            if (isset($template['sections']) && is_array($template['sections'])) {
                foreach ($template['sections'] as $sectionKey) {
                    $sectionKey = sanitize_key($sectionKey);

                    if ($sectionKey !== '') {
                        $sections[] = $sectionKey;
                    }
                }
            }

            $sections = array_values(array_unique($sections));

            if (empty($sections)) {
                $defaultTemplates = $this->getDefaultTemplates();

                if (isset($defaultTemplates[$templateKey]['sections'])) {
                    $sections = $defaultTemplates[$templateKey]['sections'];
                }
            }

            $clean[$templateKey] = array(
                'key' => $templateKey,
                'label' => isset($template['label']) ? sanitize_text_field($template['label']) : $templateKey,
                'description' => isset($template['description']) ? sanitize_text_field($template['description']) : '',
                'active' => !empty($template['active']) ? 1 : 0,
                'sections' => $sections,
            );
        }

        return !empty($clean) ? $clean : $this->getDefaultTemplates();
    }

    public function getActiveTemplates()
    {
        $active = array();

        foreach ($this->getAllTemplates() as $key => $template) {
            if (!empty($template['active'])) {
                $active[$key] = $template;
            }
        }

        return $active;
    }

    public function getTemplateForPrefix($prefix)
    {
        $prefix = strtoupper(sanitize_key((string) $prefix));
        $templates = $this->getAllTemplates();

        if (isset($templates[$prefix]) && !empty($templates[$prefix]['active'])) {
            return $templates[$prefix];
        }

        if (isset($templates['DEFAULT'])) {
            return $templates['DEFAULT'];
        }

        return reset($templates);
    }

    public function saveTemplates($rawTemplates)
    {
        $clean = array();

        if (!is_array($rawTemplates)) {
            return;
        }

        foreach ($rawTemplates as $row) {
            if (!is_array($row)) {
                continue;
            }

            $key = isset($row['key'])
                ? strtoupper(preg_replace('/[^A-Za-z]/', '', (string) $row['key']))
                : '';

            $key = substr($key, 0, 8);

            $label = isset($row['label']) ? sanitize_text_field($row['label']) : '';
            $description = isset($row['description']) ? sanitize_text_field($row['description']) : '';

            if ($key === '' || $label === '') {
                continue;
            }

            $sections = array();

            if (isset($row['sections']) && is_array($row['sections'])) {
                foreach ($row['sections'] as $position => $sectionKey) {
                    $sectionKey = sanitize_key($sectionKey);

                    if ($sectionKey === '') {
                        continue;
                    }

                    $order = $position;

                    if (isset($row['sections_order'][$sectionKey]) && $row['sections_order'][$sectionKey] !== '') {
                        $order = (int) $row['sections_order'][$sectionKey];
                    }

                    $sections[$sectionKey] = $order;
                }
            }

            asort($sections);
            $sections = array_keys($sections);

            $clean[$key] = array(
                'key' => $key,
                'label' => $label,
                'description' => $description,
                'active' => !empty($row['active']) ? 1 : 0,
                'sections' => array_values(array_unique($sections)),
            );
        }

        update_option(self::OPTION_TEMPLATES, $clean, false);
    }

    public function resetDefaults()
    {
        update_option(self::OPTION_TEMPLATES, $this->getDefaultTemplates(), false);
    }
}
