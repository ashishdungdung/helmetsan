<?php
/**
 * Theme setup.
 *
 * @package HelmetsanTheme
 */

if (! defined('ABSPATH')) {
    exit;
}

add_action('after_setup_theme', 'helmetsan_theme_setup');
add_action('widgets_init', 'helmetsan_theme_register_sidebars');

function helmetsan_theme_setup(): void
{
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('html5', ['search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script']);
    add_theme_support('woocommerce');

    register_nav_menus([
        'primary' => __('Primary Menu', 'helmetsan-theme'),
        'secondary' => __('Secondary Menu', 'helmetsan-theme'),
        'footer' => __('Footer Menu', 'helmetsan-theme'),
        'legal' => __('Legal Menu', 'helmetsan-theme'),
        'social' => __('Social Menu', 'helmetsan-theme'),
    ]);
}

function helmetsan_theme_register_sidebars(): void
{
    $sidebars = [
        'footer-1' => __('Footer Column 1', 'helmetsan-theme'),
        'footer-2' => __('Footer Column 2', 'helmetsan-theme'),
        'footer-3' => __('Footer Column 3', 'helmetsan-theme'),
    ];

    foreach ($sidebars as $id => $name) {
        register_sidebar([
            'name' => $name,
            'id' => $id,
            'before_widget' => '<section id="%1$s" class="widget hs-footer-widget %2$s">',
            'after_widget' => '</section>',
            'before_title' => '<h3 class="widget-title">',
            'after_title' => '</h3>',
        ]);
    }
}
