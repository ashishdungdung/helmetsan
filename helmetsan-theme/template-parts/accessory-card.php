<?php
/**
 * Accessory Decision Card Partial V3 Editorial Final Polish
 *
 * @package HelmetsanTheme
 */

if (! defined('ABSPATH')) {
    exit;
}

$id = get_the_ID();

// Metadata
$brandName      = (string) get_post_meta($id, 'accessory_brand', true);
$color          = (string) get_post_meta($id, 'accessory_color', true);
$parentCategory = (string) get_post_meta($id, 'accessory_parent_category', true);
$subcategory    = (string) get_post_meta($id, 'accessory_subcategory', true);
$pinlockReady   = (string) get_post_meta($id, 'accessory_pinlock_ready', true);
$electricCompat = (string) get_post_meta($id, 'accessory_electric_compatible', true);
$snowCompat     = (string) get_post_meta($id, 'accessory_snow_compatible', true);
$priceJson      = (string) get_post_meta($id, 'price_json', true);
$brandsJson     = (string) get_post_meta($id, 'compatible_brands_json', true);
$helmetTypesJson = (string) get_post_meta($id, 'compatible_helmet_types_json', true);

$price       = is_string($priceJson) && $priceJson !== '' ? json_decode($priceJson, true) : null;
$brands      = is_string($brandsJson) && $brandsJson !== '' ? json_decode($brandsJson, true) : [];
$helmetTypes = is_string($helmetTypesJson) && $helmetTypesJson !== '' ? json_decode($helmetTypesJson, true) : [];

$priceCurrent = null;
if (is_array($price)) {
    if (isset($price['current']) && is_numeric($price['current'])) {
        $priceCurrent = (float) $price['current'];
    } elseif (isset($price['usd']) && is_numeric($price['usd'])) {
        $priceCurrent = (float) $price['usd'];
    }
}

// Build optional regional manual pricing if specific currencies exist
$manualPricing = [];
if (is_array($price)) {
    if (isset($price['inr']) && is_numeric($price['inr'])) {
        $manualPricing['IN'] = ['price' => (float) $price['inr'], 'currency' => 'INR'];
    }
    if (isset($price['eur']) && is_numeric($price['eur'])) {
        $manualPricing['DE'] = ['price' => (float) $price['eur'], 'currency' => 'EUR'];
        $manualPricing['FR'] = ['price' => (float) $price['eur'], 'currency' => 'EUR'];
    }
    if (isset($price['gbp']) && is_numeric($price['gbp'])) {
        $manualPricing['GB'] = ['price' => (float) $price['gbp'], 'currency' => 'GBP'];
    }
}
$manualPricingAttr = ! empty($manualPricing) ? esc_attr(wp_json_encode($manualPricing)) : '';

// Multi-tier image fallback pipeline (§10)
$imgUrl = get_the_post_thumbnail_url($id, 'medium_large');
if (! $imgUrl) {
    foreach (['image_url', 'thumbnail_url', 'image', 'featured_image', 'accessory_image'] as $metaKey) {
        $metaVal = (string) get_post_meta($id, $metaKey, true);
        if ($metaVal !== '' && filter_var($metaVal, FILTER_VALIDATE_URL)) {
            $imgUrl = $metaVal;
            break;
        }
    }
}
if (! $imgUrl && is_array($price) && ! empty($price['image_url']) && filter_var($price['image_url'], FILTER_VALIDATE_URL)) {
    $imgUrl = (string) $price['image_url'];
}

$catTerms = get_the_terms($id, 'accessory_category');
$catSlug  = (is_array($catTerms) && ! empty($catTerms)) ? $catTerms[0]->slug : '';
$catName  = (is_array($catTerms) && ! empty($catTerms)) ? $catTerms[0]->name : ($parentCategory ?: 'Accessory');

if ($brandName === '') {
    $brandName = ! empty($brands) ? $brands[0] : 'Motorcycle Equipment';
}
?>

