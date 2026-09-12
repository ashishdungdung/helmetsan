<?php
/**
 * Safety Standards Archive — Premium Hub Edition.
 *
 * Positions Helmetsan as the authority on helmet safety certifications
 * with hero, comparison table, premium cards, educational content, and CTA.
 *
 * @package HelmetsanTheme
 */

get_header();

$themeDir = get_stylesheet_directory_uri();
$hero_img = $themeDir . '/assets/images/hubs/safety_standards_hub_hero.png';

// ── Gather data for all standards ─────────────────────────────────────────
$standards_query = new WP_Query([
    'post_type'      => 'safety_standard',
    'post_status'    => 'publish',
    'posts_per_page' => 20,
    'orderby'        => 'menu_order title',
    'order'          => 'ASC',
]);

if (! $standards_query->have_posts()) {
    $standards_query = new WP_Query([
        'post_type'      => 'safety_standard',
        'post_status'    => 'publish',
        'posts_per_page' => 20,
        'orderby'        => 'menu_order title',
        'order'          => 'ASC',
        'lang'           => '',
    ]);
}

// Pre-build enriched data array for comparison table + cards
$standards_data = [];
$total_helmets  = 0;
$total_regions  = [];

if ($standards_query->have_posts()) :
    while ($standards_query->have_posts()) : $standards_query->the_post();
        $id   = get_the_ID();
        $slug = get_post_field('post_name', $id);

        // Metadata
        $regionsJson   = (string) get_post_meta($id, 'standard_regions_json', true);
        $mandatoryJson = (string) get_post_meta($id, 'mandatory_markets_json', true);
        $certSlug      = (string) get_post_meta($id, 'linked_certification_slug', true);

        $regions   = json_decode($regionsJson, true) ?: [];
        $mandatory = json_decode($mandatoryJson, true) ?: [];

        // Count helmets linked to this certification
        $helmet_count = 0;
        $cert_url     = '#';
        if ($certSlug) {
            $term = get_term_by('slug', $certSlug, 'certification');
            if ($term && function_exists('pll_get_term') && function_exists('pll_current_language')) {
                $transTermId = pll_get_term($term->term_id, pll_current_language());
                if ($transTermId) {
                    $transTerm = get_term($transTermId, 'certification');
                    if ($transTerm && ! is_wp_error($transTerm)) {
                        $term = $transTerm;
                    }
                }
            }
            if ($term && ! is_wp_error($term)) {
                $helmet_count = $term->count;
                $cert_url     = get_term_link($term);
            }
        }
        $total_helmets += $helmet_count;
        $total_regions  = array_merge($total_regions, $regions);

        // Image
        $img = get_the_post_thumbnail_url($id, 'large');
        if (!$img) {
            $extensions = ['png', 'svg', 'jpg', 'jpeg', 'webp', 'avif'];
            foreach ($extensions as $ext) {
                $local_path = '/assets/images/hubs/safety_standard/' . $slug . '.' . $ext;
                if (file_exists(get_stylesheet_directory() . $local_path)) {
                    $img = $themeDir . $local_path;
                    break;
                }
            }
            if (!$img) {
                $img = $hero_img;
            }
        }

        // Enrichment data per standard (for the comparison table)
        $enrichment = [
            'dot-fmvss-218' => [
                'impact'     => '400G max',
                'rotational' => false,
                'rigor'      => 2,
                'scope'      => 'USA (Mandatory)',
                'key_stat'   => '400G Threshold',
                'tag_color'  => 'red',
            ],
            'ece-22-06' => [
                'impact'     => '275G max',
                'rotational' => true,
                'rigor'      => 5,
                'scope'      => 'Global (50+ Countries)',
                'key_stat'   => '12 Sensor Points',
                'tag_color'  => 'green',
            ],
            'snell-m2020r' => [
                'impact'     => '275G max',
                'rotational' => false,
                'rigor'      => 4,
                'scope'      => 'USA (Voluntary)',
                'key_stat'   => '275G Threshold',
                'tag_color'  => 'blue',
            ],
            'sharp-uk-rating' => [
                'impact'     => 'Multiple Angles',
                'rotational' => true,
                'rigor'      => 5,
                'scope'      => 'UK (Rating System)',
                'key_stat'   => '5-Star Rating',
                'tag_color'  => 'purple',
            ],
            'isi-is-4151' => [
                'impact'     => 'Varies',
                'rotational' => false,
                'rigor'      => 2,
                'scope'      => 'India (Mandatory)',
                'key_stat'   => 'BIS Certified',
                'tag_color'  => 'orange',
            ],
        ];

        $meta = $enrichment[$slug] ?? [
            'impact'     => 'N/A',
            'rotational' => false,
            'rigor'      => 3,
            'scope'      => implode(', ', array_slice($regions, 0, 2)) ?: 'Global',
            'key_stat'   => count($regions) . ' Regions',
            'tag_color'  => 'blue',
        ];

        $standards_data[] = [
            'id'           => $id,
            'title'        => get_the_title(),
            'slug'         => $slug,
            'link'         => get_permalink(),
            'excerpt'      => get_the_excerpt() ?: 'Learn about the ' . get_the_title() . ' safety protocol.',
            'img'          => $img,
            'regions'      => $regions,
            'mandatory'    => $mandatory,
            'helmet_count' => $helmet_count,
            'cert_url'     => $cert_url,
            'meta'         => $meta,
        ];
    endwhile;
    wp_reset_postdata();
