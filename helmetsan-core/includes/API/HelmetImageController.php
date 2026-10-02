<?php

declare(strict_types=1);

namespace Helmetsan\Core\API;

use Helmetsan\Core\AI\ProviderRegistry;
use Helmetsan\Core\AI\Providers\NvidiaNimProvider;
use Helmetsan\Core\Media\HelmetImageManager;
use Helmetsan\Core\Media\ImageAuditService;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WP_Error;

/**
 * REST API Controller for Helmet 5-Shot Image Gallery & Dedicated Manager.
 */
class HelmetImageController extends WP_REST_Controller
{
    protected $namespace = 'helmetsan/v1';
    protected $rest_base = 'helmets';

    public function __construct(
        private readonly HelmetImageManager $imageManager,
        private readonly ImageAuditService $auditService,
        private readonly ?ProviderRegistry $registry = null
    ) {}

    public function register(): void
    {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public function register_routes(): void
    {
        // 1. Catalog Images Overview (all helmets coverage stats)
        register_rest_route($this->namespace, '/' . $this->rest_base . '/images/overview', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'get_images_overview'],
                'permission_callback' => [$this, 'check_read_permission'],
            ],
        ]);

        // 2. Images & 5-Shot Coverage for a specific helmet
        register_rest_route($this->namespace, '/' . $this->rest_base . '/(?P<id>[\w\-]+)/images', [
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'get_helmet_images'],
                'permission_callback' => [$this, 'check_read_permission'],
            ],
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'register_helmet_image'],
                'permission_callback' => [$this, 'check_write_permission'],
            ],
        ]);

        // 3. Set Primary Hero Image
        register_rest_route($this->namespace, '/' . $this->rest_base . '/(?P<id>[\w\-]+)/images/set-primary', [
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'set_primary_image'],
                'permission_callback' => [$this, 'check_write_permission'],
            ],
        ]);

        // 4. Trigger Kimi-K3 Quality Audit
        register_rest_route($this->namespace, '/' . $this->rest_base . '/(?P<id>[\w\-]+)/images/audit', [
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'audit_helmet_image'],
                'permission_callback' => [$this, 'check_write_permission'],
            ],
        ]);

        // 5. Delete specific image
        register_rest_route($this->namespace, '/' . $this->rest_base . '/images/(?P<imageId>[\w\-]+)', [
            [
                'methods'             => WP_REST_Server::DELETABLE,
                'callback'            => [$this, 'delete_helmet_image'],
                'permission_callback' => [$this, 'check_write_permission'],
            ],
        ]);

        // 6. Generate 5-Shot via FLUX.1 (NVIDIA NIM) + WebP + pHash + Kimi-K3 Audit
        register_rest_route($this->namespace, '/' . $this->rest_base . '/(?P<id>[\w\-]+)/images/generate-shot', [
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'generate_shot'],
                'permission_callback' => [$this, 'check_write_permission'],
            ],
        ]);
    }

    public function check_read_permission(): bool
    {
        return true;
    }

    public function check_write_permission(): bool
    {
        return current_user_can('edit_posts') || defined('HELMETSAN_INTERNAL_API_CALL');
    }

    public function get_images_overview(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;
        $table = $wpdb->prefix . HelmetImageManager::TABLE_NAME;

        $totalImages = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
        $verifiedCount = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE validation_status IN ('approved', 'published', 'verified', 'audit_passed')");
        $pendingCount = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE validation_status IN ('queued', 'generating', 'processing', 'audit_pending', 'human_review')");

        return new WP_REST_Response([
            'total_images' => $totalImages,
            'verified_images' => $verifiedCount,
            'pending_images' => $pendingCount,
            'canonical_shots' => HelmetImageManager::CANONICAL_SHOTS,
        ], 200);
    }

    public function get_helmet_images(WP_REST_Request $request): WP_REST_Response
    {
        $helmetId = (string) $request['id'];
        $coverage = $this->imageManager->getCoverageForHelmet($helmetId);
        $images = $this->imageManager->getImagesForHelmet($helmetId);

        return new WP_REST_Response([
            'helmet_id' => $helmetId,
            'coverage' => $coverage,
            'images' => $images,
        ], 200);
    }

    public function register_helmet_image(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $helmetId = (string) $request['id'];
        $params = $request->get_json_params() ?: $request->get_params();

        if (empty($params['url']) || empty($params['shot_type'])) {
            return new WP_Error('missing_required_fields', 'url and shot_type are required.', ['status' => 400]);
        }

        $params['helmet_id'] = $helmetId;
        $imageId = $this->imageManager->registerImage($params);

        return new WP_REST_Response([
            'success' => true,
            'image_id' => $imageId,
            'coverage' => $this->imageManager->getCoverageForHelmet($helmetId),
        ], 201);
    }

    public function set_primary_image(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $helmetId = (string) $request['id'];
        $params = $request->get_json_params() ?: $request->get_params();
        $imageId = (string) ($params['image_id'] ?? '');

        if ($imageId === '') {
            return new WP_Error('missing_image_id', 'image_id is required.', ['status' => 400]);
        }

        $success = $this->imageManager->setPrimaryImage($helmetId, $imageId);

        return new WP_REST_Response([
            'success' => $success,
            'helmet_id' => $helmetId,
            'primary_image_id' => $imageId,
        ], 200);
    }

    public function audit_helmet_image(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $helmetId = (string) $request['id'];
        $params = $request->get_json_params() ?: $request->get_params();
        $imageId = (string) ($params['image_id'] ?? '');
        $imageUrl = (string) ($params['url'] ?? '');
        $shotType = (string) ($params['shot_type'] ?? HelmetImageManager::SHOT_FRONT_HERO);

        if ($imageId === '' || $imageUrl === '') {
            return new WP_Error('missing_params', 'image_id and url are required.', ['status' => 400]);
        }

        $auditResult = $this->auditService->auditImage($imageId, $imageUrl, $shotType, [
            'helmet_id' => $helmetId,
            'model' => $params['model'] ?? '',
            'brand' => $params['brand'] ?? '',
        ]);

        return new WP_REST_Response([
            'success' => true,
            'image_id' => $imageId,
            'audit' => $auditResult,
        ], 200);
    }

    public function delete_helmet_image(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $imageId = (string) $request['imageId'];
        $deleted = $this->imageManager->deleteImage($imageId);

        return new WP_REST_Response([
            'success' => $deleted,
            'image_id' => $imageId,
        ], 200);
    }

    /**
     * Generate canonical shot using NVIDIA NIM FLUX.1 + WebP derivatives + Kimi-K3 audit.
     */
    public function generate_shot(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $helmetIdOrSlug = (string) $request['id'];
        $params = $request->get_json_params() ?: $request->get_params();

        $shotType = (string) ($params['shot_type'] ?? HelmetImageManager::SHOT_FRONT_HERO);
        $modelChoice = (string) ($params['model'] ?? 'flux-dev');
        $promptOverride = trim((string) ($params['prompt_override'] ?? ''));
        $autoAudit = !isset($params['auto_audit']) || (bool) $params['auto_audit'];

        // 1. Resolve helmet post
        $post = is_numeric($helmetIdOrSlug) ? get_post((int) $helmetIdOrSlug) : null;
        if (! $post) {
            $posts = get_posts([
                'name' => sanitize_title($helmetIdOrSlug),
                'post_type' => 'helmet',
                'post_status' => 'any',
                'numberposts' => 1,
            ]);
            if (!empty($posts)) {
                $post = $posts[0];
            }
        }

        $helmetId = $post ? (string) $post->ID : $helmetIdOrSlug;
        $title = $post ? $post->post_title : 'Motorcycle Helmet';

        // Extract metadata
        $brand = '';
        if ($post) {
            $brandTerms = get_the_terms($post->ID, 'helmet_brand');
            if (is_array($brandTerms) && !empty($brandTerms)) {
                $brand = $brandTerms[0]->name;
            }
        }
        if ($brand === '') {
            $brand = (string) ($params['brand'] ?? 'Motorcycle Helmet');
        }

        $shell = $post ? (string) get_post_meta($post->ID, 'spec_shell_material', true) : '';
        if ($shell === '') {
            $shell = 'Composite Carbon Fiber';
        }

        $colorway = $post ? (string) get_post_meta($post->ID, 'spec_colorway', true) : '';
        if ($colorway === '') {
            $colorway = 'Gloss Finish';
        }

        // 2. Build prompt incorporating Kimi-K3 failure-prevention constraints
        $shotSpecs = [
            HelmetImageManager::SHOT_FRONT_HERO => [
                'title' => '3/4 Front Isometric Hero',
                'suffix' => 'Isometric 3/4 front studio hero photograph, eye-level 35mm lens, helmet centered at 80% frame height, visor cracked 10mm open, Pinlock anti-fog pins clearly visible, brow and chin intake vents open. Diffused studio softbox lighting at 45-degree angles, polarized filter, anti-glare studio environment, no specular highlight blowout on visor or curved gloss surfaces, preserved carbon weave texture and deep blacks. Seamless dark slate studio backdrop (#1e293b), sharp antialiased edges, zero halo artifacts, photorealistic 8k, calibrated 5500K daylight white balance.',
            ],
            HelmetImageManager::SHOT_SIDE_PROFILE => [
                'title' => 'Lateral Side Profile',
                'suffix' => 'Strictly perpendicular 90-degree lateral profile photograph, eye-level, horizontal axis locked, zero yaw. Showcasing aerodynamic shell contour, rear spoiler extension, visor pivot mechanism baseplate, and chin bar profile. Controlled rim lighting outlining shell edges without halo artifacts, seamless dark slate studio backdrop (#1e293b), zero glare blowout, sharp focus 8k.',
            ],
            HelmetImageManager::SHOT_REAR_EXHAUST => [
                'title' => 'Rear Exhaust & Diffuser',
                'suffix' => 'Rear three-quarter perspective studio photograph, slight 15-degree downward angle. Highlighting functional exhaust extractor vents, aerodynamic air channels, neck roll taper, and official ECE 22.06 and DOT certification decals clearly rendered. Soft diffused studio lighting, deep rich blacks, seamless dark slate studio backdrop (#1e293b), photorealistic 8k.',
            ],
            HelmetImageManager::SHOT_INTERIOR_MACRO => [
                'title' => 'Macro Interior & Retention',
                'suffix' => 'Extreme close-up macro studio photograph looking directly into the helmet cavity. Detailed multi-density EPS safety impact channels, bright red emergency quick-release cheek pad pull tabs, breathable antibacterial mesh fabric lining, and titanium double D-ring chin strap buckle with red snap button. Studio macro lighting, shallow depth of field, photorealistic 8k.',
            ],
            HelmetImageManager::SHOT_COCKPIT_CONTEXT => [
                'title' => 'Motorcycle Cockpit Pairing',
                'suffix' => 'Natural ergonomic motorcycle cockpit photograph, helmet resting securely on the sculpted fuel tank of a modern sportbike. Soft ambient morning light, textured carbon fiber tank protector, TFT instrument cluster in soft background bokeh. Cinematic ultra-realistic automotive commercial aesthetic, true-to-life scale and proportions.',
            ],
        ];

        $spec = $shotSpecs[$shotType] ?? $shotSpecs[HelmetImageManager::SHOT_FRONT_HERO];
        $prompt = $promptOverride !== '' 
            ? $promptOverride 
            : "Commercial studio product photograph of the {$brand} {$title} motorcycle helmet. Shell Material: {$shell}. Finish: {$colorway}. " . $spec['suffix'];

        // 3. Resolve NIM provider
        $provider = $this->registry?->get('nvidia_nim');
        if (! $provider instanceof NvidiaNimProvider || ! $provider->isConfigured()) {
            return new WP_Error('provider_not_configured', 'NVIDIA NIM provider is not configured or missing API key.', ['status' => 500]);
        }

        $modelSlug = str_contains($modelChoice, 'schnell') ? 'black-forest-labs/flux.1-schnell' : 'black-forest-labs/flux.1-dev';
        
        $genResult = $provider->generateImage($prompt, [
            'model' => $modelSlug,
            'width' => 1024,
            'height' => 1024,
        ]);

        if (empty($genResult['b64_json'])) {
            $err = $provider->getLastError();
            return new WP_Error('generation_failed', 'FLUX generation failed: ' . ($err['message'] ?? 'Empty response from NVIDIA NIM'), ['status' => 502]);
        }

        // 4. Decode and save raw image
        $rawBinary = base64_decode($genResult['b64_json']);
        if ($rawBinary === false) {
            return new WP_Error('decode_failed', 'Failed to decode base64 image data.', ['status' => 500]);
        }

        $uploadDir = wp_upload_dir();
        $helmetSlug = sanitize_title($brand . '-' . ($post ? $post->post_name : $helmetIdOrSlug));
        $targetDir = $uploadDir['basedir'] . '/helmetsan-gallery/' . $helmetSlug;
        $targetUrl = $uploadDir['baseurl'] . '/helmetsan-gallery/' . $helmetSlug;

        if (! file_exists($targetDir)) {
            wp_mkdir_p($targetDir);
        }

        $baseFilename = $shotType . '-' . time();
        $sourcePath = $targetDir . '/' . $baseFilename . '-source.png';
        file_put_contents($sourcePath, $rawBinary);

        // 5. Generate WebP derivatives (hero 1920, gallery 1024, thumb 480)
        $derivatives = $this->generateWebpDerivatives($sourcePath, $targetDir, $targetUrl, $baseFilename);

        // 6. Compute 64-bit difference pHash & sha256
        $phash = $this->computeDiffHash($sourcePath);
        $sha256 = hash('sha256', $rawBinary);

        $heroUrl = $derivatives['hero']['url'] ?? ($targetUrl . '/' . $baseFilename . '-source.png');
        $galleryUrl = $derivatives['gallery']['url'] ?? $heroUrl;
        $thumbUrl = $derivatives['thumb']['url'] ?? $galleryUrl;

        // 7. Register in wp_helmetsan_images
        $isPrimary = ($shotType === HelmetImageManager::SHOT_FRONT_HERO);
        $imageId = $this->imageManager->registerImage([
            'helmet_id' => $helmetId,
            'shot_type' => $shotType,
            'source_type' => 'ai_generated',
            'url' => $heroUrl,
            'thumbnail_url' => $thumbUrl,
            'alt_text' => "{$brand} {$title} - {$spec['title']}",
            'caption' => $spec['title'],
            'dimensions' => [
                'hero' => $derivatives['hero'] ?? ['width' => 1920, 'height' => 1080],
                'gallery' => $derivatives['gallery'] ?? ['width' => 1024, 'height' => 576],
                'thumb' => $derivatives['thumb'] ?? ['width' => 480, 'height' => 270],
            ],
            'format' => 'webp',
            'file_size_bytes' => filesize($sourcePath) ?: strlen($rawBinary),
            'phash' => $phash,
            'sha256' => $sha256,
            'is_primary' => $isPrimary,
            'validation_status' => 'queued',
            'generation_metadata' => [
                'model' => $genResult['model_used'] ?? $modelSlug,
                'prompt' => $prompt,
                'derivatives' => $derivatives,
                'generated_at' => current_time('mysql'),
            ],
        ]);

        // 8. If primary and post exists, register as featured image attachment
        if ($isPrimary && $post) {
            $this->syncFeaturedImage($post->ID, $sourcePath, "{$brand} {$title}");
        }

        // 9. Run automated Kimi-K3 audit if requested
        $auditResult = null;
        if ($autoAudit) {
            $auditResult = $this->auditService->auditImage($imageId, $sourcePath, $shotType, [
                'helmet_id' => $helmetId,
                'brand' => $brand,
                'model' => $title,
                'shell_material' => $shell,
            ]);
        }

        return new WP_REST_Response([
            'success' => true,
            'image_id' => $imageId,
            'helmet_id' => $helmetId,
            'shot_type' => $shotType,
            'model' => $genResult['model_used'] ?? $modelSlug,
            'hero_url' => $heroUrl,
            'gallery_url' => $galleryUrl,
            'thumbnail_url' => $thumbUrl,
            'phash' => $phash,
            'audit' => $auditResult,
            'coverage' => $this->imageManager->getCoverageForHelmet($helmetId),
        ], 201);
    }

    /**
     * Generate responsive WebP derivatives (hero: 1920, gallery: 1024, thumb: 480).
     */
    private function generateWebpDerivatives(string $sourcePath, string $targetDir, string $targetUrl, string $baseFilename): array
    {
        $derivatives = [];
        $sizes = [
            'hero' => ['width' => 1920, 'quality' => 85],
            'gallery' => ['width' => 1024, 'quality' => 85],
            'thumb' => ['width' => 480, 'quality' => 80],
        ];

        if (function_exists('imagecreatefrompng') && function_exists('imagewebp')) {
            $src = @imagecreatefrompng($sourcePath);
            if ($src !== false) {
                $srcW = imagesx($src);
                $srcH = imagesy($src);

                foreach ($sizes as $label => $opts) {
                    $w = $opts['width'];
                    $h = (int) round(($srcH / $srcW) * $w);
                    $dest = imagecreatetruecolor($w, $h);

                    // Preserve transparency / smooth scaling
                    imagealphablending($dest, false);
                    imagesavealpha($dest, true);
                    imagecopyresampled($dest, $src, 0, 0, 0, 0, $w, $h, $srcW, $srcH);

                    $webpFilename = "{$baseFilename}-{$label}.webp";
                    $outPath = "{$targetDir}/{$webpFilename}";
                    imagewebp($dest, $outPath, $opts['quality']);
                    imagedestroy($dest);

                    $derivatives[$label] = [
                        'url' => "{$targetUrl}/{$webpFilename}",
                        'path' => $outPath,
                        'width' => $w,
                        'height' => $h,
                        'size_bytes' => file_exists($outPath) ? filesize($outPath) : 0,
                    ];
                }
                imagedestroy($src);
                return $derivatives;
            }
        }

        // Fallback: Return source path as hero
        $fallbackUrl = "{$targetUrl}/{$baseFilename}-source.png";
        return [
            'hero' => ['url' => $fallbackUrl, 'path' => $sourcePath, 'width' => 1024, 'height' => 1024, 'size_bytes' => filesize($sourcePath)],
            'gallery' => ['url' => $fallbackUrl, 'path' => $sourcePath, 'width' => 1024, 'height' => 1024, 'size_bytes' => filesize($sourcePath)],
            'thumb' => ['url' => $fallbackUrl, 'path' => $sourcePath, 'width' => 480, 'height' => 480, 'size_bytes' => filesize($sourcePath)],
        ];
    }

    /**
     * Compute 64-bit difference hash (dHash) in pure PHP.
     */
    private function computeDiffHash(string $filePath): string
    {
        if (function_exists('imagecreatefromstring')) {
            $content = file_get_contents($filePath);
            if ($content !== false) {
                $img = @imagecreatefromstring($content);
                if ($img !== false) {
                    $small = imagecreatetruecolor(9, 8);
                    imagecopyresampled($small, $img, 0, 0, 0, 0, 9, 8, imagesx($img), imagesy($img));
                    $bits = '';
                    for ($y = 0; $y < 8; $y++) {
                        for ($x = 0; $x < 8; $x++) {
                            $rgbLeft = imagecolorat($small, $x, $y);
                            $rL = ($rgbLeft >> 16) & 0xFF;
                            $gL = ($rgbLeft >> 8) & 0xFF;
                            $bL = $rgbLeft & 0xFF;
                            $grayL = (int) (0.299 * $rL + 0.587 * $gL + 0.114 * $bL);

                            $rgbRight = imagecolorat($small, $x + 1, $y);
                            $rR = ($rgbRight >> 16) & 0xFF;
                            $gR = ($rgbRight >> 8) & 0xFF;
                            $bR = $rgbRight & 0xFF;
                            $grayR = (int) (0.299 * $rR + 0.587 * $gR + 0.114 * $bR);

                            $bits .= ($grayL > $grayR) ? '1' : '0';
                        }
                    }
                    imagedestroy($small);
                    imagedestroy($img);
                    return sprintf('%016x', bindec(substr($bits, 0, 32))) . sprintf('%08x', bindec(substr($bits, 32)));
                }
            }
        }
        return substr(hash_file('sha256', $filePath) ?: '', 0, 16);
    }

    /**
     * Sync WordPress featured image.
     */
    private function syncFeaturedImage(int $postId, string $filePath, string $title): void
    {
        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';

        $filetype = wp_check_filetype(basename($filePath), null);
        $attachment = [
            'guid' => wp_upload_dir()['url'] . '/' . basename($filePath),
            'post_mime_type' => $filetype['type'] ?: 'image/png',
            'post_title' => preg_replace('/\.[^.]+$/', '', basename($filePath)),
            'post_content' => '',
            'post_status' => 'inherit',
        ];

        $attachId = wp_insert_attachment($attachment, $filePath, $postId);
        if (! is_wp_error($attachId) && $attachId > 0) {
            $attachData = wp_generate_attachment_metadata($attachId, $filePath);
            wp_update_attachment_metadata($attachId, $attachData);
            set_post_thumbnail($postId, $attachId);
        }
    }
}
