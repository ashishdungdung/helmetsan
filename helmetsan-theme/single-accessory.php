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
        <article <?php post_class('hs-section'); ?>>
            <header class="hs-section__head">
                <p class="hs-eyebrow">Accessory</p>
                <h1><?php the_title(); ?></h1>
            </header>

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

