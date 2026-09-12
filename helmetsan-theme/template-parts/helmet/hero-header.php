<?php
/**
 * Helmet Single: Hero Header & Navigation
 *
 * @package HelmetsanTheme
 */

if (!defined('ABSPATH')) {
    exit;
}

$brandName       = $args['brandName'] ?? '';
$brandId         = $args['brandId'] ?? 0;
$helmetFamily    = $args['helmetFamily'] ?? '';
$isVariant       = !empty($args['isVariant']);
$parentPost      = $args['parentPost'] ?? null;
$helmetTypeLabel = $args['helmetTypeLabel'] ?? '';
$certs           = $args['certs'] ?? '';
$shell           = $args['shell'] ?? '';
$weight          = (int) ($args['weight'] ?? 0);
?>

<nav class="hs-pdp-breadcrumb" aria-label="<?php esc_attr_e('Breadcrumb', 'helmetsan-theme'); ?>">
    <ol class="hs-pdp-breadcrumb__list">
        <li class="hs-pdp-breadcrumb__item"><a href="<?php echo esc_url(helmetsan_url('/')); ?>"><?php esc_html_e('Home', 'helmetsan-theme'); ?></a></li>
        <li class="hs-pdp-breadcrumb__sep">/</li>
        <li class="hs-pdp-breadcrumb__item"><a href="<?php echo esc_url(helmetsan_url('/helmets/')); ?>"><?php esc_html_e('Helmets', 'helmetsan-theme'); ?></a></li>
        <?php if ($brandName !== '') : ?>
            <li class="hs-pdp-breadcrumb__sep">/</li>
            <li class="hs-pdp-breadcrumb__item"><a href="<?php echo esc_url(helmetsan_permalink($brandId)); ?>"><?php echo esc_html($brandName); ?></a></li>
        <?php endif; ?>
        <li class="hs-pdp-breadcrumb__sep">/</li>
        <li class="hs-pdp-breadcrumb__item hs-pdp-breadcrumb__item--active" aria-current="page"><?php the_title(); ?></li>
    </ol>
</nav>

<header class="helmet-single__hero hs-pdp-hero">
    <p class="helmet-single__eyebrow hs-pdp-hero__eyebrow">
        <?php if ($brandName !== '') : ?>
            <a class="hs-pdp-hero__brand-link" href="<?php echo esc_url(get_permalink($brandId)); ?>"><?php echo esc_html($brandName); ?></a>
        <?php endif; ?>
        <?php if ($helmetFamily !== '') : ?>
            <span class="hs-pdp-hero__eyebrow-sep">·</span> <span class="hs-pdp-hero__family"><?php echo esc_html($helmetFamily); ?></span>
        <?php endif; ?>
        <?php if ($isVariant && $parentPost) : ?>
            <span class="hs-pdp-hero__eyebrow-sep">·</span> <a class="hs-pdp-hero__parent-link" href="<?php echo esc_url(get_permalink($parentPost)); ?>"><?php echo esc_html($parentPost->post_title); ?></a>
        <?php endif; ?>
    </p>
    
    <h1 class="helmet-single__title hs-pdp-hero__title"><?php the_title(); ?></h1>
    
    <!-- Helmetsan Intelligence Metadata Chips & Data Verification Status -->
    <div class="hs-pdp-hero__badge-container">
        <span class="hs-pdp-data-status-badge">
            <span class="hs-pdp-data-status-badge__dot"></span>
            <?php esc_html_e('VERIFIED TECHNICAL SPEC', 'helmetsan-theme'); ?>
        </span>
        <?php if ($helmetTypeLabel !== '') : ?>
            <span class="hs-badge hs-badge--primary"><?php echo esc_html($helmetTypeLabel); ?></span>
        <?php endif; ?>
        <?php if ($certs !== '' && $certs !== 'N/A') : ?>
            <span class="hs-badge hs-badge--accent"><?php echo esc_html($certs); ?></span>
        <?php endif; ?>
        <?php if ($shell !== '') : ?>
            <span class="hs-badge hs-badge--subtle"><?php echo esc_html($shell); ?></span>
        <?php endif; ?>
        <?php if ($weight > 0) : ?>
            <span class="hs-badge hs-badge--subtle"><?php echo esc_html($weight . 'g'); ?></span>
        <?php endif; ?>
    </div>
</header>

<!-- Sticky Technical PDP Section Navigation -->
<nav class="hs-pdp-sticky-nav js-pdp-sticky-nav" aria-label="<?php esc_attr_e('Page sections', 'helmetsan-theme'); ?>">
    <ul class="hs-pdp-sticky-nav__list">
        <li><a href="#quick-verdict" class="hs-pdp-sticky-nav__link is-active"><?php esc_html_e('Quick Verdict', 'helmetsan-theme'); ?></a></li>
        <li><a href="#quick-facts" class="hs-pdp-sticky-nav__link"><?php esc_html_e('Quick Facts', 'helmetsan-theme'); ?></a></li>
        <li><a href="#safety-snapshot" class="hs-pdp-sticky-nav__link"><?php esc_html_e('Safety', 'helmetsan-theme'); ?></a></li>
        <li><a href="#fit-sizing" class="hs-pdp-sticky-nav__link"><?php esc_html_e('Fit & Sizing', 'helmetsan-theme'); ?></a></li>
        <li><a href="#helmet-overview" class="hs-pdp-sticky-nav__link"><?php esc_html_e('Overview', 'helmetsan-theme'); ?></a></li>
        <li><a href="#technical-specs" class="hs-pdp-sticky-nav__link"><?php esc_html_e('Specifications', 'helmetsan-theme'); ?></a></li>
        <li><a href="#where-to-buy" class="hs-pdp-sticky-nav__link"><?php esc_html_e('Where to Buy', 'helmetsan-theme'); ?></a></li>
        <li><a href="#reviews" class="hs-pdp-sticky-nav__link"><?php esc_html_e('Reviews', 'helmetsan-theme'); ?></a></li>
        <li><a href="#compatible-accessories" class="hs-pdp-sticky-nav__link"><?php esc_html_e('Accessories', 'helmetsan-theme'); ?></a></li>
        <li><a href="#data-sources" class="hs-pdp-sticky-nav__link"><?php esc_html_e('Data & Sources', 'helmetsan-theme'); ?></a></li>
    </ul>
</nav>
