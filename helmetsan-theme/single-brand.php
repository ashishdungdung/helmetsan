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
        
        // Fetch ALL helmets for this brand
        $helmets = get_posts([
            'post_type' => 'helmet',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'meta_query' => [
                [
                    'key' => 'rel_brand',
                    'value' => $brandId,
                ],
            ],
            'orderby' => 'title',
            'order' => 'ASC',
        ]);

        // Group helmets by type
        $groupedHelmets = [];
        $uncategorized = [];

        foreach ($helmets as $helmet) {
            // Check if it's a parent or standalone (exclude variants from main list if preferred, 
            // but for now we list what we found. Ideally we filter parents only if using strict hierarchy).
            if ($helmet->post_parent > 0) {
                 continue; // Skip variants in main catalog
            }

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

        // Sort groups alphabetically
        ksort($groupedHelmets);
        ?>
        
        <article <?php post_class('brand-hub'); ?>>
            
            <!-- 1. Immersive Hero -->
            <header class="brand-hub__hero hs-section">
                <div class="brand-hub__hero-content">
                    <?php if ($logoUrl !== '') : ?>
                        <div class="brand-hub__logo">
                            <img src="<?php echo esc_url($logoUrl); ?>" alt="<?php the_title_attribute(); ?>" />
                        </div>
                    <?php else : ?>
                         <h1 class="brand-hub__title"><?php the_title(); ?></h1>
                    <?php endif; ?>
                    
                    <div class="brand-hub__meta">
                        <?php if ($founded) : ?><span>Est. <?php echo esc_html($founded); ?></span><?php endif; ?>
                        <?php if ($origin) : ?><span><?php echo esc_html($origin); ?></span><?php endif; ?>
                    </div>

                    <?php if ($motto) : ?>
                        <p class="brand-hub__motto">"<?php echo esc_html($motto); ?>"</p>
                    <?php endif; ?>
                </div>
            </header>

            <!-- 2. Key Stats Bar -->
            <section class="hs-stat-grid brand-hub__stats">
                <article class="hs-stat-card">
                    <span>Warranty</span>
                    <strong><?php echo esc_html($warranty !== '' ? $warranty : 'Avg. Standard'); ?></strong>
                </article>
                <article class="hs-stat-card">
                    <span>Models Tracked</span>
                    <strong><?php echo esc_html($totalModels !== '' ? $totalModels : count($helmets)); ?></strong>
                </article>
                <article class="hs-stat-card">
                    <span>Support</span>
                    <strong><?php echo $supportEmail ? '<a href="mailto:'.esc_attr($supportEmail).'">Email</a>' : ($supportUrl ? '<a href="'.esc_url($supportUrl).'" target="_blank">Online</a>' : 'N/A'); ?></strong>
                </article>
            </section>

            <!-- 2. Brand Spotlight Video -->
            <?php 
            $brandVideoUrl = helmetsan_core()->mediaService()->getBrandVideo(get_the_ID());
            if ($brandVideoUrl) : 
                $embedCode = helmetsan_core()->mediaService()->getEmbedCode($brandVideoUrl);
            ?>
                <section class="brand-hub__spotlight hs-panel" style="margin-bottom: 3rem; padding: 0; overflow: hidden;">
                    <div class="hs-responsive-embed">
                        <?php echo $embedCode; ?>
                    </div>
                </section>
            <?php endif; ?>

            <!-- 3. Story & Ethos -->
            <div class="brand-hub__layout">
                <div class="brand-hub__main hs-panel">
                    <h2>About <?php the_title(); ?></h2>
                    <div class="brand-hub__story-content">
                        <?php the_content(); ?>
                        <?php if (empty(get_the_content()) && !$ethos) : ?>
                            <p class="hs-text-muted"><em>Brand story coming soon.</em></p>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($ethos) : ?>
                        <div class="brand-hub__ethos">
                            <h3>Manufacturing Philosophy</h3>
                            <blockquote><?php echo wpautop(esc_html($ethos)); ?></blockquote>
                        </div>
                    <?php endif; ?>
                </div>
                
                <aside class="brand-hub__sidebar hs-panel">
                    <h3>Quick Facts</h3>
                    <ul class="hs-list">
                        <li><strong>Founded:</strong> <?php echo esc_html($founded ?: 'Unknown'); ?></li>
                        <li><strong>HQ:</strong> <?php echo esc_html($origin ?: 'Global'); ?></li>
                        <?php if ($website) : ?>
                            <li><strong>Website:</strong> <a href="<?php echo esc_url($website); ?>" target="_blank" rel="noopener">Visit Site</a></li>
                        <?php endif; ?>
                    </ul>

                    <?php if ($supportUrl) : ?>
                        <div class="brand-hub__cta">
                            <a href="<?php echo esc_url($supportUrl); ?>" class="hs-btn hs-btn--ghost" target="_blank" rel="noopener">
                                Official Support
                            </a>
                        </div>
                    <?php endif; ?>
                </aside>
            </div>

            <?php if (!empty($groupedHelmets)) : ?>
                
                <!-- 4. Category Discovery Cards -->
                <section class="brand-hub__discovery">
                    <h2 class="brand-hub__section-title">Explore by Category</h2>
                    <div class="link-card-grid">
                        <?php foreach ($groupedHelmets as $catName => $catHelmets) : 
                            $firstHelmet = $catHelmets[0];
                            $thumbUrl = get_the_post_thumbnail_url($firstHelmet->ID, 'medium');
                        ?>
                            <a href="#cat-<?php echo sanitize_title($catName); ?>" class="link-card">
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

                <!-- 5. Featured Lists per Category -->
                <section class="brand-hub__catalog">
                    <?php foreach ($groupedHelmets as $categoryName => $categoryHelmets) : 
                        $limit = 4;
                        $total = count($categoryHelmets);
                        $displayHelmets = array_slice($categoryHelmets, 0, $limit);
                        $hasMore = $total > $limit;
                        $catSlug = sanitize_title($categoryName);
                        
                        // Build "View All" URL - filters main archive by Brand + Helmet Type
                        // Assuming 'helmet' archive is /helmets/
                        $viewAllUrl = add_query_arg([
                            'post_type' => 'helmet',
                            'rel_brand' => $brandId, // or use taxonomy filter if ready
                            'helmet_type' => $catSlug // This might need mapping to actual slug if $categoryName is pretty
                        ], home_url('/helmets/'));
                        
                        // Better approach: Find term slug
                        $term = get_term_by('name', $categoryName, 'helmet_type');
                        if ($term) {
                             $viewAllUrl = add_query_arg([
                                'brand_filter' => $brandId, // Custom filter logic if supported
                                'helmet_type' => $term->slug
                            ], home_url('/helmets/'));
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

            <!-- 5. Brand Navigation -->
            <nav class="brand-hub__nav">
                <div class="brand-hub__nav-inner">
                    <div class="brand-hub__nav-prev">
                        <?php previous_post_link('%link', '<span>&larr; Previous Brand</span><strong>%title</strong>'); ?>
                    </div>
                    <div class="brand-hub__nav-next">
                        <?php next_post_link('%link', '<span>Next Brand &rarr;</span><strong>%title</strong>'); ?>
                    </div>
                </div>
            </nav>

        </article>
        <?php
    }
}

get_footer();
