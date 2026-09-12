<?php
/**
 * Today's featured helmet — hero CTA block for the front page.
 * Expects the current post to be a helmet (use after WP_Query + the_post()).
 *
 * @package HelmetsanTheme
 */

if (! defined('ABSPATH')) {
    exit;
}

$helmetId = get_the_ID();
$price = function_exists('helmetsan_render_price_element') ? helmetsan_render_price_element((int) $helmetId, 'hs-featured-helmet__price') : '';
$certs = function_exists('helmetsan_get_certifications') ? helmetsan_get_certifications($helmetId) : '';
$brandName = '';
$brandTerms = get_the_terms($helmetId, 'helmet_brand');
if ($brandTerms && ! is_wp_error($brandTerms)) {
    $brandName = $brandTerms[0]->name ?? '';
}
// Only display the brand line if the post title doesn't already start with the brand name
$postTitle = get_the_title();
if ($brandName !== '' && stripos($postTitle, $brandName) === 0) {
    $brandName = ''; // suppress duplicate — title already starts with brand
}
// Strip all HTML from the excerpt to avoid raw anchor tags leaking into the tagline
$tagline = wp_strip_all_tags(get_the_excerpt());
if ($tagline === '') {
    $tagline = __('Built for riders who demand clarity and compliance.', 'helmetsan-theme');
}
?>
<section class="hs-featured-helmet" aria-labelledby="featured-helmet-title">
    <div class="hs-featured-helmet__inner">
        <div class="hs-featured-helmet__media">
            <?php if (has_post_thumbnail()) : ?>
                <a href="<?php the_permalink(); ?>" class="hs-featured-helmet__link">
                    <?php the_post_thumbnail('large', ['class' => 'hs-featured-helmet__img', 'loading' => 'eager', 'fetchpriority' => 'high']); ?>
                </a>
            <?php else :
                $defaultImg = function_exists('helmetsan_core') ? helmetsan_core()->defaultImages()->getDefaultImageUrl('helmet') : '';
                ?>
                <a href="<?php the_permalink(); ?>" class="hs-featured-helmet__link hs-featured-helmet__link--placeholder">
                    <?php if ($defaultImg !== '') : ?>
                        <img src="<?php echo esc_url($defaultImg); ?>" alt="" class="hs-featured-helmet__img" loading="eager" />
                    <?php else : ?>
                        <span class="hs-featured-helmet__placeholder" aria-hidden="true"></span>
                    <?php endif; ?>
                </a>
            <?php endif; ?>
        </div>
        <div class="hs-featured-helmet__content">
            <p class="hs-featured-helmet__eyebrow"><?php esc_html_e("Today's pick", 'helmetsan-theme'); ?></p>
            <h2 id="featured-helmet-title" class="hs-featured-helmet__title">
                <a href="<?php the_permalink(); ?>" class="hs-featured-helmet__title-link"><?php the_title(); ?></a>
            </h2>
            <?php if ($brandName !== '') : ?>
                <p class="hs-featured-helmet__brand"><?php echo esc_html($brandName); ?></p>
            <?php endif; ?>
            <p class="hs-featured-helmet__tagline"><?php echo esc_html($tagline); ?></p>
            <div class="hs-featured-helmet__specs">
                <?php if ($price !== '') : ?>
                    <?php echo $price; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- helmetsan_render_price_element returns sanitised HTML ?>
                <?php endif; ?>
                <?php if ($certs !== '') : ?>
                    <span class="hs-featured-helmet__certs"><?php echo esc_html($certs); ?></span>
                <?php endif; ?>
            </div>
            <div class="hs-featured-helmet__actions">
                <a href="<?php the_permalink(); ?>" class="hs-btn hs-btn--primary hs-featured-helmet__cta"><?php esc_html_e('View helmet', 'helmetsan-theme'); ?></a>
                <button type="button" class="hs-btn hs-btn--ghost js-add-to-compare" data-id="<?php echo esc_attr((string) $helmetId); ?>" aria-label="<?php esc_attr_e('Add to comparison', 'helmetsan-theme'); ?>"><?php esc_html_e('Add to compare', 'helmetsan-theme'); ?></button>
            </div>
        </div>
    </div>
</section>
