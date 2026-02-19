<?php

declare(strict_types=1);

namespace Helmetsan\Core\API;

use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use Helmetsan\Core\Price\PriceService;
use Helmetsan\Core\Price\PriceHistory;

/**
 * REST API controller for price data.
 *
 * Endpoints (public, no auth):
 *   GET /hs/v1/prices/{id}              → best price + all offers
 *   GET /hs/v1/prices/{id}/history      → price history chart data
 */
final class PriceController
{
    private string $namespace = 'hs/v1';

    public function __construct(
        private readonly PriceService $priceService,
        private readonly PriceHistory $priceHistory,
    ) {
    }

    public function register(): void
    {
        add_action('rest_api_init', [$this, 'registerRoutes']);
    }

    public function registerRoutes(): void
    {
        register_rest_route($this->namespace, '/prices/(?P<id>[\d]+)', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'getPrices'],
                'permission_callback' => '__return_true',
                'args'                => [
                    'id'      => ['type' => 'integer', 'required' => true],
                    'country' => ['type' => 'string',  'required' => false],
                ],
            ],
        ]);

        register_rest_route($this->namespace, '/prices/(?P<id>[\d]+)/history', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'getPriceHistory'],
                'permission_callback' => '__return_true',
                'args'                => [
                    'id'          => ['type' => 'integer', 'required' => true],
                    'days'        => ['type' => 'integer', 'required' => false, 'default' => 30],
                    'marketplace' => ['type' => 'string',  'required' => false],
                    'country'     => ['type' => 'string',  'required' => false],
                ],
            ],
        ]);
    }

    /**
     * GET /hs/v1/prices/{id}
     *
     * Returns best price and all marketplace offers for a helmet.
     */
    public function getPrices(WP_REST_Request $request): WP_REST_Response
    {
        $postId  = (int) $request['id'];
        $country = $request->get_param('country');

        $post = get_post($postId);
        if (!$post || $post->post_type !== 'helmet') {
            return new WP_REST_Response(['message' => 'Helmet not found'], 404);
        }

        $best   = $this->priceService->getBestPrice($postId, $country);
        $offers = $this->priceService->getAllOffers($postId, $country);

        return new WP_REST_Response([
            'helmet_id'  => $postId,
            'helmet_ref' => $post->post_name,
            'best_price' => $best !== null ? $best->toArray() : null,
            'offers'     => array_map(fn($o) => $o->toArray(), $offers),
            'formatted'  => $this->priceService->getGeoPrice($postId, $country),
        ], 200);
    }

    /**
     * GET /hs/v1/prices/{id}/history
     *
     * Returns price history for Chart.js consumption.
     */
    public function getPriceHistory(WP_REST_Request $request): WP_REST_Response
    {
        $postId      = (int) $request['id'];
        $days        = max(1, min(365, (int) ($request->get_param('days') ?? 30)));
        $marketplace = $request->get_param('marketplace');
        $country     = $request->get_param('country');

        $post = get_post($postId);
        if (!$post || $post->post_type !== 'helmet') {
            return new WP_REST_Response(['message' => 'Helmet not found'], 404);
        }

        $history = $this->priceHistory->getHistory(
            $postId,
            $days,
            $marketplace !== '' ? $marketplace : null,
            $country !== '' && $country !== null ? $country : null
        );

        // Group by marketplace for Chart.js multi-series
        $series = [];
        foreach ($history as $entry) {
            $mpId = $entry['marketplace_id'];
            if (!isset($series[$mpId])) {
                $series[$mpId] = [
                    'marketplace_id' => $mpId,
                    'currency'       => $entry['currency'],
                    'data'           => [],
                ];
            }
            $series[$mpId]['data'][] = [
                'date'  => $entry['captured_at'],
                'price' => $entry['price'],
            ];
        }

        return new WP_REST_Response([
            'helmet_id' => $postId,
            'days'      => $days,
            'series'    => array_values($series),
        ], 200);
    }
}
