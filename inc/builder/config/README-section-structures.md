# Structures de sections Schilo Builder

Ce fichier permet de définir la structure interne des sections.

Fichier principal :

```text
config/section-structures.php
```

Pour personnaliser sans écraser lors d’une mise à jour, créer :

```text
config/section-structures.custom.php
```

Ce fichier doit retourner un tableau PHP. Exemple :

```php
<?php
return array(
    'evangiles' => array(
        'use_content_editor' => false,
        'fields' => array(
            'versets' => array(
                'type' => 'repeatable_text',
                'label' => 'Versets',
                'min' => 4,
                'placeholder' => '[verset ref="Jean 3:16"]',
                'add_button' => '+ Ajouter un verset',
            ),
        ),
    ),
);
```
