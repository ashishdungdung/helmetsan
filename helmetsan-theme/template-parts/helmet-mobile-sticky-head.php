<?php
/**
 * Sticky mini-header for Mobile PDP.
 * Appears on scroll.
 *
 * @package HelmetsanTheme
 */

if (! defined('ABSPATH')) {
    exit;
}

$helmetId = isset($args['helmet_id']) ? (int) $args['helmet_id'] : get_the_ID();
$brandName = isset($args['brand_name']) ? (string) $args['brand_name'] : '';
$price = isset($args['price']) ? (string) $args['price'] : '';
$slug = (string) get_post_field('post_name', $helmetId);
$ctaUrl = $slug !== '' ? (string) home_url('/go/' . $slug . '/?source=mobile_sticky_head') : '';
?>
<div id="hs-mobile-sticky-header" class="hs-mobile-sticky-head" aria-hidden="true">
    <div class="hs-mobile-sticky-head__inner">
        <div class="hs-mobile-sticky-head__content">
            <div class="hs-mobile-sticky-head__title"><?php the_title(); ?></div>
            <div class="hs-mobile-sticky-head__meta">
                <span class="hs-mobile-sticky-head__brand"><?php echo esc_html($brandName); ?></span>
                <span class="hs-mobile-sticky-head__price"><?php echo helmetsan_render_price_element($helmetId); ?></span>
            </div>
        </div>
        <?php if ($ctaUrl !== '') : ?>
            <a href="<?php echo esc_url($ctaUrl); ?>" class="hs-btn hs-btn--primary hs-btn--sm" rel="nofollow sponsored">Buy</a>
        <?php endif; ?>
    </div>
</div>
