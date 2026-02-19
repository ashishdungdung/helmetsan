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

$ctaUrl = '';
$asin = (string) get_post_meta($helmetId, 'affiliate_asin', true);
if ($asin !== '') {
    $slug = (string) get_post_field('post_name', $helmetId);
    $ctaUrl = (string) home_url('/go/' . $slug . '/?source=single_page_mobile');
}
?>
<article <?php post_class('helmet-mobile-pdp hs-section'); ?>>
    <header class="helmet-mobile-pdp__head hs-panel">
        <p class="hs-eyebrow"><?php echo esc_html($brandName !== '' ? $brandName : 'Helmet'); ?></p>
        <h1><?php the_title(); ?></h1>
        <p class="helmet-mobile-pdp__rating"><?php echo esc_html($certs !== '' ? $certs : 'Certification details available'); ?></p>
        <p class="helmet-mobile-pdp__price"><?php echo esc_html($price !== '' ? $price : 'N/A'); ?></p>
    </header>

    <section class="helmet-mobile-pdp__gallery hs-panel" style="padding:0;">
        <?php 
        $gallery = helmetsan_core()->mediaService()->getProductGallery($helmetId);
        if (!empty($gallery)) : ?>
            <div class="hs-carousel">
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
            <div class="helmet-single__placeholder" style="padding: 2rem; text-align: center;">No image available</div>
        <?php endif; ?>
    </section>

    <!-- About the Helmet (Mobile) -->
    <?php
    $analysis = helmetsan_get_technical_analysis($helmetId);
    $helmetTypeLabel = '';
    $helmetTypeTermsRaw = get_the_terms($helmetId, 'helmet_type');
    if (is_array($helmetTypeTermsRaw) && !empty($helmetTypeTermsRaw)) {
        $helmetTypeLabel = $helmetTypeTermsRaw[0]->name;
    }
    $featuresJson = (string) get_post_meta($helmetId, 'features_json', true);
    $featuresArr = json_decode($featuresJson, true);
    ?>
    <section class="hs-panel">
        <div class="hs-about-card">
            <div class="hs-about-card__icon">🪖</div>
            <div class="hs-about-card__body">
                <h2>About the <?php echo esc_html(get_the_title()); ?></h2>
                <?php if ($helmetTypeLabel !== '') : ?>
                    <span class="hs-about-card__type"><?php echo esc_html($helmetTypeLabel); ?></span>
                <?php endif; ?>
                <?php $descContent = get_the_content(); ?>
                <?php if ($descContent) : ?>
                    <div class="hs-about-card__desc"><?php echo wpautop(wp_kses_post($descContent)); ?></div>
                <?php elseif ($analysis) : ?>
                    <p class="hs-about-card__desc"><?php echo esc_html($analysis); ?></p>
                <?php endif; ?>
                <div class="hs-about-card__attrs">
                    <?php if ($certs !== '' && $certs !== 'N/A') : ?>
                        <span class="hs-about-card__attr"><span class="hs-about-card__attr-icon">✅</span> <?php echo esc_html($certs); ?></span>
                    <?php endif; ?>
                    <?php if ($headShape !== '') : ?>
                        <span class="hs-about-card__attr"><span class="hs-about-card__attr-icon">🧠</span> <?php echo esc_html(ucwords(str_replace('-', ' ', $headShape))); ?></span>
                    <?php endif; ?>
                    <?php if ($helmetFamily !== '') : ?>
                        <span class="hs-about-card__attr"><span class="hs-about-card__attr-icon">🏷️</span> <?php echo esc_html($helmetFamily); ?> Family</span>
                    <?php endif; ?>
                    <?php if ($shell !== '') : ?>
                        <span class="hs-about-card__attr"><span class="hs-about-card__attr-icon">🛡️</span> <?php echo esc_html($shell); ?></span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <?php if (is_array($featuresArr) && $featuresArr !== []) : ?>
        <section class="hs-panel">
            <h2>Feature Highlights</h2>
            <div class="hs-feature-pills">
                <?php foreach ($featuresArr as $feature) : ?>
                    <span class="hs-feature-pill"><?php echo esc_html((string) $feature); ?></span>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <section class="helmet-mobile-pdp__size hs-panel">
        <h2>Size Selection</h2>
        <?php if ($sizeOptions !== []) : ?>
            <div class="hs-pill-grid">
                <?php foreach ($sizeOptions as $sizeLabel) : ?>
                    <label class="hs-pill-input">
                        <input type="radio" name="helmet_size_mobile" value="<?php echo esc_attr($sizeLabel); ?>" />
                        <span><?php echo esc_html($sizeLabel); ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
            <p class="helmet-mobile-pdp__size-help"><a class="hs-link" href="#helmet-sizing-fit">Size Guide</a></p>
        <?php else : ?>
            <p>Size matrix will appear as soon as variant data is added.</p>
        <?php endif; ?>
    </section>

    <section class="helmet-mobile-pdp__snapshot hs-panel">
        <h2>Key Features</h2>
        <ul class="hs-list">
            <li>Weight: <?php echo esc_html($weight !== '' ? $weight . ' g' : 'N/A'); ?><?php echo $weightLbs !== '' ? esc_html(' / ' . $weightLbs . ' lbs') : ''; ?></li>
            <li>Shell: <?php echo esc_html($shell !== '' ? $shell : 'N/A'); ?></li>
            <li>Head Shape: <?php echo esc_html($headShape !== '' ? $headShape : 'N/A'); ?></li>
            <li>Helmet Family: <?php echo esc_html($helmetFamily !== '' ? $helmetFamily : 'N/A'); ?></li>
            <li>Certifications: <?php echo esc_html($certs !== '' ? $certs : 'N/A'); ?></li>
        </ul>
    </section>

    <section class="helmet-mobile-pdp__accordion">
        <details class="hs-panel" open>
            <summary>Description</summary>
            <div><?php the_content(); ?></div>
        </details>

        <details class="hs-panel" id="helmet-sizing-fit">
            <summary>Sizing &amp; Fit</summary>
            <?php if (! empty($sizingFit['fit_notes'])) : ?>
                <p><?php echo esc_html((string) $sizingFit['fit_notes']); ?></p>
            <?php endif; ?>
            <?php if (isset($sizingFit['size_translation']) && is_array($sizingFit['size_translation']) && $sizingFit['size_translation'] !== []) : ?>
                <div class="hs-table-wrap">
                    <table class="hs-table">
                        <thead><tr><th>Size</th><th>CM</th><th>Inches</th></tr></thead>
                        <tbody>
                        <?php foreach ($sizingFit['size_translation'] as $row) : if (! is_array($row)) { continue; } ?>
                            <tr>
                                <td><?php echo esc_html((string) ($row['size'] ?? '')); ?></td>
                                <td><?php echo esc_html((string) ($row['cm'] ?? '')); ?></td>
                                <td><?php echo esc_html((string) ($row['in'] ?? '')); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </details>

        <details class="hs-panel">
            <summary>Product Details</summary>
            <div class="hs-table-wrap">
                <table class="hs-table">
                    <tbody>
                        <tr><th>Product Style</th><td><?php echo esc_html((string) ($productDetails['style'] ?? 'N/A')); ?></td></tr>
                        <tr><th>MFR Product Number</th><td><?php echo esc_html((string) ($productDetails['mfr_product_number'] ?? 'N/A')); ?></td></tr>
                        <tr><th>Sizing &amp; Fit</th><td><?php echo esc_html((string) ($productDetails['sizing_fit'] ?? 'See Sizing & Fit')); ?></td></tr>
                    </tbody>
                </table>
            </div>
        </details>

        <?php if ($relatedVideos !== []) : ?>
            <details class="hs-panel">
                <summary>Related Videos</summary>
                <div class="hs-meta-grid">
                    <?php foreach ($relatedVideos as $video) : if (! is_array($video)) { continue; }
                        $videoUrl = isset($video['url']) ? esc_url((string) $video['url']) : '';
                        if ($videoUrl === '') { continue; }
                        ?>
                        <article class="hs-meta-card">
                            <h3><?php echo esc_html((string) ($video['title'] ?? 'Video')); ?></h3>
                            <p><a class="hs-link" href="<?php echo $videoUrl; ?>" target="_blank" rel="noopener noreferrer">Watch</a></p>
                        </article>
                    <?php endforeach; ?>
                </div>
            </details>
        <?php endif; ?>
    </section>

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
            <strong><?php echo esc_html($price !== '' ? $price : 'N/A'); ?></strong>
            <span><?php echo esc_html($brandName); ?></span>
        </div>
        <a class="hs-btn hs-btn--primary" href="<?php echo esc_url($ctaUrl); ?>" rel="nofollow sponsored">Check Price</a>
    </div>
<?php endif; ?>
