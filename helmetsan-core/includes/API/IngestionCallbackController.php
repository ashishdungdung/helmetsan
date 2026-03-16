<?php

declare(strict_types=1);

namespace Helmetsan\Core\API;

use Helmetsan\Core\Media\AssetManager;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Endpoint for Cloudflare Workers to signal that asset ingestion has completed.
 */
class IngestionCallbackController
{
    public function __construct(
        private readonly AssetManager $assetManager
    ) {}

    public function registerRoutes(): void
    {
        register_rest_route('helmetsan/v1', '/ingestion/callback', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [$this, 'handleCallback'],
            'permission_callback' => [$this, 'checkPermission'],
        ]);
    }

    public function checkPermission(WP_REST_Request $request): bool
    {
        // Require a pre-shared secret in the Authorization header to prevent unauthorized access.
        $token = $request->get_header('authorization');
        $expectedToken = defined('HELMETSAN_WEBHOOK_SECRET') ? HELMETSAN_WEBHOOK_SECRET : '';

        if (empty($expectedToken)) {
            // Unsafe to proceed without a configured secure token.
            return false;
        }

        // Expected format: "Bearer {token}"
        $providedToken = str_replace('Bearer ', '', (string)$token);

        return hash_equals($expectedToken, $providedToken);
    }

    public function handleCallback(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $helmetId = $request->get_param('helmet_id');
        $sourceUrl = $request->get_param('source_url');
        $r2Url = $request->get_param('r2_url');
        $photoType = $request->get_param('photo_type');

        if (empty($helmetId) || empty($sourceUrl) || empty($r2Url) || empty($photoType)) {
            return new WP_Error('missing_params', 'Missing required parameters.', ['status' => 400]);
        }

        // The Cloudflare worker has successfully downloaded, analyzed, and uploaded the file to R2.
        // Now we just need to create the Asset record in WordPress and link it.
        $assetId = $this->assetManager->createAsset(
            0, // No local WP attachment ID since it's strictly in R2
            sanitize_url($sourceUrl),
            sanitize_url($r2Url),
            sanitize_text_field($photoType),
            [(int)$helmetId]
        );

        if (is_wp_error($assetId)) {
            return $assetId;
        }

        return new WP_REST_Response([
            'success'  => true,
            'asset_id' => $assetId,
            'message'  => 'Asset ingestion finalized.'
        ], 200);
    }
}
