<?php
/**
 * Single accessory template.
 *
 * @package HelmetsanTheme
 */

get_header();

if (have_posts()) {
    while (have_posts()) {
        the_post();
        $id = get_the_ID();
        $type = (string) get_post_meta($id, 'accessory_type', true);
        $color = (string) get_post_meta($id, 'accessory_color', true);
        $parentCategory = (string) get_post_meta($id, 'accessory_parent_category', true);
        $subcategory = (string) get_post_meta($id, 'accessory_subcategory', true);
        $youthAdult = (string) get_post_meta($id, 'accessory_youth_adult', true);
        $pinlockReady = (string) get_post_meta($id, 'accessory_pinlock_ready', true);
        $electricCompat = (string) get_post_meta($id, 'accessory_electric_compatible', true);
        $snowCompat = (string) get_post_meta($id, 'accessory_snow_compatible', true);
        $priceJson = (string) get_post_meta($id, 'price_json', true);
        $helmetTypesJson = (string) get_post_meta($id, 'compatible_helmet_types_json', true);
        $brandsJson = (string) get_post_meta($id, 'compatible_brands_json', true);
        $familiesJson = (string) get_post_meta($id, 'compatible_helmet_families_json', true);
        $featuresJson = (string) get_post_meta($id, 'accessory_features_json', true);

        $price = is_string($priceJson) && $priceJson !== '' ? json_decode($priceJson, true) : null;
        $helmetTypes = is_string($helmetTypesJson) && $helmetTypesJson !== '' ? json_decode($helmetTypesJson, true) : [];
        $brands = is_string($brandsJson) && $brandsJson !== '' ? json_decode($brandsJson, true) : [];
        $families = is_string($familiesJson) && $familiesJson !== '' ? json_decode($familiesJson, true) : [];
        $features = is_string($featuresJson) && $featuresJson !== '' ? json_decode($featuresJson, true) : [];

        $helmetTypes = is_array($helmetTypes) ? $helmetTypes : [];
        $brands = is_array($brands) ? $brands : [];
        $families = is_array($families) ? $families : [];
        $features = is_array($features) ? $features : [];

        $categoryTerms = get_the_terms($id, 'accessory_category');
        $categoryTerms = is_array($categoryTerms) ? $categoryTerms : [];

        $priceDisplay = '—';
        $currency = '';
        if (is_array($price) && isset($price['current'])) {
            $priceDisplay = is_numeric($price['current']) ? number_format_i18n((float) $price['current']) : (string) $price['current'];
            $currency = (string) ($price['currency'] ?? '');
        }
        $compatCount = count($helmetTypes);
        $accessoriesUrl = get_post_type_archive_link('accessory') ?: home_url('/accessories/');
        ?>
        <article <?php post_class('accessory-single'); ?>>
            <nav class="accessory-single__breadcrumb" aria-label="Breadcrumb">
                <a href="<?php echo esc_url($accessoriesUrl); ?>">← Accessories</a>
            </nav>

            <header class="accessory-hero hs-section">
                <div class="accessory-hero__info">
                    <p class="hs-eyebrow accessory-hero__eyebrow">Accessory</p>
                    <h1 class="accessory-hero__title"><?php the_title(); ?></h1>
                    <?php if (has_excerpt()) : ?>
                        <p class="accessory-hero__tagline"><?php echo esc_html(get_the_excerpt()); ?></p>
                    <?php endif; ?>

                    <div class="accessory-hero__meta">
                        <?php if ($color !== '') : ?>
                            <span class="accessory-hero__chip" aria-label="Color"><?php echo esc_html($color); ?></span>
                        <?php endif; ?>
                        <?php if ($parentCategory !== '') : ?>
                            <span class="accessory-hero__chip accessory-hero__chip--category"><?php echo esc_html($parentCategory); ?></span>
                        <?php endif; ?>
                    </div>

                    <ul class="accessory-hero__stats" aria-label="Product summary">
                        <li class="accessory-hero__stat">
                            <span class="accessory-hero__stat-label">Category</span>
                            <strong class="accessory-hero__stat-value"><?php echo esc_html($type !== '' ? $type : '—'); ?></strong>
                        </li>
                        <li class="accessory-hero__stat">
                            <span class="accessory-hero__stat-label">Price</span>
                            <strong class="accessory-hero__stat-value"><?php echo esc_html($priceDisplay . ($currency !== '' ? ' ' . $currency : '')); ?></strong>
                        </li>
                        <li class="accessory-hero__stat">
                            <span class="accessory-hero__stat-label">Compatibility</span>
                            <strong class="accessory-hero__stat-value"><?php echo esc_html($compatCount === 0 ? '—' : sprintf(_n('%s helmet type', '%s helmet types', $compatCount, 'helmetsan-theme'), number_format_i18n($compatCount))); ?></strong>
                        </li>
                    </ul>
                </div>

                <div class="accessory-hero__media">
                    <div class="accessory-hero__image-wrap">
                        <?php if (has_post_thumbnail()) : ?>
                            <?php the_post_thumbnail('large', ['class' => 'accessory-hero__image', 'loading' => 'eager', 'decoding' => 'async']); ?>
                        <?php else : ?>
                            <div class="accessory-hero__image-placeholder" aria-hidden="true">
                                <svg class="accessory-hero__placeholder-icon" xmlns="http://www.w3.org/2000/svg" width="80" height="80" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/></svg>
                                <span class="accessory-hero__placeholder-text">Product image</span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </header>

            <section class="hs-stat-grid accessory-single__stat-cards" aria-label="Product details">
                <article class="hs-stat-card"><span>Type</span><strong><?php echo esc_html($type !== '' ? $type : '—'); ?></strong></article>
                <article class="hs-stat-card"><span>Price</span><strong><?php echo esc_html($priceDisplay . ($currency !== '' ? ' ' . $currency : '')); ?></strong></article>
                <article class="hs-stat-card"><span>Helmet Types</span><strong><?php echo esc_html($compatCount === 0 ? '—' : number_format_i18n($compatCount)); ?></strong></article>
            </section>

            <div class="hs-panel page-content accessory-single__content">
                <?php
                $accContent = get_the_content();
                if (trim(strip_tags($accContent)) !== '') :
                    the_content();
                else :
                    ?>
                    <p><?php the_title(); ?> is a <?php echo esc_html($type ?: 'accessory'); ?><?php echo $parentCategory ? ' in the ' . esc_html($parentCategory) . ' category' : ''; ?>. <?php echo $compatCount > 0 ? 'Compatible with ' . number_format_i18n($compatCount) . ' helmet type(s). ' : ''; ?>Check compatibility and pricing above, or <a href="<?php echo esc_url($accessoriesUrl); ?>">browse more accessories</a>.</p>
                <?php endif; ?>
            </div>

            <section class="hs-panel accessory-single__panel">
                <h2 class="accessory-single__panel-title">Product details</h2>
                <dl class="accessory-single__specs">
                    <div class="accessory-single__spec-row"><dt>Type</dt><dd><?php echo esc_html($type !== '' ? $type : '—'); ?></dd></div>
                    <div class="accessory-single__spec-row"><dt>Color</dt><dd><?php echo esc_html($color !== '' ? $color : '—'); ?></dd></div>
                    <div class="accessory-single__spec-row"><dt>Parent category</dt><dd><?php echo esc_html($parentCategory !== '' ? $parentCategory : '—'); ?></dd></div>
                    <div class="accessory-single__spec-row"><dt>Subcategory</dt><dd><?php echo esc_html($subcategory !== '' ? $subcategory : '—'); ?></dd></div>
                    <div class="accessory-single__spec-row"><dt>Youth / Adult</dt><dd><?php echo esc_html($youthAdult !== '' ? $youthAdult : '—'); ?></dd></div>
                    <div class="accessory-single__spec-row"><dt>Pinlock ready</dt><dd><?php echo esc_html($pinlockReady === '1' ? 'Yes' : ($pinlockReady !== '' ? 'No' : '—')); ?></dd></div>
                    <div class="accessory-single__spec-row"><dt>Electric compatible</dt><dd><?php echo esc_html($electricCompat === '1' ? 'Yes' : ($electricCompat !== '' ? 'No' : '—')); ?></dd></div>
                    <div class="accessory-single__spec-row"><dt>Snow compatible</dt><dd><?php echo esc_html($snowCompat === '1' ? 'Yes' : ($snowCompat !== '' ? 'No' : '—')); ?></dd></div>
                </dl>
            </section>

            <?php
            $affiliateLink = is_array($price) && isset($price['url']) ? $price['url'] : '';
            $plugin = function_exists('helmetsan_core') ? helmetsan_core() : null;
            $revenueService = $plugin && method_exists($plugin, 'revenue') ? $plugin->revenue() : null;
            $geoService = $plugin && method_exists($plugin, 'geo') ? $plugin->geo() : null;
            $visitorCountry = $geoService ? strtolower($geoService->getCountry()) : 'us';
            $visitorSuffix = ($visitorCountry === 'uk' || $visitorCountry === 'gb') ? 'uk' : $visitorCountry;
            $affiliateLinks = $revenueService ? $revenueService->getAffiliateLinks($id) : [];
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
            $geoMpId = $revenueService ? $revenueService->getGeoAmazonMarketplaceId(null) : 'amazon-us';
            $hasGeoRow = !empty($geoRelevantLinks) || $revenueService;
            $hasWhereToBuy = $affiliateLink !== '' || $hasGeoRow;
            $accessorySlug = get_post_field('post_name', $id);
            ?>
            <?php if ($hasWhereToBuy && $accessorySlug !== '') : ?>
                <section class="hs-panel hs-where-to-buy accessory-single__where" id="where-to-buy">
                    <h2 class="accessory-single__where-title">
                        <svg class="accessory-single__where-icon" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
                        Where to buy
                    </h2>
                    <div class="hs-table-wrap">
                        <table class="hs-table hs-price-table">
                            <thead>
                                <tr>
                                    <th>Retailer</th>
                                    <th>Price</th>
                                    <th>Availability</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($affiliateLink !== '') : ?>
                                    <tr>
                                        <td class="hs-price-table__merchant">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="21" r="1"></circle><circle cx="20" cy="21" r="1"></circle><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path></svg>
                                            <span class="hs-price-table__merchant-name">Official Partner</span>
                                        </td>
                                        <td><strong><?php echo esc_html($priceDisplay . ($currency !== '' ? ' ' . $currency : '')); ?></strong></td>
                                        <td><span class="accessory-single__avail">In stock</span></td>
                                        <td>
                                            <a href="<?php echo esc_url($affiliateLink); ?>" class="hs-price-cta" target="_blank" rel="nofollow noopener noreferrer sponsored">Buy now →</a>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                                <?php foreach ($geoRelevantLinks as $mpId => $entry) :
                                    $goUrl = home_url('/go/' . $accessorySlug . '/?marketplace=' . urlencode($mpId) . '&source=accessory_pdp');
                                    $mpLower = strtolower($mpId);
                                ?>
                                    <tr>
                                        <td class="hs-price-table__merchant">
                                            <?php if (str_contains($mpLower, 'amazon')) : ?>
                                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#FF9900" stroke-width="2"><path d="M4 17c2.5 2.5 6.5 3.5 10.5 1.5M16.5 17l1.5 1.5.5-2"></path></svg>
                                            <?php else : ?>
                                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="21" r="1"></circle><circle cx="20" cy="21" r="1"></circle><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path></svg>
                                            <?php endif; ?>
                                            <span class="hs-price-table__merchant-name"><?php echo esc_html(function_exists('helmetsan_marketplace_label') ? helmetsan_marketplace_label($mpId) : $mpId); ?></span>
                                        </td>
                                        <td><strong><span class="hs-muted">Check price</span></strong></td>
                                        <td><span class="accessory-single__avail">View on site</span></td>
                                        <td>
                                            <a href="<?php echo esc_url($goUrl); ?>" class="hs-price-cta" target="_blank" rel="noopener noreferrer sponsored">Buy now →</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php
                                if (empty($geoRelevantLinks) && $revenueService) :
                                    $goUrl = home_url('/go/' . $accessorySlug . '/?marketplace=' . urlencode($geoMpId) . '&source=accessory_pdp');
                                ?>
                                    <tr>
                                        <td class="hs-price-table__merchant">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#FF9900" stroke-width="2"><path d="M4 17c2.5 2.5 6.5 3.5 10.5 1.5M16.5 17l1.5 1.5.5-2"></path></svg>
                                            <span class="hs-price-table__merchant-name"><?php echo esc_html(function_exists('helmetsan_marketplace_label') ? helmetsan_marketplace_label($geoMpId) : $geoMpId); ?></span>
                                        </td>
                                        <td><strong><span class="hs-muted">Check price</span></strong></td>
                                        <td><span class="accessory-single__avail">View on site</span></td>
                                        <td>
                                            <a href="<?php echo esc_url($goUrl); ?>" class="hs-price-cta" target="_blank" rel="noopener noreferrer sponsored">Buy now →</a>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
            <?php endif; ?>

            <div class="hs-grid hs-grid--2">
                <section class="hs-panel accessory-single__panel">
                    <h2 class="accessory-single__panel-title">Compatible helmet types</h2>
                    <ul class="hs-list">
                        <?php if (!empty($helmetTypes)) : ?>
                            <?php foreach ($helmetTypes as $item) : ?>
                                <li><?php echo esc_html((string) $item); ?></li>
                            <?php endforeach; ?>
                        <?php else : ?>
                            <li class="hs-list__empty">—</li>
                        <?php endif; ?>
                    </ul>
                </section>
                <section class="hs-panel accessory-single__panel">
                    <h2 class="accessory-single__panel-title">Compatible brands</h2>
                    <ul class="hs-list">
                        <?php if (!empty($brands)) : ?>
                            <?php foreach ($brands as $item) : ?>
                                <li><?php echo esc_html((string) $item); ?></li>
                            <?php endforeach; ?>
                        <?php else : ?>
                            <li class="hs-list__empty">—</li>
                        <?php endif; ?>
                    </ul>
                </section>
            </div>

            <section class="hs-panel accessory-single__panel">
                <h2 class="accessory-single__panel-title">Compatible helmet families</h2>
                <ul class="hs-list">
                    <?php if (!empty($families)) : ?>
                        <?php foreach ($families as $item) : ?>
                            <li><?php echo esc_html((string) $item); ?></li>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <li class="hs-list__empty">—</li>
                    <?php endif; ?>
                </ul>
            </section>

            <section class="hs-panel accessory-single__panel">
                <h2 class="accessory-single__panel-title">Key features</h2>
                <ul class="hs-list">
                    <?php if (!empty($features)) : ?>
                        <?php foreach ($features as $item) : ?>
                            <li><?php echo esc_html((string) $item); ?></li>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <li class="hs-list__empty">—</li>
                    <?php endif; ?>
                </ul>
            </section>

            <?php if (!empty($categoryTerms)) : ?>
                <section class="hs-panel accessory-single__panel">
                    <h2 class="accessory-single__panel-title">Categories</h2>
                    <ul class="accessory-single__categories">
                        <?php foreach ($categoryTerms as $term) : ?>
                            <?php if ($term instanceof WP_Term) : ?>
                                <li><a href="<?php echo esc_url(get_term_link($term)); ?>" class="accessory-single__category-link"><?php echo esc_html($term->name); ?></a></li>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </ul>
                </section>
            <?php endif; ?>

            <?php
            $compatibleIds = (string) get_post_meta($id, 'compatible_helmet_ids_json', true);
            $helmetIdsArray = json_decode($compatibleIds, true);
            $helmetIdsArray = is_array($helmetIdsArray) ? $helmetIdsArray : [];
            $hasCompatibleHelmets = !empty($helmetIdsArray);
            $helmetsQuery = $hasCompatibleHelmets ? new WP_Query([
                'post_type' => 'helmet',
                'post_name__in' => $helmetIdsArray,
                'posts_per_page' => -1,
            ]) : null;
            $showHelmetCards = $helmetsQuery && $helmetsQuery->have_posts();
            ?>
            <section class="hs-section accessory-single__helmets">
                <h2 class="accessory-single__panel-title">Compatible helmets</h2>
                <?php if ($showHelmetCards) : ?>
                    <div class="helmet-grid">
                        <?php
                        while ($helmetsQuery->have_posts()) {
                            $helmetsQuery->the_post();
                            get_template_part('template-parts/helmet', 'card');
                        }
                        wp_reset_postdata();
                        ?>
                    </div>
                <?php else : ?>
                    <p class="accessory-single__empty-state">No specific compatible helmets linked. Use compatible helmet types and brands above to find suitable helmets.</p>
                <?php endif; ?>
            </section>

        </article>
        <?php
    }
}

get_footer();
