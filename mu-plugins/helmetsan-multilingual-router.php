<?php
/**
 * Plugin Name: Helmetsan Multilingual Slug, Taxonomy & Language Resolver
 * Description: Guarantees that single posts, taxonomy archives (including use-case, safety-standard), and post type archives (brands, motorcycles) resolve seamlessly across all supported languages without 404 errors or Polylang redirect loops.
 * Version: 1.1.0
 * Author: Helmetsan Engineering
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

/**
 * 0. Canonical Redirect for Untranslated Custom Taxonomies
 * Harmonizes with Polylang's canonical redirect policy by redirecting non-default
 * language taxonomy URLs (e.g. /pl/use-case/commuting/) to canonical English URLs.
 */
add_action('template_redirect', function(): void {
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    $path = (string) wp_parse_url($uri, PHP_URL_PATH);

    $supportedLangs = 'de|zh|fr|es|it|pl|pt|nl|ja';
    if (preg_match("#^/({$supportedLangs})/use-case/(.*)$#i", $path, $matches)) {
        $canonicalPath = '/use-case/' . $matches[2];
        wp_safe_redirect(home_url($canonicalPath), 301);
        exit;
    }
}, 1);

/**
 * 1. Register Multilingual Rewrite Rules for Custom Taxonomies
 */
add_action('init', function(): void {
    $langs = 'de|zh|fr|es|it|pl|pt|nl|ja';
    $customTaxonomies = [
        'use-case'           => 'use_case',
        'motorcycle-segment' => 'motorcycle_segment',
        'motorcycle-make'    => 'motorcycle_make',
        'price-range'        => 'price_range',
    ];

    foreach ($customTaxonomies as $slug => $taxonomy) {
        add_rewrite_rule(
            "^({$langs})/{$slug}/([^/]+)/page/?([0-9]{1,})/?$",
            'index.php?lang=$matches[1]&' . $taxonomy . '=$matches[2]&paged=$matches[3]',
            'top'
        );
        add_rewrite_rule(
            "^({$langs})/{$slug}/([^/]+)/?$",
            'index.php?lang=$matches[1]&' . $taxonomy . '=$matches[2]',
            'top'
        );
    }
}, 5);

/**
 * 2. Main Query Pre-Get-Posts Guard
 * Prevents 404s on post types and taxonomies with global/untranslated entities.
 */
add_action('pre_get_posts', function(WP_Query $query): void {
    if (is_admin() || ! $query->is_main_query()) {
        return;
    }

    // A. Global Brand & Motorcycle Archives
    // All 81 brands and 3,247 motorcycles are canonical global entities.
    // Prevent Polylang from filtering by language and causing 404 on page 2+ in non-EN languages.
    if ($query->is_post_type_archive(['brand', 'motorcycle'])) {
        $query->set('lang', '');
        return;
    }

    // B. Custom Taxonomy Archives (use_case, etc.)
    // Ensure post_type includes 'helmet' and 'accessory' instead of defaulting to standard 'post'.
    if ($query->is_tax('use_case')) {
        $query->set('post_type', ['helmet', 'accessory']);
    } elseif ($query->is_tax(['motorcycle_segment', 'motorcycle_make'])) {
        $query->set('post_type', 'motorcycle');
        $query->set('lang', '');
    }

    // C. Localized Standard Pages (about, contact, privacy-policy, etc.)
    // Directly targets the localized page ID to prevent false Polylang canonical redirects back to English.
    $pagename = $query->get('pagename');
    if (! empty($pagename)) {
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        $path = trim((string) wp_parse_url($uri, PHP_URL_PATH), '/');
        $parts = explode('/', $path);
        $supported = ['de', 'zh', 'fr', 'es', 'it', 'pl', 'pt', 'nl', 'ja'];
        $targetLang = 'en';
        if (! empty($parts) && in_array(strtolower($parts[0]), $supported, true)) {
            $targetLang = strtolower($parts[0]);
        }

        global $wpdb;
        $foundId = $wpdb->get_var($wpdb->prepare(
            "SELECT p.ID FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id
             INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
             INNER JOIN {$wpdb->terms} t ON tt.term_id = t.term_id
             WHERE p.post_name = %s AND p.post_type = 'page' AND p.post_status = 'publish' AND tt.taxonomy = 'language' AND t.slug = %s
             ORDER BY p.ID ASC LIMIT 1",
            basename($pagename), $targetLang
        ));
        if ($foundId) {
            $query->set('page_id', (int) $foundId);
            $query->set('pagename', '');
        }
    }
}, 1);

