<?php

namespace Schilo\Builder\Service;

class SectionTypeService
{
    const OPTION_SECTION_TYPES = 'schilo_builder_section_types';

    public function getDefaultTypes()
    {
        return array(
            'intro' => array('key'=>'intro','label'=>'Introduction','description'=>'Section d’introduction.','active'=>1,'view'=>'intro.php','admin_view'=>'default.php'),
            'contexte' => array('key'=>'contexte','label'=>'Contexte','description'=>'Contexte ou mise en situation.','active'=>1,'view'=>'contexte.php','admin_view'=>'default.php'),
            'paragraphe' => array('key'=>'paragraphe','label'=>'Paragraphe','description'=>'Bloc de texte libre.','active'=>1,'view'=>'paragraphe.php','admin_view'=>'default.php'),
            'evangiles' => array('key'=>'evangiles','label'=>'Évangiles','description'=>'Références bibliques structurées.','active'=>1,'view'=>'evangiles.php','admin_view'=>'evangiles.php'),
            'image-textes' => array('key'=>'image-textes','label'=>'Image + textes','description'=>'Image et textes.','active'=>1,'view'=>'image-textes.php','admin_view'=>'default.php'),
            'details-techniques' => array('key'=>'details-techniques','label'=>'Détails techniques','description'=>'Image accompagnée de plusieurs encarts d’information (lieu, date, mode opératoire...).','active'=>1,'view'=>'details-techniques.php','admin_view'=>'details-techniques.php'),
            'details-colonnes' => array('key'=>'details-colonnes','label'=>'Détails par colonne','description'=>'Mise en page 2 colonnes : image d’un côté, encarts de texte de l’autre.','active'=>1,'view'=>'details-colonnes.php','admin_view'=>'details-colonnes.php'),
            'detail-technique-img-droite' => array('key'=>'detail-technique-img-droite','label'=>'Détail technique - Image droite','description'=>'Lieu, date, mode opératoire et note à gauche, image à droite, texte libre sous l’image.','active'=>1,'view'=>'detail-technique-img-droite.php','admin_view'=>'detail-technique-img-droite.php'),
            'liens-articles' => array('key'=>'liens-articles','label'=>'Liens / Articles liés','description'=>'Liste de liens (texte + URL) vers d’autres articles ou pages.','active'=>1,'view'=>'liens-articles.php','admin_view'=>'liens-articles.php'),
            'titre-simple' => array('key'=>'titre-simple','label'=>'Titre simple','description'=>'Un simple titre de section (H2), sans contenu ni image.','active'=>1,'view'=>'titre-simple.php','admin_view'=>'titre-simple.php'),
            'questions' => array('key'=>'questions','label'=>'Questions','description'=>'Questions et réponses liées à l\'article.','active'=>1,'view'=>'questions.php','admin_view'=>'questions.php'),
            'references' => array('key'=>'references','label'=>'Références','description'=>'Sources et notes.','active'=>1,'view'=>'references.php','admin_view'=>'default.php'),
            'conclusion' => array('key'=>'conclusion','label'=>'Conclusion','description'=>'Conclusion.','active'=>1,'view'=>'conclusion.php','admin_view'=>'default.php'),
        );
    }

    public function getAllTypes()
    {
        $saved = get_option(self::OPTION_SECTION_TYPES, array());
        if (!is_array($saved) || empty($saved)) {
            return $this->getDefaultTypes();
        }

        $types = array();
        foreach ($saved as $key => $config) {
            if (!is_array($config)) {
                continue;
            }

            $cleanKey = isset($config['key']) ? sanitize_key($config['key']) : sanitize_key($key);
            if ($cleanKey === '') {
                continue;
            }

            $types[$cleanKey] = array(
                'key' => $cleanKey,
                'label' => isset($config['label']) ? sanitize_text_field($config['label']) : ucfirst($cleanKey),
                'description' => isset($config['description']) ? sanitize_text_field($config['description']) : '',
                'active' => !empty($config['active']) ? 1 : 0,
                'view' => isset($config['view']) && $config['view'] !== '' ? sanitize_file_name($config['view']) : $cleanKey . '.php',
                'admin_view' => isset($config['admin_view']) && $config['admin_view'] !== '' ? sanitize_file_name($config['admin_view']) : 'default.php',
            );
        }

        foreach ($this->getDefaultTypes() as $defaultKey => $defaultConfig) {
            if (!isset($types[$defaultKey])) {
                $types[$defaultKey] = $defaultConfig;
            }
        }

        return !empty($types) ? $types : $this->getDefaultTypes();
    }

    public function getActiveTypes()
    {
        $active = array();
        foreach ($this->getAllTypes() as $key => $type) {
            if (!empty($type['active'])) {
                $active[$key] = $type;
            }
        }
        return $active;
    }

    public function getType($type)
    {
        $type = sanitize_key((string) $type);
        $types = $this->getAllTypes();
        return isset($types[$type]) ? $types[$type] : array();
    }

    public function getAdminViewForType($type)
    {
        $config = $this->getType($type);
        return !empty($config['admin_view']) ? sanitize_file_name($config['admin_view']) : 'default.php';
    }

    public function saveTypes($rawTypes)
    {
        $clean = array();

        if (!is_array($rawTypes)) {
            return;
        }

        foreach ($rawTypes as $row) {
            if (!is_array($row)) {
                continue;
            }

            $key = isset($row['key']) ? sanitize_key($row['key']) : '';
            $label = isset($row['label']) ? sanitize_text_field($row['label']) : '';

            if ($key === '' || $label === '') {
                continue;
            }

            $clean[$key] = array(
                'key' => $key,
                'label' => $label,
                'description' => isset($row['description']) ? sanitize_text_field($row['description']) : '',
                'active' => !empty($row['active']) ? 1 : 0,
                'view' => isset($row['view']) && $row['view'] !== '' ? sanitize_file_name($row['view']) : $key . '.php',
                'admin_view' => isset($row['admin_view']) && $row['admin_view'] !== '' ? sanitize_file_name($row['admin_view']) : 'default.php',
            );
        }

        update_option(self::OPTION_SECTION_TYPES, $clean, false);
    }

    public function resetDefaults()
    {
        update_option(self::OPTION_SECTION_TYPES, $this->getDefaultTypes(), false);
    }
}
