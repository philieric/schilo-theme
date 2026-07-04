<?php

return array(
    'evangiles' => array(
        'label' => 'Évangiles',
        'description' => 'Références bibliques avec shortcode [bnv] ajouté automatiquement côté front.',
        'fields' => array(
            'versets' => array(
                'type' => 'repeatable_bible_refs',
                'label' => 'Références bibliques',
                'help' => 'Saisis uniquement la référence, par exemple Luc 23.28-31. Le shortcode [bnv]...[/bnv] sera ajouté automatiquement.',
                'default_items' => array(
                    array('label' => 'Matthieu', 'class' => 'citation-matthieu', 'reference' => 'Matthieu'),
                    array('label' => 'Marc', 'class' => 'citation-marc', 'reference' => 'Marc'),
                    array('label' => 'Luc', 'class' => 'citation-luc', 'reference' => 'Luc'),
                    array('label' => 'Jean', 'class' => 'citation-jean', 'reference' => 'Jean'),
                ),
                'class_choices' => array(
                    'citation-matthieu' => 'citation-matthieu',
                    'citation-marc' => 'citation-marc',
                    'citation-luc' => 'citation-luc',
                    'citation-jean' => 'citation-jean',
                    'citation-bible' => 'citation-bible',
                ),
            ),
        ),
    ),

    'details-techniques' => array(
        'label' => 'Détails techniques',
        'description' => 'Une image accompagnée d’encarts d’information (lieu, date, mode opératoire, etc.) avant et/ou après l’image.',
        'fields' => array(
            'image_id' => array(
                'type' => 'image',
                'label' => 'Image',
            ),
            'blocks_before' => array(
                'type' => 'repeatable_html_blocks',
                'label' => 'Encarts avant l’image',
                'help' => 'Un encart par information : Lieu, Date, Mode opératoire... Utilisez <strong>texte</strong> pour le gras.',
            ),
            'blocks_after' => array(
                'type' => 'repeatable_html_blocks',
                'label' => 'Encarts après l’image',
                'help' => 'Un encart par information complémentaire affichée sous l’image.',
            ),
        ),
    ),

    'details-colonnes' => array(
        'label' => 'Détails par colonne',
        'description' => 'Mise en page sur 2 colonnes : une image d’un côté (gauche ou droite) et des encarts de texte de l’autre côté.',
        'fields' => array(
            'image_id' => array(
                'type' => 'image',
                'label' => 'Image',
            ),
            'image_position' => array(
                'type' => 'select',
                'label' => 'Position de l’image',
                'choices' => array('left' => 'Gauche', 'right' => 'Droite'),
                'default' => 'right',
            ),
            'blocks' => array(
                'type' => 'repeatable_html_blocks',
                'label' => 'Encarts de texte',
                'help' => 'Un encart par information, affiché dans la colonne opposée à l’image.',
            ),
        ),
    ),

    'detail-technique-img-droite' => array(
        'label' => 'Détail technique - Image droite',
        'description' => 'Lieu, date, mode opératoire et note affichés à gauche, image à droite, et un texte libre sous l’image.',
        'fields' => array(
            'image_id' => array('type' => 'image', 'label' => 'Image'),
            'lieu' => array('type' => 'text', 'label' => 'Lieu :'),
            'date' => array('type' => 'text', 'label' => 'Date :'),
            'mode_operatoire' => array('type' => 'text', 'label' => 'Mode opératoire :'),
            'note_mode_operatoire' => array('type' => 'text', 'label' => 'Note sur le mode opératoire :'),
            'texte_dessous' => array('type' => 'text', 'label' => 'Texte sous l’image'),
        ),
    ),

    'liens-articles' => array(
        'label' => 'Liens / Articles liés',
        'description' => 'Une liste de liens (texte affiché + URL) vers d’autres articles, péricopes ou pages.',
        'fields' => array(
            'links' => array(
                'type' => 'repeatable_link_items',
                'label' => 'Liens',
                'help' => 'Un lien par ligne : texte affiché + URL.',
            ),
        ),
    ),

    'titre-simple' => array(
        'label' => 'Titre simple',
        'description' => 'Un simple titre de section (H2), sans contenu ni image.',
        'fields' => array(),
    ),
);
