<?php

declare(strict_types=1);

namespace Helmetsan\Core\API;

use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WP_Error;
use WP_Query;

/**
 * REST API Controller for CDN and Cloudflare Workers edge caching compatibility.
 */
class CdnController extends WP_REST_Controller
{
    protected $namespace = 'helmetsan/v1';
    protected $rest_base = 'edge';

    /**
     * Register hooks.
     */
    public function register(): void
    {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    /**
     * Register routes.
     */
    public function register_routes(): void
    {
        register_rest_route($this->namespace, '/' . $this->rest_base . '/mega-menu', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'get_mega_menu'],
                'permission_callback' => '__return_true',
                'args'                => [
                    'type' => [
                        'description' => 'Mega menu type: helmet, brands, accessories, motorcycles',
                        'type'        => 'string',
                        'required'    => true,
                        'enum'        => ['helmet', 'brands', 'accessories', 'motorcycles'],
                    ],
                    'lang' => [
                        'description' => 'Language code: en, de, zh',
                        'type'        => 'string',
                        'required'    => false,
                    ],
                    'format' => [
                        'description' => 'Response format: json, html',
                        'type'        => 'string',
                        'required'    => false,
                        'enum'        => ['json', 'html'],
                    ],
                ],
            ],
        ]);

        register_rest_route($this->namespace, '/' . $this->rest_base . '/stats', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'get_stats'],
                'permission_callback' => '__return_true',
                'args'                => [
                    'lang' => [
                        'description' => 'Language code: en, de, zh',
                        'type'        => 'string',
                        'required'    => false,
                    ],
                ],
            ],
        ]);

        register_rest_route($this->namespace, '/' . $this->rest_base . '/rates', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'get_rates'],
                'permission_callback' => '__return_true',
            ],
        ]);
    }

    /**
     * Retrieve a cached mega menu HTML block.
     */
    public function get_mega_menu(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $type = sanitize_text_field($request['type']);
        $lang = $request['lang'] ? sanitize_text_field($request['lang']) : null;
        $format = $request['format'] ? sanitize_text_field($request['format']) : 'json';

        if (empty($lang)) {
            $lang = function_exists('pll_current_language') ? pll_current_language() : 'en';
        }

        $cacheKey = 'hs_mega_menu_' . $type . '_' . $lang;
        $html = get_transient($cacheKey);

        // If not cached, dynamically render it (which will also populate the transient)
        if ($html === false) {
            if (!function_exists('helmetsan_render_mega_menu')) {
                return new WP_Error('rest_error', 'Theme template functions are unavailable', ['status' => 500]);
            }

            // Align Polylang language setting if requested language is different from active language
            if (function_exists('pll_current_language') && $lang !== pll_current_language()) {
                if (isset($GLOBALS['polylang'])) {
                    $GLOBALS['polylang']->curlang = $GLOBALS['polylang']->model->get_language($lang);
                }
            }

            ob_start();
            helmetsan_render_mega_menu($type);
            $html = ob_get_clean();
        }

        if (empty($html)) {
            return new WP_Error('rest_empty', 'Mega menu content is empty or failed to generate', ['status' => 404]);
        }

        if ($format === 'html') {
            header('Content-Type: text/html; charset=UTF-8');
            echo $html;
            exit;
        }


        return new WP_REST_Response(['html' => $html], 200);
    }

    /**
     * Retrieve cached homepage counts.
     */
    public function get_stats(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $lang = $request['lang'] ? sanitize_text_field($request['lang']) : null;
        if (empty($lang)) {
            $lang = function_exists('pll_current_language') ? pll_current_language() : 'en';
        }

        $cacheKey = 'hs_homepage_counts_' . $lang;
        $counts = get_transient($cacheKey);

        if ($counts === false) {
            $types = ['helmet', 'brand', 'accessory', 'motorcycle', 'dealer'];
            $counts = [];
            foreach ($types as $type) {
                $args = [
                    'post_type'      => $type,
                    'post_status'    => 'publish',
                    'posts_per_page' => 1,
                    'fields'         => 'ids',
                    'no_found_rows'  => false,
                ];
                
                if (function_exists('pll_current_language')) {
                    $args['tax_query'] = [
                        [
                            'taxonomy' => 'language',
                            'field'    => 'slug',
                            'terms'    => $lang,
                        ]
                    ];
                }
                
                $query = new WP_Query($args);
                $counts[$type] = $query->found_posts;
            }
            set_transient($cacheKey, $counts, 12 * HOUR_IN_SECONDS);
        }

        return new WP_REST_Response($counts, 200);
    }

    /**
     * Retrieve cached exchange rates.
     */
    public function get_rates(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        if (!function_exists('helmetsan_core')) {
            return new WP_Error('rest_error', 'Plugin is unavailable', ['status' => 500]);
        }

        $rates = helmetsan_core()->exchangeRates()->getRates();
        $country = helmetsan_core()->geo()->getCountry();
        $response = new WP_REST_Response([
            'rates' => $rates,
            'detected_country' => $country
        ], 200);

        // Prevent Cloudflare edge / reverse-proxy caching from serving one visitor's detected country to all users
        $response->header('Cache-Control', 'private, no-cache, no-store, must-revalidate');
        $response->header('Vary', 'CF-IPCountry, Accept-Encoding');

        return $response;
    }
}
