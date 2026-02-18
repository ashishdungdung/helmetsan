<?php
/**
 * WooCommerce integration hooks.
 *
 * @package HelmetsanTheme
 */

if (! defined('ABSPATH')) {
    exit;
}

add_action('after_setup_theme', 'helmetsan_theme_register_woo_hooks', 20);

function helmetsan_theme_register_woo_hooks(): void
{
    if (! class_exists('WooCommerce')) {
        return;
    }

    add_filter('body_class', 'helmetsan_theme_woo_body_classes');
    add_action('woocommerce_before_shop_loop', 'helmetsan_theme_woo_mobile_tools', 8);
    add_action('woocommerce_after_single_product', 'helmetsan_theme_woo_sticky_atc_markup', 30);
}

/**
 * @param array<int,string> $classes
 * @return array<int,string>
 */
function helmetsan_theme_woo_body_classes(array $classes): array
{
    if (function_exists('is_product') && is_product()) {
        $classes[] = 'hs-woo-product';
    }
    if (function_exists('is_shop') && is_shop()) {
        $classes[] = 'hs-woo-shop';
    }
    if (function_exists('is_product_taxonomy') && is_product_taxonomy()) {
        $classes[] = 'hs-woo-taxonomy';
    }
    if (function_exists('is_cart') && is_cart()) {
        $classes[] = 'hs-woo-cart';
    }

    return $classes;
}

function helmetsan_theme_woo_mobile_tools(): void
{
    if (! wp_is_mobile()) {
        return;
    }

    echo '<div class="hs-woo-mobile-tools" role="group" aria-label="Catalog tools">';
    echo '<button type="button" data-woo-filter>Filter</button>';
    echo '<button type="button" data-woo-sort>Sort</button>';
    echo '<button type="button" data-woo-size>Size</button>';
    echo '</div>';
}

function helmetsan_theme_woo_sticky_atc_markup(): void
{
    if (! wp_is_mobile() || ! function_exists('is_product') || ! is_product()) {
        return;
    }

    global $product;
    if (! $product instanceof WC_Product) {
        return;
    }

    $priceHtml = wp_strip_all_tags((string) $product->get_price_html());
    $title = (string) $product->get_name();

    echo '<div class="hs-woo-sticky-atc" aria-label="Mobile Add to Cart">';
    echo '<div class="hs-woo-sticky-atc__meta">';
    echo '<strong class="hs-woo-sticky-atc__price">' . esc_html($priceHtml !== '' ? $priceHtml : 'Price on selection') . '</strong>';
    echo '<span class="hs-woo-sticky-atc__title">' . esc_html($title) . '</span>';
    echo '<span class="hs-woo-sticky-atc__stock hs-stock--neutral">Select size</span>';
    echo '</div>';
    echo '<button type="button" class="hs-btn hs-btn--primary hs-woo-sticky-atc__btn" disabled>Add to Cart</button>';
    echo '</div>';
}
