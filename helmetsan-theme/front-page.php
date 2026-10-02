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
        <div class="hs-home-hero__badge"><?php hs_e('Global Helmet Intelligence Platform'); ?></div>
        <h1 class="hs-home-hero__title"><?php hs_e('Find the right helmet for how you ride.'); ?></h1>
        <p class="hs-home-hero__subtitle"><?php hs_e('Compare safety, fit, features, riding style and price across thousands of helmets.'); ?></p>

        <form role="search" method="get" class="hs-home-hero__search-form" action="<?php echo esc_url(helmetsan_url('/')); ?>">
            <input type="search" name="s" class="hs-home-hero__search-input" placeholder="<?php hs_attr_e('Search helmets, brands, motorcycles or requirements...'); ?>" aria-label="<?php hs_attr_e('Search Helmetsan'); ?>" />
            <button type="submit" class="hs-home-hero__search-btn"><?php hs_e('Search'); ?></button>
        </form>

        <div class="hs-home-hero__actions">
            <a href="#hs-home-intent" class="hs-home-hero__cta-primary"><?php hs_e('Find My Helmet →'); ?></a>
            <a href="<?php echo esc_url($helmetsUrl); ?>" class="hs-home-hero__cta-secondary"><?php printf(esc_html(hs_t('Browse all %s+ helmets')), number_format_i18n($stats['helmets'])); ?></a>
        </div>

        <div class="hs-home-hero__chips">
            <span class="hs-home-hero__chip-label"><?php hs_e('Popular:'); ?></span>
            <a href="<?php echo esc_url(helmetsan_url('/?s=ECE+22.06')); ?>" class="hs-home-hero__chip">ECE 22.06</a>
            <a href="<?php echo esc_url(helmetsan_url('/?s=Himalayan+450')); ?>" class="hs-home-hero__chip">Himalayan 450</a>
            <a href="<?php echo esc_url(helmetsan_url('/?s=Arai')); ?>" class="hs-home-hero__chip">Arai</a>
            <a href="<?php echo esc_url(helmetsan_url('/?s=Shoei')); ?>" class="hs-home-hero__chip">Shoei</a>
            <a href="<?php echo esc_url(helmetsan_url('/helmet-type/adventure-dual-sport/')); ?>" class="hs-home-hero__chip"><?php hs_e('Adventure'); ?></a>
            <a href="<?php echo esc_url(helmetsan_url('/?s=under+500')); ?>" class="hs-home-hero__chip"><?php hs_e('Under $500'); ?></a>
        </div>
    </div>
</section>