endif;

$unique_regions = count(array_unique($total_regions));
$standards_count = count($standards_data);
?>

<!-- ═══════════════════════════════════════════════════════════════════════════
     SECTION 1: HERO
     ═══════════════════════════════════════════════════════════════════════ -->
<section class="hs-safety-hub__hero">
    <div class="hs-safety-hub__hero-gradient" aria-hidden="true"></div>
    <div class="hs-safety-hub__hero-inner">
        <p class="hs-eyebrow">Safety Intelligence Hub</p>
        <h1 class="hs-safety-hub__hero-title">Understanding Helmet Safety Standards</h1>
        <p class="hs-safety-hub__hero-desc">
            Not all helmets are created equal. Safety certifications define testing protocols, impact thresholds,
            and legal requirements that determine real-world protection. We decode every major global standard so you can ride informed.
        </p>
        <div class="hs-safety-hub__hero-stats">
            <div class="hs-safety-hub__stat">
                <span class="hs-safety-hub__stat-value"><?php echo esc_html($standards_count); ?></span>
                <span class="hs-safety-hub__stat-label">Standards Tracked</span>
            </div>
            <div class="hs-safety-hub__stat">
                <span class="hs-safety-hub__stat-value"><?php echo esc_html($unique_regions ?: '30+'); ?></span>
                <span class="hs-safety-hub__stat-label">Countries Covered</span>
            </div>
            <div class="hs-safety-hub__stat">
                <span class="hs-safety-hub__stat-value"><?php echo number_format($total_helmets); ?></span>
                <span class="hs-safety-hub__stat-label">Certified Helmets</span>
            </div>
        </div>
        <div class="hs-safety-hub__hero-actions">
            <a href="#standards-comparison" class="hs-btn hs-btn--primary hs-btn--md">
                Compare All Standards
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M7 17l9.2-9.2M7 7h10v10"/></svg>
            </a>
            <a href="#standards-grid" class="hs-btn hs-btn--ghost hs-btn--md">
                Browse Standards
            </a>
        </div>
    </div>
</section>

<!-- ═══════════════════════════════════════════════════════════════════════════
     SECTION 2: COMPARISON TABLE — "Standards at a Glance"
     ═══════════════════════════════════════════════════════════════════════ -->
