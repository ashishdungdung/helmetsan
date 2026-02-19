<?php
/**
 * Template tags.
 *
 * @package HelmetsanTheme
 */

if (! defined('ABSPATH')) {
    exit;
}

function helmetsan_theme_posted_on(): void
{
    echo '<span class="posted-on">' . esc_html(get_the_date()) . '</span>';
}

function helmetsan_get_brand_id(int $helmetId): int
{
    return (int) get_post_meta($helmetId, 'rel_brand', true);
}

function helmetsan_get_brand_name(int $helmetId): string
{
    $brandId = helmetsan_get_brand_id($helmetId);
    if ($brandId <= 0) {
        return '';
    }

    $brand = get_post($brandId);
    return $brand instanceof WP_Post ? (string) $brand->post_title : '';
}

function helmetsan_get_related_helmets_by_brand(int $helmetId, int $limit = 6): array
{
    $brandId = helmetsan_get_brand_id($helmetId);
    if ($brandId <= 0) {
        return [];
    }

    $q = new WP_Query([
        'post_type' => 'helmet',
        'post_status' => 'publish',
        'posts_per_page' => max(1, $limit),
        'post__not_in' => [$helmetId],
        'meta_query' => [
            [
                'key' => 'rel_brand',
                'value' => $brandId,
            ],
        ],
    ]);

    if (! $q->have_posts()) {
        return [];
    }

    return $q->posts;
}

function helmetsan_get_helmet_price($helmetId): string
{
    if (function_exists('helmetsan_core')) {
        return helmetsan_core()->price()->getPrice($helmetId);
    }

    $price = get_post_meta($helmetId, 'price_retail_usd', true);
    if (! is_numeric((string) $price)) {
        return 'N/A';
    }

    return '$' . number_format((float) $price, 2);
}

function helmetsan_get_brand_helmet_count(int $brandId): int
{
    $cacheKey = 'helmetsan_brand_count_' . $brandId;
    $cached = wp_cache_get($cacheKey);
    if (false !== $cached) {
        return (int) $cached;
    }

    $q = new WP_Query([
        'post_type' => 'helmet',
        'post_status' => 'publish',
        'posts_per_page' => 1,
        'fields' => 'ids',
        'meta_query' => [
            [
                'key' => 'rel_brand',
                'value' => $brandId,
            ],
        ],
    ]);

    $count = (int) $q->found_posts;
    wp_cache_set($cacheKey, $count, '', 3600);
    return $count;
}

/**
 * Get formatted price for any currency.
 */
function helmetsan_get_price($post, string $currency = 'USD'): string
{
    if (function_exists('helmetsan_core')) {
        return helmetsan_core()->price()->getPrice($post, $currency);
    }
    
    return 'N/A';
}

function helmetsan_get_certifications(int $helmetId): string
{
    $terms = get_the_terms($helmetId, 'certification');
    if (! is_array($terms) || $terms === []) {
        return 'N/A';
    }

    $names = array_map(static fn ($term): string => (string) $term->name, $terms);
    return implode(', ', $names);
}

function helmetsan_get_logo_url(int $postId): string
{
    $attachmentId = (int) get_post_meta($postId, '_helmetsan_logo_attachment_id', true);
    if ($attachmentId > 0) {
        $attachmentUrl = (string) wp_get_attachment_url($attachmentId);
        if ($attachmentUrl !== '') {
            return $attachmentUrl;
        }
    }

    $url = (string) get_post_meta($postId, '_helmetsan_logo_url', true);
    if ($url !== '') {
        return $url;
    }

    $thumbUrl = (string) get_the_post_thumbnail_url($postId, 'full');
    if ($thumbUrl !== '') {
        return $thumbUrl;
    }

    $postType = get_post_type($postId);
    if ($postType === 'brand') {
        $supportUrl = (string) get_post_meta($postId, 'brand_support_url', true);
        $domain = (string) wp_parse_url($supportUrl, PHP_URL_HOST);
        $domain = strtolower(trim($domain));
        $domain = preg_replace('#^www\.#', '', $domain) ?? $domain;
        if ($domain !== '') {
            $mediaCfg = wp_parse_args((array) get_option('helmetsan_media', []), [
                'logodev_enabled' => false,
                'logodev_publishable_key' => '',
                'logodev_token' => '',
            ]);
            if (! empty($mediaCfg['logodev_enabled'])) {
                $token = (string) ($mediaCfg['logodev_publishable_key'] ?? '');
                if ($token === '') {
                    $token = (string) ($mediaCfg['logodev_token'] ?? '');
                }
                $fallback = 'https://img.logo.dev/' . rawurlencode($domain);
                if ($token !== '') {
                    $fallback = add_query_arg(['token' => $token], $fallback);
                }
                return (string) $fallback;
            }
        }
    }

    if ($postType === 'helmet') {
        $brandId = helmetsan_get_brand_id($postId);
        if ($brandId > 0) {
            return (string) get_post_meta($brandId, '_helmetsan_logo_url', true);
        }
    }

    return '';
}

