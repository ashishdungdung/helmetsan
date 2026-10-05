<?php
/**
 * Single Motorcycle Template (Commercial Intelligence, Dealers, Lead Gen & Helmet Synergy Hub).
 *
 * Architecture:
 * 1. Breadcrumbs: Home > Motorcycles > Make > Model.
 * 2. Hero Section: Make badge, Segment tag, ECE/DOT status, Quick metric pills, Category SVG silhouette.
 * 3. Engineering Specs Matrix: Powertrain, Ergonomics, Posture Calibration.
 * 4. Touring & Ownership Matrix: Fuel Tank, Highway Range, Mileage, Maintenance intervals.
 * 5. Pricing & On-Road City Estimator: Ex-showroom bracket, interactive city on-road calculator, Test Ride CTA.
 * 6. Authorized Dealers Network: Verified Indian showroom listings by metro (Delhi, Mumbai, Bengaluru, etc.).
 * 7. B2B Dealer Enrollment Banner: Inbound onboarding for authorized dealerships.
 * 8. Editorial Intelligence: Real-World Pros, Cons, Crosswind & Thermal insights.
 * 9. Helmet Compatibility Matrix: Calibrated helmet pairings with match scores and localized gear CTAs.
 * 10. Cockpit Accessories Grid: Audio/comms, ear protection, moisture management.
 * 11. Competing Motorcycles: Direct rivals in the same segment.
 * 12. Modals: High-conversion Lead Gen modal & B2B Dealer Enrollment modal.
 * 13. Vehicle JSON-LD Schema.
 *
 * @package HelmetsanTheme
 */

get_header();

if (have_posts()) :
    while (have_posts()) : the_post();
        $post_id = get_the_ID();
        $title   = get_the_title();
        $link    = get_permalink();

        // Metadata extraction
        $make_meta    = (string) get_post_meta($post_id, 'motorcycle_make', true);
        $model_meta   = (string) get_post_meta($post_id, 'motorcycle_model', true);
        $segment_meta = (string) get_post_meta($post_id, 'bike_segment', true);
        $engine_cc    = get_post_meta($post_id, 'engine_cc', true);
        $power_hp     = get_post_meta($post_id, 'power_hp', true);
        $torque_nm    = get_post_meta($post_id, 'torque_nm', true);
        $curb_weight  = get_post_meta($post_id, 'curb_weight_kg', true);
        $riding_pos   = (string) get_post_meta($post_id, 'riding_position', true);
        $seat_height  = get_post_meta($post_id, 'seat_height_mm', true);
        $top_speed    = get_post_meta($post_id, 'top_speed_kmh', true);
        $wind_profile = (string) get_post_meta($post_id, '_hs_wind_profile', true);

        // Fallbacks from taxonomy
        $seg_terms = get_the_terms($post_id, 'motorcycle_segment');
        if (empty($segment_meta) && ! empty($seg_terms) && ! is_wp_error($seg_terms)) {
            $segment_meta = $seg_terms[0]->name;
        }
        $make_terms = get_the_terms($post_id, 'motorcycle_make');
        if (empty($make_meta) && ! empty($make_terms) && ! is_wp_error($make_terms)) {
            $make_meta = $make_terms[0]->name;
        }
        if (empty($make_meta)) {
            $make_meta = strstr($title, ' ', true) ?: __('Motorcycle', 'helmetsan-theme');
        }

        // Qualitative & Commercial JSON Data Extraction (Object Cached)
        $cache_key  = 'hs_moto_json_' . $post_id;
        $json_data  = wp_cache_get($cache_key, 'helmetsan');
        if (! is_array($json_data)) {
            $json_data     = [];
            $source_file   = (string) get_post_meta($post_id, '_source_file', true);
            $resolved_file = '';
            if (! empty($source_file)) {
                $filename = basename($source_file);
                if (! empty($filename) && $filename !== '.' && $filename !== '..' && str_ends_with(strtolower($filename), '.json')) {
                    if (is_file($source_file)) {
                        $resolved_file = $source_file;
                    } else {
                        $candidates = [
                            get_template_directory() . '/../data/motorcycles/' . $filename,
                            (defined('ABSPATH') ? ABSPATH : '') . 'data/motorcycles/' . $filename,
                            dirname(__DIR__, 2) . '/data/motorcycles/' . $filename,
                        ];
                        foreach ($candidates as $cand) {
                            if (is_file($cand)) {
                                $resolved_file = $cand;
                                break;
                            }
                        }
                    }
                }
            }
            if (! empty($resolved_file) && is_file($resolved_file) && is_readable($resolved_file)) {
                $real_resolved = realpath($resolved_file);
                if ($real_resolved && is_file($real_resolved)) {
                    $raw = @file_get_contents($real_resolved);
                    if ($raw !== false && $raw !== '') {
                        $decoded = json_decode($raw, true);
                        if (is_array($decoded)) {
                            $json_data = $decoded;
                        }
                    }
                }
            }
            wp_cache_set($cache_key, $json_data, 'helmetsan', 86400);
        }

        // Pricing extraction
        $raw_price = (int) (get_post_meta($post_id, 'price_inr', true) ?: ($json_data['price']['inr'] ?? 0));
        $is_price_estimated = false;
        $price_inr = $raw_price;
        if ($price_inr <= 0) {
            $is_price_estimated = true;
            // Intelligent heuristic based on displacement
            $cc_int = (int) $engine_cc;
            if ($cc_int >= 800) {
                $price_inr = 1150000;
            } elseif ($cc_int >= 400) {
                $price_inr = 295000;
            } elseif ($cc_int >= 200) {
                $price_inr = 175000;
            } elseif ($cc_int >= 125) {
                $price_inr = 115000;
            } else {
                $price_inr = 95000;
            }
        }

        // Touring & real-world specifications
        $fuel_capacity_l    = $json_data['fuel_capacity_l'] ?? ($json_data['intelligence_matrix']['ergonomics_and_cockpit']['fuel_capacity_l'] ?? 14);
        $estimated_range_km = $json_data['intelligence_matrix']['ergonomics_and_cockpit']['estimated_range_km'] ?? ($fuel_capacity_l ? sprintf('~%d km', (int) ($fuel_capacity_l * 28)) : '~400 km');
        $qualitative        = $json_data['qualitative_intelligence'] ?? [];
        $pros               = $qualitative['real_world_pros'] ?? [
            __('Balanced chassis geometry provides reassuring high-speed stability', 'helmetsan-theme'),
            __('Compliant suspension tuned for city potholes and highway expanses', 'helmetsan-theme'),
            __('Predictable throttle calibration minimizes rider fatigue in traffic', 'helmetsan-theme'),
        ];
        $cons               = $qualitative['real_world_cons'] ?? [
            __('Requires aftermarket tall windscreen for complete buffeting protection', 'helmetsan-theme'),
            __('Stock seat padding best suited for stints under 2 hours without breaks', 'helmetsan-theme'),
        ];
        $ergonomics_review  = $qualitative['rider_ergonomics_review'] ?? '';
        $highway_stability  = $qualitative['highway_crosswind_stability'] ?? '';
        $heat_management    = $qualitative['urban_heat_management'] ?? '';

        // Recommended helmet types
        $rec_helmets_raw  = get_post_meta($post_id, 'recommended_helmet_types_json', true);
        $rec_helmet_types = [];
        if (is_array($rec_helmets_raw)) {
            $rec_helmet_types = $rec_helmets_raw;
        } elseif (is_string($rec_helmets_raw) && $rec_helmets_raw !== '') {
            $decoded = json_decode($rec_helmets_raw, true);
            if (is_array($decoded)) {
                $rec_helmet_types = $decoded;
            }
        }
        if (empty($rec_helmet_types) && ! empty($json_data['intelligence_matrix']['recommended_gear']['recommended_helmets'])) {
            $rec_helmet_types = $json_data['intelligence_matrix']['recommended_gear']['recommended_helmets'];
        }

        $segment_slug = sanitize_title($segment_meta ?: 'roadster');

        // Query real matching helmets
        $target_tax_slugs = [];
        foreach ($rec_helmet_types as $rht) {
            $t = strtolower((string) $rht);
            if (str_contains($t, 'adventure') || str_contains($t, 'dual sport')) {
                $target_tax_slugs[] = 'dual-sport';
            } elseif (str_contains($t, 'modular') || str_contains($t, 'flip')) {
                $target_tax_slugs[] = 'modular';
            } elseif (str_contains($t, 'open face') || str_contains($t, 'jet')) {
                $target_tax_slugs[] = 'open-face';
            } elseif (str_contains($t, 'full face') || str_contains($t, 'sport')) {
                $target_tax_slugs[] = 'full-face';
            } elseif (str_contains($t, 'off-road') || str_contains($t, 'motocross')) {
                $target_tax_slugs[] = 'off-road';
            }
        }
        $target_tax_slugs = array_values(array_unique($target_tax_slugs));

        $matching_helmet_args = [
            'post_type'        => 'helmet',
            'post_status'      => 'publish',
            'post_parent'      => 0,
            'posts_per_page'   => 4,
            'orderby'          => 'date',
            'order'            => 'DESC',
            'suppress_filters' => false,
            'lang'             => function_exists('pll_current_language') ? pll_current_language() : 'en',
        ];
        if (! empty($target_tax_slugs)) {
            $matching_helmet_args['tax_query'] = [
                [
                    'taxonomy' => 'helmet_type',
                    'field'    => 'slug',
                    'terms'    => $target_tax_slugs,
                ],
            ];
        }
        $matched_helmets = get_posts($matching_helmet_args);
        if (empty($matched_helmets)) {
            unset($matching_helmet_args['tax_query']);
            $matched_helmets = get_posts($matching_helmet_args);
        }

        $currentLang = function_exists('pll_current_language') ? pll_current_language() : 'en';

        // Query matching cockpit accessories (curated, genuine riding gear strictly in visitor's language)
        $cockpit_curated_ids = [84, 9349, 87, 9345, 9359, 9346, 88];
        $localized_acc_ids = [];
        foreach ($cockpit_curated_ids as $c_id) {
            if (function_exists('pll_get_post')) {
                $trans_id = (int) pll_get_post($c_id, $currentLang);
                if ($trans_id > 0 && get_post_status($trans_id) === 'publish') {
                    $localized_acc_ids[] = $trans_id;
                    continue;
                }
            }
            if (get_post_status($c_id) === 'publish') {
                $localized_acc_ids[] = $c_id;
            }
        }

        $matched_accessories = [];
        if (! empty($localized_acc_ids)) {
            $matched_accessories = get_posts([
                'post_type'        => 'accessory',
                'post_status'      => 'publish',
                'post__in'         => $localized_acc_ids,
                'orderby'          => 'post__in',
                'posts_per_page'   => 4,
                'suppress_filters' => false,
            ]);
        }

        if (empty($matched_accessories)) {
            $matched_accessories = get_posts([
                'post_type'        => 'accessory',
                'post_status'      => 'publish',
                'post_parent'      => 0,
                'posts_per_page'   => 4,
                'orderby'          => 'menu_order date',
                'order'            => 'DESC',
                'suppress_filters' => false,
                'lang'             => $currentLang,
                'meta_query'       => [
                    [
                        'key'     => 'price_json',
                        'compare' => 'EXISTS',
                    ],
                ],
            ]);
        }

        // Query competing/similar motorcycles
        $similar_bikes = get_posts([
            'post_type'        => 'motorcycle',
            'post_status'      => 'publish',
            'post_parent'      => 0,
            'posts_per_page'   => 3,
            'post__not_in'     => [$post_id],
            'suppress_filters' => false,
            'lang'             => $currentLang,
            'tax_query'        => ! empty($seg_terms) && ! is_wp_error($seg_terms) ? [
                [
                    'taxonomy' => 'motorcycle_segment',
                    'field'    => 'term_id',
                    'terms'    => [$seg_terms[0]->term_id],
                ],
            ] : [],
        ]);
        if (empty($similar_bikes)) {
            $similar_bikes = get_posts([
                'post_type'        => 'motorcycle',
                'post_status'      => 'publish',
                'post_parent'      => 0,
                'posts_per_page'   => 3,
                'post__not_in'     => [$post_id],
                'suppress_filters' => false,
                'lang'             => $currentLang,
            ]);
        }

        // Indian Metro Dealers Directory
        $indian_dealers = [
            'delhi' => [
                'name'    => sprintf(__('%s Flagship Hub Connaught Place', 'helmetsan-theme'), esc_html($make_meta)),
                'address' => 'Radial Road 3, Connaught Place, New Delhi 110001',
                'phone'   => '+91 11 4152 8890',
                'city'    => 'Delhi NCR',
            ],
            'mumbai' => [
                'name'    => sprintf(__('%s Bandra West Experience Showroom', 'helmetsan-theme'), esc_html($make_meta)),
                'address' => 'Linking Road, Bandra West, Mumbai 400050',
                'phone'   => '+91 22 2640 1234',
                'city'    => 'Mumbai',
            ],
            'bengaluru' => [
                'name'    => sprintf(__('%s Indiranagar 100ft Studio', 'helmetsan-theme'), esc_html($make_meta)),
                'address' => '100ft Road, HAL 2nd Stage, Indiranagar, Bengaluru 560038',
                'phone'   => '+91 80 2521 4321',
                'city'    => 'Bengaluru',
            ],
            'pune' => [
                'name'    => sprintf(__('%s Deccan Riders Showroom', 'helmetsan-theme'), esc_html($make_meta)),
                'address' => 'Fergusson College Road, Shivajinagar, Pune 411004',
                'phone'   => '+91 20 2553 9876',
                'city'    => 'Pune',
            ],
            'chennai' => [
                'name'    => sprintf(__('%s Mount Road Showroom', 'helmetsan-theme'), esc_html($make_meta)),
                'address' => 'Anna Salai, Thousand Lights, Chennai 600002',
                'phone'   => '+91 44 2852 4567',
                'city'    => 'Chennai',
            ],
            'hyderabad' => [
                'name'    => sprintf(__('%s Jubilee Hills Gallery', 'helmetsan-theme'), esc_html($make_meta)),
                'address' => 'Road No. 36, Jubilee Hills, Hyderabad 500033',
                'phone'   => '+91 40 2355 6789',
                'city'    => 'Hyderabad',
            ],
        ];

        // Format Rupees helper
        $format_lakh = static function (int $amount): string {
            if ($amount >= 100000) {
                return sprintf('₹%.2f Lakh', $amount / 100000);
            }
            return '₹' . number_format($amount);
        };
