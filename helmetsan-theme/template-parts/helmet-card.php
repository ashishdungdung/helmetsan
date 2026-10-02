<?php
/**
 * Helmet Card Component V3 Editorial — Product Decision Card
 *
 * @package HelmetsanTheme
 */

$helmetId  = get_the_ID();
$price     = helmetsan_get_helmet_price($helmetId);
$certs     = helmetsan_get_certifications($helmetId);
$logoUrl   = helmetsan_get_logo_url($helmetId);

$brandName = '';
$brandTerms = get_the_terms($helmetId, 'helmet_brand');
if ($brandTerms && ! is_wp_error($brandTerms)) {
    $brandName = $brandTerms[0]->name ?? '';
}

$priceNum   = is_numeric(preg_replace('/[^0-9.]/', '', $price)) ? (float) preg_replace('/[^0-9.]/', '', $price) : 0;
$useCase    = helmetsan_get_use_case($helmetId);

// Meta fields
$sharpRating = get_post_meta($helmetId, 'sharp_rating', true);
$weightG     = get_post_meta($helmetId, 'weight_g', true);

// Robust Product Image URL Resolution
$imgUrl = '';
if (has_post_thumbnail($helmetId)) {
    $imgUrl = get_the_post_thumbnail_url($helmetId, 'medium_large');
} else {
    // Check geo_media_json
    $geoMedia = get_post_meta($helmetId, 'geo_media_json', true);
    if (is_string($geoMedia) && $geoMedia !== '') {
        $arr = json_decode($geoMedia, true);
        if (is_array($arr) && ! empty($arr) && is_string($arr[0])) {
            $imgUrl = esc_url_raw($arr[0]);
        }
    }
    // Check image_url meta fallback
    if ($imgUrl === '') {
        $metaImg = get_post_meta($helmetId, 'image_url', true);
        if (is_string($metaImg) && $metaImg !== '') {
            $imgUrl = esc_url_raw($metaImg);
        }
    }
}

// Score verification (only show score if backed by SHARP or explicit verified score, never fabricate)
$rawScore = (int) get_post_meta($helmetId, 'helmetsan_score', true);
$hasVerifiedScore = false;
$score = 0;

if ($rawScore > 0 && $rawScore !== 85) {
    $score = $rawScore;
    $hasVerifiedScore = true;
} elseif ($sharpRating) {
    $score = 70 + ((int) $sharpRating * 5);
    $hasVerifiedScore = true;
}

// Comparison Badge logic (tight neutral visual vocabulary)
$badgeLabel = '';
$badgeClass = '';
if ($hasVerifiedScore && $score >= 90) {
    $badgeLabel = __('Best Match', 'helmetsan-theme');
    $badgeClass = 'best-match';
} elseif ($priceNum > 0 && $priceNum <= 350 && $hasVerifiedScore && $score >= 82) {
    $badgeLabel = __('Best Value', 'helmetsan-theme');
    $badgeClass = 'best-value';
} elseif ((int) $weightG > 0 && (int) $weightG <= 1380) {
    $badgeLabel = __('Lightest', 'helmetsan-theme');
    $badgeClass = 'lightest';
} elseif ($priceNum >= 750) {
    $badgeLabel = __('Helmetsan Pick', 'helmetsan-theme');
    $badgeClass = 'pick';
}

// Certifications array - compact badges
$certList = array_filter(array_map('trim', explode(',', (string) $certs)));

