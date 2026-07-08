<?php

namespace Schilo\Builder\Front;

use Schilo\Builder\Service\ContextualDefinitionService;

class ContextualDefinitionRenderer
{
    private array $matched = array();
    private bool $includeBiblicalReferences = true;

    public function register(): void
    {
        add_filter('the_content', array($this, 'enhance'), 30);
        add_action('wp_footer', array($this, 'renderModals'), 20);
    }

    public function enhance(string $content): string
    {
        if (!is_singular('post') || !in_the_loop() || !is_main_query()) return $content;
        $currentId = (int)get_the_ID();
        $service = new ContextualDefinitionService();
        $settings = $service->getSettings();
        $this->includeBiblicalReferences = !empty($settings['include_biblical_references']);

        foreach ($service->getDefinitions() as $definition) {
            if ($currentId === $definition['source_id']) continue;
            foreach ($definition['terms'] as $term) {
                $quotedTerm = preg_quote($term, '/');
                $quotedTerm = str_replace("'", "['’]", $quotedTerm);
                $quotedTerm = str_replace('\\ ', '[\s\p{P}]+', $quotedTerm);
                $pattern = '/(?<![\pL\pN])(' . $quotedTerm . ')(?![\pL\pN])/iu';
                $modalId = 'schilo-definition-modal-' . $definition['source_id'];
                [$content, $replaced] = $this->replaceEligibleOccurrences($content, $pattern, $modalId, $definition['code']);
                if ($replaced) {
                    $this->matched[$definition['source_id']] = $definition;
                    break;
                }
            }
        }
        return $content;
    }

    public function renderModals(): void
    {
        foreach ($this->matched as $definition) {
            $source = get_post($definition['source_id']);
            if (!$source) continue;
            $body = $this->getBody($source);
            $modalId = 'schilo-definition-modal-' . $source->ID;
            ?>
            <div class="schilo-definition-modal" id="<?php echo esc_attr($modalId); ?>" aria-hidden="true">
                <div class="schilo-definition-modal__overlay" data-schilo-definition-close></div>
                <section class="schilo-definition-modal__panel" role="dialog" aria-modal="true" aria-labelledby="<?php echo esc_attr($modalId); ?>-title" tabindex="-1">
                    <header class="schilo-definition-modal__header">
                        <span class="schilo-definition-modal__code"><?php echo esc_html($definition['code']); ?></span>
                        <div><span class="schilo-definition-modal__eyebrow">Note d’information</span><h2 id="<?php echo esc_attr($modalId); ?>-title"><?php echo esc_html(get_the_title($source)); ?></h2></div>
                        <button type="button" class="schilo-definition-modal__close" data-schilo-definition-close aria-label="Fermer la définition">&times;</button>
                    </header>
                    <div class="schilo-definition-modal__body"><?php echo $body; // phpcs:ignore ?></div>
                    <footer class="schilo-definition-modal__footer"><span>Définition issue de la bibliothèque Schilo</span><a href="<?php echo esc_url(get_permalink($source)); ?>">Consulter la fiche complète <i class="ti ti-arrow-right" aria-hidden="true"></i></a></footer>
                </section>
            </div>
            <?php
        }
    }

    private function getBody(\WP_Post $source): string
    {
        $sections = get_post_meta($source->ID, '_schilo_builder_sections', true);
        $body = '';
        if (is_array($sections)) {
            foreach ($sections as $section) {
                if (empty($section['content'])) continue;
                if (!empty($section['title'])) $body .= '<h3>' . esc_html($section['title']) . '</h3>';
                $body .= wpautop(wp_kses_post($section['content']));
            }
        }
        return $body !== '' ? $body : wpautop(wp_kses_post($source->post_content));
    }

    private function replaceEligibleOccurrences(string $content, string $pattern, string $modalId, string $code): array
    {
        $parts = preg_split('/(<[^>]+>)/u', $content, -1, PREG_SPLIT_DELIM_CAPTURE);
        if (!is_array($parts)) return array($content, false);

        $excludedTags = array('a', 'button', 'script', 'style', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6');
        $stack = array();
        $excludedDepth = 0;
        $replacementCount = 0;
        $maxCandidates = 12;

        foreach ($parts as $index => $part) {
            if ($part === '' || $part[0] !== '<') {
                if ($replacementCount >= $maxCandidates || $excludedDepth > 0 || !preg_match($pattern, $part)) continue;
                $remaining = $maxCandidates - $replacementCount;
                $parts[$index] = preg_replace_callback($pattern, function (array $match) use ($modalId, $code, &$replacementCount): string {
                    $isActive = $replacementCount === 0;
                    $replacementCount++;
                    $classes = 'schilo-definition-trigger schilo-definition-candidate ' . ($isActive ? 'is-visible' : 'is-passive');
                    $inactiveAttributes = $isActive ? '' : ' disabled aria-disabled="true" tabindex="-1"';
                    return '<button type="button" class="' . esc_attr($classes) . '" data-schilo-definition-open="' . esc_attr($modalId) . '" data-schilo-definition-candidate="' . esc_attr($modalId) . '" aria-haspopup="dialog" aria-controls="' . esc_attr($modalId) . '"' . $inactiveAttributes . '><span>' . esc_html($match[1]) . '</span><i class="ti ti-book-2" aria-hidden="true"></i><span class="schilo-sr-only"> — afficher la définition ' . esc_html($code) . '</span></button>';
                }, $part, $remaining);
                continue;
            }

            if (preg_match('/^<\s*\/\s*[a-z0-9]+/i', $part)) {
                $entry = array_pop($stack);
                if (!empty($entry['excluded'])) $excludedDepth--;
                continue;
            }
            if (!preg_match('/^<\s*([a-z0-9]+)/i', $part, $opening)) continue;

            $tag = strtolower($opening[1]);
            $isVoid = preg_match('/\/\s*>$/', $part) || in_array($tag, array('br', 'hr', 'img', 'input', 'meta', 'link'), true);
            if ($isVoid) continue;

            $excluded = in_array($tag, $excludedTags, true)
                || (bool)preg_match('/\bclass\s*=\s*(["\'])[^"\']*\bschilo-definition-trigger\b[^"\']*\1/i', $part)
                || (!$this->includeBiblicalReferences
                    && (bool)preg_match('/\bclass\s*=\s*(["\'])[^"\']*\b(?:bvc-container|usx-version-switcher)\b[^"\']*\1/i', $part));
            $stack[] = array('excluded' => $excluded);
            if ($excluded) $excludedDepth++;
        }

        return array(implode('', $parts), $replacementCount > 0);
    }
}
