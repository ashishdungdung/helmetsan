<?php

declare(strict_types=1);

namespace Helmetsan\Core\Reviews;

/**
 * High-performance review service that uses isolated custom tables
 * for storing reviews and ratings, offloading standard WP comment tables.
 */
final class ReviewService
{
    private string $tableReviews;
    private string $tableVotes;

    public function __construct()
    {
        global $wpdb;
        $this->tableReviews = $wpdb->prefix . 'helmetsan_reviews';
        $this->tableVotes   = $wpdb->prefix . 'helmetsan_review_votes';
    }

    /**
     * Get reviews for a specific product.
     *
     * @param int    $postId
     * @param int    $limit
     * @param int    $offset
     * @param string $sort newest|helpful
     * @return array<int, array<string, mixed>>
     */
    public function getReviews(int $postId, int $limit = 10, int $offset = 0, string $sort = 'newest'): array
    {
        global $wpdb;

        $orderby = 'created_at';
        if ($sort === 'helpful') {
            $orderby = 'helpful_votes';
        }

        $sql = $wpdb->prepare(
            "SELECT * FROM {$this->tableReviews} 
             WHERE post_id = %d AND status = 'approved' 
             ORDER BY {$orderby} DESC 
             LIMIT %d OFFSET %d",
            $postId,
            $limit,
            $offset
        );

        $results = $wpdb->get_results($sql, ARRAY_A);
        if (!is_array($results)) {
            return [];
        }

        return array_map(function ($row) {
            $row['id'] = (int) $row['id'];
            $row['post_id'] = (int) $row['post_id'];
            $row['user_id'] = (int) $row['user_id'];
            $row['rating'] = (int) $row['rating'];
            $row['helpful_votes'] = (int) $row['helpful_votes'];
            $row['unhelpful_votes'] = (int) $row['unhelpful_votes'];
            $row['pros'] = $row['pros'] ? json_decode($row['pros'], true) : [];
            $row['cons'] = $row['cons'] ? json_decode($row['cons'], true) : [];
            $row['author'] = $row['author_name'] ?: __('Anonymous', 'helmetsan-core');
            $row['date'] = date_i18n(get_option('date_format'), strtotime($row['created_at']));
            $row['country_flag'] = (!empty($row['country_code']) && function_exists('helmetsan_get_country_flag')) ? helmetsan_get_country_flag($row['country_code']) : '';
            return $row;
        }, $results);
    }

    /**
     * Submit a new review.
     */
    public function submitReview(array $data): int
    {
        global $wpdb;

        $inserted = $wpdb->insert(
            $this->tableReviews,
            [
                'post_id'      => (int) ($data['post_id'] ?? 0),
                'user_id'      => (int) ($data['user_id'] ?? 0),
                'author_name'  => sanitize_text_field($data['name'] ?? ''),
                'author_email' => sanitize_email($data['email'] ?? ''),
                'rating'       => (int) ($data['rating'] ?? 0),
                'content'      => wp_kses_post($data['content'] ?? ''),
                'pros'         => !empty($data['pros']) ? json_encode($data['pros']) : null,
                'cons'         => !empty($data['cons']) ? json_encode($data['cons']) : null,
                'status'       => 'approved', // Auto-approve for now as per user preference for fast loads/engagement
                'country_code' => sanitize_text_field($data['country_code'] ?? ''),
                'created_at'   => current_time('mysql'),
                'updated_at'   => current_time('mysql'),
            ]
        );

        if (!$inserted) {
            return 0;
        }

        $reviewId = (int) $wpdb->insert_id;
        $this->syncAggregates((int) $data['post_id']);

        return $reviewId;
    }