/**
 * Get technical analysis for a helmet, inheriting from parent if needed.
 */
function helmetsan_get_technical_analysis($helmetId): string
{
    if (function_exists('helmetsan_core')) {
        return (string) helmetsan_core()->helmets()->getInheritedMeta($helmetId, 'technical_analysis');
    }
    return (string) get_post_meta($helmetId, 'technical_analysis', true);
}

/**
 * Get key specs for a helmet.
 */
function helmetsan_get_key_specs($helmetId): array
{
    if (function_exists('helmetsan_core')) {
        $json = helmetsan_core()->helmets()->getInheritedMeta($helmetId, 'key_specs_json');
    } else {
        $json = get_post_meta($helmetId, 'key_specs_json', true);
    }

    if (is_string($json) && $json !== '') {
        $decoded = json_decode($json, true);
        return is_array($decoded) ? $decoded : [];
    }
    return [];
}

/**
 * Get compatible accessory IDs for a helmet.
 */
function helmetsan_get_compatible_accessories($helmetId): array
{
    if (function_exists('helmetsan_core')) {
        $json = helmetsan_core()->helmets()->getInheritedMeta($helmetId, 'compatible_accessories_json');
    } else {
        $json = get_post_meta($helmetId, 'compatible_accessories_json', true);
    }

    if (is_array($json)) {
        return $json;
    }
    if (is_string($json) && $json !== '') {
        $decoded = json_decode($json, true);
        return is_array($decoded) ? $decoded : [];
    }
    return [];
}

/**
 * Get shell material for a helmet.
 */
function helmetsan_get_shell_material($helmetId): string
{
    if (function_exists('helmetsan_core')) {
        return (string) helmetsan_core()->helmets()->getInheritedMeta($helmetId, 'spec_shell_material');
    }
    return (string) get_post_meta($helmetId, 'spec_shell_material', true);
}

/**
 * Get weight for a helmet (grams).
 */
function helmetsan_get_weight($helmetId): int
{
    if (function_exists('helmetsan_core')) {
        return (int) helmetsan_core()->helmets()->getInheritedMeta($helmetId, 'spec_weight_g');
    }
    return (int) get_post_meta($helmetId, 'spec_weight_g', true);
}

/**
 * Get head shape for a helmet.
 */
function helmetsan_get_head_shape($helmetId): string
{
    if (function_exists('helmetsan_core')) {
        return (string) helmetsan_core()->helmets()->getInheritedMeta($helmetId, 'head_shape');
    }
    return (string) get_post_meta($helmetId, 'head_shape', true);
}

/**
 * @param string $type
 * @return array<string,mixed>
 */
function helmetsan_get_mega_menu_data(string $type = 'helmet'): array
{
    $filename = $type . '-mega-menu.json';
    $paths = [
        WP_CONTENT_DIR . '/uploads/helmetsan-data/catalogs/' . $filename,
        get_stylesheet_directory() . '/data/' . $filename,
        ABSPATH . '../data/catalogs/' . $filename,
    ];

    foreach ($paths as $path) {
        if (! is_string($path) || ! file_exists($path)) {
            continue;
        }
        $raw = file_get_contents($path);
        if (! is_string($raw) || $raw === '') {
            continue;
        }
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            return $decoded;
        }
    }

    return [];
}

/**
 * Backward compatibility wrapper.
 */
