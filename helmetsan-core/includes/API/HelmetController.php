<?php

declare(strict_types=1);

namespace Helmetsan\Core\API;

use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WP_Error;

/**
 * REST API Controller for Helmet data.
 */
class HelmetController extends WP_REST_Controller
{
    protected $namespace = 'helmetsan/v1';
    protected $rest_base = 'helmets';

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
        register_rest_route($this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)/tech-profile', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'get_tech_profile'],
                'permission_callback' => '__return_true',
                'args'                => [
                    'id' => [
                        'description' => 'Helmet Post ID',
                        'type'        => 'integer',
                    ],
                ],
            ],
        ]);
    }

    /**
     * Get the unified technical profile for a helmet.
     */
    public function get_tech_profile(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $id = (int) $request['id'];

        if (get_post_type($id) !== 'helmet') {
            return new WP_Error('rest_not_found', 'Helmet not found', ['status' => 404]);
        }

        // Use the unified helper (ensure theme functions are available or duplicate logic if necessary)
        // Since this is in the plugin, we should ideally have the logic in a service, but for now 
        // we'll use the template tag if available, or call the logic directly.
        if (!function_exists('helmetsan_get_technical_profile')) {
             return new WP_Error('rest_error', 'Technical profile service unavailable', ['status' => 500]);
        }

        $profile = helmetsan_get_technical_profile($id);

        return new WP_REST_Response($profile, 200);
    }
}
