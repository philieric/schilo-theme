<?php
$data      = method_exists( $section, 'getData' ) ? $section->getData() : array();
$questions = isset( $data['questions'] ) && is_array( $data['questions'] ) ? $data['questions'] : array();
?>
<section class="<?php echo esc_attr( $sectionClass ); ?>">
    <?php if ( $section->getTitle() !== '' ) : ?>
        <h2 class="schilo-section-title" id="<?php echo esc_attr( sanitize_title( $section->getTitle() ) ); ?>">
            <?php echo esc_html( $section->getTitle() ); ?>
        </h2>
    <?php endif; ?>

    <?php if ( ! empty( $questions ) ) : ?>
    <div class="schilo-questions-list" id="sec-questions">
        <?php foreach ( $questions as $i => $q ) :
            $qtext   = isset( $q['text'] )   ? trim( (string) $q['text'] )   : '';
            $answer  = isset( $q['answer'] ) ? trim( (string) $q['answer'] ) : '';
            if ( $qtext === '' && $answer === '' ) continue;
        ?>
        <div class="schilo-question-item">
            <?php if ( $qtext !== '' ) : ?>
            <p class="schilo-question-text">
                <span class="schilo-question-num"><?php echo esc_html( $i + 1 ); ?>.</span>
                <?php echo esc_html( $qtext ); ?>
            </p>
            <?php endif; ?>
            <?php if ( $answer !== '' ) : ?>
            <div class="schilo-question-answer"><?php echo wp_kses_post( wpautop( $answer ) ); ?></div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</section>
