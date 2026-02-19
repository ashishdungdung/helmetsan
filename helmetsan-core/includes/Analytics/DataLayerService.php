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
        add_action('wp_head', [$this, 'injectDataLayer'], 5);
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

        $data = [
            'id'       => (string) $helmetId,
            'sku'      => (string) get_post_meta($helmetId, 'sku', true),
            'name'     => get_post_field('post_title', $helmetId),
            'brand'    => $brand,
            'category' => $category,
            'price'    => $price,
            'currency' => $currency,
            'eventNonce' => wp_create_nonce('helmetsan_event'),
        ];

        echo '<script>window.helmetsanData = ' . wp_json_encode($data) . ';</script>' . "\n";
    }
}
