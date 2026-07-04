<?php
$data = method_exists($section, 'getData') ? $section->getData() : array();

$links = isset($data['links']) && is_array($data['links']) ? $data['links'] : array();
// Déduplique l'intro si la migration a concaténé la même phrase (ex: "A : A : A :")
$intro_raw = isset($data['intro']) ? (string) $data['intro'] : '';
$intro = $intro_raw !== ''
    ? preg_replace('/^(.{5,}?)(?:\s*:\s*\1)+(\s*:?\s*)$/u', '$1$2', trim($intro_raw))
    : '';
$texteLibre = isset($data['texte_libre']) ? $data['texte_libre'] : '';
?>
<section class="<?php echo esc_attr($sectionClass); ?>">
    <?php if ($section->getTitle() !== '') : ?>
        <h2 class="schilo-section-title" id="<?php echo esc_attr(sanitize_title($section->getTitle())); ?>">
            <?php echo esc_html($section->getTitle()); ?>
        </h2>
    <?php endif; ?>

    <?php if (trim((string) $intro) !== '') : ?>
        <p class="schilo-links-intro"><?php echo esc_html($intro); ?></p>
    <?php endif; ?>

    <?php if (trim((string) $texteLibre) !== '') : ?>
        <div class="schilo-details-blocks schilo-details-blocks-before-top">
            <div class="schilo-details-box">
                <span class="schilo-details-icon" aria-hidden="true">i</span>
                <div class="schilo-details-box-content">
                    <p><?php echo esc_html($texteLibre); ?></p>
                </div>
            </div>
        </div>
    <?php endif; ?>


    <?php if (!empty($links)) : ?>
        <ul class="schilo-links-list">
            <?php foreach ($links as $link) : ?>
                <?php
                $label = isset($link['label']) ? $link['label'] : '';
                $url = isset($link['url']) ? $link['url'] : '';

                if ($label === '' && $url === '') {
                    continue;
                }

                if ($label === '') {
                    $label = $url;
                }
                ?>
                <li class="schilo-links-item">
                    <?php if ($url !== '') : ?>
                        <a class="schilo-links-link" href="<?php echo esc_url($url); ?>">
                            <span class="schilo-links-arrow" aria-hidden="true">&rarr;</span>
                            <span class="schilo-links-label"><?php echo esc_html($label); ?></span>
                        </a>
                    <?php else : ?>
                        <span class="schilo-links-link schilo-links-link-disabled">
                            <span class="schilo-links-label"><?php echo esc_html($label); ?></span>
                        </span>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>
