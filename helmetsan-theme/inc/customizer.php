<?php
/**
 * Theme Customizer options.
 *
 * @package HelmetsanTheme
 */

if (! defined('ABSPATH')) {
    exit;
}

add_action('customize_register', 'helmetsan_theme_customize_register');
add_action('customize_controls_enqueue_scripts', 'helmetsan_theme_customize_assets');

if (class_exists('WP_Customize_Control') && ! class_exists('Helmetsan_Theme_Legal_Sortable_Control')) {
    class Helmetsan_Theme_Legal_Sortable_Control extends WP_Customize_Control
    {
        public $type = 'hs_legal_sortable';

        /**
         * @var array<string,string>
         */
        public $choices = [];

        public function render_content(): void
        {
            if (! is_array($this->choices) || $this->choices === []) {
                return;
            }
            ?>
            <label>
                <?php if (! empty($this->label)) : ?>
                    <span class="customize-control-title"><?php echo esc_html((string) $this->label); ?></span>
                <?php endif; ?>
                <?php if (! empty($this->description)) : ?>
                    <span class="description customize-control-description"><?php echo esc_html((string) $this->description); ?></span>
                <?php endif; ?>
            </label>
            <ul class="hs-legal-sortable" data-control="<?php echo esc_attr($this->id); ?>">
                <?php foreach ($this->choices as $key => $label) : ?>
                    <li class="hs-legal-sortable__item" data-key="<?php echo esc_attr((string) $key); ?>">
                        <span class="hs-legal-sortable__handle" aria-hidden="true">⋮⋮</span>
                        <span><?php echo esc_html((string) $label); ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
            <input type="hidden" class="hs-legal-sortable-input" <?php $this->link(); ?> value="<?php echo esc_attr((string) $this->value()); ?>" />
            <?php
        }
    }
}

