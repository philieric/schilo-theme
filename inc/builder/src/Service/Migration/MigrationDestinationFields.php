<?php

namespace Schilo\Builder\Service\Migration;

/**
 * Decrit, pour chaque type de section Schilo, la liste des champs
 * vers lesquels un element extrait peut etre envoye lors de la migration.
 */
class MigrationDestinationFields
{
    public function getCommonFields()
    {
        return array(
            array('value' => 'section_title', 'label' => 'Titre de la section'),
        );
    }

    public function getFieldsForType($sectionType)
    {
        $sectionType = sanitize_key((string) $sectionType);
        $specific = $this->getTypeSpecificFields();
        $fields = isset($specific[$sectionType]) ? $specific[$sectionType] : $this->getDefaultFields();
        $hasTitleField = false;
        foreach ($fields as $field) {
            if ($field['value'] === 'section_title') { $hasTitleField = true; break; }
        }
        if (!$hasTitleField) {
            array_unshift($fields, array('value' => 'section_title', 'label' => 'Titre de la section'));
        }
        return $fields;
    }

    private function getDefaultFields()
    {
        return array(
            array('value' => 'section_title', 'label' => 'Titre de la section'),
            array('value' => 'content',        'label' => 'Contenu (editeur)'),
        );
    }

    private function getTypeSpecificFields()
    {
        return array(
            'paragraphe' => array(
                array('value' => 'section_title', 'label' => 'Titre de la section'),
                array('value' => 'content',        'label' => 'Contenu (editeur)'),
            ),

            'titre-simple' => array(
                array('value' => 'section_title', 'label' => 'Titre de la section'),
            ),

            'liens-articles' => array(
                array('value' => 'section_title', 'label' => 'Titre de la section'),
                array('value' => 'intro',         'label' => "Texte d'introduction"),
                array('value' => 'texte_libre',   'label' => 'Texte libre'),
                array('value' => 'links_auto',    'label' => "Liste de liens (extraction auto. Vous pouvez consulter l'annexe)"),
            ),

            'evangiles' => array(
                array('value' => 'section_title', 'label' => 'Titre de la section'),
                array('value' => 'versets_auto',  'label' => 'References bibliques (extraction automatique)'),
            ),

            'details-techniques' => array(
                array('value' => 'section_title',  'label' => 'Titre de la section'),
                array('value' => 'image_id',       'label' => 'Image'),
                array('value' => 'image_url_auto', 'label' => 'Image (URL extraite automatiquement)'),
                array('value' => 'blocks_before',  'label' => "Encarts avant l'image"),
                array('value' => 'blocks_after',   'label' => "Encarts apres l'image"),
            ),

            'details-colonnes' => array(
                array('value' => 'section_title',  'label' => 'Titre de la section'),
                array('value' => 'image_id',       'label' => 'Image'),
                array('value' => 'image_url_auto', 'label' => 'Image (URL extraite automatiquement)'),
                array('value' => 'blocks',         'label' => 'Encarts de texte'),
            ),

            'detail-technique-img-droite' => array(
                array('value' => 'section_title',        'label' => 'Titre de la section'),
                array('value' => 'image_id',             'label' => 'Image principale'),
                array('value' => 'lieu',                 'label' => 'Lieu'),
                array('value' => 'date',                 'label' => 'Date'),
                array('value' => 'mode_operatoire',      'label' => 'Mode operatoire'),
                array('value' => 'note_mode_operatoire', 'label' => 'Note sur le mode operatoire'),
                array('value' => 'texte_avant',          'label' => 'Texte libre au-dessus'),
                array('value' => 'texte_dessous',        'label' => "Texte sous l'image"),
                array('value' => 'texte_apres',          'label' => 'Texte libre en dessous'),
            ),

            'image-textes' => array(
                array('value' => 'section_title', 'label' => 'Titre de la section'),
                array('value' => 'image_id',      'label' => 'Image'),
                array('value' => 'content',       'label' => 'Contenu (editeur)'),
            ),
        );
    }
}