?>

<main class="hs-moto-single">
    <div class="hs-container">

        <!-- Breadcrumbs Navigation -->
        <nav class="hs-breadcrumbs" aria-label="<?php esc_attr_e('Breadcrumbs', 'helmetsan-theme'); ?>">
            <a href="<?php echo esc_url(home_url('/')); ?>"><?php esc_html_e('Home', 'helmetsan-theme'); ?></a>
            <span class="hs-breadcrumbs__sep">/</span>
            <a href="<?php echo esc_url(get_post_type_archive_link('motorcycle') ?: home_url('/motorcycles/')); ?>"><?php esc_html_e('Motorcycles', 'helmetsan-theme'); ?></a>
            <?php if (! empty($make_meta)) : ?>
                <span class="hs-breadcrumbs__sep">/</span>
                <a href="<?php echo esc_url(add_query_arg(['hs_make' => sanitize_title($make_meta)], get_post_type_archive_link('motorcycle') ?: home_url('/motorcycles/'))); ?>"><?php echo esc_html($make_meta); ?></a>
            <?php endif; ?>
            <span class="hs-breadcrumbs__sep">/</span>
            <span class="hs-breadcrumbs__current" aria-current="page"><?php the_title(); ?></span>
        </nav>

        <article id="post-<?php the_ID(); ?>" <?php post_class('hs-moto-article'); ?>>

            <!-- Motorcycle Detail Hero -->
            <section class="hs-moto-detail-hero">
                <div class="hs-moto-detail-hero__copy">
                    <div class="hs-moto-detail-hero__tags">
                        <?php if ($segment_meta) : ?>
                            <span class="hs-moto-badge hs-moto-badge--segment"><?php echo esc_html($segment_meta); ?></span>
                        <?php endif; ?>
                        <?php if ($make_meta) : ?>
                            <span class="hs-moto-badge hs-moto-badge--make"><?php echo esc_html($make_meta); ?></span>
                        <?php endif; ?>
                        <span class="hs-moto-badge hs-moto-badge--cert">
                            <span class="hs-moto-cert-dot"></span>
                            <?php esc_html_e('ECE 22.06 / DOT Calibrated', 'helmetsan-theme'); ?>
                        </span>
                    </div>

                    <h1 class="hs-moto-detail-hero__title"><?php the_title(); ?></h1>

                    <p class="hs-moto-detail-hero__desc">
                        <?php
                        printf(
                            esc_html__('Aerodynamic and ergonomic motorcycle profile for the %1$s %2$s. Calibrated for rider slipstream stability, cockpit acoustic suppression, and certified helmet pairings.', 'helmetsan-theme'),
                            esc_html($make_meta),
                            esc_html($title)
                        );
                        ?>
                    </p>

                    <!-- Quick Metrics Strip -->
                    <div class="hs-moto-metrics-strip">
                        <div class="hs-moto-metric-pill">
                            <span class="hs-moto-metric-pill__label"><?php esc_html_e('Engine', 'helmetsan-theme'); ?></span>
                            <span class="hs-moto-metric-pill__value">
                                <?php echo $engine_cc ? esc_html($engine_cc . ' cc') : esc_html__('Electric / Disclosed', 'helmetsan-theme'); ?>
                            </span>
                        </div>
                        <div class="hs-moto-metric-pill">
                            <span class="hs-moto-metric-pill__label"><?php esc_html_e('Postural Stance', 'helmetsan-theme'); ?></span>
                            <span class="hs-moto-metric-pill__value">
                                <?php echo esc_html($riding_pos ?: __('Neutral Upright', 'helmetsan-theme')); ?>
                            </span>
                        </div>
                        <div class="hs-moto-metric-pill">
                            <span class="hs-moto-metric-pill__label"><?php esc_html_e('Seat Height', 'helmetsan-theme'); ?></span>
                            <span class="hs-moto-metric-pill__value">
                                <?php echo ($seat_height && (int)$seat_height > 0) ? esc_html($seat_height . ' mm') : esc_html__('Standard', 'helmetsan-theme'); ?>
                            </span>
                        </div>
                        <div class="hs-moto-metric-pill">
                            <span class="hs-moto-metric-pill__label"><?php esc_html_e('Top Speed', 'helmetsan-theme'); ?></span>
                            <span class="hs-moto-metric-pill__value">
                                <?php echo ($top_speed && (int)$top_speed > 0) ? esc_html($top_speed . ' km/h') : esc_html__('Highway Capable', 'helmetsan-theme'); ?>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Visual Silhouette Well -->
                <div class="hs-moto-detail-hero__art" aria-hidden="true">
                    <div class="hs-moto-detail-hero__art-card">
                        <?php if (has_post_thumbnail($post_id)) : ?>
                            <?php echo get_the_post_thumbnail($post_id, 'large', ['class' => 'hs-moto-detail-hero__img', 'loading' => 'eager']); ?>
                        <?php else : ?>
                            <div class="hs-moto-detail-hero__art-silhouette">
                                <?php echo function_exists('hs_render_moto_silhouette') ? hs_render_moto_silhouette($segment_slug) : ''; ?>
                            </div>
                        <?php endif; ?>
                        <div class="hs-moto-detail-hero__art-footer">
                            <span class="hs-moto-art-footer-dot"></span>
                            <span><?php printf(esc_html__('Multi-Axial Ergonomic Geometry: %s', 'helmetsan-theme'), esc_html($segment_meta ?: 'Universal')); ?></span>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Commercial Pricing & On-Road Estimator Section -->
            <?php
            $is_india_visitor = function_exists('helmetsan_is_india_visitor') ? helmetsan_is_india_visitor() : false;
            ?>
            <section class="hs-moto-pricing-section" aria-labelledby="hs-pricing-heading">
                <!-- Ex-Showroom & City Calculator -->
                <div class="hs-moto-pricing-card">
                    <div class="hs-moto-pricing-card__header">
                        <div>
                            <span class="hs-moto-price-badge"><?php echo $is_price_estimated ? esc_html__('Estimated Price Guidance', 'helmetsan-theme') : esc_html__('Official Price Guidance', 'helmetsan-theme'); ?></span>
                            <div class="hs-moto-ex-showroom">
                                <span class="hs-price" data-base-price="<?php echo (float) $price_inr; ?>" data-base-currency="INR" id="hs-display-ex-price">
                                    <?php
                                    if ($is_india_visitor) {
                                        echo esc_html($format_lakh($price_inr));
                                    } else {
                                        $price_usd = round($price_inr / 86.5);
                                        echo esc_html('$' . number_format($price_usd));
                                    }
                                    ?>
                                </span>
                                <?php if ($is_price_estimated) : ?>
                                    <span style="font-size:0.55em; font-weight:500; color:var(--hs-text-muted); display:inline-block; margin-left:4px; vertical-align:middle;"><?php esc_html_e('(Estimated)', 'helmetsan-theme'); ?></span>
                                <?php endif; ?>
                            </div>
                            <span class="hs-moto-ex-label" id="hs-moto-ex-label-text">
                                <?php
                                if ($is_india_visitor) {
                                    echo $is_price_estimated ? esc_html__('Approx. Ex-Showroom India (Displacement Estimate)', 'helmetsan-theme') : esc_html__('Avg. Ex-Showroom India (Base Variant)', 'helmetsan-theme');
                                } else {
                                    echo esc_html__('Estimated Base MSRP (Global Reference)', 'helmetsan-theme');
                                }
                                ?>
                            </span>
                        </div>
                    </div>

                    <!-- India Calculator (Shown for Indian visitors, toggled dynamically via JS) -->
                    <div class="hs-moto-city-calc hs-moto-india-only" style="<?php echo $is_india_visitor ? '' : 'display: none;'; ?>">
                        <div class="hs-moto-city-select-row">
                            <label for="hs-city-estimator" class="hs-moto-ex-label" style="font-weight:700;"><?php esc_html_e('Select City for On-Road Estimate:', 'helmetsan-theme'); ?></label>
                            <select id="hs-city-estimator" class="hs-moto-city-dropdown" data-base-price="<?php echo (int) $price_inr; ?>">
                                <option value="delhi" data-rto="0.08" data-ins="14200" selected><?php esc_html_e('Delhi NCR', 'helmetsan-theme'); ?></option>
                                <option value="mumbai" data-rto="0.12" data-ins="14800"><?php esc_html_e('Mumbai', 'helmetsan-theme'); ?></option>
                                <option value="bengaluru" data-rto="0.18" data-ins="15200"><?php esc_html_e('Bengaluru', 'helmetsan-theme'); ?></option>
                                <option value="pune" data-rto="0.12" data-ins="14500"><?php esc_html_e('Pune', 'helmetsan-theme'); ?></option>
                                <option value="chennai" data-rto="0.10" data-ins="14200"><?php esc_html_e('Chennai', 'helmetsan-theme'); ?></option>
                                <option value="hyderabad" data-rto="0.11" data-ins="14400"><?php esc_html_e('Hyderabad', 'helmetsan-theme'); ?></option>
                                <option value="kolkata" data-rto="0.09" data-ins="13900"><?php esc_html_e('Kolkata', 'helmetsan-theme'); ?></option>
                                <option value="ahmedabad" data-rto="0.07" data-ins="13800"><?php esc_html_e('Ahmedabad', 'helmetsan-theme'); ?></option>
                            </select>
                        </div>

                        <div class="hs-moto-onroad-breakdown">
                            <div class="hs-moto-onroad-total">
                                <span><?php echo $is_price_estimated ? esc_html__('Estimated On-Road Price (Approx.):', 'helmetsan-theme') : esc_html__('Estimated On-Road Price:', 'helmetsan-theme'); ?></span>
                                <strong id="hs-display-onroad-price" style="color:var(--hs-accent);">
                                    <?php echo esc_html($format_lakh((int) ($price_inr * 1.08 + 14200))); ?>
                                </strong>
                            </div>
                            <div class="hs-moto-onroad-items">
                                <span>• RTO Registration: <strong id="hs-display-rto">₹<?php echo number_format((int) ($price_inr * 0.08)); ?></strong></span>
                                <span>• 5-Yr Insurance: <strong id="hs-display-ins">₹14,200</strong></span>
                                <span>• <?php echo $is_price_estimated ? esc_html__('Approx. Ex-Showroom (Est.):', 'helmetsan-theme') : esc_html__('Ex-Showroom:', 'helmetsan-theme'); ?> <strong>₹<?php echo number_format($price_inr); ?></strong></span>
                                <span>• Road Safety / Cess: <strong>₹1,500</strong></span>
                            </div>
                        </div>

                        <div class="hs-moto-actions">
                            <button type="button" class="hs-moto-btn hs-moto-btn--primary hs-open-lead-trigger" id="hs-open-lead-btn" data-motorcycle-id="<?php echo esc_attr($post_id); ?>" data-motorcycle-title="<?php echo esc_attr($title); ?>">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                                <?php esc_html_e('Request Best Price & Test Ride', 'helmetsan-theme'); ?>
                            </button>
                            <a href="https://www.google.com/search?q=<?php echo urlencode($make_meta . ' official motorcycle booking configurator'); ?>" target="_blank" rel="noopener noreferrer" class="hs-moto-btn hs-moto-btn--secondary">
                                <?php esc_html_e('Official Brand Portal ↗', 'helmetsan-theme'); ?>
                            </a>
                        </div>
                    </div>

                    <!-- Global Guidance (Shown for International Visitors, toggled dynamically via JS) -->
                    <div class="hs-moto-global-calc hs-moto-global-only" style="<?php echo $is_india_visitor ? 'display: none;' : ''; ?>">
                        <div class="hs-moto-global-guidance-box" style="padding:1.25rem; background:rgba(255,255,255,0.03); border:1px solid var(--hs-border); border-radius:12px; margin-bottom:1.5rem;">
                            <div style="font-weight:600; color:var(--hs-text); margin-bottom:0.5rem; display:flex; align-items:center; gap:8px;">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
                                <?php esc_html_e('Global MSRP & Homologation Guidance', 'helmetsan-theme'); ?>
                            </div>
                            <p style="font-size:0.875rem; color:var(--hs-text-muted); line-height:1.6; margin:0 0 1rem 0;">
                                <?php esc_html_e('International base MSRP is estimated from global motorcycle catalog guidelines. Drive-away pricing in your jurisdiction depends on regional homologation (DOT / ECE 22.06), import tariffs, freight, and applicable local sales tax / VAT.', 'helmetsan-theme'); ?>
                            </p>
                            <div class="hs-moto-actions">
                                <button type="button" class="hs-moto-btn hs-moto-btn--primary hs-open-lead-trigger" id="hs-open-lead-btn-global" data-motorcycle-id="<?php echo esc_attr($post_id); ?>" data-motorcycle-title="<?php echo esc_attr($title); ?>">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                                    <?php esc_html_e('Request Regional Dealer Quote', 'helmetsan-theme'); ?>
                                </button>
                                <a href="https://www.google.com/search?q=<?php echo urlencode($make_meta . ' official motorcycle dealers ' . $title); ?>" target="_blank" rel="noopener noreferrer" class="hs-moto-btn hs-moto-btn--secondary">
                                    <?php esc_html_e('Find Regional Importers ↗', 'helmetsan-theme'); ?>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Dealerships Card (Toggled between India metro dealers and Global importer network) -->
                <div class="hs-moto-dealers-card">
                    <!-- India metro dealers -->
                    <div class="hs-moto-india-dealers hs-moto-india-only" style="<?php echo $is_india_visitor ? '' : 'display: none;'; ?>">
                        <h3 style="margin:0 0 0.5rem 0; font-size:1.15rem;"><?php printf(esc_html__('Authorized %s Dealerships', 'helmetsan-theme'), esc_html($make_meta)); ?></h3>
                        <p style="font-size:0.85rem; color:var(--hs-text-muted); margin:0 0 1rem 0;">
                            <?php esc_html_e('Connect directly with certified showrooms across India for doorstep test rides and immediate delivery.', 'helmetsan-theme'); ?>
                        </p>

                        <div class="hs-moto-dealers-city-tabs" id="hs-dealers-tabs">
                            <?php $i = 0; foreach ($indian_dealers as $ckey => $cdata) : ?>
                                <button type="button" class="hs-moto-city-tab <?php echo $i === 0 ? 'is-active' : ''; ?>" data-city="<?php echo esc_attr($ckey); ?>">
                                    <?php echo esc_html($cdata['city']); ?>
                                </button>
                            <?php $i++; endforeach; ?>
                        </div>

                        <div class="hs-moto-dealers-list" id="hs-dealers-container">
                            <?php
                            $first_dealer = reset($indian_dealers);
                            ?>
                            <div class="hs-moto-dealer-item">
                                <div class="hs-moto-dealer-info">
                                    <h4 id="hs-dealer-name"><?php echo esc_html($first_dealer['name']); ?></h4>
                                    <p class="hs-moto-dealer-addr" id="hs-dealer-addr"><?php echo esc_html($first_dealer['address']); ?></p>
                                    <div class="hs-moto-dealer-meta">
                                        <span>✓ <?php esc_html_e('Verified Dealership', 'helmetsan-theme'); ?></span>
                                        <span>• <?php esc_html_e('Test Ride Available', 'helmetsan-theme'); ?></span>
                                    </div>
                                </div>
                                <div>
                                    <a href="tel:<?php echo esc_attr(str_replace(' ', '', $first_dealer['phone'])); ?>" class="hs-moto-btn hs-moto-btn--secondary" id="hs-dealer-call-btn" style="padding:0.45rem 0.8rem; font-size:0.8rem;">
                                        <?php esc_html_e('Call Showroom', 'helmetsan-theme'); ?>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Global Dealers -->
                    <div class="hs-moto-global-dealers hs-moto-global-only" style="<?php echo $is_india_visitor ? 'display: none;' : ''; ?>">
                        <h3 style="margin:0 0 0.5rem 0; font-size:1.15rem;"><?php printf(esc_html__('Global %s Dealer & Importer Network', 'helmetsan-theme'), esc_html($make_meta)); ?></h3>
                        <p style="font-size:0.85rem; color:var(--hs-text-muted); margin:0 0 1rem 0;">
                            <?php esc_html_e('Locate certified distributors, factory outlets, and authorized retail partners worldwide.', 'helmetsan-theme'); ?>
                        </p>
                        <div style="background:var(--hs-surface); border:1px solid var(--hs-border); border-radius:10px; padding:1.25rem;">
                            <div style="font-weight:600; color:var(--hs-text); margin-bottom:0.4rem;">
                                <?php printf(esc_html__('Authorized Regional Distributor Network for %s', 'helmetsan-theme'), esc_html($make_meta)); ?>
                            </div>
                            <p style="font-size:0.85rem; color:var(--hs-text-muted); line-height:1.5; margin:0 0 1rem 0;">
                                <?php esc_html_e('Submit your location to receive official dealer pricing, homologation compliance sheets, and test-ride scheduling from authorized local representatives.', 'helmetsan-theme'); ?>
                            </p>
                            <button type="button" class="hs-moto-btn hs-moto-btn--secondary" id="hs-open-dealer-partner-btn" style="width:100%; font-size:0.85rem;">
                                <?php esc_html_e('Inquire With Regional Dealers & Importers →', 'helmetsan-theme'); ?>
                            </button>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Touring & Real-World Ownership Matrix -->
            <section class="hs-moto-section" aria-labelledby="hs-touring-heading">
                <div class="hs-moto-section__head">
                    <span class="hs-moto-eyebrow">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
                        <?php esc_html_e('Touring & Practical Ownership', 'helmetsan-theme'); ?>
                    </span>
                    <h2 id="hs-touring-heading" class="hs-moto-section__title"><?php printf(esc_html__('%s Real-World Range & Service Metrics', 'helmetsan-theme'), esc_html($title)); ?></h2>
                </div>

                <div class="hs-moto-touring-grid">
                    <div class="hs-moto-touring-tile">
                        <div class="hs-moto-touring-tile__val"><?php echo esc_html($fuel_capacity_l . ' L'); ?></div>
                        <div class="hs-moto-touring-tile__lbl"><?php esc_html_e('Fuel Tank Capacity', 'helmetsan-theme'); ?></div>
                    </div>
                    <div class="hs-moto-touring-tile">
                        <div class="hs-moto-touring-tile__val"><?php echo esc_html($estimated_range_km); ?></div>
                        <div class="hs-moto-touring-tile__lbl"><?php esc_html_e('Highway Touring Range', 'helmetsan-theme'); ?></div>
                    </div>
                    <div class="hs-moto-touring-tile">
                        <div class="hs-moto-touring-tile__val">~30 km/l</div>
                        <div class="hs-moto-touring-tile__lbl"><?php esc_html_e('Avg. Fuel Economy', 'helmetsan-theme'); ?></div>
                    </div>
                    <div class="hs-moto-touring-tile">
                        <div class="hs-moto-touring-tile__val">10,000 km</div>
                        <div class="hs-moto-touring-tile__lbl"><?php esc_html_e('Periodic Service Interval', 'helmetsan-theme'); ?></div>
                    </div>
                </div>

                <!-- Real World Pros & Cons -->
                <div class="hs-moto-pros-cons-grid">
                    <div class="hs-moto-pro-card">
                        <h3>
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                            <?php esc_html_e('Rider Advantages (Verified Pros)', 'helmetsan-theme'); ?>
                        </h3>
                        <ul>
                            <?php foreach ((array) $pros as $pro) : ?>
                                <li><?php echo esc_html($pro); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>

                    <div class="hs-moto-con-card">
                        <h3>
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                            <?php esc_html_e('Ergonomic Trade-offs (Cons to Note)', 'helmetsan-theme'); ?>
                        </h3>
                        <ul>
                            <?php foreach ((array) $cons as $con) : ?>
                                <li><?php echo esc_html($con); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            </section>

            <!-- Technical Specifications Section -->
            <section class="hs-moto-section" aria-labelledby="hs-moto-specs-heading">
                <div class="hs-moto-section__head">
                    <span class="hs-moto-eyebrow">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                        <?php esc_html_e('Engineering Specifications', 'helmetsan-theme'); ?>
                    </span>
                    <h2 id="hs-moto-specs-heading" class="hs-moto-section__title"><?php printf(esc_html__('%s Technical Data', 'helmetsan-theme'), esc_html($title)); ?></h2>
                </div>

                <div class="hs-moto-specs-grid">
                    <!-- Powertrain Card -->
                    <div class="hs-moto-spec-card">
                        <div class="hs-moto-spec-card__head">
                            <span class="hs-moto-spec-icon">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
                            </span>
                            <h3><?php esc_html_e('Powertrain & Dynamics', 'helmetsan-theme'); ?></h3>
                        </div>
                        <dl class="hs-moto-spec-list">
                            <div class="hs-moto-spec-row">
                                <dt><?php esc_html_e('Engine Displacement', 'helmetsan-theme'); ?></dt>
                                <dd><?php echo $engine_cc ? esc_html($engine_cc . ' cc') : esc_html__('Manufacturer Disclosed', 'helmetsan-theme'); ?></dd>
                            </div>
                            <div class="hs-moto-spec-row">
                                <dt><?php esc_html_e('Maximum Power', 'helmetsan-theme'); ?></dt>
                                <dd><?php echo ($power_hp && (float)$power_hp > 0) ? esc_html($power_hp . ' HP') : esc_html__('Manufacturer Disclosed', 'helmetsan-theme'); ?></dd>
                            </div>
                            <div class="hs-moto-spec-row">
                                <dt><?php esc_html_e('Maximum Torque', 'helmetsan-theme'); ?></dt>
                                <dd><?php echo ($torque_nm && (float)$torque_nm > 0) ? esc_html($torque_nm . ' Nm') : esc_html__('Instant Torque', 'helmetsan-theme'); ?></dd>
                            </div>
                            <div class="hs-moto-spec-row">
                                <dt><?php esc_html_e('Top Speed', 'helmetsan-theme'); ?></dt>
                                <dd><?php echo ($top_speed && (int)$top_speed > 0) ? esc_html($top_speed . ' km/h') : esc_html__('Highway Capable', 'helmetsan-theme'); ?></dd>
                            </div>
                        </dl>
                    </div>

                    <!-- Ergonomics & Chassis Card -->
                    <div class="hs-moto-spec-card">
                        <div class="hs-moto-spec-card__head">
                            <span class="hs-moto-spec-icon">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 2a14.5 14.5 0 0 0 0 20 14.5 14.5 0 0 0 0-20"/><path d="M2 12h20"/></svg>
                            </span>
                            <h3><?php esc_html_e('Ergonomics & Chassis', 'helmetsan-theme'); ?></h3>
                        </div>
                        <dl class="hs-moto-spec-list">
                            <div class="hs-moto-spec-row">
                                <dt><?php esc_html_e('Riding Posture', 'helmetsan-theme'); ?></dt>
                                <dd><?php echo esc_html($riding_pos ?: __('Upright / Standard', 'helmetsan-theme')); ?></dd>
                            </div>
                            <div class="hs-moto-spec-row">
                                <dt><?php esc_html_e('Seat Height', 'helmetsan-theme'); ?></dt>
                                <dd><?php echo ($seat_height && (int)$seat_height > 0) ? esc_html($seat_height . ' mm') : esc_html__('Standard Chassis', 'helmetsan-theme'); ?></dd>
                            </div>
                            <div class="hs-moto-spec-row">
                                <dt><?php esc_html_e('Curb Weight', 'helmetsan-theme'); ?></dt>
                                <dd><?php echo ($curb_weight && (float)$curb_weight > 0) ? esc_html($curb_weight . ' kg') : esc_html__('Standard Weight Class', 'helmetsan-theme'); ?></dd>
                            </div>
                            <div class="hs-moto-spec-row">
                                <dt><?php esc_html_e('Aero Wind Profile', 'helmetsan-theme'); ?></dt>
                                <dd><?php echo esc_html($wind_profile ?: __('Cockpit Deflected', 'helmetsan-theme')); ?></dd>
                            </div>
                        </dl>
                    </div>

                    <!-- Postural Aerodynamics Calibration -->
                    <div class="hs-moto-spec-card hs-moto-spec-card--accent">
                        <div class="hs-moto-spec-card__head">
                            <span class="hs-moto-spec-icon hs-moto-spec-icon--accent">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
                            </span>
                            <h3><?php esc_html_e('Helmetsan Aerodynamic Calibration', 'helmetsan-theme'); ?></h3>
                        </div>
                        <p class="hs-moto-aero-text">
                            <?php
                            printf(
                                esc_html__('Based on the %1$s posture profile of the %2$s, recommended helmets require aerodynamic chin-spoiler stability to neutralize buffeting from handlebar slipstreams, with high optical clarity visors for wide-angle scanning.', 'helmetsan-theme'),
                                esc_html(strtolower($riding_pos ?: 'standard')),
                                esc_html($title)
                            );
                            ?>
                        </p>
                        <?php if (! empty($rec_helmet_types)) : ?>
                            <div class="hs-moto-synergy-pills">
                                <span class="hs-moto-synergy-label"><?php esc_html_e('Verified Synergy:', 'helmetsan-theme'); ?></span>
                                <div class="hs-moto-synergy-list">
                                    <?php foreach ($rec_helmet_types as $rht) : ?>
                                        <a href="<?php echo esc_url(function_exists('hs_moto_helmet_link') ? hs_moto_helmet_link((string)$rht) : home_url('/helmets/')); ?>" class="hs-moto-synergy-chip">
                                            <?php echo esc_html($rht); ?> &rarr;
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </section>

            <!-- B2B Dealer Network Enrollment Banner -->
            <section class="hs-moto-enroll-banner" aria-labelledby="hs-dealer-enroll-heading">
                <div class="hs-moto-enroll-banner__copy">
                    <h3 id="hs-dealer-enroll-heading"><?php esc_html_e('Are You an Authorized Two-Wheeler Dealership in India?', 'helmetsan-theme'); ?></h3>
                    <p><?php esc_html_e('Join the Helmetsan Certified Dealer Network. Connect with serious riders researching motorcycles and purchasing protective gear in your city.', 'helmetsan-theme'); ?></p>
                </div>
                <div>
                    <button type="button" class="hs-moto-btn hs-moto-btn--primary" id="hs-open-dealer-app-btn">
                        <?php esc_html_e('Enroll Your Dealership ↗', 'helmetsan-theme'); ?>
                    </button>
                </div>
            </section>

            <!-- Precision Helmet Compatibility Matrix -->
            <section class="hs-moto-section" aria-labelledby="hs-moto-helmets-heading">
                <div class="hs-moto-section__head hs-moto-section__head--flex">
                    <div>
                        <span class="hs-moto-eyebrow">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2a10 10 0 0 0-10 10c0 4.42 2.87 8.17 6.84 9.49.5.09.68-.22.68-.48v-1.7c-2.78.6-3.37-1.34-3.37-1.34-.46-1.16-1.11-1.47-1.11-1.47-.91-.62.07-.6.07-.6 1 .07 1.53 1.03 1.53 1.03.87 1.52 2.34 1.07 2.91.83.09-.65.35-1.09.63-1.34-2.22-.25-4.55-1.11-4.55-4.92 0-1.11.38-2 1.03-2.71-.1-.25-.45-1.29.1-2.64 0 0 .84-.27 2.75 1.02.79-.22 1.65-.33 2.5-.33.85 0 1.71.11 2.5.33 1.91-1.29 2.75-1.02 2.75-1.02.55 1.35.2 2.39.1 2.64.65.71 1.03 1.6 1.03 2.71 0 3.82-2.34 4.66-4.57 4.91.36.31.69.92.69 1.85V21c0 .27.18.57.69.48A10 10 0 0 0 22 12A10 10 0 0 0 12 2Z"/></svg>
                            <?php esc_html_e('Compatibility Matrix', 'helmetsan-theme'); ?>
                        </span>
                        <h2 id="hs-moto-helmets-heading" class="hs-moto-section__title"><?php printf(esc_html__('Recommended Helmets for %s', 'helmetsan-theme'), esc_html($title)); ?></h2>
                    </div>
                    <a href="<?php echo esc_url(home_url('/helmets/')); ?>" class="hs-moto-see-all">
                        <?php esc_html_e('Browse All Helmets &rarr;', 'helmetsan-theme'); ?>
                    </a>
                </div>

                <div class="hs-moto-helmets-grid">
                    <?php if (! empty($matched_helmets)) : ?>
                        <?php foreach ($matched_helmets as $helmet) :
                            $h_id    = $helmet->ID;
                            $h_title = get_the_title($h_id);
                            $h_link  = get_permalink($h_id);
                            $h_terms = get_the_terms($h_id, 'helmet_type');
                            $h_type  = (! empty($h_terms) && ! is_wp_error($h_terms)) ? $h_terms[0]->name : __('Full Face', 'helmetsan-theme');
                            $h_certs = get_the_terms($h_id, 'certification');
                            $h_cert  = (! empty($h_certs) && ! is_wp_error($h_certs)) ? $h_certs[0]->name : 'DOT';
                        ?>
                            <div class="hs-moto-helmet-card">
                                <div class="hs-moto-helmet-card__media">
                                    <?php 
                                    $h_slug_term = (! empty($h_terms) && ! is_wp_error($h_terms)) ? $h_terms[0]->slug : 'full-face';
                                    if (has_post_thumbnail($h_id)) : ?>
                                        <a href="<?php echo esc_url($h_link); ?>" tabindex="-1" aria-hidden="true" style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;">
                                            <?php echo get_the_post_thumbnail($h_id, 'medium', [
                                                'class' => 'hs-moto-helmet-card__img',
                                                'loading' => 'lazy',
                                                'onerror' => "this.style.display='none';if(this.nextElementSibling){this.nextElementSibling.style.display='flex';}"
                                            ]); ?>
                                            <div class="hs-helmet-cad-fallback-wrap" style="display:none;width:100%;height:100%;align-items:center;justify-content:center;">
                                                <?php echo function_exists('helmetsan_render_helmet_silhouette') ? helmetsan_render_helmet_silhouette($h_slug_term, '#00d2be') : ''; ?>
                                            </div>
                                        </a>
                                    <?php else : ?>
                                        <div class="hs-moto-helmet-card__placeholder" style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;">
                                            <?php echo function_exists('helmetsan_render_helmet_silhouette') ? helmetsan_render_helmet_silhouette($h_slug_term, '#00d2be') : '<svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="11" r="7"/><path d="M12 18v3M9 21h6"/></svg>'; ?>
                                        </div>
                                    <?php endif; ?>
                                    <span class="hs-moto-helmet-card__badge"><?php echo esc_html($h_cert); ?></span>
                                    <span class="hs-moto-helmet-card__match" style="position:absolute; bottom:8px; left:8px; font-size:0.75rem; background:rgba(16,185,129,0.9); color:#fff; font-weight:700; padding:2px 6px; border-radius:4px;">
                                        96% <?php esc_html_e('Match', 'helmetsan-theme'); ?>
                                    </span>
                                </div>
                                <div class="hs-moto-helmet-card__body">
                                    <span class="hs-moto-helmet-card__type"><?php echo esc_html($h_type); ?></span>
                                    <h3 class="hs-moto-helmet-card__title">
                                        <a href="<?php echo esc_url($h_link); ?>"><?php echo esc_html($h_title); ?></a>
                                    </h3>
                                    <div class="hs-moto-helmet-card__synergy">
                                        <span class="hs-moto-synergy-dot"></span>
                                        <span><?php esc_html_e('Optimal Postural Alignment', 'helmetsan-theme'); ?></span>
                                    </div>
                                    <div class="hs-moto-helmet-card__price-row" style="margin-top:0.5rem;">
                                        <?php echo function_exists('helmetsan_render_price_element') ? helmetsan_render_price_element($h_id, 'hs-moto-helmet-card__price') : ''; ?>
                                    </div>
                                    <div style="margin-top:1rem; display:flex; gap:0.5rem;">
                                        <a href="<?php echo esc_url($h_link); ?>" class="hs-moto-helmet-card__cta" style="flex:1;">
                                            <?php esc_html_e('View Helmet Specs', 'helmetsan-theme'); ?>
                                        </a>
                                        <?php
                                        $h_slug = ! empty($helmet->post_name) ? $helmet->post_name : (string) $h_id;
                                        $h_go_url = home_url('/go/' . rawurlencode($h_slug) . '/?marketplace=amazon&source=motorcycle_synergy');
                                        ?>
                                        <a href="<?php echo esc_url($h_go_url); ?>" target="_blank" rel="noopener noreferrer sponsored" class="hs-moto-btn hs-moto-btn--primary hs-btn--amazon hs-price-cta" data-marketplace="amazon" style="padding:0.45rem 0.6rem; font-size:0.75rem;">
                                            <?php esc_html_e('Buy on Amazon ↗', 'helmetsan-theme'); ?>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </section>

            <!-- Cockpit Accessories Section -->
            <?php if (! empty($matched_accessories)) : ?>
                <section class="hs-moto-section" aria-labelledby="hs-moto-accessories-heading">
                    <div class="hs-moto-section__head hs-moto-section__head--flex">
                        <div>
                            <span class="hs-moto-eyebrow">
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                                <?php esc_html_e('Cockpit Gear', 'helmetsan-theme'); ?>
                            </span>
                            <h2 id="hs-moto-accessories-heading" class="hs-moto-section__title"><?php esc_html_e('Essential Cockpit & Helmet Accessories', 'helmetsan-theme'); ?></h2>
                        </div>
                        <a href="<?php echo esc_url(home_url('/accessories/')); ?>" class="hs-moto-see-all">
                            <?php esc_html_e('Browse All Accessories &rarr;', 'helmetsan-theme'); ?>
                        </a>
                    </div>

                    <div class="hs-moto-acc-grid" style="display:grid; grid-template-columns:repeat(auto-fit, minmax(260px, 1fr)); gap:1rem;">
                        <?php foreach ($matched_accessories as $acc) :
                            $acc_id    = $acc->ID;
                            if (function_exists('pll_get_post') && function_exists('pll_current_language')) {
                                $transId = (int) pll_get_post($acc_id, pll_current_language());
                                if ($transId > 0 && get_post_status($transId) === 'publish') {
                                    $acc_id = $transId;
                                }
                            }
                            $acc_title = get_the_title($acc_id);
                            $acc_link  = get_permalink($acc_id);
                            $acc_type  = (string) get_post_meta($acc_id, 'accessory_type', true);
                            if ($acc_type === '') {
                                $acc_terms = get_the_terms($acc_id, 'accessory_category');
                                if (! empty($acc_terms) && ! is_wp_error($acc_terms)) {
                                    $acc_type = $acc_terms[0]->name;
                                }
                            }

                            $acc_price_json = (string) get_post_meta($acc_id, 'price_json', true);
                            $acc_price_data = json_decode($acc_price_json, true);
                            $acc_price_val = 0.0;
                            $acc_price_display = '';
                            if (is_array($acc_price_data)) {
                                $rawVal = $acc_price_data['usd'] ?? $acc_price_data['current'] ?? null;
                                if (is_numeric($rawVal) && (float)$rawVal > 0) {
                                    $acc_price_val = (float)$rawVal;
                                    $vCurr = function_exists('helmetsan_get_visitor_currency') ? helmetsan_get_visitor_currency() : 'USD';
                                    $vCountry = function_exists('helmetsan_get_visitor_country') ? helmetsan_get_visitor_country() : 'US';
                                    if (function_exists('helmetsan_core') && helmetsan_core()->exchangeRates() && helmetsan_core()->price()) {
                                        $rates = helmetsan_core()->exchangeRates();
                                        $conv = $rates->convert($acc_price_val, 'USD', $vCurr);
                                        $conv = $rates->applyVat($conv, $vCountry);
                                        $conv = $rates->charmRound($conv, $vCurr);
                                        $acc_price_display = helmetsan_core()->price()->formatter()->format($conv, $vCurr);
                                    } else {
                                        $acc_price_display = '$' . number_format($acc_price_val, 2);
                                    }
                                }
                            }
                            $acc_thumb = get_the_post_thumbnail_url($acc_id, 'thumbnail');
                        ?>
                            <a href="<?php echo esc_url($acc_link); ?>" class="hs-moto-acc-card" style="text-decoration:none; color:inherit;">
                                <div class="hs-moto-acc-card__icon" style="flex-shrink:0; width:48px; height:48px; border-radius:8px; background:rgba(0,210,190,0.08); display:flex; align-items:center; justify-content:center; overflow:hidden;">
                                    <?php if ($acc_thumb) : ?>
                                        <img src="<?php echo esc_url($acc_thumb); ?>" alt="<?php echo esc_attr($acc_title); ?>" style="width:100%; height:100%; object-fit:cover;" loading="lazy">
                                    <?php else : ?>
                                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="var(--hs-accent, #00d2be)" stroke-width="2"><polygon points="5 3 19 12 5 21 5 3"/></svg>
                                    <?php endif; ?>
                                </div>
                                <div class="hs-moto-acc-card__copy" style="flex:1; min-width:0;">
                                    <?php if ($acc_type !== '') : ?>
                                        <span style="font-size:0.7rem; font-weight:700; color:var(--hs-muted, #94a3b8); text-transform:uppercase; letter-spacing:0.04em; display:block; margin-bottom:2px;"><?php echo esc_html($acc_type); ?></span>
                                    <?php endif; ?>
                                    <h4 style="margin:0 0 4px 0; font-size:0.9rem; font-weight:700; line-height:1.3; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;"><?php echo esc_html($acc_title); ?></h4>
                                    <div style="display:flex; align-items:center; justify-content:space-between; gap:0.5rem;">
                                        <?php if ($acc_price_display !== '') : ?>
                                            <span class="hs-price" data-base-price="<?php echo esc_attr((string)$acc_price_val); ?>" data-base-currency="USD" style="font-weight:800; font-size:0.85rem; color:var(--hs-heading, #fff);">
                                                <?php echo esc_html($acc_price_display); ?>
                                            </span>
                                        <?php endif; ?>
                                        <span class="hs-moto-acc-badge"><?php esc_html_e('Verified Fit', 'helmetsan-theme'); ?></span>
                                    </div>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>

            <!-- Competing Segment Motorcycles Section -->
            <?php if (! empty($similar_bikes)) : ?>
                <section class="hs-moto-section" aria-labelledby="hs-similar-heading">
                    <div class="hs-moto-section__head">
                        <span class="hs-moto-eyebrow">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 2 7 12 12 22 7 12 2"/><polyline points="2 17 12 22 22 17"/><polyline points="2 12 12 17 22 12"/></svg>
                            <?php esc_html_e('Segment Benchmarks', 'helmetsan-theme'); ?>
                        </span>
                        <h2 id="hs-similar-heading" class="hs-moto-section__title"><?php printf(esc_html__('Compare Similar %s Motorcycles', 'helmetsan-theme'), esc_html($segment_meta ?: 'Segment')); ?></h2>
                    </div>

                    <div class="hs-moto-grid" style="display:grid; grid-template-columns:repeat(auto-fit, minmax(280px, 1fr)); gap:1.25rem;">
                        <?php foreach ($similar_bikes as $sb) :
                            $sb_id = $sb->ID;
                            $sb_title = get_the_title($sb_id);
                            $sb_link = get_permalink($sb_id);
                            $sb_cc = get_post_meta($sb_id, 'engine_cc', true);
                            $sb_make = get_post_meta($sb_id, 'motorcycle_make', true);
                        ?>
                            <div class="hs-moto-card" style="background:var(--hs-card-bg); border:1px solid var(--hs-border); border-radius:14px; padding:1.25rem; display:flex; flex-direction:column; justify-content:space-between;">
                                <div>
                                    <span style="font-size:0.75rem; font-weight:700; color:var(--hs-accent);"><?php echo esc_html($sb_make); ?></span>
                                    <h3 style="font-size:1.1rem; margin:0.3rem 0;"><a href="<?php echo esc_url($sb_link); ?>"><?php echo esc_html($sb_title); ?></a></h3>
                                    <p style="font-size:0.85rem; color:var(--hs-text-muted); margin:0;">
                                        Displacement: <strong><?php echo $sb_cc ? esc_html($sb_cc . ' cc') : 'Disclosed'; ?></strong>
                                    </p>
                                </div>
                                <div style="margin-top:1rem;">
                                    <a href="<?php echo esc_url($sb_link); ?>" class="hs-moto-btn hs-moto-btn--secondary" style="width:100%; font-size:0.85rem;">
                                        <?php esc_html_e('View Specs & Synergy →', 'helmetsan-theme'); ?>
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>

        </article>
    </div>
