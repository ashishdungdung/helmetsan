<?php

declare(strict_types=1);

namespace Helmetsan\Core\API;

/**
 * API Gateway middleware for the Structured Data API.
 *
 * Handles authentication (API keys), tiered rate limiting, search-engine bot
 * bypass, usage tracking, and response field filtering. Designed to sit
 * between the incoming request and the DataApiController payload builders.
 *
 * Tiers:
 *   - public:      No API key, IP-based rate limit (60/hour), limited fields
 *   - registered:  Free API key, daily cap (500/day), full payload
 *   - premium:     Paid API key, daily cap (10,000/day), full + price_history
 *   - bot:         Known search engine crawler, unlimited, full payload
 */
final class ApiGateway
{
    /** @var array<string,int> Default rate limits per tier */
    private const TIER_DEFAULTS = [
        'public'     => 60,      // per hour (IP-based)
        'registered' => 500,     // per day (key-based)
        'premium'    => 10000,   // per day (key-based)
    ];

    private const PUBLIC_WINDOW_SECONDS = 3600; // 1 hour for public tier

    /** @var string[] Known bot User-Agent substrings (case-insensitive) */
    private const BOT_SIGNATURES = [
        'googlebot', 'bingbot', 'slurp', 'duckduckbot', 'baiduspider',
        'yandexbot', 'facebot', 'ia_archiver', 'applebot', 'petalbot',
        'gptbot', 'chatgpt-user', 'oai-searchbot', 'claudebot', 'claude-web',
        'perplexitybot', 'anthropic-ai', 'google-extended', 'googleother',
        'applebot-extended', 'amazonbot', 'diffbot', 'tavily', 'youbot',
        'duckassistbot', 'bytespider', 'meta-externalagent', 'meta-externalfetcher',
    ];

    private string $table;

    public function __construct()
    {
        global $wpdb;
        $this->table = $wpdb->prefix . 'helmetsan_api_keys';
    }

    // ─── Authentication ─────────────────────────────────────────────────

    /**
     * Determine the request's API context (tier, key record, client IP).
     *
     * @return array{tier:string, key:array|null, ip:string, error:array|null}
     */
    public function authorize(): array
    {
        $ip = $this->getClientIp();

        // 1. Check for known search engine bots (bypass everything)
        if ($this->isSearchBot()) {
            return ['tier' => 'bot', 'key' => null, 'ip' => $ip, 'error' => null];
        }

        // 2. Check for API key in header or query param
        $rawKey = $this->extractApiKey();
        if ($rawKey === '') {
            return ['tier' => 'public', 'key' => null, 'ip' => $ip, 'error' => null];
        }

        // 3. Look up the hashed key in the database
        $keyRecord = $this->lookupKey($rawKey);
        if ($keyRecord === null) {
            return [
                'tier'  => 'public',
                'key'   => null,
                'ip'    => $ip,
                'error' => [
                    'code'    => 401,
                    'message' => 'Invalid API key. Request a key at https://helmetsan.com/api/',
                ],
            ];
        }

        if (!(bool) $keyRecord['is_active']) {
            return [
                'tier'  => 'public',
                'key'   => null,
                'ip'    => $ip,
                'error' => [
                    'code'    => 403,
                    'message' => 'API key has been revoked.',
                ],
            ];
        }

        return [
            'tier'  => $keyRecord['tier'],
            'key'   => $keyRecord,
            'ip'    => $ip,
            'error' => null,
        ];
    }

    // ─── Rate Limiting ──────────────────────────────────────────────────

    /**
     * Enforce rate limits based on the resolved tier.
     *
     * @param array{tier:string, key:array|null, ip:string} $ctx
     * @return array|null Null if allowed; error array if rate-limited.
     */
    public function enforceRateLimit(array $ctx): ?array
    {
        $tier = $ctx['tier'];

        // Bots are exempt
        if ($tier === 'bot') {
            return null;
        }

        if ($tier === 'public') {
            return $this->enforceIpRateLimit($ctx['ip']);
        }

        // Registered/Premium: daily cap based on key
        return $this->enforceKeyRateLimit($ctx['key']);
    }

