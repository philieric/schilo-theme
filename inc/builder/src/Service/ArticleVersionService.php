<?php

namespace Schilo\Builder\Service;

/**
 * Relie plusieurs articles representant des versions differentes d'un meme
 * contenu (ex : "grand public" / "academique"). Relation symetrique stockee
 * en liste d'IDs sur chaque post (pas de table de groupe séparée) : lier
 * A a B et C synchronise automatiquement B<->C.
 */
class ArticleVersionService
{
    const META_ENABLED = '_schilo_version_enabled';
    const META_LINKED   = '_schilo_version_linked_ids';
    const META_LABEL    = '_schilo_version_label';
    const META_PRIMARY  = '_schilo_version_primary';

    const OPTION_TYPES = 'schilo_builder_version_types';

    public function getAvailableLabels()
    {
        $saved = get_option(self::OPTION_TYPES, array());
        $labels = is_array($saved) ? array_values(array_filter(array_map('trim', $saved))) : array();

        return !empty($labels) ? $labels : array('Grand public', 'Académique');
    }

    public function saveAvailableLabels(array $labels)
    {
        $clean = array();
        foreach ($labels as $label) {
            $label = trim(sanitize_text_field((string) $label));
            if ($label !== '' && !in_array($label, $clean, true)) {
                $clean[] = $label;
            }
        }
        update_option(self::OPTION_TYPES, $clean, false);
    }

    public function isEnabled($postId)
    {
        return get_post_meta((int) $postId, self::META_ENABLED, true) === '1';
    }

    public function isPrimary($postId)
    {
        return get_post_meta((int) $postId, self::META_PRIMARY, true) === '1';
    }

    public function getLabel($postId)
    {
        $label = get_post_meta((int) $postId, self::META_LABEL, true);
        return $label !== '' ? (string) $label : '';
    }

    public function getLinkedIds($postId)
    {
        $linked = get_post_meta((int) $postId, self::META_LINKED, true);
        if (!is_array($linked)) {
            return array();
        }
        return array_values(array_unique(array_map('intval', $linked)));
    }

    /**
     * Tous les membres du groupe (post courant inclus), post courant en tete.
     *
     * @return int[]
     */
    public function getGroupIds($postId)
    {
        $postId = (int) $postId;
        return array_values(array_unique(array_merge(array($postId), $this->getLinkedIds($postId))));
    }

