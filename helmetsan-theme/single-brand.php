<?php
/**
 * Single brand template.
 *
 * @package HelmetsanTheme
 */

get_header();

if (! function_exists('helmetsan_render_brand_model_card')) {
    function helmetsan_render_brand_model_card($post) {
        $hId = $post->ID;
        $hPrice = helmetsan_get_helmet_price($hId);
        $hCerts = helmetsan_get_certifications($hId);
        $hShell = get_post_meta($hId, 'spec_shell_material', true) ?: 'Polycarbonate';
        $hWeight = get_post_meta($hId, 'spec_weight_g', true);
        $hShape = get_post_meta($hId, 'head_shape', true) ?: 'Intermediate Oval';
        
        // Count variants
        $variantsJson = get_post_meta($hId, 'variants_json', true);
        $variants = json_decode($variantsJson, true) ?: [];
        $variantCount = count($variants);
        $colors = [];
        if (is_array($variants)) {
            foreach ($variants as $v) {
                if (isset($v['color']) && ! in_array($v['color'], $colors, true)) {
                    $colors[] = $v['color'];
                }
            }
        }
        $colorPalette = array_slice($colors, 0, 5);
        ?>
        <div class="hs-brand-model-card hs-panel">
            <div class="hs-brand-model-card__image-wrap">
                <?php if (has_post_thumbnail()) : ?>
                    <?php the_post_thumbnail('medium_large'); ?>
                <?php else : ?>
                    <div class="hs-brand-model-card__placeholder">No Image</div>
                <?php endif; ?>
                <div class="hs-brand-model-card__price"><?php echo esc_html($hPrice); ?></div>
            </div>
            
            <div class="hs-brand-model-card__content">
                <h3 class="hs-brand-model-card__title">
                    <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
                </h3>
                
                <ul class="hs-brand-model-card__specs">
                    <li>
                        <span class="spec-label">Shell:</span>
                        <strong class="spec-val"><?php echo esc_html($hShell); ?></strong>
                    </li>
                    <li>
                        <span class="spec-label">Weight:</span>
                        <strong class="spec-val"><?php echo $hWeight ? esc_html($hWeight) . 'g' : '—'; ?></strong>
                    </li>
                    <li>
                        <span class="spec-label">Shape:</span>
                        <strong class="spec-val"><?php echo esc_html($hShape); ?></strong>
                    </li>
                </ul>
                
                <?php if (!empty($hCerts)) : ?>
                    <div class="hs-brand-model-card__certs">
                        <?php foreach ($hCerts as $c) : ?>
                            <span class="hs-brand-model-card__cert-badge"><?php echo esc_html($c); ?></span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($variantCount > 0) : ?>
                    <div class="hs-brand-model-card__variants">
                        <span class="hs-brand-model-card__variants-count"><?php echo esc_html($variantCount); ?> color variants:</span>
                        <div class="hs-brand-model-card__color-dots">
                            <?php 
                            $color_codes = [
                                'black' => '#111111', 'gloss black' => '#111111', 'matte black' => '#222222', 'white' => '#ffffff',
                                'gloss white' => '#ffffff', 'yellow' => '#ffff00', 'hi-viz yellow' => '#dfff00', 'blue' => '#0000ff',
                                'track blue' => '#1e90ff', 'grey' => '#808080', 'silver metallic' => '#c0c0c0', 'red' => '#ff0000',
                                'orange' => '#ffa500', 'green' => '#008000', 'pink' => '#ffc0cb'
                            ];
                            foreach ($colorPalette as $cName) : 
                                $dotColor = $color_codes[strtolower(trim($cName))] ?? '#666';
                            ?>
                                <span class="hs-brand-model-card__color-dot" style="background: <?php echo $dotColor; ?>;" title="<?php echo esc_attr($cName); ?>"></span>
                            <?php endforeach; ?>
                            <?php if (count($colors) > 5) : ?>
                                <span class="hs-brand-model-card__color-more">+<?php echo count($colors) - 5; ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="hs-brand-model-card__footer">
                <a href="<?php the_permalink(); ?>" class="hs-btn hs-btn--sm hs-btn--primary hs-brand-model-card__cta">View Specifications</a>
            </div>
        </div>
        <?php
    }
}

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
            'post_parent' => 0,
            'posts_per_page' => -1,
            'meta_query' => [['key' => 'rel_brand', 'value' => $brandId]],
            'fields' => 'ids',
        ]);

        $taxIds = get_posts([
            'post_type' => 'helmet',
            'post_status' => 'publish',
            'post_parent' => 0,
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
                'post_parent' => 0,
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
                    <div class="brand-hero-card__header-flex">
                        <?php if ($logoUrl !== '') : ?>
                            <div class="brand-hub__logo brand-hero-card__logo">
                                <img src="<?php echo esc_url($logoUrl); ?>" alt="<?php the_title_attribute(); ?>" />
                            </div>
                        <?php endif; ?>
                        <div class="brand-hero-card__titles">
                            <span class="brand-hero-card__badge"><?php esc_html_e('Official Brand Hub', 'helmetsan-theme'); ?></span>
                            <h1 class="brand-hub__title brand-hero-card__title"><?php the_title(); ?></h1>
                            <?php if ($motto) : ?>
                                <p class="brand-hub__motto brand-hero-card__motto">"<?php echo esc_html($motto); ?>"</p>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="brand-hub__meta brand-hero-card__meta">
                        <?php if ($founded) : ?>
                            <span class="brand-meta-badge">Est. <?php echo esc_html($founded); ?></span>
                        <?php endif; ?>
                        <?php 
                        $flags = ['Japan' => '🇯🇵', 'Italy' => '🇮🇹', 'USA' => '🇺🇸', 'United States' => '🇺🇸', 'Germany' => '🇩🇪', 'France' => '🇫🇷', 'South Korea' => '🇰🇷', 'Korea' => '🇰🇷', 'Thailand' => '🇹🇭', 'China' => '🇨🇳', 'Spain' => '🇪🇸', 'UK' => '🇬🇧', 'United Kingdom' => '🇬🇧', 'Switzerland' => '🇨🇭', 'Portugal' => '🇵🇹'];
                        $flag = isset($flags[$origin]) ? $flags[$origin] . ' ' : '🌍 ';
                        ?>
                        <?php if ($origin) : ?>
                            <span class="brand-meta-badge"><?php echo esc_html($flag . $origin); ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </header>

            <!-- 2. Key Stats & Quick Stats (Glassmorphism) -->
            <section class="brand-stats-glass hs-reveal">
                <div class="brand-stats-glass__grid">
                    <article class="brand-stats-glass__item">
                        <div class="brand-stats-glass__icon">
                            <svg viewBox="0 0 24 24" width="20" height="20" stroke="currentColor" stroke-width="2" fill="none"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                        </div>
                        <span><?php esc_html_e('Warranty', 'helmetsan-theme'); ?></span>
                        <strong><?php echo esc_html($warranty !== '' ? $warranty : __('Avg. Standard', 'helmetsan-theme')); ?></strong>
                    </article>
                    <article class="brand-stats-glass__item">
                        <div class="brand-stats-glass__icon">
                            <svg viewBox="0 0 24 24" width="20" height="20" stroke="currentColor" stroke-width="2" fill="none"><path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/></svg>
                        </div>
                        <span><?php esc_html_e('Models Tracked', 'helmetsan-theme'); ?></span>
                        <strong><?php 
                        $inDb = count($helmets);
                        if ($totalModels !== '' && (int)$totalModels > $inDb) {
                            /* translators: 1: models in database, 2: total models globally */
                            printf(esc_html__('%1$d / %2$d Global', 'helmetsan-theme'), $inDb, (int)$totalModels);
                        } else {
                            echo esc_html($inDb);
                        }
                        ?></strong>
                    </article>
                    <article class="brand-stats-glass__item">
                        <div class="brand-stats-glass__icon">
                            <svg viewBox="0 0 24 24" width="20" height="20" stroke="currentColor" stroke-width="2" fill="none"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>
                        </div>
                        <span><?php esc_html_e('Market Position', 'helmetsan-theme'); ?></span>
                        <strong><?php 
                        if ($stats['max_price'] >= 700) {
                            esc_html_e('Premium / Pro', 'helmetsan-theme');
                        } elseif ($stats['max_price'] >= 300) {
                            esc_html_e('Mid-Range', 'helmetsan-theme');
                        } elseif ($stats['max_price'] > 0) {
                            esc_html_e('Value / Entry', 'helmetsan-theme');
                        } else {
                            esc_html_e('Various', 'helmetsan-theme');
                        }
                        ?></strong>
                    </article>
                    <article class="brand-stats-glass__item">
                        <div class="brand-stats-glass__icon">
                            <svg viewBox="0 0 24 24" width="20" height="20" stroke="currentColor" stroke-width="2" fill="none"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg>
                        </div>
                        <span><?php esc_html_e('Avg. Weight', 'helmetsan-theme'); ?></span>
                        <strong><?php echo $avgWeight > 0 ? esc_html($avgWeight) . 'g' : __('N/A', 'helmetsan-theme'); ?></strong>
                    </article>
                    <article class="brand-stats-glass__item">
                        <div class="brand-stats-glass__icon">
                            <svg viewBox="0 0 24 24" width="20" height="20" stroke="currentColor" stroke-width="2" fill="none"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg>
                        </div>
                        <span><?php esc_html_e('Price Range', 'helmetsan-theme'); ?></span>
                        <strong><?php echo $stats['max_price'] > 0 ? '$' . $stats['min_price'] . ' – $' . $stats['max_price'] : __('Various', 'helmetsan-theme'); ?></strong>
                    </article>
                </div>
            </section>

            <!-- 3. Story & Ethos (Dual Column) -->
            <div class="brand-hub__layout hs-section" style="margin-top: var(--hs-sp-10);">
                <div class="brand-hub__main hs-panel brand-about-panel">
                    <h2 id="about-brand" class="brand-about-title">The <?php the_title(); ?> Story</h2>
                    <div class="brand-hub__story-content brand-story-text">
                        <?php 
                        $brandContent = get_the_content();
                        $story = (string) get_post_meta($brandId, 'brand_story', true);
                        
                        if (trim(strip_tags($brandContent)) !== '') {
                            the_content();
                        } elseif ($story !== '') {
                            echo wpautop(esc_html($story));
                        } else {
                            $origin = (string) get_post_meta($brandId, 'brand_origin_country', true);
                            $founded = (string) get_post_meta($brandId, 'brand_founded_year', true);
                            $brandSlug = get_post_field('post_name', $brandId);
                            $helmetsLink = add_query_arg('brand_slug', $brandSlug, helmetsan_url('/helmets/'));
                            ?>
                            <p><?php the_title(); ?> is a recognized helmet manufacturer<?php echo $origin ? ' originating from ' . esc_html($origin) : ''; ?><?php echo $founded ? ' (established in ' . esc_html($founded) . ')' : ''; ?>. We track their complete lineup of models across the globe. Compare their technical specifications, safety certifications, and find the best prices using our advanced comparison tool.</p>
                            <p><a href="<?php echo esc_url($helmetsLink); ?>" class="hs-btn hs-btn--primary">Browse <?php the_title(); ?> lineup</a></p>
                            <?php
                        }
                        ?>
                    </div>
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
                    $helmetsArchiveWithBrand = add_query_arg('brand_slug', $brandSlug, helmetsan_url('/helmets/'));
                    ?>
                    <div class="brand-hub__cta-group">
                        <a href="<?php echo esc_url($helmetsArchiveWithBrand); ?>" class="hs-btn hs-btn--primary hs-btn--block">Browse <?php the_title(); ?> Helmets</a>
                        <?php if ($supportUrl) : ?>
                            <a href="<?php echo esc_url($supportUrl); ?>" class="hs-btn hs-btn--ghost hs-btn--block" target="_blank" rel="noopener">Official Support</a>
                        <?php endif; ?>
                    </div>
                </aside>
            </div>

            <!-- 3b. Manufacturing Philosophy (Full-Width Accent Callout) -->
            <?php if ($ethos) : ?>
                <section class="brand-hub__ethos-section hs-section hs-reveal">
                    <div class="brand-hub__ethos">
                        <div class="brand-hub__ethos-header">
                            <svg class="brand-hub__ethos-icon" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg>
                            <h3>Manufacturing Philosophy</h3>
                        </div>
                        <blockquote class="brand-ethos-quote"><?php echo wpautop(esc_html($ethos)); ?></blockquote>
                    </div>
                </section>
            <?php endif; ?>

            <!-- ═══ B2B Wholesale Supply Channels ═══ -->
            <?php
            $geoService = function_exists('helmetsan_core') ? helmetsan_core()->geo() : null;
            $regionCode = $geoService ? $geoService->getRegion() : 'NA';
            $brandNameVal = get_the_title();
            $distributors = function_exists('helmetsan_get_brand_distributors') ? helmetsan_get_brand_distributors($brandNameVal, $regionCode) : [];
            ?>
            <?php if (!empty($distributors)) : ?>
                <section class="brand-hub__distributors hs-section hs-reveal">
                    <div class="hs-panel brand-distributors-panel">
                        <div class="brand-distributors-header">
                            <h2><?php esc_html_e('Official Wholesale Importers &amp; Distributors', 'helmetsan-theme'); ?></h2>
                            <p class="hs-text-muted"><?php printf(esc_html__('Supply channels, regional warehouses, and B2B contacts authorized to supply %s helmets in the %s region.', 'helmetsan-theme'), esc_html($brandNameVal), esc_html(strtoupper($regionCode))); ?></p>
                        </div>
                        <div class="hs-distributors-grid">
                            <?php foreach ($distributors as $dist) : 
                                $typeLabel = $dist['type'] === 'exclusive' ? __('Exclusive Importer', 'helmetsan-theme') : ($dist['type'] === 'regional' ? __('Regional Distributor', 'helmetsan-theme') : __('Authorized Distributor', 'helmetsan-theme'));
                                $typeClass = $dist['type'] === 'exclusive' ? 'hs-badge--error' : 'hs-badge--info';
                            ?>
                                <article class="hs-panel hs-distributor-card">
                                    <div class="hs-distributor-card__header">
                                        <h3><?php echo esc_html($dist['title']); ?></h3>
                                        <span class="hs-badge <?php echo $typeClass; ?>"><?php echo esc_html($typeLabel); ?></span>
                                    </div>
                                    
                                    <div class="hs-distributor-card__body">
                                        <?php if (!empty($dist['warehouses'])) : ?>
                                            <p class="hs-distributor-card__warehouses">
                                                <strong><?php esc_html_e('Warehouses:', 'helmetsan-theme'); ?></strong> 
                                                <?php echo esc_html(implode(', ', array_map(fn($w) => $w['city'] . ' (' . $w['country'] . ')', $dist['warehouses']))); ?>
                                            </p>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($dist['contacts'])) : ?>
                                            <div class="hs-distributor-card__contacts">
                                                <?php foreach ($dist['contacts'] as $contact) : ?>
                                                    <span class="hs-distributor-card__contact-item">
                                                        <?php echo esc_html(ucfirst($contact['type'])); ?>: 
                                                        <?php if (isset($contact['email'])) : ?>
                                                            <a href="mailto:<?php echo esc_attr($contact['email']); ?>" class="hs-link"><?php echo esc_html($contact['email']); ?></a>
                                                        <?php elseif (isset($contact['url'])) : ?>
                                                            <a href="<?php echo esc_url($contact['url']); ?>" class="hs-link" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Contact Page &rarr;', 'helmetsan-theme'); ?></a>
                                                        <?php endif; ?>
                                                    </span>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="hs-distributor-card__footer">
                                        <?php if ($dist['phone']) : ?>
                                            <a href="tel:<?php echo esc_attr($dist['phone']); ?>" class="hs-btn hs-btn--sm hs-btn--ghost">📞 <?php esc_html_e('Call', 'helmetsan-theme'); ?></a>
                                        <?php endif; ?>
                                        <?php if ($dist['website']) : ?>
                                            <a href="<?php echo esc_url($dist['website']); ?>" class="hs-btn hs-btn--sm hs-btn--primary" target="_blank" rel="noopener noreferrer">🌐 <?php esc_html_e('Portal', 'helmetsan-theme'); ?></a>
                                        <?php endif; ?>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </section>
            <?php endif; ?>

            <!-- 4. Global Safety Standards -->
            <?php if (!empty($stats['certs'])) : ?>
            <section class="brand-hub__standards hs-section hs-reveal">
                <div class="hs-panel brand-standards-panel">
                    <div class="brand-standards-header">
                        <h2>Safety Standards Attained</h2>
                        <p>Models within this brand's lineup have been certified to meet the following international safety protocols.</p>
                    </div>
                    <div class="brand-standards-grid" style="display: flex; gap: var(--hs-sp-4); flex-wrap: wrap;">
                        <?php foreach($stats['certs'] as $certName): 
                            $bg = 'var(--hs-bg-elevated)';
                            $color = 'var(--hs-text)';
                            $borderColor = 'var(--hs-border)';
                            
                            if (stripos($certName, 'ECE 22.06') !== false) {
                                $color = 'var(--hs-warning)';
                                $borderColor = 'var(--hs-warning)';
                            } elseif (stripos($certName, 'FIM') !== false) {
                                $color = 'var(--hs-error)';
                                $borderColor = 'var(--hs-error)';
                            } elseif (stripos($certName, 'Snell') !== false) {
                                $color = 'var(--hs-success)';
                                $borderColor = 'var(--hs-success)';
                            }
                        ?>
                            <span class="brand-cert-badge" style="background: <?php echo $bg; ?>; color: <?php echo $color; ?>; border: 1px solid <?php echo $borderColor; ?>; padding: 0.5rem 1rem; border-radius: var(--hs-radius-sm); font-weight: 700; font-size: 0.9rem; letter-spacing: 0.5px; box-shadow: var(--hs-shadow-sm); display: inline-flex; align-items: center; gap: 0.5rem;">
                                <svg viewBox="0 0 24 24" width="16" height="16" stroke="currentColor" stroke-width="2.5" fill="none"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                                <?php echo esc_html($certName); ?>
                            </span>
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
                <section class="brand-hub__spotlight hs-section hs-reveal">
                    <div class="brand-video-container hs-panel">
                        <div class="hs-responsive-embed">
                            <?php echo $embedCode; ?>
                        </div>
                    </div>
                </section>
            <?php endif; ?>

            <!-- 6. Featured Models -->
            <?php if (!empty($featuredSlice)) : ?>
            <section class="brand-hub__featured hs-section hs-reveal">
                <div class="hs-section__head">
                    <h2>Featured <?php the_title(); ?> Helmets</h2>
                </div>
                <div class="helmet-grid brand-featured-grid">
                    <?php 
                    foreach ($featuredSlice as $post) {
                        setup_postdata($post);
                        helmetsan_render_brand_model_card($post);
                    }
                    wp_reset_postdata();
                    ?>
                </div>
            </section>
            <?php endif; ?>

            <?php if (!empty($groupedHelmets)) : ?>
                <!-- 7. Category Discovery Cards -->
                <section class="brand-hub__discovery hs-section hs-reveal">
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
                        
                        $viewAllUrl = helmetsan_url('/helmets/');
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
                                    helmetsan_render_brand_model_card($post);
                                }
                                wp_reset_postdata();
                                ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </section>
                
            <?php else : ?>
                <section class="hs-panel" style="text-align: center; padding: var(--hs-sp-12) var(--hs-sp-8); border-radius: var(--hs-radius-lg); background: var(--hs-panel); border: 1px solid var(--hs-border);">
                    <div style="max-width: 600px; margin: 0 auto;">
                        <svg viewBox="0 0 24 24" width="64" height="64" stroke="var(--hs-muted)" stroke-width="1.5" fill="none" style="margin-bottom: var(--hs-sp-4);"><circle cx="12" cy="12" r="10"/><path d="M8 12h8"/><path d="M12 8v8"/></svg>
                        <h2 style="margin-bottom: var(--hs-sp-2); color: var(--hs-text);">Profiling <?php the_title(); ?>'s Lineup</h2>
                        <p class="hs-text-muted" style="margin-bottom: var(--hs-sp-6); font-size: 1.1rem; color: var(--hs-text-soft);">We are currently tracking and reviewing <?php echo esc_html($totalModels ?: 'the latest'); ?> helmets from <?php the_title(); ?>. Our team is running safety data analysis and building the technical spec sheets.</p>
                        <form class="hs-notify-form" style="display: flex; gap: var(--hs-sp-3); justify-content: center; max-width: 400px; margin: 0 auto;" onsubmit="event.preventDefault(); alert('Thanks! We will notify you.');">
                            <input type="email" placeholder="Enter your email" required class="hs-input" style="flex: 1; min-width: 0;">
                            <button type="submit" class="hs-btn hs-btn--primary" style="white-space: nowrap;">Notify Me</button>
                        </form>
                        <p style="font-size: 0.85rem; margin-top: var(--hs-sp-3); color: var(--hs-muted);">Get an alert when <?php the_title(); ?> helmets are added to the comparison tool.</p>
                    </div>
                </section>
            <?php endif; ?>

            <!-- 9. Brand Navigation -->
            <nav class="brand-hub__nav hs-section" style="margin-top: var(--hs-sp-12);">
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
