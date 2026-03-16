<?php

declare(strict_types=1);

namespace Helmetsan\Core\Support;

/**
 * Handles exponential backoff and rate limiting using WordPress transients.
 */
final class RateLimiter
{
    private const TRANSIENT_PREFIX = 'hs_rate_limit_';
    private const DEFAULT_BACKOFF_SECONDS = 60;
    private const MAX_BACKOFF_SECONDS = 3600; // 1 hour

    /**
     * Check if a specific key is currently rate-limited.
     *
     * @param string $key e.g., 'revzilla', 'ai_search'
     * @return bool True if allowed to proceed, false if limited.
     */
    public function check(string $key): bool
    {
        $limitUntil = (int) get_transient(self::TRANSIENT_PREFIX . $key);
        if ($limitUntil > time()) {
            return false;
        }
        return true;
    }

    /**
     * Record a failure and increment the backoff period.
     *
     * @param string $key e.g., 'revzilla'
     * @param int $code HTTP response code (e.g., 429, 403, 503)
     */
    public function recordFailure(string $key, int $code): void
    {
        $failCountKey = self::TRANSIENT_PREFIX . 'fails_' . $key;
        $fails = 1;

        if (function_exists('wp_cache_incr')) {
            $fails = wp_cache_incr($failCountKey, 1, 'transient');
            if (false === $fails) {
                wp_cache_add($failCountKey, 1, 'transient', self::MAX_BACKOFF_SECONDS * 2);
                $fails = 1;
            }
            // Sync to transient for persistence across cache flushes
            set_transient($failCountKey, $fails, self::MAX_BACKOFF_SECONDS * 2);
        } else {
            $fails = (int) get_transient($failCountKey) + 1;
            set_transient($failCountKey, $fails, self::MAX_BACKOFF_SECONDS * 2);
        }

        // Exponential backoff: 60s, 120s, 240s, 480s, etc.
        $backoff = self::DEFAULT_BACKOFF_SECONDS * (2 ** ($fails - 1));
        $backoff = min($backoff, self::MAX_BACKOFF_SECONDS);

        set_transient(self::TRANSIENT_PREFIX . $key, time() + $backoff, $backoff);
    }

    /**
     * Reset failure count and limit for a key.
     */
    public function reset(string $key): void
    {
        delete_transient(self::TRANSIENT_PREFIX . $key);
        delete_transient(self::TRANSIENT_PREFIX . 'fails_' . $key);
    }

    /**
     * Get remaining wait time in seconds.
     */
    public function getRemainingDelay(string $key): int
    {
        $limitUntil = (int) get_transient(self::TRANSIENT_PREFIX . $key);
        return max(0, $limitUntil - time());
    }
}
