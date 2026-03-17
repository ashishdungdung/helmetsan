<?php
/**
 * Single helmet template.
 *
 * @package HelmetsanTheme
 */

get_header();

// Single helmet: only one post should be in the main query. Guard against any
// plugin/theme altering the query so we never output duplicate sections.
if (have_posts()) {
    while (have_posts()) {
        the_post();

        $helmetId = get_the_ID();
        $brandId = helmetsan_get_brand_id($helmetId);
        $brandName = helmetsan_get_brand_name($helmetId);
        $related = helmetsan_get_related_helmets_by_brand($helmetId, 6);
        $weight = helmetsan_get_weight($helmetId);
        $shell = helmetsan_get_shell_material($helmetId);
        $certs = helmetsan_get_certifications($helmetId);
        $geoPricingJson = (string) get_post_meta($helmetId, 'geo_pricing_json', true);
        $geoLegalityJson = (string) helmetsan_core()->helmets()->getInheritedMeta($helmetId, 'geo_legality_json');
        $certDocsJson = (string) helmetsan_core()->helmets()->getInheritedMeta($helmetId, 'certification_documents_json');
        $variantsJson = (string) get_post_meta($helmetId, 'variants_json', true);
        $productDetailsJson = (string) get_post_meta($helmetId, 'product_details_json', true);
        $partNumbersJson = (string) get_post_meta($helmetId, 'part_numbers_json', true);
        $sizingFitJson = (string) helmetsan_core()->helmets()->getInheritedMeta($helmetId, 'sizing_fit_json');
        $relatedVideosJson = (string) helmetsan_core()->helmets()->getInheritedMeta($helmetId, 'related_videos_json');
        $geoPricing = json_decode($geoPricingJson, true);
        $geoLegality = json_decode($geoLegalityJson, true);
        $certDocs = json_decode($certDocsJson, true);
        $variants = json_decode($variantsJson, true);
        $productDetails = json_decode($productDetailsJson, true);
        $partNumbers = json_decode($partNumbersJson, true);
        $sizingFit = json_decode($sizingFitJson, true);
        $relatedVideos = json_decode($relatedVideosJson, true);
        $weightLbs = (string) get_post_meta($helmetId, 'spec_weight_lbs', true);
        $headShape = helmetsan_get_head_shape($helmetId);
        $helmetFamily = (string) get_post_meta($helmetId, 'helmet_family', true);
        $analysis = helmetsan_get_technical_analysis($helmetId);
        $warrantyYears = helmetsan_get_warranty_years($helmetId);
        $useCase = helmetsan_get_use_case($helmetId);
        $priceRange = helmetsan_get_price_range($helmetId);
        $featuresJson = (string) get_post_meta($helmetId, 'features_json', true);
        $featuresArr = json_decode($featuresJson, true);
        $helmetTypeLabel = '';
        $helmetTypeTermsRaw = get_the_terms($helmetId, 'helmet_type');
        if (is_array($helmetTypeTermsRaw) && !empty($helmetTypeTermsRaw)) {
            $helmetTypeLabel = $helmetTypeTermsRaw[0]->name;
        }
        // Variant-specific fields
        $sku = (string) get_post_meta($helmetId, 'sku', true);
        $finish = (string) get_post_meta($helmetId, 'finish', true);
        $colorFamily = (string) get_post_meta($helmetId, 'color_family', true);
        $parentId = (int) $post->post_parent;
        $isVariant = $parentId > 0;
        $parentPost = $isVariant ? get_post($parentId) : null;
        
        // Multi-currency price
        $priceUsd = helmetsan_get_price($helmetId, 'USD');
        $priceEur = helmetsan_get_price($helmetId, 'EUR');
        $priceGbp = helmetsan_get_price($helmetId, 'GBP');
        
        // New Schema Fields (v1.1) with inheritance
        if (function_exists('helmetsan_core')) {
            $safetyJson = (string) helmetsan_core()->helmets()->getInheritedMeta($helmetId, 'safety_intelligence_json');
            $aeroJson = (string) helmetsan_core()->helmets()->getInheritedMeta($helmetId, 'aero_acoustic_profile_json');
            $techJson = (string) helmetsan_core()->helmets()->getInheritedMeta($helmetId, 'tech_integration_json');
            $fitJson = (string) helmetsan_core()->helmets()->getInheritedMeta($helmetId, 'fitment_coordinates_json');
        } else {
            $safetyJson = (string) get_post_meta($helmetId, 'safety_intelligence_json', true);
            $aeroJson = (string) get_post_meta($helmetId, 'aero_acoustic_profile_json', true);
            $techJson = (string) get_post_meta($helmetId, 'tech_integration_json', true);
            $fitJson = (string) get_post_meta($helmetId, 'fitment_coordinates_json', true);
        }

        $safety = json_decode($safetyJson, true);
        $aero = json_decode($aeroJson, true);
        $tech = json_decode($techJson, true);
        $fitCoords = json_decode($fitJson, true);

        $helmetTypeTerms = get_the_terms($helmetId, 'helmet_type');
        $helmetTypeSlugs = [];
        if (is_array($helmetTypeTerms)) {
            foreach ($helmetTypeTerms as $term) {
                if ($term instanceof WP_Term) {
                    $helmetTypeSlugs[] = $term->slug;
                }
            }
        }
        $accessoryMetaQueries = [];
        if ($brandName !== '') {
            $accessoryMetaQueries[] = [
                'key' => 'compatible_brands_json',
                'value' => '"' . $brandName . '"',
                'compare' => 'LIKE',
            ];
        }
        foreach ($helmetTypeSlugs as $slug) {
            $accessoryMetaQueries[] = [
                'key' => 'compatible_helmet_types_json',
                'value' => '"' . $slug . '"',
                'compare' => 'LIKE',
            ];
        }
        $relatedAccessories = [];
        if ($accessoryMetaQueries !== []) {
            $relatedAccessories = get_posts([
                'post_type' => 'accessory',
                'post_status' => 'publish',
                'posts_per_page' => 8,
                'meta_query' => array_merge(['relation' => 'OR'], $accessoryMetaQueries),
            ]);
        }

        if (wp_is_mobile()) {
            get_template_part(
                'template-parts/helmet',
                'mobile-pdp',
                [
                    'helmet_id' => $helmetId,
                    'brand_id' => $brandId,
                    'brand_name' => $brandName,
                    'weight' => $weight,
                    'weight_lbs' => $weightLbs,
                    'shell' => $shell,
                    'price' => $price,
                    'certs' => $certs,
                    'head_shape' => $headShape,
                    'helmet_family' => $helmetFamily,
                    'product_details' => is_array($productDetails) ? $productDetails : [],
                    'variants' => is_array($variants) ? $variants : [],
                    'sizing_fit' => is_array($sizingFit) ? $sizingFit : [],
                    'related_videos' => is_array($relatedVideos) ? $relatedVideos : [],
                    'related_accessories' => is_array($relatedAccessories) ? $relatedAccessories : [],
                    'related_helmets' => is_array($related) ? $related : [],
                ]
            );
            continue;
        }
        ?>
        <article <?php post_class('helmet-single helmet-single--pdp'); ?>>
            <header class="helmet-single__hero">
                <p class="helmet-single__eyebrow">
                    <?php if ($brandName !== '') : ?>
                        <a href="<?php echo esc_url(get_permalink($brandId)); ?>"><?php echo esc_html($brandName); ?></a>
                    <?php endif; ?>
                    <?php if ($helmetFamily !== '') : ?>
                        <span class="helmet-single__eyebrow-sep">·</span> <?php echo esc_html($helmetFamily); ?>
                    <?php endif; ?>
                    <?php if ($isVariant && $parentPost) : ?>
                        <span class="helmet-single__eyebrow-sep">·</span> <a href="<?php echo esc_url(get_permalink($parentPost)); ?>"><?php echo esc_html($parentPost->post_title); ?></a>
                    <?php endif; ?>
                </p>
                <h1 class="helmet-single__title"><?php the_title(); ?></h1>
                <?php if ($certs !== '' && $certs !== 'N/A') : ?>
                    <p class="helmet-single__certs"><?php echo esc_html($certs); ?></p>
                <?php endif; ?>
            </header>

            <div class="helmet-single__layout">
                <div class="helmet-single__media">
                    <?php 
                    $gallery = helmetsan_core()->mediaService()->getProductGallery($helmetId);
                    if (!empty($gallery)) :
                    ?>
                        <div class="helmet-single__gallery hs-panel">
                            <div class="hs-carousel">
                                <div class="hs-carousel__track">
                                    <?php foreach ($gallery as $item) : ?>
                                        <div class="hs-carousel__slide">
                                            <?php if ($item['type'] === 'video') : ?>
                                                <div class="hs-responsive-embed"><?php echo $item['embed']; ?></div>
                                            <?php else : ?>
                                                <img src="<?php echo esc_url($item['url']); ?>" alt="<?php echo esc_attr($item['alt'] ?? ''); ?>" loading="eager">
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    <?php else : ?>
                        <div class="helmet-single__media-placeholder hs-panel">
                            <div class="helmet-single__placeholder-icon" aria-hidden="true">
                                <svg xmlns="http://www.w3.org/2000/svg" width="80" height="80" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2a9 9 0 0 0-9 9v7a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7a9 9 0 0 0-9-9z"/><path d="M6 12h12"/><path d="M12 12v8"/><path d="M8 12v4"/><path d="M16 12v4"/></svg>
                            </div>
                            <p class="helmet-single__placeholder-text"><?php esc_html_e('Image coming soon', 'helmetsan-theme'); ?></p>
                            <p class="helmet-single__placeholder-hint"><?php esc_html_e('Compare specs and check latest deals below.', 'helmetsan-theme'); ?></p>
                            <a href="<?php echo esc_url(home_url('/comparison/')); ?>" class="hs-btn hs-btn--primary js-add-to-compare" data-id="<?php echo esc_attr((string) $helmetId); ?>"><?php esc_html_e('Add to compare', 'helmetsan-theme'); ?></a>
                        </div>
                    <?php endif; ?>
                    <div class="helmet-single__media-actions">
                        <button type="button" class="js-add-to-compare hs-btn hs-btn--icon helmet-single__compare-btn" data-id="<?php echo esc_attr((string) $helmetId); ?>" title="<?php esc_attr_e('Compare', 'helmetsan-theme'); ?>" aria-label="<?php esc_attr_e('Add to comparison', 'helmetsan-theme'); ?>">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                        </button>
                        <a href="<?php echo esc_url(home_url('/comparison/')); ?>" class="js-view-compare hs-btn hs-btn--sm hs-btn--primary is-hidden helmet-single__view-compare"><?php esc_html_e('View compare', 'helmetsan-theme'); ?></a>
                    </div>
                </div>

                <aside class="helmet-single__aside hs-panel">
                    <h2 class="helmet-single__aside-title"><?php esc_html_e('Key specs', 'helmetsan-theme'); ?></h2>
                    <dl class="helmet-single__specs">
                        <?php if ($weight > 0) : ?><div class="helmet-single__spec-row"><dt><?php esc_html_e('Weight', 'helmetsan-theme'); ?></dt><dd><?php echo esc_html($weight . ' g' . ($weightLbs !== '' ? ' / ' . $weightLbs . ' lbs' : '')); ?></dd></div><?php endif; ?>
                        <?php if ($shell !== '') : ?><div class="helmet-single__spec-row"><dt><?php esc_html_e('Shell', 'helmetsan-theme'); ?></dt><dd><?php echo esc_html($shell); ?></dd></div><?php endif; ?>
                        <?php if ($headShape !== '') : ?><div class="helmet-single__spec-row"><dt><?php esc_html_e('Head shape', 'helmetsan-theme'); ?></dt><dd><?php echo esc_html(ucwords(str_replace('-', ' ', $headShape))); ?></dd></div><?php endif; ?>
                        <?php if ($helmetFamily !== '') : ?><div class="helmet-single__spec-row"><dt><?php esc_html_e('Family', 'helmetsan-theme'); ?></dt><dd><?php echo esc_html($helmetFamily); ?></dd></div><?php endif; ?>
                        <?php if ($certs !== '' && $certs !== 'N/A') : ?><div class="helmet-single__spec-row"><dt><?php esc_html_e('Certification', 'helmetsan-theme'); ?></dt><dd><?php echo esc_html($certs); ?></dd></div><?php endif; ?>
                        <?php if ($warrantyYears !== '') : ?><div class="helmet-single__spec-row"><dt><?php esc_html_e('Warranty', 'helmetsan-theme'); ?></dt><dd><?php echo esc_html($warrantyYears . (is_numeric($warrantyYears) ? ' years' : '')); ?></dd></div><?php endif; ?>
                        <?php if ($useCase !== '') : ?><div class="helmet-single__spec-row"><dt><?php esc_html_e('Use case', 'helmetsan-theme'); ?></dt><dd><?php echo esc_html(ucwords(str_replace('-', ' ', $useCase))); ?></dd></div><?php endif; ?>
                    </dl>
                    <div class="helmet-single__aside-cta">
                        <?php get_template_part('template-parts/helmet', 'cta'); ?>
                    </div>
                    <?php if ($brandId > 0) : ?>
                        <p class="helmet-single__brand-link"><a class="hs-link" href="<?php echo esc_url(get_permalink($brandId)); ?>"><?php esc_html_e('View brand', 'helmetsan-theme'); ?></a></p>
                    <?php endif; ?>
                </aside>
            </div>

            <?php get_template_part('template-parts/legal', 'warning'); ?>

            <?php
            $affiliateLinksForNav = json_decode((string) get_post_meta($helmetId, 'affiliate_links_json', true), true);
            $hasRetailerLinks = is_array($affiliateLinksForNav) && $affiliateLinksForNav !== [];
            ?>
            <?php if ($hasRetailerLinks) : ?>
            <section class="hs-panel helmet-single__retailer-links" aria-label="<?php esc_attr_e('Product at retailers', 'helmetsan-theme'); ?>">
                <h2 class="hs-section-icon-title">
                    <span class="hs-section-icon-title__icon" aria-hidden="true">
                        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
                    </span>
                    <?php esc_html_e('View on retailers', 'helmetsan-theme'); ?>
                </h2>
                <p class="helmet-single__retailer-desc"><?php esc_html_e('This helmet is linked to product pages on the following retailers. Links can be used for affiliate and as a source for data and images.', 'helmetsan-theme'); ?></p>
                <ul class="helmet-single__retailer-list">
                    <?php foreach ($affiliateLinksForNav as $mpId => $entry) :
                        $url = is_array($entry) && isset($entry['url']) ? (string) $entry['url'] : '';
                        if ($url === '') continue;
                        $label = function_exists('helmetsan_marketplace_label') ? helmetsan_marketplace_label($mpId) : $mpId;
                        $goUrl = home_url('/go/' . $post->post_name . '/?marketplace=' . urlencode($mpId) . '&source=pdp_retailers');
                    ?>
                        <li><a href="<?php echo esc_url($goUrl); ?>" class="hs-btn hs-btn--sm helmet-single__retailer-link" target="_blank" rel="noopener noreferrer sponsored"><?php echo esc_html($label); ?> →</a></li>
                    <?php endforeach; ?>
                </ul>
            </section>
            <?php endif; ?>

            <?php
            $hasPartNumbersContent = (is_array($variants) && $variants !== []) || (is_array($partNumbers) && $partNumbers !== []);
            $hasSizingContent = is_array($sizingFit) && $sizingFit !== [];
            $hasSizeChart = $hasSizingContent && isset($sizingFit['size_translation']) && is_array($sizingFit['size_translation']) && $sizingFit['size_translation'] !== [];
            $hasAnySizing = $hasSizingContent || (is_array($fitCoords) && $fitCoords !== []);
            ?>
            <nav class="helmet-single__on-page-nav hs-panel" aria-label="<?php esc_attr_e('Product details', 'helmetsan-theme'); ?>">
                <ul class="helmet-single__tab-list" role="list">
                    <li><a href="#helmet-product-description" class="helmet-single__tab-link"><?php esc_html_e('Product description', 'helmetsan-theme'); ?></a></li>
                    <?php if ($hasPartNumbersContent) : ?><li><a href="#helmet-part-numbers" class="helmet-single__tab-link"><?php esc_html_e('Part numbers', 'helmetsan-theme'); ?></a></li><?php endif; ?>
                    <?php if ($hasAnySizing) : ?><li><a href="#helmet-sizing-fit" class="helmet-single__tab-link"><?php esc_html_e('Sizing &amp; fit', 'helmetsan-theme'); ?></a></li><?php endif; ?>
                </ul>
            </nav>

            <!-- About the Helmet (always shown for content depth & AdSense) -->
            <?php $descContent = helmetsan_get_description($helmetId); ?>
            <section class="hs-panel hs-about-section" id="helmet-product-description">
                <div class="hs-about-card">
                    <div class="hs-about-card__icon" aria-hidden="true">🪖</div>
                    <div class="hs-about-card__body">
                        <h2 id="about">About the <?php echo esc_html(get_the_title()); ?></h2>
                        <?php if ($helmetTypeLabel !== '') : ?>
                            <span class="hs-about-card__type"><?php echo esc_html($helmetTypeLabel); ?></span>
                        <?php endif; ?>
                        <?php if ($descContent) : ?>
                            <div class="hs-about-card__desc"><?php echo wpautop(wp_kses_post($descContent)); ?></div>
                        <?php else : ?>
                            <div class="hs-about-card__desc hs-about-card__desc--fallback">
                                <p><?php echo esc_html(get_the_title()); ?> is a <?php echo $helmetTypeLabel !== '' ? esc_html($helmetTypeLabel) : 'motorcycle'; ?> helmet<?php echo $brandName !== '' ? ' from ' . esc_html($brandName) : ''; ?>.<?php if ($certs !== '' && $certs !== 'N/A') : ?> It meets <?php echo esc_html($certs); ?> certification.<?php endif; ?><?php if ((int) $weight > 0) : ?> Weight: <?php echo esc_html($weight); ?>g.<?php endif; ?><?php if ($shell !== '') : ?> Shell: <?php echo esc_html($shell); ?>.<?php endif; ?> Compare it with similar helmets or check current offers below.</p>
                            </div>
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
                            <?php if ($warrantyYears !== '') : ?>
                                <span class="hs-about-card__attr"><span class="hs-about-card__attr-icon">📋</span> <?php echo esc_html($warrantyYears . (is_numeric($warrantyYears) ? ' year warranty' : '')); ?></span>
                            <?php endif; ?>
                            <?php if ($useCase !== '') : ?>
                                <span class="hs-about-card__attr"><span class="hs-about-card__attr-icon">🎯</span> <?php echo esc_html(ucwords(str_replace('-', ' ', $useCase))); ?></span>
                            <?php endif; ?>
                            <?php if ($priceRange !== '' && $priceRange !== 'n/a') : ?>
                                <span class="hs-about-card__attr"><span class="hs-about-card__attr-icon">💰</span> <?php echo esc_html(ucwords(str_replace('-', ' ', $priceRange))); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Technical Analysis (always shown for content depth & AdSense) -->
            <section class="hs-panel hs-technical-analysis" id="technical-analysis">
                <h2 class="hs-technical-analysis__title">
                    <span class="hs-technical-analysis__icon" aria-hidden="true">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
                    </span>
                    Technical Analysis
                </h2>
                <?php if ($analysis) : ?>
                    <div class="hs-analysis-rich-text"><?php echo wpautop(wp_kses_post($analysis)); ?></div>
                <?php else : ?>
                    <div class="hs-analysis-rich-text hs-analysis-rich-text--fallback">
                        <p>Our technical overview for <?php echo esc_html(get_the_title()); ?> is being prepared. In the meantime, use the specs above—weight, shell material, certifications, and head shape—to compare with other <?php echo $helmetTypeLabel !== '' ? esc_html($helmetTypeLabel) : 'full-face'; ?> helmets. Check current prices and offers in the sidebar, or add this helmet to the comparison tool to see it side by side with others.</p>
                    </div>
                <?php endif; ?>
            </section>

            <!-- Feature Highlights -->
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

            <?php if (is_array($safety) && $safety !== []) : ?>
                <section class="hs-panel">
                    <h2>Safety Intelligence</h2>
                    <div class="hs-meta-grid">
                        <article class="hs-meta-card">
                            <h3>Homologation</h3>
                            <p><strong><?php echo esc_html((string) ($safety['homologation_standard'] ?? 'N/A')); ?></strong></p>
                            <?php if (! empty($safety['rotational_mitigation'])) : ?>
                                <p>Rotational Tech: <?php echo esc_html((string) $safety['rotational_mitigation']); ?></p>
                            <?php endif; ?>
                        </article>
                        <?php if (! empty($safety['sharp_rating'])) : ?>
                            <article class="hs-meta-card">
                                <h3>SHARP Rating</h3>
                                <div class="helmet-rating" aria-label="<?php echo esc_attr($safety['sharp_rating']); ?> stars">
                                    <?php echo str_repeat('★', (int) $safety['sharp_rating']) . str_repeat('☆', 5 - (int) $safety['sharp_rating']); ?>
                                </div>
                            </article>
                        <?php endif; ?>
                        <?php if (isset($safety['sharp_impact_zones']) && is_array($safety['sharp_impact_zones'])) : ?>
                            <article class="hs-meta-card">
                                <h3>Impact Zones</h3>
                                <ul class="hs-list-compact">
                                    <li>Front: <?php echo esc_html((string) ($safety['sharp_impact_zones']['frontal'] ?? '-')); ?></li>
                                    <li>Rear: <?php echo esc_html((string) ($safety['sharp_impact_zones']['rear'] ?? '-')); ?></li>
                                    <li>Left: <?php echo esc_html((string) ($safety['sharp_impact_zones']['left'] ?? '-')); ?></li>
                                    <li>Right: <?php echo esc_html((string) ($safety['sharp_impact_zones']['right'] ?? '-')); ?></li>
                                </ul>
                            </article>
                        <?php endif; ?>
                    </div>
                </section>
            <?php endif; ?>

            <?php if ((is_array($aero) && $aero !== []) || (is_array($tech) && $tech !== [])) : ?>
                <section class="hs-panel">
                    <h2>Features &amp; Comfort</h2>
                    <div class="helmetsan-specs-grid">
                        <?php if (! empty($aero['noise_db_at_100kph'])) : ?>
                            <div class="helmetsan-specs-row"><dt>Noise @ 100kph</dt><dd><?php echo esc_html((string) $aero['noise_db_at_100kph']); ?> dB</dd></div>
                        <?php endif; ?>
                        <?php if (! empty($aero['ventilation_efficiency_score'])) : ?>
                            <div class="helmetsan-specs-row"><dt>Ventilation Score</dt><dd><?php echo esc_html((string) $aero['ventilation_efficiency_score']); ?>/10</dd></div>
                        <?php endif; ?>
                        <?php if (! empty($aero['drag_coefficient'])) : ?>
                            <div class="helmetsan-specs-row"><dt>Drag Coeff (Cd)</dt><dd><?php echo esc_html((string) $aero['drag_coefficient']); ?></dd></div>
                        <?php endif; ?>
                        <?php if (! empty($tech['comms_cutout_type'])) : ?>
                            <div class="helmetsan-specs-row"><dt>Comms Ready</dt><dd><?php echo esc_html((string) $tech['comms_cutout_type']); ?></dd></div>
                        <?php endif; ?>
                        <?php if (! empty($tech['speaker_pocket_depth_mm'])) : ?>
                            <div class="helmetsan-specs-row"><dt>Speaker Depth</dt><dd><?php echo esc_html((string) $tech['speaker_pocket_depth_mm']); ?> mm</dd></div>
                        <?php endif; ?>
                        <?php if (isset($tech['hud_ready']) && $tech['hud_ready']) : ?>
                            <div class="helmetsan-specs-row"><dt>HUD Support</dt><dd>Yes</dd></div>
                        <?php endif; ?>
                    </div>
                </section>
            <?php endif; ?>


            <?php
            $emptyDetailValues = ['', 'N/A', 'n/a', '—', '--', '-'];
            $isRealValue = static function ($v) use ($emptyDetailValues) {
                $v = trim((string) $v);
                return $v !== '' && ! in_array($v, $emptyDetailValues, true);
            };
            $detailRows = [];
            if ($isRealValue($productDetails['style'] ?? '')) {
                $detailRows[] = ['Product Style', esc_html((string) $productDetails['style'])];
            }
            if ($isRealValue($productDetails['mfr_product_number'] ?? '')) {
                $detailRows[] = ['MFR Product Number', esc_html((string) $productDetails['mfr_product_number'])];
            }
            if ($isRealValue($sku)) {
                $detailRows[] = ['SKU', esc_html($sku)];
            }
            if ($isRealValue($colorFamily)) {
                $detailRows[] = ['Color Family', esc_html($colorFamily)];
            }
            if ($isRealValue($finish)) {
                $detailRows[] = ['Finish', esc_html(ucfirst($finish))];
            }
            if ($isRealValue($helmetFamily)) {
                $detailRows[] = ['Helmet Family', esc_html($helmetFamily)];
            }
            if ($isRealValue($productDetails['sizing_fit'] ?? '')) {
                $detailRows[] = ['Sizing & Fit', esc_html((string) $productDetails['sizing_fit'])];
            }
            if ($weight > 0) {
                $detailRows[] = ['Weight', esc_html($weight . 'g' . ($weightLbs !== '' ? ' / ' . $weightLbs . ' lbs' : ''))];
            }
            if ($isRealValue($shell)) {
                $detailRows[] = ['Shell Material', esc_html($shell)];
            }
            if ($isRealValue($headShape)) {
                $detailRows[] = ['Head Shape', esc_html(ucwords(str_replace('-', ' ', $headShape)))];
            }
            if ($isRealValue($certs)) {
                $detailRows[] = ['Certifications', esc_html($certs)];
            }
            ?>
            <?php if (!empty($detailRows)) : ?>
                <section class="hs-panel">
                    <h2>Product Details</h2>
                    <div class="hs-table-wrap">
                        <table class="hs-table">
                            <tbody>
                                <?php foreach ($detailRows as $row) : ?>
                                    <tr><th><?php echo $row[0]; ?></th><td><?php echo $row[1]; ?></td></tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
            <?php endif; ?>

            <!-- ═══ Where to Buy ═══ -->
            <?php
            $plugin = helmetsan_core();
            $priceService = $plugin->price();
            $bestOffer = $priceService->getBestPrice($helmetId);
            $allOffers = $priceService->getAllOffers($helmetId);
            $revenueService = $plugin->revenue();
            $affiliateLinks = $revenueService->getAffiliateLinks($helmetId);
            ?>
            <?php
            // Geo-driven: show when we have price offers and/or stored affiliate links for current region
            $hasWhereToBuy = !empty($allOffers) || $bestOffer !== null
                || (!empty($affiliateLinks) && function_exists('helmetsan_core') && helmetsan_core()->geo());
            $visitorCountry = function_exists('helmetsan_core') && helmetsan_core()->geo() ? strtolower(helmetsan_core()->geo()->getCountry()) : 'us';
            $visitorSuffix = ($visitorCountry === 'uk' || $visitorCountry === 'gb') ? 'uk' : $visitorCountry;
            $geoRelevantLinks = [];
            if (!empty($affiliateLinks)) {
                foreach ($affiliateLinks as $mpId => $entry) {
                    if (str_starts_with($mpId, 'amazon-') && $mpId === 'amazon-' . $visitorSuffix) {
                        $geoRelevantLinks[$mpId] = $entry;
                    } elseif (str_ends_with($mpId, '-' . $visitorSuffix)) {
                        $geoRelevantLinks[$mpId] = $entry;
                    }
                }
            }
            // For India: show Flipkart row when enabled even if no stored link (redirect will use search URL)
            if ($visitorSuffix === 'in' && $revenueService && $revenueService->hasFlipkartEnabled() && !isset($geoRelevantLinks['flipkart-in'])) {
                $geoRelevantLinks['flipkart-in'] = ['url' => '', 'network' => 'flipkart'];
            }
            $hasWhereToBuy = $hasWhereToBuy || !empty($geoRelevantLinks);
            ?>
            <?php if ($hasWhereToBuy) : ?>
                <section class="hs-panel hs-where-to-buy helmet-single__where" id="where-to-buy">
                    <h2 class="hs-section-icon-title">
                        <span class="hs-section-icon-title__icon" aria-hidden="true">
                            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
                        </span>
                        <?php esc_html_e('Where to buy', 'helmetsan-theme'); ?>
                    </h2>
                    <?php if ($bestOffer !== null && $bestOffer->price > 0) : ?>
                        <?php
                        $bestGoUrl = home_url('/go/' . $post->post_name . '/?marketplace=' . urlencode($bestOffer->marketplaceId) . '&source=pdp');
                        ?>
                        <div class="helmet-single__best-offer">
                            <span class="helmet-single__best-offer-label"><?php esc_html_e('Best price', 'helmetsan-theme'); ?></span>
                            <a href="<?php echo esc_url($bestGoUrl); ?>" class="helmet-single__best-offer-cta hs-price-cta" target="_blank" rel="noopener noreferrer sponsored">
                                <span class="helmet-single__best-offer-price"><?php echo esc_html($priceService->formatPrice($bestOffer->price, $bestOffer->currency)); ?></span>
                                <span class="helmet-single__best-offer-source"><?php echo esc_html(function_exists('helmetsan_marketplace_label') ? helmetsan_marketplace_label($bestOffer->marketplaceId) : $bestOffer->marketplaceId); ?></span>
                            </a>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($allOffers)) : ?>
                        <?php if ($bestOffer !== null && $bestOffer->price > 0 && count($allOffers) > 1) : ?>
                            <h3 class="helmet-single__where-all-title"><?php esc_html_e('All retailers', 'helmetsan-theme'); ?></h3>
                        <?php endif; ?>
                        <div class="hs-table-wrap">
                            <table class="hs-table hs-price-table">
                                <thead>
                                    <tr>
                                        <th>Marketplace</th>
                                        <th>Price</th>
                                        <th>Availability</th>
                                        <th>Updated</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($allOffers as $offer) :
                                    $isBest = $bestOffer !== null && $offer->marketplaceId === $bestOffer->marketplaceId && $offer->price === $bestOffer->price;
                                    $mpId = $offer->marketplaceId;
                                    $goUrl = home_url('/go/' . $post->post_name . '/?marketplace=' . urlencode($mpId) . '&source=pdp');
                                ?>
                                    <tr class="<?php echo $isBest ? 'hs-price-table__row--best' : ''; ?>">
                                        <td class="hs-price-table__merchant">
                                            <?php if ($isBest) : ?><span class="hs-price-table__best-tag" title="Best Price Today">★</span><?php endif; ?>
                                            <?php 
                                            $mpLower = strtolower($mpId);
                                            if (str_contains($mpLower, 'amazon')) {
                                                echo '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#FF9900" stroke-width="2"><path d="M4 17c2.5 2.5 6.5 3.5 10.5 1.5M16.5 17l1.5 1.5.5-2"></path></svg>';
                                            } elseif (str_contains($mpLower, 'flipkart')) {
                                                echo '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#047BD5" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"></rect><circle cx="8.5" cy="8.5" r="1.5"></circle><polyline points="21 15 16 10 5 21"></polyline></svg>';
                                            } else {
                                                echo '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="21" r="1"></circle><circle cx="20" cy="21" r="1"></circle><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path></svg>';
                                            }
                                            ?>
                                            <span class="hs-price-table__merchant-name"><?php echo esc_html(helmetsan_marketplace_label($mpId)); ?></span>
                                        </td>
                                        <td><strong><?php echo $offer->price > 0 ? esc_html($priceService->formatPrice($offer->price, $offer->currency)) : '<span class="hs-muted">Check price</span>'; ?></strong></td>
                                        <td>
                                            <?php if ($offer->availability === 'in_stock') : ?>
                                                <span style="color: var(--hs-success, #059669);">● In Stock</span>
                                            <?php else : ?>
                                                <span style="color: var(--hs-muted);"><?php echo esc_html(ucfirst(str_replace('_', ' ', $offer->availability))); ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td><small><?php echo esc_html($offer->capturedAt !== '' ? human_time_diff(strtotime($offer->capturedAt), time()) . ' ago' : '—'); ?></small></td>
                                        <td>
                                            <a href="<?php echo esc_url($goUrl); ?>" class="hs-price-cta" target="_blank" rel="noopener noreferrer sponsored">
                                                Buy Now →
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>

                    <?php
                    // Fallback: no price-engine offers but we have stored links for visitor's region (e.g. no API yet)
                    if (empty($allOffers) && !empty($geoRelevantLinks)) :
                        ?>
                        <div class="hs-table-wrap">
                            <table class="hs-table hs-price-table">
                                <thead>
                                    <tr>
                                        <th>Marketplace</th>
                                        <th>Price</th>
                                        <th>Availability</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($geoRelevantLinks as $mpId => $entry) :
                                    $goUrl = home_url('/go/' . $post->post_name . '/?marketplace=' . urlencode($mpId) . '&source=pdp');
                                    $mpLower = strtolower($mpId);
                                    ?>
                                    <tr>
                                        <td class="hs-price-table__merchant">
                                            <?php if (str_contains($mpLower, 'amazon')) : ?>
                                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#FF9900" stroke-width="2"><path d="M4 17c2.5 2.5 6.5 3.5 10.5 1.5M16.5 17l1.5 1.5.5-2"></path></svg>
                                            <?php elseif (str_contains($mpLower, 'flipkart')) : ?>
                                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#047BD5" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"></rect><circle cx="8.5" cy="8.5" r="1.5"></circle><polyline points="21 15 16 10 5 21"></polyline></svg>
                                            <?php else : ?>
                                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="21" r="1"></circle><circle cx="20" cy="21" r="1"></circle><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path></svg>
                                            <?php endif; ?>
                                            <span class="hs-price-table__merchant-name"><?php echo esc_html(helmetsan_marketplace_label($mpId)); ?></span>
                                        </td>
                                        <td><strong><span class="hs-muted">Check price</span></strong></td>
                                        <td><span style="color: var(--hs-success, #059669);">● View on site</span></td>
                                        <td>
                                            <a href="<?php echo esc_url($goUrl); ?>" class="hs-price-cta" target="_blank" rel="noopener noreferrer sponsored">Buy Now →</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>

                    <!-- Price History Chart (section always visible; chart or "No history" message) -->
                    <div class="hs-price-chart-wrap" id="hs-price-chart-wrap">
                        <h3>Price History</h3>
                        <p id="hs-price-chart-empty" class="hs-muted" style="display:none;">No price history recorded yet.</p>
                        <div class="hs-price-date-toggles" id="hs-date-toggles">
                            <button class="hs-btn hs-btn--sm is-active" data-days="30">30 Days</button>
                            <button class="hs-btn hs-btn--sm" data-days="90">90 Days</button>
                            <button class="hs-btn hs-btn--sm" data-days="365">1 Year</button>
                        </div>
                        <canvas id="hs-price-chart" data-helmet-id="<?php echo esc_attr((string) $helmetId); ?>" height="300"></canvas>
                    </div>
                </section>
            <?php endif; ?>

            <?php
            $hasVariantsTable = is_array($variants) && $variants !== [];
            $hasPartNumbersTable = is_array($partNumbers) && $partNumbers !== [];
            ?>
            <?php if ($hasVariantsTable || $hasPartNumbersTable) : ?>
                <section class="hs-panel" id="helmet-part-numbers">
                    <h2 class="hs-section-icon-title">
                        <span class="hs-section-icon-title__icon" aria-hidden="true">
                            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
                        </span>
                        <?php esc_html_e('Part numbers', 'helmetsan-theme'); ?>
                    </h2>
                    <?php if ($hasVariantsTable) : ?>
                        <div class="hs-table-wrap">
                            <table class="hs-table hs-table--part-numbers">
                                <thead>
                                    <tr>
                                        <th><?php esc_html_e('Product style', 'helmetsan-theme'); ?></th>
                                        <th><?php esc_html_e('MFR. product #', 'helmetsan-theme'); ?></th>
                                        <?php if (array_filter(array_column($variants, 'sku')) !== []) : ?><th><?php esc_html_e('SKU', 'helmetsan-theme'); ?></th><?php endif; ?>
                                        <th><?php esc_html_e('Availability', 'helmetsan-theme'); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($variants as $v) : if (! is_array($v)) continue;
                                    $style = trim((string) ($v['style'] ?? '') . ' ' . (string) ($v['color'] ?? '') . ' / ' . (string) ($v['size'] ?? ''));
                                    if ($style === ' / ') $style = (string) ($v['color'] ?? '') . ' / ' . (string) ($v['size'] ?? '—');
                                    $mfr = (string) ($v['mfr_part_number'] ?? '');
                                    $sku = (string) ($v['sku'] ?? '');
                                    $avail = (string) ($v['availability'] ?? '');
                                ?>
                                    <tr>
                                        <td><?php echo esc_html($style !== '' ? $style : '—'); ?></td>
                                        <td><code><?php echo esc_html($mfr !== '' ? $mfr : '—'); ?></code></td>
                                        <?php if (array_filter(array_column($variants, 'sku')) !== []) : ?><td><?php echo esc_html($sku !== '' ? $sku : '—'); ?></td><?php endif; ?>
                                        <td><?php echo esc_html($avail !== '' ? $avail : '—'); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                    <?php if ($hasPartNumbersTable) : ?>
                        <?php if ($hasVariantsTable) : ?><h3 class="helmet-single__part-numbers-extra"><?php esc_html_e('Other part numbers', 'helmetsan-theme'); ?></h3><?php endif; ?>
                        <div class="hs-table-wrap">
                            <table class="hs-table">
                                <thead>
                                    <tr><th><?php esc_html_e('Type', 'helmetsan-theme'); ?></th><th><?php esc_html_e('Value', 'helmetsan-theme'); ?></th></tr>
                                </thead>
                                <tbody>
                                <?php foreach ($partNumbers as $row) : if (! is_array($row)) continue; ?>
                                    <tr>
                                        <td><?php echo esc_html((string) ($row['label'] ?? 'Part Number')); ?></td>
                                        <td><code><?php echo esc_html((string) ($row['value'] ?? 'N/A')); ?></code></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </section>
            <?php endif; ?>

            <?php if ($hasAnySizing) : ?>
            <section class="hs-panel" id="helmet-sizing-fit">
                <h2 class="hs-section-icon-title">
                    <span class="hs-section-icon-title__icon" aria-hidden="true">
                        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
                    </span>
                    <?php esc_html_e('Sizing &amp; fit', 'helmetsan-theme'); ?>
                </h2>
                <?php if ($hasSizingContent) : ?>
                    <?php if (! empty($sizingFit['fit_notes'])) : ?>
                        <p><?php echo esc_html((string) $sizingFit['fit_notes']); ?></p>
                    <?php endif; ?>
                    <?php if (! empty($sizingFit['head_shape'])) : ?>
                        <p><strong><?php esc_html_e('Head shape:', 'helmetsan-theme'); ?></strong> <?php echo esc_html((string) $sizingFit['head_shape']); ?></p>
                    <?php endif; ?>
                    <?php if ($hasSizeChart) : ?>
                        <h3 class="helmet-single__size-chart-title"><?php echo esc_html($brandName !== '' ? $brandName . ' ' : ''); ?><?php esc_html_e('helmet sizing', 'helmetsan-theme'); ?></h3>
                        <div class="hs-table-wrap">
                            <table class="hs-table">
                                <thead>
                                    <tr><th><?php esc_html_e('Size', 'helmetsan-theme'); ?></th><th><?php esc_html_e('Head (cm)', 'helmetsan-theme'); ?></th><th><?php esc_html_e('Head (in)', 'helmetsan-theme'); ?></th></tr>
                                </thead>
                                <tbody>
                                <?php foreach ($sizingFit['size_translation'] as $row) : if (! is_array($row)) continue; ?>
                                    <tr>
                                        <td><?php echo esc_html((string) ($row['size'] ?? '')); ?></td>
                                        <td><?php echo esc_html((string) ($row['cm'] ?? '')); ?></td>
                                        <td><?php echo esc_html((string) ($row['in'] ?? '')); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <p class="helmet-single__sizing-disclaimer"><?php esc_html_e('Sizing information is provided by the manufacturer and does not guarantee a perfect fit.', 'helmetsan-theme'); ?></p>
                        <div class="helmet-single__how-to-measure hs-how-to-measure">
                            <h3 class="hs-how-to-measure__title">
                                <span class="hs-how-to-measure__icon" aria-hidden="true">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M12 3v3M12 18v3M3 12h3M18 12h3"/><path d="M12 7v10M7 12h10"/></svg>
                                </span>
                                <?php esc_html_e('How to measure', 'helmetsan-theme'); ?>
                            </h3>
                            <p><?php esc_html_e('Wrap a cloth measuring tape around your head just above your eyebrows and ears. Pull the tape comfortably snug, read the length, repeat for consistency and use the largest measurement. Compare to the size chart above.', 'helmetsan-theme'); ?></p>
                        </div>
                    <?php endif; ?>

                    <?php if (is_array($fitCoords) && $fitCoords !== []) : ?>
                        <h3><?php esc_html_e('Internal dimensions', 'helmetsan-theme'); ?></h3>
                        <dl class="helmetsan-specs-grid">
                            <?php if (! empty($fitCoords['internal_shape_3d'])) : ?>
                                <div class="helmetsan-specs-row"><dt>3D Shape</dt><dd><?php echo esc_html(ucwords(str_replace('_', ' ', (string) $fitCoords['internal_shape_3d']))); ?></dd></div>
                            <?php endif; ?>
                            <?php if (! empty($fitCoords['internal_length_mm'])) : ?>
                                <div class="helmetsan-specs-row"><dt>Length</dt><dd><?php echo esc_html((string) $fitCoords['internal_length_mm']); ?> mm</dd></div>
                            <?php endif; ?>
                            <?php if (! empty($fitCoords['internal_width_mm'])) : ?>
                                <div class="helmetsan-specs-row"><dt>Width</dt><dd><?php echo esc_html((string) $fitCoords['internal_width_mm']); ?> mm</dd></div>
                            <?php endif; ?>
                        </dl>
                    <?php endif; ?>
                <?php endif; ?>
            </section>
            <?php endif; ?>

            <?php 
            $children = get_posts([
                'post_parent'    => $isVariant ? $parentId : $helmetId,
                'post_type'      => 'helmet',
                'posts_per_page' => -1,
                'orderby'        => 'title',
                'order'          => 'ASC',
            ]);
            if (! empty($children)) : 
            ?>
                <section class="hs-panel">
                    <h2>Available Colors & Graphics</h2>
                    <div class="hs-variant-grid">
                        <?php foreach ($children as $child) : 
                            $isActive = $child->ID === $helmetId;
                        ?>
                            <a href="<?php echo esc_url(get_permalink($child)); ?>" class="hs-variant-item <?php echo $isActive ? 'is-active' : ''; ?>">
                                <div class="hs-variant-item__image">
                                    <?php 
                                    $thumb = get_the_post_thumbnail($child->ID, 'thumbnail');
                                    if ($thumb) : 
                                        echo $thumb;
                                    else : 
                                        $childGeoMedia = json_decode((string) get_post_meta($child->ID, 'geo_media_json', true), true);
                                        $fallbackUrl = is_array($childGeoMedia) && !empty($childGeoMedia) ? $childGeoMedia[0] : '';
                                        if ($fallbackUrl) : ?>
                                            <img src="<?php echo esc_url($fallbackUrl); ?>" alt="<?php echo esc_attr($child->post_title); ?>" loading="lazy">
                                        <?php else : ?>
                                            <span style="color: var(--hs-muted); font-size: 10px;">No Image</span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                                <div class="hs-variant-item__label"><?php echo esc_html($child->post_title); ?></div>
                                <div class="hs-variant-item__price"><?php echo esc_html(helmetsan_get_helmet_price($child->ID)); ?></div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>

            <?php if (is_array($relatedVideos) && $relatedVideos !== []) : ?>
                <section class="hs-panel">
                    <h2 class="hs-section-icon-title">
                        <span class="hs-section-icon-title__icon" aria-hidden="true">
                            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="23 7 16 12 23 17 23 7"></polygon><rect x="1" y="5" width="15" height="14" rx="2" ry="2"></rect></svg>
                        </span>
                        <?php esc_html_e('Related videos', 'helmetsan-theme'); ?>
                    </h2>
                    <div class="hs-meta-grid">
                        <?php foreach ($relatedVideos as $video) : if (! is_array($video)) { continue; }
                            $videoUrl = isset($video['url']) ? esc_url((string) $video['url']) : '';
                            if ($videoUrl === '') { continue; }
                            $embed = wp_oembed_get($videoUrl);
                            ?>
                            <article class="hs-meta-card">
                                <h3><?php echo esc_html((string) ($video['title'] ?? 'Video')); ?></h3>
                                <?php if (is_string($embed) && $embed !== '') : ?>
                                    <div class="helmet-video-embed"><?php echo $embed; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
                                <?php else : ?>
                                    <p><a class="hs-link" href="<?php echo $videoUrl; ?>" target="_blank" rel="noopener noreferrer">Watch video</a></p>
                                <?php endif; ?>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>

            <?php if (is_array($geoPricing) && $geoPricing !== []) : ?>
                <section class="hs-panel">
                    <h2>Geo Pricing & Availability</h2>
                    <div class="hs-table-wrap">
                        <table class="hs-table">
                            <thead>
                                <tr><th>Country</th><th>Price</th><th>Availability</th><th>Source</th><th>Updated</th></tr>
                            </thead>
                            <tbody>
                            <?php foreach ($geoPricing as $country => $row) : if (! is_array($row)) { continue; } ?>
                                <tr>
                                    <td><?php echo esc_html((string) $country); ?></td>
                                    <td><?php echo esc_html((string) ($row['price'] ?? 'N/A') . ' ' . (string) ($row['currency'] ?? '')); ?></td>
                                    <td><?php echo esc_html((string) ($row['availability'] ?? 'N/A')); ?></td>
                                    <td><?php echo esc_html((string) ($row['source'] ?? 'N/A')); ?></td>
                                    <td><?php echo esc_html((string) ($row['updated_at'] ?? 'N/A')); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
            <?php endif; ?>

            <?php if (is_array($geoLegality) && $geoLegality !== []) : ?>
                <section class="hs-panel">
                    <h2>Regional Legality Guidance</h2>
                    <div class="hs-meta-grid">
                        <?php foreach ($geoLegality as $country => $row) : if (! is_array($row)) { continue; } ?>
                            <article class="hs-meta-card">
                                <h3><?php echo esc_html((string) $country); ?></h3>
                                <p><strong>Status:</strong> <?php echo esc_html((string) ($row['status'] ?? 'N/A')); ?></p>
                                <?php if (isset($row['certification_required']) && is_array($row['certification_required'])) : ?>
                                    <p><strong>Required:</strong> <?php echo esc_html(implode(', ', array_map('strval', $row['certification_required']))); ?></p>
                                <?php endif; ?>
                                <?php if (! empty($row['notes'])) : ?>
                                    <p><?php echo esc_html((string) $row['notes']); ?></p>
                                <?php endif; ?>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>

            <?php if (is_array($certDocs) && $certDocs !== []) : ?>
                <section class="hs-panel">
                    <h2>Certification Documents & References</h2>
                    <ul class="hs-list">
                        <?php foreach ($certDocs as $doc) : if (! is_array($doc)) { continue; } ?>
                            <li>
                                <strong><?php echo esc_html((string) ($doc['code'] ?? 'Standard')); ?></strong>
                                <?php if (! empty($doc['country'])) : ?> (<?php echo esc_html((string) $doc['country']); ?>)<?php endif; ?>
                                <?php if (! empty($doc['issuer'])) : ?> - <?php echo esc_html((string) $doc['issuer']); ?><?php endif; ?>
                                <?php if (! empty($doc['url'])) : ?>
                                    - <a class="hs-link" href="<?php echo esc_url((string) $doc['url']); ?>" target="_blank" rel="noopener noreferrer">Reference</a>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </section>
            <?php endif; ?>

            <!-- Compare & buy CTA (action-oriented, always visible) -->
            <section class="hs-panel hs-cta-section" aria-labelledby="cta-heading">
                <h2 id="cta-heading" class="hs-cta-section__title">Compare &amp; buy</h2>
                <p class="hs-cta-section__lead">Add this helmet to the comparison tool to see it side by side with others, or check current offers from trusted retailers.</p>
                <div class="hs-cta-section__actions">
                    <a href="<?php echo esc_url(home_url('/comparison/')); ?>" class="hs-btn hs-btn--primary js-add-to-compare" data-id="<?php echo esc_attr((string) $helmetId); ?>">Add to compare</a>
                    <?php get_template_part('template-parts/helmet', 'cta'); ?>
                </div>
            </section>

            <?php if (is_array($relatedAccessories) && $relatedAccessories !== []) : ?>
                <section class="hs-panel">
                    <h2>Compatible Accessories</h2>
                    <div class="helmet-grid">
                        <?php foreach ($relatedAccessories as $post) : setup_postdata($post); get_template_part('template-parts/entity', 'card'); endforeach; wp_reset_postdata(); ?>
                    </div>
                </section>
            <?php endif; ?>

            <?php if ($related !== []) : ?>
                <section class="hs-panel">
                    <h2>More from <?php echo esc_html($brandName); ?></h2>
                    <div class="helmet-grid">
                        <?php foreach ($related as $post) : setup_postdata($post); get_template_part('template-parts/helmet', 'card'); endforeach; wp_reset_postdata(); ?>
                    </div>
                </section>
            <?php endif; ?>
        </article>
        <?php
        // Single helmet page must show only one product block.
        break;
    }
}

get_footer();
