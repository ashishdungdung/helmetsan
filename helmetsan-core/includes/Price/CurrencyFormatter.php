<?php

declare(strict_types=1);

namespace Helmetsan\Core\Price;

/**
 * Multi-currency formatting engine.
 *
 * Supports 18 currencies aligned with GeoService::COUNTRY_MAP.
 * Each currency defines symbol, position (before/after amount),
 * decimal places, and thousand/decimal separators.
 */
final class CurrencyFormatter
{
    /** @var array<string, array{symbol: string, position: string, decimals: int, thousands: string, decimal: string}> */
    private const CURRENCIES = [
        'USD' => ['symbol' => '$',    'position' => 'before', 'decimals' => 2, 'thousands' => ',', 'decimal' => '.'],
        'EUR' => ['symbol' => '€',    'position' => 'before', 'decimals' => 2, 'thousands' => '.', 'decimal' => ','],
        'GBP' => ['symbol' => '£',    'position' => 'before', 'decimals' => 2, 'thousands' => ',', 'decimal' => '.'],
        'INR' => ['symbol' => '₹',    'position' => 'before', 'decimals' => 0, 'thousands' => ',', 'decimal' => '.'],
        'JPY' => ['symbol' => '¥',    'position' => 'before', 'decimals' => 0, 'thousands' => ',', 'decimal' => '.'],
        'AUD' => ['symbol' => 'A$',   'position' => 'before', 'decimals' => 2, 'thousands' => ',', 'decimal' => '.'],
        'CAD' => ['symbol' => 'CA$',  'position' => 'before', 'decimals' => 2, 'thousands' => ',', 'decimal' => '.'],
        'MXN' => ['symbol' => 'MX$',  'position' => 'before', 'decimals' => 2, 'thousands' => ',', 'decimal' => '.'],
        'BRL' => ['symbol' => 'R$',   'position' => 'before', 'decimals' => 2, 'thousands' => '.', 'decimal' => ','],
        'PLN' => ['symbol' => 'zł',   'position' => 'after',  'decimals' => 2, 'thousands' => ' ', 'decimal' => ','],
        'AED' => ['symbol' => 'AED ', 'position' => 'before', 'decimals' => 2, 'thousands' => ',', 'decimal' => '.'],
        'NGN' => ['symbol' => '₦',    'position' => 'before', 'decimals' => 0, 'thousands' => ',', 'decimal' => '.'],
        'KES' => ['symbol' => 'KSh ', 'position' => 'before', 'decimals' => 0, 'thousands' => ',', 'decimal' => '.'],
        'EGP' => ['symbol' => 'E£',   'position' => 'before', 'decimals' => 2, 'thousands' => ',', 'decimal' => '.'],
        'MAD' => ['symbol' => ' MAD', 'position' => 'after',  'decimals' => 2, 'thousands' => ' ', 'decimal' => ','],
        'GHS' => ['symbol' => 'GH₵',  'position' => 'before', 'decimals' => 2, 'thousands' => ',', 'decimal' => '.'],
        'UGX' => ['symbol' => 'USh ', 'position' => 'before', 'decimals' => 0, 'thousands' => ',', 'decimal' => '.'],
        'TZS' => ['symbol' => 'TSh ', 'position' => 'before', 'decimals' => 0, 'thousands' => ',', 'decimal' => '.'],
    ];

    /**
     * Format a price amount with the correct currency symbol and locale conventions.
     */
    public function format(float $amount, string $currency): string
    {
        $code = strtoupper($currency);
        $cfg  = self::CURRENCIES[$code] ?? null;

        if ($cfg === null) {
            return number_format($amount, 2) . ' ' . $code;
        }

        $formatted = number_format($amount, $cfg['decimals'], $cfg['decimal'], $cfg['thousands']);

        return $cfg['position'] === 'before'
            ? $cfg['symbol'] . $formatted
            : $formatted . $cfg['symbol'];
    }

    /**
     * Get the symbol for a currency code.
     */
    public function symbol(string $currency): string
    {
        $code = strtoupper($currency);

        return trim(self::CURRENCIES[$code]['symbol'] ?? $code);
    }

    /**
     * Whether a currency code is supported.
     */
    public function isSupported(string $currency): bool
    {
        return isset(self::CURRENCIES[strtoupper($currency)]);
    }

    /**
     * Get number of decimal places for a currency.
     */
    public function decimals(string $currency): int
    {
        $code = strtoupper($currency);

        return self::CURRENCIES[$code]['decimals'] ?? 2;
    }
}
