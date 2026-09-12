<?php

declare(strict_types=1);

namespace Helmetsan\Core\Geo;

/**
 * Detects the visitor's country from the HTTP request.
 *
 * Detection chain (fastest to slowest):
 * 1. CloudFlare CF-IPCountry header (free on all CF plans)
 * 2. WordPress geo cookie (set by previous visit)
 * 3. MaxMind GeoLite2 DB lookup (fallback)
 * 4. Default to 'US'
 *
 * Once detected, the country is cached in a cookie for 24 hours.
 */
final class GeoService
{
    private const COOKIE_NAME = 'helmetsan_geo';
    private const COOKIE_TTL  = 86400; // 24 hours

    /** @var array<string, array{region: string, currency: string, name: string, symbol: string, flag: string}> */
    private const COUNTRY_MAP = [
        // Asia-Pacific
        'IN' => ['region' => 'APAC', 'currency' => 'INR', 'name' => 'India',                'symbol' => '₹',    'flag' => '🇮🇳'],
        'JP' => ['region' => 'APAC', 'currency' => 'JPY', 'name' => 'Japan',                'symbol' => '¥',    'flag' => '🇯🇵'],
        'AU' => ['region' => 'APAC', 'currency' => 'AUD', 'name' => 'Australia',            'symbol' => 'A$',   'flag' => '🇦🇺'],
        'NZ' => ['region' => 'APAC', 'currency' => 'NZD', 'name' => 'New Zealand',          'symbol' => 'NZ$',  'flag' => '🇳🇿'],
        'SG' => ['region' => 'APAC', 'currency' => 'SGD', 'name' => 'Singapore',            'symbol' => 'S$',   'flag' => '🇸🇬'],
        'KR' => ['region' => 'APAC', 'currency' => 'KRW', 'name' => 'South Korea',          'symbol' => '₩',    'flag' => '🇰🇷'],

        // North America
        'US' => ['region' => 'NA',   'currency' => 'USD', 'name' => 'United States',        'symbol' => '$',    'flag' => '🇺🇸'],
        'CA' => ['region' => 'NA',   'currency' => 'CAD', 'name' => 'Canada',               'symbol' => 'CA$',  'flag' => '🇨🇦'],
        'MX' => ['region' => 'NA',   'currency' => 'MXN', 'name' => 'Mexico',               'symbol' => 'MX$',  'flag' => '🇲🇽'],

        // Europe & UK
        'GB' => ['region' => 'EU',   'currency' => 'GBP', 'name' => 'United Kingdom',       'symbol' => '£',    'flag' => '🇬🇧'],
        'UK' => ['region' => 'EU',   'currency' => 'GBP', 'name' => 'United Kingdom',       'symbol' => '£',    'flag' => '🇬🇧'],
        'DE' => ['region' => 'EU',   'currency' => 'EUR', 'name' => 'Germany',              'symbol' => '€',    'flag' => '🇩🇪'],
        'FR' => ['region' => 'EU',   'currency' => 'EUR', 'name' => 'France',               'symbol' => '€',    'flag' => '🇫🇷'],
        'IT' => ['region' => 'EU',   'currency' => 'EUR', 'name' => 'Italy',                'symbol' => '€',    'flag' => '🇮🇹'],
        'ES' => ['region' => 'EU',   'currency' => 'EUR', 'name' => 'Spain',                'symbol' => '€',    'flag' => '🇪🇸'],
        'NL' => ['region' => 'EU',   'currency' => 'EUR', 'name' => 'Netherlands',          'symbol' => '€',    'flag' => '🇳🇱'],
        'PL' => ['region' => 'EU',   'currency' => 'PLN', 'name' => 'Poland',               'symbol' => 'zł',   'flag' => '🇵🇱'],
        'BE' => ['region' => 'EU',   'currency' => 'EUR', 'name' => 'Belgium',              'symbol' => '€',    'flag' => '🇧🇪'],
        'CH' => ['region' => 'EU',   'currency' => 'CHF', 'name' => 'Switzerland',          'symbol' => 'CHF ', 'flag' => '🇨🇭'],
        'SE' => ['region' => 'EU',   'currency' => 'SEK', 'name' => 'Sweden',               'symbol' => ' kr',  'flag' => '🇸🇪'],
        'NO' => ['region' => 'EU',   'currency' => 'NOK', 'name' => 'Norway',               'symbol' => ' kr',  'flag' => '🇳🇴'],

        // Middle East & Latin America
        'AE' => ['region' => 'ME',   'currency' => 'AED', 'name' => 'United Arab Emirates', 'symbol' => 'AED ', 'flag' => '🇦🇪'],
        'SA' => ['region' => 'ME',   'currency' => 'SAR', 'name' => 'Saudi Arabia',         'symbol' => 'SAR ', 'flag' => '🇸🇦'],
        'BR' => ['region' => 'SA',   'currency' => 'BRL', 'name' => 'Brazil',               'symbol' => 'R$',   'flag' => '🇧🇷'],

        // Africa
        'NG' => ['region' => 'AF',   'currency' => 'NGN', 'name' => 'Nigeria',              'symbol' => '₦',    'flag' => '🇳🇬'],
        'KE' => ['region' => 'AF',   'currency' => 'KES', 'name' => 'Kenya',                'symbol' => 'KSh ', 'flag' => '🇰🇪'],
        'EG' => ['region' => 'AF',   'currency' => 'EGP', 'name' => 'Egypt',                'symbol' => 'E£',   'flag' => '🇪🇬'],
        'MA' => ['region' => 'AF',   'currency' => 'MAD', 'name' => 'Morocco',              'symbol' => 'MAD',  'flag' => '🇲🇦'],
        'GH' => ['region' => 'AF',   'currency' => 'GHS', 'name' => 'Ghana',                'symbol' => 'GH₵',  'flag' => '🇬🇭'],
        'UG' => ['region' => 'AF',   'currency' => 'UGX', 'name' => 'Uganda',               'symbol' => 'USh ', 'flag' => '🇺🇬'],
        'TZ' => ['region' => 'AF',   'currency' => 'TZS', 'name' => 'Tanzania',             'symbol' => 'TSh ', 'flag' => '🇹🇿'],
    ];

