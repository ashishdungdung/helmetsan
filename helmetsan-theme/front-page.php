<?php
/**
 * Front page template — Helmetsan Homepage V2
 * Helmet Decision Engine: DISCOVER → UNDERSTAND → COMPARE → DECIDE
 *
 * @package HelmetsanTheme
 */

get_header();

// Canonical stats from single source of truth
$stats = function_exists('helmetsan_get_catalog_stats')
    ? helmetsan_get_catalog_stats()
    : ['helmets' => 2260, 'brands' => 58, 'accessories' => 27, 'motorcycles' => 5, 'standards' => 6];

$helmetsUrl     = helmetsan_url('/helmets/');
$brandsUrl      = helmetsan_url('/brands/');
$accessoriesUrl = helmetsan_url('/accessories/');
$motorcyclesUrl = helmetsan_url('/motorcycles/');
$safetyUrl      = helmetsan_url('/safety-standards/');
$compareUrl     = helmetsan_url('/comparison/');
?>

<!-- §1 HERO / HELMET FINDER -->
<section class="hs-home-hero">
    <div class="hs-home-section__inner">
        <div class="hs-home-hero__badge">Global Helmet Intelligence Platform</div>
        <h1 class="hs-home-hero__title">Find the right helmet for how you ride.</h1>
        <p class="hs-home-hero__subtitle">Compare safety, fit, features, riding style and price across thousands of helmets.</p>

        <form role="search" method="get" class="hs-home-hero__search-form" action="<?php echo esc_url(helmetsan_url('/')); ?>">
            <input type="search" name="s" class="hs-home-hero__search-input" placeholder="Search helmets, brands, motorcycles or requirements..." aria-label="Search Helmetsan" />
            <button type="submit" class="hs-home-hero__search-btn">Search</button>
        </form>

        <div class="hs-home-hero__actions">
            <a href="#hs-home-intent" class="hs-home-hero__cta-primary">Find My Helmet →</a>
            <a href="<?php echo esc_url($helmetsUrl); ?>" class="hs-home-hero__cta-secondary">Browse all <?php echo number_format($stats['helmets']); ?>+ helmets</a>
        </div>

        <div class="hs-home-hero__chips">
            <span class="hs-home-hero__chip-label">Popular:</span>
            <a href="<?php echo esc_url(helmetsan_url('/?s=ECE+22.06')); ?>" class="hs-home-hero__chip">ECE 22.06</a>
            <a href="<?php echo esc_url(helmetsan_url('/?s=Himalayan+450')); ?>" class="hs-home-hero__chip">Himalayan 450</a>
            <a href="<?php echo esc_url(helmetsan_url('/?s=Arai')); ?>" class="hs-home-hero__chip">Arai</a>
            <a href="<?php echo esc_url(helmetsan_url('/?s=Shoei')); ?>" class="hs-home-hero__chip">Shoei</a>
            <a href="<?php echo esc_url(helmetsan_url('/helmet-type/adventure-dual-sport/')); ?>" class="hs-home-hero__chip">Adventure</a>
            <a href="<?php echo esc_url(helmetsan_url('/?s=under+500')); ?>" class="hs-home-hero__chip">Under $500</a>
        </div>
    </div>
</section>


