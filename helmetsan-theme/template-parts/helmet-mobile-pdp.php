<?php
/**
 * Mobile-first helmet PDP layout.
 *
 * @package HelmetsanTheme
 */

if (! defined('ABSPATH')) {
    exit;
}

$helmetId = isset($args['helmet_id']) ? (int) $args['helmet_id'] : get_the_ID();
$brandId = isset($args['brand_id']) ? (int) $args['brand_id'] : 0;
$brandName = isset($args['brand_name']) ? (string) $args['brand_name'] : '';
$weight = isset($args['weight']) ? (string) $args['weight'] : '';
$weightLbs = isset($args['weight_lbs']) ? (string) $args['weight_lbs'] : '';
$shell = isset($args['shell']) ? (string) $args['shell'] : '';
$price = isset($args['price']) ? (string) $args['price'] : '';
$certs = isset($args['certs']) ? (string) $args['certs'] : '';
$headShape = isset($args['head_shape']) ? (string) $args['head_shape'] : '';
$helmetFamily = isset($args['helmet_family']) ? (string) $args['helmet_family'] : '';
$productDetails = isset($args['product_details']) && is_array($args['product_details']) ? $args['product_details'] : [];
$variants = isset($args['variants']) && is_array($args['variants']) ? $args['variants'] : [];
$sizingFit = isset($args['sizing_fit']) && is_array($args['sizing_fit']) ? $args['sizing_fit'] : [];
$relatedVideos = isset($args['related_videos']) && is_array($args['related_videos']) ? $args['related_videos'] : [];
$relatedAccessories = isset($args['related_accessories']) && is_array($args['related_accessories']) ? $args['related_accessories'] : [];
$relatedHelmets = isset($args['related_helmets']) && is_array($args['related_helmets']) ? $args['related_helmets'] : [];

$sizeOptions = [];
if (isset($sizingFit['size_translation']) && is_array($sizingFit['size_translation'])) {
    foreach ($sizingFit['size_translation'] as $row) {
        if (! is_array($row)) {
            continue;
        }
        $label = isset($row['size']) ? trim((string) $row['size']) : '';
        if ($label !== '') {
            $sizeOptions[$label] = $label;
        }
    }
}
if ($sizeOptions === [] && $variants !== []) {
    foreach ($variants as $row) {
        if (! is_array($row)) {
            continue;
        }
        $label = isset($row['size']) ? trim((string) $row['size']) : '';
        if ($label !== '') {
            $sizeOptions[$label] = $label;
        }
    }
}

