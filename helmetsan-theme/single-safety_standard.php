<?php
/**
 * Single safety standard template - Redesigned Premium Hub Edition
 *
 * @package HelmetsanTheme
 */

get_header();

if (have_posts()) :
    while (have_posts()) :
        the_post();
        $id = get_the_ID();
        $slug = get_post_field('post_name', $id);
        
        // Metadata
        $regionsJson   = (string) get_post_meta($id, 'standard_regions_json', true);
        $mandatoryJson = (string) get_post_meta($id, 'mandatory_markets_json', true);
        $officialUrl   = (string) get_post_meta($id, 'official_url', true);
        $testingInfo   = (string) get_post_meta($id, 'testing_info', true);
        $docLinksJson  = (string) get_post_meta($id, 'doc_links_json', true);
        $storyHistory  = (string) get_post_meta($id, 'story_history', true);
        $certSlug      = (string) get_post_meta($id, 'linked_certification_slug', true);

        $regions   = json_decode($regionsJson, true) ?: [];
        $mandatory = json_decode($mandatoryJson, true) ?: [];
        $docLinks  = json_decode($docLinksJson, true) ?: [];

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
        
        // Dynamic logo resolver scanning multiple extensions
        $logoUrl  = '';
        $extensions = ['png', 'svg', 'jpg', 'jpeg', 'webp', 'avif'];
        foreach ($extensions as $ext) {
            $local_path = '/assets/images/hubs/safety_standard/' . $slug . '.' . $ext;
            if (file_exists(get_stylesheet_directory() . $local_path)) {
                $logoUrl = $themeDir . $local_path;
                break;
            }
        }

        if (!$logoUrl) {
            $logoUrl = get_the_post_thumbnail_url($id, 'large');
        }

        if (!$logoUrl) {
            $logoUrl = $themeDir . '/assets/images/placeholder-hub.png';
        }

        // ── 1. Define Milestones ──────────────────────────────────────────────
        $milestones = [];
        switch ($slug) {
            case 'dot-fmvss-218':
                $milestones = [
                    ['year' => '1974', 'text' => 'Initial introduction of FMVSS 218 by NHTSA.'],
                    ['year' => '1988', 'text' => 'Significant update to testing procedures and labels.'],
                    ['year' => '2011', 'text' => 'Updated to combat \'novelty\' helmets with stricter labeling.'],
                    ['year' => '2024', 'text' => 'Current standard remains primary US legal requirement.']
                ];
                break;
            case 'ece-22-06':
                $milestones = [
                    ['year' => '2000', 'text' => 'ECE 22.05 becomes the dominant global safety standard.'],
                    ['year' => '2020', 'text' => 'UN Regulation 22.06 officially adopted with rotational testing.'],
                    ['year' => '2022', 'text' => 'Mandatory for new helmet certifications in the EU.'],
                    ['year' => '2024', 'text' => 'Full enforcement; older 22.05 variants no longer manufacturable.']
                ];
                break;
            case 'snell-m2020r':
                $milestones = [
                    ['year' => '1957', 'text' => 'Snell Memorial Foundation established after Pete Snell\'s fatal crash.'],
                    ['year' => '2015', 'text' => 'Snell M2015 standard implemented with high-energy double impact testing.'],
                    ['year' => '2020', 'text' => 'Snell M2020 released in M2020D (US) and M2020R (ECE compatible) formats.'],
                    ['year' => '2025', 'text' => 'Continues as the premier voluntary standard for racing organizations worldwide.']
                ];
                break;
            case 'sharp-uk-rating':
                $milestones = [
                    ['year' => '2007', 'text' => 'SHARP launched by UK Department for Transport to provide objective safety ratings.'],
                    ['year' => '2012', 'text' => 'Published ratings for over 200 helmet models, showing massive safety gaps.'],
                    ['year' => '2020', 'text' => 'Updated testing protocol to align with ECE 22.06 rotational standards.'],
                    ['year' => '2026', 'text' => 'Acts as the premier independent 5-star safety scoring service globally.']
                ];
                break;
            case 'isi-is-4151':
                $milestones = [
                    ['year' => '1993', 'text' => 'First release of Indian Standard IS 4151 regulating motorcycle protective helmets.'],
                    ['year' => '2015', 'text' => 'Major revision aligning testing requirements closely with ECE principles.'],
                    ['year' => '2018', 'text' => 'Government of India mandates ISI certification for all helmet manufacturing and sales.'],
                    ['year' => '2024', 'text' => 'Strict enforcement banning non-ISI helmets to eliminate unsafe counterfeit imports.']
                ];
                break;
        }

        // ── 2. Define Technical Specs & Comparison Details ─────────────────────
        $tech_specs = [];
        $comparison = null;

        switch ($slug) {
            case 'dot-fmvss-218':
                $tech_specs = [
                    ['label' => 'Peak Acceleration', 'value' => '400G'],
                    ['label' => 'Impact Speed', 'value' => '6.0 m/s'],
                    ['label' => 'Penetration Mass', 'value' => '3.0 kg'],
                    ['label' => 'Dwell Time', 'value' => '2.0 ms']
                ];
                $comparison = [
                    'title' => 'How it Compares: The Safety Gap',
                    'subtitle' => 'DOT 218 vs ECE 22.06 (The Gold Standard)',
                    'cols' => [
                        [
                            'name' => 'DOT FMVSS 218',
                            'active' => false,
                            'metrics' => [
                                ['label' => 'Max G-Force Allowance: 400G', 'value' => '400G', 'width' => '100%', 'color' => '#ff4d4f'],
                                ['label' => 'Impact Sensors: 1', 'value' => '1', 'width' => '20%', 'color' => 'var(--hs-muted)']
                            ]
                        ],
                        [
                            'name' => 'ECE 22.06',
                            'active' => true,
                            'metrics' => [
                                ['label' => 'Max G-Force Allowance: 275G', 'value' => '275G', 'width' => '68.75%', 'color' => '#52c41a'],
                                ['label' => 'Impact Sensors: 12', 'value' => '12', 'width' => '100%', 'color' => '#1890ff']
                            ]
                        ]
                    ]
                ];
                break;

            case 'ece-22-06':
                $tech_specs = [
                    ['label' => 'Rotational Impact', 'value' => '12 Sensors'],
                    ['label' => 'Impact Threshold', 'value' => '275G Max'],
                    ['label' => 'Testing Speeds', 'value' => '6.0 - 8.2 m/s'],
                    ['label' => 'Impact Points', 'value' => '18+ Locations']
                ];
                $comparison = [
                    'title' => 'Generational Leap: 22.05 vs 22.06',
                    'subtitle' => 'Comparing the legacy ECE 22.05 with current ECE 22.06 criteria',
                    'cols' => [
                        [
                            'name' => 'ECE 22.05 (Legacy)',
                            'active' => false,
                            'metrics' => [
                                ['label' => 'Rotational Testing: No', 'value' => 'No', 'width' => '0%', 'color' => 'var(--hs-muted)'],
                                ['label' => 'Impact Points: 5', 'value' => '5', 'width' => '27%', 'color' => 'var(--hs-muted)']
                            ]
                        ],
                        [
                            'name' => 'ECE 22.06 (Current)',
                            'active' => true,
                            'metrics' => [
                                ['label' => 'Rotational Testing: Oblique Impact', 'value' => 'Oblique', 'width' => '100%', 'color' => '#52c41a'],
                                ['label' => 'Impact Points: 18+ Points', 'value' => '18+ Points', 'width' => '100%', 'color' => '#1890ff']
                            ]
                        ]
                    ]
                ];
                break;

            case 'snell-m2020r':
                $tech_specs = [
                    ['label' => 'Dual Strike Test', 'value' => '2 Successive Hits'],
                    ['label' => 'Impact Limit', 'value' => '275G Max'],
                    ['label' => 'Hemispherical Anvil', 'value' => 'Severe Point Test'],
                    ['label' => 'Retention Load', 'value' => 'Dynamic 38kg']
                ];
                $comparison = [
                    'title' => 'Snell M2020R vs DOT FMVSS 218',
                    'subtitle' => 'How the premium racing standard compares to legal US minimums',
                    'cols' => [
                        [
                            'name' => 'DOT FMVSS 218',
                            'active' => false,
                            'metrics' => [
                                ['label' => 'Double Impact Test: No (Single Hit)', 'value' => 'No', 'width' => '10%', 'color' => '#ff4d4f'],
                                ['label' => 'Max G-Force Allowance: 400G', 'value' => '400G', 'width' => '100%', 'color' => '#ff4d4f']
                            ]
                        ],
                        [
                            'name' => 'Snell M2020R',
                            'active' => true,
                            'metrics' => [
                                ['label' => 'Double Impact Test: Passed (Double Hit)', 'value' => 'Passed', 'width' => '100%', 'color' => '#52c41a'],
                                ['label' => 'Max G-Force Allowance: 275G', 'value' => '275G', 'width' => '68.75%', 'color' => '#52c41a']
                            ]
                        ]
                    ]
                ];
                break;

            case 'sharp-uk-rating':
                $tech_specs = [
                    ['label' => 'Impact Sites', 'value' => '32 Locations'],
                    ['label' => 'Anvils Tested', 'value' => 'Flat & Kerbed'],
                    ['label' => 'Testing Speeds', 'value' => '3 Rates (to 8.5 m/s)'],
                    ['label' => 'Score Format', 'value' => '1 - 5 Stars']
                ];
                $comparison = [
                    'title' => 'SHARP UK Rating vs Standard ECE 22.06',
                    'subtitle' => 'Additional consumer testing metrics beyond baseline pass/fail',
                    'cols' => [
                        [
                            'name' => 'ECE 22.06 (Baseline)',
                            'active' => false,
                            'metrics' => [
                                ['label' => 'Testing Locations: 18 Points', 'value' => '18', 'width' => '56%', 'color' => 'var(--hs-muted)'],
                                ['label' => 'Output Type: Pass/Fail Only', 'value' => 'Pass/Fail', 'width' => '50%', 'color' => 'var(--hs-muted)']
                            ]
                        ],
                        [
                            'name' => 'SHARP UK Rating',
                            'active' => true,
                            'metrics' => [
                                ['label' => 'Testing Locations: 32 Points', 'value' => '32', 'width' => '100%', 'color' => '#1890ff'],
                                ['label' => 'Output Type: 1-5 Star Scorecard', 'value' => '5-Star System', 'width' => '100%', 'color' => '#52c41a']
                            ]
                        ]
                    ]
                ];
                break;

            case 'isi-is-4151':
                $tech_specs = [
                    ['label' => 'Impact G-Force', 'value' => '300G Max'],
                    ['label' => 'Drop Velocity', 'value' => '7.0 m/s'],
                    ['label' => 'Retention Load', 'value' => '150 kg static'],
                    ['label' => 'Rigidity Test', 'value' => 'Transverse Load']
                ];
                $comparison = [
                    'title' => 'ISI IS 4151 vs DOT FMVSS 218',
                    'subtitle' => 'Comparing legal minimums for India and the USA',
                    'cols' => [
                        [
                            'name' => 'DOT FMVSS 218',
                            'active' => false,
                            'metrics' => [
                                ['label' => 'Max G-Force Allowance: 400G', 'value' => '400G', 'width' => '100%', 'color' => '#ff4d4f'],
                                ['label' => 'Government Lab Audit: No', 'value' => 'No', 'width' => '20%', 'color' => 'var(--hs-muted)']
                            ]
                        ],
                        [
                            'name' => 'ISI IS 4151',
                            'active' => true,
                            'metrics' => [
                                ['label' => 'Max G-Force Allowance: 300G', 'value' => '300G', 'width' => '75%', 'color' => '#52c41a'],
                                ['label' => 'Government Lab Audit: Yes (Mandatory BIS)', 'value' => 'Yes', 'width' => '100%', 'color' => '#1890ff']
                            ]
                        ]
                    ]
                ];
                break;
        }
        ?>

        <article <?php post_class('hs-standard-detail'); ?>>
            
            <!-- Hero Header Banner -->
            <header class="hs-standard-detail__hero">
                <div class="hs-standard-detail__hero-gradient" aria-hidden="true"></div>
                <div class="hs-standard-detail__hero-inner">
                    <div class="hs-standard-detail__hero-logo">
                        <img src="<?php echo esc_url($logoUrl); ?>" alt="<?php the_title_attribute(); ?>">
                    </div>
                    <div class="hs-standard-detail__hero-content">
                        <span class="hs-eyebrow">Safety Protocol Specification</span>
                        <h1 class="hs-standard-detail__hero-title"><?php the_title(); ?></h1>
                        
                        <div class="hs-standard-detail__hero-meta">
                            <span class="hs-standard-detail__badge">Verified Database CPT</span>
                            <?php if ($officialUrl) : ?>
                                <a href="<?php echo esc_url($officialUrl); ?>" target="_blank" class="hs-standard-detail__external-link">
                                    Official Source
                                    <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6m3-3h7m0 0v7m0-7L10 14"/></svg>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </header>

            <!-- 2-Column Responsive Layout -->
            <div class="hs-standard-layout">
                
                <!-- Left: Main informational content -->
                <div class="hs-standard-layout__main">
                    
                    <!-- Story & Background Section -->
                    <section class="hs-panel hs-standard-section">
                        <header class="hs-standard-section__head">
                            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 20h9M3 20h7M3 12h14M3 4h18"/></svg>
                            <h2>Story & Background</h2>
                        </header>
                        <div class="hs-prose">
                            <?php echo wp_kses_post($storyHistory ?: get_the_content()); ?>
                        </div>
                        
                        <?php if (!empty($milestones)) : ?>
                            <div class="hs-standard-timeline">
                                <?php foreach ($milestones as $item) : ?>
                                    <div class="hs-standard-timeline__item">
                                        <div class="hs-standard-timeline__marker"></div>
                                        <div class="hs-standard-timeline__content">
                                            <span class="hs-standard-timeline__year"><?php echo esc_html($item['year']); ?></span>
                                            <p class="hs-standard-timeline__text"><?php echo esc_html($item['text']); ?></p>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </section>

                    <!-- Testing Protocol Section -->
                    <section class="hs-panel hs-standard-section">
                        <header class="hs-standard-section__head">
                            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                            <h2>Testing Protocol & Methods</h2>
                        </header>
                        <div class="hs-prose">
                            <?php echo wp_kses_post($testingInfo ?: 'Technical testing protocol information for this standard is currently being updated.'); ?>
                        </div>
                    </section>

                    <!-- Specifications & Comparisons Section -->
                    <?php if (!empty($tech_specs)) : ?>
                    <section class="hs-panel hs-standard-section hs-standard-section--benchmarks">
                        <header class="hs-standard-section__head">
                            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21.21 15.89A10 10 0 1 1 8 2.83M22 12A10 10 0 0 0 12 2v10z"/></svg>
                            <h2>Technical Specifications & Performance</h2>
                        </header>
                        
                        <div class="hs-standard-specs-grid">
                            <?php foreach ($tech_specs as $spec) : ?>
                                <div class="hs-standard-spec-card">
                                    <span class="hs-standard-spec-card__label"><?php echo esc_html($spec['label']); ?></span>
                                    <span class="hs-standard-spec-card__value"><?php echo esc_html($spec['value']); ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <?php if ($comparison) : ?>
                            <div class="hs-standard-comparison">
                                <h3 class="hs-standard-comparison__title"><?php echo esc_html($comparison['title']); ?></h3>
                                <p class="hs-standard-comparison__subtitle"><?php echo esc_html($comparison['subtitle']); ?></p>
                                
                                <div class="hs-standard-comparison__grid">
                                    <?php foreach ($comparison['cols'] as $col) : ?>
                                        <div class="hs-standard-comparison__col <?php echo $col['active'] ? 'hs-standard-comparison__col--active' : ''; ?>">
                                            <div class="hs-standard-comparison__col-header">
                                                <span class="hs-standard-comparison__col-name"><?php echo esc_html($col['name']); ?></span>
                                                <?php if ($col['active']) : ?>
                                                    <span class="hs-badge hs-badge--accent">Benchmark</span>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <div class="hs-standard-comparison__metrics">
                                                <?php foreach ($col['metrics'] as $metric) : ?>
                                                    <div class="hs-standard-comparison__metric">
                                                        <div class="hs-standard-comparison__metric-info">
                                                            <span class="hs-standard-comparison__metric-label"><?php echo esc_html($metric['label']); ?></span>
                                                        </div>
                                                        <div class="hs-standard-comparison__bar-bg">
                                                            <div class="hs-standard-comparison__bar" style="width: <?php echo esc_attr($metric['width']); ?>; background: <?php echo esc_attr($metric['color']); ?>;"></div>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </section>
                    <?php endif; ?>

                </div>

                <!-- Right: Actionable sidebar widgets -->
                <aside class="hs-standard-layout__sidebar">
                    
                    <!-- Market Status Sidebar Widget -->
                    <div class="hs-panel hs-standard-side-card">
                        <h3>Market & Registry</h3>
                        
                        <div class="hs-standard-side-card__group">
                            <span class="hs-standard-side-card__label">Active Regions</span>
                            <div class="hs-standard-side-card__tags">
                                <?php if (is_array($regions) && !empty($regions)) : foreach ($regions as $region) : ?>
                                    <span class="hs-tag"><?php echo esc_html($region); ?></span>
                                <?php endforeach; else : ?>
                                    <span class="hs-standard-side-card__none">Global scope</span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="hs-standard-side-card__group">
                            <span class="hs-standard-side-card__label">Legal Enforcement</span>
                            <p class="hs-standard-side-card__text">
                                <?php if (is_array($mandatory) && !empty($mandatory)) : ?>
                                    Mandatory requirement for markets in <strong><?php echo implode(', ', $mandatory); ?></strong>.
                                <?php else : ?>
                                    Voluntary certification or subject to regional racing organization rules.
                                <?php endif; ?>
                            </p>
                        </div>

                        <?php if ($officialUrl) : ?>
                            <a href="<?php echo esc_url($officialUrl); ?>" target="_blank" class="hs-btn hs-btn--ghost hs-btn--sm hs-standard-side-card__btn">
                                Visit Official Site
                                <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6m3-3h7m0 0v7m0-7L10 14"/></svg>
                            </a>
                        <?php endif; ?>
                    </div>

                    <!-- Helmet Discovery Sidebar Widget -->
                    <div class="hs-panel hs-standard-side-card hs-standard-side-card--discovery">
                        <div class="hs-standard-side-card__stats">
                            <span class="hs-standard-side-card__stat-val"><?php echo number_format($helmet_count); ?></span>
                            <span class="hs-standard-side-card__stat-lbl">Certified Products</span>
                        </div>
                        <h3>Certified Helmets</h3>
                        <p>Browse our catalog of verified helmets meeting this protocol.</p>
                        
                        <a href="<?php echo esc_url($certificationUrl); ?>" class="hs-btn hs-btn--primary hs-btn--sm hs-standard-side-card__btn">
                            Explore Certified Helmets
                            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12h14m-7-7 7 7-7 7"/></svg>
                        </a>
                    </div>

                    <!-- Resource Library Sidebar Widget -->
                    <?php if (!empty($docLinks)) : ?>
                    <div class="hs-panel hs-standard-side-card">
                        <h3>Technical Library</h3>
                        <p class="hs-standard-side-card__hint">Official references and publications.</p>
                        
                        <ul class="hs-standard-doc-list">
                            <?php foreach ($docLinks as $link) : ?>
                                <li>
                                    <a href="<?php echo esc_url($link['url']); ?>" target="_blank" class="hs-standard-doc-link">
                                        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M16 13H8m8 4H8m2-8H8"/></svg>
                                        <span><?php echo esc_html($link['label']); ?></span>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <?php endif; ?>

                </aside>
            </div>

            <!-- Page-to-Page Navigation Footer -->
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
