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

    /**
     * Ordonne des sections selon le template du prefixe, en mode "ancrage
     * positionnel" pour l'affichage public : seuls les types de section connus
     * du template sont permutes entre les emplacements qu'ils occupent deja ;
     * tout type absent du template garde exactement sa position d'origine.
     *
     * Consequence : un article dont le template est complet (ex: PER) est remis
     * dans l'ordre attendu meme si l'ordre stocke en base differe (ex: bloc
     * "Détails" enregistre en fin d'article), tandis qu'un article dont le
     * template est partiel ne peut jamais etre desordonne par ce tri.
     *
     * @param string[] $types  Types de section dans l'ordre courant.
     * @param string   $prefix Prefixe de l'article (ex: 'PER').
     * @return int[]           Indices d'origine dans le nouvel ordre d'affichage.
     */
    public function orderIndexesByTemplate(array $types, $prefix)
    {
        $identity = array_keys($types);

        $template = $this->getTemplateForPrefix($prefix);
        $canonical = (!empty($template['sections']) && is_array($template['sections']))
            ? array_values($template['sections'])
            : array();

        if (empty($canonical)) {
            return $identity;
        }

        $rank = array_flip($canonical);

        $slots = array(); // positions occupees par un type connu du template
        $known = array(); // {rank, orig} pour chaque section de type connu
        foreach ($types as $i => $type) {
            $type = sanitize_key((string) $type);
            if (isset($rank[$type])) {
                $slots[] = $i;
                $known[] = array('rank' => $rank[$type], 'orig' => $i);
            }
        }

        // Rien a reordonner tant qu'il n'y a pas au moins deux types connus.
        if (count($known) < 2) {
            return $identity;
        }

        usort($known, function ($a, $b) {
            if ($a['rank'] !== $b['rank']) {
                return $a['rank'] <=> $b['rank'];
            }
            return $a['orig'] <=> $b['orig'];
        });

        $result = $identity;
        foreach ($slots as $slotIndex => $position) {
            $result[$position] = $known[$slotIndex]['orig'];
        }

        return $result;
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
