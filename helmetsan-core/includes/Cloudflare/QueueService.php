<?php

declare(strict_types=1);

namespace Helmetsan\Core\Cloudflare;

use Helmetsan\Core\Support\Config;
use WP_Error;

/**
 * Handles dispatching messages to Cloudflare Queues via the Cloudflare API.
 */
class QueueService
{
    private string $accountId = '';
    private string $apiToken = '';
    private string $queueName = '';
    private bool $enabled = false;

    public function __construct(Config $config)
    {
        $mediaSettings = get_option(Config::OPTION_MEDIA, $config->mediaDefaults());
        $isQueueEnabled = !empty($mediaSettings['enable_cloudflare_queues']);

        // For MVP, look for these in CONSTANTS or env.
        $this->accountId = defined('HELMETSAN_CF_ACCOUNT_ID') ? HELMETSAN_CF_ACCOUNT_ID : '';
        $this->apiToken  = defined('HELMETSAN_CF_API_TOKEN') ? HELMETSAN_CF_API_TOKEN : '';
        $this->queueName = defined('HELMETSAN_CF_INGEST_QUEUE') ? HELMETSAN_CF_INGEST_QUEUE : 'helmetsan-ingest-queue';

        $this->enabled = $isQueueEnabled && !empty($this->accountId) && !empty($this->apiToken);
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Dispatches a single message to a Cloudflare Queue.
     *
     * @param array $payload The JSON-serializable message payload.
     * @return true|WP_Error True on success, WP_Error on failure.
     */
    public function dispatchMessage(array $payload)
    {
        if (!$this->isEnabled()) {
            return new WP_Error('cf_queues_disabled', 'Cloudflare Queues are not configured.');
        }

        $url = sprintf(
            'https://api.cloudflare.com/client/v4/accounts/%s/queues/%s/messages',
            $this->accountId,
            $this->queueName
        );

        $body = [
            // CF Queues API expects an array of messages
            [
                'body' => $payload
            ]
        ];

        $response = wp_remote_post($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->apiToken,
                'Content-Type'  => 'application/json',
            ],
            'body'    => json_encode($body),
            'timeout' => 15,
        ]);

        if (is_wp_error($response)) {
            return new WP_Error('cf_queue_network_error', 'Failed to reach Cloudflare: ' . $response->get_error_message());
        }

        $code = wp_remote_retrieve_response_code($response);
        $resBody = json_decode(wp_remote_retrieve_body($response), true);

        if ($code !== 200 || empty($resBody['success'])) {
            $err = $resBody['errors'][0]['message'] ?? 'Unknown Cloudflare API error.';
            return new WP_Error('cf_queue_api_error', "Cloudflare rejected the queue message: $err");
        }

        return true;
    }
}
