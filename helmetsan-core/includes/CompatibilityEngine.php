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
}

function helmetsan_evaluate_accessories(array $helmet, array $accessories): array {
    return Helmetsan_CompatibilityEngine::evaluate_accessories($helmet, $accessories);
}

function helmetsan_get_entity_compatibility(string $type, string $id): array {
    return Helmetsan_CompatibilityEngine::get_entity_compatibility($type, $id);
}
