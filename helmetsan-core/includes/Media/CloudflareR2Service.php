<?php

declare(strict_types=1);

namespace Helmetsan\Core\Media;

use Aws\S3\S3Client;
use Aws\Exception\AwsException;
use Helmetsan\Core\Support\Config;
use WP_Error;

/**
 * Handles offloading image assets directly to Cloudflare R2 via the AWS S3 SDK.
 */
class CloudflareR2Service
{
    private ?S3Client $client = null;
    private bool $enabled = false;
    private string $bucket = '';
    private string $publicUrlPrefix = '';
    private bool $resizingEnabled = false;
    private string $resizerUrl = '';

    public function __construct(Config $config)
    {
        $settings = get_option(Config::OPTION_MEDIA, $config->mediaDefaults());
        $cfSettings = get_option(Config::OPTION_CLOUDFLARE, []);

        $r2_account_id = defined('HELMETSAN_CLOUDFLARE_ACCOUNT_ID') ? HELMETSAN_CLOUDFLARE_ACCOUNT_ID : ($cfSettings['cf_account_id'] ?? ($settings['r2_account_id'] ?? ''));
        $r2_access_key = defined('HELMETSAN_R2_ACCESS_KEY_ID') ? HELMETSAN_R2_ACCESS_KEY_ID : ($settings['r2_access_key'] ?? '');
        $r2_secret_key = defined('HELMETSAN_R2_SECRET_ACCESS_KEY') ? HELMETSAN_R2_SECRET_ACCESS_KEY : ($settings['r2_secret_key'] ?? '');

        $this->bucket = defined('HELMETSAN_R2_BUCKET') ? HELMETSAN_R2_BUCKET : ($cfSettings['r2_bucket'] ?? ($settings['r2_bucket'] ?? ''));
        $this->publicUrlPrefix = rtrim(defined('HELMETSAN_R2_PUBLIC_URL') ? HELMETSAN_R2_PUBLIC_URL : ($cfSettings['r2_public_url'] ?? ($settings['r2_public_url'] ?? '')), '/');
        $this->resizingEnabled = !empty($settings['r2_image_resizing_enabled']);
        $this->resizerUrl = rtrim($settings['r2_image_resizer_url'] ?? '', '/');

        $this->enabled = !empty($cfSettings['enable_r2_backups']) || !empty($settings['r2_enabled']) || (!empty($r2_account_id) && !empty($r2_access_key) && !empty($r2_secret_key) && !empty($this->bucket));

        if ($this->enabled && !empty($r2_account_id) && !empty($r2_access_key) && !empty($r2_secret_key)) {
            $this->client = new S3Client([
                'region' => 'auto',
                'endpoint' => sprintf('https://%s.r2.cloudflarestorage.com', $r2_account_id),
                'version' => 'latest',
                'credentials' => [
                    'key' => $r2_access_key,
                    'secret' => $r2_secret_key,
                ],
            ]);
        }

    }

    public function isEnabled(): bool
    {
        return $this->enabled && $this->client !== null && $this->bucket !== '';
    }

    /**
     * Uploads a local file to R2.
     * 
     * @param string $localFilePath Absolute path to the local file.
     * @param string $r2Key Desired path/filename inside the R2 bucket.
     * @param string $contentType Optional MIME type.
     * @return string|WP_Error The public URL of the uploaded file on success, or WP_Error on failure.
     */
    public function uploadFile(string $localFilePath, string $r2Key, string $contentType = 'image/jpeg')
    {
        if (!$this->isEnabled()) {
            return new WP_Error('r2_not_configured', 'Cloudflare R2 is not configured or enabled.');
        }

        if (!file_exists($localFilePath)) {
            return new WP_Error('file_not_found', 'Local file not found for upload.');
        }

        try {
            $this->client->putObject([
                'Bucket'      => $this->bucket,
                'Key'         => $r2Key,
                'SourceFile'  => $localFilePath,
                'ContentType' => $contentType,
                // R2 does not support ACLs in the same way as S3 standard, so omit ACL => 'public-read'
            ]);

            return $this->getFileUrl($r2Key);
        } catch (AwsException $e) {
            return new WP_Error('r2_upload_failed', 'Failed to upload to R2: ' . $e->getMessage());
        }
    }

    /**
     * Generate the public URL for an R2 key.
     */
    public function getFileUrl(string $r2Key): string
    {
        if (empty($this->publicUrlPrefix)) {
            return '';
        }
        return sprintf('%s/%s', $this->publicUrlPrefix, ltrim($r2Key, '/'));
    }

    /**
     * Generate a resized public URL using the Cloudflare Worker.
     * Falls back to the original URL if resizing is not enabled or parameters are missing.
     */
    public function getResizedFileUrl(string $r2Key, int $width = 0, int $quality = 75, string $format = 'webp'): string
    {
        if (!$this->resizingEnabled || empty($this->resizerUrl)) {
            return $this->getFileUrl($r2Key);
        }

        $originalUrl = $this->getFileUrl($r2Key);
        if (empty($originalUrl)) {
            return '';
        }

        $params = [];
        if ($width > 0) {
            $params['width'] = $width;
        }
        if ($quality > 0 && $quality <= 100) {
            $params['quality'] = $quality;
        }
        if (!empty($format)) {
            $params['format'] = $format;
        }

        if (empty($params)) {
             return $originalUrl;
        }

        return esc_url_raw(add_query_arg($params, $this->resizerUrl . '/' . ltrim($r2Key, '/')));
    }
}
