<?php

declare(strict_types=1);

namespace Helmetsan\Core\Analytics;

use Helmetsan\Core\Price\PriceService;

final class DataLayerService
{
    public function __construct(
        private readonly PriceService $priceService
    ) {
    }

    public function register(): void
    {
        add_action('wp_head', [$this, 'injectPageContext'], 1);
        add_action('wp_head', [$this, 'injectDataLayer'], 5);
    }

    /** Global page context for GA/GTM (all pages). */
    public function injectPageContext(): void
    {
        $pageType = 'other';
        if (is_singular('helmet')) {
            $pageType = 'helmet';
        } elseif (is_singular('brand')) {
            $pageType = 'brand';
        } elseif (is_post_type_archive('helmet')) {
            $pageType = 'helmet_archive';
        } elseif (is_post_type_archive('brand')) {
            $pageType = 'brand_archive';
        } elseif (is_search()) {
            $pageType = 'search';
        } elseif (is_home() || is_front_page()) {
            $pageType = 'home';
        }
        $ctx = ['page_type' => $pageType];
        if (get_post_type()) {
            $ctx['post_type'] = get_post_type();
        }
        echo '<script>window.dataLayer=window.dataLayer||[];window.dataLayer.push(' . wp_json_encode($ctx) . ');</script>' . "\n";
    }

    public function injectDataLayer(): void
    {
        if (! is_singular('helmet')) {
            return;
        }

        $helmetId = (int) get_queried_object_id();
        if ($helmetId <= 0) {
            return;
        }

        $bestPrice = $this->priceService->getBestPrice($helmetId);
        $price = $bestPrice !== null ? $bestPrice->price : 0.0;
        $currency = $bestPrice !== null ? $bestPrice->currency : 'USD';

        if ($price <= 0.0) {
            // Fallback to static meta
            $priceStr = $this->priceService->getPrice($helmetId, 'USD');
            // Remove symbols
            $price = (float) preg_replace('/[^0-9.]/', '', $priceStr);
        }

        $brandTerms = get_the_terms($helmetId, 'helmet_brand');
        $brand = ! empty($brandTerms) && ! is_wp_error($brandTerms) ? $brandTerms[0]->name : '';

        $categoryTerms = get_the_terms($helmetId, 'helmet_type');
        $category = ! empty($categoryTerms) && ! is_wp_error($categoryTerms) ? $categoryTerms[0]->name : '';

        $listId   = isset($_GET['list_id']) ? sanitize_text_field((string) $_GET['list_id']) : '';
        $listName = isset($_GET['list_name']) ? sanitize_text_field((string) $_GET['list_name']) : '';

        $data = [
            'id'            => (string) $helmetId,
            'sku'           => (string) get_post_meta($helmetId, 'sku', true),
            'name'          => get_post_field('post_title', $helmetId),
            'brand'         => $brand,
            'category'      => $category,
            'price'         => $price,
            'currency'      => $currency,
            'eventNonce'    => wp_create_nonce('helmetsan_event'),
            'item_list_id'  => $listId,
            'item_list_name' => $listName,
        ];

        echo '<script>window.helmetsanData = ' . wp_json_encode($data) . ';</script>' . "\n";
    }
}
