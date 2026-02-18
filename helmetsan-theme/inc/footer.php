<?php
/**
 * Footer helpers.
 *
 * @package HelmetsanTheme
 */

if (! defined('ABSPATH')) {
    exit;
}

/**
 * @return array<int,array{url:string,title:string}>
 */
function helmetsan_theme_get_social_links(): array
{
    $locations = get_nav_menu_locations();
    if (! isset($locations['social'])) {
        return [];
    }

    $menuItems = wp_get_nav_menu_items((int) $locations['social']);
    if (! is_array($menuItems)) {
        return [];
    }

    $out = [];
    foreach ($menuItems as $item) {
        if (! ($item instanceof WP_Post)) {
            continue;
        }
        $url = (string) get_post_meta($item->ID, '_menu_item_url', true);
        $title = (string) $item->post_title;
        if ($url === '') {
            continue;
        }
        $out[] = ['url' => esc_url($url), 'title' => sanitize_text_field($title)];
    }

    return $out;
}

function helmetsan_theme_social_icon_svg(string $url, string $title = ''): string
{
    $host = strtolower((string) wp_parse_url($url, PHP_URL_HOST));
    $name = strtolower($title);
    if ($host !== '') {
        if (str_contains($host, 'instagram')) { $name = 'instagram'; }
        elseif (str_contains($host, 'facebook')) { $name = 'facebook'; }
        elseif (str_contains($host, 'youtube')) { $name = 'youtube'; }
        elseif (str_contains($host, 'x.com') || str_contains($host, 'twitter')) { $name = 'x'; }
        elseif (str_contains($host, 'linkedin')) { $name = 'linkedin'; }
        elseif (str_contains($host, 'tiktok')) { $name = 'tiktok'; }
    }

    $icons = [
        'facebook' => '<path d="M13.5 9H16V6h-2.5C11.6 6 10 7.6 10 9.5V12H8v3h2v5h3v-5h2.4l.6-3H13v-2.1c0-.5.4-.9.5-.9z"/>',
        'instagram' => '<path d="M12 8a4 4 0 100 8 4 4 0 000-8zm6.5-2.5A2.5 2.5 0 0016 3H8a5 5 0 00-5 5v8a5 5 0 005 5h8a5 5 0 005-5V8a2.5 2.5 0 00-2.5-2.5zM12 17a5 5 0 110-10 5 5 0 010 10zm5.5-9.5a1.5 1.5 0 110-3 1.5 1.5 0 010 3z"/>',
        'youtube' => '<path d="M21 8.5s-.2-1.4-.8-2c-.8-.8-1.7-.8-2.1-.9C15.2 5.4 12 5.4 12 5.4h0s-3.2 0-6.1.2c-.4.1-1.3.1-2.1.9-.6.6-.8 2-.8 2S3 10.1 3 11.8v1.4c0 1.7.2 3.3.2 3.3s.2 1.4.8 2c.8.8 1.9.8 2.4.9 1.8.2 5.6.2 5.6.2s3.2 0 6.1-.2c.4-.1 1.3-.1 2.1-.9.6-.6.8-2 .8-2s.2-1.6.2-3.3v-1.4c0-1.7-.2-3.3-.2-3.3zM10 15V9l5 3-5 3z"/>',
        'x' => '<path d="M18.9 4H22l-6.8 7.8L23 20h-6.2l-4.9-5.7L7 20H4l7.3-8.3L1 4h6.3l4.4 5.1L18.9 4z"/>',
        'linkedin' => '<path d="M6.5 8.8H3.5V20h3V8.8zM5 3a1.8 1.8 0 100 3.6A1.8 1.8 0 005 3zM20.5 13.8c0-3.1-1.7-5.2-4.5-5.2-2.1 0-3 .9-3.6 1.6V8.8h-3V20h3v-6.2c0-1.7.3-3.4 2.4-3.4 2 0 2.1 1.9 2.1 3.5V20h3v-6.2z"/>',
        'tiktok' => '<path d="M13.5 4h3.1c.2 1.3 1 2.3 2.2 2.8v3.1c-1.1-.1-2.2-.5-3.1-1.2v5.1a5.2 5.2 0 11-5.2-5.2c.2 0 .3 0 .5.1V12a2.1 2.1 0 102.1 2.1V4z"/>',
    ];

    $path = $icons[$name] ?? '<circle cx="12" cy="12" r="9"/><path d="M8 12h8"/>';

    return '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">' . $path . '</svg>';
}