/**
 * 3. Fallback Post Results Guard for Taxonomies & Single Entities
 */
add_filter('posts_results', function(array $posts, WP_Query $query): array {
    if (is_admin() || ! $query->is_main_query()) {
        return $posts;
    }

    // A. Taxonomy empty result fallback
    // If a localized taxonomy query yields 0 posts (e.g. term exists but no translation yet in that lang),
    // fallback to English to prevent empty/404 screens.
    if (empty($posts) && $query->is_tax(['use_case', 'certification', 'feature_tag'])) {
        $fallbackQuery = clone $query;
        $fallbackQuery->set('lang', '');
        $fallbackPosts = $fallbackQuery->get_posts();
        if (! empty($fallbackPosts)) {
            return $fallbackPosts;
        }
    }

    // B. Single post & page language matching
    if (empty($posts) || (! $query->is_single() && ! $query->is_page())) {
        return $posts;
    }

    $postType = $query->get('post_type') ?: ($query->is_page() ? 'page' : 'post');
    if (! in_array($postType, ['helmet', 'brand', 'accessory', 'motorcycle', 'page'], true)) {
        return $posts;
    }

    $uri = $_SERVER['REQUEST_URI'] ?? '';
    $path = trim((string) wp_parse_url($uri, PHP_URL_PATH), '/');
    $segments = explode('/', $path);
    $supportedLangs = ['de', 'zh', 'fr', 'es', 'it', 'pl', 'pt', 'nl', 'ja'];
    $targetLang = 'en';
    if (! empty($segments) && in_array(strtolower($segments[0]), $supportedLangs, true)) {
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

/**
 * 4. Multi-language Unique Post Slug Resolver
 * Allows clean, identical slugs (e.g. /de/about/, /fr/about/, /nl/about/) across languages.
 */
add_filter('wp_unique_post_slug', function(string $slug, int $post_id, string $post_status, string $post_type, int $post_parent, string $original_slug): string {
    if (! in_array($post_type, ['page', 'helmet', 'accessory', 'motorcycle', 'brand', 'post', 'safety_standard'], true)) {
        return $slug;
    }
    if (! function_exists('pll_get_post_language')) {
        return $slug;
    }
    $lang = pll_get_post_language($post_id);
    if (! $lang) {
        return $slug;
    }
    global $wpdb;
    $existing = $wpdb->get_var($wpdb->prepare(
        "SELECT p.ID FROM {$wpdb->posts} p
         INNER JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id
         INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
         INNER JOIN {$wpdb->terms} t ON tt.term_id = t.term_id
         WHERE p.post_name = %s AND p.post_type = %s AND p.ID != %d AND tt.taxonomy = 'language' AND t.slug = %s
         LIMIT 1",
        $original_slug, $post_type, $post_id, $lang
    ));
    if (! $existing) {
        return $original_slug;
    }
    return $slug;
}, 10, 6);

/**
 * 5. Prevent False Canonical Redirects on Localized Pages & Posts
 * If a localized URL (e.g. /de/contact/, /fr/about/, /ja/legal/) is requested,
 * never allow Polylang to strip the language prefix and redirect back to English.
 */
add_filter('pll_check_canonical_url', function($redirect_url, $language) {
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    $path = trim((string) wp_parse_url($uri, PHP_URL_PATH), '/');
    $parts = explode('/', $path);
    $supported = ['de', 'zh', 'fr', 'es', 'it', 'pl', 'pt', 'nl', 'ja'];
    if (! empty($parts) && in_array(strtolower($parts[0]), $supported, true)) {
        return false;
    }
    return $redirect_url;
}, 999, 2);