    /**
     * Enregistre les versions liees a un post : synchronise la liste
     * symetriquement sur tous les membres (anciens et nouveaux), applique le
     * label/primaire pour CE post, et garantit qu'un seul membre du groupe
     * est marque primaire (celui coche efface le flag chez les autres).
     *
     * @param int    $postId
     * @param bool   $enabled
     * @param int[]  $linkedIds     IDs des autres articles du groupe (sans le post courant)
     * @param string $label
     * @param bool   $isPrimary
     * @param array  $linkedLabels  [id lié => type de version choisi pour cet article,
     *                               depuis l'écran courant] — évite d'avoir à rouvrir
     *                               chaque article lié pour lui assigner son type.
     */
    public function saveVersion($postId, $enabled, array $linkedIds, $label, $isPrimary, array $linkedLabels = array())
    {
        $postId = (int) $postId;
        $linkedIds = array_values(array_unique(array_filter(array_map('intval', $linkedIds), function ($id) use ($postId) {
            return $id > 0 && $id !== $postId;
        })));

        if (!$enabled || empty($linkedIds)) {
            // Désactiver ici équivaut à retirer ce post de tous ses liens :
            // même nettoyage en cascade que le retrait d'un lien individuel
            // (si un membre lié n'a plus aucun lien après ça, il est aussi
            // décoché), sinon il reste orphelin, encore coché, pointant vers
            // un groupe qui n'existe plus côté post courant.
            // "empty($linkedIds)" couvre le cas où la case est restée cochée
            // mais que le dernier article lié vient d'être retiré : un post
            // "version multiple" sans aucun lien n'a pas de sens, on le
            // décoche aussi (règle symétrique à celle des membres du groupe).
            foreach ($this->getLinkedIds($postId) as $memberId) {
                $memberId = (int) $memberId;
                $memberLinked = array_values(array_diff($this->getLinkedIds($memberId), array($postId)));
                update_post_meta($memberId, self::META_LINKED, $memberLinked);

                if (empty($memberLinked)) {
                    delete_post_meta($memberId, self::META_ENABLED);
                    delete_post_meta($memberId, self::META_LABEL);
                    delete_post_meta($memberId, self::META_PRIMARY);
                }
            }

            delete_post_meta($postId, self::META_ENABLED);
            delete_post_meta($postId, self::META_LINKED);
            delete_post_meta($postId, self::META_LABEL);
            delete_post_meta($postId, self::META_PRIMARY);
            return;
        }

        // Un type de version (label) doit être UNIQUE au sein d'un groupe : deux
        // membres ne peuvent pas être tous les deux "Grand public", par exemple.
        // On calcule le label "voulu" de chaque membre (post courant + liés
        // restants) AVANT toute écriture, on résout les conflits (le membre qui
        // vient de changer de type "prend" celui d'un membre inchangé, qui
        // hérite alors automatiquement de l'ancien type du premier), puis
        // seulement on écrit les valeurs définitives.
        $previousLabels = array($postId => $this->getLabel($postId));
        $intendedLabels = array($postId => sanitize_text_field((string) $label));
        foreach ($linkedIds as $memberId) {
            $memberId = (int) $memberId;
            $previousLabels[$memberId] = $this->getLabel($memberId);
            if (isset($linkedLabels[$memberId]) && trim((string) $linkedLabels[$memberId]) !== '') {
                $intendedLabels[$memberId] = sanitize_text_field((string) $linkedLabels[$memberId]);
            } elseif ($previousLabels[$memberId] !== '') {
                $intendedLabels[$memberId] = $previousLabels[$memberId];
            } else {
                $intendedLabels[$memberId] = 'Version';
            }
        }
        $resolvedLabels = $this->resolveLabelConflicts($previousLabels, $intendedLabels);

        update_post_meta($postId, self::META_ENABLED, '1');
        update_post_meta($postId, self::META_LABEL, $resolvedLabels[$postId]);

        // Union de l'ancien groupe (pour ne pas laisser d'anciens membres
        // orphelins si on retire un lien) et du nouveau, avant resynchronisation.
        $previousLinked = $this->getLinkedIds($postId);
        $allTouched = array_unique(array_merge(array($postId), $previousLinked, $linkedIds));

        update_post_meta($postId, self::META_LINKED, $linkedIds);

        foreach ($allTouched as $memberId) {
            $memberId = (int) $memberId;
            if ($memberId === $postId) {
                continue;
            }

            $stillLinked = in_array($memberId, $linkedIds, true);
            $memberEnabled = $this->isEnabled($memberId);

            if (!$stillLinked && !$memberEnabled) {
                continue;
            }

            $memberLinked = $this->getLinkedIds($memberId);

            if ($stillLinked) {
                // Le membre doit pointer vers tous les autres du groupe (post
                // courant + le reste), pas seulement vers le post courant.
                $memberLinked = array_values(array_unique(array_merge(
                    array_diff($memberLinked, array($postId)),
                    array($postId),
                    array_diff($linkedIds, array($memberId))
                )));
                update_post_meta($memberId, self::META_ENABLED, '1');
                update_post_meta($memberId, self::META_LINKED, $memberLinked);
                update_post_meta($memberId, self::META_LABEL, $resolvedLabels[$memberId]);
            } else {
                // Retire uniquement le lien vers le post courant, conserve le
                // reste du groupe de ce membre s'il en a un (groupe à 3+).
                $memberLinked = array_values(array_diff($memberLinked, array($postId)));
                update_post_meta($memberId, self::META_LINKED, $memberLinked);

                if (empty($memberLinked)) {
                    // Plus aucun lien restant pour ce membre : il ne fait plus
                    // partie d'aucun groupe, l'option ne doit plus être cochée.
                    delete_post_meta($memberId, self::META_ENABLED);
                    delete_post_meta($memberId, self::META_LABEL);
                    delete_post_meta($memberId, self::META_PRIMARY);
                }
            }
        }

        $this->setPrimary($postId, (bool) $isPrimary);
    }

