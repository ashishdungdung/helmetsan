<?php

declare(strict_types=1);

namespace Helmetsan\Core\Price;

use WP_Post;

final class PriceService
{
    /**
     * Get formatted price for a helmet
     *
     * @param int|WP_Post $post
     * @param string $currency 'USD', 'EUR', 'GBP'
     * @return string
     */
    public function getPrice($post, string $currency = 'USD'): string
    {
        $post = get_post($post);
        if (!$post) return '';

        $key = match (strtoupper($currency)) {
            'EUR' => 'price_eur',
            'GBP' => 'price_gbp',
            default => 'price_usd',
        };

        // Try specific currency meta
        $price = get_post_meta($post->ID, $key, true);

        // Fallback to retail_usd if USD requested and specific missing
        if ($currency === 'USD' && $price === '') {
            $price = get_post_meta($post->ID, 'price_retail_usd', true);
        }

        if (!is_numeric($price)) return 'Check Retailer';

        return $this->formatPrice((float) $price, $currency);
    }

    public function formatPrice(float $amount, string $currency): string
    {
        return match (strtoupper($currency)) {
            'EUR' => '€' . number_format($amount, 2),
            'GBP' => '£' . number_format($amount, 2),
            'USD' => '$' . number_format($amount, 2),
            default => $amount . ' ' . $currency,
        };
    }
}