function helmetsan_get_helmet_mega_menu_data(): array
{
    return helmetsan_get_mega_menu_data('helmet');
}

function helmetsan_find_term_slug_by_label(string $taxonomy, string $label): string
{
    static $cache = [];
    $key = $taxonomy . '::' . $label;
    if (isset($cache[$key])) {
        return $cache[$key];
    }

    $normalizedLabel = helmetsan_normalize_filter_label($taxonomy, $label);
    $slug = sanitize_title($normalizedLabel);
    $term = get_term_by('slug', $slug, $taxonomy);
    if ($term instanceof WP_Term) {
        $cache[$key] = (string) $term->slug;
        return $cache[$key];
    }

    $term = get_term_by('name', $normalizedLabel, $taxonomy);
    if ($term instanceof WP_Term) {
        $cache[$key] = (string) $term->slug;
        return $cache[$key];
    }

    $cache[$key] = $slug;
    return $cache[$key];
}

function helmetsan_normalize_filter_label(string $taxonomy, string $label): string
{
    $value = trim((string) $label);
    if ($value === '') {
        return '';
    }
    if ($taxonomy !== 'helmet_type') {
        return $value;
    }

    $lower = strtolower($value);
    $lower = str_replace(['&', '/'], [' and ', ' '], $lower);
    $lower = preg_replace('/\s+/', ' ', $lower) ?? $lower;
    $lower = str_replace('helmets', '', $lower);
    $lower = trim($lower);

    return match ($lower) {
        'full face', 'full-face' => 'Full Face',
        'modular' => 'Modular',
        'open face', 'open-face' => 'Open Face',
        'half', 'half helmet', 'half-helmet' => 'Half',
        'dirt', 'mx', 'dirt mx', 'dirt motocross', 'off road', 'off-road', 'motocross' => 'Dirt/MX',
        'adventure', 'dual sport', 'adventure dual sport', 'adventure and dual sport' => 'Adventure/Dual Sport',
        'touring' => 'Touring',
        'track', 'race', 'track race' => 'Track/Race',
        'youth' => 'Youth',
        'snow', 'snowmobile' => 'Snow',
        'carbon fiber', 'carbon-fiber' => 'Carbon Fiber',
        'graphics', 'graphic' => 'Graphics',
        'sale', 'closeout' => 'Sale',
        default => $value,
    };
}

function helmetsan_is_certification_label(string $label): bool
{
    $needle = strtolower(trim($label));
    if ($needle === '') {
        return false;
    }

    $certHints = ['ece', 'snell', 'fim', 'dot'];
    foreach ($certHints as $hint) {
        if (str_contains($needle, $hint)) {
            return true;
        }
    }
    return false;
}

function helmetsan_is_type_label(string $label): bool
{
    $needle = strtolower(trim($label));
    $typeHints = ['full face', 'modular', 'open face', 'half', 'dirt', 'adventure', 'touring', 'track', 'youth', 'snow'];
    foreach ($typeHints as $hint) {
        if (str_contains($needle, $hint)) {
            return true;
        }
    }
    return false;
}

