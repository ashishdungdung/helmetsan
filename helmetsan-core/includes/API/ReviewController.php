<?php

declare(strict_types=1);

namespace Helmetsan\Core\API;

use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use Helmetsan\Core\Cloudflare\TurnstileService;
use Helmetsan\Core\Reviews\ReviewService;

final class ReviewController
{
    private const NAMESPACE = 'hs/v1';
    private const REST_BASE = 'reviews';

    public function __construct(
        private readonly TurnstileService $turnstileService,
        private readonly ReviewService $reviewService
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
                'permission_callback' => '__return_true',
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/' . self::REST_BASE . '/(?P<product_id>[\d]+)', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'get_reviews'],
                'permission_callback' => '__return_true',
            ],
        ]);

        register_rest_route(self::NAMESPACE, '/' . self::REST_BASE . '/vote', [
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'vote_review'],
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

        if ($this->reviewService->isD1Enabled()) {
            $data = [
                'post_id'                 => $productId,
                'name'                    => $name,
                'email'                   => $email,
                'content'                 => $content,
                'rating'                  => $rating,
                'pros'                    => $pros,
                'cons'                    => $cons,
                'user_id'                 => get_current_user_id(),
                'country_code'            => helmetsan_core()->geo()->getCountry(),
                'cf_turnstile_response'   => $turnstileToken,
            ];
            $res = $this->reviewService->submitD1Review($data);
            if (is_wp_error($res)) {
                return $this->errorResponse('Failed to insert D1 review: ' . $res->get_error_message(), 500);
            }
            return new WP_REST_Response([
                'success'   => true,
                'message'   => 'Review submitted successfully.',
                'review_id' => $res,
            ], 201);
        }

        $reviewId = $this->reviewService->submitReview([
            'post_id'      => $productId,
            'name'         => $name,
            'email'        => $email,
            'content'      => $content,
            'rating'       => $rating,
            'pros'         => $pros,
            'cons'         => $cons,
            'user_id'      => get_current_user_id(),
            'country_code' => helmetsan_core()->geo()->getCountry(),
        ]);

        if (!$reviewId) {
            return $this->errorResponse('Failed to insert review.', 500);
        }

        return new WP_REST_Response([
            'success'   => true,
            'message'   => 'Review submitted successfully.',
            'review_id' => $reviewId,
        ], 201);
    }

    public function get_reviews(WP_REST_Request $request): WP_REST_Response
    {
        $productId = (int) $request['product_id'];
        $page      = (int) $request->get_param('page') ?: 1;
        $perPage   = (int) $request->get_param('per_page') ?: 10;
        $sort      = $request->get_param('sort') ?: 'newest';

        if ($productId <= 0) {
            return $this->errorResponse('Invalid product ID.', 400);
        }

        if ($this->reviewService->isD1Enabled()) {
            $res = $this->reviewService->getD1Reviews($productId, $perPage, ($page - 1) * $perPage, $sort);
            if (!is_wp_error($res)) {
                $normalizedReviews = array_map(function ($row) {
                    $row['id'] = (int) $row['id'];
                    $row['post_id'] = (int) $row['product_id'];
                    $row['user_id'] = (int) ($row['user_id'] ?? 0);
                    $row['rating'] = (int) $row['rating'];
                    $row['helpful_votes'] = (int) ($row['helpful_votes'] ?? 0);
                    $row['unhelpful_votes'] = (int) ($row['unhelpful_votes'] ?? 0);
                    $row['pros'] = isset($row['pros']) ? (is_array($row['pros']) ? $row['pros'] : json_decode($row['pros'], true)) : [];
                    $row['cons'] = isset($row['cons']) ? (is_array($row['cons']) ? $row['cons'] : json_decode($row['cons'], true)) : [];
                    $row['author'] = $row['author_name'] ?: __('Anonymous', 'helmetsan-core');
                    $row['date'] = date_i18n(get_option('date_format'), strtotime($row['created_at']));
                    $row['country_flag'] = !empty($row['country_code']) ? helmetsan_get_country_flag($row['country_code']) : '';
                    return $row;
                }, $res['reviews']);

                return new WP_REST_Response([
                    'success'       => true,
                    'product_id'    => $productId,
                    'total_pages'   => (int) ($res['total_pages'] ?? 1),
                    'current_page'  => $page,
                    'reviews_count' => count($normalizedReviews),
                    'reviews'       => $normalizedReviews,
                ], 200);
            }
        }

        $reviews = $this->reviewService->getReviews($productId, $perPage, ($page - 1) * $perPage, $sort);
        
        // Count total for pagination
        global $wpdb;
        $tableReviews = $wpdb->prefix . 'helmetsan_reviews';
        $totalCount = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$tableReviews} WHERE post_id = %d AND status = 'approved'",
            $productId
        ));
        
        return new WP_REST_Response([
            'success'       => true,
            'product_id'    => $productId,
            'total_pages'   => ceil($totalCount / $perPage),
            'current_page'  => $page,
            'reviews_count' => count($reviews),
            'reviews'       => $reviews,
        ], 200);
    }

    public function vote_review(WP_REST_Request $request): WP_REST_Response
    {
        $payload = $request->get_json_params();
        if (!is_array($payload)) {
            return $this->errorResponse('Invalid payload format', 400);
        }

        $reviewId = isset($payload['review_id']) ? (int) $payload['review_id'] : 0;
        $vote     = isset($payload['vote']) ? sanitize_text_field($payload['vote']) : '';
        $clientIp = $request->get_header('x_forwarded_for') ?: $request->get_header('remote_addr') ?: $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

        if ($reviewId <= 0 || !in_array($vote, ['helpful', 'unhelpful'], true)) {
            return $this->errorResponse('Missing or invalid review ID or vote type.', 400);
        }

        if ($this->reviewService->isD1Enabled()) {
            $res = $this->reviewService->voteD1($reviewId, $vote, $clientIp, get_current_user_id());
            if (is_wp_error($res)) {
                return $this->errorResponse('Vote failed: ' . $res->get_error_message(), 400);
            }
            return new WP_REST_Response([
                'success'         => true,
                'message'         => 'Vote recorded.',
                'helpful_votes'   => (int) ($res['helpful_votes'] ?? 0),
                'unhelpful_votes' => (int) ($res['unhelpful_votes'] ?? 0),
            ], 200);
        }

        $success = $this->reviewService->vote($reviewId, $vote, $clientIp, get_current_user_id());

        if (!$success) {
            return $this->errorResponse('Already voted or invalid review.', 400);
        }

        global $wpdb;
        $tableReviews = $wpdb->prefix . 'helmetsan_reviews';
        $counts = $wpdb->get_row($wpdb->prepare(
            "SELECT helpful_votes, unhelpful_votes FROM {$tableReviews} WHERE id = %d",
            $reviewId
        ), ARRAY_A);

        return new WP_REST_Response([
            'success'         => true,
            'message'         => 'Vote recorded.',
            'helpful_votes'   => (int) ($counts['helpful_votes'] ?? 0),
            'unhelpful_votes' => (int) ($counts['unhelpful_votes'] ?? 0),
        ], 200);
    }
}
