<?php
/**
 * WooCommerce Single Product (child override - minimal, GP-safe).
 *
 * @package HelmetsanTheme
 */

defined('ABSPATH') || exit;

get_header('shop');
?>
<section class="hs-section hs-woo-single-wrap">
    <?php
    do_action('woocommerce_before_main_content');

    while (have_posts()) {
        the_post();
        wc_get_template_part('content', 'single-product');
    }

    do_action('woocommerce_after_main_content');
    do_action('woocommerce_sidebar');
    ?>
</section>
<?php
get_footer('shop');
