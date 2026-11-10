<?php
/**
 * Dedicated Brand Intelligence Card partial for the brands directory V3 Final Polish.
 *
 * @package HelmetsanTheme
 */

if (! defined('ABSPATH')) {
    exit;
}

$postId          = get_the_ID();
$title           = get_the_title();
$logoUrl         = helmetsan_get_logo_url($postId);
$rawCountry      = (string) get_post_meta($postId, 'brand_origin_country', true);
$cData           = helmetsan_normalize_country($rawCountry);
$country         = $cData['name'];
$flag            = $cData['flag'];
$isInternational = $cData['is_international'];

$founded         = (string) get_post_meta($postId, 'brand_founded_year', true);
$motto           = (string) get_post_meta($postId, 'brand_motto', true);
$excerpt         = get_the_excerpt($postId);
$helmetCount     = function_exists('helmetsan_get_brand_helmet_count') ? helmetsan_get_brand_helmet_count($postId) : 0;
$helmetTypesMeta = (string) get_post_meta($postId, 'brand_helmet_types', true);
$certCoverage    = (string) get_post_meta($postId, 'brand_certification_coverage', true);

// Verification status check
$statusMeta   = (string) get_post_meta($postId, 'brand_verification_status', true);
$isUnverified = (stripos($statusMeta, 'unverified') !== false || stripos($motto, 'unverified') !== false || empty($rawCountry));

// Short description fallback
$shortDesc = $motto !== '' ? $motto : $excerpt;
if ($shortDesc === '' || stripos($shortDesc, 'unverified') !== false) {
    if (!empty($helmetTypesMeta)) {
        $shortDesc = 'Specialized in ' . esc_html($helmetTypesMeta) . ' motorcycle helmets.';
    } else {
        $shortDesc = 'Certified motorcycle helmet manufacturer.';
    }
}
?>

<article <?php post_class('brand-card brand-card--editorial hs-panel'); ?>>
    <!-- 1. TOP META BAR -->
    <div class="brand-card__top">
        <span class="brand-card__country-chip <?php echo $isInternational ? 'brand-card__country-chip--intl' : ''; ?>">
            <span class="brand-card__flag" aria-hidden="true"><?php echo $flag; ?></span>
            <span class="brand-card__country-name"><?php echo esc_html($country); ?></span>
        </span>
        <span class="brand-card__helmet-count">
            <?php echo $helmetCount > 0 ? number_format_i18n($helmetCount) . ' helmets' : '0 helmets'; ?>
        </span>
    </div>

    <!-- 2. HIGH-CONTRAST LOGO CONTAINER -->
    <a href="<?php the_permalink(); ?>" class="brand-card__logo-box" aria-label="<?php echo esc_attr($title); ?>">
        <?php if ($logoUrl !== '') : ?>
            <img class="brand-card__logo-img" src="<?php echo esc_url($logoUrl); ?>" alt="<?php the_title_attribute(); ?>" loading="lazy" />
        <?php else : ?>
            <div class="brand-card__logo-fallback"><?php echo esc_html(strtoupper(substr($title, 0, 2))); ?></div>
        <?php endif; ?>
    </a>

    <!-- 3. BRAND CARD BODY -->
    <div class="brand-card__body">
        <h3 class="brand-card__title">
            <a href="<?php the_permalink(); ?>" class="brand-card__title-link"><?php the_title(); ?></a>
        </h3>

        <p class="brand-card__desc"><?php echo esc_html($shortDesc); ?></p>

        <!-- Brand Specifications / Badges -->
        <div class="brand-card__meta-row">
            <?php if ($founded !== '') : ?>
                <span class="brand-card__spec-pill">🏛️ Est. <?php echo esc_html($founded); ?></span>
            <?php endif; ?>
            <?php if (!empty($certCoverage)) : 
                $firstCert = trim(explode(',', $certCoverage)[0]);
                if ($firstCert !== '') : ?>
                    <span class="brand-card__spec-pill">🛡️ <?php echo esc_html($firstCert); ?></span>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <?php if ($isUnverified) : ?>
            <div class="brand-card__unverified-badge" title="Brand information pending independent verification">
                <span>⚠️ Unverified Status</span>
            </div>
        <?php endif; ?>
    </div>

    <!-- 4. ACTION FOOTER -->
    <div class="brand-card__footer">
        <a class="brand-card__action-link" href="<?php the_permalink(); ?>">
            <span>Explore Catalog</span> <span class="brand-card__arrow" aria-hidden="true">→</span>
        </a>
    </div>
</article>
