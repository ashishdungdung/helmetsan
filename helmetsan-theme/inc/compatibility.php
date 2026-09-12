<?php
/**
 * Plugin compatibility checks.
 *
 * @package HelmetsanTheme
 */

if (! defined('ABSPATH')) {
    exit;
}

add_action('after_setup_theme', 'helmetsan_theme_check_dependencies');

function helmetsan_theme_check_dependencies(): void
{
    if (get_template() !== 'generatepress') {
        add_action('admin_notices', static function (): void {
            echo '<div class="notice notice-warning"><p>' . esc_html__('Helmetsan Mac Child expects GeneratePress as parent theme.', 'helmetsan-theme') . '</p></div>';
        });
    }

    if (! post_type_exists('helmet')) {
        add_action('admin_notices', static function (): void {
            echo '<div class="notice notice-warning"><p>' . esc_html__('Helmetsan Theme works best with Helmetsan Core plugin active.', 'helmetsan-theme') . '</p></div>';
        });
    }
}
add_filter('use_widgets_block_editor', '__return_false');

// Declare WooCommerce HPOS compatibility
add_action('before_woocommerce_init', static function (): void {
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', get_stylesheet(), true);
    }
});