function helmetsan_mega_menu_item_url(string $label, string $heading, string $type = 'helmet'): string
{
    $label = trim($label);
    $heading = trim($heading);

    // --- STRATEGY FOR BRANDS ---
    if ($type === 'brands') {
         // Link to /brand/slug/
         $slug = sanitize_title($label);
         return (string) home_url('/brand/' . $slug . '/');
    }

    // --- STRATEGY FOR ACCESSORIES ---
    if ($type === 'accessories') {
        // Link to /accessory-category/slug/
        // Assuming labels map to accessory_category terms
        $slug = sanitize_title($label);
        return (string) home_url('/accessory-category/' . $slug . '/');
    }

    // --- STRATEGY FOR MOTORCYCLES ---
    if ($type === 'motorcycles') {
        // Link to /motorcycles/?s=Label for now, as we don't have a robust taxonomy yet
        return (string) add_query_arg('s', $label, get_post_type_archive_link('motorcycle'));
    }

    // --- DEFAULT: HELMETS ---
    $helmetsArchive = (string) get_post_type_archive_link('helmet');
    if ($helmetsArchive === '') {
        $helmetsArchive = (string) home_url('/helmets/');
    }
    if ($label === '') {
        return $helmetsArchive;
    }

    if (stripos($heading, 'Brand') !== false) {
        // Updated: use /brand/slug/ instead of query arg if new strategy is fully adopted?
        // But keeping query arg for backward compat if flexible.
        // Actually, user wants "integrate the same". Let's use the new Singular Taxonomy URL strategy.
        $slug = sanitize_title($label);
        return (string) home_url('/brand/' . $slug . '/');
    }

    if (stripos($heading, 'Type') !== false || helmetsan_is_type_label($label)) {
        $slug = helmetsan_find_term_slug_by_label('helmet_type', $label);
        // Use new URL strategy: /helmet-type/slug/
        return (string) home_url('/helmet-type/' . $slug . '/');
    }

    if (stripos($heading, 'Safety') !== false || helmetsan_is_certification_label($label)) {
        $slug = helmetsan_find_term_slug_by_label('certification', $label);
        return (string) home_url('/reviews/?certification=' . $slug); // Or archive if we have one. Certification archive?
        // Registrar says: rewrite slug 'certification'. So /certification/slug/
        return (string) home_url('/certification/' . $slug . '/');
    }

    if (stripos($heading, 'Model') !== false || str_contains(strtolower($label), 'rf1400') || str_contains(strtolower($label), 'pista')) {
        return (string) add_query_arg(['helmet_family' => $label], $helmetsArchive);
    }

    if (stripos($heading, 'Style') !== false || stripos($heading, 'Feature') !== false || stripos($heading, 'Riding') !== false) {
        $slug = helmetsan_find_term_slug_by_label('feature_tag', $label);
        // /feature/slug/ ? Registrar: rewrite slug 'feature'.
        return (string) home_url('/feature/' . $slug . '/');
    }

    return (string) add_query_arg(['feature' => helmetsan_find_term_slug_by_label('feature_tag', $label)], $helmetsArchive);
}

