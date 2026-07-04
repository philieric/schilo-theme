<?php

namespace Schilo\Builder\Service;

use Schilo\Builder\Admin\SettingsPage;

class CategoryAssigner
{
    public function assignCategoryOnSave($postId, $post, $update)
    {
        if (!$post || !isset($post->post_type) || $post->post_type !== 'post') {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (wp_is_post_revision($postId) || wp_is_post_autosave($postId)) {
            return;
        }

        if (!current_user_can('edit_post', (int) $postId)) {
            return;
        }

        $prefix = $this->detectPrefixFromTitle(get_the_title((int) $postId));

        if ($prefix === '') {
            return;
        }

        $mappings = get_option(SettingsPage::OPTION_PREFIX_CATEGORIES, array());

        if (!is_array($mappings) || empty($mappings[$prefix])) {
            return;
        }

        $categoryId = (int) $mappings[$prefix];

        if ($categoryId <= 0 || !term_exists($categoryId, 'category')) {
            return;
        }

        $currentCategories = wp_get_post_categories((int) $postId);

        if (!is_array($currentCategories)) {
            $currentCategories = array();
        }

        if (!in_array($categoryId, $currentCategories, true)) {
            $currentCategories[] = $categoryId;
        }

        $defaultCategoryId = (int) get_option('default_category');

        if ($defaultCategoryId > 0 && $defaultCategoryId !== $categoryId) {
            $currentCategories = array_diff($currentCategories, array($defaultCategoryId));
        }

        wp_set_post_categories(
            (int) $postId,
            array_values(array_unique(array_map('intval', $currentCategories))),
            false
        );
    }

    private function detectPrefixFromTitle($title)
    {
        $title = html_entity_decode((string) $title, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $title = trim($title);

        if (preg_match('/^([A-Za-z]{3})\d*/u', $title, $matches)) {
            return strtoupper($matches[1]);
        }

        return '';
    }
}
