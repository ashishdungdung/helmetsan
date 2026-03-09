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

    public function __construct(Config $config)
    {
        $settings = get_option(Config::OPTION_MEDIA, $config->mediaDefaults());

        $this->enabled = !empty($settings['r2_enabled']);
        $this->bucket = $settings['r2_bucket'] ?? '';
        $this->publicUrlPrefix = rtrim($settings['r2_public_url'] ?? '', '/');

        if ($this->enabled && !empty($settings['r2_account_id']) && !empty($settings['r2_access_key']) && !empty($settings['r2_secret_key'])) {
            $this->client = new S3Client([
                'region' => 'auto',
                'endpoint' => sprintf('https://%s.r2.cloudflarestorage.com', $settings['r2_account_id']),
                'version' => 'latest',
                'credentials' => [
                    'key' => $settings['r2_access_key'],
                    'secret' => $settings['r2_secret_key'],
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
}
