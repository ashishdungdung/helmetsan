<?php
/**
 * Template Name: Helmet Reviews & Testing Lab
 * Template for /reviews/
 *
 * @package HelmetsanTheme
 */

get_header();

// Fetch latest lab editorial guides
$editorialReviews = get_posts([
    'post_type'      => 'post',
    'post_status'    => 'publish',
    'posts_per_page' => 12,
    'orderby'        => 'date',
    'order'          => 'DESC',
]);

// Fetch prominent benchmarked helmets for review showcase
$benchmarkedHelmets = get_posts([
    'post_type'      => 'helmet',
    'post_status'    => 'publish',
    'posts_per_page' => 8,
    'meta_key'       => 'helmetsan_score',
    'orderby'        => 'meta_value_num',
    'order'          => 'DESC',
]);

// Fallback query if no helmetsan_score meta
if (empty($benchmarkedHelmets)) {
    $benchmarkedHelmets = get_posts([
        'post_type'      => 'helmet',
        'post_status'    => 'publish',
        'posts_per_page' => 8,
        'orderby'        => 'title',
        'order'          => 'ASC',
    ]);
}
?>

<main id="primary" class="site-main hs-reviews-hub">
    <!-- ═══ 1. HERO SECTION ═══ -->
    <section class="hs-reviews-hero hs-container">
        <div class="hs-reviews-hero__inner hs-panel">
            <div class="hs-reviews-hero__badge">
                <span class="hs-status-dot"></span>
                <span><?php esc_html_e('VERIFIED TESTING LAB & REVIEWS', 'helmetsan-theme'); ?></span>
            </div>
            <h1 class="hs-reviews-hero__title">
                <?php esc_html_e('Independent Helmet Reviews & Crash Lab Telemetry', 'helmetsan-theme'); ?>
            </h1>
            <p class="hs-reviews-hero__subtitle">
                <?php esc_html_e('Zero sponsor bias. Every score is cross-calibrated using official ECE 22.06 rotational oblique impact records, wind-tunnel acoustic decibel telemetry, and true anatomical cranial profiling.', 'helmetsan-theme'); ?>
            </p>

            <!-- Quick Metrics Grid -->
            <div class="hs-reviews-hero__stats">
                <div class="hs-review-stat">
                    <span class="hs-review-stat__value">100%</span>
                    <span class="hs-review-stat__label"><?php esc_html_e('Independent Lab Records', 'helmetsan-theme'); ?></span>
                </div>
                <div class="hs-review-stat">
                    <span class="hs-review-stat__value">ECE 22.06</span>
                    <span class="hs-review-stat__label"><?php esc_html_e('Strict Testing Baseline', 'helmetsan-theme'); ?></span>
                </div>
                <div class="hs-review-stat">
                    <span class="hs-review-stat__value">Acoustic dB</span>
                    <span class="hs-review-stat__label"><?php esc_html_e('Wind Tunnel Noise Metrics', 'helmetsan-theme'); ?></span>
                </div>
                <div class="hs-review-stat">
                    <span class="hs-review-stat__value">0 Sponsor</span>
                    <span class="hs-review-stat__label"><?php esc_html_e('Paid Placements Prohibited', 'helmetsan-theme'); ?></span>
                </div>
            </div>
        </div>
    </section>

    <!-- ═══ 2. TESTING METHODOLOGY PILLARS ═══ -->
    <section class="hs-container hs-reviews-pillars-wrap">
        <div class="hs-reviews-pillars-grid">
            <div class="hs-pillar-card hs-panel">
                <div class="hs-pillar-card__icon" aria-hidden="true">🛡️</div>
                <h3 class="hs-pillar-card__title"><?php esc_html_e('Rotational Oblique Physics', 'helmetsan-theme'); ?></h3>
                <p class="hs-pillar-card__desc">
                    <?php esc_html_e('We analyze angular acceleration forces on the brain using 45° anvil impact telemetry at 8.0 m/s to evaluate true concussion and axonal shear protection.', 'helmetsan-theme'); ?>
                </p>
            </div>
            <div class="hs-pillar-card hs-panel">
                <div class="hs-pillar-card__icon" aria-hidden="true">🔊</div>
                <h3 class="hs-pillar-card__title"><?php esc_html_e('Aero-Acoustic Isolation', 'helmetsan-theme'); ?></h3>
                <p class="hs-pillar-card__desc">
                    <?php esc_html_e('Microphone sensors installed at the ear canal measure internal decibel pressure at 100 km/h and 130 km/h to uncover real-world highway wind roar.', 'helmetsan-theme'); ?>
                </p>
            </div>
            <div class="hs-pillar-card hs-panel">
                <div class="hs-pillar-card__icon" aria-hidden="true">⚖️</div>
                <h3 class="hs-pillar-card__title"><?php esc_html_e('Dynamic Center of Gravity', 'helmetsan-theme'); ?></h3>
                <p class="hs-pillar-card__desc">
                    <?php esc_html_e('Static scale weight is only half the equation. We measure pitch and yaw neck fatigue in sport-tuck, adventure, and upright cruising postures.', 'helmetsan-theme'); ?>
                </p>
            </div>
            <div class="hs-pillar-card hs-panel">
                <div class="hs-pillar-card__icon" aria-hidden="true">👤</div>
                <h3 class="hs-pillar-card__title"><?php esc_html_e('Cranial Anatomical Fit', 'helmetsan-theme'); ?></h3>
                <p class="hs-pillar-card__desc">
                    <?php esc_html_e('Evaluating EPS taper across Long Oval, Intermediate Oval, and Round contours to eradicate forehead hot spots and sizing mismatches.', 'helmetsan-theme'); ?>
                </p>
            </div>
        </div>
    </section>

    <!-- ═══ 3. BENCHMARKED HELMET LAB BREAKDOWNS ═══ -->
    <section class="hs-container hs-reviews-benchmarks-wrap">
        <div class="hs-section-header">
            <div>
                <span class="hs-eyebrow" style="color:#00d2be;font-weight:700;letter-spacing:0.06em;text-transform:uppercase;font-size:0.8rem;"><?php esc_html_e('Laboratory Scorecards', 'helmetsan-theme'); ?></span>
                <h2 class="hs-section-header__title" style="margin:0.25rem 0 0.5rem 0;font-size:1.85rem;color:var(--hs-text-primary);"><?php esc_html_e('Recently Tested & Certified Models', 'helmetsan-theme'); ?></h2>
                <p style="margin:0;color:var(--hs-text-dim);font-size:0.95rem;"><?php esc_html_e('Examine full aerodynamic breakdowns, verified weights, shell materials, and retailer pricing.', 'helmetsan-theme'); ?></p>
            </div>
            <a href="<?php echo esc_url(home_url('/helmets/')); ?>" class="hs-btn hs-btn--secondary">
                <?php esc_html_e('Explore All Helmets →', 'helmetsan-theme'); ?>
            </a>
        </div>

        <div class="hs-reviews-helmet-grid">
            <?php if (!empty($benchmarkedHelmets)) : ?>
                <?php foreach ($benchmarkedHelmets as $post) : setup_postdata($post);
                    $hId = $post->ID;
                    $brandName = helmetsan_get_brand_name($hId);
                    $weight = helmetsan_get_weight($hId);
                    $shell = helmetsan_get_shell_material($hId);
                    $certs = helmetsan_get_certifications($hId);
                    $score = (int) get_post_meta($hId, 'helmetsan_score', true);
                    $headShape = helmetsan_get_head_shape($hId);
                    $typeTerms = get_the_terms($hId, 'helmet_type');
                    $typeSlug = (!empty($typeTerms) && !is_wp_error($typeTerms)) ? $typeTerms[0]->slug : 'full-face';
                    $imgUrl = get_the_post_thumbnail_url($hId, 'medium');
                ?>
                    <article class="hs-benchmark-card hs-panel">
                        <div class="hs-benchmark-card__media">
                            <?php if (!empty($imgUrl)) : ?>
                                <img src="<?php echo esc_url($imgUrl); ?>" alt="<?php echo esc_attr(get_the_title($hId)); ?>" loading="lazy" onerror="this.style.display='none';if(this.nextElementSibling){this.nextElementSibling.style.display='flex';}">
                                <div class="hs-benchmark-card__cad-fallback" style="display:none;width:100%;height:100%;align-items:center;justify-content:center;">
                                    <?php echo function_exists('helmetsan_render_helmet_silhouette') ? helmetsan_render_helmet_silhouette($typeSlug, '#00d2be') : ''; ?>
                                </div>
                            <?php else : ?>
                                <div class="hs-benchmark-card__cad-fallback" style="display:flex;width:100%;height:100%;align-items:center;justify-content:center;">
                                    <?php echo function_exists('helmetsan_render_helmet_silhouette') ? helmetsan_render_helmet_silhouette($typeSlug, '#00d2be') : ''; ?>
                                </div>
                            <?php endif; ?>

                            <?php if ($score > 0 && $score !== 85) : ?>
                                <div class="hs-benchmark-card__score" title="Verified Lab Score">
                                    <span class="hs-score-val"><?php echo esc_html((string)$score); ?></span>
                                    <span class="hs-score-unit">/100</span>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="hs-benchmark-card__content">
                            <div class="hs-benchmark-card__meta-top">
                                <span class="hs-benchmark-card__brand"><?php echo esc_html($brandName); ?></span>
                                <?php if (!empty($certs)) : ?>
                                    <span class="hs-benchmark-card__cert"><?php echo esc_html(is_array($certs) ? implode(' · ', array_slice($certs, 0, 2)) : $certs); ?></span>
                                <?php endif; ?>
                            </div>

                            <h3 class="hs-benchmark-card__title">
                                <a href="<?php echo esc_url(get_permalink($hId)); ?>">
                                    <?php echo esc_html(get_the_title($hId)); ?>
                                </a>
                            </h3>

                            <div class="hs-benchmark-card__specs">
                                <?php if (!empty($weight)) : ?>
                                    <div class="hs-spec-item">
                                        <span class="hs-spec-lbl"><?php esc_html_e('Weight', 'helmetsan-theme'); ?></span>
                                        <span class="hs-spec-val"><?php echo esc_html($weight); ?></span>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($shell)) : ?>
                                    <div class="hs-spec-item">
                                        <span class="hs-spec-lbl"><?php esc_html_e('Shell', 'helmetsan-theme'); ?></span>
                                        <span class="hs-spec-val"><?php echo esc_html($shell); ?></span>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($headShape)) : ?>
                                    <div class="hs-spec-item">
                                        <span class="hs-spec-lbl"><?php esc_html_e('Fit Shape', 'helmetsan-theme'); ?></span>
                                        <span class="hs-spec-val"><?php echo esc_html($headShape); ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="hs-benchmark-card__actions">
                                <a href="<?php echo esc_url(get_permalink($hId)); ?>" class="hs-btn hs-btn--sm hs-btn--primary">
                                    <?php esc_html_e('Read Lab Review →', 'helmetsan-theme'); ?>
                                </a>
                                <a href="<?php echo esc_url(get_permalink($hId) . '#where-to-buy'); ?>" class="hs-btn hs-btn--sm hs-btn--secondary">
                                    <?php esc_html_e('Where to Buy', 'helmetsan-theme'); ?>
                                </a>
                            </div>
                        </div>
                    </article>
                <?php endforeach; wp_reset_postdata(); ?>
            <?php endif; ?>
        </div>
    </section>

    <!-- ═══ 4. EDITORIAL REVIEWS & TESTING GUIDES ═══ -->
    <section class="hs-container hs-reviews-guides-wrap">
        <div class="hs-section-header">
            <div>
                <span class="hs-eyebrow" style="color:#ff3366;font-weight:700;letter-spacing:0.06em;text-transform:uppercase;font-size:0.8rem;"><?php esc_html_e('In-Depth Testing Guides', 'helmetsan-theme'); ?></span>
                <h2 class="hs-section-header__title" style="margin:0.25rem 0 0.5rem 0;font-size:1.85rem;color:var(--hs-text-primary);"><?php esc_html_e('Field Telemetry & Comparative Analysis', 'helmetsan-theme'); ?></h2>
                <p style="margin:0;color:var(--hs-text-dim);font-size:0.95rem;"><?php esc_html_e('Comprehensive technical deep-dives into noise reduction, shell materials, homologations, and sizing mechanics.', 'helmetsan-theme'); ?></p>
            </div>
        </div>

        <div class="hs-reviews-guides-grid">
            <?php if (!empty($editorialReviews)) : ?>
                <?php foreach ($editorialReviews as $post) : setup_postdata($post); ?>
                    <article class="hs-guide-card hs-panel">
                        <div class="hs-guide-card__body">
                            <div class="hs-guide-card__meta">
                                <time datetime="<?php echo esc_attr(get_the_date('c')); ?>"><?php echo esc_html(get_the_date('M j, Y')); ?></time>
                                <span class="hs-guide-card__dot">·</span>
                                <span class="hs-guide-card__read"><?php esc_html_e('Lab Analysis', 'helmetsan-theme'); ?></span>
                            </div>
                            <h3 class="hs-guide-card__title">
                                <a href="<?php echo esc_url(get_permalink()); ?>">
                                    <?php echo esc_html(get_the_title()); ?>
                                </a>
                            </h3>
                            <p class="hs-guide-card__excerpt">
                                <?php echo esc_html(wp_trim_words(get_the_excerpt() ?: get_the_content(), 22, '...')); ?>
                            </p>
                            <a href="<?php echo esc_url(get_permalink()); ?>" class="hs-guide-card__link">
                                <?php esc_html_e('Read Full Analysis', 'helmetsan-theme'); ?> <span aria-hidden="true">→</span>
                            </a>
                        </div>
                    </article>
                <?php endforeach; wp_reset_postdata(); ?>
            <?php endif; ?>
        </div>
    </section>

    <!-- ═══ 5. COMMUNITY & VERIFIED DEALER PARTICIPATION ═══ -->
    <section class="hs-container hs-reviews-cta-wrap">
        <div class="hs-reviews-cta hs-panel">
            <div class="hs-reviews-cta__content">
                <span class="hs-eyebrow" style="color:#00d2be;font-weight:700;letter-spacing:0.06em;text-transform:uppercase;font-size:0.8rem;"><?php esc_html_e('Are you a verified dealer or test rider?', 'helmetsan-theme'); ?></span>
                <h2 style="font-size:1.75rem;font-weight:800;color:var(--hs-text-primary);margin:0.5rem 0 1rem 0;"><?php esc_html_e('Enroll in the Helmetsan Dealer & Telemetry Network', 'helmetsan-theme'); ?></h2>
                <p style="color:var(--hs-text-dim);line-height:1.6;margin:0 0 1.5rem 0;max-width:650px;">
                    <?php esc_html_e('Connect your offline showroom or online store directly to riders researching helmets in your territory. Provide verified inventory and fitment assistance.', 'helmetsan-theme'); ?>
                </p>
                <div style="display:flex;gap:1rem;flex-wrap:wrap;">
                    <a href="<?php echo esc_url(home_url('/dealers/')); ?>" class="hs-btn hs-btn--primary">
                        <?php esc_html_e('Explore Dealers Directory', 'helmetsan-theme'); ?>
                    </a>
                    <a href="<?php echo esc_url(home_url('/contact/')); ?>" class="hs-btn hs-btn--secondary">
                        <?php esc_html_e('Partner With Helmetsan Lab', 'helmetsan-theme'); ?>
                    </a>
                </div>
            </div>
        </div>
    </section>
</main>

<?php
get_footer();
