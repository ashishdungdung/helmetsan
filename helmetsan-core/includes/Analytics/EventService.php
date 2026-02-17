<?php

declare(strict_types=1);

namespace Helmetsan\Core\Analytics;

final class EventService
{
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
            'permission_callback' => '__return_true',
        ]);
    }

    public function ingestEvent(\WP_REST_Request $request): \WP_REST_Response
    {
        if (! $this->events->tableExists()) {
            return new \WP_REST_Response(['ok' => false, 'message' => 'Event table unavailable'], 503);
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
}
