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

            <section class="hs-stat-grid">
                <article class="hs-stat-card"><span>Type</span><strong><?php echo esc_html($type !== '' ? $type : 'N/A'); ?></strong></article>
                <article class="hs-stat-card"><span>Price</span><strong><?php echo esc_html((string) ($price['current'] ?? 'N/A') . ' ' . (string) ($price['currency'] ?? '')); ?></strong></article>
                <article class="hs-stat-card"><span>Helmet Types</span><strong><?php echo esc_html(is_array($helmetTypes) ? (string) count($helmetTypes) : '0'); ?></strong></article>
            </section>

            <div class="hs-panel"><?php the_content(); ?></div>

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
        </article>
        <?php
    }
}

get_footer();