    /**
     * Get rate limit headers for the response.
     *
     * @return array<string,string>
     */
    public function getRateLimitHeaders(array $ctx): array
    {
        $tier = $ctx['tier'];

        if ($tier === 'bot') {
            return ['X-RateLimit-Tier' => 'bot'];
        }

        if ($tier === 'public') {
            $ip = $ctx['ip'];
            $key = 'hs_data_api_' . substr(hash('sha256', $ip), 0, 16);
            $current = (int) get_transient($key);
            $limit = self::TIER_DEFAULTS['public'];

            return [
                'X-RateLimit-Tier'      => 'public',
                'X-RateLimit-Limit'     => (string) $limit,
                'X-RateLimit-Remaining' => (string) max(0, $limit - $current),
                'X-RateLimit-Window'    => '3600',
            ];
        }

        // Key-based tiers
        $keyRecord = $ctx['key'];
        $dailyLimit = (int) ($keyRecord['daily_limit'] ?? self::TIER_DEFAULTS[$tier] ?? 500);
        $dailyKey = 'hs_data_api_key_' . ($keyRecord['key_prefix'] ?? 'unknown');
        $used = (int) get_transient($dailyKey);

        return [
            'X-RateLimit-Tier'      => $tier,
            'X-RateLimit-Limit'     => (string) $dailyLimit,
            'X-RateLimit-Remaining' => (string) max(0, $dailyLimit - $used),
            'X-RateLimit-Window'    => '86400',
        ];
    }

    // ─── Response Field Filtering ───────────────────────────────────────

    /**
     * Filter a full payload based on the tier's allowed fields.
     *
     * @param array $payload Full product payload
     * @param string $tier   The resolved tier
     * @return array Filtered payload
     */
    public function filterPayload(array $payload, string $tier): array
    {
        // Bots, registered, and premium get full metadata + specs + pricing + reviews
        if (in_array($tier, ['bot', 'registered', 'premium'], true)) {
            // Premium gets extra fields (price_history, affiliate_links)
            if ($tier !== 'premium') {
                unset($payload['price_history'], $payload['affiliate_links']);
            }
            return $payload;
        }

        // Public tier: metadata + basic specs only (no pricing, no reviews)
        unset(
            $payload['pricing'],
            $payload['reviews'],
            $payload['price_history'],
            $payload['affiliate_links']
        );

        // Strip advanced spec fields for public
        if (isset($payload['specifications'])) {
            $basicKeys = [
                'homologation', 'sharp_rating', 'weight', 'shell',
                'head_shape', 'noise_db', 'ventilation_score',
                // Accessory
                'accessory_type', 'brand', 'material',
                // Motorcycle
                'engine_cc', 'engine_type', 'power_hp', 'weight_kg',
            ];
            $payload['specifications'] = array_intersect_key(
                $payload['specifications'],
                array_flip($basicKeys)
            );
        }

        $payload['_notice'] = 'Public tier: limited data. Register for a free API key at https://helmetsan.com/api/ for full pricing and reviews.';

        return $payload;
    }

    // ─── Usage Tracking ─────────────────────────────────────────────────

    /**
     * Record a successful API request against a key.
     */
    public function recordUsage(array $ctx): void
    {
        if ($ctx['key'] === null) {
            return;
        }

        global $wpdb;
        $wpdb->query($wpdb->prepare(
            "UPDATE {$this->table} SET total_requests = total_requests + 1, last_used = %s WHERE id = %d",
            current_time('mysql'),
            (int) $ctx['key']['id']
        ));
    }

    // ─── Key Management (for CLI/Admin) ─────────────────────────────────

