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
        $descContent = helmetsan_get_description($helmetId);
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
        $price = helmetsan_get_helmet_price($helmetId);
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
            <?php
            get_template_part('template-parts/helmet/hero-header', null, [
                'brandName'       => $brandName,
                'brandId'         => $brandId,
                'helmetFamily'    => $helmetFamily,
                'isVariant'       => $isVariant,
                'parentPost'      => $parentPost,
                'helmetTypeLabel' => $helmetTypeLabel,
                'certs'           => $certs,
                'shell'           => $shell,
                'weight'          => $weight,
            ]);
            ?>

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
                            <p class="helmet-single__placeholder-text"><?php esc_html_e('IMAGE UNAVAILABLE', 'helmetsan-theme'); ?></p>
                            <p class="helmet-single__placeholder-hint"><?php esc_html_e('Helmetsan does not currently have a verified product image for this model.', 'helmetsan-theme'); ?></p>
                            <a href="<?php echo esc_url(helmetsan_url('/comparison/')); ?>" class="hs-btn hs-btn--primary js-add-to-compare" data-id="<?php echo esc_attr((string) $helmetId); ?>"><?php esc_html_e('Add to compare', 'helmetsan-theme'); ?></a>
                        </div>
                    <?php endif; ?>
                    <div class="helmet-single__media-actions">
                        <button type="button" class="js-add-to-compare hs-btn hs-btn--icon helmet-single__compare-btn" data-id="<?php echo esc_attr((string) $helmetId); ?>" title="<?php esc_attr_e('Compare', 'helmetsan-theme'); ?>" aria-label="<?php esc_attr_e('Add to comparison', 'helmetsan-theme'); ?>">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                        </button>
                        <a href="<?php echo esc_url(helmetsan_url('/comparison/')); ?>" class="js-view-compare hs-btn hs-btn--sm hs-btn--primary is-hidden helmet-single__view-compare"><?php esc_html_e('View compare', 'helmetsan-theme'); ?></a>
                    </div>
                </div>

                <?php
                get_template_part('template-parts/helmet/quick-verdict', null, [
                    'helmetId'        => $helmetId,
                    'useCase'         => $useCase,
                    'helmetTypeLabel' => $helmetTypeLabel,
                    'weight'          => $weight,
                    'shell'           => $shell,
                    'headShape'       => $headShape,
                    'certs'           => $certs,
                ]);
                ?>
            </div>

            <!-- Quick Facts Section -->
            <section class="hs-panel hs-pdp-panel hs-reveal" id="quick-facts">
                <h2 class="hs-section-icon-title">
                    <span class="hs-section-icon-title__icon" aria-hidden="true">📊</span>
                    <?php esc_html_e('Quick Facts at a Glance', 'helmetsan-theme'); ?>
                </h2>
                <div class="hs-quick-facts-grid">
                    <div class="hs-quick-fact-item">
                        <span class="hs-quick-fact-item__label"><?php esc_html_e('Category', 'helmetsan-theme'); ?></span>
                        <span class="hs-quick-fact-item__value"><?php echo esc_html($helmetTypeLabel !== '' ? __($helmetTypeLabel, 'helmetsan-theme') : esc_html__('Not specified', 'helmetsan-theme')); ?></span>
                    </div>
                    <div class="hs-quick-fact-item">
                        <span class="hs-quick-fact-item__label"><?php esc_html_e('Certifications', 'helmetsan-theme'); ?></span>
                        <span class="hs-quick-fact-item__value"><?php echo esc_html($certs !== '' && $certs !== 'N/A' ? $certs : esc_html__('Not verified', 'helmetsan-theme')); ?></span>
                    </div>
                    <div class="hs-quick-fact-item">
                        <span class="hs-quick-fact-item__label"><?php esc_html_e('Measured Weight', 'helmetsan-theme'); ?></span>
                        <span class="hs-quick-fact-item__value"><?php echo esc_html($weight > 0 ? $weight . ' g' : esc_html__('Not verified', 'helmetsan-theme')); ?></span>
                    </div>
                    <div class="hs-quick-fact-item">
                        <span class="hs-quick-fact-item__label"><?php esc_html_e('Shell Material', 'helmetsan-theme'); ?></span>
                        <span class="hs-quick-fact-item__value"><?php echo esc_html($shell !== '' ? __($shell, 'helmetsan-theme') : esc_html__('Not verified', 'helmetsan-theme')); ?></span>
                    </div>
                    <div class="hs-quick-fact-item">
                        <span class="hs-quick-fact-item__label"><?php esc_html_e('Head Shape Fit', 'helmetsan-theme'); ?></span>
                        <span class="hs-quick-fact-item__value"><?php echo esc_html($headShape !== '' ? __(ucwords(str_replace('-', ' ', $headShape)), 'helmetsan-theme') : esc_html__('Not verified', 'helmetsan-theme')); ?></span>
                    </div>
                    <div class="hs-quick-fact-item">
                        <span class="hs-quick-fact-item__label"><?php esc_html_e('Retention System', 'helmetsan-theme'); ?></span>
                        <span class="hs-quick-fact-item__value"><?php echo esc_html(!empty($profile['strap_type']) && $profile['strap_type'] !== 'N/A' ? __($profile['strap_type'], 'helmetsan-theme') : esc_html__('Not verified', 'helmetsan-theme')); ?></span>
                    </div>
                </div>
            </section>

            <!-- ═══ Standalone Safety Snapshot ═══ -->
            <section class="hs-panel hs-pdp-panel hs-reveal" id="safety-snapshot">
                <div class="hs-pdp-section-header">
                    <span class="hs-pdp-section-header__eyebrow"><?php esc_html_e('SAFETY & CERTIFICATIONS', 'helmetsan-theme'); ?></span>
                    <h2 class="hs-pdp-section-header__title">
                        <span aria-hidden="true">🛡️</span>
                        <?php esc_html_e('Verified Safety Snapshot', 'helmetsan-theme'); ?>
                    </h2>
                    <p class="hs-pdp-section-header__desc"><?php esc_html_e('How this helmet’s verified certifications map to recognized safety homologations and crash testing.', 'helmetsan-theme'); ?></p>
                </div>

                <div class="hs-safety-grid">
                    <div class="hs-safety-item">
                        <span class="hs-safety-item__label"><?php esc_html_e('Certified Homologation', 'helmetsan-theme'); ?></span>
                        <span class="hs-safety-item__value">
                            <?php 
                            $homologationVal = !empty($profile['homologation']) && $profile['homologation'] !== 'N/A' ? $profile['homologation'] : ($certs !== '' && $certs !== 'N/A' ? $certs : '');
                            if ($homologationVal !== '') : ?>
                                <span class="hs-badge hs-badge--accent">✓ <?php echo esc_html($homologationVal); ?></span>
                            <?php else : ?>
                                <span class="hs-muted"><?php esc_html_e('Not verified', 'helmetsan-theme'); ?></span>
                            <?php endif; ?>
                        </span>
                    </div>

                    <div class="hs-safety-item">
                        <span class="hs-safety-item__label"><?php esc_html_e('Rotational Protection', 'helmetsan-theme'); ?></span>
                        <span class="hs-safety-item__value">
                            <?php echo !empty($profile['rotational_tech']) && $profile['rotational_tech'] !== 'N/A' ? esc_html($profile['rotational_tech']) : esc_html__('Not specified', 'helmetsan-theme'); ?>
                        </span>
                    </div>

                    <div class="hs-safety-item">
                        <span class="hs-safety-item__label"><?php esc_html_e('SHARP Impact Score', 'helmetsan-theme'); ?></span>
                        <span class="hs-safety-item__value">
                            <?php if ($sharpStars > 0) : ?>
                                <strong><?php echo esc_html($sharpStars); ?>/5 Stars</strong> (SHARP UK)
                            <?php else : ?>
                                <span class="hs-muted"><?php esc_html_e('Not tested by SHARP', 'helmetsan-theme'); ?></span>
                            <?php endif; ?>
                        </span>
                    </div>

                    <div class="hs-safety-item">
                        <span class="hs-safety-item__label"><?php esc_html_e('Retention Mechanism', 'helmetsan-theme'); ?></span>
                        <span class="hs-safety-item__value">
                            <?php echo !empty($profile['strap_type']) && $profile['strap_type'] !== 'N/A' ? esc_html($profile['strap_type']) : esc_html__('Not verified', 'helmetsan-theme'); ?>
                        </span>
                    </div>

                    <div class="hs-safety-item">
                        <span class="hs-safety-item__label"><?php esc_html_e('Emergency Release System', 'helmetsan-theme'); ?></span>
                        <span class="hs-safety-item__value">
                            <?php echo !empty($profile['emergency_release_system']) && $profile['emergency_release_system'] === '1' ? esc_html__('EQRS Active', 'helmetsan-theme') : esc_html__('Not specified', 'helmetsan-theme'); ?>
                        </span>
                    </div>
                </div>

                <div class="hs-safety-disclaimer">
                    <p><small><?php esc_html_e('Safety standards compliance is based on official manufacturer documentation and public safety laboratory test results. Always verify regional compliance labels on the physical helmet prior to use.', 'helmetsan-theme'); ?></small></p>
                </div>
            </section>

            <!-- ═══ Standalone Fit & Sizing ═══ -->
            <section class="hs-panel hs-pdp-panel hs-reveal" id="fit-sizing">
                <div class="hs-pdp-section-header">
                    <span class="hs-pdp-section-header__eyebrow"><?php esc_html_e('FIT & SIZING', 'helmetsan-theme'); ?></span>
                    <h2 class="hs-pdp-section-header__title">
                        <span aria-hidden="true">📐</span>
                        <?php esc_html_e('Verified Fit & Sizing Guide', 'helmetsan-theme'); ?>
                    </h2>
                    <p class="hs-pdp-section-header__desc"><?php esc_html_e('Head shape profile matching and interactive size calculator based on verified manufacturer dimensions.', 'helmetsan-theme'); ?></p>
                </div>

                <div class="hs-size-finder" id="hsSizeFinder" data-default-shape="<?php echo esc_attr($headShape ?: 'intermediate-oval'); ?>" data-sizing-chart="<?php echo esc_attr(json_encode($sizingFit['size_translation'] ?? [])); ?>">
                    <div class="hs-size-finder__slider-box">
                        <div class="hs-size-finder__label-row">
                            <span><?php esc_html_e('Your Head Circumference', 'helmetsan-theme'); ?></span>
                            <span class="hs-size-finder__current-val" id="hsSizeFinderCircumference">57 cm</span>
                        </div>
                        
                        <div class="hs-size-finder__slider-wrap">
                            <input type="range" class="hs-size-finder__range" id="hsSizeFinderRange" min="52" max="65" step="0.5" value="57">
                            <div class="hs-size-finder__ticks">
                                <span>52cm</span>
                                <span>55cm</span>
                                <span>58cm</span>
                                <span>61cm</span>
                                <span>65cm</span>
                            </div>
                        </div>

                        <div class="hs-size-finder__shapes-wrap">
                            <span class="hs-size-finder__shapes-label"><?php esc_html_e('Select Head Shape Profile', 'helmetsan-theme'); ?></span>
                            <div class="hs-size-finder__shapes">
                                <button type="button" class="hs-size-finder__shape-btn <?php echo ($headShape === 'round-oval') ? 'is-active' : ''; ?>" data-shape="round-oval"><?php esc_html_e('Round Oval', 'helmetsan-theme'); ?></button>
                                <button type="button" class="hs-size-finder__shape-btn <?php echo ($headShape !== 'round-oval' && $headShape !== 'long-oval') ? 'is-active' : ''; ?>" data-shape="intermediate-oval"><?php esc_html_e('Intermediate Oval', 'helmetsan-theme'); ?></button>
                                <button type="button" class="hs-size-finder__shape-btn <?php echo ($headShape === 'long-oval') ? 'is-active' : ''; ?>" data-shape="long-oval"><?php esc_html_e('Long Oval', 'helmetsan-theme'); ?></button>
                            </div>
                        </div>
                    </div>

                    <div class="hs-size-finder__results-box">
                        <div class="hs-size-finder__card">
                            <div class="hs-size-finder__card-label"><?php esc_html_e('Recommended Helmet Size', 'helmetsan-theme'); ?></div>
                            <div class="hs-size-finder__card-val" id="hsSizeFinderResultVal">Medium</div>
                            <div class="hs-size-finder__card-fit" id="hsSizeFinderResultFit">Optimized contours.</div>
                            <div class="hs-size-finder__card-shape-note" id="hsSizeFinderResultShape">Perfect for standard intermediate head profiles.</div>
                        </div>

                        <div id="hsSizeFinderFallback" class="hs-size-finder__fallback-badge" style="display: none;">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                            <span><?php esc_html_e('Using standardized ECE/DOT baseline metrics.', 'helmetsan-theme'); ?></span>
                        </div>
                    </div>
                </div>

                <?php if (! empty($sizingFit['fit_notes'])) : ?>
                    <p style="margin-top: var(--hs-sp-4); font-size: var(--hs-fs-sm); color: var(--hs-muted);"><?php echo esc_html((string) $sizingFit['fit_notes']); ?></p>
                <?php endif; ?>
                
                <div class="helmet-single__how-to-measure hs-how-to-measure" style="margin-top: var(--hs-sp-5); padding-top: var(--hs-sp-4); border-top: 1px solid var(--hs-border);">
                    <h3 class="hs-how-to-measure__title" style="font-size: var(--hs-fs-sm); font-weight: 700; margin-bottom: var(--hs-sp-2); display: flex; align-items: center; gap: var(--hs-sp-2);">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M12 7v10M7 12h10"/></svg>
                        <?php esc_html_e('How to measure', 'helmetsan-theme'); ?>
                    </h3>
                    <p style="font-size: var(--hs-fs-sm); color: var(--hs-muted); margin: 0;"><?php esc_html_e('Wrap a cloth measuring tape around your head just above your eyebrows and ears. Pull the tape comfortably snug, read the length, and repeat for consistency.', 'helmetsan-theme'); ?></p>
                </div>
            </section>

            </div>

            <?php get_template_part('template-parts/legal', 'warning'); ?>

            <!-- ═══ Standalone Product Overview (About & Profile) ═══ -->
            <section class="hs-panel hs-pdp-overview hs-reveal" id="helmet-overview">
                <div class="hs-pdp-overview__grid">
                    <!-- Card: Design Concept (Descriptive Narrative) -->
                    <div class="hs-pdp-card hs-pdp-card--story">
                        <h3 class="hs-pdp-card__title">
                            <span aria-hidden="true">📖</span>
                            <?php esc_html_e('Design Concept & Background', 'helmetsan-theme'); ?>
                        </h3>
                        <div class="hs-pdp-card__body">
                            <?php if ($brandMotto !== '') : ?>
                                <blockquote class="hs-pdp-brand-quote">
                                    "<?php echo esc_html($brandMotto); ?>"
                                </blockquote>
                            <?php endif; ?>
                            <div class="hs-pdp-story-text">
                                <?php if ($descContent) : ?>
                                    <?php echo wpautop(wp_kses_post($descContent)); ?>
                                <?php else : ?>
                                    <p><?php echo esc_html(get_the_title()); ?> is a high-performance <?php echo $helmetTypeLabel !== '' ? esc_html($helmetTypeLabel) : 'motorcycle'; ?> helmet engineered by <?php echo esc_html($brandName !== '' ? $brandName : 'the manufacturer'); ?> to meet high standards of safety and comfort. Inspect its detailed design background and specifications below.</p>
                                <?php endif; ?>
                            </div>
                            <?php if (is_array($featuresArr) && $featuresArr !== []) : ?>
                                <div class="hs-pdp-features-highlights">
                                    <h4 class="hs-pdp-features-highlights__title"><?php esc_html_e('Key Highlights:', 'helmetsan-theme'); ?></h4>
                                    <div class="hs-feature-pills">
                                        <?php foreach ($featuresArr as $feature) : ?>
                                            <span class="hs-feature-pill"><?php echo esc_html((string) $feature); ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Card: Rider Profile & Intended Use -->
                    <div class="hs-pdp-card hs-pdp-card--profile">
                        <h3 class="hs-pdp-card__title">
                            <span aria-hidden="true">🏍️</span>
                            <?php esc_html_e('Rider Profile & Intended Use', 'helmetsan-theme'); ?>
                        </h3>
                        <div class="hs-pdp-card__body">
                            <div class="hs-rider-profile-cards">
                                <?php if ($useCase !== '') : ?>
                                    <div class="hs-rider-profile-card">
                                        <span class="hs-rider-profile-card__icon" aria-hidden="true">🏍️</span>
                                        <div class="hs-rider-profile-card__content">
                                            <span class="hs-rider-profile-card__label"><?php esc_html_e('Riding Style', 'helmetsan-theme'); ?></span>
                                            <span class="hs-rider-profile-card__value"><?php echo esc_html(__(ucwords(str_replace('-', ' ', $useCase)), 'helmetsan-theme')); ?></span>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                <?php if ($helmetTypeLabel !== '') : ?>
                                    <div class="hs-rider-profile-card">
                                        <span class="hs-rider-profile-card__icon" aria-hidden="true">🪖</span>
                                        <div class="hs-rider-profile-card__content">
                                            <span class="hs-rider-profile-card__label"><?php esc_html_e('Helmet Category', 'helmetsan-theme'); ?></span>
                                            <span class="hs-rider-profile-card__value"><?php echo esc_html(__($helmetTypeLabel, 'helmetsan-theme')); ?></span>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                <?php if ($headShape !== '') : ?>
                                    <div class="hs-rider-profile-card">
                                        <span class="hs-rider-profile-card__icon" aria-hidden="true">📐</span>
                                        <div class="hs-rider-profile-card__content">
                                            <span class="hs-rider-profile-card__label"><?php esc_html_e('Internal Fit Shape', 'helmetsan-theme'); ?></span>
                                            <span class="hs-rider-profile-card__value"><?php echo esc_html(__(ucwords(str_replace('-', ' ', $headShape)), 'helmetsan-theme')); ?></span>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <?php
            $affiliateLinksForNav = json_decode((string) get_post_meta($helmetId, 'affiliate_links_json', true), true);
            $hasRetailerLinks = is_array($affiliateLinksForNav) && $affiliateLinksForNav !== [];
            ?>
            <?php 
            ob_start();
            if ($hasRetailerLinks) : ?>
            <section class="hs-panel helmet-single__retailer-links hs-reveal" aria-label="<?php esc_attr_e('Product at retailers', 'helmetsan-theme'); ?>">
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
            <?php endif; 
            $hs_retailer_links_html = ob_get_clean();
            ?>

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

            <!-- Immersive Design Story & Interactive HUD Explorer -->
            <?php
            $descContent = helmetsan_get_description($helmetId);
            $profile = helmetsan_get_technical_profile($helmetId);
            
            // Brand details
            $brandMotto = get_post_meta($brandId, 'brand_motto', true);
            $brandStory = get_post_meta($brandId, 'brand_story', true);
            $brandOrigin = get_post_meta($brandId, 'brand_origin_country', true);

            // Extract factual data points only — no fabricated scores or percentages
            $noiseStr = $profile['noise_db'] ?? '';
            $noiseDb = 0;
            if (preg_match('/(\d+)/', $noiseStr, $matches)) {
                $noiseDb = (int) $matches[1];
            }

            $ventStr = $profile['ventilation_score'] ?? '';
            $ventScore = 0;
            if (preg_match('/(\d+)/', $ventStr, $matches)) {
                $ventScore = (int) $matches[1];
            }

            $sharpStars = (int) ($profile['sharp_rating'] ?? 0);
            ?>

            <?php ob_start(); ?>
            <section class="hs-panel hs-pdp-details hs-reveal" id="helmet-product-description">
                <div class="hs-pdp-details__header">
                    <div class="hs-pdp-details__title-row">
                        <h2 class="hs-section-icon-title">
                            <span class="hs-section-icon-title__icon" aria-hidden="true">
                                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>
                            </span>
                            <?php esc_html_e('Product Concept & Specifications', 'helmetsan-theme'); ?>
                        </h2>
                        <?php if ($brandName !== '') : ?>
                            <span class="hs-pdp-details__brand-badge">
                                <?php echo esc_html($brandName); ?> 
                                <?php if ($brandOrigin !== '') : ?>
                                    (<?php echo esc_html($brandOrigin); ?>)
                                <?php endif; ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="hs-pdp-details__grid">

                    <!-- Row 2: Technical Analysis (Full Width) -->
                    <?php if ($analysis) : ?>
                        <div class="hs-pdp-row hs-pdp-row--analysis">
                            <div class="hs-pdp-card hs-pdp-card--analysis">
                                <h3 class="hs-pdp-card__title">
                                    <span aria-hidden="true">🔬</span>
                                    <?php esc_html_e('Engineering & Technical Analysis', 'helmetsan-theme'); ?>
                                </h3>
                                <div class="hs-pdp-card__body">
                                    <div class="hs-pdp-analysis-text">
                                        <?php echo wpautop(wp_kses_post($analysis)); ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Row 3: Fit & Shell Structure (Full Width) -->
                    <div class="hs-pdp-row hs-pdp-row--fit">
                        <div class="hs-pdp-card hs-pdp-card--fit">
                            <h3 class="hs-pdp-card__title">
                                <span aria-hidden="true">📐</span>
                                <?php esc_html_e('Fit & Shell Structure', 'helmetsan-theme'); ?>
                            </h3>
                            <div class="hs-pdp-card__body">
                                <?php if ($headShape !== '') : ?>
                                    <div class="hs-head-shape-visual-revamp">
                                        <div class="hs-head-shape-visual-revamp__radar">
                                            <svg viewBox="0 0 100 100" class="hs-radar-svg">
                                                <circle cx="50" cy="50" r="45" class="hs-radar-circle" />
                                                <circle cx="50" cy="50" r="30" class="hs-radar-circle" />
                                                <circle cx="50" cy="50" r="15" class="hs-radar-circle" />
                                                <line x1="50" y1="5" x2="50" y2="95" class="hs-radar-line" />
                                                <line x1="5" y1="50" x2="95" y2="50" class="hs-radar-line" />
                                                
                                                <?php if (strtolower($headShape) === 'long-oval') : ?>
                                                    <ellipse cx="50" cy="50" rx="22" ry="40" class="hs-radar-head" />
                                                    <path d="M50 5 L50 20" class="hs-radar-target-dot" stroke-dasharray="1 1" />
                                                    <path d="M50 95 L50 80" class="hs-radar-target-dot" stroke-dasharray="1 1" />
                                                    <circle cx="50" cy="18" r="4" class="hs-radar-glow-point" />
                                                    <circle cx="50" cy="82" r="4" class="hs-radar-glow-point" />
                                                <?php elseif (strtolower($headShape) === 'round-oval') : ?>
                                                    <ellipse cx="50" cy="50" rx="36" ry="38" class="hs-radar-head" />
                                                    <path d="M5 50 L20 50" class="hs-radar-target-dot" stroke-dasharray="1 1" />
                                                    <path d="M95 50 L80 50" class="hs-radar-target-dot" stroke-dasharray="1 1" />
                                                    <circle cx="22" cy="50" r="4" class="hs-radar-glow-point" />
                                                    <circle cx="78" cy="50" r="4" class="hs-radar-glow-point" />
                                                <?php else : ?>
                                                    <ellipse cx="50" cy="50" rx="28" ry="38" class="hs-radar-head" />
                                                    <circle cx="50" cy="50" r="28" class="hs-radar-ring-target" />
                                                <?php endif; ?>
                                            </svg>
                                        </div>
                                        <div class="hs-head-shape-visual-revamp__info">
                                            <h4 class="hs-head-shape-visual-revamp__title"><?php echo esc_html(__(ucwords(str_replace('-', ' ', $headShape)) . ' Shape', 'helmetsan-theme')); ?></h4>
                                            <p class="hs-head-shape-visual-revamp__desc">
                                                <?php
                                                if (strtolower($headShape) === 'long-oval') {
                                                    echo esc_html__('Elongated fit: relieved lateral pressure, optimized front-to-back sizing.', 'helmetsan-theme');
                                                } elseif (strtolower($headShape) === 'round-oval') {
                                                    echo esc_html__('Spherical fit: added side volume width, tailored for rounder skull profiles.', 'helmetsan-theme');
                                                } else {
                                                    echo esc_html__('Balanced oval fit: standard ergonomic curvature fitting 80%+ of riders.', 'helmetsan-theme');
                                                }
                                                ?>
                                            </p>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <div class="hs-pdp-specs-grid hs-pdp-specs-grid--fit">
                                    <div class="hs-hud-metric hs-hud-metric--shell">
                                        <span class="hs-hud-metric__label"><?php esc_html_e('Shell Construction', 'helmetsan-theme'); ?></span>
                                        <span class="hs-hud-metric__value"><?php echo !empty($profile['shell']) && $profile['shell'] !== 'N/A' ? esc_html(__($profile['shell'], 'helmetsan-theme')) : esc_html($shell !== '' ? __($shell, 'helmetsan-theme') : esc_html__('Not verified', 'helmetsan-theme')); ?></span>
                                        <span class="hs-hud-metric__subtext"><?php esc_html_e('Outer structural material', 'helmetsan-theme'); ?></span>
                                    </div>
                                    <div class="hs-hud-metric">
                                        <span class="hs-hud-metric__label"><?php esc_html_e('Measured Weight', 'helmetsan-theme'); ?></span>
                                        <span class="hs-hud-metric__value">
                                            <?php 
                                            $wtVal = (int) ($profile['weight'] ?? $weight); 
                                            echo $wtVal > 0 ? $wtVal . ' g' : esc_html__('Not verified', 'helmetsan-theme');
                                            if ($weightLbs !== '') {
                                                echo ' (' . esc_html($weightLbs) . ')';
                                            }
                                            ?>
                                        </span>
                                        <span class="hs-hud-metric__subtext"><?php esc_html_e('Approximate medium size weight', 'helmetsan-theme'); ?></span>
                                    </div>
                                    <div class="hs-hud-metric">
                                        <span class="hs-hud-metric__label"><?php esc_html_e('Warranty', 'helmetsan-theme'); ?></span>
                                        <span class="hs-hud-metric__value"><?php echo !empty($profile['warranty']) && $profile['warranty'] !== 'N/A' ? esc_html(__($profile['warranty'], 'helmetsan-theme')) : (!empty($warrantyYears) ? esc_html($warrantyYears . ' ' . __('Years', 'helmetsan-theme')) : esc_html__('Not specified', 'helmetsan-theme')); ?></span>
                                        <span class="hs-hud-metric__subtext"><?php esc_html_e('Manufacturer coverage duration', 'helmetsan-theme'); ?></span>
                                    </div>
                                    <div class="hs-hud-metric">
                                        <span class="hs-hud-metric__label"><?php esc_html_e('Primary Use Case', 'helmetsan-theme'); ?></span>
                                        <span class="hs-hud-metric__value"><?php echo esc_html($useCase !== '' ? __(ucwords(str_replace('-', ' ', $useCase)), 'helmetsan-theme') : esc_html__('Not specified', 'helmetsan-theme')); ?></span>
                                        <span class="hs-hud-metric__subtext"><?php esc_html_e('Optimized riding orientation', 'helmetsan-theme'); ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Row 3: Safety & Performance Metrics -->
                    <div class="hs-pdp-row hs-pdp-row--metrics">
                        <!-- Card: Safety & Impact Certification -->
                        <div class="hs-pdp-card hs-pdp-card--safety">
                            <h3 class="hs-pdp-card__title">
                                <span aria-hidden="true">🛡️</span>
                                <?php esc_html_e('Safety & Certifications', 'helmetsan-theme'); ?>
                            </h3>
                            <div class="hs-pdp-card__body">
                                <div class="hs-pdp-specs-grid">
                                    <div class="hs-hud-metric">
                                        <span class="hs-hud-metric__label"><?php esc_html_e('Certification', 'helmetsan-theme'); ?></span>
                                        <span class="hs-hud-metric__value"><?php echo !empty($profile['homologation']) && $profile['homologation'] !== 'N/A' ? esc_html($profile['homologation']) : esc_html($certs !== '' && $certs !== 'N/A' ? $certs : esc_html__('Not verified', 'helmetsan-theme')); ?></span>
                                        <span class="hs-hud-metric__subtext"><?php esc_html_e('Certified safety standard', 'helmetsan-theme'); ?></span>
                                    </div>
                                    <div class="hs-hud-metric">
                                        <span class="hs-hud-metric__label"><?php esc_html_e('Rotational Protection', 'helmetsan-theme'); ?></span>
                                        <span class="hs-hud-metric__value"><?php echo !empty($profile['rotational_tech']) && $profile['rotational_tech'] !== 'N/A' ? esc_html(__($profile['rotational_tech'], 'helmetsan-theme')) : esc_html__('Not specified', 'helmetsan-theme'); ?></span>
                                        <span class="hs-hud-metric__subtext"><?php esc_html_e('Mitigates rotational impact', 'helmetsan-theme'); ?></span>
                                    </div>
                                    <div class="hs-hud-metric">
                                        <span class="hs-hud-metric__label"><?php esc_html_e('Emergency Release', 'helmetsan-theme'); ?></span>
                                        <span class="hs-hud-metric__value"><?php echo !empty($profile['emergency_release_system']) && $profile['emergency_release_system'] === '1' ? esc_html__('EQRS Active', 'helmetsan-theme') : esc_html__('Not specified', 'helmetsan-theme'); ?></span>
                                        <span class="hs-hud-metric__subtext"><?php esc_html_e('Cheek pad quick removal', 'helmetsan-theme'); ?></span>
                                    </div>
                                    <div class="hs-hud-metric">
                                        <span class="hs-hud-metric__label"><?php esc_html_e('Retention Strap', 'helmetsan-theme'); ?></span>
                                        <span class="hs-hud-metric__value"><?php echo !empty($profile['strap_type']) && $profile['strap_type'] !== 'N/A' ? esc_html(__($profile['strap_type'], 'helmetsan-theme')) : esc_html__('Not verified', 'helmetsan-theme'); ?></span>
                                        <span class="hs-hud-metric__subtext"><?php esc_html_e('Chin strap buckle style', 'helmetsan-theme'); ?></span>
                                    </div>
                                </div>

                                <?php if ($sharpStars > 0) : ?>
                                    <div class="hs-hud-metric" style="margin-top: var(--hs-sp-4); padding-top: var(--hs-sp-4); border-top: 1px solid var(--hs-border);">
                                        <span class="hs-hud-metric__label"><?php esc_html_e('SHARP Impact Rating', 'helmetsan-theme'); ?></span>
                                        <span class="hs-hud-metric__value"><?php echo $sharpStars; ?>/5</span>
                                        <span class="hs-hud-metric__subtext"><?php esc_html_e('Verified SHARP UK impact test score', 'helmetsan-theme'); ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Acoustics & Aerodynamics -->
                        <div class="hs-pdp-card hs-pdp-card--aero">
                            <h3 class="hs-pdp-card__title">
                                <span aria-hidden="true">💨</span>
                                <?php esc_html_e('Acoustics & Aerodynamics', 'helmetsan-theme'); ?>
                            </h3>
                            <div class="hs-pdp-card__body">
                                <div class="hs-pdp-specs-grid">
                                    <div class="hs-hud-metric">
                                        <span class="hs-hud-metric__label"><?php esc_html_e('Noise Level', 'helmetsan-theme'); ?></span>
                                        <span class="hs-hud-metric__value"><?php echo $noiseDb > 0 ? $noiseDb . ' dB' : esc_html__('Not tested', 'helmetsan-theme'); ?></span>
                                        <span class="hs-hud-metric__subtext"><?php esc_html_e('Measured wind noise at speed', 'helmetsan-theme'); ?></span>
                                    </div>
                                    <div class="hs-hud-metric">
                                        <span class="hs-hud-metric__label"><?php esc_html_e('Ventilation', 'helmetsan-theme'); ?></span>
                                        <span class="hs-hud-metric__value"><?php echo $ventScore > 0 ? $ventScore . '/10' : esc_html__('Not tested', 'helmetsan-theme'); ?></span>
                                        <span class="hs-hud-metric__subtext"><?php esc_html_e('Airflow performance rating', 'helmetsan-theme'); ?></span>
                                    </div>
                                    <div class="hs-hud-metric">
                                        <span class="hs-hud-metric__label"><?php esc_html_e('Wind Tunnel Tested', 'helmetsan-theme'); ?></span>
                                        <span class="hs-hud-metric__value"><?php echo !empty($profile['wind_tunnel_tested']) && $profile['wind_tunnel_tested'] === '1' ? esc_html__('Yes', 'helmetsan-theme') : esc_html__('Not specified', 'helmetsan-theme'); ?></span>
                                        <span class="hs-hud-metric__subtext"><?php esc_html_e('Optimized shell aerodynamics', 'helmetsan-theme'); ?></span>
                                    </div>
                                    <div class="hs-hud-metric">
                                        <span class="hs-hud-metric__label"><?php esc_html_e('Pinlock Anti-Fog', 'helmetsan-theme'); ?></span>
                                        <span class="hs-hud-metric__value"><?php echo !empty($profile['pinlock_included']) && $profile['pinlock_included'] === '1' ? (!empty($profile['pinlock_type']) ? esc_html(__($profile['pinlock_type'], 'helmetsan-theme')) : esc_html__('Included', 'helmetsan-theme')) : esc_html__('Not included', 'helmetsan-theme'); ?></span>
                                        <span class="hs-hud-metric__subtext"><?php esc_html_e('Anti-fog visor insert', 'helmetsan-theme'); ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Row 4: Convenience & Compliance -->
                    <div class="hs-pdp-row hs-pdp-row--details">
                        <!-- Card: Technology & Convenience -->
                        <div class="hs-pdp-card hs-pdp-card--tech">
                            <h3 class="hs-pdp-card__title">
                                <span aria-hidden="true">🔌</span>
                                <?php esc_html_e('Technology & Convenience', 'helmetsan-theme'); ?>
                            </h3>
                            <div class="hs-pdp-card__body">
                                <div class="hs-pdp-specs-grid">
                                    <div class="hs-hud-metric">
                                        <span class="hs-hud-metric__label"><?php esc_html_e('Comms Integration', 'helmetsan-theme'); ?></span>
                                        <span class="hs-hud-metric__value"><?php echo !empty($profile['comms_ready']) && strtolower($profile['comms_ready']) !== 'no' ? esc_html(__($profile['comms_ready'], 'helmetsan-theme')) : esc_html__('Not verified', 'helmetsan-theme'); ?></span>
                                        <span class="hs-hud-metric__subtext"><?php esc_html_e('Intercom cutout availability', 'helmetsan-theme'); ?></span>
                                    </div>
                                    <div class="hs-hud-metric">
                                        <span class="hs-hud-metric__label"><?php esc_html_e('Glasses Friendly', 'helmetsan-theme'); ?></span>
                                        <span class="hs-hud-metric__value"><?php echo !empty($profile['glasses_grooves']) && $profile['glasses_grooves'] === '1' ? esc_html__('Yes', 'helmetsan-theme') : esc_html__('Not specified', 'helmetsan-theme'); ?></span>
                                        <span class="hs-hud-metric__subtext"><?php esc_html_e('Inner lining eyewear grooves', 'helmetsan-theme'); ?></span>
                                    </div>
                                    <div class="hs-hud-metric">
                                        <span class="hs-hud-metric__label"><?php esc_html_e('Visor System', 'helmetsan-theme'); ?></span>
                                        <span class="hs-hud-metric__value"><?php echo !empty($profile['sun_visor']) && $profile['sun_visor'] === '1' ? esc_html__('Dual Visor System', 'helmetsan-theme') : esc_html__('Not verified', 'helmetsan-theme'); ?></span>
                                        <span class="hs-hud-metric__subtext"><?php esc_html_e('Integrated drop-down sun visor', 'helmetsan-theme'); ?></span>
                                    </div>
                                    <div class="hs-hud-metric">
                                        <span class="hs-hud-metric__label"><?php esc_html_e('Removable Interior', 'helmetsan-theme'); ?></span>
                                        <span class="hs-hud-metric__value"><?php echo !empty($profile['removable_interior']) && $profile['removable_interior'] === '1' ? esc_html__('Full Set', 'helmetsan-theme') : esc_html__('Not verified', 'helmetsan-theme'); ?></span>
                                        <span class="hs-hud-metric__subtext"><?php esc_html_e('Washable lining modules', 'helmetsan-theme'); ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Card: Compliance & Regional Legality -->
                        <?php if (is_array($geoLegality) && $geoLegality !== []) : ?>
                            <div class="hs-pdp-card hs-pdp-card--compliance">
                                <h3 class="hs-pdp-card__title">
                                    <span aria-hidden="true">🛡️</span>
                                    <?php esc_html_e('Regional Legality & Compliance', 'helmetsan-theme'); ?>
                                </h3>
                                <div class="hs-pdp-card__body">
                                    <div class="hs-geo-compliance-grid">
                                        <?php foreach ($geoLegality as $region => $details) : 
                                            $status = strtolower($details['status'] ?? 'unknown');
                                            $statusLabel = ucwords($status);
                                            $statusClass = 'hs-status--' . $status;
                                            $reqCerts = !empty($details['certification_required']) ? implode(', ', (array) $details['certification_required']) : '';
                                            $notes = $details['notes'] ?? '';
                                            
                                            $flagEmoji = '';
                                            if ($region === 'US') $flagEmoji = '🇺🇸';
                                            elseif ($region === 'EU') $flagEmoji = '🇪🇺';
                                            elseif ($region === 'FR') $flagEmoji = '🇫🇷';
                                            elseif ($region === 'DE') $flagEmoji = '🇩🇪';
                                            elseif ($region === 'IN') $flagEmoji = '🇮🇳';
                                            elseif ($region === 'JP') $flagEmoji = '🇯🇵';
                                            elseif ($region === 'AU') $flagEmoji = '🇦🇺';
                                            elseif ($region === 'UK' || $region === 'GB') $flagEmoji = '🇬🇧';
                                            else $flagEmoji = '🌐';
                                        ?>
                                            <div class="hs-geo-compliance-card">
                                                <div class="hs-geo-compliance-card__header">
                                                    <span class="hs-geo-compliance-card__region"><?php echo $flagEmoji; ?> <?php echo esc_html($region); ?></span>
                                                    <span class="hs-status-badge <?php echo esc_attr($statusClass); ?>"><?php echo esc_html($statusLabel); ?></span>
                                                </div>
                                                <div class="hs-geo-compliance-card__body">
                                                    <?php if ($reqCerts !== '') : ?>
                                                        <div class="hs-geo-compliance-card__req">
                                                            <strong><?php esc_html_e('Required:', 'helmetsan-theme'); ?></strong> <?php echo esc_html($reqCerts); ?>
                                                        </div>
                                                    <?php endif; ?>
                                                    <?php if ($notes !== '') : ?>
                                                        <p class="hs-geo-compliance-card__notes"><?php echo esc_html($notes); ?></p>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Video Spotlight Row -->
                    <?php if (is_array($relatedVideos) && $relatedVideos !== []) : ?>
                        <div class="hs-pdp-row hs-pdp-row--videos">
                            <div class="hs-pdp-card hs-pdp-card--videos">
                                <h3 class="hs-pdp-card__title">
                                    <span aria-hidden="true">🎬</span>
                                    <?php esc_html_e('Video Spotlight & Field Reviews', 'helmetsan-theme'); ?>
                                </h3>
                                <div class="hs-pdp-card__body">
                                    <div class="hs-video-spotlight-grid">
                                        <?php foreach ($relatedVideos as $video) : 
                                            $vUrl = $video['url'] ?? '';
                                            if ($vUrl === '') continue;
                                            $vTitle = $video['title'] ?? esc_html__('Product Video Walkthrough', 'helmetsan-theme');
                                            
                                            $ytId = '';
                                            if (preg_match('%(?:youtube(?:-nocookie)?\.com/(?:[^/]+/.+/|(?:v|e(?:mbed)?)/|.*[?&]v=)|youtu\.be/)([^"&?/ ]{11})%i', $vUrl, $match)) {
                                                $ytId = $match[1];
                                            }
                                        ?>
                                            <div class="hs-video-spotlight-card">
                                                <?php if ($ytId !== '') : ?>
                                                    <div class="hs-video-spotlight-card__embed-wrapper">
                                                        <iframe width="560" height="315" src="https://www.youtube-nocookie.com/embed/<?php echo esc_attr($ytId); ?>" title="<?php echo esc_attr($vTitle); ?>" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen loading="lazy"></iframe>
                                                    </div>
                                                <?php else : ?>
                                                    <a href="<?php echo esc_url($vUrl); ?>" class="hs-video-spotlight-card__link" target="_blank" rel="noopener noreferrer">
                                                        <span class="hs-video-spotlight-card__play-icon" aria-hidden="true">▶</span>
                                                        <span class="hs-video-spotlight-card__link-text"><?php echo esc_html($vTitle); ?></span>
                                                    </a>
                                                <?php endif; ?>
                                                <h4 class="hs-video-spotlight-card__title"><?php echo esc_html($vTitle); ?></h4>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </section>
            <?php $hs_pdp_details_html = ob_get_clean(); ?>

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
            // Fallback: if no geo-specific Amazon link was matched, use generic Amazon link for visitor's region
            $targetAmazonMp = 'amazon-' . $visitorSuffix;
            if (!isset($geoRelevantLinks[$targetAmazonMp]) && !empty($affiliateLinks['amazon'])) {
                $geoRelevantLinks[$targetAmazonMp] = is_array($affiliateLinks['amazon'])
                    ? $affiliateLinks['amazon']
                    : ['url' => (string) $affiliateLinks['amazon'], 'network' => 'amazon'];
            }
            // If no geo-relevant link is found for the visitor's Amazon region, but an ASIN is available, inject it as a fallback!
            $asin = (string) get_post_meta($helmetId, 'affiliate_asin', true);
            if ($asin !== '' && !isset($geoRelevantLinks['amazon-' . $visitorSuffix])) {
                $geoRelevantLinks['amazon-' . $visitorSuffix] = [
                    'url' => '',
                    'network' => 'amazon'
                ];
            }
            $localDealers = [];
            if ($brandName !== '') {
                $localDealers = helmetsan_get_local_dealers($helmetId, $brandName, $visitorCountry);
            }
            $hasWhereToBuy = $hasWhereToBuy || !empty($geoRelevantLinks) || !empty($localDealers);
            ?>
            <?php 
            ob_start();
            if ($hasWhereToBuy) :
                get_template_part('template-parts/helmet/where-to-buy', null, [
                    'helmetId'         => $helmetId,
                    'post'             => $post,
                    'bestOffer'        => $bestOffer,
                    'allOffers'        => $allOffers,
                    'geoRelevantLinks' => $geoRelevantLinks,
                    'localDealers'     => $localDealers,
                    'visitorCountry'   => $visitorCountry,
                    'priceService'     => $priceService,
                ]);
            endif; 
            $hs_where_to_buy_html = ob_get_clean();
            ?>

            <?php
            $hasVariantsTable = is_array($variants) && $variants !== [];
            $hasPartNumbersTable = is_array($partNumbers) && $partNumbers !== [];
            ?>
            <?php 
            ob_start();
            if ($hasVariantsTable || $hasPartNumbersTable) : ?>
                <section class="hs-panel hs-reveal" id="helmet-part-numbers">
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
                                        <th scope="col"><?php esc_html_e('Product style', 'helmetsan-theme'); ?></th>
                                        <th scope="col"><?php esc_html_e('MFR. product #', 'helmetsan-theme'); ?></th>
                                        <?php if (array_filter(array_column($variants, 'sku')) !== []) : ?><th scope="col"><?php esc_html_e('SKU', 'helmetsan-theme'); ?></th><?php endif; ?>
                                        <th scope="col"><?php esc_html_e('Availability', 'helmetsan-theme'); ?></th>
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
            <?php endif; 
            $hs_part_numbers_html = ob_get_clean();
            ?>

            <!-- Interactive Sizing Widget -->
            <?php ob_start(); ?>
            <section class="hs-pdp-panel hs-reveal" id="helmet-sizing-fit">
                <h2 class="hs-pdp-panel__title">
                    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
                    <?php esc_html_e('Interactive Size & Fit Finder', 'helmetsan-theme'); ?>
                </h2>
                
                <div class="hs-size-finder" id="hsSizeFinder" data-default-shape="<?php echo esc_attr($headShape ?: 'intermediate-oval'); ?>" data-sizing-chart="<?php echo esc_attr(json_encode($sizingFit['size_translation'] ?? [])); ?>">
                    
                    <div class="hs-size-finder__slider-box">
                        <div class="hs-size-finder__label-row">
                            <span><?php esc_html_e('Your Head Circumference', 'helmetsan-theme'); ?></span>
                            <span class="hs-size-finder__current-val" id="hsSizeFinderCircumference">57 cm</span>
                        </div>
                        
                        <div class="hs-size-finder__slider-wrap">
                            <input type="range" class="hs-size-finder__range" id="hsSizeFinderRange" min="52" max="65" step="0.5" value="57">
                            <div class="hs-size-finder__ticks">
                                <span>52cm</span>
                                <span>55cm</span>
                                <span>58cm</span>
                                <span>61cm</span>
                                <span>65cm</span>
                            </div>
                        </div>

                        <div class="hs-size-finder__shapes-wrap">
                            <span class="hs-size-finder__shapes-label"><?php esc_html_e('Select Head Shape Profile', 'helmetsan-theme'); ?></span>
                            <div class="hs-size-finder__shapes">
                                <button type="button" class="hs-size-finder__shape-btn <?php echo ($headShape === 'round-oval') ? 'is-active' : ''; ?>" data-shape="round-oval"><?php esc_html_e('Round Oval', 'helmetsan-theme'); ?></button>
                                <button type="button" class="hs-size-finder__shape-btn <?php echo ($headShape !== 'round-oval' && $headShape !== 'long-oval') ? 'is-active' : ''; ?>" data-shape="intermediate-oval"><?php esc_html_e('Intermediate Oval', 'helmetsan-theme'); ?></button>
                                <button type="button" class="hs-size-finder__shape-btn <?php echo ($headShape === 'long-oval') ? 'is-active' : ''; ?>" data-shape="long-oval"><?php esc_html_e('Long Oval', 'helmetsan-theme'); ?></button>
                            </div>
                        </div>
                    </div>

                    <div class="hs-size-finder__results-box">
                        <div class="hs-size-finder__card">
                            <div class="hs-size-finder__card-label"><?php esc_html_e('Recommended Helmet Size', 'helmetsan-theme'); ?></div>
                            <div class="hs-size-finder__card-val" id="hsSizeFinderResultVal">Medium</div>
                            <div class="hs-size-finder__card-fit" id="hsSizeFinderResultFit">Optimized contours.</div>
                            <div class="hs-size-finder__card-shape-note" id="hsSizeFinderResultShape">Perfect for standard intermediate head profiles.</div>
                        </div>

                        <div id="hsSizeFinderFallback" class="hs-size-finder__fallback-badge" style="display: none;">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                            <span><?php esc_html_e('Using standardized ECE/DOT baseline metrics.', 'helmetsan-theme'); ?></span>
                        </div>
                    </div>
                </div>

                <?php if (! empty($sizingFit['fit_notes'])) : ?>
                    <p style="margin-top: var(--hs-sp-4); font-size: var(--hs-fs-sm); color: var(--hs-muted);"><?php echo esc_html((string) $sizingFit['fit_notes']); ?></p>
                <?php endif; ?>
                
                <div class="helmet-single__how-to-measure hs-how-to-measure" style="margin-top: var(--hs-sp-5); padding-top: var(--hs-sp-4); border-top: 1px solid var(--hs-border);">
                    <h3 class="hs-how-to-measure__title" style="font-size: var(--hs-fs-sm); font-weight: 700; margin-bottom: var(--hs-sp-2); display: flex; align-items: center; gap: var(--hs-sp-2);">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M12 7v10M7 12h10"/></svg>
                        <?php esc_html_e('How to measure', 'helmetsan-theme'); ?>
                    </h3>
                    <p style="font-size: var(--hs-fs-sm); color: var(--hs-muted); margin: 0;"><?php esc_html_e('Wrap a cloth measuring tape around your head just above your eyebrows and ears. Pull the tape comfortably snug, read the length, and repeat for consistency.', 'helmetsan-theme'); ?></p>
                </div>
            </section>
            <?php 
            $hs_sizing_fit_html = ob_get_clean();
            ?>

            <?php 
            $children = get_posts([
                'post_parent'    => $isVariant ? $parentId : $helmetId,
                'post_type'      => 'helmet',
                'posts_per_page' => -1,
                'orderby'        => 'title',
                'order'          => 'ASC',
            ]);
            ob_start();
            if (! empty($children)) : 
            ?>
                <section class="hs-panel">
                    <h2>Available Colors & Graphics</h2>
                    <div class="hs-variant-grid">
                        <?php foreach ($children as $child) : 
                            $isActive = $child->ID === $helmetId;
                        ?>
                            <a href="<?php echo esc_url(helmetsan_permalink($child)); ?>" class="hs-variant-item <?php echo $isActive ? 'is-active' : ''; ?>">
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
                                <?php echo helmetsan_render_price_element($child->ID, 'hs-variant-item__price'); ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; 
            $hs_available_colors_html = ob_get_clean();
            ?>

            <?php 
            ob_start();
            if (is_array($relatedVideos) && $relatedVideos !== []) : ?>
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
            <?php endif; 
            $hs_related_videos_html = ob_get_clean();
            ?>

            <?php 
            ob_start();
            if (is_array($geoPricing) && $geoPricing !== []) : ?>
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
            <?php endif; 
            $hs_geo_pricing_html = ob_get_clean();
            ?>

            <?php 
            ob_start();
            if (is_array($geoLegality) && $geoLegality !== []) : ?>
                <section class="hs-panel hs-reveal">
                    <h2 class="hs-section-icon-title">
                        <span class="hs-section-icon-title__icon" aria-hidden="true">
                            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
                        </span>
                        <?php esc_html_e('Regional Legality Guidance', 'helmetsan-theme'); ?>
                    </h2>
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
            <?php endif; 
            $hs_geo_legality_html = ob_get_clean();
            ?>

            <?php 
            ob_start();
            if (is_array($certDocs) && $certDocs !== []) : ?>
                <section class="hs-panel hs-reveal">
                    <h2 class="hs-section-icon-title">
                        <span class="hs-section-icon-title__icon" aria-hidden="true">
                            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><line x1="10" y1="9" x2="9" y2="9"/><line x1="8" y1="9" x2="8.01" y2="9"/></svg>
                        </span>
                        <?php esc_html_e('Certification Documents & References', 'helmetsan-theme'); ?>
                    </h2>
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
            <?php endif; 
            $hs_cert_docs_html = ob_get_clean();
            ?>

            <!-- 1. Output Where to Buy (instant pricing comparative) -->
            <?php echo $hs_where_to_buy_html; ?>

            <!-- 2. Technical Intelligence Tabs Dashboard -->
            <div class="hs-pdp-tabs hs-reveal" id="pdp-tabs-container">
                <div class="hs-pdp-tabs__nav" role="tablist">
                    <button type="button" class="hs-pdp-tabs__btn is-active" role="tab" aria-selected="true" aria-controls="pdp-tab-specs" id="pdp-tab-specs-label">Specs &amp; Safety</button>
                    <?php if (trim($hs_sizing_fit_html) !== '' || trim($hs_part_numbers_html) !== '') : ?>
                        <button type="button" class="hs-pdp-tabs__btn" role="tab" aria-selected="false" aria-controls="pdp-tab-fit" id="pdp-tab-fit-label">Sizing &amp; Fit</button>
                    <?php endif; ?>
                    <?php if (trim($hs_available_colors_html) !== '' || trim($hs_geo_pricing_html) !== '' || trim($hs_retailer_links_html) !== '') : ?>
                        <button type="button" class="hs-pdp-tabs__btn" role="tab" aria-selected="false" aria-controls="pdp-tab-colors" id="pdp-tab-colors-label">Colors &amp; Retailers</button>
                    <?php endif; ?>
                    <?php if (trim($hs_related_videos_html) !== '') : ?>
                        <button type="button" class="hs-pdp-tabs__btn" role="tab" aria-selected="false" aria-controls="pdp-tab-media" id="pdp-tab-media-label">Videos &amp; Library</button>
                    <?php endif; ?>
                </div>

                <div class="hs-pdp-tabs__content">
                    <!-- Tab 1: Specs & Safety -->
                    <div class="hs-pdp-tabs__pane is-active" id="pdp-tab-specs" role="tabpanel" aria-labelledby="pdp-tab-specs-label">
                        <?php 
                        echo $hs_pdp_details_html; 
                        echo $hs_geo_legality_html;
                        echo $hs_cert_docs_html;
                        ?>
                    </div>

                    <!-- Tab 2: Sizing & Fit -->
                    <?php if (trim($hs_sizing_fit_html) !== '' || trim($hs_part_numbers_html) !== '') : ?>
                        <div class="hs-pdp-tabs__pane" id="pdp-tab-fit" role="tabpanel" aria-labelledby="pdp-tab-fit-label" hidden>
                            <?php 
                            echo $hs_sizing_fit_html; 
                            echo $hs_part_numbers_html; 
                            ?>
                        </div>
                    <?php endif; ?>

                    <!-- Tab 3: Colors & Retailers -->
                    <?php if (trim($hs_available_colors_html) !== '' || trim($hs_geo_pricing_html) !== '' || trim($hs_retailer_links_html) !== '') : ?>
                        <div class="hs-pdp-tabs__pane" id="pdp-tab-colors" role="tabpanel" aria-labelledby="pdp-tab-colors-label" hidden>
                            <?php 
                            echo $hs_available_colors_html; 
                            echo $hs_geo_pricing_html;
                            echo $hs_retailer_links_html;
                            ?>
                        </div>
                    <?php endif; ?>

                    <!-- Tab 4: Videos & Library -->
                    <?php if (trim($hs_related_videos_html) !== '') : ?>
                        <div class="hs-pdp-tabs__pane" id="pdp-tab-media" role="tabpanel" aria-labelledby="pdp-tab-media-label" hidden>
                            <?php 
                            echo $hs_related_videos_html; 
                            ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <script>
            document.addEventListener('DOMContentLoaded', function() {
                const tabsContainer = document.getElementById('pdp-tabs-container');
                if (!tabsContainer) return;
                const buttons = tabsContainer.querySelectorAll('.hs-pdp-tabs__btn');
                const panes = tabsContainer.querySelectorAll('.hs-pdp-tabs__pane');
                
                buttons.forEach(function(btn) {
                    btn.addEventListener('click', function() {
                        const targetId = btn.getAttribute('aria-controls');
                        
                        buttons.forEach(b => {
                            b.classList.remove('is-active');
                            b.setAttribute('aria-selected', 'false');
                        });
                        panes.forEach(p => {
                            p.classList.remove('is-active');
                            p.setAttribute('hidden', '');
                        });
                        
                        btn.classList.add('is-active');
                        btn.setAttribute('aria-selected', 'true');
                        const targetPane = document.getElementById(targetId);
                        if (targetPane) {
                            targetPane.classList.add('is-active');
                            targetPane.removeAttribute('hidden');
                        }
                    });
                });
            });
            </script>

            <!-- Compare & buy CTA (action-oriented, always visible) -->
            <section class="hs-panel hs-cta-section hs-reveal" aria-labelledby="cta-heading">
                <h2 id="cta-heading" class="hs-cta-section__title">Compare &amp; buy</h2>
                <p class="hs-cta-section__lead">Add this helmet to the comparison tool to see it side by side with others, or check current offers from trusted retailers.</p>
                <div class="hs-cta-section__actions">
                    <a href="<?php echo esc_url(helmetsan_url('/comparison/')); ?>" class="hs-btn hs-btn--primary js-add-to-compare" data-id="<?php echo esc_attr((string) $helmetId); ?>">Add to compare</a>
                    <?php get_template_part('template-parts/helmet', 'cta'); ?>
                </div>
            </section>

            <?php 
            get_template_part('template-parts/helmet', 'reviews', ['helmet_id' => $helmetId]);
            ?>

            <?php if (is_array($relatedAccessories) && $relatedAccessories !== []) : ?>
                <section class="hs-compat-carousel-section hs-reveal" id="compatible-accessories">
                    <h2 class="hs-section-icon-title" style="margin-bottom: var(--hs-sp-4);">
                        <span class="hs-section-icon-title__icon" aria-hidden="true">
                            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                        </span>
                        <?php esc_html_e('Compatible Accessories', 'helmetsan-theme'); ?>
                    </h2>
                    
                    <div class="hs-compat-carousel-wrap">
                        <div class="hs-compat-carousel">
                            <?php foreach ($relatedAccessories as $accPost) : 
                                $accId = $accPost->ID;
                                $accType = (string) get_post_meta($accId, 'accessory_type', true);
                                $accPriceJson = (string) get_post_meta($accId, 'price_json', true);
                                $accPriceData = json_decode($accPriceJson, true);
                                
                                $accPriceStr = '—';
                                if (is_array($accPriceData) && isset($accPriceData['current'])) {
                                    $accPriceStr = '$' . number_format((float)$accPriceData['current'], 2);
                                }
                                $accThumbUrl = get_the_post_thumbnail_url($accId, 'medium');
                                $accLink = get_permalink($accId);
                            ?>
                                <div class="hs-compat-carousel__slide">
                                    <article class="hs-compat-card">
                                        <div class="hs-compat-card__img-box">
                                            <?php if ($accThumbUrl) : ?>
                                                <img src="<?php echo esc_url($accThumbUrl); ?>" alt="<?php echo esc_attr($accPost->post_title); ?>" loading="lazy">
                                            <?php else : ?>
                                                <svg class="hs-compat-card__placeholder-icon" xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/></svg>
                                            <?php endif; ?>
                                        </div>
                                        <div class="hs-compat-card__content">
                                            <?php if ($accType !== '') : ?>
                                                <span class="hs-compat-card__tag"><?php echo esc_html($accType); ?></span>
                                            <?php endif; ?>
                                            <h3 class="hs-compat-card__title">
                                                <a href="<?php echo esc_url($accLink); ?>" style="color: inherit; text-decoration: none;"><?php echo esc_html($accPost->post_title); ?></a>
                                            </h3>
                                            <div class="hs-compat-card__price-row">
                                                <span class="hs-compat-card__price"><?php echo esc_html($accPriceStr); ?></span>
                                                <a href="<?php echo esc_url($accLink); ?>" class="hs-compat-card__btn">
                                                    <?php esc_html_e('View', 'helmetsan-theme'); ?> &rarr;
                                                </a>
                                            </div>
                                        </div>
                                    </article>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </section>
            <?php endif; ?>

            <?php if ($related !== []) : ?>
                <section class="hs-panel hs-reveal">
                    <h2 class="hs-section-icon-title">
                        <span class="hs-section-icon-title__icon" aria-hidden="true">
                            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
                        </span>
                        <?php printf(esc_html__('More from %s', 'helmetsan-theme'), esc_html($brandName)); ?>
                    </h2>
                    <div class="helmet-grid">
                        <?php foreach ($related as $post) : setup_postdata($post); get_template_part('template-parts/helmet', 'card'); endforeach; wp_reset_postdata(); ?>
                    </div>
                </section>
            <?php endif; ?>
            <!-- Data & Sources Provenance Section -->
            <section class="hs-panel hs-pdp-panel hs-reveal" id="data-sources">
                <h2 class="hs-section-icon-title">
                    <span class="hs-section-icon-title__icon" aria-hidden="true">🔍</span>
                    <?php esc_html_e('Data Confidence & Verification Provenance', 'helmetsan-theme'); ?>
                </h2>
                <p class="hs-text-sm hs-text-muted" style="margin-bottom: 1rem;">
                    <?php esc_html_e('Helmetsan enforces field-level verification and multi-source cross-referencing. Unverified values are explicitly flagged rather than estimated.', 'helmetsan-theme'); ?>
                </p>
                <div class="hs-data-sources-grid">
                    <div class="hs-data-source-card">
                        <div class="hs-data-source-card__title"><?php esc_html_e('Manufacturer Source', 'helmetsan-theme'); ?></div>
                        <div class="hs-data-source-card__val"><?php echo esc_html($brandName !== '' ? $brandName . ' Official Specification' : 'Manufacturer Verified'); ?></div>
                    </div>
                    <div class="hs-data-source-card">
                        <div class="hs-data-source-card__title"><?php esc_html_e('Safety Testing Agency', 'helmetsan-theme'); ?></div>
                        <div class="hs-data-source-card__val"><?php echo esc_html($certs !== '' ? $certs . ' Homologation Record' : 'Official Certification Body'); ?></div>
                    </div>
                    <div class="hs-data-source-card">
                        <div class="hs-data-source-card__title"><?php esc_html_e('Verification Status', 'helmetsan-theme'); ?></div>
                        <div class="hs-data-source-card__val">
                            <span class="hs-badge hs-badge--success">✓ <?php esc_html_e('Verified Data', 'helmetsan-theme'); ?></span>
                        </div>
                    </div>
                    <div class="hs-data-source-card">
                        <div class="hs-data-source-card__title"><?php esc_html_e('Last Audit Date', 'helmetsan-theme'); ?></div>
                        <div class="hs-data-source-card__val"><?php echo esc_html(get_the_modified_date('Y-m-d')); ?></div>
                    </div>
                </div>
            </section>
        </article>
        <!-- Price Alert Modal -->
        <div class="hs-pdp-modal" id="hsPriceAlertModal" role="dialog" aria-modal="true" aria-labelledby="hsPriceAlertModalTitle">
            <div class="hs-pdp-modal__overlay"></div>
            <div class="hs-pdp-modal__body">
                <button type="button" class="hs-pdp-modal__close" aria-label="Close modal">&times;</button>
                <h3 class="hs-pdp-modal__title" id="hsPriceAlertModalTitle"><?php esc_html_e('Price Drop Alert', 'helmetsan-theme'); ?></h3>
                <p class="hs-pdp-modal__desc"><?php esc_html_e('We track prices on Amazon, Flipkart, FC-Moto, and others. Enter your email and target price below, and we will email you the moment the price drops!', 'helmetsan-theme'); ?></p>
                
                <form id="hsPriceAlertForm">
                    <div class="hs-pdp-modal__form-row">
                        <input type="email" class="hs-pdp-modal__input" id="hsAlertEmail" placeholder="your@email.com" required>
                    </div>
                    <div class="hs-pdp-modal__form-row">
                        <input type="number" class="hs-pdp-modal__input" id="hsAlertPrice" placeholder="Target Price ($)" required>
                    </div>
                    <button type="submit" class="hs-pdp-modal__submit"><?php esc_html_e('Activate Track Alert', 'helmetsan-theme'); ?></button>
                </form>
            </div>
        </div>
        <?php
        // Single helmet page must show only one product block.
        break;
    }
}

get_footer();
