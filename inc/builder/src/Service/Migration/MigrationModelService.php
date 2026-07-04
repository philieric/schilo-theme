<?php

namespace Schilo\Builder\Service\Migration;

/**
 * Gère les "modèles de migration" : des mappings nommés et réutilisables
 * (élément extrait → section/champ du template), associés à un préfixe
 * (PER, ANN, CTD...).
 *
 * Contrairement à un mapping enregistré sur un seul article, un modèle de
 * migration peut être appliqué à n'importe quel article du même préfixe.
 */
class MigrationModelService
{
    const OPTION_KEY = 'schilo_builder_migration_models';

    /**
     * Retourne tous les modèles de migration enregistrés, indexés par
     * identifiant unique.
     *
     * @return array<string, array{id:string,name:string,prefix:string,assignments:array,created_at:string,updated_at:string}>
     */
    public function getAllModels()
    {
        $saved = get_option(self::OPTION_KEY, array());

        return is_array($saved) ? $saved : array();
    }

    /**
     * Retourne uniquement les modèles associés à un préfixe donné.
     */
    public function getModelsForPrefix($prefix)
    {
        $prefix = strtoupper(sanitize_key((string) $prefix));

        $models = array();

        foreach ($this->getAllModels() as $id => $model) {
            if (isset($model['prefix']) && $model['prefix'] === $prefix) {
                $models[$id] = $model;
            }
        }

        return $models;
    }

    public function getModel($modelId)
    {
        $modelId = sanitize_key((string) $modelId);
        $all = $this->getAllModels();

        return isset($all[$modelId]) ? $all[$modelId] : null;
    }

    /**
     * Enregistre un nouveau modèle de migration, ou met à jour un modèle
     * existant si un identifiant est fourni.
     *
     * Les correspondances sont enregistrées par "motif" plutôt que par
     * identifiant exact d'élément, afin que le modèle s'applique quel que
     * soit le nombre d'occurrences sur un article donné (ex: un article PER
     * de référence avec 4 liens "Consultation" doit aussi bien s'appliquer
     * à un article qui n'en a que 2, ou qui en a 6).
     *
     * Un motif est l'identifiant d'élément avec son éventuel suffixe
     * numérique de répétition retiré (ex: "consultation_link_3" devient
     * le motif "consultation_link" — qui couvre alors "consultation_link",
     * "consultation_link_1", "consultation_link_2", etc.).
     *
     * @param string      $name        Nom lisible du modèle (ex: "PER standard").
     * @param string      $prefix      Préfixe associé (ex: "PER").
     * @param array       $assignments [element_id => ['section_type' => ..., 'field' => ...]]
     * @param string|null $modelId     Identifiant du modèle à mettre à jour, ou null pour en créer un nouveau.
     * @param array       $contentOverrides [element_id => texte modifié] — ex: la formule "Vous pouvez
     *                    consulter l'annexe" éditée manuellement, à réutiliser pour tous les articles
     *                    migrés avec ce modèle quand l'article source ne fournit pas ce texte.
     * @return string Identifiant du modèle enregistré.
     */
    public function saveModel($name, $prefix, $assignments, $modelId = null, $contentOverrides = array())
    {
        $name = sanitize_text_field((string) $name);
        $prefix = strtoupper(sanitize_key((string) $prefix));

        if ($name === '') {
            $name = $prefix . ' — modèle sans nom';
        }

        $clean = array();

        if (is_array($assignments)) {
            foreach ($assignments as $elementId => $assignment) {
                $elementId = sanitize_key((string) $elementId);
                $sectionType = isset($assignment['section_type']) ? sanitize_key((string) $assignment['section_type']) : '';
                $field = isset($assignment['field']) ? sanitize_key((string) $assignment['field']) : '';

                if ($elementId === '' || $sectionType === '' || $sectionType === 'ignore' || $field === '') {
                    continue;
                }

                $pattern = $this->elementIdToPattern($elementId);

                // Si plusieurs éléments d'un même motif (ex: plusieurs liens)
                // ont des champs de destination différents (cas rare mais
                // possible), on garde le premier rencontré pour ce motif et
                // la règle s'appliquera identiquement à toutes les occurrences.
                if (!isset($clean[$pattern])) {
                    $clean[$pattern] = array(
                        'section_type' => $sectionType,
                        'field' => $field,
                    );
                }
            }
        }

        $cleanContentOverrides = array();

        if (is_array($contentOverrides)) {
            foreach ($contentOverrides as $elementId => $value) {
                $elementId = sanitize_key((string) $elementId);
                $value = sanitize_text_field((string) $value);

                if ($elementId === '' || $value === '') {
                    continue;
                }

                $pattern = $this->elementIdToPattern($elementId);

                if (!isset($cleanContentOverrides[$pattern])) {
                    $cleanContentOverrides[$pattern] = $value;
                }
            }
        }

        $all = $this->getAllModels();

        $modelId = $modelId !== null ? sanitize_key((string) $modelId) : '';

        if ($modelId === '' || !isset($all[$modelId])) {
            $modelId = $this->generateModelId($prefix, $all);
            $createdAt = current_time('mysql');
        } else {
            $createdAt = isset($all[$modelId]['created_at']) ? $all[$modelId]['created_at'] : current_time('mysql');
        }

        $all[$modelId] = array(
            'id' => $modelId,
            'name' => $name,
            'prefix' => $prefix,
            'assignments' => $clean,
            'content_overrides' => $cleanContentOverrides,
            'created_at' => $createdAt,
            'updated_at' => current_time('mysql'),
        );

        update_option(self::OPTION_KEY, $all, false);

        return $modelId;
    }