function helmetsan_theme_customize_register(WP_Customize_Manager $wpCustomize): void
{
    $wpCustomize->add_section('helmetsan_theme_layout', [
        'title' => __('Helmetsan Layout & Footer', 'helmetsan-theme'),
        'priority' => 45,
    ]);

    $wpCustomize->add_setting('helmetsan_layout_alignment', [
        'default' => 'balanced',
        'sanitize_callback' => 'helmetsan_theme_sanitize_layout_alignment',
    ]);
    $wpCustomize->add_control('helmetsan_layout_alignment', [
        'label' => __('Content Alignment', 'helmetsan-theme'),
        'section' => 'helmetsan_theme_layout',
        'type' => 'select',
        'choices' => [
            'balanced' => __('Balanced (Centered)', 'helmetsan-theme'),
            'wide' => __('Wide (Centered)', 'helmetsan-theme'),
            'left' => __('Left Aligned', 'helmetsan-theme'),
        ],
    ]);

    $wpCustomize->add_setting('helmetsan_content_max_width', [
        'default' => 1200,
        'sanitize_callback' => 'absint',
    ]);
    $wpCustomize->add_control('helmetsan_content_max_width', [
        'label' => __('Content Max Width (px)', 'helmetsan-theme'),
        'section' => 'helmetsan_theme_layout',
        'type' => 'number',
        'input_attrs' => [
            'min' => 980,
            'max' => 1600,
            'step' => 10,
        ],
    ]);

    $wpCustomize->add_setting('helmetsan_show_footer_legal', [
        'default' => true,
        'sanitize_callback' => 'rest_sanitize_boolean',
    ]);
    $wpCustomize->add_control('helmetsan_show_footer_legal', [
        'label' => __('Show Required Legal Links', 'helmetsan-theme'),
        'section' => 'helmetsan_theme_layout',
        'type' => 'checkbox',
    ]);

    $legalToggles = [
        'privacy' => __('Show Privacy Policy', 'helmetsan-theme'),
        'terms' => __('Show Terms of Use', 'helmetsan-theme'),
        'cookie' => __('Show Cookie Policy', 'helmetsan-theme'),
        'disclaimer' => __('Show Disclaimer', 'helmetsan-theme'),
        'affiliate' => __('Show Affiliate Disclosure', 'helmetsan-theme'),
        'ai' => __('Show AI Policy', 'helmetsan-theme'),
        'accessibility' => __('Show Accessibility Statement', 'helmetsan-theme'),
    ];

    foreach ($legalToggles as $key => $label) {
        $settingId = 'helmetsan_legal_' . $key;
        $wpCustomize->add_setting($settingId, [
            'default' => true,
            'sanitize_callback' => 'rest_sanitize_boolean',
        ]);
        $wpCustomize->add_control($settingId, [
            'label' => $label,
            'section' => 'helmetsan_theme_layout',
            'type' => 'checkbox',
        ]);
    }

    $wpCustomize->add_setting('helmetsan_legal_links_order', [
        'default' => 'privacy,terms,cookie,disclaimer,affiliate,ai,accessibility',
        'sanitize_callback' => 'helmetsan_theme_sanitize_legal_order',
    ]);
    $wpCustomize->add_control(new Helmetsan_Theme_Legal_Sortable_Control(
        $wpCustomize,
        'helmetsan_legal_links_order',
        [
            'label' => __('Legal Links Order', 'helmetsan-theme'),
            'description' => __('Drag and drop to reorder links in footer bottom line.', 'helmetsan-theme'),
            'section' => 'helmetsan_theme_layout',
            'choices' => [
                'privacy' => __('Privacy Policy', 'helmetsan-theme'),
                'terms' => __('Terms of Use', 'helmetsan-theme'),
                'cookie' => __('Cookie Policy', 'helmetsan-theme'),
                'disclaimer' => __('Disclaimer', 'helmetsan-theme'),
                'affiliate' => __('Affiliate Disclosure', 'helmetsan-theme'),
                'ai' => __('AI Policy', 'helmetsan-theme'),
                'accessibility' => __('Accessibility Statement', 'helmetsan-theme'),
            ],
        ]
    ));

    $wpCustomize->add_setting('helmetsan_show_made_in_india', [
        'default' => true,
        'sanitize_callback' => 'rest_sanitize_boolean',
    ]);
    $wpCustomize->add_control('helmetsan_show_made_in_india', [
        'label' => __('Show “Made with <3 in India”', 'helmetsan-theme'),
        'section' => 'helmetsan_theme_layout',
        'type' => 'checkbox',
    ]);

    $wpCustomize->add_setting('helmetsan_copyright_text', [
        'default' => '© {year} {site_name}. All rights reserved.',
        'sanitize_callback' => 'sanitize_text_field',
    ]);
    $wpCustomize->add_control('helmetsan_copyright_text', [
        'label' => __('Copyright Notice', 'helmetsan-theme'),
        'description' => __('Use {year} and {site_name} placeholders.', 'helmetsan-theme'),
        'section' => 'helmetsan_theme_layout',
        'type' => 'text',
    ]);
}

function helmetsan_theme_sanitize_layout_alignment(string $value): string
{
    $allowed = ['balanced', 'wide', 'left'];
    return in_array($value, $allowed, true) ? $value : 'balanced';
}

function helmetsan_theme_sanitize_legal_order(string $value): string
{
    $allowed = ['privacy', 'terms', 'cookie', 'disclaimer', 'affiliate', 'ai', 'accessibility'];
    $parts = array_filter(array_map('trim', explode(',', strtolower($value))));
    $parts = array_values(array_unique(array_intersect($parts, $allowed)));

    foreach ($allowed as $fallback) {
        if (! in_array($fallback, $parts, true)) {
            $parts[] = $fallback;
        }
    }

    return implode(',', $parts);
}