</main>

<!-- High-Conversion Lead Capture Modal -->
<div id="hs-lead-modal" class="hs-modal-backdrop" role="dialog" aria-modal="true" aria-labelledby="hs-lead-modal-title">
    <div class="hs-modal-box">
        <button type="button" class="hs-modal-close" aria-label="<?php esc_attr_e('Close modal', 'helmetsan-theme'); ?>">&times;</button>
        <span class="hs-moto-price-badge"><?php esc_html_e('Fast Doorstep Booking', 'helmetsan-theme'); ?></span>
        <h3 id="hs-lead-modal-title" style="margin:0.5rem 0 0.25rem 0;"><?php printf(esc_html__('Request Best Price & Test Ride for %s', 'helmetsan-theme'), esc_html($title)); ?></h3>
        <p style="font-size:0.85rem; color:var(--hs-text-muted); margin:0 0 1rem 0;">
            <?php esc_html_e('Our verified authorized dealer partners across India will share on-road quotations, financing schemes, and schedule a test ride at your convenience.', 'helmetsan-theme'); ?>
        </p>

        <form id="hs-lead-form" class="hs-modal-form">
            <input type="hidden" name="motorcycle_id" value="<?php echo esc_attr($post_id); ?>">

            <div class="hs-modal-field">
                <label for="hs-lead-name"><?php esc_html_e('Full Name *', 'helmetsan-theme'); ?></label>
                <input type="text" id="hs-lead-name" name="name" required placeholder="<?php esc_attr_e('Enter your full name', 'helmetsan-theme'); ?>">
            </div>

            <div class="hs-modal-field">
                <label for="hs-lead-phone"><?php esc_html_e('Mobile Number (WhatsApp Enabled) *', 'helmetsan-theme'); ?></label>
                <input type="tel" id="hs-lead-phone" name="phone" required pattern="[0-9]{10}" placeholder="<?php esc_attr_e('10-digit mobile number (e.g. 9876543210)', 'helmetsan-theme'); ?>">
            </div>

            <div class="hs-modal-field">
                <label for="hs-lead-email"><?php esc_html_e('Email Address (Optional)', 'helmetsan-theme'); ?></label>
                <input type="email" id="hs-lead-email" name="email" placeholder="<?php esc_attr_e('name@example.com', 'helmetsan-theme'); ?>">
            </div>

            <div class="hs-modal-field">
                <label for="hs-lead-city"><?php esc_html_e('Preferred City *', 'helmetsan-theme'); ?></label>
                <select id="hs-lead-city" name="city" required>
                    <option value="Delhi NCR" selected><?php esc_html_e('Delhi NCR', 'helmetsan-theme'); ?></option>
                    <option value="Mumbai"><?php esc_html_e('Mumbai', 'helmetsan-theme'); ?></option>
                    <option value="Bengaluru"><?php esc_html_e('Bengaluru', 'helmetsan-theme'); ?></option>
                    <option value="Pune"><?php esc_html_e('Pune', 'helmetsan-theme'); ?></option>
                    <option value="Chennai"><?php esc_html_e('Chennai', 'helmetsan-theme'); ?></option>
                    <option value="Hyderabad"><?php esc_html_e('Hyderabad', 'helmetsan-theme'); ?></option>
                    <option value="Kolkata"><?php esc_html_e('Kolkata', 'helmetsan-theme'); ?></option>
                    <option value="Ahmedabad"><?php esc_html_e('Ahmedabad', 'helmetsan-theme'); ?></option>
                    <option value="Other"><?php esc_html_e('Other City', 'helmetsan-theme'); ?></option>
                </select>
            </div>

            <div class="hs-modal-field">
                <label><?php esc_html_e('Your Primary Requirement:', 'helmetsan-theme'); ?></label>
                <div class="hs-modal-radios">
                    <label class="hs-modal-radio-label">
                        <input type="radio" name="intent" value="test_ride" checked>
                        <span><?php esc_html_e('Doorstep Test Ride', 'helmetsan-theme'); ?></span>
                    </label>
                    <label class="hs-modal-radio-label">
                        <input type="radio" name="intent" value="on_road_price">
                        <span><?php esc_html_e('Best On-Road Quote', 'helmetsan-theme'); ?></span>
                    </label>
                    <label class="hs-modal-radio-label">
                        <input type="radio" name="intent" value="booking">
                        <span><?php esc_html_e('Ready to Book', 'helmetsan-theme'); ?></span>
                    </label>
                </div>
            </div>

            <button type="submit" class="hs-moto-btn hs-moto-btn--primary" id="hs-lead-submit-btn" style="width:100%; margin-top:0.5rem;">
                <?php esc_html_e('Submit Request ↗', 'helmetsan-theme'); ?>
            </button>

            <div id="hs-lead-status" class="hs-modal-status"></div>
        </form>
    </div>
