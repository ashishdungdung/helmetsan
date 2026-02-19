<?php
/**
 * Single helmet template.
 *
 * @package HelmetsanTheme
 */

get_header();

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
        $geoLegalityJson = (string) get_post_meta($helmetId, 'geo_legality_json', true);
        $certDocsJson = (string) get_post_meta($helmetId, 'certification_documents_json', true);
        $variantsJson = (string) get_post_meta($helmetId, 'variants_json', true);
        $productDetailsJson = (string) get_post_meta($helmetId, 'product_details_json', true);
        $partNumbersJson = (string) get_post_meta($helmetId, 'part_numbers_json', true);
        $sizingFitJson = (string) get_post_meta($helmetId, 'sizing_fit_json', true);
        $relatedVideosJson = (string) get_post_meta($helmetId, 'related_videos_json', true);
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
        $parentId = (int) $post->post_parent;
        $isVariant = $parentId > 0;
        $parentPost = $isVariant ? get_post($parentId) : null;
        
        // Multi-currency price
        $priceUsd = helmetsan_get_price($helmetId, 'USD');
        $priceEur = helmetsan_get_price($helmetId, 'EUR');
        $priceGbp = helmetsan_get_price($helmetId, 'GBP');
        
        // New Schema Fields (v1.1)
        $safetyJson = (string) get_post_meta($helmetId, 'safety_intelligence_json', true);
        $aeroJson = (string) get_post_meta($helmetId, 'aero_acoustic_profile_json', true);
        $techJson = (string) get_post_meta($helmetId, 'tech_integration_json', true);
        $fitJson = (string) get_post_meta($helmetId, 'fitment_coordinates_json', true);

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
        <article <?php post_class('helmet-single hs-section'); ?>>
            <header class="helmet-single__header hs-section__head">
                <?php if ($isVariant && $parentPost) : ?>
                    <p class="hs-eyebrow">
                        <a href="<?php echo esc_url(get_permalink($parentPost)); ?>"><?php echo esc_html($parentPost->post_title); ?></a>
                         &rsaquo; <?php echo esc_html($brandName); ?>
                    </p>
                <?php else : ?>
                    <p class="hs-eyebrow"><?php echo esc_html($brandName !== '' ? $brandName : 'Helmet'); ?></p>
                <?php endif; ?>
                
                <h1><?php the_title(); ?></h1>
                
                <div class="helmet-single__meta-chip">
                    <?php if ($priceUsd !== 'N/A') : ?><span><?php echo esc_html($priceUsd); ?></span><?php endif; ?>
                    <?php if ($priceEur !== 'N/A') : ?><span><?php echo esc_html($priceEur); ?></span><?php endif; ?>
                    <?php if ($priceGbp !== 'N/A') : ?><span><?php echo esc_html($priceGbp); ?></span><?php endif; ?>
                    <span><?php echo esc_html($certs); ?></span>
                </div>
            </header>

            <div class="helmet-single__layout">
                <div class="helmet-single__media hs-panel" style="padding:0; overflow:hidden;">
                    <a href="/comparison/" 
                       class="js-view-compare hs-btn hs-btn--sm hs-btn--primary is-hidden"
                       style="position:absolute; top:1rem; right:5rem; z-index:10;">
                        View Compare
                    </a>
                    <button type="button" 
                            class="js-add-to-compare hs-btn hs-btn--icon" 
                            data-id="<?php echo esc_attr((string) $helmetId); ?>" 
                            title="<?php esc_attr_e('Compare', 'helmetsan-theme'); ?>"
                            aria-pressed="false"
                            style="position:absolute; top:1rem; right:1rem; z-index:10;">
                        <span>Compare</span>
                    </button>
                    <?php 
                    $gallery = helmetsan_core()->mediaService()->getProductGallery($helmetId);
                    if (!empty($gallery)) : 
                    ?>
                        <div class="hs-carousel">
                            <div class="hs-carousel__track">
                                <?php foreach ($gallery as $item) : ?>
                                    <div class="hs-carousel__slide">
                                        <?php if ($item['type'] === 'video') : ?>
                                            <div class="hs-responsive-embed">
                                                <?php echo $item['embed']; ?>
                                            </div>
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
                </div>

                <aside class="helmet-single__aside hs-panel">
                    <h2>Key Specs</h2>
                    <dl class="helmetsan-specs-grid">
                        <div class="helmetsan-specs-row"><dt>Weight</dt><dd><?php echo esc_html($weight > 0 ? $weight . ' g' : 'N/A'); ?></dd></div>
                        <div class="helmetsan-specs-row"><dt>Weight (lbs)</dt><dd><?php echo esc_html($weightLbs !== '' ? $weightLbs . ' lbs' : 'N/A'); ?></dd></div>
                        <div class="helmetsan-specs-row"><dt>Shell</dt><dd><?php echo esc_html($shell !== '' ? $shell : 'N/A'); ?></dd></div>
                        <div class="helmetsan-specs-row"><dt>Head Shape</dt><dd><?php echo esc_html($headShape !== '' ? ucwords(str_replace('-', ' ', $headShape)) : 'N/A'); ?></dd></div>
                        <div class="helmetsan-specs-row"><dt>Helmet Family</dt><dd><?php echo esc_html($helmetFamily !== '' ? $helmetFamily : 'N/A'); ?></dd></div>
                        <div class="helmetsan-specs-row"><dt>Certification Marks</dt><dd><?php echo esc_html($certs); ?></dd></div>
                        <div class="helmetsan-specs-row"><dt>Price</dt><dd><?php echo esc_html($price); ?></dd></div>
                    </dl>

                    <?php get_template_part('template-parts/helmet', 'cta'); ?>

                    <?php if ($brandId > 0) : ?>
                        <p><a class="hs-link" href="<?php echo esc_url(get_permalink($brandId)); ?>">View brand profile</a></p>
                    <?php endif; ?>
                </aside>
            </div>

            <?php get_template_part('template-parts/legal', 'warning'); ?>

            <div class="helmet-single__content hs-panel">
                <h2>Technical Analysis</h2>
                <?php if ($analysis) : ?>
                    <div class="hs-analysis-rich-text">
                        <?php echo wpautop(esc_html($analysis)); ?>
                    </div>
                <?php endif; ?>
                <?php the_content(); ?>
            </div>

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


            <?php if (is_array($productDetails) && $productDetails !== []) : ?>
                <section class="hs-panel">
                    <h2>Product Details</h2>
                    <div class="hs-table-wrap">
                        <table class="hs-table">
                            <tbody>
                                <tr><th>Product Style</th><td><?php echo esc_html((string) ($productDetails['style'] ?? 'N/A')); ?></td></tr>
                                <tr><th>MFR Product Number</th><td><?php echo esc_html((string) ($productDetails['mfr_product_number'] ?? 'N/A')); ?></td></tr>
                                <tr><th>Sizing &amp; Fit</th><td><?php echo esc_html((string) ($productDetails['sizing_fit'] ?? 'See sizing section below')); ?></td></tr>
                            </tbody>
                        </table>
                    </div>
                    <?php if (! empty($productDetails['description'])) : ?>
                        <p><?php echo esc_html((string) $productDetails['description']); ?></p>
                    <?php endif; ?>
                </section>
            <?php endif; ?>

            <?php if (is_array($partNumbers) && $partNumbers !== []) : ?>
                <section class="hs-panel">
                    <h2>Part Numbers</h2>
                    <div class="hs-table-wrap">
                        <table class="hs-table">
                            <thead>
                                <tr><th>Type</th><th>Value</th></tr>
                            </thead>
                            <tbody>
                            <?php foreach ($partNumbers as $row) : if (! is_array($row)) { continue; } ?>
                                <tr>
                                    <td><?php echo esc_html((string) ($row['label'] ?? 'Part Number')); ?></td>
                                    <td><?php echo esc_html((string) ($row['value'] ?? 'N/A')); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
            <?php endif; ?>

            <?php if (is_array($sizingFit) && $sizingFit !== []) : ?>
                <section class="hs-panel">
                    <h2>Sizing &amp; Fit</h2>
                    <?php if (! empty($sizingFit['fit_notes'])) : ?>
                        <p><?php echo esc_html((string) $sizingFit['fit_notes']); ?></p>
                    <?php endif; ?>
                    <?php if (! empty($sizingFit['head_shape'])) : ?>
                        <p><strong>Head Shape:</strong> <?php echo esc_html((string) $sizingFit['head_shape']); ?></p>
                    <?php endif; ?>
                    <?php if (isset($sizingFit['size_translation']) && is_array($sizingFit['size_translation']) && $sizingFit['size_translation'] !== []) : ?>
                        <div class="hs-table-wrap">
                            <table class="hs-table">
                                <thead>
                                    <tr><th>Size</th><th>CM</th><th>Inches</th></tr>
                                </thead>
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

                    <?php if (is_array($fitCoords) && $fitCoords !== []) : ?>
                        <h3>Internal Dimensions</h3>
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
                                    <?php if (has_post_thumbnail($child->ID)) : ?>
                                        <?php echo get_the_post_thumbnail($child->ID, 'thumbnail'); ?>
                                    <?php else : ?>
                                        <span style="color: var(--hs-muted); font-size: 10px;">No Image</span>
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
                    <h2>Related Videos</h2>
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
    }
}

get_footer();
