<?php

namespace Schilo\Builder\Service;

class SectionStructureService
{
    public function getAll()
    {
        $baseFile = SCHILO_BUILDER_PATH . 'config/section-structures.php';
        $customFile = SCHILO_BUILDER_PATH . 'config/section-structures.custom.php';

        $structures = array();

        if (file_exists($baseFile)) {
            $loaded = include $baseFile;
            if (is_array($loaded)) {
                $structures = $loaded;
            }
        }

        if (file_exists($customFile)) {
            $custom = include $customFile;
            if (is_array($custom)) {
                $structures = array_replace_recursive($structures, $custom);
            }
        }

        return $structures;
    }

    public function get($sectionType)
    {
        $sectionType = sanitize_key((string) $sectionType);
        $all = $this->getAll();
        return isset($all[$sectionType]) && is_array($all[$sectionType]) ? $all[$sectionType] : array();
    }

    public function normalizeSectionData($sectionType, $data)
    {
        $sectionType = sanitize_key((string) $sectionType);
        $data = is_array($data) ? $data : array();

        if ($sectionType === 'details-techniques') {
            return $this->normalizeDetailsTechniquesData($data);
        }

        if ($sectionType === 'details-colonnes') {
            return $this->normalizeDetailsColonnesData($data);
        }

        if ($sectionType === 'detail-technique-img-droite') {
            return $this->normalizeDetailTechniqueImgDroiteData($data);
        }

        if ($sectionType === 'liens-articles') {
            return $this->normalizeLiensArticlesData($data);
        }

        if ($sectionType !== 'evangiles') {
            return $data;
        }

        $structure = $this->get('evangiles');
        $field = isset($structure['fields']['versets']) ? $structure['fields']['versets'] : array();
        $defaults = isset($field['default_items']) && is_array($field['default_items']) ? $field['default_items'] : array();
        $hasExplicitVersets = array_key_exists('versets', $data) || !empty($data['versets_present']);

        if ($hasExplicitVersets) {
            // Si l'administrateur retire une ligne, on respecte exactement ce qui est envoyé.
            // Si toutes les lignes sont retirées, on enregistre une liste vide.
            $versets = isset($data['versets']) && is_array($data['versets']) ? $data['versets'] : array();
        } else {
            // Valeurs utilisées uniquement pour une section nouvellement initialisée
            // ou pour d'anciennes données sans champ versets.
            $versets = $defaults;
        }

        ksort($versets);

        $clean = array();

        foreach ($versets as $line) {
            if (!is_array($line)) {
                continue;
            }

            $reference = isset($line['reference']) ? sanitize_text_field($this->stripBnvShortcode($line['reference'])) : '';

            $clean[] = array(
                'label' => isset($line['label']) ? sanitize_text_field($line['label']) : '',
                'class' => isset($line['class']) ? sanitize_html_class($line['class']) : 'citation-bible',
                'reference' => $reference,
            );
        }

        return array('versets' => array_values($clean));
    }

    public function stripBnvShortcode($reference)
    {
        $reference = trim((string) $reference);
        $reference = preg_replace('/^\[bnv\]/i', '', $reference);
        $reference = preg_replace('/\[\/bnv\]$/i', '', $reference);
        return trim($reference);
    }

    private function normalizeLiensArticlesData($data)
    {
        $links = isset($data['links']) && is_array($data['links']) ? $data['links'] : array();
        ksort($links);

        $clean = array();

        foreach ($links as $link) {
            if (!is_array($link)) {
                continue;
            }

            $label = isset($link['label']) ? sanitize_text_field((string) $link['label']) : '';
            $url = isset($link['url']) ? esc_url_raw((string) $link['url']) : '';
            $post_id = isset($link['post_id']) ? absint($link['post_id']) : 0;

            if ($label === '' && $url === '') {
                continue;
            }

            $clean[] = array('label' => $label, 'url' => $url, 'post_id' => $post_id);
        }

        return array(
            'intro' => isset($data['intro']) ? sanitize_text_field((string) $data['intro']) : '',
            'texte_libre' => isset($data['texte_libre']) ? sanitize_text_field((string) $data['texte_libre']) : '',
            'links' => array_values($clean),
        );
    }

    private function normalizeDetailTechniqueImgDroiteData($data)
    {
        $imageId = isset($data['image_id']) ? absint($data['image_id']) : 0;

        return array(
            'image_id' => $imageId,
            'image_haut_id' => isset($data['image_haut_id']) ? absint($data['image_haut_id']) : 0,
            'image_bas_id' => isset($data['image_bas_id']) ? absint($data['image_bas_id']) : 0,
            'texte_avant' => isset($data['texte_avant']) ? sanitize_text_field((string) $data['texte_avant']) : '',
            'texte_milieu' => isset($data['texte_milieu']) ? sanitize_text_field((string) $data['texte_milieu']) : '',
            'texte_apres' => isset($data['texte_apres']) ? sanitize_text_field((string) $data['texte_apres']) : '',
            'lieu' => isset($data['lieu']) ? sanitize_text_field((string) $data['lieu']) : '',
            'date' => isset($data['date']) ? sanitize_text_field((string) $data['date']) : '',
            'mode_operatoire' => isset($data['mode_operatoire']) ? sanitize_text_field((string) $data['mode_operatoire']) : '',
            'note_mode_operatoire' => isset($data['note_mode_operatoire']) ? sanitize_text_field((string) $data['note_mode_operatoire']) : '',
            'texte_dessous' => isset($data['texte_dessous']) ? sanitize_text_field((string) $data['texte_dessous']) : '',
        );
    }

    private function normalizeDetailsColonnesData($data)
    {
        $imageId = isset($data['image_id']) ? absint($data['image_id']) : 0;
        $imagePosition = isset($data['image_position']) && $data['image_position'] === 'left' ? 'left' : 'right';

        return array(
            'image_id' => $imageId,
            'image_position' => $imagePosition,
            'blocks' => $this->normalizeDetailsBlocks(isset($data['blocks']) ? $data['blocks'] : array()),
        );
    }

    private function normalizeDetailsTechniquesData($data)
    {
        $imageId = isset($data['image_id']) ? absint($data['image_id']) : 0;

        $clean = array(
            'image_id' => $imageId,
            'blocks_before' => $this->normalizeDetailsBlocks(isset($data['blocks_before']) ? $data['blocks_before'] : array()),
            'blocks_after' => $this->normalizeDetailsBlocks(isset($data['blocks_after']) ? $data['blocks_after'] : array()),
        );

        return $clean;
    }

    private function normalizeDetailsBlocks($blocks)
    {
        $blocks = is_array($blocks) ? $blocks : array();
        ksort($blocks);

        $clean = array();

        foreach ($blocks as $block) {
            if (!is_array($block)) {
                continue;
            }

            $content = isset($block['content']) ? wp_kses_post((string) $block['content']) : '';

            if (trim($content) === '') {
                continue;
            }

            $clean[] = array('content' => $content);
        }

        return array_values($clean);
    }

}
