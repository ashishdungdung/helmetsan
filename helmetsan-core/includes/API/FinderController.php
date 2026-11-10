<?php

declare(strict_types=1);

namespace Helmetsan\Core\API;

use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use Helmetsan_CompatibilityEngine;

/**
 * REST API controller for the interactive "Find My Helmet" Guided Advisor
 * and 6-Axial compatibility matching engine.
 *
 * Endpoints:
 *   GET /hs/v1/finder/match     → 6-Axial match calculation & ranked recommendations
 *   GET /hs/v1/finder/bikes     → Motorcycle autocomplete & category lookup
 */
final class FinderController
{
    private string $namespace = 'hs/v1';

    public function register(): void
    {
        add_action('rest_api_init', [$this, 'registerRoutes']);
    }

    public function registerRoutes(): void
    {
        // 1. Finder Match Calculation
        register_rest_route($this->namespace, '/finder/match', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'calculateMatch'],
                'permission_callback' => '__return_true',
                'args'                => [
                    'bike'            => ['type' => 'string',  'required' => false],
                    'bike_category'   => ['type' => 'string',  'required' => false],
                    'riding_style'    => ['type' => 'string',  'required' => false, 'default' => 'commute'],
                    'head_shape'      => ['type' => 'string',  'required' => false, 'default' => 'intermediate_oval'],
                    'eyewear'         => ['type' => 'boolean', 'required' => false, 'default' => false],
                    'safety_priority' => ['type' => 'string',  'required' => false, 'default' => 'max_safety'],
                    'features'        => ['type' => 'string',  'required' => false], // comma-separated
                    'budget_max'      => ['type' => 'number',  'required' => false, 'default' => 0],
                    'currency'        => ['type' => 'string',  'required' => false, 'default' => 'USD'],
                    'limit'           => ['type' => 'integer', 'required' => false, 'default' => 9],
                ],
            ],
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'calculateMatchPost'],
                'permission_callback' => '__return_true',
            ]
        ]);

        // 2. Bike Search / Autocomplete
        register_rest_route($this->namespace, '/finder/bikes', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'searchBikes'],
                'permission_callback' => '__return_true',
                'args'                => [
                    'q'     => ['type' => 'string',  'required' => false, 'default' => ''],
                    'limit' => ['type' => 'integer', 'required' => false, 'default' => 12],
                ],
            ],
        ]);
    }

    /**
     * Handle GET request for matching
     */
    public function calculateMatch(WP_REST_Request $request): WP_REST_Response
    {
        $params = $request->get_params();
        $profile = $this->sanitizeProfile($params);
        $limit = max(1, min(24, (int) ($params['limit'] ?? 9)));

        $results = Helmetsan_CompatibilityEngine::evaluate_rider_profile($profile, $limit);
        return new WP_REST_Response($results, 200);
    }

    /**
     * Handle POST request for matching (supports complex JSON payload)
     */
    public function calculateMatchPost(WP_REST_Request $request): WP_REST_Response
    {
        $params = $request->get_json_params() ?: $request->get_params();
        $profile = $this->sanitizeProfile($params);
        $limit = max(1, min(24, (int) ($params['limit'] ?? 9)));

        $results = Helmetsan_CompatibilityEngine::evaluate_rider_profile($profile, $limit);
        return new WP_REST_Response($results, 200);
    }

    /**
     * Search motorcycles for autocomplete
     */
    public function searchBikes(WP_REST_Request $request): WP_REST_Response
    {
        $q = sanitize_text_field((string) ($request->get_param('q') ?? ''));
        $limit = max(1, min(25, (int) ($request->get_param('limit') ?? 12)));

        $bikes = Helmetsan_CompatibilityEngine::search_motorcycles($q, $limit);
        return new WP_REST_Response(['bikes' => $bikes], 200);
    }

    /**
     * Sanitize input profile
     *
     * @param array<string, mixed> $raw
     * @return array<string, mixed>
     */
    private function sanitizeProfile(array $raw): array
    {
        $features = [];
        if (!empty($raw['features'])) {
            if (is_array($raw['features'])) {
                $features = array_map('sanitize_key', $raw['features']);
            } else {
                $features = array_filter(array_map('sanitize_key', explode(',', (string) $raw['features'])));
            }
        }

        return [
            'bike'            => sanitize_text_field((string) ($raw['bike'] ?? '')),
            'bike_category'   => sanitize_key((string) ($raw['bike_category'] ?? '')),
            'riding_style'    => sanitize_key((string) ($raw['riding_style'] ?? 'commute')),
            'head_shape'      => sanitize_key((string) ($raw['head_shape'] ?? 'intermediate_oval')),
            'eyewear'         => !empty($raw['eyewear']),
            'safety_priority' => sanitize_key((string) ($raw['safety_priority'] ?? 'max_safety')),
            'features'        => $features,
            'budget_max'      => (float) ($raw['budget_max'] ?? 0),
            'currency'        => strtoupper(sanitize_key((string) ($raw['currency'] ?? 'USD'))),
        ];
    }
}