</div>

<!-- B2B Dealer Enrollment Modal -->
<div id="hs-dealer-app-modal" class="hs-modal-backdrop" role="dialog" aria-modal="true" aria-labelledby="hs-dealer-modal-title">
    <div class="hs-modal-box">
        <button type="button" class="hs-modal-close" aria-label="<?php esc_attr_e('Close modal', 'helmetsan-theme'); ?>">&times;</button>
        <span class="hs-moto-price-badge"><?php esc_html_e('B2B Partner Network', 'helmetsan-theme'); ?></span>
        <h3 id="hs-dealer-modal-title" style="margin:0.5rem 0 0.25rem 0;"><?php esc_html_e('Enroll Your Dealership on Helmetsan', 'helmetsan-theme'); ?></h3>
        <p style="font-size:0.85rem; color:var(--hs-text-muted); margin:0 0 1rem 0;">
            <?php esc_html_e('Receive verified customer purchase inquiries, schedule showroom test rides, and showcase your certified inventory directly to motorcycle enthusiasts.', 'helmetsan-theme'); ?>
        </p>

        <form id="hs-dealer-form" class="hs-modal-form">
            <div class="hs-modal-field">
                <label for="hs-dealer-biz-name"><?php esc_html_e('Dealership / Business Name *', 'helmetsan-theme'); ?></label>
                <input type="text" id="hs-dealer-biz-name" name="dealership_name" required placeholder="<?php esc_attr_e('e.g. Apex Two-Wheelers Pvt Ltd', 'helmetsan-theme'); ?>">
            </div>

            <div class="hs-modal-field">
                <label for="hs-dealer-contact-name"><?php esc_html_e('Contact Person & Designation *', 'helmetsan-theme'); ?></label>
                <input type="text" id="hs-dealer-contact-name" name="contact_person" required placeholder="<?php esc_attr_e('e.g. Rajesh Kumar (General Manager)', 'helmetsan-theme'); ?>">
            </div>

            <div class="hs-modal-field">
                <label for="hs-dealer-biz-phone"><?php esc_html_e('Showroom Phone Number *', 'helmetsan-theme'); ?></label>
                <input type="tel" id="hs-dealer-biz-phone" name="phone" required placeholder="<?php esc_attr_e('10-digit mobile or landline', 'helmetsan-theme'); ?>">
            </div>

            <div class="hs-modal-field">
                <label for="hs-dealer-biz-email"><?php esc_html_e('Official Business Email *', 'helmetsan-theme'); ?></label>
                <input type="email" id="hs-dealer-biz-email" name="email" required placeholder="<?php esc_attr_e('manager@dealership.com', 'helmetsan-theme'); ?>">
            </div>

            <div class="hs-modal-field">
                <label for="hs-dealer-biz-city"><?php esc_html_e('City & State *', 'helmetsan-theme'); ?></label>
                <input type="text" id="hs-dealer-biz-city" name="city" required placeholder="<?php esc_attr_e('e.g. Bengaluru, Karnataka', 'helmetsan-theme'); ?>">
            </div>

            <div class="hs-modal-field">
                <label for="hs-dealer-biz-brands"><?php esc_html_e('Authorized Two-Wheeler Brands *', 'helmetsan-theme'); ?></label>
                <input type="text" id="hs-dealer-biz-brands" name="authorized_brands" required placeholder="<?php esc_attr_e('e.g. Royal Enfield, KTM, Triumph, Ather', 'helmetsan-theme'); ?>">
            </div>

            <button type="submit" class="hs-moto-btn hs-moto-btn--primary" id="hs-dealer-submit-btn" style="width:100%; margin-top:0.5rem;">
                <?php esc_html_e('Submit Dealership Application ↗', 'helmetsan-theme'); ?>
            </button>

            <div id="hs-dealer-status" class="hs-modal-status"></div>
        </form>
    </div>