    /**
     * Résout les conflits de label au sein d'un groupe : un membre "statique"
     * (dont le label voulu == son label précédent, donc pas touché sur cette
     * sauvegarde) qui se retrouve avec le même label qu'un membre "mobile"
     * (dont le label voulu diffère de son label précédent) hérite de l'ANCIEN
     * label du membre mobile — c'est l'échange automatique demandé : si B
     * passe à "Grand public" (déjà pris par A, inchangé), A repasse à
     * l'ancien label de B ("Académique").
     *
     * Plusieurs passes pour propager les échanges en chaîne sur un groupe à
     * 3+ membres (un membre qui vient d'être décalé peut à son tour entrer
     * en conflit avec un autre membre statique).
     *
     * @param array $previousLabels [id => label avant cette sauvegarde]
     * @param array $intendedLabels [id => label voulu par cette sauvegarde]
     * @return array [id => label définitif]
     */
    private function resolveLabelConflicts(array $previousLabels, array $intendedLabels)
    {
        $final = $intendedLabels;
        $ids = array_keys($intendedLabels);

        for ($pass = 0; $pass < count($ids); $pass++) {
            $changed = false;

            foreach ($ids as $moverId) {
                $prev = isset($previousLabels[$moverId]) ? $previousLabels[$moverId] : '';
                $new  = $final[$moverId];

                if ($new === $prev || $prev === '') {
                    continue; // pas un mouvement exploitable pour liberer une place
                }

                foreach ($ids as $staticId) {
                    if ($staticId === $moverId) {
                        continue;
                    }

                    $staticPrev = isset($previousLabels[$staticId]) ? $previousLabels[$staticId] : '';
                    if ($final[$staticId] !== $staticPrev) {
                        continue; // ce membre a lui-meme deja bouge sur cette sauvegarde
                    }

                    if ($final[$staticId] === $new) {
                        $final[$staticId] = $prev;
                        $changed = true;
                    }
                }
            }

            if (!$changed) {
                break;
            }
        }

        return $final;
    }

    /**
     * Ajoute un nouveau type de version à la liste disponible (si absent) et
     * retourne la liste à jour — utilisé par la popup "type déjà pris" de la
     * metabox pour créer un type à la volée sans quitter l'écran d'édition.
     *
     * @return string[]
     */
    public function addAvailableLabel($label)
    {
        $label = trim(sanitize_text_field((string) $label));
        $labels = $this->getAvailableLabels();

        if ($label !== '' && !in_array($label, $labels, true)) {
            $labels[] = $label;
            $this->saveAvailableLabels($labels);
        }

        return $labels;
    }

    /**
     * Marque $postId comme article primaire de son groupe et decoche
     * automatiquement le flag chez tous les autres membres (un seul primaire
     * par groupe).
     */
    public function setPrimary($postId, $isPrimary)
    {
        $postId = (int) $postId;

        if (!$isPrimary) {
            update_post_meta($postId, self::META_PRIMARY, '0');
            return;
        }

        update_post_meta($postId, self::META_PRIMARY, '1');

        foreach ($this->getLinkedIds($postId) as $memberId) {
            update_post_meta((int) $memberId, self::META_PRIMARY, '0');
        }
    }

    /**
     * IDs a exclure des listings (archives, recherche...) : les membres non
     * primaires d'un groupe actif. Reste accessibles par URL directe.
     */
    public function getExcludedFromListingsIds()
    {
        global $wpdb;

        $enabledIds = $wpdb->get_col($wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value = '1'",
            self::META_ENABLED
        ));

        $excluded = array();
        foreach ($enabledIds as $id) {
            if (!$this->isPrimary($id)) {
                $excluded[] = (int) $id;
            }
        }

        return $excluded;
    }
}
