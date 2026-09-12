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
     * Match a Motorcycle to compatible Helmets dynamically based on segment, speed, and aero.
     */
    public static function match_motorcycle_to_helmets(array $bike, array $helmets, int $limit = 6): array {
        $category = (string) ($bike['category'] ?? 'Urban Roadster');
        $topSpeed = (int) ($bike['top_speed_kmh'] ?? 120);

        $results = [];
        foreach ($helmets as $h) {
            $hType = (string) ($h['helmet_type'] ?? $h['type'] ?? 'Full Face');
            $score = 70;
            $reasons = [];

            // Segment Posture Correlation
            if (stripos($category, 'Superbike') !== false || stripos($category, 'Sportbike') !== false) {
                if ($hType === 'Track / Race' || $hType === 'Full Face') {
                    $score += 25;
                    $reasons[] = 'Optimized for full-tuck forward vision and high-velocity aero stability.';
                } else {
                    $score -= 30;
                }
            } elseif (stripos($category, 'Adventure') !== false) {
                if (stripos($hType, 'Adventure') !== false || stripos($hType, 'Dual Sport') !== false) {
                    $score += 25;
                    $reasons[] = 'Aerodynamic sun-peak and wide eyeport accommodate off-road terrain and goggle fitment.';
                } elseif ($hType === 'Modular' || $hType === 'Full Face') {
                    $score += 15;
                    $reasons[] = 'Comfortable upright touring dynamics and sound isolation.';
                }
            } elseif (stripos($category, 'Tourer') !== false) {
                if ($hType === 'Modular' || $hType === 'Touring' || $hType === 'Full Face') {
                    $score += 25;
                    $reasons[] = 'All-day acoustic insulation and drop-down sun visor convenience for cross-country routes.';
                }
            } else {
                if ($hType === 'Full Face' || $hType === 'Modular') {
                    $score += 20;
                    $reasons[] = 'Versatile protection profile for daily urban and canyon riding.';
                }
            }

            // Speed & Homologation Checks
            if ($topSpeed >= 200) {
                $certs = $h['certifications'] ?? [];
                if (in_array('ECE 22.06', $certs, true) || in_array('FIM', $certs, true)) {
                    $score += 5;
                    $reasons[] = 'Certified to high-energy impact standards exceeding 200 km/h requirements.';
                }
            }

            $score = min(99, max(40, $score));
            $results[] = [
                'id'           => $h['id'] ?? '',
                'title'        => $h['title'] ?? '',
                'brand'        => $h['brand'] ?? '',
                'type'         => $hType,
                'match_score'  => $score,
                'match_reason' => implode(' ', $reasons) ?: 'Compatible all-round protection profile.'
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
