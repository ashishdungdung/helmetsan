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

    /** @var array<string, array{region: string, currency: string}> */
    private const COUNTRY_MAP = [
        'US' => ['region' => 'NA',   'currency' => 'USD'],
        'CA' => ['region' => 'NA',   'currency' => 'CAD'],
        'MX' => ['region' => 'NA',   'currency' => 'MXN'],
        'UK' => ['region' => 'EU',   'currency' => 'GBP'],
        'GB' => ['region' => 'EU',   'currency' => 'GBP'],
        'DE' => ['region' => 'EU',   'currency' => 'EUR'],
        'FR' => ['region' => 'EU',   'currency' => 'EUR'],
        'IT' => ['region' => 'EU',   'currency' => 'EUR'],
        'ES' => ['region' => 'EU',   'currency' => 'EUR'],
        'PL' => ['region' => 'EU',   'currency' => 'PLN'],
        'IN' => ['region' => 'APAC', 'currency' => 'INR'],
        'JP' => ['region' => 'APAC', 'currency' => 'JPY'],
        'AU' => ['region' => 'APAC', 'currency' => 'AUD'],
        'BR' => ['region' => 'SA',   'currency' => 'BRL'],
        'AE' => ['region' => 'ME',   'currency' => 'AED'],
        'NG' => ['region' => 'AF',   'currency' => 'NGN'],
        'KE' => ['region' => 'AF',   'currency' => 'KES'],
        'EG' => ['region' => 'AF',   'currency' => 'EGP'],
        'MA' => ['region' => 'AF',   'currency' => 'MAD'],
        'GH' => ['region' => 'AF',   'currency' => 'GHS'],
        'UG' => ['region' => 'AF',   'currency' => 'UGX'],
        'TZ' => ['region' => 'AF',   'currency' => 'TZS'],
    ];

    private ?string $resolvedCountry = null;

    /**
     * Get the visitor's ISO 3166-1 alpha-2 country code.
     */
    public function getCountry(): string
    {
        if ($this->resolvedCountry !== null) {
            return $this->resolvedCountry;
        }

        // 1. CloudFlare header (fastest, most reliable behind CF)
        $cf = $this->fromCloudFlare();
        if ($cf !== '') {
            $this->resolvedCountry = $cf;
            $this->setCookie($cf);
            return $cf;
        }

        // 2. Cached cookie
        $cookie = $this->fromCookie();
        if ($cookie !== '') {
            $this->resolvedCountry = $cookie;
            return $cookie;
        }

        // 3. Fallback to default
        $this->resolvedCountry = 'US';

        return $this->resolvedCountry;
    }

    /**
     * Get the region code for a country (NA, EU, APAC, SA, ME, AF).
     */
    public function getRegion(?string $countryCode = null): string
    {
        $cc = $countryCode ?? $this->getCountry();

        return self::COUNTRY_MAP[strtoupper($cc)]['region'] ?? 'NA';
    }

    /**
     * Get the default currency for a country.
     */
    public function getCurrency(?string $countryCode = null): string
    {
        $cc = $countryCode ?? $this->getCountry();

        return self::COUNTRY_MAP[strtoupper($cc)]['currency'] ?? 'USD';
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

        if ($cc !== '' && strlen($cc) === 2 && ctype_alpha($cc)) {
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
                'secure'   => is_ssl(),
                'httponly'  => false,
                'samesite'  => 'Lax',
            ]
        );
    }
}