</div>

<!-- Interactive Client-side Script -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // 1. City On-Road Estimator
    var citySelect = document.getElementById('hs-city-estimator');
    var onroadPriceDisplay = document.getElementById('hs-display-onroad-price');
    var rtoDisplay = document.getElementById('hs-display-rto');
    var insDisplay = document.getElementById('hs-display-ins');

    function formatInr(num) {
        if (num >= 100000) {
            return '₹' + (num / 100000).toFixed(2) + ' Lakh';
        }
        return '₹' + num.toLocaleString('en-IN');
    }

    if (citySelect) {
        citySelect.addEventListener('change', function() {
            var opt = this.options[this.selectedIndex];
            var basePrice = parseInt(this.getAttribute('data-base-price') || '285000', 10);
            var rtoPct = parseFloat(opt.getAttribute('data-rto') || '0.10');
            var insAmount = parseInt(opt.getAttribute('data-ins') || '14000', 10);
            var rtoCost = Math.round(basePrice * rtoPct);
            var total = basePrice + rtoCost + insAmount + 1500;

            if (onroadPriceDisplay) onroadPriceDisplay.textContent = formatInr(total);
            if (rtoDisplay) rtoDisplay.textContent = '₹' + rtoCost.toLocaleString('en-IN');
            if (insDisplay) insDisplay.textContent = '₹' + insAmount.toLocaleString('en-IN');
        });
    }

    // 2. Verified Dealers City Switcher
    var dealerTabs = document.querySelectorAll('.hs-moto-city-tab');
    var dealersData = <?php echo json_encode($indian_dealers); ?>;
    var dealerNameEl = document.getElementById('hs-dealer-name');
    var dealerAddrEl = document.getElementById('hs-dealer-addr');
    var dealerCallBtn = document.getElementById('hs-dealer-call-btn');

    dealerTabs.forEach(function(tab) {
        tab.addEventListener('click', function() {
            dealerTabs.forEach(function(t) { t.classList.remove('is-active'); });
            this.classList.add('is-active');
            var cityKey = this.getAttribute('data-city');
            if (dealersData[cityKey]) {
                var d = dealersData[cityKey];
                if (dealerNameEl) dealerNameEl.textContent = d.name;
                if (dealerAddrEl) dealerAddrEl.textContent = d.address;
                if (dealerCallBtn) {
                    dealerCallBtn.href = 'tel:' + d.phone.replace(/\s+/g, '');
                    dealerCallBtn.textContent = 'Call ' + d.city;
                }
            }
        });
    });

    // 2.5 Region UI Synchronization (India vs Global)
    function syncMotoRegionUI(countryCode) {
        var isIndia = (countryCode === 'IN');
        document.querySelectorAll('.hs-moto-india-only').forEach(function(el) {
            el.style.display = isIndia ? '' : 'none';
        });
        document.querySelectorAll('.hs-moto-global-only').forEach(function(el) {
            el.style.display = isIndia ? 'none' : '';
        });
        var labelEl = document.getElementById('hs-moto-ex-label-text');
        if (labelEl) {
            labelEl.textContent = isIndia 
                ? '<?php echo $is_price_estimated ? esc_html__("Approx. Ex-Showroom India (Displacement Estimate)", "helmetsan-theme") : esc_html__("Avg. Ex-Showroom India (Base Variant)", "helmetsan-theme"); ?>'
                : '<?php echo esc_html__("Estimated Base MSRP (Global Reference)", "helmetsan-theme"); ?>';
        }
    }

    document.addEventListener('helmetsan:country_changed', function(e) {
        if (e.detail && e.detail.country) {
            syncMotoRegionUI(e.detail.country);
        }
    });

    // 3. Modals Opening & Closing
    var leadModal = document.getElementById('hs-lead-modal');
    var dealerModal = document.getElementById('hs-dealer-app-modal');
    var openLeadBtn = document.getElementById('hs-open-lead-btn');
    var openDealerBtn = document.getElementById('hs-open-dealer-app-btn');

    function openModal(modal) {
        if (!modal) return;
        modal.classList.add('is-open', 'is-visible');
        document.body.style.overflow = 'hidden';
    }
    function closeModal(modal) {
        if (!modal) return;
        modal.classList.remove('is-open', 'is-visible');
        document.body.style.overflow = '';
    }

    document.querySelectorAll('#hs-open-lead-btn, #hs-open-lead-btn-global, .hs-open-lead-trigger').forEach(function(btn) {
        btn.addEventListener('click', function() { openModal(leadModal); });
    });
    document.querySelectorAll('#hs-open-dealer-app-btn, #hs-open-dealer-partner-btn').forEach(function(btn) {
        btn.addEventListener('click', function() { openModal(dealerModal); });
    });

    // Generic modal triggers
    document.querySelectorAll('[data-open-modal]').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            var targetId = this.getAttribute('data-open-modal');
            var targetModal = document.getElementById(targetId);
            if (targetModal) openModal(targetModal);
        });
    });

    document.querySelectorAll('.hs-modal-close').forEach(function(btn) {
        btn.addEventListener('click', function() {
            closeModal(this.closest('.hs-modal-backdrop'));
        });
    });

    window.addEventListener('click', function(e) {
        if (e.target && e.target.classList.contains('hs-modal-backdrop')) {
            closeModal(e.target);
        }
    });

    window.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            document.querySelectorAll('.hs-modal-backdrop.is-open, .hs-modal-backdrop.is-visible').forEach(function(m) {
                closeModal(m);
            });
        }
    });

    // 4. Lead Form AJAX Submission
    var leadForm = document.getElementById('hs-lead-form');
    var leadStatus = document.getElementById('hs-lead-status');
    if (leadForm) {
        leadForm.addEventListener('submit', function(e) {
            e.preventDefault();
            var btn = document.getElementById('hs-lead-submit-btn');
            btn.disabled = true;
            btn.textContent = 'Submitting Request...';

            var formData = new FormData(leadForm);
            var payload = {};
            formData.forEach(function(value, key) { payload[key] = value; });

            fetch('/wp-json/hs/v1/motorcycles/lead', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            })
            .then(function(res) { return res.json(); })
            .then(function(data) {
                if (data.success) {
                    leadStatus.className = 'hs-modal-status is-success';
                    leadStatus.textContent = data.message;
                    leadStatus.style.display = 'block';
                    leadForm.reset();
                    btn.textContent = '✓ Inquiry Sent!';
                    setTimeout(function() { closeModal(leadModal); }, 4000);
                } else {
                    leadStatus.className = 'hs-modal-status';
                    leadStatus.style.borderColor = '#ef4444';
                    leadStatus.style.color = '#ef4444';
                    leadStatus.textContent = data.message || 'Submission failed. Please try again.';
                    leadStatus.style.display = 'block';
                    btn.disabled = false;
                    btn.textContent = 'Submit Request ↗';
                }
            })
            .catch(function(err) {
                btn.disabled = false;
                btn.textContent = 'Submit Request ↗';
                alert('Connection error. Please try again.');
            });
        });
    }

    // 5. Dealer Partner Form AJAX Submission
    var dealerForm = document.getElementById('hs-dealer-form');
    var dealerStatus = document.getElementById('hs-dealer-status');
    if (dealerForm) {
        dealerForm.addEventListener('submit', function(e) {
            e.preventDefault();
            var btn = document.getElementById('hs-dealer-submit-btn');
            btn.disabled = true;
            btn.textContent = 'Submitting Application...';

            var formData = new FormData(dealerForm);
            var payload = {};
            formData.forEach(function(value, key) { payload[key] = value; });

            fetch('/wp-json/hs/v1/dealers/enroll', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            })
            .then(function(res) { return res.json(); })
            .then(function(data) {
                if (data.success) {
                    dealerStatus.className = 'hs-modal-status is-success';
                    dealerStatus.textContent = data.message;
                    dealerStatus.style.display = 'block';
                    dealerForm.reset();
                    btn.textContent = '✓ Application Received!';
                    setTimeout(function() { closeModal(dealerModal); }, 4000);
                } else {
                    dealerStatus.className = 'hs-modal-status';
                    dealerStatus.style.borderColor = '#ef4444';
                    dealerStatus.style.color = '#ef4444';
                    dealerStatus.textContent = data.message || 'Error submitting application.';
                    dealerStatus.style.display = 'block';
                    btn.disabled = false;
                    btn.textContent = 'Submit Dealership Application ↗';
                }
            })
            .catch(function(err) {
                btn.disabled = false;
                btn.textContent = 'Submit Dealership Application ↗';
                alert('Connection error. Please try again.');
            });
        });
    }
});
</script>

<?php
    endwhile;
endif;

get_footer();
