<?php
/**
 * Single brand template.
 *
 * @package HelmetsanTheme
 */

get_header();

if (have_posts()) {
    while (have_posts()) {
        the_post();

        $brandId = get_the_ID();
        $origin = (string) get_post_meta($brandId, 'brand_origin_country', true);
        $founded = (string) get_post_meta($brandId, 'brand_founded_year', true);
        $warranty = (string) get_post_meta($brandId, 'brand_warranty_terms', true);
        $supportUrl = (string) get_post_meta($brandId, 'brand_support_url', true);
        $supportEmail = (string) get_post_meta($brandId, 'brand_support_email', true);
        $website = (string) get_post_meta($brandId, 'brand_website_url', true);
        $logoUrl = helmetsan_get_logo_url($brandId);
        $totalModels = (string) get_post_meta($brandId, 'brand_total_models', true);
        $motto = (string) get_post_meta($brandId, 'brand_motto', true);
        $ethos = (string) get_post_meta($brandId, 'brand_manufacturing_ethos', true);
        
        $brandSlug = get_post_field('post_name', $brandId);

        $metaIds = get_posts([
            'post_type' => 'helmet',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'meta_query' => [['key' => 'rel_brand', 'value' => $brandId]],
            'fields' => 'ids',
        ]);

        $taxIds = get_posts([
            'post_type' => 'helmet',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'tax_query' => [['taxonomy' => 'helmet_brand', 'field' => 'slug', 'terms' => $brandSlug]],
            'fields' => 'ids',
        ]);

        $uniqueIds = array_unique(array_merge($metaIds, $taxIds));
        $helmets = [];

        if (!empty($uniqueIds)) {
            $helmets = get_posts([
                'post_type' => 'helmet',
                'post_status' => 'publish',
                'posts_per_page' => -1,
                'post__in' => $uniqueIds,
                'orderby' => 'title',
                'order' => 'ASC',
            ]);
        }

        // Cache Heavy Computations 
        $transientKey = 'hs_brand_stats_v2_' . $brandId;
        $stats = get_transient($transientKey);
        
        if (false === $stats || defined('WP_DEBUG') && WP_DEBUG) {
            $stats = [
                'min_price' => INF,
                'max_price' => 0,
                'weight_sum' => 0,
                'weight_count' => 0,
                'certs' => [],
            ];
            
            foreach ($helmets as $helmet) {
                // Ignore variants for aggregation
                if ($helmet->post_parent > 0) continue;

                $price = (float) get_post_meta($helmet->ID, 'price_retail_usd', true);
                if ($price > 0) {
                    $stats['min_price'] = min($stats['min_price'], $price);
                    $stats['max_price'] = max($stats['max_price'], $price);
                }

                $weight = (int) get_post_meta($helmet->ID, 'spec_weight_g', true);
                if ($weight > 0) {
                    $stats['weight_sum'] += $weight;
                    $stats['weight_count']++;
                }

                $certs = wp_get_post_terms($helmet->ID, 'certification', ['fields' => 'names']);
                if (!is_wp_error($certs)) {
                    foreach ($certs as $cert) {
                        $stats['certs'][$cert] = true;
                    }
                }
            }
            $stats['certs'] = array_keys($stats['certs']);
            sort($stats['certs']);
            
            if ($stats['min_price'] === INF) {
                $stats['min_price'] = 0;
            }
            
            set_transient($transientKey, $stats, 12 * HOUR_IN_SECONDS);
        }

        $avgWeight = $stats['weight_count'] > 0 ? round($stats['weight_sum'] / $stats['weight_count']) : 0;

        // Group helmets by type
        $groupedHelmets = [];
        $uncategorized = [];
        $featuredHelmets = [];

        foreach ($helmets as $helmet) {
            if ($helmet->post_parent > 0) continue; 

            // Featured simple logic: highest priced ones first
            $featuredHelmets[] = $helmet;

            $terms = get_the_terms($helmet->ID, 'helmet_type');
            if ($terms && !is_wp_error($terms)) {
                foreach ($terms as $term) {
                    $groupedHelmets[$term->name][] = $helmet;
                }
            } else {
                $uncategorized[] = $helmet;
            }
        }
        
        if (!empty($uncategorized)) {
            $groupedHelmets['Other Models'] = $uncategorized;
        }

        ksort($groupedHelmets);
        
        // Grab top 4 helmets for Featured Section (by ID descending for "newest" roughly)
        usort($featuredHelmets, function ($a, $b) {
            return $b->ID <=> $a->ID;
        });
        $featuredSlice = array_slice($featuredHelmets, 0, 4);
        ?>
        
        <article <?php post_class('brand-hub'); ?>>
            
            <!-- 1. Immersive Hero -->
            <header class="brand-hub__hero brand-hero-card">
                <div class="brand-hero-card__bg"></div>
                <div class="brand-hub__hero-content brand-hero-card__content">
                    <?php if ($logoUrl !== '') : ?>
                        <div class="brand-hub__logo brand-hero-card__logo">
                            <img src="<?php echo esc_url($logoUrl); ?>" alt="<?php the_title_attribute(); ?>" />
                        </div>
                    <?php else : ?>
                         <h1 class="brand-hub__title brand-hero-card__title"><?php the_title(); ?></h1>
                    <?php endif; ?>
                    
                    <div class="brand-hub__meta brand-hero-card__meta">
                        <?php if ($founded) : ?><span>Est. <?php echo esc_html($founded); ?></span><?php endif; ?>
                        <?php if ($origin) : ?><span class="dot-separator">•</span><span><?php echo esc_html($origin); ?></span><?php endif; ?>
                    </div>

                    <?php if ($motto) : ?>
                        <p class="brand-hub__motto brand-hero-card__motto">"<?php echo esc_html($motto); ?>"</p>
                    <?php endif; ?>
                </div>
            </header>

            <!-- 2. Key Stats & Quick Stats (Glassmorphism) -->
            <section class="brand-stats-glass">
                <div class="brand-stats-glass__grid">
                    <article class="brand-stats-glass__item">
                        <span>Warranty</span>
                        <strong><?php echo esc_html($warranty !== '' ? $warranty : 'Avg. Standard'); ?></strong>
                    </article>
                    <article class="brand-stats-glass__item">
                        <span>Models Tracked</span>
                        <strong><?php echo esc_html($totalModels !== '' ? $totalModels : count($helmets)); ?></strong>
                    </article>
                    <article class="brand-stats-glass__item">
                        <span>Avg. Weight</span>
                        <strong><?php echo $avgWeight > 0 ? esc_html($avgWeight) . 'g' : 'N/A'; ?></strong>
                    </article>
                    <article class="brand-stats-glass__item">
                        <span>Price Range</span>
                        <strong><?php echo $stats['max_price'] > 0 ? '$' . $stats['min_price'] . ' – $' . $stats['max_price'] : 'Various'; ?></strong>
                    </article>
                </div>
            </section>

            <!-- 3. Story & Ethos (Dual Column) -->
            <div class="brand-hub__layout hs-section" style="margin-top: 3rem;">
                <div class="brand-hub__main hs-panel brand-about-panel">
                    <h2 id="about-brand" class="brand-about-title">The <?php the_title(); ?> Story</h2>
                    <div class="brand-hub__story-content brand-story-text">
                        <?php the_content(); ?>
                        <?php
                        $brandContent = get_the_content();
                        if (trim(strip_tags($brandContent)) === '' && $ethos === '') :
                            $helmetsLink = add_query_arg('brand_slug', $brandSlug, get_post_type_archive_link('helmet'));
                            if (! $helmetsLink) {
                                $helmetsLink = home_url('/helmets/?brand_slug=' . $brandSlug);
                            }
                        ?>
                            <p><?php the_title(); ?> is a recognized helmet manufacturer<?php echo $origin ? ' originating from ' . esc_html($origin) : ''; ?><?php echo $founded ? ' (established in ' . esc_html($founded) . ')' : ''; ?>. We track their complete lineup of models across the globe. Compare their technical specifications, safety certifications, and find the best prices using our advanced comparison tool.</p>
                            <p><a href="<?php echo esc_url($helmetsLink); ?>" class="hs-btn hs-btn--primary">Browse <?php the_title(); ?> lineup</a></p>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($ethos) : ?>
                        <div class="brand-hub__ethos">
                            <h3>Manufacturing Philosophy</h3>
                            <blockquote class="brand-ethos-quote"><?php echo wpautop(esc_html($ethos)); ?></blockquote>
                        </div>
                    <?php endif; ?>
                </div>
                
                <aside class="brand-hub__sidebar hs-panel brand-sidebar-panel">
                    <h3>Quick Facts</h3>
                    <ul class="hs-list brand-facts-list">
                        <li><strong>Founded:</strong> <span><?php echo esc_html($founded ?: 'Unknown'); ?></span></li>
                        <li><strong>Headquarters:</strong> <span><?php echo esc_html($origin ?: 'Global'); ?></span></li>
                        <?php if ($website) : ?>
                            <li><strong>Official Site:</strong> <a href="<?php echo esc_url($website); ?>" target="_blank" rel="noopener">Visit Website</a></li>
                        <?php endif; ?>
                        <li><strong>Support:</strong> 
                            <span><?php echo $supportEmail ? '<a href="mailto:'.esc_attr($supportEmail).'">Email</a>' : ($supportUrl ? '<a href="'.esc_url($supportUrl).'" target="_blank">Online Portal</a>' : 'N/A'); ?></span>
                        </li>
                    </ul>

                    <?php
                    $helmetsArchiveWithBrand = add_query_arg('brand_slug', $brandSlug, get_post_type_archive_link('helmet'));
                    if (! $helmetsArchiveWithBrand) {
                        $helmetsArchiveWithBrand = home_url('/helmets/?brand_slug=' . $brandSlug);
                    }
                    ?>
                    <div class="brand-hub__cta-group">
                        <a href="<?php echo esc_url($helmetsArchiveWithBrand); ?>" class="hs-btn hs-btn--primary hs-btn--block">Browse <?php the_title(); ?> Helmets</a>
                        <?php if ($supportUrl) : ?>
                            <a href="<?php echo esc_url($supportUrl); ?>" class="hs-btn hs-btn--ghost hs-btn--block" target="_blank" rel="noopener">Official Support</a>
                        <?php endif; ?>
                    </div>
                </aside>
            </div>

            <!-- 4. Global Safety Standards -->
            <?php if (!empty($stats['certs'])) : ?>
            <section class="brand-hub__standards hs-section">
                <div class="hs-panel brand-standards-panel">
                    <div class="brand-standards-header">
                        <h2>Safety Standards Attained</h2>
                        <p>Models within this brand's lineup have been certified to meet the following international safety protocols.</p>
                    </div>
                    <div class="brand-standards-grid">
                        <?php foreach($stats['certs'] as $certName): ?>
                            <span class="brand-cert-badge"><?php echo esc_html($certName); ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>
            <?php endif; ?>

            <!-- 5. Brand Spotlight Video -->
            <?php 
            $brandVideoUrl = helmetsan_core()->mediaService()->getBrandVideo($brandId);
            if ($brandVideoUrl) : 
                $embedCode = helmetsan_core()->mediaService()->getEmbedCode($brandVideoUrl);
            ?>
                <section class="brand-hub__spotlight hs-section">
                    <div class="brand-video-container hs-panel">
                        <div class="hs-responsive-embed">
                            <?php echo $embedCode; ?>
                        </div>
                    </div>
                </section>
            <?php endif; ?>

            <!-- 6. Featured Models -->
            <?php if (!empty($featuredSlice)) : ?>
            <section class="brand-hub__featured hs-section">
                <div class="hs-section__head">
                    <h2>Featured <?php the_title(); ?> Helmets</h2>
                </div>
                <div class="helmet-grid brand-featured-grid">
                    <?php 
                    foreach ($featuredSlice as $post) {
                        setup_postdata($post);
                        get_template_part('template-parts/helmet', 'card');
                    }
                    wp_reset_postdata();
                    ?>
                </div>
            </section>
            <?php endif; ?>

            <?php if (!empty($groupedHelmets)) : ?>
                <!-- 7. Category Discovery Cards -->
                <section class="brand-hub__discovery hs-section">
                    <div class="hs-section__head">
                        <h2>Explore The Lineup</h2>
                    </div>
                    <div class="link-card-grid brand-categories-grid">
                        <?php foreach ($groupedHelmets as $catName => $catHelmets) : 
                            $firstHelmet = $catHelmets[0];
                            $thumbUrl = get_the_post_thumbnail_url($firstHelmet->ID, 'medium');
                        ?>
                            <a href="#cat-<?php echo sanitize_title($catName); ?>" class="link-card brand-cat-card">
                                <div class="link-card__image">
                                    <?php if ($thumbUrl) : ?>
                                        <img src="<?php echo esc_url($thumbUrl); ?>" alt="<?php echo esc_attr($catName); ?>" loading="lazy">
                                    <?php endif; ?>
                                </div>
                                <div class="link-card__content">
                                    <h3><?php echo esc_html($catName); ?></h3>
                                    <span><?php echo count($catHelmets); ?> Models</span>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </section>

                <!-- 8. Full Catalog per Category -->
                <section class="brand-hub__catalog">
                    <?php foreach ($groupedHelmets as $categoryName => $categoryHelmets) : 
                        $limit = 4;
                        $total = count($categoryHelmets);
                        $displayHelmets = array_slice($categoryHelmets, 0, $limit);
                        $hasMore = $total > $limit;
                        $catSlug = sanitize_title($categoryName);
                        
                        $viewAllUrl = home_url('/helmets/');
                        $term = get_term_by('name', $categoryName, 'helmet_type');
                        if ($term) {
                            $viewAllUrl = add_query_arg([
                                'brand_slug' => $brandSlug,
                                'helmet_type[]' => $term->slug
                            ], $viewAllUrl);
                        } else {
                            $viewAllUrl = add_query_arg([
                                'brand_slug' => $brandSlug
                            ], $viewAllUrl);
                        }
                    ?>
                        <div id="cat-<?php echo esc_attr($catSlug); ?>" class="brand-hub__category hs-section">
                            <div class="hs-section__head">
                                <h2><?php echo esc_html($categoryName); ?></h2>
                                <?php if ($hasMore) : ?>
                                    <a href="<?php echo esc_url($viewAllUrl); ?>" class="hs-link">View All <?php echo esc_html($categoryName); ?> &rarr;</a>
                                <?php endif; ?>
                            </div>
                            
                            <div class="helmet-grid">
                                <?php 
                                foreach ($displayHelmets as $post) {
                                    setup_postdata($post);
                                    get_template_part('template-parts/helmet', 'card');
                                }
                                wp_reset_postdata();
                                ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </section>
                
            <?php else : ?>
                <section class="hs-panel" style="text-align: center; padding: 4rem;">
                    <p class="hs-text-muted">No helmets currently listed for <?php the_title(); ?>.</p>
                </section>
            <?php endif; ?>

            <!-- 9. Brand Navigation -->
            <nav class="brand-hub__nav hs-section" style="margin-top: 4rem;">
                <div class="brand-hub__nav-inner hs-panel" style="display: flex; justify-content: space-between;">
                    <div class="brand-hub__nav-prev">
                        <?php previous_post_link('%link', '<span>&larr; Previous Brand</span><br><strong>%title</strong>'); ?>
                    </div>
                    <div class="brand-hub__nav-next" style="text-align: right;">
                        <?php next_post_link('%link', '<span>Next Brand &rarr;</span><br><strong>%title</strong>'); ?>
                    </div>
                </div>
            </nav>

        </article>
        <?php
    }
}

get_footer();
