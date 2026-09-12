<?php
/**
 * Theme hooks.
 *
 * @package HelmetsanTheme
 */

if (! defined('ABSPATH')) {
    exit;
}

add_filter('body_class', 'helmetsan_theme_body_class');
add_filter('the_content', 'helmetsan_theme_append_about_attribution');
add_filter('the_content', 'helmetsan_theme_helmet_content_from_technical_analysis', 5);
add_filter('the_content', 'helmetsan_theme_iframe_title_attribute_filter', 20);
add_filter('wpseo_metadesc', 'helmetsan_theme_fallback_meta_description');
add_action('wp_head', 'helmetsan_theme_dynamic_layout_css', 30);
add_action('wp_head', 'helmetsan_theme_front_page_lcp_preload', 2);
// Schema is handled dynamically by Helmetsan Core's SchemaService
// add_action('wp_head', 'helmetsan_theme_single_helmet_schema', 5);
// add_action('wp_head', 'helmetsan_theme_global_schema', 1);
add_action('wp_footer', 'helmetsan_theme_aria_live_announcer', 50);
add_filter('document_title_parts', 'helmetsan_theme_helmet_archive_title_fix', 20);
add_filter('wpseo_title', 'helmetsan_theme_yoast_archive_title_fix', 20);
add_filter('get_avatar_url', 'helmetsan_theme_gravatar_mirror', 10, 3);

/**
 * Automatically add title attributes to iframes (YouTube/Vimeo) for accessibility.
 */
function helmetsan_theme_iframe_title_attribute_filter(string $content): string
{
    if (str_contains($content, '<iframe')) {
        $title = get_the_title();
        $replacement = sprintf(' <iframe title="%s" ', esc_attr(sprintf(__('Video content for %s', 'helmetsan-theme'), $title)));
        $content = str_replace('<iframe ', $replacement, $content);
    }
    return $content;
}

/**
 * Add Product-specific OpenGraph tags to enrich Yoast's social previews.
 */
function helmetsan_theme_product_og_tags(): void
{
    if (! is_singular('helmet')) {
        return;
    }

    $helmetId = get_the_ID();
    $price = helmetsan_get_helmet_price($helmetId);
    $priceNum = preg_replace('/[^0-9.]/', '', $price);

    if ($priceNum && $priceNum !== '0.00') {
        echo "\n" . '<!-- Helmetsan Deep SEO: Product OG Tags -->' . "\n";
        echo '<meta property="og:type" content="og:product" />' . "\n";
        echo '<meta property="product:price:amount" content="' . esc_attr((string) $priceNum) . '" />' . "\n";
        echo '<meta property="product:price:currency" content="USD" />' . "\n";
    }
}

/**
 * Inject a visually hidden aria-live region for dynamic content announcements.
 */
function helmetsan_theme_aria_live_announcer(): void
{
    echo '<div id="hs-a11y-announcer" class="screen-reader-text" aria-live="polite" aria-atomic="true"></div>' . "\n";
}

/**
 * Output JSON-LD Product, BreadcrumbList, and Organization Schema for single helmet pages.
 */