    /**
     * Generate a new API key.
     *
     * @return array{raw_key:string, prefix:string, hash:string}
     */
    public static function generateKey(): array
    {
        $raw = 'hs_pk_' . bin2hex(random_bytes(24)); // 54 chars total
        $prefix = substr($raw, 0, 12); // "hs_pk_" + 6 hex chars
        $hash = hash('sha256', $raw);

        return ['raw_key' => $raw, 'prefix' => $prefix, 'hash' => $hash];
    }

    /**
     * Create and store a new API key.
     *
     * @return array{raw_key:string, prefix:string}|null
     */
    public function createKey(string $label, string $tier = 'registered', string $email = '', ?int $dailyLimit = null): ?array
    {
        $keyData = self::generateKey();

        if ($dailyLimit === null) {
            $dailyLimit = self::TIER_DEFAULTS[$tier] ?? 500;
        }

        global $wpdb;
        $inserted = $wpdb->insert($this->table, [
            'api_key'     => $keyData['hash'],
            'key_prefix'  => $keyData['prefix'],
            'label'       => sanitize_text_field($label),
            'tier'        => in_array($tier, ['registered', 'premium'], true) ? $tier : 'registered',
            'daily_limit' => $dailyLimit,
            'owner_email' => sanitize_email($email),
            'is_active'   => 1,
            'created_at'  => current_time('mysql'),
        ]);

        if (!$inserted) {
            return null;
        }

        return ['raw_key' => $keyData['raw_key'], 'prefix' => $keyData['prefix']];
    }

    /**
     * List all API keys (without the hash).
     *
     * @return array[]
     */
    public function listKeys(): array
    {
        global $wpdb;
        return $wpdb->get_results(
            "SELECT id, key_prefix, label, tier, daily_limit, owner_email, is_active, created_at, last_used, total_requests 
             FROM {$this->table} ORDER BY created_at DESC",
            ARRAY_A
        ) ?: [];
    }

    /**
     * Revoke (deactivate) a key by its prefix.
     *
     * @return bool
     */
    public function revokeKey(string $prefix): bool
    {
        global $wpdb;
        $affected = $wpdb->update(
            $this->table,
            ['is_active' => 0],
            ['key_prefix' => sanitize_text_field($prefix)]
        );
        return $affected > 0;
    }

    // ─── Private Helpers ────────────────────────────────────────────────

    /**
     * Extract API key from X-Api-Key header or ?api_key query param.
     */
    private function extractApiKey(): string
    {
        // Header takes priority
        $header = $_SERVER['HTTP_X_API_KEY'] ?? '';
        if ($header !== '') {
            return sanitize_text_field((string) $header);
        }

        // Query param fallback
        return isset($_GET['api_key']) ? sanitize_text_field((string) $_GET['api_key']) : '';
    }