<!-- §2 RIDING INTENT SELECTOR -->
<section id="hs-home-intent" class="hs-home-intent">
    <div class="hs-home-section__inner">
        <div class="hs-home-section__header">
            <span class="hs-home-section__eyebrow"><?php hs_e('How Do You Ride?'); ?></span>
            <h2 class="hs-home-section__title"><?php hs_e('Tell us your riding style'); ?></h2>
            <p class="hs-home-section__subtitle"><?php hs_e("We'll show you helmets designed for exactly how you ride."); ?></p>
        </div>

        <div class="hs-home-intent__grid">
            <a href="<?php echo esc_url(helmetsan_url('/use-case/commuting/')); ?>" class="hs-home-intent__card">
                <span class="hs-home-intent__icon">🏙️</span>
                <span class="hs-home-intent__label"><?php hs_e('Commute'); ?></span>
                <span class="hs-home-intent__desc"><?php hs_e('City & daily use'); ?></span>
            </a>
            <a href="<?php echo esc_url(helmetsan_url('/helmet-type/full-face/')); ?>" class="hs-home-intent__card">
                <span class="hs-home-intent__icon">🛣️</span>
                <span class="hs-home-intent__label"><?php hs_e('Highway'); ?></span>
                <span class="hs-home-intent__desc"><?php hs_e('Stability & comfort'); ?></span>
            </a>
            <a href="<?php echo esc_url(helmetsan_url('/helmet-type/touring/')); ?>" class="hs-home-intent__card">
                <span class="hs-home-intent__icon">🧳</span>
                <span class="hs-home-intent__label"><?php hs_e('Touring'); ?></span>
                <span class="hs-home-intent__desc"><?php hs_e('Long-distance rides'); ?></span>
            </a>
            <a href="<?php echo esc_url(helmetsan_url('/helmet-type/adventure-dual-sport/')); ?>" class="hs-home-intent__card">
                <span class="hs-home-intent__icon">🏕️</span>
                <span class="hs-home-intent__label"><?php hs_e('Adventure'); ?></span>
                <span class="hs-home-intent__desc"><?php hs_e('Road & trail'); ?></span>
            </a>
            <a href="<?php echo esc_url(helmetsan_url('/use-case/sport/')); ?>" class="hs-home-intent__card">
                <span class="hs-home-intent__icon">🏁</span>
                <span class="hs-home-intent__label"><?php hs_e('Sport'); ?></span>
                <span class="hs-home-intent__desc"><?php hs_e('Aggressive street'); ?></span>
            </a>
            <a href="<?php echo esc_url(helmetsan_url('/helmet-type/track-race/')); ?>" class="hs-home-intent__card">
                <span class="hs-home-intent__icon">🏎️</span>
                <span class="hs-home-intent__label"><?php hs_e('Track'); ?></span>
                <span class="hs-home-intent__desc"><?php hs_e('Race-focused'); ?></span>
            </a>
        </div>
    </div>
</section>


<!-- §3 INTELLIGENCE STATS -->
<section class="hs-home-stats">
    <div class="hs-home-stats__grid">
        <div class="hs-home-stats__item">
            <span class="hs-home-stats__number"><?php echo number_format_i18n($stats['helmets']); ?>+</span>
            <span class="hs-home-stats__label"><?php hs_e('Helmets Tracked'); ?></span>
        </div>
        <div class="hs-home-stats__item">
            <span class="hs-home-stats__number"><?php echo number_format_i18n($stats['brands']); ?>+</span>
            <span class="hs-home-stats__label"><?php hs_e('Brands'); ?></span>
        </div>
        <div class="hs-home-stats__item">
            <span class="hs-home-stats__number"><?php echo number_format_i18n($stats['accessories']); ?>+</span>
            <span class="hs-home-stats__label"><?php hs_e('Accessories'); ?></span>
        </div>
        <div class="hs-home-stats__item">
            <span class="hs-home-stats__number"><?php echo (int) $stats['standards']; ?></span>
            <span class="hs-home-stats__label"><?php hs_e('Safety Standards'); ?></span>
        </div>
    </div>
    <p class="hs-home-stats__tagline"><?php hs_e('One structured database. One place to understand the helmet market.'); ?></p>
</section>