<section class="hs-safety-hub__comparison" id="standards-comparison">
    <header class="hs-safety-hub__section-head">
        <p class="hs-eyebrow">Head-to-Head</p>
        <h2>Standards at a Glance</h2>
        <p class="hs-safety-hub__section-desc">
            How do the world's major helmet certifications stack up against each other?
        </p>
    </header>

    <div class="hs-safety-hub__table-wrap">
        <table class="hs-safety-hub__table">
            <thead>
                <tr>
                    <th>Standard</th>
                    <th>Region / Scope</th>
                    <th>Impact Limit</th>
                    <th>Rotational Test</th>
                    <th>Rigor</th>
                    <th>Helmets</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($standards_data as $std) : ?>
                <tr>
                    <td>
                        <a href="<?php echo esc_url($std['link']); ?>" class="hs-safety-hub__table-name">
                            <?php echo esc_html($std['title']); ?>
                        </a>
                    </td>
                    <td>
                        <span class="hs-safety-hub__scope-tag hs-safety-hub__scope-tag--<?php echo esc_attr($std['meta']['tag_color']); ?>">
                            <?php echo esc_html($std['meta']['scope']); ?>
                        </span>
                    </td>
                    <td class="hs-safety-hub__table-mono"><?php echo esc_html($std['meta']['impact']); ?></td>
                    <td>
                        <?php if ($std['meta']['rotational']) : ?>
                            <span class="hs-safety-hub__check hs-safety-hub__check--yes" title="Yes">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="M20 6L9 17l-5-5"/></svg>
                                Oblique
                            </span>
                        <?php else : ?>
                            <span class="hs-safety-hub__check hs-safety-hub__check--no" title="No">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="M18 6L6 18M6 6l12 12"/></svg>
                                No
                            </span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="hs-safety-hub__rigor">
                            <?php for ($i = 1; $i <= 5; $i++) : ?>
                                <span class="hs-safety-hub__star <?php echo $i <= $std['meta']['rigor'] ? 'is-active' : ''; ?>">★</span>
                            <?php endfor; ?>
                        </div>
                    </td>
                    <td>
                        <?php if ($std['helmet_count'] > 0) : ?>
                            <a href="<?php echo esc_url($std['cert_url']); ?>" class="hs-safety-hub__helmet-count">
                                <?php echo number_format($std['helmet_count']); ?>
                            </a>
                        <?php else : ?>
                            <span class="hs-safety-hub__helmet-count hs-safety-hub__helmet-count--zero">—</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<!-- ═══════════════════════════════════════════════════════════════════════════
     SECTION 3: STANDARDS GRID — Premium Cards
     ═══════════════════════════════════════════════════════════════════════ -->
<section class="hs-safety-hub__grid-section" id="standards-grid">
    <header class="hs-safety-hub__section-head">
        <p class="hs-eyebrow">Explore</p>
        <h2>Global Safety Standards</h2>
        <p class="hs-safety-hub__section-desc">
            Dive into the technical details, testing protocols, and regional requirements of each certification.
        </p>
    </header>

    <div class="hs-safety-hub__cards">
        <?php foreach ($standards_data as $std) : ?>
        <a href="<?php echo esc_url($std['link']); ?>" class="hs-safety-hub__card">
            <div class="hs-safety-hub__card-header">
                <div class="hs-safety-hub__card-logo">
                    <img src="<?php echo esc_url($std['img']); ?>" alt="<?php echo esc_attr($std['title']); ?>" loading="lazy">
                </div>
                <span class="hs-safety-hub__card-scope hs-safety-hub__scope-tag--<?php echo esc_attr($std['meta']['tag_color']); ?>">
                    <?php echo esc_html($std['meta']['scope']); ?>
                </span>
            </div>
            <div class="hs-safety-hub__card-body">
                <h3 class="hs-safety-hub__card-title"><?php echo esc_html($std['title']); ?></h3>
                <p class="hs-safety-hub__card-desc"><?php echo esc_html($std['excerpt']); ?></p>
                <div class="hs-safety-hub__card-meta">
                    <span class="hs-safety-hub__card-stat">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                        <?php echo esc_html($std['meta']['key_stat']); ?>
                    </span>
                    <?php if ($std['helmet_count'] > 0) : ?>
                    <span class="hs-safety-hub__card-stat">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
                        <?php echo number_format($std['helmet_count']); ?> Helmets
                    </span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="hs-safety-hub__card-footer">
                <span class="hs-safety-hub__card-cta">
                    Explore Standard
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12h14m-7-7 7 7-7 7"/></svg>
                </span>
                <div class="hs-safety-hub__card-rigor" aria-label="Rigor: <?php echo esc_attr($std['meta']['rigor']); ?> of 5">
                    <?php for ($i = 1; $i <= 5; $i++) : ?>
                        <span class="hs-safety-hub__star <?php echo $i <= $std['meta']['rigor'] ? 'is-active' : ''; ?>">★</span>
                    <?php endfor; ?>
                </div>
            </div>
        </a>
        <?php endforeach; ?>
    </div>
