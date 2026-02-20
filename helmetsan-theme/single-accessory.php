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

            <!-- ═══ Where to Buy (Accessories) ═══ -->
            <?php
            // Extract the simple affiliate link from the JSON if available, or build a generic one
            $affiliateLink = '';
            if (isset($price['url'])) {
                $affiliateLink = $price['url'];
            }
            ?>
            <?php if ($affiliateLink !== '') : ?>
                <section class="hs-panel hs-where-to-buy">
                    <h2>🛒 Where to Buy</h2>
                    <div class="hs-table-wrap">
                        <table class="hs-table hs-price-table">
                            <thead>
                                <tr>
                                    <th>Retailer</th>
                                    <th>Price</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><strong>Official Partner</strong></td>
                                    <td><strong><?php echo esc_html((string) ($price['current'] ?? 'N/A') . ' ' . (string) ($price['currency'] ?? '')); ?></strong></td>
                                    <td>
                                        <a href="<?php echo esc_url($affiliateLink); ?>" target="_blank" rel="nofollow noopener" class="hs-btn hs-btn--sm">Buy Now &rarr;</a>
                                    </td>
                                </tr>
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