    /**
     * Look up a key by its raw value (hashed for comparison).
     */
    private function lookupKey(string $rawKey): ?array
    {
        global $wpdb;
        $hash = hash('sha256', $rawKey);

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table} WHERE api_key = %s LIMIT 1",
            $hash
        ), ARRAY_A);

        return is_array($row) ? $row : null;
    }

    /**
     * IP-based rate limiting for public tier (transient-based, 60/hour).
     */
    private function enforceIpRateLimit(string $ip): ?array
    {
        $key = 'hs_data_api_' . substr(hash('sha256', $ip), 0, 16);
        $current = (int) get_transient($key);
        $limit = self::TIER_DEFAULTS['public'];

        if ($current >= $limit) {
            return [
                'code'    => 429,
                'message' => 'Rate limit exceeded. Public tier allows ' . $limit . ' requests per hour. Register for a free API key at https://helmetsan.com/api/',
            ];
        }

        set_transient($key, $current + 1, self::PUBLIC_WINDOW_SECONDS);
        return null;
    }

    /**
     * Key-based daily rate limiting for registered/premium tiers.
     */
    private function enforceKeyRateLimit(array $keyRecord): ?array
    {
        $dailyLimit = (int) ($keyRecord['daily_limit'] ?? 500);
        $dailyKey = 'hs_data_api_key_' . $keyRecord['key_prefix'];
        $current = (int) get_transient($dailyKey);

        if ($current >= $dailyLimit) {
            return [
                'code'    => 429,
                'message' => 'Daily rate limit exceeded (' . $dailyLimit . ' requests/day). Upgrade to premium for higher limits.',
            ];
        }

        // Increment (set with 24h TTL if first request of the window)
        if ($current === 0) {
            set_transient($dailyKey, 1, DAY_IN_SECONDS);
        } else {
            set_transient($dailyKey, $current + 1, DAY_IN_SECONDS);
        }

        return null;
    }

    /**
     * Detect known search engine and AI crawlers by User-Agent, Cloudflare bot status, or IP verification.
     */
    private function isSearchBot(): bool
    {
        // 1. Check Cloudflare verified bot header (added by CF Bot Management)
        if (isset($_SERVER['HTTP_CF_VERIFIED_BOT']) && $_SERVER['HTTP_CF_VERIFIED_BOT'] === 'true') {
            return true;
        }

        $ua = strtolower($_SERVER['HTTP_USER_AGENT'] ?? '');
        if ($ua === '') {
            return false;
        }

        foreach (self::BOT_SIGNATURES as $sig) {
            if (str_contains($ua, $sig)) {
                // Perform reverse DNS verification for major search bots to prevent spoofing
                if ($sig === 'googlebot' || $sig === 'google-extended') {
                    return $this->verifyGoogleBot();
                }
                if ($sig === 'bingbot') {
                    return $this->verifyBingBot();
                }
                // For other bots (like GPTbot, ClaudeBot), assume User-Agent is sufficient
                return true;
            }
        }

        return false;
    }

    /**
     * Verify Googlebot connecting IP matches reverse DNS rules.
     */
    private function verifyGoogleBot(): bool
    {
        $ip = $this->getClientIp();
        if ($ip === '0.0.0.0') {
            return false;
        }

        $cacheKey = 'hs_bot_verify_g_' . md5($ip);
        $cached = get_transient($cacheKey);
        if ($cached !== false) {
            return $cached === 'yes';
        }

        $hostname = gethostbyaddr($ip);
        $isGoogle = false;
        if ($hostname !== false) {
            $isGoogle = str_ends_with($hostname, '.googlebot.com') || str_ends_with($hostname, '.google.com');
            if ($isGoogle) {
                $resolvedIp = gethostbyname($hostname);
                $isGoogle = ($resolvedIp === $ip);
            }
        }

        set_transient($cacheKey, $isGoogle ? 'yes' : 'no', DAY_IN_SECONDS);
        return $isGoogle;
    }

    /**
     * Verify Bingbot connecting IP matches reverse DNS rules.
     */
    private function verifyBingBot(): bool
    {
        $ip = $this->getClientIp();
        if ($ip === '0.0.0.0') {
            return false;
        }

        $cacheKey = 'hs_bot_verify_b_' . md5($ip);
        $cached = get_transient($cacheKey);
        if ($cached !== false) {
            return $cached === 'yes';
        }

        $hostname = gethostbyaddr($ip);
        $isBing = false;
        if ($hostname !== false) {
            $isBing = str_ends_with($hostname, '.search.msn.com');
            if ($isBing) {
                $resolvedIp = gethostbyname($hostname);
                $isBing = ($resolvedIp === $ip);
            }
        }

        set_transient($cacheKey, $isBing ? 'yes' : 'no', DAY_IN_SECONDS);
        return $isBing;
    }

    /**
     * Get the client IP from Cloudflare / proxy headers.
     */
    private function getClientIp(): string
    {
        $headers = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'];

        foreach ($headers as $header) {
            $value = isset($_SERVER[$header]) ? (string) $_SERVER[$header] : '';
            if ($value !== '') {
                $ip = trim(explode(',', $value)[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP) !== false) {
                    return $ip;
                }
            }
        }

        return '0.0.0.0';
    }
}