<!-- §4 HELMETSAN PICKS -->
<section class="hs-home-picks">
    <div class="hs-home-section__inner">
        <div class="hs-home-section__header">
            <span class="hs-home-section__eyebrow"><?php hs_e('Helmetsan Picks'); ?></span>
            <h2 class="hs-home-section__title"><?php hs_e('Recommended helmets'); ?></h2>
            <p class="hs-home-section__subtitle"><?php hs_e('Based on safety, value, weight and rider feedback across our database.'); ?></p>
        </div>

        <div class="hs-home-picks__grid">
            <?php
            // Query top helmets from real catalog in active language
            $picks_args = [
                'post_type'      => 'helmet',
                'post_parent'    => 0,
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
                ['label' => '🏆 ' . hs_t('Best Overall'),     'class' => 'gold',   'featured' => true],
                ['label' => '💰 ' . hs_t('Best Value'),        'class' => 'green',  'featured' => false],
                ['label' => '🪶 ' . hs_t('Best Lightweight'),   'class' => 'blue',   'featured' => false],
                ['label' => '🏕️ ' . hs_t('Best Adventure'),    'class' => 'purple', 'featured' => false],
            ];
            $pick_reasons = [
                hs_t('Premium construction with strong certification coverage.'),
                hs_t('Outstanding performance at a competitive price point.'),
                hs_t('Ultra-lightweight shell reduces neck fatigue on long rides.'),
                hs_t('Dual-sport versatility for road and trail riding.'),
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
                                <div class="hs-home-pick-card__dim-label"><?php hs_e('Weight'); ?></div>
                                <div class="hs-home-pick-card__dim-value"><?php echo esc_html(number_format_i18n((int)$weight)); ?>g</div>
                            </div>
                            <div>
                                <div class="hs-home-pick-card__dim-label"><?php hs_e('Material'); ?></div>
                                <div class="hs-home-pick-card__dim-value"><?php echo esc_html(get_post_meta($hid, 'shell_material', true) ?: 'Composite'); ?></div>
                            </div>
                            <div>
                                <div class="hs-home-pick-card__dim-label"><?php hs_e('Standard'); ?></div>
                                <div class="hs-home-pick-card__dim-value"><?php echo esc_html($homo); ?></div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <p class="hs-home-pick-card__reason"><?php echo esc_html($reason); ?></p>

                        <div class="hs-home-pick-card__footer">
                            <div>
                                <?php if ($price) : ?>
                                <div class="hs-home-pick-card__price-label"><?php hs_e('MSRP'); ?></div>
                                <div class="hs-home-pick-card__price"><?php echo function_exists('helmetsan_render_price_element') ? helmetsan_render_price_element($hid) : '$' . esc_html($price); ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="hs-home-pick-card__actions">
                                <a href="<?php the_permalink(); ?>" class="hs-btn hs-btn--primary"><?php hs_e('View →'); ?></a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php
                    $pick_index++;
                endwhile;
                wp_reset_postdata();
            else :
            ?>
                <div class="hs-home-pick-card">
                    <span class="hs-home-pick-card__badge hs-home-pick-card__badge--gold">🏆 <?php hs_e('Best Overall'); ?></span>
                    <div class="hs-home-pick-card__body">
                        <div class="hs-home-pick-card__brand"><?php hs_e('Explore'); ?></div>
                        <div class="hs-home-pick-card__name"><a href="<?php echo esc_url($helmetsUrl); ?>"><?php hs_e('Browse Our Catalog'); ?></a></div>
                        <p class="hs-home-pick-card__reason"><?php printf(esc_html(hs_t('Discover helmets across %s+ products from %s+ brands.')), number_format_i18n($stats['helmets']), number_format_i18n($stats['brands'])); ?></p>
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
            <span class="hs-home-section__eyebrow"><?php hs_e('Explore Helmets'); ?></span>
            <h2 class="hs-home-section__title"><?php hs_e('Find the right category'); ?></h2>
        </div>

        <div class="hs-home-types__grid">
            <a href="<?php echo esc_url(helmetsan_url('/helmet-type/full-face/')); ?>" class="hs-home-type-tile">
                <span class="hs-home-type-tile__icon">🛡️</span>
                <span class="hs-home-type-tile__name"><?php hs_e('Full Face'); ?></span>
                <span class="hs-home-type-tile__desc"><?php hs_e('Maximum coverage'); ?></span>
            </a>
            <a href="<?php echo esc_url(helmetsan_url('/helmet-type/modular/')); ?>" class="hs-home-type-tile">
                <span class="hs-home-type-tile__icon">🔄</span>
                <span class="hs-home-type-tile__name"><?php hs_e('Modular'); ?></span>
                <span class="hs-home-type-tile__desc"><?php hs_e('Versatility & touring'); ?></span>
            </a>
            <a href="<?php echo esc_url(helmetsan_url('/helmet-type/adventure-dual-sport/')); ?>" class="hs-home-type-tile">
                <span class="hs-home-type-tile__icon">🏔️</span>
                <span class="hs-home-type-tile__name"><?php hs_e('Adventure'); ?></span>
                <span class="hs-home-type-tile__desc"><?php hs_e('Road + off-road'); ?></span>
            </a>
            <a href="<?php echo esc_url(helmetsan_url('/helmet-type/open-face/')); ?>" class="hs-home-type-tile">
                <span class="hs-home-type-tile__icon">💨</span>
                <span class="hs-home-type-tile__name"><?php hs_e('Open Face'); ?></span>
                <span class="hs-home-type-tile__desc"><?php hs_e('Airflow & visibility'); ?></span>
            </a>
            <a href="<?php echo esc_url(helmetsan_url('/helmet-type/track-race/')); ?>" class="hs-home-type-tile">
                <span class="hs-home-type-tile__icon">🏎️</span>
                <span class="hs-home-type-tile__name"><?php hs_e('Track / Race'); ?></span>
                <span class="hs-home-type-tile__desc"><?php hs_e('Race-focused protection'); ?></span>
            </a>
        </div>

        <div class="hs-home-browse-all">
            <a href="<?php echo esc_url($helmetsUrl); ?>"><?php printf(esc_html(hs_t('Browse all %s+ helmets →')), number_format_i18n($stats['helmets'])); ?></a>
        </div>
    </div>
</section>


<!-- §6 COMPARISON SHOWCASE -->
<section class="hs-home-compare">
    <div class="hs-home-section__inner">
        <div class="hs-home-section__header">
            <span class="hs-home-section__eyebrow"><?php hs_e('Compare'); ?></span>
            <h2 class="hs-home-section__title"><?php hs_e("Can't decide between two helmets?"); ?></h2>
            <p class="hs-home-section__subtitle"><?php hs_e('Compare them side by side — safety, weight, features and price.'); ?></p>
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
                    <td><?php hs_e('Safety'); ?></td>
                    <td class="hs-best">ECE 22.06</td>
                    <td>ECE 22.06</td>
                    <td>ECE 22.06</td>
                </tr>
                <tr>
                    <td><?php hs_e('Weight'); ?></td>
                    <td>1,520g</td>
                    <td>1,450g</td>
                    <td class="hs-best">1,255g</td>
                </tr>
                <tr>
                    <td><?php hs_e('Material'); ?></td>
                    <td>PB-cLc</td>
                    <td>AIM+</td>
                    <td>Carbon-Aramid</td>
                </tr>
                <tr>
                    <td><?php hs_e('Price'); ?></td>
                    <td>$749</td>
                    <td>$579</td>
                    <td class="hs-best">$499</td>
                </tr>
            </tbody>
        </table>

        <div class="hs-home-compare__cta">
            <a href="<?php echo esc_url($compareUrl); ?>" class="hs-btn hs-btn--primary"><?php hs_e('Compare Helmets →'); ?></a>
        </div>
    </div>
</section>


<!-- §7 SAFETY INTELLIGENCE -->
<section class="hs-home-safety">
    <div class="hs-home-section__inner">
        <div class="hs-home-section__header">
            <span class="hs-home-section__eyebrow"><?php hs_e('Safety Intelligence'); ?></span>
            <h2 class="hs-home-section__title"><?php hs_e('Understand helmet safety'); ?></h2>
            <p class="hs-home-section__subtitle"><?php hs_e('Not all certifications mean the same thing. Know what protects you.'); ?></p>
        </div>

        <div class="hs-home-safety__grid">
            <a href="<?php echo esc_url(helmetsan_url('/certification/ece-22-06/')); ?>" class="hs-home-safety__card">
                <span class="hs-home-safety__card-region"><?php hs_e('🌍 Global'); ?></span>
                <div class="hs-home-safety__card-name">ECE 22.06</div>
                <div class="hs-home-safety__card-desc"><?php hs_e('Impact + rotational testing'); ?></div>
            </a>
            <a href="<?php echo esc_url(helmetsan_url('/certification/dot-approved/')); ?>" class="hs-home-safety__card">
                <span class="hs-home-safety__card-region"><?php hs_e('🇺🇸 USA'); ?></span>
                <div class="hs-home-safety__card-name">DOT FMVSS 218</div>
                <div class="hs-home-safety__card-desc"><?php hs_e('US regulatory standard'); ?></div>
            </a>
            <a href="<?php echo esc_url(helmetsan_url('/certification/snell-certified/')); ?>" class="hs-home-safety__card">
                <span class="hs-home-safety__card-region"><?php hs_e('🏁 Track'); ?></span>
                <div class="hs-home-safety__card-name">Snell M2020</div>
                <div class="hs-home-safety__card-desc"><?php hs_e('Independent performance standard'); ?></div>
            </a>
            <a href="<?php echo esc_url(helmetsan_url('/safety-standards/')); ?>" class="hs-home-safety__card">
                <span class="hs-home-safety__card-region"><?php hs_e('🇬🇧 UK'); ?></span>
                <div class="hs-home-safety__card-name">SHARP</div>
                <div class="hs-home-safety__card-desc"><?php hs_e('Independent UK rating system'); ?></div>
            </a>
            <a href="<?php echo esc_url(helmetsan_url('/certification/fim-certified/')); ?>" class="hs-home-safety__card">
                <span class="hs-home-safety__card-region"><?php hs_e('🏆 MotoGP'); ?></span>
                <div class="hs-home-safety__card-name">FIM FRHPhe</div>
                <div class="hs-home-safety__card-desc"><?php hs_e('Mandatory racing standard'); ?></div>
            </a>
        </div>

        <div class="hs-home-safety__cta">
            <a href="<?php echo esc_url($safetyUrl); ?>" class="hs-btn hs-btn--ghost"><?php hs_e('Compare Safety Standards →'); ?></a>
        </div>
    </div>
</section>


<!-- §8 MOTORCYCLE MATCHER -->
<section class="hs-home-moto">
    <div class="hs-home-section__inner">
        <div class="hs-home-section__header">
            <span class="hs-home-section__eyebrow"><?php hs_e('Motorcycle → Helmet'); ?></span>
            <h2 class="hs-home-section__title"><?php hs_e('Find a helmet for your motorcycle'); ?></h2>
            <p class="hs-home-section__subtitle"><?php hs_e("Tell us what you ride. We'll recommend helmets that match."); ?></p>
        </div>

        <form role="search" method="get" class="hs-home-moto__search-form" action="<?php echo esc_url($motorcyclesUrl); ?>">
            <input type="search" name="s" class="hs-home-moto__search-input" placeholder="<?php hs_attr_e('Search your motorcycle...'); ?>" aria-label="<?php hs_attr_e('Search motorcycles'); ?>" />
            <button type="submit" class="hs-home-moto__search-btn"><?php hs_e('Search'); ?></button>
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
            <span class="hs-home-section__eyebrow"><?php hs_e('Compatible Gear'); ?></span>
            <h2 class="hs-home-section__title"><?php hs_e('Make your helmet work harder'); ?></h2>
            <p class="hs-home-section__subtitle"><?php hs_e('Find compatible accessories for your helmet.'); ?></p>
        </div>

        <div class="hs-home-gear__grid">
            <a href="<?php echo esc_url(helmetsan_url('/accessory-category/bluetooth-headsets/')); ?>" class="hs-home-gear__card">
                <span class="hs-home-gear__card-icon">📡</span>
                <span class="hs-home-gear__card-name"><?php hs_e('Communication'); ?></span>
                <span class="hs-home-gear__card-items"><?php hs_e('Bluetooth · Mesh · Audio Kits'); ?></span>
            </a>
            <a href="<?php echo esc_url(helmetsan_url('/accessory-category/face-shields/')); ?>" class="hs-home-gear__card">
                <span class="hs-home-gear__card-icon">🔍</span>
                <span class="hs-home-gear__card-name"><?php hs_e('Visors & Optics'); ?></span>
                <span class="hs-home-gear__card-items"><?php hs_e('Shields · Pinlock · Anti-Fog'); ?></span>
            </a>
            <a href="<?php echo esc_url(helmetsan_url('/accessory-category/cheek-pads/')); ?>" class="hs-home-gear__card">
                <span class="hs-home-gear__card-icon">☁️</span>
                <span class="hs-home-gear__card-name"><?php hs_e('Comfort'); ?></span>
                <span class="hs-home-gear__card-items"><?php hs_e('Cheek Pads · Liners · Balaclavas'); ?></span>
            </a>
            <a href="<?php echo esc_url(helmetsan_url('/accessory-category/helmet-cleaners/')); ?>" class="hs-home-gear__card">
                <span class="hs-home-gear__card-icon">🧴</span>
                <span class="hs-home-gear__card-name"><?php hs_e('Care'); ?></span>
                <span class="hs-home-gear__card-items"><?php hs_e('Cleaners · Bags · Storage'); ?></span>
            </a>
            <a href="<?php echo esc_url(helmetsan_url('/accessory-category/reflective-stickers/')); ?>" class="hs-home-gear__card">
                <span class="hs-home-gear__card-icon">🦺</span>
                <span class="hs-home-gear__card-name"><?php hs_e('Safety'); ?></span>
                <span class="hs-home-gear__card-items"><?php hs_e('Reflective · Chin Guards · Vents'); ?></span>
            </a>
        </div>
    </div>
</section>


<!-- §10 INTELLIGENCE GUIDES -->
<section class="hs-home-guides">
    <div class="hs-home-section__inner">
        <div class="hs-home-section__header">
            <span class="hs-home-section__eyebrow"><?php hs_e('Helmetsan Intelligence'); ?></span>
            <h2 class="hs-home-section__title"><?php hs_e('Guides & research'); ?></h2>
        </div>

        <div class="hs-home-guides__grid">
            <a href="<?php echo esc_url(helmetsan_url('/certification/ece-22-06/')); ?>" class="hs-home-guide-card">
                <span class="hs-home-guide-card__category"><?php hs_e('Safety Standards'); ?></span>
                <span class="hs-home-guide-card__title"><?php hs_e('What does ECE 22.06 actually mean?'); ?></span>
                <span class="hs-home-guide-card__meta"><?php hs_e('Safety · 8 min read'); ?></span>
            </a>
            <a href="<?php echo esc_url($safetyUrl); ?>" class="hs-home-guide-card">
                <span class="hs-home-guide-card__category"><?php hs_e('Safety'); ?></span>
                <span class="hs-home-guide-card__title"><?php hs_e("ECE vs DOT: What's the difference?"); ?></span>
                <span class="hs-home-guide-card__meta"><?php hs_e('Comparison · 6 min read'); ?></span>
            </a>
            <a href="<?php echo esc_url(helmetsan_url('/helmet-type/touring/')); ?>" class="hs-home-guide-card">
                <span class="hs-home-guide-card__category"><?php hs_e('Buying Guide'); ?></span>
                <span class="hs-home-guide-card__title"><?php hs_e('Best helmets for long-distance touring'); ?></span>
                <span class="hs-home-guide-card__meta"><?php hs_e('Touring · 10 min read'); ?></span>
            </a>
        </div>
    </div>
</section>


<!-- §11 FINAL CTA -->
<section class="hs-home-final-cta">
    <h2 class="hs-home-final-cta__title"><?php hs_e('Still not sure which helmet is right for you?'); ?></h2>
    <p class="hs-home-final-cta__subtitle"><?php hs_e('Tell Helmetsan how you ride.'); ?></p>
    <a href="#hs-home-intent" class="hs-home-hero__cta-primary"><?php hs_e('Find My Helmet →'); ?></a>
</section>


<!-- Floating Comparison Bar -->
<?php get_template_part('template-parts/components/comparison-bar'); ?>

<?php
get_footer();
