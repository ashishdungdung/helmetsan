<?php
/**
 * Helmet CTA partial.
 *
 * Uses the price engine to display live pricing alongside the CTA.
 * Falls back to ASIN-based URL if no live price is available.
 *
 * @package HelmetsanTheme
 */

$postId = get_the_ID();
$slug   = get_post_field('post_name', $postId);

// Try the price engine first
$bestPrice = null;
if (function_exists('helmetsan_core')) {
    $bestPrice = helmetsan_core()->price()->getBestPrice((int) $postId);
}

if ($bestPrice !== null) :
    $goUrl       = home_url('/go/' . $slug . '/?marketplace=' . urlencode($bestPrice->marketplaceId) . '&source=single_page');
    $formatted   = helmetsan_core()->price()->formatPrice($bestPrice->price, $bestPrice->currency);
    ?>
    <div class="hs-cta-wrap">
        <a class="hs-btn hs-btn--primary hs-price-cta"
           href="<?php echo esc_url($goUrl); ?>"
           rel="nofollow sponsored"
           data-marketplace="<?php echo esc_attr($bestPrice->marketplaceId); ?>"
           data-price="<?php echo esc_attr((string) $bestPrice->price); ?>">
            Check Price &mdash; <?php echo esc_html($formatted); ?>
        </a>
    </div>
    <?php
else :
    // Fallback: ASIN-based URL
    $asin = get_post_meta($postId, 'affiliate_asin', true);
    if ($asin !== '') :
        $goUrl = home_url('/go/' . $slug . '/?source=single_page');
        ?>
        <p><a class="hs-btn hs-btn--primary hs-price-cta" href="<?php echo esc_url($goUrl); ?>" rel="nofollow sponsored">Check Price</a></p>
        <?php
    endif;
endif;
