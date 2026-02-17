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
        $geoPricing = json_decode($geoPricingJson, true);
        $geoLegality = json_decode($geoLegalityJson, true);
        $certDocs = json_decode($certDocsJson, true);

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
                        <div class="helmetsan-specs-row"><dt>Shell</dt><dd><?php echo esc_html($shell !== '' ? $shell : 'N/A'); ?></dd></div>
                        <div class="helmetsan-specs-row"><dt>Certifications</dt><dd><?php echo esc_html($certs); ?></dd></div>
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
