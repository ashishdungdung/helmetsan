<?php

declare(strict_types=1);

namespace Helmetsan\Core\Price;

/**
 * Handles exchange rates and currency conversion.
 *
 * Fetches USD-based exchange rates from the open exchange rate API
 * and caches them in a transient. Provides fallback rates in case
 * of network or API failure to ensure the system is completely robust.
 */
final class ExchangeRateService
{
    private const TRANSIENT_KEY = 'hs_exchange_rates';
    private const TRANSIENT_TTL = 43200; // 12 hours

    /**
     * Fallback rates keyed by currency code (base USD).
     *
     * @var array<string, float>
     */
    private const FALLBACK_RATES = [
        'USD' => 1.0,
        'EUR' => 0.92,
        'GBP' => 0.79,
        'INR' => 86.5,
        'JPY' => 155.0,
        'CAD' => 1.38,
        'AUD' => 1.52,
        'MXN' => 19.5,
        'BRL' => 5.60,
        'PLN' => 4.0,
        'AED' => 3.67,
        'NGN' => 1500.0,
        'KES' => 129.0,
        'EGP' => 49.0,
        'MAD' => 9.8,
        'GHS' => 15.5,
        'UGX' => 3700.0,
        'TZS' => 2600.0,
        'CHF' => 0.88,
        'NZD' => 1.65,
        'SGD' => 1.34,
        'SEK' => 10.4,
        'NOK' => 10.6,
        'SAR' => 3.75,
        'KRW' => 1350.0,
    ];

    /**
     * Get USD-based exchange rates.
     *
     * @return array<string, float>
     */
    public function getRates(): array
    {
        $rates = get_transient(self::TRANSIENT_KEY);

        if (is_array($rates) && !empty($rates)) {
            return $rates;
        }

        $rates = $this->fetchRates();

        if (!empty($rates)) {
            set_transient(self::TRANSIENT_KEY, $rates, self::TRANSIENT_TTL);
            return $rates;
        }

        // Cache fallback rates for 5 minutes if API fails to prevent consecutive 8s timeouts
        set_transient(self::TRANSIENT_KEY, self::FALLBACK_RATES, 300);

        return self::FALLBACK_RATES;
    }

    /**
     * Convert an amount from one currency to another.
     */
    public function convert(float $amount, string $from, string $to): float
    {
        $from = strtoupper(trim($from));
        $to = strtoupper(trim($to));

        if ($from === $to) {
            return $amount;
        }

        $rates = $this->getRates();

        $fromRate = $rates[$from] ?? null;
        $toRate = $rates[$to] ?? null;

        // If either currency is not found, fallback to static mapping or USD direct
        if ($fromRate === null) {
            $fromRate = self::FALLBACK_RATES[$from] ?? 1.0;
        }
        if ($toRate === null) {
            $toRate = self::FALLBACK_RATES[$to] ?? 1.0;
        }

        // Guard against division by zero
        $fromRate = ($fromRate > 0) ? $fromRate : 1.0;

        // Convert $from to USD first, then to $to
        $usdAmount = $amount / $fromRate;
        return $usdAmount * $toRate;
    }

    /**
     * Apply country-specific VAT or sales tax.
     */
    public function applyVat(float $amount, string $country): float
    {
        $country = strtoupper(trim($country));
        
        // VAT Rates Mapping
        $vatRates = [
            'DE' => 1.19, // 19%
            'FR' => 1.20, // 20%
            'IT' => 1.22, // 22%
            'ES' => 1.21, // 21%
            'GB' => 1.20, // 20%
            'UK' => 1.20, // 20%
            'PL' => 1.23, // 23%
            'AT' => 1.20, 'BE' => 1.21, 'BG' => 1.20, 'CY' => 1.19, 'CZ' => 1.21,
            'DK' => 1.25, 'EE' => 1.22, 'FI' => 1.24, 'GR' => 1.24, 'HR' => 1.25,
            'HU' => 1.27, 'IE' => 1.23, 'LT' => 1.21, 'LU' => 1.17, 'LV' => 1.21,
            'MT' => 1.18, 'NL' => 1.21, 'PT' => 1.23, 'RO' => 1.19, 'SE' => 1.25,
            'SI' => 1.22, 'SK' => 1.20
        ];

        $rate = $vatRates[$country] ?? 1.0;
        return $amount * $rate;
    }

    /**
     * Apply psychological rounding (charm pricing) to a currency amount.
     */
    public function charmRound(float $amount, string $currency): float
    {
        if ($amount <= 0.0) {
            return 0.0;
        }
        if ($amount < 5.0) {
            return $amount;
        }

        $currency = strtoupper(trim($currency));
        $decimalCurrencies = ['USD', 'EUR', 'GBP', 'CAD', 'AUD', 'MXN', 'BRL', 'PLN'];

        if (in_array($currency, $decimalCurrencies, true)) {
            $rounded = round($amount);
            if ($currency === 'EUR' || $currency === 'GBP') {
                return $rounded - 0.01;
            }
            return $rounded - 0.05;
        }

        // Zero-decimal currencies
        if ($amount < 1000.0) {
            return round($amount / 10.0) * 10.0 - 1.0;
        } else {
            return round($amount / 100.0) * 100.0 - 10.0;
        }
    }

    /**
     * Fetch fresh rates from the exchange rate API.
     *
     * @return array<string, float>
     */
    private function fetchRates(): array
    {
        $url = 'https://open.er-api.com/v6/latest/USD';
        $response = wp_remote_get($url, [
            'timeout' => 8,
        ]);

        if (is_wp_error($response)) {
            return [];
        }

        $body = wp_remote_retrieve_body($response);
        if ($body === '') {
            return [];
        }

        $data = json_decode($body, true);
        if (!is_array($data) || ($data['result'] ?? '') !== 'success' || !isset($data['rates']) || !is_array($data['rates'])) {
            return [];
        }

        $rates = [];
        foreach ($data['rates'] as $currency => $rate) {
            if (is_numeric($rate)) {
                $rates[strtoupper($currency)] = (float) $rate;
            }
        }

        return $rates;
    }
}
