<?php

declare(strict_types=1);

namespace Helmetsan\Core\Analytics;

final class EventService
{
    private const RATE_LIMIT_MAX = 30;
    private const RATE_LIMIT_WINDOW = 60;

    /** @var array<int,string> */
    private array $allowedEvents = [
        'outbound_click',
        'internal_search',
        'directory_filter',
        'cta_click',
    ];

    public function __construct(private readonly EventRepository $events)
    {
    }

    public function register(): void
    {
        add_action('rest_api_init', [$this, 'registerRoutes']);
    }

    public function registerRoutes(): void
    {
        register_rest_route('helmetsan/v1', '/event', [
            'methods'             => 'POST',
            'callback'            => [$this, 'ingestEvent'],
            'permission_callback' => [$this, 'checkPermission'],
        ]);
    }

    /**
     * Verify nonce auth before allowing event writes.
     */
    public function checkPermission(\WP_REST_Request $request): bool|\WP_Error
    {
        $nonce = $request->get_header('X-WP-Nonce');

        if (empty($nonce)) {
            $payload = $request->get_json_params();
            $nonce = isset($payload['_wpnonce']) ? (string) $payload['_wpnonce'] : '';
        }

        if (empty($nonce) || wp_verify_nonce($nonce, 'helmetsan_event') === false) {
            return new \WP_Error(
                'helmetsan_unauthorized',
                'Invalid or missing security token.',
                ['status' => 403]
            );
        }

        return true;
    }

    public function ingestEvent(\WP_REST_Request $request): \WP_REST_Response
    {
        if (! $this->events->tableExists()) {
            return new \WP_REST_Response(['ok' => false, 'message' => 'Event table unavailable'], 503);
        }

        // IP-based rate limiting
        $rateLimitError = $this->enforceRateLimit();
        if ($rateLimitError !== null) {
            return $rateLimitError;
        }

        $payload = $request->get_json_params();
        if (! is_array($payload)) {
            return new \WP_REST_Response(['ok' => false, 'message' => 'Invalid payload'], 400);
        }

        $eventName = isset($payload['event_name']) ? sanitize_text_field((string) $payload['event_name']) : '';
        if ($eventName === '' || ! in_array($eventName, $this->allowedEvents, true)) {
            return new \WP_REST_Response(['ok' => false, 'message' => 'Unsupported event'], 400);
        }

        $meta = isset($payload['meta']) && is_array($payload['meta']) ? $payload['meta'] : [];
        $safeMeta = [];
        foreach ($meta as $k => $v) {
            $key = sanitize_key((string) $k);
            if ($key === '') {
                continue;
            }
            if (is_scalar($v)) {
                $safeMeta[$key] = substr((string) $v, 0, 300);
            }
        }

        $this->events->add([
            'event_name' => $eventName,
            'page_url'   => isset($payload['page_url']) ? (string) $payload['page_url'] : '',
            'referrer'   => isset($payload['referrer']) ? (string) $payload['referrer'] : '',
            'source'     => isset($payload['source']) ? (string) $payload['source'] : 'frontend',
            'meta'       => $safeMeta,
        ]);

        return new \WP_REST_Response(['ok' => true], 200);
    }

    /**
     * IP-based rate limiting using transients.
     *
     * @return \WP_REST_Response|null Response if rate-limited, null if allowed.
     */
    private function enforceRateLimit(): ?\WP_REST_Response
    {
        $ip = $this->getClientIp();
        $key = 'helmetsan_evt_rate_' . substr(hash('sha256', $ip), 0, 16);

        $current = (int) get_transient($key);

        if ($current >= self::RATE_LIMIT_MAX) {
            return new \WP_REST_Response(
                ['ok' => false, 'message' => 'Rate limit exceeded. Try again later.'],
                429
            );
        }

        set_transient($key, $current + 1, self::RATE_LIMIT_WINDOW);

        return null;
    }

    private function getClientIp(): string
    {
        $headers = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'];

        foreach ($headers as $header) {
            $value = isset($_SERVER[$header]) ? (string) $_SERVER[$header] : '';
            if ($value !== '') {
                // X-Forwarded-For may contain a comma-separated list; take first
                $ip = trim(explode(',', $value)[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP) !== false) {
                    return $ip;
                }
            }
        }

        return '0.0.0.0';
    }
}