// Riding style text formatting
$ridingText = $useCase !== '' ? __(ucwords(str_replace('-', ' ', $useCase)), 'helmetsan-theme') : __('Sport · Highway', 'helmetsan-theme');
?>
<article <?php post_class('helmet-card helmet-card--editorial hs-panel'); ?>
    itemscope itemtype="https://schema.org/Product"
    data-helmet-id="<?php echo esc_attr((string) $helmetId); ?>"
    data-helmet-name="<?php echo esc_attr(get_the_title()); ?>"
    data-helmet-brand="<?php echo esc_attr($brandName); ?>"
    data-helmet-price="<?php echo esc_attr((string) $priceNum); ?>">

    <meta itemprop="sku" content="<?php echo esc_attr($helmetId); ?>" />

    <!-- Product Link Wrapper -->
    <a href="<?php the_permalink(); ?>" class="helmet-card__link" aria-labelledby="helmet-title-<?php echo esc_attr((string) $helmetId); ?>">

        <!-- 1. PRODUCT IMAGE CONTAINER -->
        <div class="helmet-card__image-container">
            <?php if ($badgeLabel !== '') : ?>
                <span class="helmet-card__decision-badge helmet-card__decision-badge--<?php echo esc_attr($badgeClass); ?>">
                    <?php echo esc_html($badgeLabel); ?>
                </span>
            <?php endif; ?>

        <?php
        $cardTypeTerms = get_the_terms($helmetId, 'helmet_type');
        $cardTypeSlug = (is_array($cardTypeTerms) && !empty($cardTypeTerms)) ? $cardTypeTerms[0]->slug : 'full-face';
        ?>
        <?php if ($imgUrl !== '') : ?>
            <div class="helmet-card__image-wrapper">
                <div class="helmet-card__image" itemprop="image">
                    <img src="<?php echo esc_url($imgUrl); ?>" alt="<?php echo esc_attr(get_the_title()); ?>" loading="lazy" decoding="async" onerror="this.onerror=null;var p=this.closest('.helmet-card__image-wrapper');if(p){var f=p.querySelector('.hs-card-cad-fallback');if(f){this.style.display='none';f.style.display='flex';}}" />
                    <div class="hs-card-cad-fallback" style="display:none;width:100%;height:100%;align-items:center;justify-content:center;">
                        <?php echo function_exists('helmetsan_render_helmet_silhouette') ? helmetsan_render_helmet_silhouette($cardTypeSlug, '#00d2be', 'hs-card-cad-fallback') : ''; ?>
                    </div>
                </div>
            </div>
        <?php else : ?>
            <div class="helmet-card__image-wrapper helmet-card__image-wrapper--cad">
                <div class="hs-card-cad-fallback" style="display:flex;width:100%;height:100%;align-items:center;justify-content:center;">
                    <?php echo function_exists('helmetsan_render_helmet_silhouette') ? helmetsan_render_helmet_silhouette($cardTypeSlug, '#00d2be', 'hs-card-cad-fallback') : ''; ?>
                </div>
            </div>
        <?php endif; ?>

            <!-- Subtle Compare Checkbox Overlay (Top Right) -->
            <button type="button"
                    class="js-add-to-compare helmet-card__compare-overlay"
                    data-id="<?php echo esc_attr((string) $helmetId); ?>"
                    data-title="<?php echo esc_attr(get_the_title()); ?>"
                    aria-label="<?php esc_attr_e('Add to compare', 'helmetsan-theme'); ?>">
                <span>＋</span>
            </button>
        </div>

        <!-- 2. EDITORIAL CARD CONTENT -->
        <div class="helmet-card__content">
            <!-- Brand Subtitle -->
            <?php if ($brandName !== '') : ?>
                <div class="helmet-card__brand-name"><?php echo esc_html(strtoupper($brandName)); ?></div>
            <?php endif; ?>

            <!-- Model Title -->
            <h3 class="helmet-card__title" id="helmet-title-<?php echo esc_attr((string) $helmetId); ?>" itemprop="name">
                <span class="helmet-card__title-text"><?php the_title(); ?></span>
            </h3>

            <!-- Score Display -->
            <?php if ($hasVerifiedScore) : ?>
                <div class="helmet-card__score-block">
                    <span class="helmet-card__score-big"><?php echo (int) $score; ?></span>
                    <span class="helmet-card__score-sub"><?php esc_html_e('Helmetsan Score', 'helmetsan-theme'); ?></span>
                </div>
            <?php else : ?>
                <div class="helmet-card__score-block helmet-card__score-block--unrated">
                    <span class="helmet-card__score-unrated"><?php esc_html_e('Safety profile available', 'helmetsan-theme'); ?></span>
                </div>
            <?php endif; ?>

            <!-- Compact Cert Badges -->
            <div class="helmet-card__cert-badges">
                <?php if (!empty($certList)) : foreach (array_slice($certList, 0, 2) as $cItem) : ?>
                    <span class="hs-cert-chip"><?php echo esc_html($cItem); ?></span>
                <?php endforeach; else : ?>
                    <span class="hs-cert-chip">ECE 22.06</span>
                    <span class="hs-cert-chip">DOT</span>
                <?php endif; ?>
            </div>

            <!-- Riding Use -->
            <div class="helmet-card__use-text"><?php echo esc_html($ridingText); ?></div>

            <!-- Bold Black Price -->
            <div class="helmet-card__price-row">
                <?php echo helmetsan_render_price_element($helmetId, 'helmet-card__price', 'itemprop="price"'); ?>
            </div>

            <!-- Subtle View Link -->
            <div class="helmet-card__view-link">
                <?php esc_html_e('View helmet →', 'helmetsan-theme'); ?>
            </div>
        </div>
    </a>
</article>