function helmetsan_theme_single_helmet_schema(): void
{
    if (! is_singular('helmet')) {
        return;
    }

    $helmetId = get_the_ID();
    $title = get_the_title();
    $brandName = helmetsan_get_brand_name($helmetId);
    $price = helmetsan_get_helmet_price($helmetId);
    $priceNum = is_numeric(preg_replace('/[^0-9.]/', '', $price)) ? (float) preg_replace('/[^0-9.]/', '', $price) : null;
    $certs = helmetsan_get_certifications($helmetId);
    $weight = (int) get_post_meta($helmetId, 'spec_weight_g', true);
    $sku = (string) get_post_meta($helmetId, 'sku', true);
    $description = get_the_excerpt() ?: wp_trim_words(get_the_content(), 30);

    // 1. Product Schema
    $productSchema = [
        '@context' => 'https://schema.org/',
        '@type'    => 'Product',
        'name'     => $title,
        'image'    => [get_the_post_thumbnail_url($helmetId, 'large')],
        'description' => esc_html($description),
        'sku'      => $sku ?: $helmetId,
        'brand'    => [
            '@type' => 'Brand',
            'name'  => $brandName ?: 'Generic',
        ],
        'offers'   => [
            '@type'         => 'Offer',
            'url'           => get_permalink($helmetId),
            'priceCurrency' => 'USD',
            'price'         => $priceNum ?: '0.00',
            'itemCondition' => 'https://schema.org/NewCondition',
            'availability'  => 'https://schema.org/InStock',
            'seller'        => [
                '@type' => 'Organization',
                'name'  => 'Helmetsan',
            ],
        ],
    ];

    $properties = [];
    if ($certs !== '' && $certs !== 'N/A') {
        $properties[] = ['@type' => 'PropertyValue', 'name' => 'Certification', 'value' => $certs];
    }
    if ($weight > 0) {
        $properties[] = ['@type' => 'PropertyValue', 'name' => 'Weight', 'value' => $weight . 'g'];
    }
    if (! empty($properties)) {
        $productSchema['additionalProperty'] = $properties;
    }

    // 2. BreadcrumbList Schema
    $breadcrumbItems = helmetsan_breadcrumb(false);
    $breadcrumbSchema = [
        '@context' => 'https://schema.org',
        '@type'    => 'BreadcrumbList',
        'itemListElement' => [],
    ];

    if (is_array($breadcrumbItems)) {
        foreach ($breadcrumbItems as $i => $item) {
            $breadcrumbSchema['itemListElement'][] = [
                '@type'    => 'ListItem',
                'position' => $i + 1,
                'name'     => $item['name'],
                'item'     => $item['url'],
            ];
        }
    }

    // 3. Organization Schema (Global Brand Authority)
    $orgSchema = [
        '@context' => 'https://schema.org',
        '@type'    => 'Organization',
        'name'     => 'Helmetsan',
        'url'      => home_url('/'),
        'logo'     => get_site_icon_url(),
        'sameAs'   => [
            'https://twitter.com/helmetsan',
            'https://facebook.com/helmetsan',
        ],
    ];

    $allSchema = [$productSchema, $breadcrumbSchema, $orgSchema];

    echo "\n" . '<!-- Helmetsan Deep SEO: Product, Breadcrumb & Org Schema -->' . "\n";
    echo '<script type="application/ld+json">' . wp_json_encode($allSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>' . "\n";
}

/**
 * Preload the LCP (Largest Contentful Paint) image for the Front-Page.
 * Since the front-page uses a featured helmet query, we run the same deterministic
 * query here to inject a preload link.
 */
function helmetsan_theme_front_page_lcp_preload(): void
{
    if (! is_front_page()) {
        return;
    }

    $featuredQuery = new WP_Query([
        'post_type'      => 'helmet',
        'post_parent'    => 0,
        'posts_per_page' => 1,
        'post_status'    => 'publish',
        'orderby'        => 'date', // Match the template's order
        'meta_query'     => [
            [
                'key'     => '_thumbnail_id',
                'compare' => 'EXISTS',
            ],
        ],
    ]);

    if ($featuredQuery->have_posts()) {
        $featuredQuery->the_post();
        $thumbnailId = get_post_thumbnail_id();
        if ($thumbnailId) {
            $imageUrl = wp_get_attachment_image_url($thumbnailId, 'large');
            $srcset = wp_get_attachment_image_srcset($thumbnailId, 'large');
            $sizes = wp_get_attachment_image_sizes($thumbnailId, 'large');

            if ($imageUrl) {
                printf(
                    '<link rel="preload" as="image" href="%s"%s%s fetchpriority="high" />' . "\n",
                    esc_url($imageUrl),
                    $srcset ? ' imagesrcset="' . esc_attr($srcset) . '"' : '',
                    $sizes ? ' imagesizes="' . esc_attr($sizes) . '"' : ''
                );
            }
        }
        wp_reset_postdata();
    }
}

/**
 * @param array<int, string> $classes
 *
 * @return array<int, string>
 */
function helmetsan_theme_body_class(array $classes): array
{
    if (is_singular('helmet')) {
        $classes[] = 'is-helmet-single';
    }

    if (is_post_type_archive('helmet')) {
        $classes[] = 'is-helmet-archive';
    }

    $layout = (string) get_theme_mod('helmetsan_layout_alignment', 'balanced');
    $classes[] = 'hs-layout-' . sanitize_html_class($layout);

    return $classes;
}

/**
 * For helmet singles: when post content is empty, use technical_analysis meta so AI fill-missing populates the About section.
 */
function helmetsan_theme_helmet_content_from_technical_analysis(string $content): string
{
    if (! is_singular('helmet') || ! in_the_loop() || ! is_main_query()) {
        return $content;
    }
    $id = get_the_ID();
    if ($id <= 0) {
        return $content;
    }
    if (trim(strip_tags($content)) !== '') {
        return $content;
    }
    $analysis = get_post_meta($id, 'technical_analysis', true);
    if (! is_string($analysis) || trim($analysis) === '') {
        return $content;
    }
    return wpautop(wp_kses_post($analysis));
}

function helmetsan_theme_append_about_attribution(string $content): string
{
    if (! is_page('about') || ! in_the_loop() || ! is_main_query()) {
        return $content;
    }

    $section = '<section class="hs-panel" aria-label="Attribution"><h2>Attribution</h2><p><a href="https://logo.dev">Logos provided by Logo.dev</a></p></section>';

    return $content . $section;
}

function helmetsan_theme_dynamic_layout_css(): void
{
    $width = absint((string) get_theme_mod('helmetsan_content_max_width', 1200));
    if ($width < 980) {
        $width = 980;
    }
    if ($width > 1600) {
        $width = 1600;
    }
    echo '<style id=\"hs-layout-width\">body{--hs-content-max:' . esc_attr((string) $width) . 'px;}</style>';
}

/**
 * Output global Organization and WebSite (Sitelinks Searchbox) schema.
 */
function helmetsan_theme_global_schema(): void
{
    $schema = [];

    // 1. WebSite SearchAction
    if (is_front_page()) {
        $schema[] = [
            '@context' => 'https://schema.org',
            '@type'    => 'WebSite',
            'url'      => home_url('/'),
            'potentialAction' => [
                '@type'       => 'SearchAction',
                'target'      => home_url('/?s={search_term_string}'),
                'query-input' => 'required name=search_term_string',
            ],
        ];

        // 2. Organization (Global)
        $schema[] = [
            '@context' => 'https://schema.org',
            '@type'    => 'Organization',
            'name'     => 'Helmetsan',
            'url'      => home_url('/'),
            'logo'     => get_site_icon_url(),
            'sameAs'   => [
                'https://twitter.com/helmetsan',
                'https://facebook.com/helmetsan',
            ],
        ];
    }

    if (! empty($schema)) {
        echo "\n" . '<!-- Helmetsan Global SEO: WebSite & Organization Schema -->' . "\n";
        echo '<script type="application/ld+json">' . wp_json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>' . "\n";
    }
}


/**
 * Provide high-quality fallback meta descriptions for archives if Yoast is empty.
 */
function helmetsan_theme_fallback_meta_description(string $desc): string
{
    if (trim($desc) !== '') {
        return $desc;
    }

    if (is_post_type_archive('helmet')) {
        return 'Browse the world\'s most comprehensive motorcycle helmet catalog. Compare technical specifications, safety certifications, and real-time prices for thousands of helmets.';
    }

    if (is_singular('brand')) {
        return sprintf(
            'Explore technical specifications, safety ratings, and price history for %s helmets. Discover the best deals and detailed analysis on Helmetsan.',
            get_the_title()
        );
    }

    return $desc;
}

/**
 * Fix SEO titles on helmet archive to reflect the real page count from filtered query.
 */
function helmetsan_theme_helmet_archive_title_fix(array $title): array
{
    if (is_post_type_archive('helmet') && is_paged()) {
        global $wp_query;
        if (isset($wp_query->max_num_pages) && $wp_query->max_num_pages > 0) {
            $title['page'] = sprintf(__('Page %d of %d', 'helmetsan-theme'), (int) get_query_var('paged'), (int) $wp_query->max_num_pages);
        }
    }
    return $title;
}

/**
 * Fix Yoast SEO titles if active.
 */
function helmetsan_theme_yoast_archive_title_fix(string $title): string
{
    if (is_post_type_archive('helmet') && is_paged()) {
        global $wp_query;
        if (isset($wp_query->max_num_pages) && $wp_query->max_num_pages > 0) {
            $currentPage = (int) get_query_var('paged');
            $totalPages = (int) $wp_query->max_num_pages;
            // Replace "Page X of Y" or just "Page X" in the title string
            $title = preg_replace('/Page \d+( of \d+)?/', "Page $currentPage of $totalPages", $title);
        }
    }
    return $title;
}

/**
 * Proxy Gravatar URLs to a CN-friendly mirror for visitors in China.
 */
function helmetsan_theme_gravatar_mirror(string $url, $id_or_email, array $args): string
{
    if (function_exists('helmetsan_is_china_visitor') && helmetsan_is_china_visitor()) {
        $url = str_replace(
            [
                'https://secure.gravatar.com/avatar/',
                'https://0.gravatar.com/avatar/',
                'https://1.gravatar.com/avatar/',
                'https://2.gravatar.com/avatar/',
                'http://secure.gravatar.com/avatar/',
                'http://0.gravatar.com/avatar/',
                'http://1.gravatar.com/avatar/',
                'http://2.gravatar.com/avatar/'
            ],
            'https://cn.gravatar.com/avatar/',
            $url
        );
    }
    return $url;
}

/**
 * Modern Speculation Rules API for instant prerendering on link hover/intent.
 * Supported natively in WP 6.8+ and modern Chromium/WebKit browsers.
 */
function helmetsan_theme_speculation_rules(): void
{
    if (is_admin() || is_user_logged_in()) {
        return;
    }

    if (function_exists('is_cart') && is_cart()) {
        return;
    }
    if (function_exists('is_checkout') && is_checkout()) {
        return;
    }

    $rules = [
        'prerender' => [
            [
                'where' => [
                    'and' => [
                        ['href_matches' => '/*'],
                        ['not' => ['href_matches' => [
                            '/wp-admin/*',
                            '/wp-login.php*',
                            '*/cart/*',
                            '*/checkout/*',
                            '*/my-account/*',
                            '*\\?*add-to-cart=*',
                            '*\\?*action=*'
                        ]]]
                    ]
                ],
                'eagerness' => 'moderate'
            ]
        ]
    ];

    echo "\n" . '<script type="speculationrules">' . wp_json_encode($rules) . '</script>' . "\n";
}
add_action('wp_head', 'helmetsan_theme_speculation_rules', 1);

/**
 * 301 Redirect legacy riding style links to canonical taxonomy archives.
 */
function helmetsan_theme_legacy_riding_style_redirects(): void
{
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    $path = parse_url($uri, PHP_URL_PATH);
    if (! is_string($path)) {
        return;
    }

    $redirects = [
        'feature/urban-commuter' => 'use-case/commuting/',
        'feature/street'         => 'helmet-type/full-face/',
        'feature/sportbike'      => 'use-case/sport/',
    ];

    foreach ($redirects as $pattern => $target) {
        if (str_contains($path, $pattern)) {
            $langPrefix = '';
            if (preg_match('#^/([a-z]{2})/#', $path, $matches)) {
                $langPrefix = '/' . $matches[1];
            }
            $targetUrl = home_url($langPrefix . '/' . $target);
            wp_safe_redirect($targetUrl, 301);
            exit;
        }
    }
}
add_action('template_redirect', 'helmetsan_theme_legacy_riding_style_redirects', 1);