    private ?string $resolvedCountry = null;
    private ?ComplianceService $compliance = null;

    public function compliance(): ComplianceService
    {
        if ($this->compliance === null) {
            $this->compliance = new ComplianceService();
        }

        return $this->compliance;
    }

    /**
     * Get the visitor's ISO 3166-1 alpha-2 country code.
     */
    public function getCountry(): string
    {
        if ($this->resolvedCountry !== null) {
            return $this->resolvedCountry;
        }

        // 0. Check for Forced Mode (Debug/Dev)
        $config = $this->getGeoConfig();
        if (($config['mode'] ?? 'auto') === 'force' && ! empty($config['force_country'])) {
            $this->resolvedCountry = strtoupper(substr((string) $config['force_country'], 0, 2));
            return $this->resolvedCountry;
        }

        // 1. Check for active query parameter override (earliest hook safety)
        if (isset($_GET['country'])) {
            $paramCc = strtoupper(substr(preg_replace('/[^a-zA-Z]/', '', (string) $_GET['country']), 0, 2));
            if (strlen($paramCc) === 2) {
                $this->resolvedCountry = $paramCc;
                $this->setCookie($paramCc);
                return $paramCc;
            }
        }

        // 2. Cached cookie (user manual selection has priority)
        $cookie = $this->fromCookie();
        if ($cookie !== '') {
            $this->resolvedCountry = $cookie;
            return $cookie;
        }

        // 3. CloudFlare header (auto-detection behind CF)
        $cf = $this->fromCloudFlare();
        if ($cf !== '') {
            $this->resolvedCountry = $cf;
            // Note: Do NOT set a cookie here on passive auto-detection.
            // CF-IPCountry is forwarded on every request; emitting Set-Cookie
            // on cacheable GET responses causes downstream shared cache poisoning.
            return $cf;
        }

        // 4. Fallback to default
        $this->resolvedCountry = 'IN';

        return $this->resolvedCountry;
    }