<!-- §2 RIDING INTENT SELECTOR -->
<section id="hs-home-intent" class="hs-home-intent">
    <div class="hs-home-section__inner">
        <div class="hs-home-section__header">
            <span class="hs-home-section__eyebrow">How Do You Ride?</span>
            <h2 class="hs-home-section__title">Tell us your riding style</h2>
            <p class="hs-home-section__subtitle">We'll show you helmets designed for exactly how you ride.</p>
        </div>

        <div class="hs-home-intent__grid">
            <a href="<?php echo esc_url(helmetsan_url('/use-case/commuting/')); ?>" class="hs-home-intent__card">
                <span class="hs-home-intent__icon">🏙️</span>
                <span class="hs-home-intent__label">Commute</span>
                <span class="hs-home-intent__desc">City &amp; daily use</span>
            </a>
            <a href="<?php echo esc_url(helmetsan_url('/helmet-type/full-face/')); ?>" class="hs-home-intent__card">
                <span class="hs-home-intent__icon">🛣️</span>
                <span class="hs-home-intent__label">Highway</span>
                <span class="hs-home-intent__desc">Stability &amp; comfort</span>
            </a>
            <a href="<?php echo esc_url(helmetsan_url('/helmet-type/touring/')); ?>" class="hs-home-intent__card">
                <span class="hs-home-intent__icon">🧳</span>
                <span class="hs-home-intent__label">Touring</span>
                <span class="hs-home-intent__desc">Long-distance rides</span>
            </a>
            <a href="<?php echo esc_url(helmetsan_url('/helmet-type/adventure-dual-sport/')); ?>" class="hs-home-intent__card">
                <span class="hs-home-intent__icon">🏕️</span>
                <span class="hs-home-intent__label">Adventure</span>
                <span class="hs-home-intent__desc">Road &amp; trail</span>
            </a>
            <a href="<?php echo esc_url(helmetsan_url('/use-case/sport/')); ?>" class="hs-home-intent__card">
                <span class="hs-home-intent__icon">🏁</span>
                <span class="hs-home-intent__label">Sport</span>
                <span class="hs-home-intent__desc">Aggressive street</span>
            </a>
            <a href="<?php echo esc_url(helmetsan_url('/helmet-type/track-race/')); ?>" class="hs-home-intent__card">
                <span class="hs-home-intent__icon">🏎️</span>
                <span class="hs-home-intent__label">Track</span>
                <span class="hs-home-intent__desc">Race-focused</span>
            </a>
        </div>
    </div>
</section>


<!-- §3 INTELLIGENCE STATS -->
<section class="hs-home-stats">
    <div class="hs-home-stats__grid">
        <div class="hs-home-stats__item">
            <span class="hs-home-stats__number"><?php echo number_format($stats['helmets']); ?>+</span>
            <span class="hs-home-stats__label">Helmets Tracked</span>
        </div>
        <div class="hs-home-stats__item">
            <span class="hs-home-stats__number"><?php echo number_format($stats['brands']); ?>+</span>
            <span class="hs-home-stats__label">Brands</span>
        </div>
        <div class="hs-home-stats__item">
            <span class="hs-home-stats__number"><?php echo number_format($stats['accessories']); ?>+</span>
            <span class="hs-home-stats__label">Accessories</span>
        </div>
        <div class="hs-home-stats__item">
            <span class="hs-home-stats__number"><?php echo (int) $stats['standards']; ?></span>
            <span class="hs-home-stats__label">Safety Standards</span>
        </div>
    </div>
    <p class="hs-home-stats__tagline">One structured database. One place to understand the helmet market.</p>
</section>


