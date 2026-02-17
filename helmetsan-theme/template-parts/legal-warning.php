<?php
/**
 * Legal warning partial.
 *
 * @package HelmetsanTheme
 */

$legal = get_post_meta(get_the_ID(), 'legal_warning', true);
if ($legal !== '') :
    ?>
    <div class="helmet-legal-warning"><?php echo esc_html((string) $legal); ?></div>
    <?php
endif;
