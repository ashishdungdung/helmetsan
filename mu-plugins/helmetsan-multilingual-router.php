<?php
/**
 * Plugin Name: Helmetsan Multilingual Slug & Language Resolver
 * Description: Guarantees that single helmet, brand, and accessory requests always resolve to the post matching the URL language, preventing cross-language hijacking and Polylang redirect loops.
 * Version: 1.0.0
 * Author: Helmetsan Engineering
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

add_filter('posts_results', function(array $posts, WP_Query $query): array {
    if (is_admin() || empty($posts) || !$query->is_main_query() || !$query->is_single()) {
        return $posts;
    }

    $postType = $query->get('post_type');
    if (!in_array($postType, ['helmet', 'brand', 'accessory'], true)) {
        return $posts;
    }

    $uri = $_SERVER['REQUEST_URI'] ?? '';
    $path = trim((string) wp_parse_url($uri, PHP_URL_PATH), '/');
    $segments = explode('/', $path);
    $supportedLangs = ['de', 'zh', 'fr', 'es', 'it', 'pl', 'pt', 'nl', 'ja'];
    $targetLang = 'en';
    if (!empty($segments) && in_array(strtolower($segments[0]), $supportedLangs, true)) {
        $targetLang = strtolower($segments[0]);
    }

    // Check if the current first post already matches target language
    $firstPost = $posts[0];
    if (function_exists('pll_get_post_language') && pll_get_post_language($firstPost->ID) === $targetLang) {
        return $posts;
    }

    // 1. Look for matching post in returned results
    foreach ($posts as $p) {
        if (function_exists('pll_get_post_language') && pll_get_post_language($p->ID) === $targetLang) {
            return [$p];
        }
    }

    // 2. Query the exact post matching post_name, post_type, and target language
    $name = $query->get('name') ?: ($firstPost->post_name ?? '');
    if ($name !== '') {
        global $wpdb;
        $foundId = $wpdb->get_var($wpdb->prepare(
            "SELECT p.ID FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id
             INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
             INNER JOIN {$wpdb->terms} t ON tt.term_id = t.term_id
             WHERE p.post_name = %s AND p.post_type = %s AND p.post_status = 'publish' AND tt.taxonomy = 'language' AND t.slug = %s
             ORDER BY p.ID ASC LIMIT 1",
            $name, $postType, $targetLang
        ));

        if ($foundId) {
            $matchedPost = get_post((int) $foundId);
            if ($matchedPost instanceof WP_Post) {
                return [$matchedPost];
            }
        }
    }

    return $posts;
}, 1, 2);