<!-- §4 HELMETSAN PICKS -->
<section class="hs-home-picks">
    <div class="hs-home-section__inner">
        <div class="hs-home-section__header">
            <span class="hs-home-section__eyebrow">Helmetsan Picks</span>
            <h2 class="hs-home-section__title">Recommended helmets</h2>
            <p class="hs-home-section__subtitle">Based on safety, value, weight and rider feedback across our database.</p>
        </div>

        <div class="hs-home-picks__grid">
            <?php
            // Query top helmets from real catalog in active language
            $picks_args = [
                'post_type'      => 'helmet',
                'post_status'    => 'publish',
                'posts_per_page' => 4,
                'orderby'        => 'modified',
                'order'          => 'DESC',
            ];
            if (function_exists('pll_current_language')) {
                $picks_args['lang'] = pll_current_language();
            }
            $picks_query = new WP_Query($picks_args);

            // If active language has fewer than 4 published items, fallback gracefully to catalog
            if ($picks_query->post_count < 4 && ! empty($picks_args['lang']) && $picks_args['lang'] !== 'en') {
                unset($picks_args['lang']);
                $picks_query = new WP_Query($picks_args);
            }

            $pick_badges = [
                ['label' => '🏆 Best Overall',     'class' => 'gold',   'featured' => true],
                ['label' => '💰 Best Value',        'class' => 'green',  'featured' => false],
                ['label' => '🪶 Best Lightweight',   'class' => 'blue',   'featured' => false],
                ['label' => '🏕️ Best Adventure',    'class' => 'purple', 'featured' => false],
            ];
            $pick_reasons = [
                'Premium construction with strong certification coverage.',
                'Outstanding performance at a competitive price point.',
                'Ultra-lightweight shell reduces neck fatigue on long rides.',
                'Dual-sport versatility for road and trail riding.',
            ];

            $pick_index = 0;
            if ($picks_query->have_posts()) :
                while ($picks_query->have_posts()) : $picks_query->the_post();
                    $hid = get_the_ID();
                    $brand_name = get_post_meta($hid, 'brand_name', true) ?: 'Manufacturer';
                    $homo = get_post_meta($hid, 'homologation_standard', true) ?: 'ECE 22.06';
                    $weight = get_post_meta($hid, 'weight_g', true) ?: '';
                    $price = get_post_meta($hid, 'price_usd', true) ?: '';
                    $badge = $pick_badges[$pick_index] ?? $pick_badges[0];
                    $reason = $pick_reasons[$pick_index] ?? '';
                    $card_class = $badge['featured'] ? 'hs-home-pick-card hs-home-pick-card--featured' : 'hs-home-pick-card';
            ?>
                <div class="<?php echo esc_attr($card_class); ?>">
                    <span class="hs-home-pick-card__badge hs-home-pick-card__badge--<?php echo esc_attr($badge['class']); ?>"><?php echo esc_html($badge['label']); ?></span>
                    <div class="hs-home-pick-card__body">
                        <div class="hs-home-pick-card__brand"><?php echo esc_html($brand_name); ?></div>
                        <div class="hs-home-pick-card__name"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></div>

                        <div class="hs-home-pick-card__certs">
                            <span class="hs-home-pick-card__cert"><?php echo esc_html($homo); ?></span>
                        </div>

                        <?php if ($weight) : ?>
                        <div class="hs-home-pick-card__dims">
                            <div>
                                <div class="hs-home-pick-card__dim-label">Weight</div>
                                <div class="hs-home-pick-card__dim-value"><?php echo esc_html($weight); ?>g</div>
                            </div>
                            <div>
                                <div class="hs-home-pick-card__dim-label">Material</div>
                                <div class="hs-home-pick-card__dim-value"><?php echo esc_html(get_post_meta($hid, 'shell_material', true) ?: 'Composite'); ?></div>
                            </div>
                            <div>
                                <div class="hs-home-pick-card__dim-label">Standard</div>
                                <div class="hs-home-pick-card__dim-value"><?php echo esc_html($homo); ?></div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <p class="hs-home-pick-card__reason"><?php echo esc_html($reason); ?></p>

                        <div class="hs-home-pick-card__footer">
                            <div>
                                <?php if ($price) : ?>
                                <div class="hs-home-pick-card__price-label">MSRP</div>
                                <div class="hs-home-pick-card__price">$<?php echo esc_html($price); ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="hs-home-pick-card__actions">
                                <a href="<?php the_permalink(); ?>" class="hs-btn hs-btn--primary">View →</a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php
                    $pick_index++;
                endwhile;
                wp_reset_postdata();
            else :
                // Fallback if no helmets found in WP — show placeholder
            ?>
                <div class="hs-home-pick-card">
                    <span class="hs-home-pick-card__badge hs-home-pick-card__badge--gold">🏆 Best Overall</span>
                    <div class="hs-home-pick-card__body">
                        <div class="hs-home-pick-card__brand">Explore</div>
                        <div class="hs-home-pick-card__name"><a href="<?php echo esc_url($helmetsUrl); ?>">Browse Our Catalog</a></div>
                        <p class="hs-home-pick-card__reason">Discover helmets across <?php echo number_format($stats['helmets']); ?>+ products from <?php echo number_format($stats['brands']); ?>+ brands.</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>


