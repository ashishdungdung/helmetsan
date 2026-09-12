<?php
/**
 * Distributor CPT archive template.
 *
 * @package HelmetsanTheme
 */

get_header();

// Fetch active brands for B2B overview
$brandsQuery = new WP_Query([
    'post_type'      => 'brand',
    'post_status'    => 'publish',
    'posts_per_page' => -1,
    'orderby'        => 'title',
    'order'          => 'ASC',
]);
?>
<section class="hs-section hs-section--archive" style="max-width: var(--hs-max-width, 1200px); margin: 0 auto; padding: var(--hs-sp-10) var(--hs-sp-4);">
    <header class="hs-section__head" style="margin-bottom: var(--hs-sp-8);">
        <p class="hs-eyebrow">Supply Chain Network</p>
        <h1 style="font-size: var(--hs-fs-3xl); font-weight: 700; color: var(--hs-text); margin: 0 0 var(--hs-sp-2) 0;"><?php echo esc_html(post_type_archive_title('', false)); ?></h1>
        <p class="hs-text-muted" style="font-size: 1.1rem; max-width: 600px;">Authorized wholesale importers, regional distributors, and logistic channels managing global inventory supply.</p>
    </header>

    <?php if (have_posts()) : ?>
        <div class="hs-distributors-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: var(--hs-sp-6);">
            <?php while (have_posts()) : the_post(); 
                $id = get_the_ID();
                $type = get_post_meta($id, 'distributor_type', true);
                $website = get_post_meta($id, 'distributor_website', true);
                $phone = get_post_meta($id, 'distributor_phone', true);
                $email = get_post_meta($id, 'distributor_email', true);
                $warehouses = json_decode((string) get_post_meta($id, 'distributor_warehouses_json', true), true) ?: [];
                $brands = json_decode((string) get_post_meta($id, 'distributor_brands_json', true), true) ?: [];
                
                $typeLabel = $type === 'exclusive' ? 'Exclusive Importer' : ($type === 'regional' ? 'Regional Distributor' : 'Authorized Distributor');
                $typeClass = $type === 'exclusive' ? 'hs-badge--error' : 'hs-badge--info';
            ?>
                <article class="hs-panel hs-distributor-card" style="display: flex; flex-direction: column; justify-content: space-between; border-radius: var(--hs-radius-lg); background: var(--hs-bg-elevated); padding: var(--hs-sp-6); border: 1px solid var(--hs-border); transition: all 0.3s ease; box-shadow: var(--hs-shadow-sm);">
                    <div class="hs-distributor-card__header" style="margin-bottom: var(--hs-sp-4);">
                        <h3 style="margin: 0 0 var(--hs-sp-2) 0; font-size: 1.25rem; color: var(--hs-text);"><?php the_title(); ?></h3>
                        <span class="hs-badge <?php echo $typeClass; ?>"><?php echo esc_html($typeLabel); ?></span>
                    </div>
                    
                    <div class="hs-distributor-card__body" style="flex: 1; margin-bottom: var(--hs-sp-6);">
                        <?php if (!empty($brands)) : ?>
                            <p style="margin: 0 0 var(--hs-sp-2) 0; font-size: 0.9rem; color: var(--hs-text);">
                                <strong>Brands Stocked:</strong> 
                                <?php echo esc_html(implode(', ', $brands)); ?>
                            </p>
                        <?php endif; ?>

                        <?php if (!empty($warehouses)) : ?>
                            <p style="margin: 0 0 var(--hs-sp-2) 0; font-size: 0.9rem; color: var(--hs-text-soft);">
                                <strong>Warehouses:</strong> 
                                <?php echo esc_html(implode(', ', array_map(fn($w) => $w['city'] . ' (' . $w['country'] . ')', $warehouses))); ?>
                            </p>
                        <?php endif; ?>
                    </div>
                    
                    <div class="hs-distributor-card__footer" style="display: flex; gap: var(--hs-sp-3); width: 100%;">
                        <?php if ($phone) : ?>
                            <a href="tel:<?php echo esc_attr($phone); ?>" class="hs-btn hs-btn--sm hs-btn--ghost" style="flex: 1; text-align: center;">📞 Call</a>
                        <?php endif; ?>
                        <?php if ($website) : ?>
                            <a href="<?php echo esc_url($website); ?>" class="hs-btn hs-btn--sm hs-btn--primary" style="flex: 1; text-align: center;" target="_blank" rel="noopener noreferrer">🌐 Website</a>
                        <?php endif; ?>
                    </div>
                </article>
            <?php endwhile; ?>
        </div>
    <?php else : ?>
        <p>No distributors found.</p>
    <?php endif; ?>
</section>
<?php
get_footer();