<article <?php post_class('accessory-card accessory-card--editorial hs-panel'); ?>>
    <!-- 1. IMAGE CONTAINER WITH CATEGORY SILHOUETTE FALLBACK -->
    <a href="<?php the_permalink(); ?>" class="accessory-card__image-container" aria-label="<?php echo esc_attr(get_the_title()); ?>">
        <?php if ($imgUrl) : ?>
            <img src="<?php echo esc_url($imgUrl); ?>" alt="<?php the_title_attribute(); ?>" class="accessory-card__image" loading="lazy" />
        <?php else : ?>
            <div class="accessory-card__image-fallback">
                <?php if (strpos($catSlug, 'visor') !== false || strpos($catSlug, 'shield') !== false || strpos($catSlug, 'pinlock') !== false) : ?>
                    <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/></svg>
                <?php elseif (strpos($catSlug, 'comm') !== false || strpos($catSlug, 'camera') !== false) : ?>
                    <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M3 18v-6a9 9 0 0 1 18 0v6"/><path d="M21 19a2 2 0 0 1-2 2h-1a2 2 0 0 1-2-2v-3a2 2 0 0 1 2-2h3zM3 19a2 2 0 0 0 2 2h1a2 2 0 0 0 2-2v-3a2 2 0 0 0-2-2H3z"/></svg>
                <?php elseif (strpos($catSlug, 'liner') !== false || strpos($catSlug, 'pad') !== false || strpos($catSlug, 'balaclava') !== false) : ?>
                    <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M12 2a10 10 0 0 0-10 10c0 5.5 4.5 10 10 10s10-4.5 10-10A10 10 0 0 0 12 2z"/><path d="M8 14s1.5 2 4 2 4-2 4-2"/></svg>
                <?php else : ?>
                    <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
                <?php endif; ?>
                <span class="accessory-card__fallback-title"><?php echo esc_html($catName); ?></span>
            </div>
        <?php endif; ?>

        <!-- Feature Badges -->
        <div class="accessory-card__feature-chips">
            <?php if ($pinlockReady === '1') : ?>
                <span class="hs-cert-chip">Pinlock Ready</span>
            <?php endif; ?>
            <?php if ($electricCompat === '1') : ?>
                <span class="hs-cert-chip">Electric</span>
            <?php endif; ?>
            <?php if ($snowCompat === '1') : ?>
                <span class="hs-cert-chip">Snow</span>
            <?php endif; ?>
        </div>
    </a>

    <!-- 2. CARD CONTENT (§15: Brand -> Title -> Compatibility -> Price -> Link) -->
    <div class="accessory-card__content">
        <!-- 1. BRAND FIRST -->
        <div class="accessory-card__brand"><?php echo esc_html(strtoupper($brandName)); ?></div>

        <!-- 2. PRODUCT NAME SECOND -->
        <h3 class="accessory-card__title">
            <a href="<?php the_permalink(); ?>" class="accessory-card__title-link"><?php the_title(); ?></a>
        </h3>

        <!-- 3. COMPATIBILITY THIRD (§16, §17) -->
        <div class="accessory-card__compat-block">
            <?php if (! empty($brands)) : ?>
                <span class="accessory-card__compat-badge accessory-card__compat-badge--confirmed">
                    ✓ Confirmed compatible: <?php echo esc_html(implode(', ', array_slice($brands, 0, 2))); ?>
                </span>
            <?php elseif (! empty($helmetTypes)) : ?>
                <span class="accessory-card__compat-badge accessory-card__compat-badge--likely">
                    ◐ Compatibility likely: <?php echo count($helmetTypes); ?> helmet types
                </span>
            <?php else : ?>
                <span class="accessory-card__compat-badge accessory-card__compat-badge--unverified">
                    ? Compatibility unverified
                </span>
            <?php endif; ?>
        </div>

        <!-- 4. PRICE FOURTH -->
        <div class="accessory-card__price-row">
            <?php if ($priceCurrent !== null && $priceCurrent > 0) : ?>
                <span class="hs-price" data-base-price="<?php echo esc_attr((string) $priceCurrent); ?>" data-base-currency="USD"<?php echo $manualPricingAttr !== '' ? ' data-manual-pricing="' . $manualPricingAttr . '"' : ''; ?>>
                    $<?php echo esc_html(number_format($priceCurrent, 2)); ?>
                </span>
            <?php else : ?>
                <span class="accessory-card__price-fallback">—</span>
            <?php endif; ?>
        </div>

        <!-- 5. ACTION FOOTER -->
        <div class="accessory-card__view-link">
            <a href="<?php the_permalink(); ?>" class="accessory-card__action">
                View accessory <span class="accessory-card__arrow">→</span>
            </a>
        </div>
    </div>
</article>