function helmetsan_theme_customize_assets(): void
{
    wp_enqueue_script(
        'helmetsan-theme-customizer-legal-order',
        get_stylesheet_directory_uri() . '/assets/js/customizer-legal-order.js',
        ['jquery', 'jquery-ui-sortable', 'customize-controls'],
        helmetsan_theme_asset_version('/assets/js/customizer-legal-order.js'),
        true
    );

    wp_enqueue_style(
        'helmetsan-theme-customizer-legal-order',
        get_stylesheet_directory_uri() . '/assets/css/customizer.css',
        [],
        helmetsan_theme_asset_version('/assets/css/customizer.css')
    );
}

/**
 * @return array<int,array{label:string,url:string}>
 */
function helmetsan_theme_get_required_legal_links(): array
{
    $links = [];
    if (! (bool) get_theme_mod('helmetsan_show_footer_legal', true)) {
        return $links;
    }

    $orderRaw = (string) get_theme_mod('helmetsan_legal_links_order', 'privacy,terms,cookie,disclaimer,affiliate,ai,accessibility');
    $order = explode(',', helmetsan_theme_sanitize_legal_order($orderRaw));

    $defs = [
        'privacy' => [
            'label' => __('Privacy Policy', 'helmetsan-theme'),
            'flag' => 'helmetsan_legal_privacy',
            'url' => static function (): string {
                $privacyId = (int) get_option('wp_page_for_privacy_policy');
                return $privacyId > 0 ? (string) get_permalink($privacyId) : '';
            },
        ],
        'terms' => [
            'label' => __('Terms of Use', 'helmetsan-theme'),
            'flag' => 'helmetsan_legal_terms',
            'slug' => 'terms-of-use',
        ],
        'cookie' => [
            'label' => __('Cookie Policy', 'helmetsan-theme'),
            'flag' => 'helmetsan_legal_cookie',
            'slug' => 'cookie-policy',
        ],
        'disclaimer' => [
            'label' => __('Disclaimer', 'helmetsan-theme'),
            'flag' => 'helmetsan_legal_disclaimer',
            'slug' => 'disclaimer',
        ],
        'affiliate' => [
            'label' => __('Affiliate Disclosure', 'helmetsan-theme'),
            'flag' => 'helmetsan_legal_affiliate',
            'slug' => 'affiliate-disclosure',
        ],
        'ai' => [
            'label' => __('AI Policy', 'helmetsan-theme'),
            'flag' => 'helmetsan_legal_ai',
            'slug' => 'ai-policy',
        ],
        'accessibility' => [
            'label' => __('Accessibility Statement', 'helmetsan-theme'),
            'flag' => 'helmetsan_legal_accessibility',
            'slug' => 'accessibility-statement',
        ],
    ];

    foreach ($order as $key) {
        if (! isset($defs[$key])) {
            continue;
        }
        $item = $defs[$key];
        if (! (bool) get_theme_mod((string) $item['flag'], true)) {
            continue;
        }
        $url = '';
        if (isset($item['url']) && is_callable($item['url'])) {
            $url = (string) $item['url']();
        } elseif (isset($item['slug'])) {
            $url = helmetsan_theme_find_page_url_by_slug((string) $item['slug']);
        }
        if ($url === '') {
            continue;
        }
        $links[] = [
            'label' => (string) $item['label'],
            'url' => $url,
        ];
    }

    return $links;
}

function helmetsan_theme_find_page_url_by_slug(string $slug): string
{
    $slug = sanitize_title($slug);
    if ($slug === '') {
        return '';
    }

    $candidates = [
        $slug,
        'legal/' . $slug,
    ];

    foreach ($candidates as $path) {
        $page = get_page_by_path($path);
        if ($page instanceof WP_Post) {
            return (string) get_permalink($page->ID);
        }
    }

    $q = new WP_Query([
        'post_type' => 'page',
        'name' => $slug,
        'post_status' => 'publish',
        'posts_per_page' => 1,
        'fields' => 'ids',
    ]);
    if (is_array($q->posts) && $q->posts !== []) {
        return (string) get_permalink((int) $q->posts[0]);
    }

    return '';
}