    public function deleteModel($modelId)
    {
        $modelId = sanitize_key((string) $modelId);
        $all = $this->getAllModels();

        if (isset($all[$modelId])) {
            unset($all[$modelId]);
            update_option(self::OPTION_KEY, $all, false);
        }
    }

    private function generateModelId($prefix, $existing)
    {
        $base = sanitize_key(strtolower($prefix)) . '_' . substr(md5(uniqid('', true)), 0, 8);

        while (isset($existing[$base])) {
            $base = sanitize_key(strtolower($prefix)) . '_' . substr(md5(uniqid('', true)), 0, 8);
        }

        return $base;
    }

    /**
     * Retire le suffixe numérique de répétition d'un identifiant d'élément
     * (ex: "consultation_link_3" -> "consultation_link", "wikilogy_title" reste
     * inchangé). C'est ce motif qui sert de clé dans le modèle enregistré.
     */
    public function elementIdToPattern($elementId)
    {
        return preg_replace('/_\d+$/', '', (string) $elementId);
    }

    /**
     * Étend les règles d'un modèle (définies par motif) en correspondances
     * concrètes pour une liste réelle d'éléments extraits d'un article
     * donné — quel que soit le nombre d'occurrences de chaque motif sur
     * cet article (0, 2, 4, 10 liens "Consultation", etc.).
     *
     * @param array $model    Le modèle tel que retourné par getModel()/getModelsForPrefix().
     * @param array $elements Liste des éléments extraits (chacun avec un 'id').
     * @return array [element_id => ['section_type' => ..., 'field' => ...]]
     */
    public function expandModelForElements($model, array $elements)
    {
        $rules = isset($model['assignments']) && is_array($model['assignments']) ? $model['assignments'] : array();

        $expanded = array();

        foreach ($elements as $element) {
            if (!isset($element['id'])) {
                continue;
            }

            $pattern = $this->elementIdToPattern($element['id']);

            if (isset($rules[$pattern])) {
                $expanded[$element['id']] = $rules[$pattern];
            }
        }

        return $expanded;
    }

    /**
     * Étend les textes personnalisés d'un modèle (ex: la formule "Vous
     * pouvez consulter l'annexe" éditée manuellement) aux éléments réels
     * d'un article, pour les éléments qui n'ont pas déjà leur propre texte
     * (ex: les articles où ce texte est absent de la source).
     *
     * @param array $model    Le modèle tel que retourné par getModel()/getModelsForPrefix().
     * @param array $elements Liste des éléments extraits (chacun avec un 'id' et un 'content').
     * @return array [element_id => texte de remplacement]
     */
    public function expandContentOverridesForElements($model, array $elements)
    {
        $overrides = isset($model['content_overrides']) && is_array($model['content_overrides']) ? $model['content_overrides'] : array();

        if (empty($overrides)) {
            return array();
        }

        $expanded = array();

        foreach ($elements as $element) {
            if (!isset($element['id'])) {
                continue;
            }

            $pattern = $this->elementIdToPattern($element['id']);

            if (isset($overrides[$pattern])) {
                $expanded[$element['id']] = $overrides[$pattern];
            }
        }

        return $expanded;
    }
}