    /**
     * Vote on a review.
     */
    public function vote(int $reviewId, string $type, string $ip, int $userId = 0): bool
    {
        global $wpdb;

        // Check for existing vote
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$this->tableVotes} 
             WHERE review_id = %d AND (ip_address = %s OR (user_id > 0 AND user_id = %d))",
            $reviewId,
            $ip,
            $userId
        ));

        if ($existing) {
            return false; // Already voted
        }

        $wpdb->insert(
            $this->tableVotes,
            [
                'review_id'  => $reviewId,
                'user_id'    => $userId,
                'ip_address' => $ip,
                'vote_type'  => $type,
                'created_at' => current_time('mysql'),
            ]
        );

        // Update counts in reviews table
        $column = $type === 'helpful' ? 'helpful_votes' : 'unhelpful_votes';
        $wpdb->query($wpdb->prepare(
            "UPDATE {$this->tableReviews} SET {$column} = {$column} + 1 WHERE id = %d",
            $reviewId
        ));

        return true;
    }

    /**
     * Sync aggregate rating and count to post meta and index table.
     */
    public function syncAggregates(int $postId): void
    {
        global $wpdb;

        $stats = $wpdb->get_row($wpdb->prepare(
            "SELECT AVG(rating) as avg_rating, COUNT(*) as review_count 
             FROM {$this->tableReviews} 
             WHERE post_id = %d AND status = 'approved'",
            $postId
        ));

        $distributionData = $wpdb->get_results($wpdb->prepare(
            "SELECT rating, COUNT(*) as count 
             FROM {$this->tableReviews} 
             WHERE post_id = %d AND status = 'approved' 
             GROUP BY rating",
            $postId
        ));

        $avgRating = (float) ($stats->avg_rating ?? 0);
        $count     = (int) ($stats->review_count ?? 0);
        $ratingCounts = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];

        foreach ($distributionData as $row) {
            $r = (int) $row->rating;
            if ($r >= 1 && $r <= 5) {
                $ratingCounts[$r] = (int) $row->count;
            }
        }

        // Update standard WC meta keys for compatibility
        update_post_meta($postId, '_wc_average_rating', $avgRating);
        update_post_meta($postId, '_wc_review_count', $count);
        update_post_meta($postId, '_wc_rating_count', $ratingCounts);

        // Update our custom index table if it exists
        $indexTable = $wpdb->prefix . 'helmetsan_product_index';
        $wpdb->update(
            $indexTable,
            ['user_rating' => $avgRating, 'review_count' => $count, 'updated_at' => current_time('mysql')],
            ['post_id' => $postId]
        );
    }

    public function isD1Enabled(): bool
    {
        $settings = get_option(\Helmetsan\Core\Support\Config::OPTION_CLOUDFLARE, []);
        return !empty($settings['enable_d1_reviews']) && !empty($settings['d1_reviews_worker_url']);
    }

    public function getD1WorkerUrl(): string
    {
        $settings = get_option(\Helmetsan\Core\Support\Config::OPTION_CLOUDFLARE, []);
        return rtrim((string)$settings['d1_reviews_worker_url'], '/');
    }

    private function getAuthHeaderValue(): string
    {
        $settings = get_option(\Helmetsan\Core\Support\Config::OPTION_CLOUDFLARE, []);
        $secret = defined('HELMETSAN_WEBHOOK_SECRET') ? HELMETSAN_WEBHOOK_SECRET : ($settings['cf_webhook_secret'] ?? '');
        return $secret !== '' ? 'Bearer ' . $secret : '';
    }

    /**
     * Fetch reviews from D1 worker.
     */
    public function getD1Reviews(int $postId, int $limit = 10, int $offset = 0, string $sort = 'newest'): array|\WP_Error
    {
        $url = add_query_arg([
            'product_id' => $postId,
            'limit'      => $limit,
            'offset'     => $offset,
            'sort'       => $sort
        ], $this->getD1WorkerUrl() . '/api/reviews');

        $headers = [];
        $auth = $this->getAuthHeaderValue();
        if ($auth !== '') {
            $headers['Authorization'] = $auth;
        }

        $response = wp_remote_get($url, [
            'headers' => $headers,
            'timeout' => 10,
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if ($code !== 200 || empty($data['success'])) {
            return new \WP_Error('d1_fetch_failed', $data['error'] ?? 'HTTP error ' . $code);
        }

        return $data;
    }

    /**
     * Submit review to D1 worker.
     */
    public function submitD1Review(array $data): int|\WP_Error
    {
        $url = $this->getD1WorkerUrl() . '/api/reviews';
        $headers = ['Content-Type' => 'application/json'];
        $auth = $this->getAuthHeaderValue();
        if ($auth !== '') {
            $headers['Authorization'] = $auth;
        }

        $body = [
            'product_id'   => (int) ($data['post_id'] ?? 0),
            'user_id'      => (int) ($data['user_id'] ?? 0),
            'author_name'  => sanitize_text_field($data['name'] ?? ''),
            'author_email' => sanitize_email($data['email'] ?? ''),
            'rating'       => (int) ($data['rating'] ?? 0),
            'content'      => wp_kses_post($data['content'] ?? ''),
            'pros'         => !empty($data['pros']) ? $data['pros'] : [],
            'cons'         => !empty($data['cons']) ? $data['cons'] : [],
            'country_code' => sanitize_text_field($data['country_code'] ?? ''),
            'turnstile_token' => $data['cf_turnstile_response'] ?? '',
        ];

        $response = wp_remote_post($url, [
            'headers' => $headers,
            'body'    => wp_json_encode($body),
            'timeout' => 10,
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        $resBody = wp_remote_retrieve_body($response);
        $res = json_decode($resBody, true);

        if ($code !== 201 || empty($res['success'])) {
            return new \WP_Error('d1_submit_failed', $res['error'] ?? 'HTTP error ' . $code);
        }

        if (isset($res['aggregates'])) {
            $this->syncD1Aggregates((int) $data['post_id'], $res['aggregates']);
        }

        return (int) ($res['review_id'] ?? 1);
    }

    /**
     * Vote on D1 review.
     */
    public function voteD1(int $reviewId, string $type, string $ip, int $userId = 0): array|\WP_Error
    {
        $url = $this->getD1WorkerUrl() . '/api/reviews/vote';
        $headers = ['Content-Type' => 'application/json'];
        $auth = $this->getAuthHeaderValue();
        if ($auth !== '') {
            $headers['Authorization'] = $auth;
        }

        $body = [
            'review_id' => $reviewId,
            'vote_type' => $type,
            'user_id'   => $userId,
            'ip'        => $ip
        ];

        $response = wp_remote_post($url, [
            'headers' => $headers,
            'body'    => wp_json_encode($body),
            'timeout' => 10,
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        $resBody = wp_remote_retrieve_body($response);
        $res = json_decode($resBody, true);

        if ($code !== 200 || empty($res['success'])) {
            return new \WP_Error('d1_vote_failed', $res['error'] ?? 'HTTP error ' . $code);
        }

        return $res;
    }

    /**
     * Sync aggregate rating and counts returned from D1 to local metadata.
     */
    public function syncD1Aggregates(int $postId, array $aggregates): void
    {
        $avgRating = (float) ($aggregates['avg_rating'] ?? 0);
        $count     = (int) ($aggregates['review_count'] ?? 0);
        $ratingCounts = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];

        if (isset($aggregates['rating_counts'])) {
            foreach ($aggregates['rating_counts'] as $r => $cnt) {
                $r = (int) $r;
                if ($r >= 1 && $r <= 5) {
                    $ratingCounts[$r] = (int) $cnt;
                }
            }
        }

        // Update standard WC meta keys for compatibility
        update_post_meta($postId, '_wc_average_rating', $avgRating);
        update_post_meta($postId, '_wc_review_count', $count);
        update_post_meta($postId, '_wc_rating_count', $ratingCounts);

        // Update our custom index table if it exists
        global $wpdb;
        $indexTable = $wpdb->prefix . 'helmetsan_product_index';
        $wpdb->update(
            $indexTable,
            ['user_rating' => $avgRating, 'review_count' => $count, 'updated_at' => current_time('mysql')],
            ['post_id' => $postId]
        );
    }
}

