<?php

namespace Schilo\Builder\Service;

/**
 * Articles secondaires ("annexes") lies a un article principal : lien simple
 * a sens unique (parent -> liste d'IDs), sans les notions de "type" ni de
 * groupe symetrique du systeme de versions (ArticleVersionService) dont ce
 * service reprend uniquement le principe d'ajout (rechercher, lier, retirer).
 *
 * Les annexes elles-memes sont identifiees par prefixe de titre (ANX*, voir
 * Schilo_Prefixes) et exclues de tous les listings publics — voir
 * Schilo\Builder\Front\AnnexeRenderer::excludeFromListings().
 */
class AnnexeService
{
    const META_ENABLED = '_schilo_secondary_enabled';
    const META_LINKED  = '_schilo_secondary_ids';
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

        if (!$enabled || empty($ids)) {
            delete_post_meta($postId, self::META_ENABLED);
            delete_post_meta($postId, self::META_LINKED);
            return;
        }

        update_post_meta($postId, self::META_ENABLED, '1');
        update_post_meta($postId, self::META_LINKED, $ids);
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