    /**
     * Get the region code for a country (NA, EU, APAC, SA, ME, AF).
     */
    public function getRegion(?string $countryCode = null): string
    {
        $cc = $countryCode ?? $this->getCountry();
        $map = $this->getSupportedCountries();

        return $map[strtoupper($cc)]['region'] ?? 'NA';
    }

    /**
     * Get the default currency for a country.
     */
    public function getCurrency(?string $countryCode = null): string
    {
        $cc = $countryCode ?? $this->getCountry();
        $map = $this->getSupportedCountries();

        return $map[strtoupper($cc)]['currency'] ?? 'USD';
    }

    /**
     * Override the detected country (useful for ?country=XX query param).
     */
    public function setCountry(string $countryCode): void
    {
        $cc = strtoupper(substr(preg_replace('/[^a-zA-Z]/', '', $countryCode), 0, 2));
        if (strlen($cc) === 2) {
            $this->resolvedCountry = $cc;
            $this->setCookie($cc);
        }
    }

    /**
     * Get the full geo context for the current visitor.
     *
     * @return array{country: string, region: string, currency: string}
     */
    public function getContext(): array
    {
        $cc = $this->getCountry();

        return [
            'country'  => $cc,
            'region'   => $this->getRegion($cc),
            'currency' => $this->getCurrency($cc),
        ];
    }

    /**
     * Register WordPress hooks for geo detection.
     */
    public function register(): void
    {
        // Allow manual country override via ?country=XX
        add_action('template_redirect', function (): void {
            if (isset($_GET['country'])) {
                $override = sanitize_text_field((string) $_GET['country']);
                if (strlen($override) === 2) {
                    $this->setCountry($override);
                }
            }
        }, 1);
    }

    // ─── Private Helpers ────────────────────────────────────────────────

    /**
     * @return array<string, mixed>
     */
    private function getGeoConfig(): array
    {
        // Avoid dependency on Config class constant if not autoloaded, but it should be.
        // Using string literal 'helmetsan_geo' to be safe or Config::OPTION_GEO if available.
        return (array) get_option('helmetsan_geo', []);
    }

    /**
     * Get the list of all supported countries.
     *
     * @return array<string, array{region: string, currency: string, name: string, symbol: string, flag: string}>
     */
    public static function getSupportedCountries(): array
    {
        return self::COUNTRY_MAP;
    }

    private function fromCloudFlare(): string
    {
        // CloudFlare sets this header on every request
        $header = $_SERVER['HTTP_CF_IPCOUNTRY'] ?? '';
        $cc = strtoupper(trim((string) $header));

        if ($cc !== '' && $cc !== 'XX' && $cc !== 'T1' && strlen($cc) === 2) {
            return $cc;
        }

        return '';
    }

    private function fromCookie(): string
    {
        $value = $_COOKIE[self::COOKIE_NAME] ?? '';
        $cc = strtoupper(trim((string) $value));

        if ($cc !== '' && strlen($cc) === 2 && ctype_alpha($cc) && isset(self::COUNTRY_MAP[$cc])) {
            return $cc;
        }

        return '';
    }

    private function setCookie(string $countryCode): void
    {
        if (headers_sent()) {
            return;
        }

        setcookie(
            self::COOKIE_NAME,
            $countryCode,
            [
                'expires'  => time() + self::COOKIE_TTL,
                'path'     => '/',
                'secure'   => function_exists('is_ssl') ? is_ssl() : (! empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
                'httponly'  => false,
                'samesite'  => 'Lax',
            ]
        );
    }
}
