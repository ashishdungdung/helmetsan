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
        $priceJson = (string) get_post_meta($id, 'price_json', true);
        $helmetTypesJson = (string) get_post_meta($id, 'compatible_helmet_types_json', true);
        $brandsJson = (string) get_post_meta($id, 'compatible_brands_json', true);
        $featuresJson = (string) get_post_meta($id, 'accessory_features_json', true);

        $price = json_decode($priceJson, true);
        $helmetTypes = json_decode($helmetTypesJson, true);
        $brands = json_decode($brandsJson, true);
        $features = json_decode($featuresJson, true);
        ?>
        <article <?php post_class('accessory-single'); ?>>
            <header class="accessory-hero hs-section">
                <div class="accessory-hero__info">
                    <p class="hs-eyebrow">Accessory</p>
                    <h1 class="accessory-hero__title"><?php the_title(); ?></h1>
                    
                    <section class="hs-stat-grid" style="margin-top:2rem">
                        <article class="hs-stat-card"><span>Type</span><strong><?php echo esc_html($type !== '' ? $type : 'N/A'); ?></strong></article>
                        <article class="hs-stat-card"><span>Price</span><strong><?php echo esc_html((string) ($price['current'] ?? 'N/A') . ' ' . (string) ($price['currency'] ?? '')); ?></strong></article>
                        <article class="hs-stat-card"><span>Compatible Elements</span><strong><?php echo esc_html(is_array($helmetTypes) ? (string) count($helmetTypes) : '0'); ?></strong></article>
                    </section>
                </div>
                
                <div class="accessory-hero__image hs-panel" style="padding: 0; overflow: hidden; display: flex; align-items: center; justify-content: center; background: white;">
                    <?php if (has_post_thumbnail()) : ?>
                        <?php the_post_thumbnail('large', ['style' => 'max-width: 100%; height: auto;']); ?>
                    <?php else: ?>
                        <svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="#ddd" stroke-width="1"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path><polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline><line x1="12" y1="22.08" x2="12" y2="12"></line></svg>
                    <?php endif; ?>
                </div>
            </header>
            
            <style>
            .accessory-hero { display: grid; grid-template-columns: 1fr; gap: 2rem; }
            @media(min-width: 768px) { .accessory-hero { grid-template-columns: 1fr 1fr; align-items: center; } }
            .accessory-hero__title { font-size: 2.5rem; margin-bottom: 1rem; }
            </style>

            <section class="hs-stat-grid" style="margin-top: 2rem; margin-bottom: 2rem;">
                <article class="hs-stat-card"><span>Type</span><strong><?php echo esc_html($type !== '' ? $type : 'N/A'); ?></strong></article>
                <article class="hs-stat-card"><span>Price</span><strong><?php echo esc_html((string) ($price['current'] ?? 'N/A') . ' ' . (string) ($price['currency'] ?? '')); ?></strong></article>
                <article class="hs-stat-card"><span>Helmet Types</span><strong><?php echo esc_html(is_array($helmetTypes) ? (string) count($helmetTypes) : '0'); ?></strong></article>
            </section>

            <div class="hs-panel page-content"><?php the_content(); ?></div>

            <!-- ═══ Where to Buy (Accessories) — geo-driven, same as helmets ═══ -->
            <?php
            $affiliateLink = isset($price['url']) ? $price['url'] : '';
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
                <section class="hs-panel hs-where-to-buy" id="where-to-buy">
                    <h2>🛒 Where to Buy</h2>
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
                                <?php if ($affiliateLink !== '') : ?>
                                    <tr>
                                        <td class="hs-price-table__merchant">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="21" r="1"></circle><circle cx="20" cy="21" r="1"></circle><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path></svg>
                                            <span class="hs-price-table__merchant-name">Official Partner</span>
                                        </td>
                                        <td><strong><?php echo esc_html((string) ($price['current'] ?? 'N/A') . ' ' . (string) ($price['currency'] ?? '')); ?></strong></td>
                                        <td><span style="color: var(--hs-success, #059669);">● View on site</span></td>
                                        <td>
                                            <a href="<?php echo esc_url($affiliateLink); ?>" class="hs-price-cta" target="_blank" rel="nofollow noopener noreferrer sponsored">Buy Now →</a>
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
                                        <td><span style="color: var(--hs-success, #059669);">● View on site</span></td>
                                        <td>
                                            <a href="<?php echo esc_url($goUrl); ?>" class="hs-price-cta" target="_blank" rel="noopener noreferrer sponsored">Buy Now →</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php
                                // Geo fallback: show regional Amazon row when no stored links for visitor
                                if (empty($geoRelevantLinks) && $revenueService) :
                                    $goUrl = home_url('/go/' . $accessorySlug . '/?marketplace=' . urlencode($geoMpId) . '&source=accessory_pdp');
                                ?>
                                    <tr>
                                        <td class="hs-price-table__merchant">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#FF9900" stroke-width="2"><path d="M4 17c2.5 2.5 6.5 3.5 10.5 1.5M16.5 17l1.5 1.5.5-2"></path></svg>
                                            <span class="hs-price-table__merchant-name"><?php echo esc_html(function_exists('helmetsan_marketplace_label') ? helmetsan_marketplace_label($geoMpId) : $geoMpId); ?></span>
                                        </td>
                                        <td><strong><span class="hs-muted">Check price</span></strong></td>
                                        <td><span style="color: var(--hs-success, #059669);">● View on site</span></td>
                                        <td>
                                            <a href="<?php echo esc_url($goUrl); ?>" class="hs-price-cta" target="_blank" rel="noopener noreferrer sponsored">Buy Now →</a>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
            <?php endif; ?>

            <div class="hs-grid hs-grid--2">
                <section class="hs-panel">
                    <h2>Compatible Helmet Types</h2>
                    <ul class="hs-list">
                        <?php if (is_array($helmetTypes)) : foreach ($helmetTypes as $item) : ?>
                            <li><?php echo esc_html((string) $item); ?></li>
                        <?php endforeach; endif; ?>
                    </ul>
                </section>
                <section class="hs-panel">
                    <h2>Compatible Brands</h2>
                    <ul class="hs-list">
                        <?php if (is_array($brands)) : foreach ($brands as $item) : ?>
                            <li><?php echo esc_html((string) $item); ?></li>
                        <?php endforeach; endif; ?>
                    </ul>
                </section>
            </div>

            <section class="hs-panel">
                <h2>Key Features</h2>
                <ul class="hs-list">
                    <?php if (is_array($features)) : foreach ($features as $item) : ?>
                        <li><?php echo esc_html((string) $item); ?></li>
                    <?php endforeach; endif; ?>
                </ul>
            </section>

            <!-- ═══ Compatible Helmets ═══ -->
            <?php
            $compatibleIds = (string) get_post_meta($id, 'compatible_helmet_ids_json', true);
            $helmetIdsArray = json_decode($compatibleIds, true);
            
            if (is_array($helmetIdsArray) && !empty($helmetIdsArray)) {
                // Query these exact post names (slugs)
                $helmetsQuery = new WP_Query([
                    'post_type' => 'helmet',
                    'post_name__in' => $helmetIdsArray,
                    'posts_per_page' => -1,
                ]);

                if ($helmetsQuery->have_posts()) : ?>
                    <section class="hs-section" style="margin-top: 4rem;">
                        <h2 class="hs-section__title" style="margin-bottom: 2rem;">Compatible Helmets</h2>
                        <div class="helmet-grid">
                            <?php 
                            while ($helmetsQuery->have_posts()) {
                                $helmetsQuery->the_post();
                                get_template_part('template-parts/helmet', 'card');
                            }
                            wp_reset_postdata();
                            ?>
                        </div>
                    </section>
                <?php endif;
            }
            ?>

        </article>
        <?php
    }
}

get_footer();

