<?php
/**
 * Dedicated Brand Intelligence Card partial for the brands directory V3 Final Polish.
 *
 * @package HelmetsanTheme
 */

if (! defined('ABSPATH')) {
    exit;
}

$postId   = get_the_ID();
$title    = get_the_title();
$logoUrl  = helmetsan_get_logo_url($postId);
$rawCountry = (string) get_post_meta($postId, 'brand_origin_country', true);
$cData    = helmetsan_normalize_country($rawCountry);
$country  = $cData['name'];
$flag     = $cData['flag'];
$isInternational = $cData['is_international'];

$founded  = (string) get_post_meta($postId, 'brand_founded_year', true);
$motto    = (string) get_post_meta($postId, 'brand_motto', true);
$excerpt  = get_the_excerpt($postId);
$helmetCount = function_exists('helmetsan_get_brand_helmet_count') ? helmetsan_get_brand_helmet_count($postId) : 0;

// Verification status check
$statusMeta = (string) get_post_meta($postId, 'brand_verification_status', true);
$isUnverified = (stripos($statusMeta, 'unverified') !== false || stripos($motto, 'unverified') !== false || empty($rawCountry));

// Short description fallback
$shortDesc = $motto !== '' ? $motto : $excerpt;
if ($shortDesc === '' || stripos($shortDesc, 'unverified') !== false) {
    $shortDesc = 'Certified motorcycle helmet manufacturer.';
}
?>

<article <?php post_class('brand-card brand-card--editorial hs-panel'); ?>>
    <!-- 1. TOP META BAR -->
    <div class="brand-card__top">
        <span class="brand-card__country-chip <?php echo $isInternational ? 'brand-card__country-chip--intl' : ''; ?>">
            <span class="brand-card__flag"><?php echo $flag; ?></span>
            <span class="brand-card__country-name"><?php echo esc_html($country); ?></span>
        </span>
        <span class="brand-card__helmet-count">
            <?php echo $helmetCount > 0 ? number_format_i18n($helmetCount) . ' helmets' : '0 helmets tracked'; ?>
        </span>
    </div>

    <!-- 2. FIXED LOGO CONTAINER (100px HEIGHT) -->
    <a href="<?php the_permalink(); ?>" class="brand-card__logo-box" aria-label="<?php echo esc_attr($title); ?>">
        <?php if ($logoUrl !== '') : ?>
            <img class="brand-card__logo-img" src="<?php echo esc_url($logoUrl); ?>" alt="<?php the_title_attribute(); ?>" loading="lazy" />
        <?php else : ?>
            <div class="brand-card__logo-fallback"><?php echo esc_html(strtoupper(substr($title, 0, 2))); ?></div>
        <?php endif; ?>
    </a>

    <!-- 3. BRAND CARD BODY -->
    <div class="brand-card__body">
        <!-- Brand Title in Bold Black (16-18px) -->
        <h3 class="brand-card__title">
            <a href="<?php the_permalink(); ?>" class="brand-card__title-link"><?php the_title(); ?></a>
        </h3>

        <!-- Short Description (Clamped to 2 lines) -->
        <p class="brand-card__desc"><?php echo esc_html($shortDesc); ?></p>

        <!-- Real Metadata Subtitle -->
        <div class="brand-card__meta-row">
            <span><?php echo $helmetCount > 0 ? (int) $helmetCount . ' helmets' : '0 helmets tracked'; ?></span>
            <?php if ($founded !== '') : ?>
                <span>· Est. <?php echo esc_html($founded); ?></span>
            <?php endif; ?>
        </div>

        <!-- Verification Status Tag (Only when unverified) -->
        <?php if ($isUnverified) : ?>
            <div class="brand-card__unverified-badge" title="Brand information pending independent verification">
                <span>⚠️ Unverified Status</span>
            </div>
        <?php endif; ?>
    </div>

    <!-- 4. ACTION FOOTER -->
    <div class="brand-card__footer">
        <a class="brand-card__action-link" href="<?php the_permalink(); ?>">
            Explore <?php the_title(); ?> <span class="brand-card__arrow">→</span>
        </a>
    </div>
</article>