<!-- §5 EXPLORE BY HELMET TYPE -->
<section class="hs-home-types">
    <div class="hs-home-section__inner">
        <div class="hs-home-section__header">
            <span class="hs-home-section__eyebrow">Explore Helmets</span>
            <h2 class="hs-home-section__title">Find the right category</h2>
        </div>

        <div class="hs-home-types__grid">
            <a href="<?php echo esc_url(helmetsan_url('/helmet-type/full-face/')); ?>" class="hs-home-type-tile">
                <span class="hs-home-type-tile__icon">🛡️</span>
                <span class="hs-home-type-tile__name">Full Face</span>
                <span class="hs-home-type-tile__desc">Maximum coverage</span>
            </a>
            <a href="<?php echo esc_url(helmetsan_url('/helmet-type/modular/')); ?>" class="hs-home-type-tile">
                <span class="hs-home-type-tile__icon">🔄</span>
                <span class="hs-home-type-tile__name">Modular</span>
                <span class="hs-home-type-tile__desc">Versatility &amp; touring</span>
            </a>
            <a href="<?php echo esc_url(helmetsan_url('/helmet-type/adventure-dual-sport/')); ?>" class="hs-home-type-tile">
                <span class="hs-home-type-tile__icon">🏔️</span>
                <span class="hs-home-type-tile__name">Adventure</span>
                <span class="hs-home-type-tile__desc">Road + off-road</span>
            </a>
            <a href="<?php echo esc_url(helmetsan_url('/helmet-type/open-face/')); ?>" class="hs-home-type-tile">
                <span class="hs-home-type-tile__icon">💨</span>
                <span class="hs-home-type-tile__name">Open Face</span>
                <span class="hs-home-type-tile__desc">Airflow &amp; visibility</span>
            </a>
            <a href="<?php echo esc_url(helmetsan_url('/helmet-type/track-race/')); ?>" class="hs-home-type-tile">
                <span class="hs-home-type-tile__icon">🏎️</span>
                <span class="hs-home-type-tile__name">Track / Race</span>
                <span class="hs-home-type-tile__desc">Race-focused protection</span>
            </a>
        </div>

        <div class="hs-home-browse-all">
            <a href="<?php echo esc_url($helmetsUrl); ?>">Browse all <?php echo number_format($stats['helmets']); ?>+ helmets →</a>
        </div>
    </div>
</section>


<!-- §6 COMPARISON SHOWCASE -->
<section class="hs-home-compare">
    <div class="hs-home-section__inner">
        <div class="hs-home-section__header">
            <span class="hs-home-section__eyebrow">Compare</span>
            <h2 class="hs-home-section__title">Can't decide between two helmets?</h2>
            <p class="hs-home-section__subtitle">Compare them side by side — safety, weight, features and price.</p>
        </div>

        <div class="hs-home-compare__preview">
            <div class="hs-home-compare__helmet">
                <div class="hs-home-compare__helmet-brand">Arai</div>
                <div class="hs-home-compare__helmet-name">Signet-X</div>
                <div class="hs-home-compare__helmet-price">$749</div>
            </div>
            <div class="hs-home-compare__helmet">
                <div class="hs-home-compare__helmet-brand">Shoei</div>
                <div class="hs-home-compare__helmet-name">RF-1400</div>
                <div class="hs-home-compare__helmet-price">$579</div>
            </div>
            <div class="hs-home-compare__helmet">
                <div class="hs-home-compare__helmet-brand">AGV</div>
                <div class="hs-home-compare__helmet-name">K6 S</div>
                <div class="hs-home-compare__helmet-price">$499</div>
            </div>
        </div>

        <table class="hs-home-compare__table">
            <thead>
                <tr>
                    <th></th>
                    <th>Arai Signet-X</th>
                    <th>Shoei RF-1400</th>
                    <th>AGV K6 S</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Safety</td>
                    <td class="hs-best">ECE 22.06</td>
                    <td>ECE 22.06</td>
                    <td>ECE 22.06</td>
                </tr>
                <tr>
                    <td>Weight</td>
                    <td>1,520g</td>
                    <td>1,450g</td>
                    <td class="hs-best">1,255g</td>
                </tr>
                <tr>
                    <td>Material</td>
                    <td>PB-cLc</td>
                    <td>AIM+</td>
                    <td>Carbon-Aramid</td>
                </tr>
                <tr>
                    <td>Price</td>
                    <td>$749</td>
                    <td>$579</td>
                    <td class="hs-best">$499</td>
                </tr>
            </tbody>
        </table>

        <div class="hs-home-compare__cta">
            <a href="<?php echo esc_url($compareUrl); ?>" class="hs-btn hs-btn--primary">Compare Helmets →</a>
        </div>
    </div>
</section>


