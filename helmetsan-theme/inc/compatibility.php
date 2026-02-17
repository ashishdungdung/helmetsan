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
