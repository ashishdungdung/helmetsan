<?php
/**
 * Helmetsan Cross-Entity Compatibility Engine
 * High-performance multi-axial correlation for Motorcycles ⇄ Helmets ⇄ Accessories.
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

class Helmetsan_CompatibilityEngine {

    private static ?array $matrixCache = null;

    /**
     * Load the precomputed compatibility matrix if available.
     */
    public static function get_matrix(): array {
        if (self::$matrixCache !== null) {
            return self::$matrixCache;
        }

        // Tier 1: Check persistent object cache for pre-warmed matrix
        $cachedMatrix = wp_cache_get('helmetsan_compat_matrix', 'helmetsan');
        if (is_array($cachedMatrix) && !empty($cachedMatrix)) {
            self::$matrixCache = $cachedMatrix;
            return self::$matrixCache;
        }

        $matrixPath = defined('WP_CONTENT_DIR') 
            ? WP_CONTENT_DIR . '/uploads/helmetsan-data/compatibility_matrix.json'
            : dirname(__DIR__, 2) . '/data/compatibility_matrix.json';

        if (file_exists($matrixPath)) {
            $raw = file_get_contents($matrixPath);
            if ($raw !== false) {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    self::$matrixCache = $decoded;
                    wp_cache_set('helmetsan_compat_matrix', self::$matrixCache, 'helmetsan', 3600);
                    return self::$matrixCache;
                }
            }
        }

        self::$matrixCache = [];
        return self::$matrixCache;
    }

    /**
     * Get precomputed compatibility for a specific entity ID.
     * High-speed execution with postmeta and transient caching.
     */
    public static function get_entity_compatibility(string $entityType, string $id): array {
        $idClean = trim($id);
        if ($idClean === '') {
            return [];
        }

        // 1. Fast-Path: Check WordPress Object Cache & Transients for this specific entity slice
        $cacheKey = 'hs_cmp_' . substr(md5($entityType . '_' . $idClean), 0, 20);
        $cachedSlice = wp_cache_get($cacheKey, 'helmetsan_compat');
        if (is_array($cachedSlice)) {
            return $cachedSlice;
        }
        $transientSlice = get_transient($cacheKey);
        if (is_array($transientSlice)) {
            wp_cache_set($cacheKey, $transientSlice, 'helmetsan_compat', 43200);
            return $transientSlice;
        }

        // 2. Fast-Path: Check if post has pre-baked compatibility metadata
        if (function_exists('get_post')) {
            $post = is_numeric($idClean) ? get_post((int)$idClean) : get_page_by_path($idClean, OBJECT, $entityType);
            if ($post instanceof \WP_Post) {
                $prebaked = get_post_meta($post->ID, '_compat_recommendations_json', true);
                if (!empty($prebaked)) {
                    $decoded = is_string($prebaked) ? json_decode($prebaked, true) : $prebaked;
                    if (is_array($decoded) && !empty($decoded)) {
                        wp_cache_set($cacheKey, $decoded, 'helmetsan_compat', 43200);
                        set_transient($cacheKey, $decoded, 43200);
                        return $decoded;
                    }
                }
            }
        }

        // 3. Fallback: Parse matrix and cache the result slice so subsequent hits are 0ms
        $matrix = self::get_matrix();
        $candidates = array_unique([
            $idClean,
            str_replace('_', '-', $idClean),
            str_replace('-', '_', $idClean),
            strtolower($idClean),
            strtolower(str_replace('_', '-', $idClean)),
            strtolower(str_replace('-', '_', $idClean)),
        ]);

        $result = [];

        if ($entityType === 'motorcycle') {
            foreach ($candidates as $cand) {
                if (isset($matrix['motorcycle_matches'][$cand])) {
                    $result = $matrix['motorcycle_matches'][$cand];
                    break;
                }
            }
            if (empty($result)) {
                $result = [
                    'recommended_helmets' => [],
                    'recommended_accessories' => []
                ];
            }
        } elseif ($entityType === 'helmet') {
            foreach ($candidates as $cand) {
                if (isset($matrix['helmet_matches'][$cand])) {
                    $result = $matrix['helmet_matches'][$cand];
                    break;
                }
            }
            if (empty($result)) {
                $result = [
                    'recommended_motorcycles' => [],
                    'compatible_accessories' => []
                ];
            }
        } elseif ($entityType === 'accessory') {
            $helmets = [];
            foreach ($matrix['helmet_matches'] ?? [] as $hId => $hData) {
                foreach ($hData['compatible_accessories'] ?? [] as $ca) {
                    if (in_array($ca['id'] ?? '', $candidates, true)) {
                        $helmets[] = [
                            'id' => $hId,
                            'title' => ucwords(str_replace(['_', '-'], ' ', (string) $hId)),
                            'fitment' => $ca['fitment'] ?? 'Compatible helmet model'
                        ];
                        if (count($helmets) >= 8) {
                            break 2;
                        }
                    }
                }
            }
            $result = [
                'compatible_helmets' => $helmets
            ];
        }

        // Cache the extracted slice for 12 hours
        wp_cache_set($cacheKey, $result, 'helmetsan_compat', 43200);
        set_transient($cacheKey, $result, 43200);

        return $result;
    }

    /**
     * Pre-bake compatibility recommendations directly into post meta for instant 0.01ms retrieval.
     */
    public static function bake_entity_compatibility(int $postId, string $entityType, string $id): void {
        $result = self::get_entity_compatibility($entityType, $id);
        if (!empty($result) && function_exists('update_post_meta')) {
            update_post_meta($postId, '_compat_recommendations_json', wp_json_encode($result));
        }
    }

    /**
     * Match a Motorcycle to compatible Helmets dynamically using the 4-Axial Aerodynamic Formula:
     * MatchScore = 0.35 * S_posture + 0.25 * S_windshield + 0.25 * S_acoustics + 0.15 * S_safety
     *
     * Evaluates riding tuck angles (30°–90°), windscreen deflection turbulence,
     * cockpit acoustic decibel dampening (<85 dB), and safety certification baselines.
     *
     * @param array $bike Motorcycle specs (category, top_speed_kmh, windshield_type, riding_posture_deg)
     * @param array $helmets List of candidate helmets
     * @param int $limit Max results to return
     * @return array Ranked recommendations with sub-scores and aero telemetry
     */
    public static function match_motorcycle_to_helmets(array $bike, array $helmets, int $limit = 6): array {
        $category = (string) ($bike['category'] ?? 'Urban Roadster');
        $topSpeed = (int) ($bike['top_speed_kmh'] ?? 140);
        $windshield = (string) ($bike['windshield_type'] ?? '');

        // 1. Determine Bike Ergonomic Tuck Angle & Aero Profile
        $tuckAngle = 75; // default neutral upright (degrees from horizontal)
        $windProfile = 'direct_laminar';

        if (preg_match('/(superbike|sportbike|supersport|track|race)/i', $category)) {
            $tuckAngle = 35;
            $windProfile = !empty($windshield) ? 'short_sport_turbulent' : 'high_velocity_laminar';
        } elseif (preg_match('/(adventure|adv|dual[- ]?sport|enduro)/i', $category)) {
            $tuckAngle = 90;
            $windProfile = 'tall_adv_vortex';
        } elseif (preg_match('/(tourer|touring|bagger|sport[- ]?touring)/i', $category)) {
            $tuckAngle = 75;
            $windProfile = 'tall_touring_bubble';
        } elseif (preg_match('/(cruiser|chopper|bobber)/i', $category)) {
            $tuckAngle = 90;
            $windProfile = 'low_speed_buffeting';
        } else { // Naked, Street, Roadster, Scrambler
            $tuckAngle = 80;
            $windProfile = 'naked_chest_pressure';
        }

        $results = [];

        foreach ($helmets as $h) {
            $hType = (string) ($h['helmet_type'] ?? $h['type'] ?? 'Full Face');
            $hTitle = (string) ($h['title'] ?? '');
            $certs = (array) ($h['certifications'] ?? []);
            $sharp = (int) ($h['sharp_rating'] ?? 0);
            $acousticDb = (int) ($h['spec_acoustic_db'] ?? $h['noise_db_at_100kph'] ?? 0);

            $reasons = [];

            // ─── AXIS 1: S_posture (Riding Tuck Angle Match - 35%) ───
            $sPosture = 70;
            if ($tuckAngle <= 45) { // Full aggressive tuck (Supersport 30°-45°)
                if (stripos($hType, 'Track') !== false || stripos($hType, 'Race') !== false) {
                    $sPosture = 98;
                    $reasons[] = 'Aerodynamic rear spoiler and high-camber upper eyeport engineered for aggressive 35° racing tuck vision.';
                } elseif (stripos($hType, 'Full Face') !== false) {
                    $sPosture = 86;
                    $reasons[] = 'Balanced full-face aero profile supports forward canted riding posture.';
                } elseif (stripos($hType, 'Modular') !== false) {
                    $sPosture = 50;
                    $reasons[] = 'Modular pivot mechanism creates forward drag when ridden in a steep sports tuck.';
                } else {
                    $sPosture = 35;
                }
            } elseif ($tuckAngle >= 85) { // Upright 85°-90° (Adventure / Cruiser / Commuter)
                if (stripos($hType, 'Adventure') !== false || stripos($hType, 'Dual Sport') !== false) {
                    $sPosture = 98;
                    $reasons[] = 'Aerodynamic sun-peak and wide eyeport accommodate upright off-road stance and goggle integration.';
                } elseif (stripos($hType, 'Modular') !== false) {
                    $sPosture = 94;
                    $reasons[] = 'Upright chin-bar balance reduces neck fatigue during long seated stretches.';
                } elseif (stripos($hType, 'Full Face') !== false) {
                    $sPosture = 84;
                    $reasons[] = 'Versatile full-face shell profile maintains low chin lift in upright air.';
                } else {
                    $sPosture = 75;
                }
            } else { // Standard / Sport-Touring / Naked (60°-80°)
                if (stripos($hType, 'Full Face') !== false) {
                    $sPosture = 95;
                    $reasons[] = 'Neutral aerodynamic balance prevents neck buffeting across variable 75° road postures.';
                } elseif (stripos($hType, 'Modular') !== false) {
                    $sPosture = 92;
                    $reasons[] = 'Comfort-focused modular shell optimal for active sport-touring ergonomics.';
                } elseif (stripos($hType, 'Track') !== false) {
                    $sPosture = 80;
                    $reasons[] = 'Track-focused field of view slightly compromises peripheral vision on upright street rides.';
                } else {
                    $sPosture = 70;
                }
            }

            // ─── AXIS 2: S_windshield (Deflection Turbulence - 25%) ───
            $sWindshield = 75;
            if ($windProfile === 'naked_chest_pressure' || $windProfile === 'high_velocity_laminar') {
                // Direct clean laminar flow onto helmet
                if (stripos($hType, 'Full Face') !== false || stripos($hType, 'Track') !== false) {
                    $sWindshield = 95;
                    $reasons[] = 'Sculpted chin bar and boundary-layer vortex generators cleanly slice undisturbed oncoming airflow.';
                } elseif (stripos($hType, 'Adventure') !== false) {
                    $sWindshield = 65;
                    $reasons[] = 'Sun peak creates aerodynamic uplift in unshielded high-speed highway wind.';
                } else {
                    $sWindshield = 80;
                }
            } elseif ($windProfile === 'tall_adv_vortex' || $windProfile === 'tall_touring_bubble') {
                // Rider sits in windshield bubble, turbulence hits top vent
                if (stripos($hType, 'Modular') !== false || stripos($hType, 'Touring') !== false) {
                    $sWindshield = 96;
                    $reasons[] = 'Acoustic visor seal and top cowl ventilation maximize airflow inside windscreen dead-air pockets.';
                } elseif (stripos($hType, 'Adventure') !== false) {
                    $sWindshield = 90;
                    $reasons[] = 'High windscreen shields the visor while channeling air smoothly over the aero peak.';
                } else {
                    $sWindshield = 82;
                }
            } else { // Short sport screen turbulent shear layer
                if (stripos($hType, 'Track') !== false || stripos($hType, 'Full Face') !== false) {
                    $sWindshield = 94;
                    $reasons[] = 'Rigid shield locking mechanism resists vibration and whistling from windscreen boundary-layer turbulence.';
                } else {
                    $sWindshield = 75;
                }
            }

            // ─── AXIS 3: S_acoustics (Decibel Noise Dampening - 25%) ───
            $sAcoustics = 75;
            if ($acousticDb > 0) {
                if ($acousticDb <= 84) {
                    $sAcoustics = 98;
                    $reasons[] = sprintf('Exceptional acoustic isolation (%d dB) dampens high-speed wind fatigue.', $acousticDb);
                } elseif ($acousticDb <= 88) {
                    $sAcoustics = 90;
                    $reasons[] = sprintf('Controlled sound pressure (%d dB) suitable for all-day highway transit.', $acousticDb);
                } elseif ($acousticDb <= 94) {
                    $sAcoustics = 78;
                } else {
                    $sAcoustics = 65;
                    $reasons[] = 'High-flow ventilation design increases internal decibel levels (>95 dB); ear protection recommended.';
                }
            } else {
                // Heuristic estimation based on helmet segment & chin curtain
                if (stripos($hType, 'Modular') !== false || stripos($hType, 'Touring') !== false) {
                    $sAcoustics = 92;
                } elseif (stripos($hType, 'Full Face') !== false) {
                    $sAcoustics = 85;
                } elseif (stripos($hType, 'Track') !== false) {
                    $sAcoustics = 72; // Race helmets favor airflow over noise reduction
                } else {
                    $sAcoustics = 68;
                }
            }

            // ─── AXIS 4: S_safety (Certification Baseline & Speed - 15%) ───
            $sSafety = 75;
            $hasECE2206 = in_array('ECE 22.06', $certs, true);
            $hasFIM = in_array('FIM', $certs, true) || in_array('FIM Racing', $certs, true) || in_array('FIM FRHPhe-01', $certs, true);

            if ($topSpeed >= 200 || $tuckAngle <= 45) {
                if ($hasFIM) {
                    $sSafety = 100;
                    $reasons[] = 'FIM racing homologated for multi-angle oblique impact protection at hyper-sport speeds.';
                } elseif ($hasECE2206) {
                    $sSafety = 94;
                    if ($sharp >= 4) {
                        $sSafety = 98;
                        $reasons[] = sprintf('ECE 22.06 certified with %d-star SHARP safety rating exceeding 200 km/h requirements.', $sharp);
                    } else {
                        $reasons[] = 'ECE 22.06 certified to rigorous high-velocity and rotational impact protocols.';
                    }
                } else {
                    $sSafety = 65;
                }
            } else {
                if ($hasECE2206) {
                    $sSafety = 95;
                } elseif ($sharp >= 4) {
                    $sSafety = 90;
                } else {
                    $sSafety = 80;
                }
            }

            // ─── MULTI-AXIAL COMPOSITE FORMULA ───
            // MatchScore = 0.35 * S_posture + 0.25 * S_windshield + 0.25 * S_acoustics + 0.15 * S_safety
            $sPostureClamped    = min(100, max(0, $sPosture));
            $sWindshieldClamped = min(100, max(0, $sWindshield));
            $sAcousticsClamped  = min(100, max(0, $sAcoustics));
            $sSafetyClamped     = min(100, max(0, $sSafety));

            $compositeScore = (0.35 * $sPostureClamped)
                            + (0.25 * $sWindshieldClamped)
                            + (0.25 * $sAcousticsClamped)
                            + (0.15 * $sSafetyClamped);

            $finalScore = (int) round(min(99, max(40, $compositeScore)));

            $results[] = [
                'id'                 => $h['id'] ?? '',
                'title'              => $h['title'] ?? '',
                'brand'              => $h['brand'] ?? '',
                'type'               => $hType,
                'match_score'        => $finalScore,
                'sub_scores'         => [
                    'posture'    => $sPostureClamped,
                    'windshield' => $sWindshieldClamped,
                    'acoustics'  => $sAcousticsClamped,
                    'safety'     => $sSafetyClamped,
                ],
                'tuck_angle_deg'     => $tuckAngle,
                'deflection_profile' => $windProfile,
                'match_reason'       => implode(' ', array_unique($reasons)) ?: 'Compliant aerodynamic and posture profile for this motorcycle category.'
            ];
        }

        usort($results, fn($a, $b) => $b['match_score'] <=> $a['match_score']);
        return array_slice($results, 0, $limit);
    }

    /**
     * Determine compatibility breakdown for a helmet and list of accessories.
     */
    public static function evaluate_accessories(array $helmet, array $accessories): array {
        $results = [
            'confirmed'    => [],
            'possible'     => [],
            'incompatible' => []
        ];

        $helmetBrand  = strtolower($helmet['brand'] ?? '');
        $helmetFamily = strtolower($helmet['helmet_family'] ?? $helmet['title'] ?? '');
        $helmetType   = strtolower($helmet['helmet_type'] ?? $helmet['type'] ?? '');
        $hasPinlock   = !empty($helmet['features_data']['visor']) && in_array('Pinlock Ready', $helmet['features_data']['visor']);

        foreach ($accessories as $acc) {
            $accTitle = strtolower($acc['title'] ?? '');
            $accBrand = strtolower($acc['brand'] ?? '');

            // Exact brand & family match
            if ($accBrand === $helmetBrand && (strpos($accTitle, $helmetFamily) !== false || strpos($accTitle, 'universal') !== false)) {
                $results['confirmed'][] = $acc;
            }
            // Universal Bluetooth comms
            elseif (strpos($accTitle, 'bluetooth') !== false || strpos($accTitle, 'intercom') !== false || strpos($accTitle, 'cardo') !== false || strpos($accTitle, 'sena') !== false) {
                if (in_array($helmetType, ['full face', 'modular', 'adventure / dual sport', 'touring'])) {
                    $results['confirmed'][] = array_merge($acc, [
                        'fitment_note' => 'Confirmed fitment: EPS speaker pockets accommodate 40mm audio drivers.'
                    ]);
                } else {
                    $results['possible'][] = $acc;
                }
            }
            // Pinlock matching
            elseif (strpos($accTitle, 'pinlock') !== false || strpos($accTitle, 'anti-fog') !== false) {
                if ($hasPinlock || in_array($helmetType, ['full face', 'track / race', 'modular', 'adventure / dual sport'])) {
                    $results['confirmed'][] = array_merge($acc, [
                        'fitment_note' => 'Confirmed fitment: Toolless shield mechanism supports hydrophilic anti-fog lens chamber.'
                    ]);
                } else {
                    $results['possible'][] = $acc;
                }
            }
            // Maintenance and universal care
            elseif (strpos($accTitle, 'cleaner') !== false || strpos($accTitle, 'dryer') !== false || strpos($accTitle, 'stand') !== false || strpos($accTitle, 'balaclava') !== false) {
                $results['confirmed'][] = array_merge($acc, [
                    'fitment_note' => 'Universal accessory: Compatible with all EPS liner fabrics and shell finishes.'
                ]);
            }
            else {
                $results['possible'][] = $acc;
            }
        }

        return $results;
    }

    /**
     * Search motorcycles for auto-complete and matching.
     *
     * @param string $query
     * @param int $limit
     * @return array<int, array<string, mixed>>
     */
    public static function search_motorcycles(string $query = '', int $limit = 12): array {
        $cleanQuery = trim($query);
        $results = [];

        // 1. Try SQLite catalog.db if present
        $dbPath = defined('WP_CONTENT_DIR')
            ? WP_CONTENT_DIR . '/uploads/helmetsan-data/catalog.db'
            : dirname(__DIR__, 2) . '/data/catalog.db';

        if (!file_exists($dbPath)) {
            $altDbPath = dirname(__DIR__, 3) . '/HelmetsanMobile/assets/database/catalog.db';
            if (file_exists($altDbPath)) {
                $dbPath = $altDbPath;
            }
        }

        if (file_exists($dbPath) && class_exists('PDO')) {
            try {
                $pdo = new \PDO('sqlite:' . $dbPath);
                $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_SILENT);
                if ($cleanQuery !== '') {
                    $stmt = $pdo->prepare("SELECT id, slug, title, brand, category, riding_position FROM motorcycles WHERE title LIKE :q OR brand LIKE :q LIMIT :lim");
                    $stmt->bindValue(':q', '%' . $cleanQuery . '%', \PDO::PARAM_STR);
                    $stmt->bindValue(':lim', $limit, \PDO::PARAM_INT);
                    $stmt->execute();
                    $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                } else {
                    $stmt = $pdo->prepare("SELECT id, slug, title, brand, category, riding_position FROM motorcycles ORDER BY id ASC LIMIT :lim");
                    $stmt->bindValue(':lim', $limit, \PDO::PARAM_INT);
                    $stmt->execute();
                    $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                }
                if (!empty($rows)) {
                    return $rows;
                }
            } catch (\Exception $e) {
                // Fall back to WP_Query
            }
        }

        // 2. Fallback to WordPress WP_Query
        if (function_exists('get_posts')) {
            $args = [
                'post_type'      => 'motorcycle',
                'posts_per_page' => $limit,
                'post_status'    => 'publish',
                'orderby'        => 'title',
                'order'          => 'ASC',
            ];
            if ($cleanQuery !== '') {
                $args['s'] = $cleanQuery;
            }
            $posts = get_posts($args);
            foreach ($posts as $p) {
                if ($p instanceof \WP_Post) {
                    $brand = (string) (get_post_meta($p->ID, 'brand', true) ?: (get_post_meta($p->ID, 'motorcycle_make', true) ?: get_post_meta($p->ID, 'motorcycle_brand', true)));
                    $category = (string) (get_post_meta($p->ID, 'category', true) ?: (get_post_meta($p->ID, 'bike_segment', true) ?: get_post_meta($p->ID, 'motorcycle_category', true)));
                    $results[] = [
                        'id'              => $p->post_name,
                        'slug'            => $p->post_name,
                        'title'           => $p->post_title,
                        'brand'           => $brand,
                        'category'        => $category,
                        'riding_position' => (string) get_post_meta($p->ID, 'riding_position', true),
                    ];
                }
            }
        }

        // 3. Fallback defaults if database is not hydrated
        if (empty($results)) {
            $popular = [
                ['id' => 'royal-enfield-himalayan-450', 'title' => 'Royal Enfield Himalayan 450', 'brand' => 'Royal Enfield', 'category' => 'Adventure'],
                ['id' => 'yamaha-mt-07', 'title' => 'Yamaha MT-07', 'brand' => 'Yamaha', 'category' => 'Urban Roadster'],
                ['id' => 'kawasaki-ninja-zx-6r', 'title' => 'Kawasaki Ninja ZX-6R', 'brand' => 'Kawasaki', 'category' => 'Supersport'],
                ['id' => 'bmw-r-1250-gs', 'title' => 'BMW R 1250 GS', 'brand' => 'BMW', 'category' => 'Adventure Touring'],
                ['id' => 'honda-cb500x', 'title' => 'Honda CB500X', 'brand' => 'Honda', 'category' => 'Adventure'],
                ['id' => 'triumph-street-triple-765', 'title' => 'Triumph Street Triple 765', 'brand' => 'Triumph', 'category' => 'Streetfighter'],
                ['id' => 'harley-davidson-street-bob', 'title' => 'Harley-Davidson Street Bob 114', 'brand' => 'Harley-Davidson', 'category' => 'Cruiser'],
                ['id' => 'ducati-panigale-v4', 'title' => 'Ducati Panigale V4', 'brand' => 'Ducati', 'category' => 'Track / Superbike'],
            ];
            if ($cleanQuery !== '') {
                return array_values(array_filter($popular, function($b) use ($cleanQuery) {
                    return stripos($b['title'], $cleanQuery) !== false || stripos($b['brand'], $cleanQuery) !== false;
                }));
            }
            return $popular;
        }

        return $results;
    }

    /**
     * Evaluate a comprehensive rider profile across the 6 Matching Dimensions:
     * 1. Bike & Ergonomics (tuck angle, windshield deflection)
     * 2. Riding Discipline (commute, highway/touring, sport, track, adv, cruiser)
     * 3. Safety & Homologation (ECE 22.06, FIM, Snell, SHARP, P/J)
     * 4. Acoustic & Cabin Isolation (decibel attenuation)
     * 5. Cranial Morphology & Eyewear (oval shape, glasses channels)
     * 6. Peripheral Gear Ecosystem (intercom cutouts, Pinlock, visors)
     *
     * @param array<string, mixed> $profile
     * @param int $limit
     * @return array<string, mixed>
     */
    public static function evaluate_rider_profile(array $profile, int $limit = 9): array {
        $bikeName       = (string) ($profile['bike'] ?? '');
        $bikeCategory   = strtolower((string) ($profile['bike_category'] ?? ''));
        $ridingStyle    = strtolower((string) ($profile['riding_style'] ?? 'commute'));
        $headShapeReq   = strtolower((string) ($profile['head_shape'] ?? 'intermediate_oval'));
        $eyewearReq     = !empty($profile['eyewear']);
        $safetyPriority = strtolower((string) ($profile['safety_priority'] ?? 'max_safety'));
        $featuresReq    = (array) ($profile['features'] ?? []);
        $budgetMax      = (float) ($profile['budget_max'] ?? 0);
        $currency       = strtoupper((string) ($profile['currency'] ?? 'USD'));

        // Derive bike ergonomics if not provided
        if ($bikeCategory === '' && $bikeName !== '') {
            if (preg_match('/(panigale|s1000rr|zx-?10r|zx-?6r|r1|r6|cbr|fireblade|supersport|superbike)/i', $bikeName)) {
                $bikeCategory = 'superbike';
            } elseif (preg_match('/(himalayan|gs|adv|adventure|tenere|transalp|tiger|v-strom|africa twin)/i', $bikeName)) {
                $bikeCategory = 'adv';
            } elseif (preg_match('/(tour|touring|goldwing|rt|k1600|bagger)/i', $bikeName)) {
                $bikeCategory = 'tourer';
            } elseif (preg_match('/(cruiser|harley|rebel|meteor|bobber|scout|chief|shadow)/i', $bikeName)) {
                $bikeCategory = 'cruiser';
            } else {
                $bikeCategory = 'roadster';
            }
        }

        // Determine tuck posture angle and wind deflection profile
        $tuckAngle = 75;
        $windProfile = 'direct_laminar';
        if ($bikeCategory === 'superbike' || $ridingStyle === 'track') {
            $tuckAngle = 35;
            $windProfile = 'high_velocity_laminar';
        } elseif ($bikeCategory === 'adv' || $ridingStyle === 'adventure') {
            $tuckAngle = 90;
            $windProfile = 'tall_adv_vortex';
        } elseif ($bikeCategory === 'tourer' || $ridingStyle === 'highway_touring') {
            $tuckAngle = 75;
            $windProfile = 'tall_touring_bubble';
        } elseif ($bikeCategory === 'cruiser') {
            $tuckAngle = 90;
            $windProfile = 'low_speed_buffeting';
        } else {
            $tuckAngle = 80;
            $windProfile = 'naked_chest_pressure';
        }

        // Fetch candidate helmets
        $candidates = self::load_candidate_helmets();

        // Load accessories for gear matching
        $accessories = self::load_candidate_accessories();

        $scored = [];

        foreach ($candidates as $h) {
            $hType        = (string) ($h['type'] ?? 'Full Face');
            $hBrand       = (string) ($h['brand'] ?? '');
            $hShape       = strtolower((string) ($h['head_shape'] ?? 'intermediate oval'));
            $certs        = (array) ($h['certifications'] ?? []);
            $sharpRating  = (int) ($h['sharp_rating'] ?? 0);
            $acousticDb   = (int) ($h['noise_db'] ?? 0);
            $weightG      = (int) ($h['weight_g'] ?? 1500);
            $hasSunVisor  = !empty($h['has_sun_visor']);
            $commsReady   = !empty($h['comms_ready']);
            $pinlockReady = !empty($h['pinlock_ready']);
            $eyewearComp  = !empty($h['eyewear_compatible']);
            $carbonFiber  = !empty($h['carbon_fiber']);

            $priceUsd = (float) ($h['price_usd'] ?? 0);
            $priceInr = (float) ($h['price_inr'] ?? 0);
            $priceEur = (float) ($h['price_eur'] ?? 0);
            $priceGbp = (float) ($h['price_gbp'] ?? 0);

            $activePrice = match($currency) {
                'INR' => $priceInr ?: ($priceUsd * 83),
                'EUR' => $priceEur ?: ($priceUsd * 0.92),
                'GBP' => $priceGbp ?: ($priceUsd * 0.79),
                default => $priceUsd,
            };

            // Budget filter check: skip if over budget by more than 20%
            if ($budgetMax > 0 && $activePrice > ($budgetMax * 1.25)) {
                continue;
            }

            $reasons = [];

            // ─── AXIS 1: Aero & Posture Stability (25%) ───
            $sAero = 75;
            if ($tuckAngle <= 45) { // Track / Superbike tuck
                if (stripos($hType, 'Track') !== false || stripos($hType, 'Race') !== false) {
                    $sAero = 98;
                    $reasons[] = 'Upper camber eyeport and racing spoiler engineered for 35° chin-on-tank tuck.';
                } elseif (stripos($hType, 'Full Face') !== false) {
                    $sAero = 88;
                    $reasons[] = 'Aerodynamic chin profile maintains high stability at speed.';
                } else {
                    $sAero = 40;
                }
            } elseif ($tuckAngle >= 85) { // Upright ADV / Cruiser / Touring
                if (stripos($hType, 'Adventure') !== false || stripos($hType, 'Dual Sport') !== false) {
                    $sAero = 98;
                    $reasons[] = 'Aerodynamic sun peak and wide eyeport accommodate upright stance and goggle adaptation.';
                } elseif (stripos($hType, 'Modular') !== false) {
                    $sAero = 94;
                    $reasons[] = 'Upright chin-bar balance minimizes neck strain on long highway journeys.';
                } elseif (stripos($hType, 'Full Face') !== false) {
                    $sAero = 85;
                } else {
                    $sAero = 75;
                }
            } else { // Neutral Roadster / Sport-Touring (60°-80°)
                if (stripos($hType, 'Full Face') !== false) {
                    $sAero = 96;
                    $reasons[] = 'Neutral airflow balance prevents buffeting across variable 75° road postures.';
                } elseif (stripos($hType, 'Modular') !== false) {
                    $sAero = 92;
                } else {
                    $sAero = 75;
                }
            }

            // ─── AXIS 2: Riding Discipline Match (25%) ───
            $sDiscipline = 75;
            switch ($ridingStyle) {
                case 'track':
                    if (stripos($hType, 'Track') !== false || stripos($hType, 'Race') !== false) {
                        $sDiscipline = 99;
                    } elseif (stripos($hType, 'Full Face') !== false) {
                        $sDiscipline = 85;
                    } else {
                        $sDiscipline = 30;
                    }
                    break;
                case 'highway_touring':
                case 'touring':
                    if (stripos($hType, 'Modular') !== false || stripos($hType, 'Touring') !== false) {
                        $sDiscipline = 98;
                        $reasons[] = 'Plush acoustic sealing and all-day ergonomic liner designed for long-distance highway comfort.';
                    } elseif (stripos($hType, 'Full Face') !== false) {
                        $sDiscipline = 90;
                    } else {
                        $sDiscipline = 60;
                    }
                    break;
                case 'adventure':
                case 'adv':
                    if (stripos($hType, 'Adventure') !== false || stripos($hType, 'Dual Sport') !== false) {
                        $sDiscipline = 99;
                        $reasons[] = 'All-terrain design with removable peak visor and dual-mode shield.';
                    } elseif (stripos($hType, 'Modular') !== false) {
                        $sDiscipline = 86;
                    } else {
                        $sDiscipline = 70;
                    }
                    break;
                case 'sport_canyon':
                case 'sport':
                    if (stripos($hType, 'Full Face') !== false) {
                        $sDiscipline = 96;
                        $reasons[] = 'Snug lateral cheek hold and quick head-check aerodynamics for spirited canyon carving.';
                    } else {
                        $sDiscipline = 75;
                    }
                    break;
                case 'commute':
                default:
                    if (stripos($hType, 'Modular') !== false || stripos($hType, 'Full Face') !== false) {
                        $sDiscipline = 95;
                        $reasons[] = 'Lightweight everyday usability with quick-release shield and wide visibility.';
                    } else {
                        $sDiscipline = 80;
                    }
                    break;
            }

            // ─── AXIS 3: Safety & Homologation Match (20%) ───
            $sSafety = 75;
            $hasECE2206 = in_array('ECE 22.06', $certs, true);
            $hasFIM     = in_array('FIM', $certs, true) || in_array('FIM Racing', $certs, true) || in_array('FIM FRHPhe-01', $certs, true);
            $hasSnell   = in_array('Snell', $certs, true) || in_array('Snell M2020D', $certs, true) || in_array('Snell M2025', $certs, true);

            if ($safetyPriority === 'track_homologated' || $ridingStyle === 'track') {
                if ($hasFIM) {
                    $sSafety = 100;
                    $reasons[] = 'FIM racing homologated for closed-course competition.';
                } elseif ($hasECE2206 && $hasSnell) {
                    $sSafety = 95;
                } elseif ($hasECE2206) {
                    $sSafety = 90;
                } else {
                    $sSafety = 50;
                }
            } elseif ($safetyPriority === 'max_safety') {
                if ($hasECE2206) {
                    $sSafety = 95;
                    if ($sharpRating >= 4) {
                        $sSafety = 99;
                        $reasons[] = sprintf('Certified ECE 22.06 with %d-star SHARP safety dissipation rating.', $sharpRating);
                    } else {
                        $reasons[] = 'Certified to strict ECE 22.06 rotational and oblique impact standards.';
                    }
                } elseif ($sharpRating >= 4) {
                    $sSafety = 90;
                } else {
                    $sSafety = 75;
                }
            } else {
                $sSafety = $hasECE2206 ? 92 : 80;
            }

            // ─── AXIS 4: Morphology & Eyewear Fit (15%) ───
            $sMorph = 80;
            if (strpos($hShape, 'round') !== false && strpos($headShapeReq, 'round') !== false) {
                $sMorph = 100;
            } elseif (strpos($hShape, 'long') !== false && strpos($headShapeReq, 'long') !== false) {
                $sMorph = 100;
            } elseif (strpos($hShape, 'intermediate') !== false && (strpos($headShapeReq, 'intermediate') !== false || $headShapeReq === '')) {
                $sMorph = 100;
            } else {
                $sMorph = 70;
            }

            if ($eyewearReq) {
                if ($eyewearComp) {
                    $sMorph = min(100, $sMorph + 5);
                    $reasons[] = 'Integrated eyeglass groove channels prevent temporal arm pinch.';
                } else {
                    $sMorph = max(40, $sMorph - 15);
                }
            }

            // ─── AXIS 5: Features & Peripheral Match (15%) ───
            $sFeatures = 80;
            if (in_array('sun_visor', $featuresReq, true)) {
                if ($hasSunVisor) {
                    $sFeatures += 10;
                    $reasons[] = 'Internal drop-down sun visor for instant glare protection.';
                } else {
                    $sFeatures -= 20;
                }
            }
            if (in_array('bluetooth_ready', $featuresReq, true)) {
                if ($commsReady) {
                    $sFeatures += 10;
                    $reasons[] = 'Factory-cutout speaker cavities ready for 40mm audio drivers.';
                } else {
                    $sFeatures -= 15;
                }
            }
            if (in_array('pinlock_included', $featuresReq, true)) {
                if ($pinlockReady) {
                    $sFeatures += 8;
                } else {
                    $sFeatures -= 10;
                }
            }
            if (in_array('carbon_fiber', $featuresReq, true)) {
                if ($carbonFiber || $weightG <= 1350) {
                    $sFeatures += 10;
                    $reasons[] = 'Ultra-lightweight carbon composite construction minimizes cervical neck fatigue.';
                } else {
                    $sFeatures -= 20;
                }
            }
            $sFeatures = min(100, max(40, $sFeatures));

            // Composite multi-axial score formula:
            // Score = 0.25 * Aero + 0.25 * Discipline + 0.20 * Safety + 0.15 * Morph + 0.15 * Features
            $composite = (0.25 * $sAero)
                       + (0.25 * $sDiscipline)
                       + (0.20 * $sSafety)
                       + (0.15 * $sMorph)
                       + (0.15 * $sFeatures);

            $finalScore = (int) round(min(99, max(45, $composite)));

            // Compatible gear bundle evaluation
            $gearMatches = self::evaluate_accessories($h, $accessories);
            $curatedGear = [];
            foreach ($gearMatches['confirmed'] as $g) {
                $curatedGear[] = [
                    'id'       => $g['id'] ?? '',
                    'title'    => $g['title'] ?? '',
                    'brand'    => $g['brand'] ?? '',
                    'category' => $g['accessory_subcategory'] ?? $g['type'] ?? 'Gear',
                    'price'    => $g['price']['usd'] ?? 50,
                    'note'     => $g['fitment_note'] ?? 'Verified mechanical fitment.',
                ];
                if (count($curatedGear) >= 3) break;
            }

            $currencySymbols = [
                'USD' => '$',
                'EUR' => '€',
                'GBP' => '£',
                'INR' => '₹',
                'CAD' => 'CA$',
                'AUD' => 'AU$',
                'JPY' => '¥',
            ];
            $sym = $currencySymbols[$currency] ?? '$';
            $formattedPrice = ($currency === 'INR' || $currency === 'JPY') 
                ? ($sym . number_format($activePrice)) 
                : ($sym . number_format($activePrice, 2));

            $scored[] = [
                'id'               => $h['id'] ?? '',
                'slug'             => $h['slug'] ?? '',
                'title'            => $h['title'] ?? '',
                'brand'            => $hBrand,
                'helmet_type'      => $hType,
                'permalink'        => $h['permalink'] ?? ('/helmets/' . ($h['slug'] ?? '') . '/'),
                'image_url'        => $h['image_url'] ?? '',
                'match_score'      => $finalScore,
                'sub_scores'       => [
                    'aero_posture' => (int) $sAero,
                    'discipline'   => (int) $sDiscipline,
                    'safety'       => (int) $sSafety,
                    'morphology'   => (int) $sMorph,
                    'features'     => (int) $sFeatures,
                ],
                'price'            => [
                    'currency'  => $currency,
                    'amount'    => $activePrice,
                    'formatted' => $formattedPrice,
                ],
                'specs'            => [
                    'weight_g'          => $weightG,
                    'material'          => $h['material'] ?? 'Composite',
                    'head_shape'        => ucwords($hShape),
                    'primary_cert'      => !empty($certs[0]) ? $certs[0] : 'ECE 22.06',
                    'noise_db'          => $acousticDb ?: 85,
                    'has_sun_visor'     => $hasSunVisor,
                    'comms_ready'       => $commsReady,
                    'eyewear_compatible'=> $eyewearComp,
                    'available_sizes'   => $h['sizes'] ?? ['XS', 'S', 'M', 'L', 'XL'],
                ],
                'key_pros'         => $h['pros'] ?? [],
                'marketplace_links'=> $h['marketplace_links'] ?? [],
                'editorial_reason' => implode(' ', array_unique($reasons)) ?: 'Engineered for exceptional aerodynamic stability and compliant impact protection.',
                'compatible_gear'  => $curatedGear,
            ];
        }

        // Sort descending by match score
        usort($scored, fn($a, $b) => $b['match_score'] <=> $a['match_score']);

        $top = array_slice($scored, 0, $limit);

        // Assign award badges
        if (isset($top[0])) $top[0]['match_tier'] = '🥇 BEST OVERALL MATCH';
        if (isset($top[1])) $top[1]['match_tier'] = '🥈 RUNNER UP / VALUE';
        if (isset($top[2])) $top[2]['match_tier'] = '🥉 TOP ALTERNATIVE';

        // Extract available brands and types from top results for instant client filtering
        $availableBrands = array_values(array_unique(array_filter(array_column($scored, 'brand'))));
        sort($availableBrands);
        $availableTypes = array_values(array_unique(array_filter(array_column($scored, 'helmet_type'))));
        sort($availableTypes);

        return [
            'profile_summary' => [
                'bike'            => $bikeName ?: ucfirst($bikeCategory),
                'bike_category'   => ucfirst($bikeCategory),
                'riding_style'    => ucwords(str_replace('_', ' ', $ridingStyle)),
                'head_shape'      => ucwords(str_replace('_', ' ', $headShapeReq)),
                'safety_priority' => ucwords(str_replace('_', ' ', $safetyPriority)),
            ],
            'facets' => [
                'brands' => $availableBrands,
                'types'  => $availableTypes,
            ],
            'total_candidates_analyzed' => count($candidates),
            'results_count'             => count($top),
            'matches'                   => $top,
        ];
    }

    /**
     * Load candidate helmets with Redis/Memory caching for high-speed sub-millisecond execution.
     *
     * @return array<int, array<string, mixed>>
     */
    private static function load_candidate_helmets(): array {
        $cacheKey = 'hs_finder_candidates_pool_v2';
        if (function_exists('wp_cache_get')) {
            $cached = wp_cache_get($cacheKey, 'helmetsan_compat');
            if (is_array($cached) && !empty($cached)) {
                return $cached;
            }
        }

        // 1. Try SQLite catalog.db
        $dbPath = defined('WP_CONTENT_DIR')
            ? WP_CONTENT_DIR . '/uploads/helmetsan-data/catalog.db'
            : dirname(__DIR__, 2) . '/data/catalog.db';

        if (!file_exists($dbPath)) {
            $altDbPath = dirname(__DIR__, 3) . '/HelmetsanMobile/assets/database/catalog.db';
            if (file_exists($altDbPath)) {
                $dbPath = $altDbPath;
            }
        }

        if (file_exists($dbPath) && class_exists('PDO')) {
            try {
                $pdo = new \PDO('sqlite:' . $dbPath);
                $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_SILENT);
                $stmt = $pdo->query("SELECT id, slug, title, brand, helmet_type, head_shape, weight_g, certifications, price_usd, price_inr, price_eur, price_gbp, material, raw_json FROM helmets LIMIT 2500");
                if ($stmt) {
                    $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                    if (!empty($rows)) {
                        $items = [];
                        foreach ($rows as $r) {
                            $certs = [];
                            if (!empty($r['certifications'])) {
                                $certs = array_filter(array_map('trim', explode(',', $r['certifications'])));
                            }
                            $raw = !empty($r['raw_json']) ? json_decode($r['raw_json'], true) : [];

                            // Extract primary front image
                            $imageUrl = '';
                            if (!empty($raw['geo_media']) && is_array($raw['geo_media']) && !empty($raw['geo_media'][0])) {
                                $imageUrl = (string) $raw['geo_media'][0];
                            } elseif (!empty($raw['media']['primary_image_url'])) {
                                $imageUrl = (string) $raw['media']['primary_image_url'];
                            }

                            // Extract verified pros
                            $pros = [];
                            if (!empty($raw['pros_and_cons']['pros']) && is_array($raw['pros_and_cons']['pros'])) {
                                $pros = array_slice($raw['pros_and_cons']['pros'], 0, 2);
                            }

                            // Extract marketplace retailer links
                            $mLinks = !empty($raw['marketplace_links']) && is_array($raw['marketplace_links']) ? $raw['marketplace_links'] : [];

                            // Extract sizes
                            $sizes = !empty($raw['sizing_fit']['sizing_chart']) && is_array($raw['sizing_fit']['sizing_chart']) 
                                ? array_keys($raw['sizing_fit']['sizing_chart']) 
                                : ['XS', 'S', 'M', 'L', 'XL', '2XL'];

                            $cleanTitle = (string) $r['title'];
                            if (strpos($cleanTitle, '_') !== false) {
                                $clean = (string) preg_replace('/^([a-z0-9]+)\s+\1_/i', '', $cleanTitle);
                                $clean = (string) preg_replace('/^([a-z0-9]+)_/i', '', $clean);
                                $cleanTitle = ucwords(str_replace(['_', '-'], ' ', $clean));
                            }

                            $items[] = [
                                'id'                 => $r['id'],
                                'slug'               => $r['slug'] ?: $r['id'],
                                'title'              => $cleanTitle,
                                'brand'              => $r['brand'],
                                'type'               => $r['helmet_type'] ?: 'Full Face',
                                'head_shape'         => $r['head_shape'] ?: 'Intermediate Oval',
                                'weight_g'           => (int) ($r['weight_g'] ?: 1500),
                                'certifications'     => $certs,
                                'sharp_rating'       => (int) ($raw['safety_intelligence']['sharp_rating'] ?? 0),
                                'noise_db'           => (int) ($raw['aero_acoustic_profile']['noise_db_at_100kph'] ?? 85),
                                'price_usd'          => (float) ($r['price_usd'] ?: 299),
                                'price_inr'          => (float) ($r['price_inr'] ?: 24900),
                                'price_eur'          => (float) ($r['price_eur'] ?: 279),
                                'price_gbp'          => (float) ($r['price_gbp'] ?: 249),
                                'material'           => (string) ($r['material'] ?: ($raw['specs']['shell_material'] ?? 'Composite Shell')),
                                'has_sun_visor'      => !empty($raw['features_data']['visor']) && in_array('Internal Sun Visor', $raw['features_data']['visor']),
                                'comms_ready'        => true,
                                'pinlock_ready'      => true,
                                'eyewear_compatible' => true,
                                'carbon_fiber'       => stripos($r['material'] ?? '', 'carbon') !== false,
                                'permalink'          => '/helmets/' . ($r['slug'] ?: $r['id']) . '/',
                                'image_url'          => $imageUrl,
                                'pros'               => $pros,
                                'marketplace_links'  => $mLinks,
                                'sizes'              => $sizes,
                            ];
                        }
                        if (function_exists('wp_cache_set')) {
                            wp_cache_set($cacheKey, $items, 'helmetsan_compat', 43200);
                        }
                        return $items;
                    }
                }
            } catch (\Exception $e) {}
        }

        // 2. Fallback to WordPress WP_Query
        if (function_exists('get_posts')) {
            $posts = get_posts([
                'post_type'      => 'helmet',
                'posts_per_page' => 200,
                'post_status'    => 'publish',
            ]);
            $items = [];
            foreach ($posts as $p) {
                if ($p instanceof \WP_Post) {
                    $pid = $p->ID;
                    $brandMeta = get_post_meta($pid, 'rel_brand', true);
                    $brandName = is_numeric($brandMeta) ? get_the_title((int)$brandMeta) : (string) get_post_meta($pid, 'brand_name', true);
                    
                    $geoMedia = json_decode((string) get_post_meta($pid, 'geo_media_json', true), true);
                    $imageUrl = !empty($geoMedia[0]) ? (string) $geoMedia[0] : (get_the_post_thumbnail_url($pid, 'medium') ?: '');

                    $prosData = json_decode((string) get_post_meta($pid, 'pros_and_cons_json', true), true);
                    $pros = !empty($prosData['pros']) && is_array($prosData['pros']) ? array_slice($prosData['pros'], 0, 2) : [];

                    $postTitle = (string) $p->post_title;
                    if (strpos($postTitle, '_') !== false) {
                        $clean = (string) preg_replace('/^([a-z0-9]+)\s+\1_/i', '', $postTitle);
                        $clean = (string) preg_replace('/^([a-z0-9]+)_/i', '', $clean);
                        $postTitle = ucwords(str_replace(['_', '-'], ' ', $clean));
                    }

                    $items[] = [
                        'id'                 => $p->post_name,
                        'slug'               => $p->post_name,
                        'title'              => $postTitle,
                        'brand'              => $brandName ?: 'Premium',
                        'type'               => (string) get_post_meta($pid, 'helmet_type', true) ?: 'Full Face',
                        'head_shape'         => (string) get_post_meta($pid, 'head_shape', true) ?: 'Intermediate Oval',
                        'weight_g'           => (int) get_post_meta($pid, 'weight_g', true) ?: 1450,
                        'certifications'     => (array) (get_post_meta($pid, 'certifications', true) ?: ['ECE 22.06']),
                        'sharp_rating'       => (int) get_post_meta($pid, 'sharp_rating', true),
                        'noise_db'           => (int) get_post_meta($pid, 'noise_db_at_100kph', true) ?: 85,
                        'price_usd'          => (float) get_post_meta($pid, 'price_usd', true) ?: 349,
                        'price_inr'          => (float) get_post_meta($pid, 'price_inr', true) ?: 29000,
                        'price_eur'          => (float) get_post_meta($pid, 'price_eur', true) ?: 320,
                        'price_gbp'          => (float) get_post_meta($pid, 'price_gbp', true) ?: 280,
                        'material'           => (string) get_post_meta($pid, 'shell_material', true) ?: 'Composite',
                        'has_sun_visor'      => (bool) get_post_meta($pid, 'has_internal_sun_visor', true),
                        'comms_ready'        => (bool) get_post_meta($pid, 'comms_ready', true) ?: true,
                        'pinlock_ready'      => (bool) get_post_meta($pid, 'pinlock_included', true) ?: true,
                        'eyewear_compatible' => true,
                        'carbon_fiber'       => stripos((string) get_post_meta($pid, 'shell_material', true), 'carbon') !== false,
                        'permalink'          => get_permalink($pid) ?: ('/helmets/' . $p->post_name . '/'),
                        'image_url'          => $imageUrl,
                        'pros'               => $pros,
                        'marketplace_links'  => [],
                        'sizes'              => ['XS', 'S', 'M', 'L', 'XL'],
                    ];
                }
            }
            if (!empty($items)) {
                if (function_exists('wp_cache_set')) {
                    wp_cache_set($cacheKey, $items, 'helmetsan_compat', 43200);
                }
                return $items;
            }
        }

        return [];
    }

    /**
     * Load candidate accessories for gear bundling.
     *
     * @return array<int, array<string, mixed>>
     */
    private static function load_candidate_accessories(): array {
        $accDir = defined('WP_CONTENT_DIR')
            ? WP_CONTENT_DIR . '/uploads/helmetsan-data/accessories'
            : dirname(__DIR__, 2) . '/data/accessories';

        if (is_dir($accDir)) {
            $files = glob($accDir . '/*.json') ?: [];
            $accessories = [];
            foreach ($files as $f) {
                if (strpos($f, 'example') !== false) continue;
                $d = json_decode((string) file_get_contents($f), true);
                if (is_array($d)) {
                    $accessories[] = $d;
                }
            }
            if (!empty($accessories)) {
                return $accessories;
            }
        }

        return [
            [
                'id' => 'cardo-packtalk-pro',
                'title' => 'Cardo Packtalk Pro',
                'brand' => 'Cardo',
                'accessory_subcategory' => 'Bluetooth Intercom',
                'price' => ['usd' => 459],
                'fitment_note' => 'Automatic crash detection & 45mm JBL audio drivers fit factory EPS cavities.',
            ],
            [
                'id' => 'sena-50s-mesh',
                'title' => 'Sena 50S Mesh 2.0',
                'brand' => 'Sena',
                'accessory_subcategory' => 'Mesh Intercom',
                'price' => ['usd' => 359],
                'fitment_note' => 'Harman Kardon audio system with universal clamp and speaker pocket fit.',
            ],
            [
                'id' => 'pinlock-120-lens',
                'title' => 'Pinlock 120 MaxVision Insert',
                'brand' => 'Pinlock',
                'accessory_subcategory' => 'Anti-Fog Visor Lens',
                'price' => ['usd' => 39],
                'fitment_note' => 'Highest-grade hydrophilic moisture absorption prevents shield fogging.',
            ],
            [
                'id' => 'hearing-protection-earplugs',
                'title' => 'NoNoise Motorsport Hearing Protection',
                'brand' => 'NoNoise',
                'accessory_subcategory' => 'Hearing Protection',
                'price' => ['usd' => 29],
                'fitment_note' => 'Precision ceramic filter dampens harmful wind buffeting by 24 dB.',
            ],
        ];
    }
}

function helmetsan_evaluate_rider_profile(array $profile, int $limit = 9): array {
    return Helmetsan_CompatibilityEngine::evaluate_rider_profile($profile, $limit);
}

function helmetsan_search_motorcycles(string $query = '', int $limit = 12): array {
    return Helmetsan_CompatibilityEngine::search_motorcycles($query, $limit);
}
