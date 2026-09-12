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
        $accessoriesUrl = helmetsan_url('/accessories/');
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

                    <?php
                    $accSlug = get_post_field('post_name', $id);
                    $accAmazonUrl = $accSlug !== '' ? home_url('/go/' . $accSlug . '/?marketplace=amazon&source=accessory_hero') : '#where-to-buy';
                    ?>
                    <div class="accessory-hero__cta" style="margin-top: 1.25rem; display: flex; gap: 0.75rem; flex-wrap: wrap;">
                        <a href="<?php echo esc_url($accAmazonUrl); ?>" class="hs-btn hs-btn--amazon hs-price-cta" target="_blank" rel="noopener noreferrer sponsored" style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); color: #111827; font-weight: 800; display: inline-flex; align-items: center; gap: 0.5rem; border: none; border-radius: var(--hs-radius-md, 8px); padding: 0.75rem 1.25rem; text-decoration: none; box-shadow: 0 4px 12px rgba(245, 158, 11, 0.3);">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="8" cy="21" r="1"/><circle cx="19" cy="21" r="1"/><path d="M2.05 2.05h2l2.66 12.42a2 2 0 0 0 2 1.58h9.78a2 2 0 0 0 1.95-1.57l1.65-7.43H5.12"/></svg>
                            <?php esc_html_e('Buy on Amazon →', 'helmetsan-theme'); ?>
                        </a>
                        <a href="#where-to-buy" class="hs-btn hs-btn--secondary" style="display: inline-flex; align-items: center; padding: 0.75rem 1rem;">
                            <?php esc_html_e('View Stores', 'helmetsan-theme'); ?>
                        </a>
                    </div>
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
                    $specsList = [];
                    if ($type !== '') {
                        $specsList[] = '<strong>' . esc_html__('Product Category:', 'helmetsan-theme') . '</strong> ' . esc_html($type);
                    }
                    if ($parentCategory !== '') {
                        $specsList[] = '<strong>' . esc_html__('Parent Classification:', 'helmetsan-theme') . '</strong> ' . esc_html($parentCategory);
                    }
                    if ($subcategory !== '') {
                        $specsList[] = '<strong>' . esc_html__('Subcategory:', 'helmetsan-theme') . '</strong> ' . esc_html($subcategory);
                    }
                    if ($color !== '') {
                        $specsList[] = '<strong>' . esc_html__('Color/Finish:', 'helmetsan-theme') . '</strong> ' . esc_html($color);
                    }
                    if ($youthAdult !== '') {
                        $specsList[] = '<strong>' . esc_html__('Sizing Group:', 'helmetsan-theme') . '</strong> ' . esc_html(ucfirst($youthAdult));
                    }
                    if ($pinlockReady === '1') {
                        $specsList[] = '<strong>' . esc_html__('Pinlock Integration:', 'helmetsan-theme') . '</strong> ' . esc_html__('Yes (Anti-fog ready)', 'helmetsan-theme');
                    }
                    if ($electricCompat === '1') {
                        $specsList[] = '<strong>' . esc_html__('Electric Heating Compatibility:', 'helmetsan-theme') . '</strong> ' . esc_html__('Yes', 'helmetsan-theme');
                    }
                    if ($snowCompat === '1') {
                        $specsList[] = '<strong>' . esc_html__('Snowmobile/Winter Use:', 'helmetsan-theme') . '</strong> ' . esc_html__('Yes', 'helmetsan-theme');
                    }
                    if ($compatCount > 0) {
                        $specsList[] = '<strong>' . esc_html__('Helmet Compatibility:', 'helmetsan-theme') . '</strong> ' . sprintf(_n('Verified compatible with %s helmet type', 'Verified compatible with %s helmet types', $compatCount, 'helmetsan-theme'), number_format_i18n($compatCount));
                    }
                    
                    if (!empty($specsList)) : ?>
                        <p><?php printf(esc_html__('Overview and specifications for the %s based on verified manufacturer details:', 'helmetsan-theme'), esc_html(get_the_title())); ?></p>
                        <ul class="hs-specs-list" style="margin-left: 20px; list-style-type: disc;">
                            <?php foreach ($specsList as $spec) : ?>
                                <li style="margin-bottom: 5px;"><?php echo $spec; ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else : ?>
                        <p><?php printf(esc_html__('Detailed specifications and technical profile details for the %s are available in the tables below.', 'helmetsan-theme'), esc_html(get_the_title())); ?></p>
                    <?php endif;
                endif; ?>
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
                    <div class="hs-price-comparer__alert-banner" style="border-radius: var(--hs-radius-lg) var(--hs-radius-lg) 0 0; margin: -1.5rem -1.5rem 1.5rem -1.5rem;">
                        <div class="hs-price-comparer__alert-text">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                            <span><?php esc_html_e('Real-time price tracking active across international retailers.', 'helmetsan-theme'); ?></span>
                            <span class="hs-price-comparer__badge"><?php esc_html_e('Good Buy', 'helmetsan-theme'); ?></span>
                        </div>
                        <button type="button" class="hs-price-comparer__alert-btn" id="hsPriceAlertTrigger" data-helmet-id="<?php echo esc_attr((string) $id); ?>">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                            <?php esc_html_e('Set Drop Alert', 'helmetsan-theme'); ?>
                        </button>
                    </div>
                    <h2 class="accessory-single__where-title" style="margin-top: var(--hs-sp-2);">
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
            <?php get_template_part('template-parts/helmet-reviews', null, ['helmet_id' => get_the_ID()]); ?>

            <section class="hs-compat-carousel-section accessory-single__helmets">
                <h2 class="hs-section-icon-title" style="margin-bottom: var(--hs-sp-4);">
                    <span class="hs-section-icon-title__icon" aria-hidden="true">
                        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
                    </span>
                    <?php esc_html_e('Compatible Helmets', 'helmetsan-theme'); ?>
                </h2>
                <?php if ($showHelmetCards) : ?>
                    <div class="hs-compat-carousel-wrap">
                        <div class="hs-compat-carousel">
                            <?php
                            while ($helmetsQuery->have_posts()) {
                                $helmetsQuery->the_post();
                                $helmetId = get_the_ID();
                                $brandName = helmetsan_get_brand_name($helmetId);
                                $priceStr = helmetsan_get_helmet_price($helmetId);
                                $thumbUrl = get_the_post_thumbnail_url($helmetId, 'medium');
                                $link = get_permalink($helmetId);
                            ?>
                                <div class="hs-compat-carousel__slide">
                                    <article class="hs-compat-card">
                                        <div class="hs-compat-card__img-box">
                                            <?php if ($thumbUrl) : ?>
                                                <img src="<?php echo esc_url($thumbUrl); ?>" alt="<?php echo esc_attr(get_the_title()); ?>" loading="lazy">
                                            <?php else : ?>
                                                <svg class="hs-compat-card__placeholder-icon" xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
                                            <?php endif; ?>
                                        </div>
                                        <div class="hs-compat-card__content">
                                            <?php if ($brandName !== '') : ?>
                                                <span class="hs-compat-card__tag"><?php echo esc_html($brandName); ?></span>
                                            <?php endif; ?>
                                            <h3 class="hs-compat-card__title">
                                                <a href="<?php echo esc_url($link); ?>" style="color: inherit; text-decoration: none;"><?php the_title(); ?></a>
                                            </h3>
                                            <div class="hs-compat-card__price-row">
                                                <span class="hs-compat-card__price"><?php echo esc_html($priceStr); ?></span>
                                                <a href="<?php echo esc_url($link); ?>" class="hs-compat-card__btn">
                                                    <?php esc_html_e('View', 'helmetsan-theme'); ?> &rarr;
                                                </a>
                                            </div>
                                        </div>
                                    </article>
                                </div>
                            <?php
                            }
                            wp_reset_postdata();
                            ?>
                        </div>
                    </div>
                <?php else : ?>
                    <p class="accessory-single__empty-state"><?php esc_html_e('No specific compatible helmets linked. Use compatible types and brands above to filter.', 'helmetsan-theme'); ?></p>
                <?php endif; ?>
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
    }
}

get_footer();