$slug = (string) get_post_field('post_name', $helmetId);
$ctaUrl = $slug !== '' ? home_url('/go/' . $slug . '/?source=single_page_mobile') : '';
?>
<article <?php post_class('helmet-mobile-pdp hs-section'); ?>>
    <?php get_template_part('template-parts/helmet-mobile-sticky-head', null, [
        'helmet_id' => $helmetId,
        'brand_name' => $brandName,
        'price' => $price,
    ]); ?>
    <header class="helmet-mobile-pdp__head hs-panel">
        <p class="hs-eyebrow"><?php echo esc_html($brandName !== '' ? $brandName : 'Helmet'); ?></p>
        <h1><?php the_title(); ?></h1>
        <p class="helmet-mobile-pdp__rating"><?php echo esc_html($certs !== '' ? $certs : 'Certification details available'); ?></p>
        <p class="helmet-mobile-pdp__price"><?php echo helmetsan_render_price_element($helmetId); ?></p>
    </header>

    <section class="helmet-mobile-pdp__gallery hs-panel" style="padding:0;">
        <?php 
        $gallery = helmetsan_core()->mediaService()->getProductGallery($helmetId);
        if (!empty($gallery)) : ?>
            <div class="hs-carousel" role="region" aria-roledescription="carousel" aria-label="<?php echo esc_attr(sprintf(__('Gallery for %s', 'helmetsan-theme'), get_the_title())); ?>">
                <div class="hs-carousel__track">
                    <?php foreach ($gallery as $item) : ?>
                        <div class="hs-carousel__slide">
                            <?php if ($item['type'] === 'video') : ?>
                                <div class="hs-responsive-embed"><?php echo $item['embed']; ?></div>
                            <?php else : ?>
                                <img src="<?php echo esc_url($item['url']); ?>" alt="<?php echo esc_attr($item['alt'] ?? ''); ?>" loading="lazy">
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php else : ?>
            <div class="helmet-single__placeholder" style="padding: 2rem; text-align: center;">
                <p class="helmet-single__placeholder-text" style="font-weight: 800; font-size: 0.85rem; margin-bottom: 0.25rem;">IMAGE UNAVAILABLE</p>
                <p class="helmet-single__placeholder-hint" style="font-size: 0.75rem; color: var(--hs-muted);">Helmetsan does not currently have a verified product image for this model.</p>
            </div>
        <?php endif; ?>
    </section>

    <div class="hs-segmented-control" id="hsPdpSegments">
        <button class="hs-segmented-control__btn is-active" data-segment="store">
            <?php echo helmetsan_get_icon('store'); ?>
            <span>Store</span>
        </button>
        <button class="hs-segmented-control__btn" data-segment="specs">
            <?php echo helmetsan_get_icon('specs'); ?>
            <span>Specs</span>
        </button>
        <button class="hs-segmented-control__btn" data-segment="about">
            <?php echo helmetsan_get_icon('analysis'); ?>
            <span>About</span>
        </button>
    </div>

    <div class="hs-segment-content is-active" id="segment-store">
        <!-- Where to Buy (Mobile) -->
        <?php
        $plugin = helmetsan_core();
        $priceService = $plugin->price();
        $bestOffer = $priceService->getBestPrice($helmetId);
        $allOffers = $priceService->getAllOffers($helmetId);
        ?>
        <?php if (!empty($allOffers) || $bestOffer !== null) : ?>
            <section class="hs-panel hs-where-to-buy" id="where-to-buy">
                <h2><?php echo helmetsan_get_icon('cart'); ?> Available Offers</h2>
                <?php if ($bestOffer !== null) : ?>
                    <div class="hs-best-badge">
                        <span class="hs-best-badge__label">Best Price Today</span>
                        <span class="hs-best-badge__price"><?php echo esc_html($priceService->formatPrice($bestOffer->price, $bestOffer->currency)); ?></span>
                        <span class="hs-best-badge__source"><?php echo esc_html(ucfirst($bestOffer->marketplaceId)); ?></span>
                    </div>
                <?php endif; ?>

                <?php if (!empty($allOffers)) : ?>
                    <div class="hs-table-wrap">
                        <table class="hs-table hs-price-table">
                            <thead>
                                <tr>
                                    <th scope="col">Store</th>
                                    <th scope="col">Price</th>
                                    <th scope="col"></th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($allOffers as $offer) :
                                $isBest = $bestOffer !== null && $offer->marketplaceId === $bestOffer->marketplaceId && $offer->price === $bestOffer->price;
                                $mpId = $offer->marketplaceId;
                                $slug = (string) get_post_field('post_name', $helmetId);
                                $goUrl = home_url('/go/' . $slug . '/?marketplace=' . urlencode($mpId) . '&source=mobile_pdp');
                            ?>
                                <tr class="<?php echo $isBest ? 'hs-price-table__row--best' : ''; ?>">
                                    <th scope="row">
                                        <?php echo esc_html(helmetsan_marketplace_label($mpId)); ?>
                                    </th>
                                    <td><strong><?php echo $offer->price > 0 ? esc_html($priceService->formatPrice($offer->price, $offer->currency)) : '<span class="hs-muted">Check price</span>'; ?></strong></td>
                                    <td>
                                        <a href="<?php echo esc_url($goUrl); ?>" class="hs-price-cta" target="_blank" rel="noopener noreferrer sponsored">Check price →</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
                
                <div class="hs-price-chart-wrap" id="hs-price-chart-wrap">
                    <h3>Price Trend</h3>
                    <canvas id="hs-price-chart" data-helmet-id="<?php echo esc_attr((string) $helmetId); ?>" height="220"></canvas>
                </div>
            </section>
        <?php endif; ?>
    </div>

    <div class="hs-segment-content" id="segment-specs">
        <?php 
        get_template_part('template-parts/helmet', 'tech-specs', ['helmet_id' => $helmetId]); 
        ?>

        <section class="helmet-mobile-pdp__size hs-panel">
            <h2>Size & Fit</h2>
            <?php if ($sizeOptions !== []) : ?>
                <div class="hs-pill-grid">
                    <?php foreach ($sizeOptions as $sizeLabel) : ?>
                        <label class="hs-pill-input">
                            <input type="radio" name="helmet_size_mobile" value="<?php echo esc_attr($sizeLabel); ?>" />
                            <span><?php echo esc_html($sizeLabel); ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($sizingFit['size_translation']) && is_array($sizingFit['size_translation']) && $sizingFit['size_translation'] !== []) : ?>
                <div class="hs-table-wrap" style="margin-top: 1rem;">
                    <table class="hs-table">
                        <thead><tr><th scope="col">Size</th><th scope="col">CM</th></tr></thead>
                        <tbody>
                        <?php foreach ($sizingFit['size_translation'] as $row) : if (! is_array($row)) { continue; } ?>
                            <tr>
                                <th scope="row"><?php echo esc_html((string) ($row['size'] ?? '')); ?></th>
                                <td><?php echo esc_html((string) ($row['cm'] ?? '')); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    </div>

    <div class="hs-segment-content" id="segment-about">
        <?php
        $analysis = helmetsan_get_technical_analysis($helmetId);
        $helmetTypeLabel = '';
        $helmetTypeTermsRaw = get_the_terms($helmetId, 'helmet_type');
        if (is_array($helmetTypeTermsRaw) && !empty($helmetTypeTermsRaw)) {
            $helmetTypeLabel = $helmetTypeTermsRaw[0]->name;
        }
        ?>
        <section class="hs-panel">
            <div class="hs-about-card">
                <div class="hs-about-card__body">
                    <h2>Platform Narrative</h2>
                    <?php if ($helmetTypeLabel !== '') : ?>
                        <span class="hs-about-card__type"><?php echo esc_html($helmetTypeLabel); ?></span>
                    <?php endif; ?>
                    <?php $descContent = get_the_content(); ?>
                    <div class="hs-about-card__desc">
                        <?php if ($descContent) : ?>
                            <?php echo wpautop(wp_kses_post($descContent)); ?>
                        <?php elseif ($analysis) : ?>
                            <p><?php echo esc_html($analysis); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </section>

        <?php if ($brandId > 0) : ?>
            <p style="text-align: center; margin: 1rem 0;">
                <a class="hs-btn hs-btn--ghost" href="<?php echo esc_url(get_permalink($brandId)); ?>">
                    Explore <?php echo esc_html($brandName); ?> Profile
                </a>
            </p>
        <?php endif; ?>
    </div>

    <?php if ($relatedAccessories !== []) : ?>
        <section class="hs-panel">
            <h2>Compatible Accessories</h2>
            <div class="helmet-grid">
                <?php foreach ($relatedAccessories as $post) : setup_postdata($post); get_template_part('template-parts/entity', 'card'); endforeach; wp_reset_postdata(); ?>
            </div>
        </section>
    <?php endif; ?>

    <?php if ($relatedHelmets !== []) : ?>
        <section class="hs-panel">
            <h2>More from <?php echo esc_html($brandName); ?></h2>
            <div class="helmet-grid">
                <?php foreach ($relatedHelmets as $post) : setup_postdata($post); get_template_part('template-parts/helmet', 'card'); endforeach; wp_reset_postdata(); ?>
            </div>
        </section>
    <?php endif; ?>

    <?php if ($brandId > 0) : ?>
        <p><a class="hs-link" href="<?php echo esc_url(get_permalink($brandId)); ?>">View brand profile</a></p>
    <?php endif; ?>
</article>

<?php if ($ctaUrl !== '') : ?>
    <div class="helmet-mobile-atc" role="region" aria-label="Helmet purchase actions">
        <div class="helmet-mobile-atc__meta">
            <strong><?php echo helmetsan_render_price_element($helmetId); ?></strong>
            <span><?php echo esc_html($brandName); ?></span>
        </div>
        <a class="hs-btn hs-btn--primary" href="<?php echo esc_url($ctaUrl); ?>" rel="nofollow sponsored">Check Price</a>
    </div>
<?php endif; ?>