</section>

<!-- ═══════════════════════════════════════════════════════════════════════════
     SECTION 4: EDUCATION — "Why Certifications Matter"
     ═══════════════════════════════════════════════════════════════════════ -->
<section class="hs-safety-hub__education">
    <header class="hs-safety-hub__section-head">
        <p class="hs-eyebrow">Knowledge Base</p>
        <h2>Why Certifications Matter</h2>
        <p class="hs-safety-hub__section-desc">
            A certification sticker isn't just decoration — it represents thousands of hours of testing, engineering, and regulatory oversight.
        </p>
    </header>

    <div class="hs-safety-hub__edu-grid">
        <div class="hs-safety-hub__edu-card">
            <div class="hs-safety-hub__edu-icon">
                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
            </div>
            <h3>Impact Protection</h3>
            <p>G-force limits define how much energy your helmet must absorb. ECE 22.06 caps at 275G — significantly stricter than DOT's 400G limit. Lower thresholds mean better brain protection in real crashes.</p>
        </div>

        <div class="hs-safety-hub__edu-card">
            <div class="hs-safety-hub__edu-icon">
                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2v4m0 12v4M4.93 4.93l2.83 2.83m8.48 8.48l2.83 2.83M2 12h4m12 0h4M4.93 19.07l2.83-2.83m8.48-8.48l2.83-2.83"/></svg>
            </div>
            <h3>Rotational Testing</h3>
            <p>Oblique (angled) impacts cause rotational forces that lead to concussions and diffuse axial injury. Only ECE 22.06 and SHARP test for this — making them the most advanced standards available.</p>
        </div>

        <div class="hs-safety-hub__edu-card">
            <div class="hs-safety-hub__edu-icon">
                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M2 12h20M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
            </div>
            <h3>Legal Requirements</h3>
            <p>DOT is mandatory for US road use. ECE 22.06 is required across 50+ countries. ISI IS 4151 is mandatory in India. Snell and SHARP are voluntary but represent the highest testing rigor available.</p>
        </div>

        <div class="hs-safety-hub__edu-card">
            <div class="hs-safety-hub__edu-icon">
                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
            </div>
            <h3>Beyond the Sticker</h3>
            <p>DOT is self-certified by manufacturers — no independent lab testing required. In contrast, ECE and Snell require third-party lab verification. This distinction dramatically affects real-world safety outcomes.</p>
        </div>
    </div>
</section>

<!-- ═══════════════════════════════════════════════════════════════════════════
     SECTION 5: CTA — "Find Your Certified Helmet"
     ═══════════════════════════════════════════════════════════════════════ -->
<section class="hs-safety-hub__cta">
    <div class="hs-safety-hub__cta-inner">
        <div class="hs-safety-hub__cta-content">
            <p class="hs-eyebrow">Ready to Ride Safe?</p>
            <h2>Find Your Certified Helmet</h2>
            <p>Browse our database of <?php echo number_format($total_helmets); ?> certified helmets. Filter by certification, brand, type, and price to find the perfect match.</p>
        </div>
        <div class="hs-safety-hub__cta-actions">
            <a href="<?php echo esc_url(helmetsan_url('/helmets/')); ?>" class="hs-btn hs-btn--primary hs-btn--lg">
                Browse All Helmets
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12h14m-7-7 7 7-7 7"/></svg>
            </a>
            <a href="<?php echo esc_url(helmetsan_url('/comparison/')); ?>" class="hs-btn hs-btn--ghost hs-btn--lg">
                Compare Helmets
            </a>
        </div>
    </div>
</section>

<?php
get_footer();
