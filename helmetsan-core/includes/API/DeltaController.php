<?php

declare(strict_types=1);

namespace Helmetsan\Core\API;

use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use Helmetsan\Core\Repository\JsonRepository;

/**
 * REST API Controller for Incremental Catalog Delta Synchronization.
 *
 * Provides a lightweight delta-sync endpoint for HelmetsanMobile and external consumers.
 * Instead of re-downloading the entire 80+ MB SQLite database on catalog updates,
 * the mobile client requests changes since its last local sync timestamp.
 *
 * Route: GET /helmetsan/v1/catalog/delta?since=<timestamp>&type=<type>&limit=<limit>
 */
class DeltaController extends WP_REST_Controller
{
    protected $namespace = 'helmetsan/v1';
    protected $rest_base = 'catalog';

    public function __construct(
        private readonly ?JsonRepository $repository = null
    ) {
    }

    /**
     * Register hooks.
     */
    public function register(): void
    {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    /**
     * Register REST API routes.
     */
    public function register_routes(): void
    {
        register_rest_route($this->namespace, '/' . $this->rest_base . '/delta', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'get_delta'],
                'permission_callback' => '__return_true',
                'args'                => [
                    'since' => [
                        'description'       => 'Unix timestamp or ISO-8601 date of the last successful client sync.',
                        'type'              => 'string',
                        'required'          => false,
                        'default'           => '0',
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                    'type' => [
                        'description'       => 'Filter by entity type: all, helmet, motorcycle, accessory, brand.',
                        'type'              => 'string',
                        'required'          => false,
                        'default'           => 'all',
                        'sanitize_callback' => 'sanitize_key',
                    ],
                    'limit' => [
                        'description'       => 'Maximum number of items to return per entity collection.',
                        'type'              => 'integer',
                        'required'          => false,
                        'default'           => 200,
                        'sanitize_callback' => 'absint',
                    ],
                ],
            ],
        ]);
    }

    /**
     * Handle the delta sync request.
     */
    public function get_delta(WP_REST_Request $request): WP_REST_Response
    {
        $rawSince = $request->get_param('since') ?? '0';
        $type = (string) ($request->get_param('type') ?? 'all');
        $limit = max(1, min(500, (int) ($request->get_param('limit') ?? 200)));

        $sinceTimestamp = is_numeric($rawSince)
            ? (int) $rawSince
            : (strtotime($rawSince) ?: 0);

        $now = time();

        $delta = [
            'helmets'     => [],
            'motorcycles' => [],
            'accessories' => [],
            'brands'      => [],
        ];

        $deleted = [
            'helmets'     => [],
            'motorcycles' => [],
            'accessories' => [],
            'brands'      => [],
        ];

        // 1. Gather updates from master JSON repository if available
        $dataDir = $this->resolveDataDirectory();
        if ($dataDir && is_dir($dataDir)) {
            $this->collectDeltaFromJson(
                $dataDir,
                $sinceTimestamp,
                $type,
                $limit,
                $delta
            );
        }

        // 2. Gather updates/trashed posts from WordPress database if WP_Query is active
        $this->collectDeltaFromWordPress($sinceTimestamp, $type, $limit, $delta, $deleted);

        $counts = [
            'helmets'     => count($delta['helmets']),
            'motorcycles' => count($delta['motorcycles']),
            'accessories' => count($delta['accessories']),
            'brands'      => count($delta['brands']),
            'deleted'     => count($deleted['helmets']) + count($deleted['motorcycles']) + count($deleted['accessories']) + count($deleted['brands']),
        ];

        $totalChanges = array_sum($counts);

        $response = [
            'success'       => true,
            'server_time'   => $now,
            'since'         => $sinceTimestamp,
            'total_changes' => $totalChanges,
            'counts'        => $counts,
            'data'          => $delta,
            'deleted'       => $deleted,
        ];

        return new WP_REST_Response($response, 200, [
            'Cache-Control' => 'no-cache, private, must-revalidate',
            'X-Helmetsan-Delta-Sync' => 'v1',
        ]);
    }

    /**
     * Resolve the filesystem path to the master data directory.
     */
    private function resolveDataDirectory(): ?string
    {
        if ($this->repository !== null && $this->repository->exists()) {
            return $this->repository->rootPath();
        }

        // Fallback search in standard directory layouts
        $candidates = [
            dirname(__DIR__, 3) . '/data',                    // HelmetsanWeb/data
            dirname(__DIR__, 4) . '/data',                    // root data
            WP_CONTENT_DIR . '/../data',                      // WP content relative
            '/var/www/helmetsan/data',                        // Production server path
        ];

        foreach ($candidates as $cand) {
            if (is_dir($cand)) {
                return $cand;
            }
        }

        return null;
    }

    /**
     * Collect delta modifications from JSON files.
     *
     * @param array<string, array<mixed>> $delta
     */
    private function collectDeltaFromJson(
        string $dataDir,
        int $sinceTimestamp,
        string $type,
        int $limit,
        array &$delta
    ): void {
        // Brands
        if (in_array($type, ['all', 'brand', 'brands'], true)) {
            $brandFiles = glob($dataDir . '/brands/*.json') ?: [];
            foreach ($brandFiles as $file) {
                if (count($delta['brands']) >= $limit) {
                    break;
                }
                $mtime = filemtime($file);
                if ($sinceTimestamp > 0 && $mtime <= $sinceTimestamp) {
                    continue;
                }
                $content = @file_get_contents($file);
                if (!$content) {
                    continue;
                }
                $b = json_decode($content, true);
                if (!is_array($b)) {
                    continue;
                }
                $bId = $b['id'] ?? pathinfo($file, PATHINFO_FILENAME);
                $delta['brands'][] = [
                    'id'           => (string) $bId,
                    'name'         => (string) ($b['name'] ?? $b['title'] ?? ucwords(str_replace('_', ' ', $bId))),
                    'slug'         => (string) ($b['slug'] ?? str_replace('_', '-', $bId)),
                    'country'      => (string) ($b['country'] ?? $b['origin_country'] ?? ''),
                    'description'  => (string) ($b['description'] ?? ''),
                    'helmet_count' => (int) ($b['helmet_count'] ?? 0),
                    'updated_at'   => $mtime,
                ];
            }
        }

        // Helmets
        if (in_array($type, ['all', 'helmet', 'helmets'], true)) {
            $helmetFiles = glob($dataDir . '/helmets/*.json') ?: [];
            foreach ($helmetFiles as $file) {
                if (count($delta['helmets']) >= $limit) {
                    break;
                }
                $mtime = filemtime($file);
                if ($sinceTimestamp > 0 && $mtime <= $sinceTimestamp) {
                    continue;
                }
                $content = @file_get_contents($file);
                if (!$content) {
                    continue;
                }
                $h = json_decode($content, true);
                if (!is_array($h)) {
                    continue;
                }
                $hId = (string) ($h['id'] ?? pathinfo($file, PATHINFO_FILENAME));
                $specs = is_array($h['specs'] ?? null) ? $h['specs'] : [];
                $prices = is_array($h['price'] ?? null) ? $h['price'] : [];
                $certs = $specs['certifications'] ?? [];
                $certsStr = is_array($certs) ? implode(', ', $certs) : (string) $certs;
                $variants = is_array($h['variants'] ?? null) ? $h['variants'] : [];

                $formattedVariants = [];
                foreach ($variants as $v) {
                    $vId = (string) ($v['id'] ?? ($hId . '_' . ($v['color'] ?? '')));
                    $vPrices = is_array($v['price'] ?? null) ? $v['price'] : $prices;
                    $formattedVariants[] = [
                        'id'           => $vId,
                        'helmet_id'    => $hId,
                        'title'        => (string) ($v['title'] ?? ''),
                        'color'        => (string) ($v['color'] ?? ''),
                        'color_family' => (string) ($v['color_family'] ?? ''),
                        'sku'          => (string) ($v['sku'] ?? ''),
                        'finish'       => (string) ($v['finish'] ?? ''),
                        'availability' => (string) ($v['availability'] ?? 'instock'),
                        'price_usd'    => (float) ($vPrices['usd'] ?? ($prices['usd'] ?? 0)),
                        'price_inr'    => (float) ($vPrices['inr'] ?? ($prices['inr'] ?? 0)),
                    ];
                }

                $delta['helmets'][] = [
                    'id'             => $hId,
                    'slug'           => (string) ($h['slug'] ?? str_replace('_', '-', $hId)),
                    'title'          => (string) ($h['title'] ?? $h['name'] ?? ucwords(str_replace('_', ' ', $hId))),
                    'brand'          => (string) ($h['brand'] ?? 'Independent'),
                    'helmet_type'    => (string) ($h['type'] ?? $h['category'] ?? 'Full Face'),
                    'helmet_family'  => (string) ($h['helmet_family'] ?? ''),
                    'head_shape'     => (string) ($h['head_shape'] ?? ''),
                    'material'       => (string) ($specs['material'] ?? ''),
                    'strap_type'     => (string) ($specs['strap_type'] ?? ''),
                    'weight_g'       => (int) ($specs['weight_g'] ?? 0),
                    'weight_lbs'     => (float) ($specs['weight_lbs'] ?? 0.0),
                    'certifications' => $certsStr,
                    'price_usd'      => (float) ($prices['usd'] ?? 0),
                    'price_inr'      => (float) ($prices['inr'] ?? 0),
                    'price_eur'      => (float) ($prices['eur'] ?? 0),
                    'price_gbp'      => (float) ($prices['gbp'] ?? 0),
                    'price_jpy'      => (float) ($prices['jpy'] ?? 0),
                    'description'    => (string) ($h['description'] ?? ''),
                    'variants_count' => count($variants),
                    'variants'       => $formattedVariants,
                    'raw_json'       => $content,
                    'updated_at'     => $mtime,
                ];
            }
        }

        // Motorcycles
        if (in_array($type, ['all', 'motorcycle', 'motorcycles'], true)) {
            $bikeFiles = glob($dataDir . '/motorcycles/*.json') ?: [];
            foreach ($bikeFiles as $file) {
                if (count($delta['motorcycles']) >= $limit) {
                    break;
                }
                $mtime = filemtime($file);
                if ($sinceTimestamp > 0 && $mtime <= $sinceTimestamp) {
                    continue;
                }
                $content = @file_get_contents($file);
                if (!$content) {
                    continue;
                }
                $b = json_decode($content, true);
                if (!is_array($b)) {
                    continue;
                }
                $bId = (string) ($b['id'] ?? pathinfo($file, PATHINFO_FILENAME));
                $prices = is_array($b['price'] ?? null) ? $b['price'] : [];

                $delta['motorcycles'][] = [
                    'id'              => $bId,
                    'slug'            => (string) ($b['slug'] ?? str_replace('_', '-', $bId)),
                    'title'           => (string) ($b['title'] ?? $b['name'] ?? ucwords(str_replace('_', ' ', $bId))),
                    'brand'           => (string) ($b['brand'] ?? $b['make'] ?? 'Independent'),
                    'category'        => (string) ($b['category'] ?? 'Urban Roadster'),
                    'displacement_cc' => (int) ($b['displacement_cc'] ?? 0),
                    'power_hp'        => (float) ($b['power_hp'] ?? 0.0),
                    'torque_nm'       => (float) ($b['torque_nm'] ?? 0.0),
                    'curb_weight_kg'  => (float) ($b['curb_weight_kg'] ?? 0.0),
                    'seat_height_mm'  => (int) ($b['seat_height_mm'] ?? 800),
                    'fuel_capacity_l' => (float) ($b['fuel_capacity_l'] ?? 14.0),
                    'riding_position' => (string) ($b['riding_position'] ?? 'Upright Neutral'),
                    'price_usd'       => (float) ($prices['usd'] ?? 0),
                    'price_inr'       => (float) ($prices['inr'] ?? 0),
                    'description'     => (string) ($b['editorial_overview'] ?? $b['description'] ?? ''),
                    'verdict'         => (string) ($b['rider_takeaway'] ?? ''),
                    'raw_json'        => $content,
                    'updated_at'      => $mtime,
                ];
            }
        }

        // Accessories
        if (in_array($type, ['all', 'accessory', 'accessories'], true)) {
            $accFiles = glob($dataDir . '/accessories/*.json') ?: [];
            foreach ($accFiles as $file) {
                if (count($delta['accessories']) >= $limit) {
                    break;
                }
                $mtime = filemtime($file);
                if ($sinceTimestamp > 0 && $mtime <= $sinceTimestamp) {
                    continue;
                }
                $content = @file_get_contents($file);
                if (!$content) {
                    continue;
                }
                $a = json_decode($content, true);
                if (!is_array($a)) {
                    continue;
                }
                $aId = (string) ($a['id'] ?? pathinfo($file, PATHINFO_FILENAME));
                $prices = is_array($a['price'] ?? null) ? $a['price'] : [];
                $verdict = is_array($a['qualitative_intelligence'] ?? null)
                    ? ($a['qualitative_intelligence']['editorial_verdict'] ?? '')
                    : '';

                $delta['accessories'][] = [
                    'id'          => $aId,
                    'title'       => (string) ($a['title'] ?? ucwords(str_replace('-', ' ', $aId))),
                    'brand'       => (string) ($a['brand'] ?? 'Universal'),
                    'category'    => (string) ($a['category'] ?? $a['type'] ?? 'Accessories'),
                    'subcategory' => (string) ($a['accessory_subcategory'] ?? $a['subcategory'] ?? ''),
                    'price_usd'   => (float) ($prices['usd'] ?? 0),
                    'price_inr'   => (float) ($prices['inr'] ?? 0),
                    'description' => (string) ($a['description'] ?? ''),
                    'verdict'     => (string) $verdict,
                    'raw_json'    => $content,
                    'updated_at'  => $mtime,
                ];
            }
        }
    }

    /**
     * Collect delta modifications and deleted/trashed records from WordPress.
     *
     * @param array<string, array<mixed>> $delta
     * @param array<string, array<string>> $deleted
     */
    private function collectDeltaFromWordPress(
        int $sinceTimestamp,
        string $type,
        int $limit,
        array &$delta,
        array &$deleted
    ): void {
        if (!function_exists('get_posts')) {
            return;
        }

        $cptMapping = [
            'helmet'     => 'helmets',
            'motorcycle' => 'motorcycles',
            'accessory'  => 'accessories',
            'brand'      => 'brands',
        ];

        // Check for trashed items since timestamp to alert mobile client of deletions
        if ($sinceTimestamp > 0) {
            $trashedQuery = [
                'post_type'      => ['helmet', 'motorcycle', 'accessory', 'brand'],
                'post_status'    => 'trash',
                'posts_per_page' => 100,
                'date_query'     => [
                    [
                        'column'    => 'post_modified_gmt',
                        'after'     => gmdate('Y-m-d H:i:s', $sinceTimestamp),
                        'inclusive' => false,
                    ],
                ],
                'fields'         => 'ids',
            ];

            $trashedPosts = get_posts($trashedQuery);
            foreach ($trashedPosts as $postId) {
                $postType = get_post_type($postId);
                $uniqueId = get_post_meta($postId, '_helmet_unique_id', true)
                    ?: get_post_field('post_name', $postId);
                if ($uniqueId && isset($cptMapping[$postType])) {
                    $key = $cptMapping[$postType];
                    if (!in_array((string) $uniqueId, $deleted[$key], true)) {
                        $deleted[$key][] = (string) $uniqueId;
                    }
                }
            }
        }
    }
}