function helmetsan_render_mega_menu(string $type = 'helmet'): void
{
    // 1. Try to render from WP Nav Menu (The "Control" Way)
    $location = 'mega_' . $type; // e.g., 'mega_brands'
    $locations = get_nav_menu_locations();

    if (isset($locations[$location])) {
        $menuId = $locations[$location];
        $items = wp_get_nav_menu_items($menuId);

        if ($items) {
            // Build Tree
            $menuTree = [];
            foreach ($items as $item) {
                if (empty($item->menu_item_parent)) {
                    $menuTree[$item->ID] = [
                        'heading' => $item->title,
                        'url' => $item->url,
                        'children' => []
                    ];
                }
            }
            foreach ($items as $item) {
                if (! empty($item->menu_item_parent) && isset($menuTree[$item->menu_item_parent])) {
                    $menuTree[$item->menu_item_parent]['children'][] = [
                        'label' => $item->title,
                        'url' => $item->url,
                    ];
                }
            }

            // Get JSON just for the highlight blocks / footer / title fallback
            $jsonData = helmetsan_get_mega_menu_data($type);
            $title = $jsonData['title'] ?? ucfirst($type) . ' Menu';
            $footerLabel = $jsonData['footer'] ?? 'View All ' . ucfirst($type);
            $footerUrl = home_url('/' . ($type === 'brand' ? 'brands' : $type . 's') . '/');
            
            ?>
            <div class="hs-mega-menu">
                <div class="hs-mega-menu__inner">
                    <div class="hs-mega-menu__grid">
                        <?php foreach ($menuTree as $column): ?>
                            <div class="hs-mega-menu__col">
                                <h3>
                                    <?php if ($column['url'] && $column['url'] !== '#'): ?>
                                        <a href="<?php echo esc_url($column['url']); ?>"><?php echo esc_html($column['heading']); ?></a>
                                    <?php else: ?>
                                        <?php echo esc_html($column['heading']); ?>
                                    <?php endif; ?>
                                </h3>

                                <?php if (! empty($column['children'])): ?>
                                    <ul>
                                        <?php foreach ($column['children'] as $link): ?>
                                            <li>
                                                <a href="<?php echo esc_url($link['url']); ?>">
                                                    <?php echo esc_html($link['label']); ?>
                                                </a>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <?php if (! empty($jsonData['highlight_blocks'])): ?>
                        <div class="hs-mega-menu__bottom">
                            <?php foreach ($jsonData['highlight_blocks'] as $block): ?>
                                <div class="hs-mega-menu__highlight">
                                    <h3><?php echo esc_html($block['heading']); ?></h3>
                                    <?php if (! empty($block['items'])): ?>
                                        <ul>
                                            <?php foreach ($block['items'] as $item): 
                                                $label = is_string($item) ? $item : $item['label'];
                                                $url = '#'; 
                                            ?>
                                                <li><a href="<?php echo esc_url($url); ?>"><?php echo esc_html($label); ?></a></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <div class="hs-mega-menu__footer">
                        <a href="<?php echo esc_url($footerUrl); ?>">
                            <?php echo esc_html($footerLabel); ?> &rarr;
                        </a>
                    </div>
                </div>
                
                <!-- Mobile Fallback (Nav Menu) -->
                <div class="hs-mega-menu-mobile">
                     <div class="hs-mega-menu-mobile__title"><?php echo esc_html($title); ?></div>
                     <?php foreach ($menuTree as $column): ?>
                        <details class="hs-mobile-nav-group">
                            <summary><?php echo esc_html($column['heading']); ?></summary>
                            <ul>
                                <?php foreach ($column['children'] as $link): ?>
                                    <li><a href="<?php echo esc_url($link['url']); ?>"><?php echo esc_html($link['label']); ?></a></li>
                                <?php endforeach; ?>
                            </ul>
                        </details>
                     <?php endforeach; ?>
                </div>

            </div>
            <?php
            return;
        }
    }

    // 2. Fallback to JSON (Original Logic)
    $menu = helmetsan_get_mega_menu_data($type);
    if ($menu === []) {
        return;
    }

    $columns = isset($menu['columns']) && is_array($menu['columns']) ? $menu['columns'] : [];
    $highlights = isset($menu['highlight_blocks']) && is_array($menu['highlight_blocks']) ? $menu['highlight_blocks'] : [];
    $families = isset($menu['family_examples']) && is_array($menu['family_examples']) ? $menu['family_examples'] : [];
    if ($columns === []) {
        return;
    }

    // Determine footer link based on type
    $footerUrl = '#'; 
    if ($type === 'helmet') {
        $footerUrl = '/brands/';
    } elseif ($type === 'brands') {
        $footerUrl = '/brands/';
    } elseif ($type === 'accessories') {
        $footerUrl = '/accessories/';
    } elseif ($type === 'motorcycles') {
        $footerUrl = '/motorcycles/';
    }
    
    // ... (Compact output for JSON fallback to save space in file) ...
    ?>
    <section class="hs-mega-menu" aria-label="<?php echo esc_attr(ucfirst($type)); ?> mega menu">
        <div class="hs-mega-menu__inner">
            <div class="hs-mega-menu__grid">
                <?php foreach ($columns as $column): 
                    $heading = isset($column['heading']) ? (string) $column['heading'] : '';
                    $items = isset($column['items']) && is_array($column['items']) ? $column['items'] : [];
                    if ($heading === '') continue;
                ?>
                    <article class="hs-mega-menu__col">
                        <h3><?php echo esc_html($heading); ?></h3>
                        <ul>
                        <?php foreach ($items as $item): 
                             $label = is_string($item) ? $item : $item['label'];
                             $url = helmetsan_mega_menu_item_url($label, $heading, $type);
                        ?>
                            <li><a href="<?php echo esc_url($url); ?>"><?php echo esc_html($label); ?></a></li>
                        <?php endforeach; ?>
                        </ul>
                    </article>
                <?php endforeach; ?>
            </div>
             <!-- ... Highlights omitted for brevity in fallback, user wants Nav Menu control ... -->
        </div>
    </section>
    <?php
}
/**
 * Backward compatibility wrapper.
 */
function helmetsan_render_helmet_mega_menu(): void
{
    helmetsan_render_mega_menu('helmet');
}

/**
 * Render sticky comparison bar.
 */
function helmetsan_render_comparison_bar(): void
{
    get_template_part('template-parts/sticky-comparison-bar');
}
add_action('wp_footer', 'helmetsan_render_comparison_bar');
