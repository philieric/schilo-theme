<?php

namespace Schilo\Builder\Service;

/**
 * Articles secondaires ("annexes") lies a un article principal : lien simple
 * a sens unique (parent -> liste d'IDs), sans les notions de "type" ni de
 * groupe symetrique du systeme de versions (ArticleVersionService) dont ce
 * service reprend le principe d'ajout (rechercher, lier, retirer) ET, depuis
 * l'ajout du "proprietaire principal", le principe d'un flag unique par
 * groupe (voir setPrimaryOwner()).
 *
 * Une meme annexe peut etre liee par PLUSIEURS articles principaux distincts
 * (ex: ANX0001 rattachee a la fois a PER001 et CTD005) : un index inverse
 * (META_OWNER_IDS) est maintenu automatiquement sur l'annexe a chaque
 * sauvegarde d'un proprietaire, pour permettre de designer, cote annexe,
 * lequel de ces proprietaires est le "principal" (META_PRIMARY_OWNER) —
 * contrairement a ArticleVersionService::setPrimary(), un seul post porte
 * tous les candidats (pas besoin de boucle de decochage sur d'autres posts).
 *
 * Les annexes elles-memes sont identifiees par prefixe de titre (ANX*, voir
 * Schilo_Prefixes) et exclues de tous les listings publics — voir
 * Schilo\Builder\Front\AnnexeRenderer::excludeFromListings().
 */
class AnnexeService
{
    const META_ENABLED = '_schilo_secondary_enabled';
    const META_LINKED  = '_schilo_secondary_ids';
    const META_OWNER_IDS = '_schilo_secondary_owner_ids';
    const META_PRIMARY_OWNER = '_schilo_secondary_primary_owner_id';
    const OPTION_SETTINGS = 'schilo_builder_annexe_settings';

    const DEFAULT_LABEL = 'Note complémentaire';

    public function isEnabled($postId)
    {
        return (bool) get_post_meta((int) $postId, self::META_ENABLED, true);
    }

    /** IDs lies, dans l'ordre d'enregistrement (ordre d'affichage cote front). */
    public function getLinkedIds($postId)
    {
        $ids = get_post_meta((int) $postId, self::META_LINKED, true);
        if (!is_array($ids)) {
            return array();
        }
        return array_values(array_map('intval', $ids));
    }

    public function saveSecondaryLinks($postId, $enabled, array $ids)
    {
        $postId = (int) $postId;
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        $previousIds = $this->getLinkedIds($postId);

        if (!$enabled || empty($ids)) {
            delete_post_meta($postId, self::META_ENABLED);
            delete_post_meta($postId, self::META_LINKED);
            $this->syncOwnerBacklinks($postId, $previousIds, array());
            return;
        }

        update_post_meta($postId, self::META_ENABLED, '1');
        update_post_meta($postId, self::META_LINKED, $ids);
        $this->syncOwnerBacklinks($postId, $previousIds, $ids);
    }

    /**
     * Maintient l'index inverse (META_OWNER_IDS) sur chaque annexe touchee
     * par ce changement : ajoute $ownerId aux annexes nouvellement liees, le
     * retire de celles qui ne le sont plus. Si une annexe perd son
     * proprietaire actuellement marque "principal", le marquage est effacé
     * (jamais reassigne automatiquement a un autre proprietaire restant —
     * choix explicite laisse a l'utilisateur, cote annexe).
     */
    private function syncOwnerBacklinks($ownerId, array $previousIds, array $newIds)
    {
        $ownerId = (int) $ownerId;
        $added = array_diff($newIds, $previousIds);
        $removed = array_diff($previousIds, $newIds);

        foreach ($added as $anxId) {
            $anxId = (int) $anxId;
            $owners = $this->getOwnerIds($anxId);
            if (!in_array($ownerId, $owners, true)) {
                $owners[] = $ownerId;
                update_post_meta($anxId, self::META_OWNER_IDS, $owners);
            }
        }

        foreach ($removed as $anxId) {
            $anxId = (int) $anxId;
            $owners = array_values(array_diff($this->getOwnerIds($anxId), array($ownerId)));

            if (empty($owners)) {
                delete_post_meta($anxId, self::META_OWNER_IDS);
            } else {
                update_post_meta($anxId, self::META_OWNER_IDS, $owners);
            }

            if ($this->getPrimaryOwnerId($anxId) === $ownerId) {
                delete_post_meta($anxId, self::META_PRIMARY_OWNER);
            }
        }
    }

    /** IDs des articles principaux ayant lie CETTE annexe (index inverse). */
    public function getOwnerIds($postId)
    {
        $ids = get_post_meta((int) $postId, self::META_OWNER_IDS, true);
        if (!is_array($ids)) {
            return array();
        }
        return array_values(array_unique(array_map('intval', $ids)));
    }

    /** ID du proprietaire actuellement marque principal pour cette annexe, ou 0. */
    public function getPrimaryOwnerId($postId)
    {
        $id = (int) get_post_meta((int) $postId, self::META_PRIMARY_OWNER, true);
        return $id > 0 ? $id : 0;
    }

    /**
     * Designe $ownerId comme proprietaire principal de l'annexe $postId — un
     * seul a la fois (meme regle que ArticleVersionService::isPrimary()).
     * $ownerId doit faire partie des proprietaires actuels de l'annexe,
     * sinon le marquage est simplement efface (ex: proprietaire retire entre
     * l'affichage du formulaire et sa soumission).
     */
    public function setPrimaryOwner($postId, $ownerId)
    {
        $postId = (int) $postId;
        $ownerId = (int) $ownerId;

        if ($ownerId <= 0 || !in_array($ownerId, $this->getOwnerIds($postId), true)) {
            delete_post_meta($postId, self::META_PRIMARY_OWNER);
            return;
        }

        update_post_meta($postId, self::META_PRIMARY_OWNER, $ownerId);
    }

    /** Libelle affiche cote front (titre du bloc + eyebrow de la popup), personnalisable. */
    public function getLabel()
    {
        $settings = get_option(self::OPTION_SETTINGS, array());
        $label = is_array($settings) && !empty($settings['label']) ? trim((string) $settings['label']) : '';
        return $label !== '' ? $label : self::DEFAULT_LABEL;
    }

    /** Texte d'introduction affiche au-dessus de la liste des annexes (facultatif). */
    public function getIntroText()
    {
        $settings = get_option(self::OPTION_SETTINGS, array());
        return is_array($settings) && !empty($settings['text']) ? (string) $settings['text'] : '';
    }

    public function saveSettings($label, $text)
    {
        update_option(self::OPTION_SETTINGS, array(
            'label' => sanitize_text_field((string) $label),
            'text'  => sanitize_textarea_field((string) $text),
        ));
    }

    /** IDs de tous les articles publies dont le titre commence par ANX. */
    public function getAllAnxPostIds()
    {
        global $wpdb;

        $rows = $wpdb->get_col($wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'post' AND post_status = 'publish' AND post_title LIKE %s",
            $wpdb->esc_like('ANX') . '%'
        ));

        return array_map('intval', $rows);
    }
}
