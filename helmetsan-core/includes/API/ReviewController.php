<?php

declare(strict_types=1);

namespace Helmetsan\Core\API;

use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use Helmetsan\Core\Cloudflare\TurnstileService;

final class ReviewController
{
    private const NAMESPACE = 'hs/v1';
    private const REST_BASE = 'reviews';

    public function __construct(
        private readonly TurnstileService $turnstileService
    ) {}

    public function register(): void
    {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public function register_routes(): void
    {
        register_rest_route(self::NAMESPACE, '/' . self::REST_BASE . '/submit', [
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'submit_review'],
                'permission_callback' => '__return_true', // Open to public, but we should add nonce or Turnstile later
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/' . self::REST_BASE . '/(?P<product_id>[\d]+)', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'get_reviews'],
                'permission_callback' => '__return_true',
            ],
        ]);
    }

    private function errorResponse(string $message, int $code = 400): WP_REST_Response
    {
        return new WP_REST_Response(['error' => true, 'message' => $message], $code);
    }

    public function submit_review(WP_REST_Request $request): WP_REST_Response
    {
        $payload = $request->get_json_params();
        if (!is_array($payload)) {
            return $this->errorResponse('Invalid payload format', 400);
        }

        $turnstileToken = isset($payload['cf_turnstile_response']) ? (string) $payload['cf_turnstile_response'] : '';
        $clientIp = $request->get_header('x_forwarded_for') ?: $request->get_header('remote_addr') ?: $_SERVER['REMOTE_ADDR'] ?? null;

        if (!$this->turnstileService->verify($turnstileToken, $clientIp)) {
            return $this->errorResponse('Turnstile verification failed. Please try again.', 403);
        }

        $productId = isset($payload['product_id']) ? (int) $payload['product_id'] : 0;
        $rating = isset($payload['rating']) ? (int) $payload['rating'] : 0;
        $name = isset($payload['name']) ? sanitize_text_field($payload['name']) : '';
        $email = isset($payload['email']) ? sanitize_email($payload['email']) : '';
        $content = isset($payload['content']) ? wp_kses_post($payload['content']) : '';
        
        $pros = isset($payload['pros']) && is_array($payload['pros']) ? array_map('sanitize_text_field', $payload['pros']) : [];
        $cons = isset($payload['cons']) && is_array($payload['cons']) ? array_map('sanitize_text_field', $payload['cons']) : [];

        if ($productId <= 0 || $rating < 1 || $rating > 5 || empty($name)) {
            return $this->errorResponse('Missing or invalid required fields (product_id, rating 1-5, name).', 400);
        }

        // Create the Review post
        $postData = [
            'post_title'   => sprintf('Review for Product #%d by %s', $productId, $name),
            'post_content' => $content,
            'post_type'    => 'review',
            'post_status'  => 'pending', // Requires admin approval
            'post_author'  => 0,
        ];

        $reviewId = wp_insert_post($postData, true);

        if (is_wp_error($reviewId) || $reviewId === 0) {
            return $this->errorResponse('Failed to insert review.', 500);
        }

        // Add Meta Fields
        update_post_meta($reviewId, 'review_rating', $rating);
        update_post_meta($reviewId, 'review_author_name', $name);
        if ($email !== '') {
            update_post_meta($reviewId, 'review_author_email', $email);
        }
        update_post_meta($reviewId, 'rel_product_id', $productId);

        if (!empty($pros)) {
            update_post_meta($reviewId, 'review_pros_json', wp_json_encode($pros));
        }

        if (!empty($cons)) {
            update_post_meta($reviewId, 'review_cons_json', wp_json_encode($cons));
        }

        // Set verified purchase to 0 for now until order logic exists
        update_post_meta($reviewId, 'review_verified_purchase', 0);

        return new WP_REST_Response([
            'success' => true,
            'message' => 'Review submitted successfully and is pending approval.',
            'review_id' => $reviewId,
        ], 201);
    }

    public function get_reviews(WP_REST_Request $request): WP_REST_Response
    {
        $productId = (int) $request['product_id'];

        if ($productId <= 0) {
            return $this->errorResponse('Invalid product ID.', 400);
        }

        $args = [
            'post_type'      => 'review',
            'post_status'    => 'publish',
            'posts_per_page' => 50,
            'meta_query'     => [
                [
                    'key'     => 'rel_product_id',
                    'value'   => $productId,
                    'compare' => '='
                ],
            ],
            'orderby'        => 'date',
            'order'          => 'DESC',
        ];

        $query = new \WP_Query($args);
        $reviews = [];

        foreach ($query->posts as $post) {
            $rating = (int) get_post_meta($post->ID, 'review_rating', true);
            $prosJson = get_post_meta($post->ID, 'review_pros_json', true);
            $consJson = get_post_meta($post->ID, 'review_cons_json', true);

            $reviews[] = [
                'id'         => $post->ID,
                'author'     => get_post_meta($post->ID, 'review_author_name', true),
                'date'       => get_the_date('c', $post->ID),
                'rating'     => $rating,
                'content'    => $post->post_content,
                'pros'       => $prosJson ? json_decode($prosJson, true) : [],
                'cons'       => $consJson ? json_decode($consJson, true) : [],
                'verified'   => (bool) get_post_meta($post->ID, 'review_verified_purchase', true),
            ];
        }

        return new WP_REST_Response([
            'success' => true,
            'product_id' => $productId,
            'reviews_count' => count($reviews),
            'reviews'  => $reviews,
        ], 200);
    }
}
