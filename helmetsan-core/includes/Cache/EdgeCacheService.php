<?php

declare(strict_types=1);

namespace Helmetsan\Core\Cache;

/**
 * Centralized Edge & Microcache HTTP Header Service.
 *
 * Emits uniform, high-performance cache-control headers across public,
 * canonical requests while strictly bypassing authenticated sessions,
 * administrative dashboards, and transactional mutations.
 */
final class EdgeCacheService
{
    /**
     * Edge and CDN TTL: 24 hours (86,400 seconds).
     */
    private const EDGE_MAX_AGE = 86400;

    /**
     * Browser / Client TTL: 1 hour (3,600 seconds).
     */
    private const CLIENT_MAX_AGE = 3600;

    /**
     * Stale-while-revalidate window: 10 minutes (600 seconds).
     */
    private const STALE_WHILE_REVALIDATE = 600;

    public function register(): void
    {
        add_action('template_redirect', [$this, 'emitEdgeHeaders'], 1);
        add_action('send_headers', [$this, 'emitEdgeHeaders'], 1);
    }

    /**
     * Emit Cache-Control and Edge headers if request is publicly cacheable.
     */
    public function emitEdgeHeaders(): void
    {
        if (headers_sent()) {
            return;
        }

        // 1. Never cache admin, CLI, or cron
        if (is_admin() || (defined('WP_CLI') && WP_CLI) || (function_exists('wp_doing_cron') && wp_doing_cron())) {
            return;
        }

        // 2. Never cache logged-in users or comment authors
        if (is_user_logged_in()) {
            return;
        }

        // 3. Only cache idempotent read methods
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if ($method !== 'GET' && $method !== 'HEAD') {
            return;
        }

        // 4. Bypass REST API write requests
        if (defined('REST_REQUEST') && REST_REQUEST) {
            return;
        }

        // 5. Bypass WooCommerce transactional pages if active
        if (function_exists('is_cart') && is_cart()) {
            return;
        }
        if (function_exists('is_checkout') && is_checkout()) {
            return;
        }
        if (function_exists('is_account_page') && is_account_page()) {
            return;
        }

        // 6. Bypass queries containing transactional actions or search keywords
        if (!empty($_GET['add-to-cart']) || !empty($_GET['action']) || !empty($_GET['hs_lead_status'])) {
            return;
        }

        // 7. Check if current query is a canonical public page, archive, or single post
        $isPublicContent = is_singular() 
            || is_archive() 
            || is_front_page() 
            || is_home() 
            || is_page() 
            || is_search()
            || is_post_type_archive();

        if ($isPublicContent) {
            $headerValue = sprintf(
                'public, max-age=%d, s-maxage=%d, stale-while-revalidate=%d',
                self::CLIENT_MAX_AGE,
                self::EDGE_MAX_AGE,
                self::STALE_WHILE_REVALIDATE
            );

            header('Cache-Control: ' . $headerValue);
            header('X-Helmetsan-Edge-Cache: HIT-ELIGIBLE');

            // Cloudflare 103 Early Hints: preload primary stylesheet
            if (function_exists('get_stylesheet_directory_uri')) {
                $themeUri = get_stylesheet_directory_uri();
                header('Link: <' . esc_url_raw($themeUri . '/assets/css/helmetsan-bundle.min.css') . '>; rel=preload; as=style', false);
            }

            // Cloudflare Enterprise & Worker Cache-Tag Headers
            $tags = ['helmetsan', 'public'];
            if (function_exists('is_singular') && is_singular('helmet')) {
                $tags[] = 'helmet';
                $id = function_exists('get_the_ID') ? get_the_ID() : 0;
                if ($id) {
                    $tags[] = 'helmet-' . $id;
                    if (function_exists('get_the_terms')) {
                        $terms = get_the_terms($id, 'helmet_brand');
                        if (is_array($terms) && !empty($terms)) {
                            $tags[] = 'brand-' . $terms[0]->slug;
                        }
                    }
                }
            } elseif (function_exists('is_post_type_archive') && is_post_type_archive('helmet')) {
                $tags[] = 'catalog';
                $tags[] = 'helmets';
            } elseif (function_exists('is_page') && (is_page('compare') || is_page('comparison'))) {
                $tags[] = 'comparison';
            } elseif (function_exists('is_front_page') && is_front_page()) {
                $tags[] = 'home';
            }
            header('Cache-Tag: ' . implode(',', $tags));
        }
    }
}