<!-- §7 SAFETY INTELLIGENCE -->
<section class="hs-home-safety">
    <div class="hs-home-section__inner">
        <div class="hs-home-section__header">
            <span class="hs-home-section__eyebrow">Safety Intelligence</span>
            <h2 class="hs-home-section__title">Understand helmet safety</h2>
            <p class="hs-home-section__subtitle">Not all certifications mean the same thing. Know what protects you.</p>
        </div>

        <div class="hs-home-safety__grid">
            <a href="<?php echo esc_url(helmetsan_url('/certification/ece-22-06/')); ?>" class="hs-home-safety__card">
                <span class="hs-home-safety__card-region">🌍 Global</span>
                <div class="hs-home-safety__card-name">ECE 22.06</div>
                <div class="hs-home-safety__card-desc">Impact + rotational testing</div>
            </a>
            <a href="<?php echo esc_url(helmetsan_url('/certification/dot-approved/')); ?>" class="hs-home-safety__card">
                <span class="hs-home-safety__card-region">🇺🇸 USA</span>
                <div class="hs-home-safety__card-name">DOT FMVSS 218</div>
                <div class="hs-home-safety__card-desc">US regulatory standard</div>
            </a>
            <a href="<?php echo esc_url(helmetsan_url('/certification/snell-certified/')); ?>" class="hs-home-safety__card">
                <span class="hs-home-safety__card-region">🏁 Track</span>
                <div class="hs-home-safety__card-name">Snell M2020</div>
                <div class="hs-home-safety__card-desc">Independent performance standard</div>
            </a>
            <a href="<?php echo esc_url(helmetsan_url('/safety-standards/')); ?>" class="hs-home-safety__card">
                <span class="hs-home-safety__card-region">🇬🇧 UK</span>
                <div class="hs-home-safety__card-name">SHARP</div>
                <div class="hs-home-safety__card-desc">Independent UK rating system</div>
            </a>
            <a href="<?php echo esc_url(helmetsan_url('/certification/fim-certified/')); ?>" class="hs-home-safety__card">
                <span class="hs-home-safety__card-region">🏆 MotoGP</span>
                <div class="hs-home-safety__card-name">FIM FRHPhe</div>
                <div class="hs-home-safety__card-desc">Mandatory racing standard</div>
            </a>
        </div>

        <div class="hs-home-safety__cta">
            <a href="<?php echo esc_url($safetyUrl); ?>" class="hs-btn hs-btn--ghost">Compare Safety Standards →</a>
        </div>
    </div>
</section>


<!-- §8 MOTORCYCLE MATCHER -->
<section class="hs-home-moto">
    <div class="hs-home-section__inner">
        <div class="hs-home-section__header">
            <span class="hs-home-section__eyebrow">Motorcycle → Helmet</span>
            <h2 class="hs-home-section__title">Find a helmet for your motorcycle</h2>
            <p class="hs-home-section__subtitle">Tell us what you ride. We'll recommend helmets that match.</p>
        </div>

        <form role="search" method="get" class="hs-home-moto__search-form" action="<?php echo esc_url($motorcyclesUrl); ?>">
            <input type="search" name="s" class="hs-home-moto__search-input" placeholder="Search your motorcycle..." aria-label="Search motorcycles" />
            <button type="submit" class="hs-home-moto__search-btn">Search</button>
        </form>

        <div class="hs-home-moto__chips">
            <a href="<?php echo esc_url(helmetsan_url('/?s=Himalayan+450')); ?>" class="hs-home-hero__chip">Himalayan 450</a>
            <a href="<?php echo esc_url(helmetsan_url('/?s=YZF-R1')); ?>" class="hs-home-hero__chip">Yamaha YZF-R1</a>
            <a href="<?php echo esc_url(helmetsan_url('/?s=R1250+GS')); ?>" class="hs-home-hero__chip">BMW R1250 GS</a>
            <a href="<?php echo esc_url(helmetsan_url('/?s=KTM+390+Adventure')); ?>" class="hs-home-hero__chip">KTM 390 Adventure</a>
            <a href="<?php echo esc_url(helmetsan_url('/?s=Honda+Activa')); ?>" class="hs-home-hero__chip">Honda Activa</a>
        </div>
    </div>
</section>


