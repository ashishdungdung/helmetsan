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
        $weight = (string) get_post_meta($helmetId, 'spec_weight_g', true);
        $shell = (string) get_post_meta($helmetId, 'spec_shell_material', true);
        $price = helmetsan_get_helmet_price($helmetId);
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
        $headShape = (string) get_post_meta($helmetId, 'head_shape', true);
        $helmetFamily = (string) get_post_meta($helmetId, 'helmet_family', true);
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
                <p class="hs-eyebrow"><?php echo esc_html($brandName !== '' ? $brandName : 'Helmet'); ?></p>
                <h1><?php the_title(); ?></h1>
                <div class="helmet-single__meta-chip">
                    <span><?php echo esc_html($price); ?></span>
                    <span><?php echo esc_html($certs); ?></span>
                </div>
            </header>

            <div class="helmet-single__layout">
                <div class="helmet-single__media hs-panel">
                    <?php if (has_post_thumbnail()) : ?>
                        <div class="helmet-single__image"><?php the_post_thumbnail('large'); ?></div>
                    <?php else : ?>
                        <div class="helmet-single__placeholder">No image available</div>
                    <?php endif; ?>
                </div>

                <aside class="helmet-single__aside hs-panel">
                    <h2>Key Specs</h2>
                    <dl class="helmetsan-specs-grid">
                        <div class="helmetsan-specs-row"><dt>Weight</dt><dd><?php echo esc_html($weight !== '' ? $weight . ' g' : 'N/A'); ?></dd></div>
                        <div class="helmetsan-specs-row"><dt>Weight (lbs)</dt><dd><?php echo esc_html($weightLbs !== '' ? $weightLbs . ' lbs' : 'N/A'); ?></dd></div>
                        <div class="helmetsan-specs-row"><dt>Shell</dt><dd><?php echo esc_html($shell !== '' ? $shell : 'N/A'); ?></dd></div>
                        <div class="helmetsan-specs-row"><dt>Head Shape</dt><dd><?php echo esc_html($headShape !== '' ? $headShape : 'N/A'); ?></dd></div>
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
                <?php the_content(); ?>
            </div>

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
                </section>
            <?php endif; ?>

            <?php if (is_array($variants) && $variants !== []) : ?>
                <section class="hs-panel">
                    <h2>Variants, Colors &amp; Sizes</h2>
                    <div class="hs-table-wrap">
                        <table class="hs-table">
                            <thead>
                                <tr><th>Variant</th><th>Color/Graphic</th><th>Size</th><th>CM</th><th>Inches</th><th>MFR Part No.</th><th>Price</th><th>Availability</th></tr>
                            </thead>
                            <tbody>
                            <?php foreach ($variants as $row) : if (! is_array($row)) { continue; } ?>
                                <tr>
                                    <td><?php echo esc_html((string) ($row['style'] ?? ($row['id'] ?? 'Variant'))); ?></td>
                                    <td><?php echo esc_html(trim((string) ($row['color'] ?? '') . ' ' . (string) ($row['graphics'] ?? ''))); ?></td>
                                    <td><?php echo esc_html((string) ($row['size'] ?? '')); ?></td>
                                    <td><?php echo esc_html((string) ($row['size_cm'] ?? '')); ?></td>
                                    <td><?php echo esc_html((string) ($row['size_in'] ?? '')); ?></td>
                                    <td><?php echo esc_html((string) ($row['mfr_part_number'] ?? ($row['sku'] ?? ''))); ?></td>
                                    <td><?php echo esc_html((string) ($row['price'] ?? '') . ' ' . (string) ($row['currency'] ?? '')); ?></td>
                                    <td><?php echo esc_html((string) ($row['availability'] ?? '')); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
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
