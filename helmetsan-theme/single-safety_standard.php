<?php
/**
 * Single safety standard template - Premium Hub Edition
 *
 * @package HelmetsanTheme
 */

get_header();

if (have_posts()) :
    while (have_posts()) :
        the_post();
        $id = get_the_ID();
        
        // Metadata
        $regionsJson = (string) get_post_meta($id, 'standard_regions_json', true);
        $mandatoryJson = (string) get_post_meta($id, 'mandatory_markets_json', true);
        $officialUrl = (string) get_post_meta($id, 'official_url', true);
        $testingInfo = (string) get_post_meta($id, 'testing_info', true);
        $docLinksJson = (string) get_post_meta($id, 'doc_links_json', true);
        $storyHistory = (string) get_post_meta($id, 'story_history', true);
        $certSlug = (string) get_post_meta($id, 'linked_certification_slug', true);

        $regions = json_decode($regionsJson, true);
        $mandatory = json_decode($mandatoryJson, true);
        $docLinks = json_decode($docLinksJson, true);

        // Fetch Helmet Count
        $helmet_count = 0;
        $certificationUrl = '#';
        if ($certSlug) {
            $term = get_term_by('slug', $certSlug, 'certification');
            if ($term) {
                $helmet_count = $term->count;
                $certificationUrl = get_term_link($term);
            }
        }

        $themeDir = get_stylesheet_directory_uri();
        $logoUrl = $themeDir . '/assets/images/hubs/safety_standard/' . get_post_field('post_name', $id) . '.png';
        ?>

        <article <?php post_class('hs-section hs-standard-detail'); ?>>
            <header class="hs-section__head hs-standard-hero">
                <div class="hs-standard-hero__container">
                    <div class="hs-standard-hero__logo">
                        <img src="<?php echo esc_url($logoUrl); ?>" alt="<?php the_title_attribute(); ?>" onerror="this.src='<?php echo esc_url($themeDir . '/assets/images/placeholder-hub.png'); ?>'">
                    </div>
                    <div class="hs-standard-hero__content">
                        <p class="hs-eyebrow">Safety Certification Standard</p>
                        <h1><?php the_title(); ?></h1>
                        <div class="hs-standard-hero__meta">
                            <span class="hs-badge hs-badge--accent">Verified Standard</span>
                            <?php if ($officialUrl) : ?>
                                <a href="<?php echo esc_url($officialUrl); ?>" target="_blank" class="hs-standard-hero__link">
                                    Official Website <svg viewBox="0 0 24 24" width="16" height="16"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6m3-3h7m0 0v7m0-7L10 14" fill="none" stroke="currentColor" stroke-width="2"/></svg>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </header>

            <!-- 1. Story & Background -->
            <section class="hs-panel hs-content-card">
                <header class="hs-content-card__head">
                    <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 8v4m0 4h.01"/></svg>
                    <h2>Story & Background</h2>
                </header>
                <div class="hs-prose">
                    <?php echo wp_kses_post($storyHistory ?: get_the_content()); ?>
                    
                    <?php if (get_post_field('post_name', $id) === 'dot-fmvss-218') : ?>
                        <div class="hs-timeline">
                            <div class="hs-timeline__item">
                                <span class="hs-timeline__year">1974</span>
                                <span class="hs-timeline__text">Initial introduction of FMVSS 218 by NHTSA.</span>
                            </div>
                            <div class="hs-timeline__item">
                                <span class="hs-timeline__year">1988</span>
                                <span class="hs-timeline__text">Significant update to testing procedures and labels.</span>
                            </div>
                            <div class="hs-timeline__item">
                                <span class="hs-timeline__year">2011</span>
                                <span class="hs-timeline__text">Updated to combat 'novelty' helmets with stricter labeling.</span>
                            </div>
                            <div class="hs-timeline__item">
                                <span class="hs-timeline__year">2024</span>
                                <span class="hs-timeline__text">Current standard remains primary US legal requirement.</span>
                            </div>
                        </div>
                    <?php elseif (get_post_field('post_name', $id) === 'ece-22-06') : ?>
                        <div class="hs-timeline">
                            <div class="hs-timeline__item">
                                <span class="hs-timeline__year">2000</span>
                                <span class="hs-timeline__text">ECE 22.05 becomes the dominant global safety standard.</span>
                            </div>
                            <div class="hs-timeline__item">
                                <span class="hs-timeline__year">2020</span>
                                <span class="hs-timeline__text">UN Regulation 22.06 officially adopted with rotational testing.</span>
                            </div>
                            <div class="hs-timeline__item">
                                <span class="hs-timeline__year">2022</span>
                                <span class="hs-timeline__text">Mandatory for new helmet certifications in the EU.</span>
                            </div>
                            <div class="hs-timeline__item">
                                <span class="hs-timeline__year">2024</span>
                                <span class="hs-timeline__text">Full enforcement; older 22.05 variants no longer manufacturable.</span>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </section>

            <!-- 2. Testing Protocol -->
            <section class="hs-panel hs-content-card">
                <header class="hs-content-card__head">
                    <svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                    <h2>Testing Protocol</h2>
                </header>
                <div class="hs-prose">
                    <?php echo wp_kses_post($testingInfo ?: 'Technical testing protocol information for this standard is currently being updated.'); ?>
                </div>
            </section>

            <!-- 3. Market Status -->
            <div class="hs-grid hs-grid--2">
                <div class="hs-panel">
                    <h3>Active Regions</h3>
                    <div class="hs-tag-cloud">
                        <?php if (is_array($regions)) : foreach ($regions as $item) : ?>
                            <span class="hs-tag"><?php echo esc_html($item); ?></span>
                        <?php endforeach; endif; ?>
                    </div>
                </div>
                <div class="hs-panel">
                    <h3>Legal Status</h3>
                    <p class="hs-small">
                        <?php if (is_array($mandatory) && $mandatory !== []) : ?>
                            Mandatory requirement for markets in <strong><?php echo implode(', ', $mandatory); ?></strong>.
                        <?php else : ?>
                            Voluntary certification or subject to regional racing organization rules.
                        <?php endif; ?>
                    </p>
                </div>
            </div>

            <!-- 4. Product Discovery -->
            <section class="hs-discovery-cta">
                <div class="hs-stat-card__value"><?php echo number_format($helmet_count); ?></div>
                <h2>Certified Helmets Found</h2>
                <p>Browse our verified database of products that meet this standard.</p>
                <a href="<?php echo esc_url($certificationUrl); ?>" class="hs-btn--action">
                    Explore All Certified Helmets
                    <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="3"><path d="M5 12h14m-7-7l7 7-7 7"/></svg>
                </a>
            </section>

            <!-- 5. Technical Benchmarks -->
            <?php if (get_post_field('post_name', $id) === 'dot-fmvss-218') : ?>
            <section class="hs-panel hs-standard-improv">
                <header class="hs-section__header-compact">
                    <h2>Technical Performance Specs</h2>
                    <p>Quantitative benchmarks for current DOT certification.</p>
                </header>
                <div class="hs-tech-spec">
                    <div class="hs-spec-item">
                        <span class="hs-spec-item__label">Peak Acceleration</span>
                        <span class="hs-spec-item__value">400G</span>
                    </div>
                    <div class="hs-spec-item">
                        <span class="hs-spec-item__label">Impact Speed</span>
                        <span class="hs-spec-item__value">6.0 m/s</span>
                    </div>
                    <div class="hs-spec-item">
                        <span class="hs-spec-item__label">Penetration Mass</span>
                        <span class="hs-spec-item__value">3.0 kg</span>
                    </div>
                    <div class="hs-spec-item">
                        <span class="hs-spec-item__label">Dwell Time</span>
                        <span class="hs-spec-item__value">2.0 ms</span>
                    </div>
                </div>

                <div class="hs-comparison">
                    <h3>How it Compares: The Safety Gap</h3>
                    <p class="hs-small">DOT 218 vs ECE 22.06 (The Gold Standard)</p>
                    <div class="hs-comparison__grid">
                        <div class="hs-comparison__col">
                            <span class="hs-comparison__name">DOT FMVSS 218</span>
                            <div class="hs-comparison__metric">
                                <span class="hs-small">Max G-Force Allowance: <strong>400G</strong></span>
                                <div class="hs-comparison__bar-bg"><div class="hs-comparison__bar" style="width: 100%; background: #ff4d4f;"></div></div>
                            </div>
                            <div class="hs-comparison__metric">
                                <span class="hs-small">Impact Sensors: <strong>1</strong></span>
                                <div class="hs-comparison__bar-bg"><div class="hs-comparison__bar" style="width: 20%;"></div></div>
                            </div>
                        </div>
                        <div class="hs-comparison__col hs-comparison__col--active">
                            <span class="hs-comparison__name">ECE 22.06</span>
                            <div class="hs-comparison__metric">
                                <span class="hs-small">Max G-Force Allowance: <strong>275G</strong></span>
                                <div class="hs-comparison__bar-bg"><div class="hs-comparison__bar" style="width: 68.75%; background: #52c41a;"></div></div>
                            </div>
                            <div class="hs-comparison__metric">
                                <span class="hs-small">Impact Sensors: <strong>12</strong></span>
                                <div class="hs-comparison__bar-bg"><div class="hs-comparison__bar" style="width: 100%;"></div></div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
            <?php elseif (get_post_field('post_name', $id) === 'ece-22-06') : ?>
            <section class="hs-panel hs-standard-improv">
                <header class="hs-section__header-compact">
                    <h2>The Gold Standard Benchmarks</h2>
                    <p>Advanced technical requirements for ECE 22.06 global certification.</p>
                </header>
                <div class="hs-tech-spec">
                    <div class="hs-spec-item">
                        <span class="hs-spec-item__label">Rotational Impact</span>
                        <span class="hs-spec-item__value">12 Sensors</span>
                    </div>
                    <div class="hs-spec-item">
                        <span class="hs-spec-item__label">Impact Threshold</span>
                        <span class="hs-spec-item__value">275G Max</span>
                    </div>
                    <div class="hs-spec-item">
                        <span class="hs-spec-item__label">Testing Speeds</span>
                        <span class="hs-spec-item__value">6.0 - 8.2 m/s</span>
                    </div>
                    <div class="hs-spec-item">
                        <span class="hs-spec-item__label">Impact Points</span>
                        <span class="hs-spec-item__value">18+ Locations</span>
                    </div>
                </div>

                <div class="hs-comparison">
                    <h3>Generational Leap: 22.05 vs 22.06</h3>
                    <div class="hs-comparison__grid">
                        <div class="hs-comparison__col">
                            <span class="hs-comparison__name">ECE 22.05 (Legacy)</span>
                            <div class="hs-comparison__metric">
                                <span class="hs-small">Rotational Testing: <strong>No</strong></span>
                                <div class="hs-comparison__bar-bg"><div class="hs-comparison__bar" style="width: 0%;"></div></div>
                            </div>
                            <div class="hs-comparison__metric">
                                <span class="hs-small">Impact Points: <strong>5</strong></span>
                                <div class="hs-comparison__bar-bg"><div class="hs-comparison__bar" style="width: 25%;"></div></div>
                            </div>
                        </div>
                        <div class="hs-comparison__col hs-comparison__col--active">
                            <span class="hs-comparison__name">ECE 22.06 (Current)</span>
                            <div class="hs-comparison__metric">
                                <span class="hs-small">Rotational Testing: <strong>Oblique Impact</strong></span>
                                <div class="hs-comparison__bar-bg"><div class="hs-comparison__bar" style="width: 100%; background: #52c41a;"></div></div>
                            </div>
                            <div class="hs-comparison__metric">
                                <span class="hs-small">Impact Points: <strong>18+ Points</strong></span>
                                <div class="hs-comparison__bar-bg"><div class="hs-comparison__bar" style="width: 100%; background: #1890ff;"></div></div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
            <?php endif; ?>

            <!-- 6. Resource Library -->
            <?php if (!empty($docLinks)) : ?>
            <section class="hs-panel hs-doc-card">
                <h3>Technical Documents</h3>
                <ul class="hs-doc-list">
                    <?php foreach ($docLinks as $link) : ?>
                        <li>
                            <a href="<?php echo esc_url($link['url']); ?>" target="_blank" class="hs-doc-link">
                                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M16 13H8m8 4H8m2-8H8"/></svg>
                                <span><?php echo esc_html($link['label']); ?></span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </section>
            <?php endif; ?>

            <!-- 7. Navigation Pagination -->
            <nav class="hs-pagination">
                <?php
                $prev_post = get_previous_post();
                $next_post = get_next_post();
                ?>
                <div class="hs-pagination__item">
                    <?php if ($prev_post) : ?>
                        <a href="<?php echo get_permalink($prev_post); ?>" class="hs-pagination__link">
                            <span class="hs-pagination__label">Previous Standard</span>
                            <span class="hs-pagination__title">← <?php echo get_the_title($prev_post); ?></span>
                        </a>
                    <?php endif; ?>
                </div>
                <div class="hs-pagination__item">
                    <?php if ($next_post) : ?>
                        <a href="<?php echo get_permalink($next_post); ?>" class="hs-pagination__link hs-pagination__link--next">
                            <span class="hs-pagination__label">Next Standard</span>
                            <span class="hs-pagination__title"><?php echo get_the_title($next_post); ?> →</span>
                        </a>
                    <?php endif; ?>
                </div>
            </nav>
            
        </article>

        <?php
    endwhile;
endif;

get_footer();