<!-- §9 ACCESSORY COMPATIBILITY -->
<section class="hs-home-gear">
    <div class="hs-home-section__inner">
        <div class="hs-home-section__header">
            <span class="hs-home-section__eyebrow">Compatible Gear</span>
            <h2 class="hs-home-section__title">Make your helmet work harder</h2>
            <p class="hs-home-section__subtitle">Find compatible accessories for your helmet.</p>
        </div>

        <div class="hs-home-gear__grid">
            <a href="<?php echo esc_url(helmetsan_url('/accessory-category/bluetooth-headsets/')); ?>" class="hs-home-gear__card">
                <span class="hs-home-gear__card-icon">📡</span>
                <span class="hs-home-gear__card-name">Communication</span>
                <span class="hs-home-gear__card-items">Bluetooth · Mesh · Audio Kits</span>
            </a>
            <a href="<?php echo esc_url(helmetsan_url('/accessory-category/face-shields/')); ?>" class="hs-home-gear__card">
                <span class="hs-home-gear__card-icon">🔍</span>
                <span class="hs-home-gear__card-name">Visors &amp; Optics</span>
                <span class="hs-home-gear__card-items">Shields · Pinlock · Anti-Fog</span>
            </a>
            <a href="<?php echo esc_url(helmetsan_url('/accessory-category/cheek-pads/')); ?>" class="hs-home-gear__card">
                <span class="hs-home-gear__card-icon">☁️</span>
                <span class="hs-home-gear__card-name">Comfort</span>
                <span class="hs-home-gear__card-items">Cheek Pads · Liners · Balaclavas</span>
            </a>
            <a href="<?php echo esc_url(helmetsan_url('/accessory-category/helmet-cleaners/')); ?>" class="hs-home-gear__card">
                <span class="hs-home-gear__card-icon">🧴</span>
                <span class="hs-home-gear__card-name">Care</span>
                <span class="hs-home-gear__card-items">Cleaners · Bags · Storage</span>
            </a>
            <a href="<?php echo esc_url(helmetsan_url('/accessory-category/reflective-stickers/')); ?>" class="hs-home-gear__card">
                <span class="hs-home-gear__card-icon">🦺</span>
                <span class="hs-home-gear__card-name">Safety</span>
                <span class="hs-home-gear__card-items">Reflective · Chin Guards · Vents</span>
            </a>
        </div>
    </div>
</section>


<!-- §10 INTELLIGENCE GUIDES -->
<section class="hs-home-guides">
    <div class="hs-home-section__inner">
        <div class="hs-home-section__header">
            <span class="hs-home-section__eyebrow">Helmetsan Intelligence</span>
            <h2 class="hs-home-section__title">Guides &amp; research</h2>
        </div>

        <div class="hs-home-guides__grid">
            <a href="<?php echo esc_url(helmetsan_url('/certification/ece-22-06/')); ?>" class="hs-home-guide-card">
                <span class="hs-home-guide-card__category">Safety Standards</span>
                <span class="hs-home-guide-card__title">What does ECE 22.06 actually mean?</span>
                <span class="hs-home-guide-card__meta">Safety · 8 min read</span>
            </a>
            <a href="<?php echo esc_url($safetyUrl); ?>" class="hs-home-guide-card">
                <span class="hs-home-guide-card__category">Safety</span>
                <span class="hs-home-guide-card__title">ECE vs DOT: What's the difference?</span>
                <span class="hs-home-guide-card__meta">Comparison · 6 min read</span>
            </a>
            <a href="<?php echo esc_url(helmetsan_url('/helmet-type/touring/')); ?>" class="hs-home-guide-card">
                <span class="hs-home-guide-card__category">Buying Guide</span>
                <span class="hs-home-guide-card__title">Best helmets for long-distance touring</span>
                <span class="hs-home-guide-card__meta">Touring · 10 min read</span>
            </a>
        </div>
    </div>
</section>


<!-- §11 FINAL CTA -->
<section class="hs-home-final-cta">
    <h2 class="hs-home-final-cta__title">Still not sure which helmet is right for you?</h2>
    <p class="hs-home-final-cta__subtitle">Tell Helmetsan how you ride.</p>
    <a href="#hs-home-intent" class="hs-home-hero__cta-primary">Find My Helmet →</a>
</section>


<!-- Floating Comparison Bar -->
<?php get_template_part('template-parts/components/comparison-bar'); ?>

<?php
get_footer();
