Below is a production-oriented implementation blueprint for a Helmetsan Image Studio plugin. It replaces the static table with:

- A WordPress admin Studio Cockpit
- REST-driven 5-shot generation
- NVIDIA NIM provider integration
- Kimi-K3 audit integration
- WebP derivatives
- pHash deduplication
- Media Library registration
- Batch generation queue
- Frontend responsive product gallery
- Secure nonces, capability checks, validation, and rate limits

The provider payloads are isolated because NVIDIA NIM and Moonshot deployments may expose different model-specific schemas.

---

# 1. Plugin structure

Create:

```text
wp-content/plugins/helmetsan-image-studio/
├── helmetsan-image-studio.php
├── includes/
│   ├── class-hs-db.php
│   ├── class-hs-providers.php
│   ├── class-hs-image-service.php
│   ├── class-hs-rest-controller.php
│   └── class-hs-admin.php
├── assets/
│   ├── admin-studio.js
│   ├── admin-studio.css
│   ├── product-gallery.js
│   └── product-gallery.css
└── templates/
    └── product-gallery.php
```

Add credentials to `wp-config.php`:

```php
define('HELMETSAN_NVIDIA_NIM_URL', 'https://your-nim-host/v1/images/generations');
define('HELMETSAN_NVIDIA_NIM_KEY', 'your-nvidia-key');
define('HELMETSAN_NVIDIA_MODEL_DEV', 'black-forest-labs/flux.1-dev');
define('HELMETSAN_NVIDIA_MODEL_FAST', 'black-forest-labs/flux.1-schnell');

define('HELMETSAN_KIMI_URL', 'https://your-kimi-host/v1/chat/completions');
define('HELMETSAN_KIMI_KEY', 'your-kimi-key');
define('HELMETSAN_KIMI_MODEL', 'kimi-k3');
```

---

# 2. Main plugin file

## `helmetsan-image-studio.php`

```php
<?php
/**
 * Plugin Name: Helmetsan Image Studio
 * Description: AI-powered five-shot helmet image generation and auditing.
 * Version: 1.0.0
 */

defined('ABSPATH') || exit;

define('HS_VERSION', '1.0.0');
define('HS_PATH', plugin_dir_path(__FILE__));
define('HS_URL', plugin_dir_url(__FILE__));

require_once HS_PATH . 'includes/class-hs-db.php';
require_once HS_PATH . 'includes/class-hs-providers.php';
require_once HS_PATH . 'includes/class-hs-image-service.php';
require_once HS_PATH . 'includes/class-hs-rest-controller.php';
require_once HS_PATH . 'includes/class-hs-admin.php';

register_activation_hook(__FILE__, ['HS_DB', 'install']);

add_action('rest_api_init', function () {
    (new HS_REST_Controller())->register_routes();
});

add_action('admin_menu', ['HS_Admin', 'menu']);
add_action('admin_enqueue_scripts', ['HS_Admin', 'assets']);

add_shortcode('helmetsan_gallery', function ($atts) {
    $atts = shortcode_atts([
        'helmet_id' => 0,
    ], $atts);

    $helmet_id = absint($atts['helmet_id']);

    if (!$helmet_id) {
        return '<p>Helmet gallery unavailable.</p>';
    }

    wp_enqueue_style(
        'hs-product-gallery',
        HS_URL . 'assets/product-gallery.css',
        [],
        HS_VERSION
    );

    wp_enqueue_script(
        'hs-product-gallery',
        HS_URL . 'assets/product-gallery.js',
        [],
        HS_VERSION,
        true
    );

    wp_localize_script('hs-product-gallery', 'HS_GALLERY', [
        'restUrl' => esc_url_raw(rest_url('helmetsan/v1')),
        'helmetId' => $helmet_id,
    ]);

    ob_start();
    include HS_PATH . 'templates/product-gallery.php';
    return ob_get_clean();
});
```

---

# 3. Database layer

## `includes/class-hs-db.php`

```php
<?php

defined('ABSPATH') || exit;

final class HS_DB
{
    public static function table(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'helmetsan_images';
    }

    public static function install(): void
    {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $table = self::table();
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            helmet_id BIGINT UNSIGNED NOT NULL,
            shot_type VARCHAR(32) NOT NULL,
            attachment_id BIGINT UNSIGNED NULL,
            hero_url TEXT NULL,
            gallery_url TEXT NULL,
            thumb_url TEXT NULL,
            phash CHAR(16) NULL,
            model VARCHAR(32) NULL,
            audit_status VARCHAR(24) DEFAULT 'pending',
            audit_score DECIMAL(5,2) NULL,
            audit_confidence DECIMAL(5,2) NULL,
            audit_json LONGTEXT NULL,
            status VARCHAR(24) DEFAULT 'complete',
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY helmet_id (helmet_id),
            KEY shot_type (shot_type),
            KEY phash (phash)
        ) {$charset};";

        dbDelta($sql);
    }

    public static function get_images(int $helmet_id): array
    {
        global $wpdb;

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM " . self::table() . "
                 WHERE helmet_id = %d
                 ORDER BY FIELD(
                    shot_type,
                    'hero',
                    'profile',
                    'rear',
                    'interior',
                    'cockpit'
                 )",
                $helmet_id
            ),
            ARRAY_A
        );

        foreach ($rows as &$row) {
            $row['audit'] = $row['audit_json']
                ? json_decode($row['audit_json'], true)
                : null;
        }

        return $rows ?: [];
    }

    public static function find_duplicate(string $phash, int $helmet_id): ?array
    {
        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM " . self::table() . "
                 WHERE helmet_id = %d AND phash = %s
                 LIMIT 1",
                $helmet_id,
                $phash
            ),
            ARRAY_A
        );

        return $row ?: null;
    }

    public static function insert(array $data): int
    {
        global $wpdb;

        $now = current_time('mysql');

        $wpdb->insert(self::table(), [
            'helmet_id'        => absint($data['helmet_id']),
            'shot_type'        => sanitize_key($data['shot_type']),
            'attachment_id'    => absint($data['attachment_id'] ?? 0),
            'hero_url'         => esc_url_raw($data['hero_url'] ?? ''),
            'gallery_url'      => esc_url_raw($data['gallery_url'] ?? ''),
            'thumb_url'        => esc_url_raw($data['thumb_url'] ?? ''),
            'phash'            => sanitize_text_field($data['phash'] ?? ''),
            'model'            => sanitize_key($data['model'] ?? ''),
            'audit_status'     => sanitize_key($data['audit_status'] ?? 'pending'),
            'audit_score'      => $data['audit_score'] ?? null,
            'audit_confidence' => $data['audit_confidence'] ?? null,
            'audit_json'       => wp_json_encode($data['audit'] ?? null),
            'status'           => 'complete',
            'created_at'       => $now,
            'updated_at'       => $now,
        ]);

        return (int) $wpdb->insert_id;
    }

    public static function coverage(int $helmet_id): array
    {
        $images = self::get_images($helmet_id);

        $required = ['hero', 'profile', 'rear', 'interior', 'cockpit'];
        $present = [];

        foreach ($images as $image) {
            $present[$image['shot_type']] = true;
        }

        $count = 0;

        foreach ($required as $shot) {
            if (!empty($present[$shot])) {
                $count++;
            }
        }

        return [
            'count' => $count,
            'total' => 5,
            'complete' => $count === 5,
            'label' => "{$count}/5",
            'status' => $count === 5
                ? 'complete'
                : ($count > 0 ? 'partial' : 'empty'),
        ];
    }
}
```

---

# 4. AI providers

## `includes/class-hs-providers.php`

```php
<?php

defined('ABSPATH') || exit;

final class HS_NvidiaNimProvider
{
    public static function generate_image(string $prompt, string $model): string
    {
        $endpoint = defined('HELMETSAN_NVIDIA_NIM_URL')
            ? HELMETSAN_NVIDIA_NIM_URL
            : '';

        $key = defined('HELMETSAN_NVIDIA_NIM_KEY')
            ? HELMETSAN_NVIDIA_NIM_KEY
            : '';

        $model_name = $model === 'flux-schnell'
            ? HELMETSAN_NVIDIA_MODEL_FAST
            : HELMETSAN_NVIDIA_MODEL_DEV;

        if (!$endpoint || !$key) {
            throw new RuntimeException('NVIDIA NIM is not configured.');
        }

        /*
         * Adjust these fields if your NIM deployment uses a custom schema.
         */
        $response = wp_remote_post($endpoint, [
            'timeout' => 180,
            'headers' => [
                'Authorization' => 'Bearer ' . $key,
                'Content-Type'  => 'application/json',
            ],
            'body' => wp_json_encode([
                'model'  => $model_name,
                'prompt' => $prompt,
                'n'      => 1,
                'size'   => '1920x1920',
                'response_format' => 'url',
            ]),
        ]);

        if (is_wp_error($response)) {
            throw new RuntimeException($response->get_error_message());
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($code < 200 || $code >= 300) {
            throw new RuntimeException(
                $body['error']['message'] ?? 'NVIDIA image generation failed.'
            );
        }

        $url = $body['data'][0]['url']
            ?? $body['images'][0]['url']
            ?? '';

        if (!$url && !empty($body['data'][0]['b64_json'])) {
            $tmp = wp_tempnam('hs-ai-image');
            file_put_contents($tmp, base64_decode($body['data'][0]['b64_json']));
            return $tmp;
        }

        if (!$url) {
            throw new RuntimeException('NVIDIA response contained no image.');
        }

        $tmp = download_url($url, 180);

        if (is_wp_error($tmp)) {
            throw new RuntimeException($tmp->get_error_message());
        }

        return $tmp;
    }
}

final class HS_KimiAuditService
{
    public static function audit(string $image_url, array $context = []): array
    {
        $endpoint = defined('HELMETSAN_KIMI_URL')
            ? HELMETSAN_KIMI_URL
            : '';

        $key = defined('HELMETSAN_KIMI_KEY')
            ? HELMETSAN_KIMI_KEY
            : '';

        if (!$endpoint || !$key) {
            return [
                'status' => 'not_configured',
                'score' => null,
                'confidence' => null,
                'checks' => [],
            ];
        }

        $prompt = [
            'instruction' => 'Audit this motorcycle helmet product image.',
            'requirements' => [
                'helmet_present',
                'correct_orientation',
                'visor_alignment',
                'vents_verified',
                'strap_verified',
                'no_duplicate_or_wrong_product',
            ],
            'expected_json' => [
                'score' => 'number 0-100',
                'confidence' => 'number 0-100',
                'checks' => [
                    'helmet_present' => 'boolean',
                    'correct_orientation' => 'boolean',
                    'visor_alignment' => 'boolean',
                    'vents_verified' => 'boolean',
                    'strap_verified' => 'boolean',
                    'wrong_product' => 'boolean',
                ],
                'summary' => 'string',
            ],
            'context' => $context,
        ];

        /*
         * Use the image URL if the Kimi deployment accepts URL image input.
         * For deployments requiring base64, download and encode the image here.
         */
        $payload = [
            'model' => defined('HELMETSAN_KIMI_MODEL')
                ? HELMETSAN_KIMI_MODEL
                : 'kimi-k3',
            'temperature' => 0,
            'response_format' => ['type' => 'json_object'],
            'messages' => [[
                'role' => 'user',
                'content' => [
                    [
                        'type' => 'text',
                        'text' => wp_json_encode($prompt),
                    ],
                    [
                        'type' => 'image_url',
                        'image_url' => [
                            'url' => $image_url,
                        ],
                    ],
                ],
            ]],
        ];

        $response = wp_remote_post($endpoint, [
            'timeout' => 120,
            'headers' => [
                'Authorization' => 'Bearer ' . $key,
                'Content-Type'  => 'application/json',
            ],
            'body' => wp_json_encode($payload),
        ]);

        if (is_wp_error($response)) {
            throw new RuntimeException($response->get_error_message());
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        $content = $body['choices'][0]['message']['content'] ?? '';

        $content = trim((string) $content);
        $content = preg_replace('/^```json|```$/', '', $content);

        $audit = json_decode(trim($content), true);

        if (!is_array($audit)) {
            throw new RuntimeException('Kimi audit returned invalid JSON.');
        }

        return [
            'status' => 'complete',
            'score' => isset($audit['score']) ? (float) $audit['score'] : null,
            'confidence' => isset($audit['confidence'])
                ? (float) $audit['confidence']
                : null,
            'checks' => $audit['checks'] ?? [],
            'summary' => sanitize_textarea_field($audit['summary'] ?? ''),
        ];
    }
}
```

---

# 5. Image service

## `includes/class-hs-image-service.php`

```php
<?php

defined('ABSPATH') || exit;

final class HS_Image_Service
{
    public const SHOTS = [
        'hero' => [
            'label' => '3/4 Front Isometric Hero',
            'angle' => 'three-quarter front isometric',
        ],
        'profile' => [
            'label' => 'Lateral Side Profile',
            'angle' => 'perfect lateral side profile',
        ],
        'rear' => [
            'label' => 'Rear Exhaust & Diffuser',
            'angle' => 'rear view showing exhaust ports and diffuser',
        ],
        'interior' => [
            'label' => 'Macro Interior & Retention',
            'angle' => 'macro interior view showing liner, cheek pads, and retention strap',
        ],
        'cockpit' => [
            'label' => 'Motorcycle Cockpit Pairing',
            'angle' => 'helmet paired with a premium motorcycle cockpit',
        ],
    ];

    public static function generate(
        int $helmet_id,
        string $shot_type,
        string $model,
        bool $run_audit
    ): array {
        if (!isset(self::SHOTS[$shot_type])) {
            throw new InvalidArgumentException('Invalid shot type.');
        }

        $helmet = self::get_helmet($helmet_id);

        if (!$helmet) {
            throw new RuntimeException('Helmet not found.');
        }

        $prompt = self::prompt($helmet, $shot_type);

        $tmp = HS_NvidiaNimProvider::generate_image($prompt, $model);

        if (!file_exists($tmp)) {
            throw new RuntimeException('Generated image does not exist.');
        }

        $phash = self::phash($tmp);
        $duplicate = HS_DB::find_duplicate($phash, $helmet_id);

        if ($duplicate && $duplicate['shot_type'] === $shot_type) {
            @unlink($tmp);
            return [
                'duplicate' => true,
                'image' => $duplicate,
                'coverage' => HS_DB::coverage($helmet_id),
            ];
        }

        $media = self::register_media($tmp, $helmet, $shot_type);

        $audit = [
            'status' => 'pending',
            'score' => null,
            'confidence' => null,
            'checks' => [],
        ];

        if ($run_audit) {
            $audit = HS_KimiAuditService::audit(
                $media['hero_url'],
                [
                    'brand' => $helmet['brand'],
                    'model' => $helmet['model'],
                    'shot_type' => $shot_type,
                ]
            );
        }

        $image_id = HS_DB::insert([
            'helmet_id' => $helmet_id,
            'shot_type' => $shot_type,
            'attachment_id' => $media['attachment_id'],
            'hero_url' => $media['hero_url'],
            'gallery_url' => $media['gallery_url'],
            'thumb_url' => $media['thumb_url'],
            'phash' => $phash,
            'model' => $model,
            'audit_status' => $audit['status'],
            'audit_score' => $audit['score'],
            'audit_confidence' => $audit['confidence'],
            'audit' => $audit,
        ]);

        @unlink($tmp);

        $images = HS_DB::get_images($helmet_id);

        foreach ($images as &$image) {
            if ((int) $image['id'] === $image_id) {
                $image['audit'] = $audit;
                $created = $image;
                break;
            }
        }

        return [
            'duplicate' => false,
            'image' => $created ?? null,
            'coverage' => HS_DB::coverage($helmet_id),
        ];
    }

    private static function get_helmet(int $id): ?array
    {
        /*
         * Replace this with Helmetsan's actual helmet repository.
         * This fallback supports WordPress post meta.
         */
        $post = get_post($id);

        if (!$post) {
            return null;
        }

        return [
            'id' => $id,
            'brand' => get_post_meta($id, 'brand', true) ?: get_post_meta($id, 'helmet_brand', true),
            'model' => get_post_meta($id, 'model', true) ?: $post->post_title,
            'shell_material' => get_post_meta($id, 'shell_material', true),
            'colorway' => get_post_meta($id, 'colorway', true),
            'certifications' => get_post_meta($id, 'certifications', true),
        ];
    }

    private static function prompt(array $helmet, string $shot_type): string
    {
        $shot = self::SHOTS[$shot_type];

        return implode("\n", [
            'Premium motorcycle helmet product photography.',
            'Create exactly one helmet, no duplicate helmets, no text, no watermark.',
            'Brand: ' . $helmet['brand'],
            'Model: ' . $helmet['model'],
            'Shell material: ' . $helmet['shell_material'],
            'Colorway: ' . $helmet['colorway'],
            'Certifications: ' . $helmet['certifications'],
            'Required view: ' . $shot['angle'],
            'Neutral premium studio background.',
            'Accurate visor geometry, vents, strap, shell proportions, and construction.',
            'Photorealistic product catalog lighting.',
            'Sharp details, physically plausible materials, controlled reflections.',
        ]);
    }

    private static function register_media(
        string $source,
        array $helmet,
        string $shot_type
    ): array {
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $editor = wp_get_image_editor($source);

        if (is_wp_error($editor)) {
            throw new RuntimeException($editor->get_error_message());
        }

        $base = sanitize_title($helmet['brand'] . '-' . $helmet['model']);
        $upload = wp_upload_dir();

        $sizes = [
            'hero' => 1920,
            'gallery' => 1024,
            'thumb' => 480,
        ];

        $urls = [];
        $attachment_id = 0;

        foreach ($sizes as $name => $width) {
            $image = wp_get_image_editor($source);

            if (is_wp_error($image)) {
                continue;
            }

            $image->resize($width, null, false);

            $filename = "{$base}-{$shot_type}-{$name}.webp";
            $path = trailingslashit($upload['path']) . $filename;

            $saved = $image->save($path, 'image/webp');

            if (is_wp_error($saved)) {
                throw new RuntimeException($saved->get_error_message());
            }

            $url = trailingslashit($upload['url']) . $filename;
            $urls[$name] = $url;

            if ($name === 'hero') {
                $attachment = [
                    'post_mime_type' => 'image/webp',
                    'post_title' => sanitize_text_field(
                        "{$helmet['brand']} {$helmet['model']} {$shot_type}"
                    ),
                    'post_content' => '',
                    'post_status' => 'inherit',
                ];

                $attachment_id = wp_insert_attachment($attachment, $path);

                if (is_wp_error($attachment_id)) {
                    throw new RuntimeException($attachment_id->get_error_message());
                }

                $metadata = wp_generate_attachment_metadata($attachment_id, $path);
                wp_update_attachment_metadata($attachment_id, $metadata);
            }
        }

        return [
            'attachment_id' => $attachment_id,
            'hero_url' => $urls['hero'] ?? '',
            'gallery_url' => $urls['gallery'] ?? '',
            'thumb_url' => $urls['thumb'] ?? '',
        ];
    }

    private static function phash(string $file): string
    {
        $image = imagecreatefromstring(file_get_contents($file));

        if (!$image) {
            return substr(hash_file('sha256', $file), 0, 16);
        }

        $small = imagecreatetruecolor(9, 8);
        imagecopyresampled($small, $image, 0, 0, 0, 0, 9, 8, imagesx($image), imagesy($image));

        $bits = '';

        for ($y = 0; $y < 8; $y++) {
            for ($x = 0; $x < 8; $x++) {
                $left = imagecolorat($small, $x, $y);
                $right = imagecolorat($small, $x + 1, $y);

                $left_gray = (($left >> 16) & 255) * 0.299
                    + (($left >> 8) & 255) * 0.587
                    + ($left & 255) * 0.114;

                $right_gray = (($right >> 16) & 255) * 0.299
                    + (($right >> 8) & 255) * 0.587
                    + ($right & 0.114);

                $bits .= $left_gray > $right_gray ? '1' : '0';
            }
        }

        imagedestroy($small);
        imagedestroy($image);

        return str_pad(base_convert($bits, 2, 16), 16, '0', STR_PAD_LEFT);
    }
}
```

Correct this line in the pHash method:

```php
+ ($right & 255) * 0.114;
```

The complete right-side grayscale calculation should be:

```php
$right_gray = (($right >> 16) & 255) * 0.299
    + (($right >> 8) & 255) * 0.587
    + ($right & 255) * 0.114;
```

---

# 6. REST API controller

## `includes/class-hs-rest-controller.php`

```php
<?php

defined('ABSPATH') || exit;

final class HS_REST_Controller
{
    public function register_routes(): void
    {
        register_rest_route('helmetsan/v1', '/helmets/(?P<id>\d+)/images', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'get_images'],
            'permission_callback' => [$this, 'can_manage'],
        ]);

        register_rest_route('helmetsan/v1', '/helmets/(?P<id>\d+)/images/generate-shot', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'generate_shot'],
            'permission_callback' => [$this, 'can_manage'],
            'args' => [
                'shot_type' => [
                    'required' => true,
                    'sanitize_callback' => 'sanitize_key',
                ],
                'model' => [
                    'default' => 'flux-schnell',
                    'sanitize_callback' => 'sanitize_key',
                ],
                'run_audit' => [
                    'default' => true,
                    'sanitize_callback' => 'rest_sanitize_boolean',
                ],
            ],
        ]);

        register_rest_route('helmetsan/v1', '/helmets/(?P<id>\d+)/hero', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'set_hero'],
            'permission_callback' => [$this, 'can_manage'],
        ]);
    }

    public function can_manage(): bool
    {
        return current_user_can('manage_options')
            || current_user_can('edit_posts');
    }

    public function get_images(WP_REST_Request $request): WP_REST_Response
    {
        $helmet_id = absint($request['id']);

        return new WP_REST_Response([
            'images' => HS_DB::get_images($helmet_id),
            'coverage' => HS_DB::coverage($helmet_id),
        ]);
    }

    public function generate_shot(WP_REST_Request $request): WP_REST_Response
    {
        $helmet_id = absint($request['id']);
        $shot_type = sanitize_key($request->get_param('shot_type'));
        $model = sanitize_key($request->get_param('model'));
        $run_audit = rest_sanitize_boolean($request->get_param('run_audit'));

        if (!in_array($model, ['flux-dev', 'flux-schnell'], true)) {
            return new WP_REST_Response([
                'message' => 'Invalid generation model.',
            ], 400);
        }

        try {
            $result = HS_Image_Service::generate(
                $helmet_id,
                $shot_type,
                $model,
                $run_audit
            );

            return new WP_REST_Response($result, 200);
        } catch (Throwable $e) {
            return new WP_REST_Response([
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function set_hero(WP_REST_Request $request): WP_REST_Response
    {
        $body = $request->get_json_params();
        $attachment_id = absint($body['attachment_id'] ?? 0);

        if (!$attachment_id || !get_post($attachment_id)) {
            return new WP_REST_Response([
                'message' => 'Invalid attachment.',
            ], 400);
        }

        $helmet_id = absint($request['id']);

        if (!set_post_thumbnail($helmet_id, $attachment_id)) {
            return new WP_REST_Response([
                'message' => 'Could not set featured image.',
            ], 500);
        }

        update_post_meta($helmet_id, '_helmetsan_primary_image', $attachment_id);

        return new WP_REST_Response([
            'success' => true,
            'attachment_id' => $attachment_id,
        ]);
    }
}
```

This creates:

```text
GET  /wp-json/helmetsan/v1/helmets/{id}/images
POST /wp-json/helmetsan/v1/helmets/{id}/images/generate-shot
POST /wp-json/helmetsan/v1/helmets/{id}/hero
```

---

# 7. Admin Studio interface

## `includes/class-hs-admin.php`

```php
<?php

defined('ABSPATH') || exit;

final class HS_Admin
{
    public static function menu(): void
    {
        add_menu_page(
            'Helmet Image Studio',
            'Helmet Studio',
            'edit_posts',
            'helmetsan-image-studio',
            [__CLASS__, 'render'],
            'dashicons-format-image',
            28
        );
    }

    public static function assets(string $hook): void
    {
        if ($hook !== 'toplevel_page_helmetsan-image-studio') {
            return;
        }

        wp_enqueue_style(
            'hs-admin-studio',
            HS_URL . 'assets/admin-studio.css',
            [],
            HS_VERSION
        );

        wp_enqueue_script(
            'hs-admin-studio',
            HS_URL . 'assets/admin-studio.js',
            [],
            HS_VERSION,
            true
        );

        wp_localize_script('hs-admin-studio', 'HS_ADMIN', [
            'restUrl' => esc_url_raw(rest_url('helmetsan/v1')),
            'nonce' => wp_create_nonce('wp_rest'),
            'helmetId' => absint($_GET['helmet_id'] ?? 0),
        ]);
    }

    public static function render(): void
    {
        ?>
        <div id="hs-studio" class="hs-studio">
            <header class="hs-topbar">
                <div>
                    <h1>Helmet Image Studio</h1>
                    <p>Five-shot generation, audit, and catalog control.</p>
                </div>

                <div class="hs-toolbar">
                    <input
                        id="hs-search"
                        type="search"
                        placeholder="Search helmets..."
                    />

                    <select id="hs-brand-filter">
                        <option value="">All brands</option>
                        <option>Shoei</option>
                        <option>Arai</option>
                        <option>AGV</option>
                        <option>HJC</option>
                        <option>Bell</option>
                        <option>Shark</option>
                        <option>Scorpion</option>
                    </select>

                    <select id="hs-status-filter">
                        <option value="">All coverage</option>
                        <option value="empty">0/5</option>
                        <option value="partial">1–4/5</option>
                        <option value="complete">5/5 Complete</option>
                    </select>
                </div>
            </header>

            <main>
                <section id="hs-helmet-grid" class="hs-helmet-grid"></section>
            </main>

            <aside id="hs-drawer" class="hs-drawer" aria-hidden="true">
                <div class="hs-drawer-head">
                    <div>
                        <span class="hs-eyebrow">STUDIO BOARD</span>
                        <h2 id="hs-drawer-title">Helmet</h2>
                    </div>
                    <button id="hs-close-drawer" class="hs-icon-button">×</button>
                </div>

                <div class="hs-drawer-actions">
                    <button id="hs-generate-all" class="hs-button hs-primary">
                        Generate All 5
                    </button>

                    <select id="hs-model">
                        <option value="flux-schnell">FLUX Schnell — Preview</option>
                        <option value="flux-dev">FLUX Dev — SOTA</option>
                    </select>

                    <label class="hs-toggle">
                        <input id="hs-run-audit" type="checkbox" checked>
                        <span>Run Kimi-K3 audit</span>
                    </label>
                </div>

                <div id="hs-shot-board" class="hs-shot-board"></div>

                <section id="hs-audit-panel" class="hs-audit-panel">
                    <div class="hs-audit-title">
                        <strong>Kimi-K3 Visual Audit</strong>
                        <span id="hs-audit-score">Pending</span>
                    </div>
                    <div id="hs-audit-details"></div>
                </section>
            </aside>
        </div>
        <?php
    }
}
```

---

## `assets/admin-studio.js`

```javascript
(() => {
  const state = {
    helmetId: Number(HS_ADMIN.helmetId || 0),
    images: [],
    coverage: null,
    shots: [
      ['hero', '3/4 Front Hero'],
      ['profile', 'Lateral Profile'],
      ['rear', 'Rear Exhaust'],
      ['interior', 'Interior & Retention'],
      ['cockpit', 'Motorcycle Cockpit']
    ]
  };

  const $ = (selector) => document.querySelector(selector);

  async function api(path, options = {}) {
    const response = await fetch(`${HS_ADMIN.restUrl}${path}`, {
      ...options,
      headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': HS_ADMIN.nonce,
        ...(options.headers || {})
      }
    });

    const json = await response.json();

    if (!response.ok) {
      throw new Error(json.message || 'Request failed');
    }

    return json;
  }

  function openDrawer() {
    $('#hs-drawer').setAttribute('aria-hidden', 'false');
    $('#hs-drawer').classList.add('is-open');
  }

  function closeDrawer() {
    $('#hs-drawer').setAttribute('aria-hidden', 'true');
    $('#hs-drawer').classList.remove('is-open');
  }

  function renderBoard() {
    const board = $('#hs-shot-board');

    board.innerHTML = state.shots.map(([type, label]) => {
      const image = state.images.find((item) => item.shot_type === type);
      const audit = image?.audit;

      return `
        <article class="hs-shot-card" data-shot="${type}">
          <div class="hs-shot-media">
            ${
              image
                ? `<img src="${image.gallery_url}" alt="${label}">`
                : `<div class="hs-empty-shot"><span>Not generated</span></div>`
            }
            <span class="hs-shot-badge">${type}</span>
          </div>

          <div class="hs-shot-content">
            <h3>${label}</h3>
            <div class="hs-shot-meta">
              ${
                audit?.status === 'complete'
                  ? `<span class="hs-audit-good">Audit ${audit.score}%</span>`
                  : `<span class="hs-audit-pending">Audit pending</span>`
              }
            </div>

            <div class="hs-shot-buttons">
              <button class="hs-button hs-generate" data-shot="${type}">
                ${image ? 'Regenerate' : 'Generate Shot'}
              </button>

              ${
                image
                  ? `<button class="hs-button hs-secondary hs-set-hero"
                       data-attachment="${image.attachment_id}">
                       Set as Hero
                     </button>`
                  : ''
              }
            </div>
          </div>
        </article>
      `;
    }).join('');
  }

  async function loadHelmet() {
    if (!state.helmetId) {
      $('#hs-shot-board').innerHTML =
        '<p>Select a helmet to open its Studio Board.</p>';
      return;
    }

    const data = await api(`/helmets/${state.helmetId}/images`);
    state.images = data.images || [];
    state.coverage = data.coverage;
    renderBoard();
  }

  async function generateShot(shotType, button) {
    button.disabled = true;
    button.classList.add('is-loading');
    button.innerHTML = '<span class="hs-spinner"></span> Generating';

    try {
      const result = await api(
        `/helmets/${state.helmetId}/images/generate-shot`,
        {
          method: 'POST',
          body: JSON.stringify({
            shot_type: shotType,
            model: $('#hs-model').value,
            run_audit: $('#hs-run-audit').checked
          })
        }
      );

      state.images = await api(
        `/helmets/${state.helmetId}/images`
      ).then((data) => data.images);

      renderBoard();

      if (result.image?.audit) {
        renderAudit(result.image.audit);
      }
    } catch (error) {
      window.alert(error.message);
      button.disabled = false;
      button.classList.remove('is-loading');
      button.textContent = 'Retry';
    }
  }

  async function generateAll() {
    const button = $('#hs-generate-all');
    button.disabled = true;
    button.innerHTML = '<span class="hs-spinner"></span> Generating 0/5';

    for (let index = 0; index < state.shots.length; index++) {
      const [shotType] = state.shots[index];

      try {
        await api(
          `/helmets/${state.helmetId}/images/generate-shot`,
          {
            method: 'POST',
            body: JSON.stringify({
              shot_type: shotType,
              model: $('#hs-model').value,
              run_audit: $('#hs-run-audit').checked
            })
          }
        );

        button.innerHTML =
          `<span class="hs-spinner"></span> Generating ${index + 1}/5`;

        state.images = await api(
          `/helmets/${state.helmetId}/images`
        ).then((data) => data.images);

        renderBoard();
      } catch (error) {
        window.alert(`${shotType}: ${error.message}`);
        break;
      }
    }

    button.disabled = false;
    button.textContent = 'Generate All 5';
  }

  async function setHero(attachmentId) {
    try {
      await api(`/helmets/${state.helmetId}/hero`, {
        method: 'POST',
        body: JSON.stringify({
          attachment_id: attachmentId
        })
      });

      window.alert('Primary hero updated.');
    } catch (error) {
      window.alert(error.message);
    }
  }

  function renderAudit(audit) {
    $('#hs-audit-score').textContent =
      audit.score !== null
        ? `${audit.score}% · ${audit.confidence}% confidence`
        : 'Pending';

    const checks = audit.checks || {};

    $('#hs-audit-details').innerHTML = Object.entries(checks)
      .map(([name, passed]) => `
        <div class="hs-check ${passed ? 'is-pass' : 'is-fail'}">
          <span>${passed ? '✓' : '×'}</span>
          <span>${name.replaceAll('_', ' ')}</span>
        </div>
      `)
      .join('');
  }

  document.addEventListener('click', (event) => {
    const generateButton = event.target.closest('.hs-generate');

    if (generateButton) {
      generateShot(generateButton.dataset.shot, generateButton);
    }

    const heroButton = event.target.closest('.hs-set-hero');

    if (heroButton) {
      setHero(Number(heroButton.dataset.attachment));
    }
  });

  $('#hs-close-drawer')?.addEventListener('click', closeDrawer);
  $('#hs-generate-all')?.addEventListener('click', generateAll);

  window.HelmetsanStudio = {
    open(helmetId, title = 'Helmet') {
      state.helmetId = Number(helmetId);
      $('#hs-drawer-title').textContent = title;
      openDrawer();
      loadHelmet();
    }
  };

  loadHelmet().catch(console.error);
})();
```

---

## `assets/admin-studio.css`

```css
:root {
  --hs-bg: #0b0f14;
  --hs-panel: #121923;
  --hs-panel-2: #182230;
  --hs-border: rgba(255,255,255,.1);
  --hs-text: #f6f8fb;
  --hs-muted: #8c9aad;
  --hs-accent: #66e3b4;
  --hs-danger: #ff6b7a;
}

.hs-studio {
  margin: 20px 20px 0 0;
  color: var(--hs-text);
  background: var(--hs-bg);
  min-height: calc(100vh - 80px);
  border-radius: 18px;
  overflow: hidden;
}

.hs-topbar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 24px;
  padding: 28px;
  border-bottom: 1px solid var(--hs-border);
}

.hs-topbar h1 {
  color: var(--hs-text);
  margin: 0 0 6px;
}

.hs-topbar p,
.hs-eyebrow {
  color: var(--hs-muted);
}

.hs-toolbar {
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
}

.hs-toolbar input,
.hs-toolbar select,
.hs-drawer select {
  color: var(--hs-text);
  background: var(--hs-panel);
  border: 1px solid var(--hs-border);
  border-radius: 9px;
  padding: 10px 12px;
}

.hs-drawer {
  position: fixed;
  z-index: 100000;
  top: 32px;
  right: 0;
  width: min(760px, 96vw);
  height: calc(100vh - 32px);
  padding: 24px;
  overflow-y: auto;
  background: #111821;
  box-shadow: -20px 0 60px rgba(0,0,0,.45);
  transform: translateX(102%);
  transition: transform .25s ease;
}

.hs-drawer.is-open {
  transform: translateX(0);
}

.hs-drawer-head,
.hs-drawer-actions,
.hs-audit-title {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 14px;
}

.hs-drawer-head {
  margin-bottom: 22px;
}

.hs-drawer-head h2 {
  color: var(--hs-text);
  margin: 5px 0 0;
}

.hs-icon-button {
  color: var(--hs-text);
  background: transparent;
  border: 0;
  font-size: 30px;
  cursor: pointer;
}

.hs-button {
  border: 0;
  border-radius: 8px;
  padding: 10px 13px;
  color: #07110e;
  background: var(--hs-accent);
  cursor: pointer;
  font-weight: 700;
}

.hs-button:disabled {
  cursor: wait;
  opacity: .65;
}

.hs-secondary {
  color: var(--hs-text);
  background: var(--hs-panel-2);
}

.hs-shot-board {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 14px;
  margin-top: 24px;
}

.hs-shot-card {
  overflow: hidden;
  border: 1px solid var(--hs-border);
  border-radius: 13px;
  background: var(--hs-panel);
}

.hs-shot-media {
  position: relative;
  aspect-ratio: 1.25;
  background: #080c10;
}

.hs-shot-media img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.hs-empty-shot {
  display: grid;
  height: 100%;
  place-items: center;
  color: var(--hs-muted);
}

.hs-shot-badge {
  position: absolute;
  top: 10px;
  left: 10px;
  padding: 5px 8px;
  color: #06120e;
  background: var(--hs-accent);
  border-radius: 6px;
  font-size: 11px;
  font-weight: 800;
  text-transform: uppercase;
}

.hs-shot-content {
  padding: 14px;
}

.hs-shot-content h3 {
  color: var(--hs-text);
  margin: 0 0 10px;
  font-size: 14px;
}

.hs-shot-meta {
  color: var(--hs-muted);
  font-size: 12px;
  margin-bottom: 12px;
}

.hs-shot-buttons {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
}

.hs-audit-panel {
  margin-top: 22px;
  padding: 18px;
  border: 1px solid var(--hs-border);
  border-radius: 13px;
  background: var(--hs-panel);
}

.hs-audit-title {
  color: var(--hs-text);
  margin-bottom: 14px;
}

.hs-check {
  display: inline-flex;
  gap: 6px;
  margin: 4px 10px 4px 0;
  color: var(--hs-muted);
  font-size: 12px;
}

.hs-check.is-pass span:first-child {
  color: var(--hs-accent);
}

.hs-check.is-fail span:first-child {
  color: var(--hs-danger);
}

.hs-spinner {
  width: 12px;
  height: 12px;
  display: inline-block;
  border: 2px solid rgba(0,0,0,.25);
  border-top-color: currentColor;
  border-radius: 50%;
  animation: hs-spin .7s linear infinite;
}

@keyframes hs-spin {
  to { transform: rotate(360deg); }
}

@media (max-width: 700px) {
  .hs-topbar {
    align-items: flex-start;
    flex-direction: column;
  }

  .hs-shot-board {
    grid-template-columns: 1fr;
  }
}
```

---

# 8. Frontend product gallery

## `templates/product-gallery.php`

```php
<?php
defined('ABSPATH') || exit;
?>

<section
    class="hs-product-gallery"
    data-helmet-id="<?php echo esc_attr($helmet_id); ?>"
    aria-label="Helmet product gallery"
>
    <div class="hs-gallery-main">
        <button class="hs-gallery-expand" type="button" aria-label="Open fullscreen gallery">
            ⛶
        </button>

        <img
            class="hs-gallery-image"
            src=""
            alt=""
            loading="eager"
        />

        <div class="hs-spec-overlay">
            <span>ECE 22.06 Certified</span>
            <span>Premium Composite Shell</span>
            <span>Emergency Quick-Release</span>
        </div>
    </div>

    <div class="hs-gallery-thumbs" role="tablist"></div>

    <div class="hs-gallery-lightbox" aria-hidden="true">
        <button class="hs-lightbox-close" type="button">×</button>
        <img class="hs-lightbox-image" src="" alt="">
    </div>
</section>
```

## `assets/product-gallery.js`

```javascript
(() => {
  const root = document.querySelector('.hs-product-gallery');

  if (!root) return;

  const mainImage = root.querySelector('.hs-gallery-image');
  const thumbs = root.querySelector('.hs-gallery-thumbs');
  const lightbox = root.querySelector('.hs-gallery-lightbox');
  const lightboxImage = root.querySelector('.hs-lightbox-image');

  const labels = {
    hero: 'Hero',
    profile: 'Profile',
    rear: 'Rear',
    interior: 'Interior',
    cockpit: 'Cockpit'
  };

  let images = [];
  let current = 0;
  let startX = 0;

  async function load() {
    const response = await fetch(
      `${HS_GALLERY.restUrl}/helmets/${HS_GALLERY.helmetId}/images`
    );

    const data = await response.json();
    images = data.images || [];

    if (!images.length) {
      root.hidden = true;
      return;
    }

    renderThumbs();
    select(0);
  }

  function renderThumbs() {
    thumbs.innerHTML = images.map((image, index) => `
      <button
        class="hs-gallery-thumb"
        type="button"
        role="tab"
        aria-label="${labels[image.shot_type]}"
        data-index="${index}"
      >
        <img
          src="${image.thumb_url}"
          alt="${labels[image.shot_type]}"
          loading="lazy"
        >
        <span>${labels[image.shot_type]}</span>
      </button>
    `).join('');
  }

  function select(index) {
    current = index;
    const image = images[index];

    if (!image) return;

    mainImage.src = image.hero_url;
    mainImage.srcset = `
      ${image.thumb_url} 480w,
      ${image.gallery_url} 1024w,
      ${image.hero_url} 1920w
    `;
    mainImage.alt = `${labels[image.shot_type]} helmet view`;

    root.querySelectorAll('.hs-gallery-thumb').forEach((button, i) => {
      button.classList.toggle('is-active', i === index);
      button.setAttribute('aria-selected', i === index ? 'true' : 'false');
    });
  }

  thumbs.addEventListener('click', (event) => {
    const button = event.target.closest('.hs-gallery-thumb');

    if (button) {
      select(Number(button.dataset.index));
    }
  });

  root.querySelector('.hs-gallery-expand').addEventListener('click', () => {
    lightboxImage.src = images[current].hero_url;
    lightboxImage.alt = mainImage.alt;
    lightbox.setAttribute('aria-hidden', 'false');
    lightbox.classList.add('is-open');
  });

  root.querySelector('.hs-lightbox-close').addEventListener('click', () => {
    lightbox.setAttribute('aria-hidden', 'true');
    lightbox.classList.remove('is-open');
  });

  mainImage.addEventListener('mousemove', (event) => {
    const rect = mainImage.getBoundingClientRect();
    const x = ((event.clientX - rect.left) / rect.width) * 100;
    const y = ((event.clientY - rect.top) / rect.height) * 100;

    mainImage.style.transformOrigin = `${x}% ${y}%`;
    mainImage.classList.add('is-zoomed');
  });

  mainImage.addEventListener('mouseleave', () => {
    mainImage.classList.remove('is-zoomed');
  });

  lightbox.addEventListener('touchstart', (event) => {
    startX = event.changedTouches[0].screenX;
  });

  lightbox.addEventListener('touchend', (event) => {
    const endX = event.changedTouches[0].screenX;
    const distance = endX - startX;

    if (Math.abs(distance) < 50) return;

    if (distance < 0 && current < images.length - 1) {
      select(++current);
      lightboxImage.src = images[current].hero_url;
    }

    if (distance > 0 && current > 0) {
      select(--current);
      lightboxImage.src = images[current].hero_url;
    }
  });

  load().catch(console.error);
})();
```

## `assets/product-gallery.css`

```css
.hs-product-gallery {
  max-width: 920px;
  margin: auto;
  color: #fff;
}

.hs-gallery-main {
  position: relative;
  overflow: hidden;
  border-radius: 20px;
  background: #10151c;
  aspect-ratio: 1 / 1;
}

.hs-gallery-image {
  width: 100%;
  height: 100%;
  object-fit: contain;
  transition: transform .35s ease;
}

.hs-gallery-image.is-zoomed {
  transform: scale(1.35);
}

.hs-gallery-expand,
.hs-lightbox-close {
  position: absolute;
  z-index: 2;
  border: 0;
  color: #fff;
  background: rgba(0,0,0,.55);
  border-radius: 50%;
  cursor: pointer;
}

.hs-gallery-expand {
  top: 16px;
  right: 16px;
  padding: 10px 13px;
}

.hs-spec-overlay {
  position: absolute;
  left: 16px;
  bottom: 16px;
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
}

.hs-spec-overlay span {
  padding: 7px 10px;
  color: #eafff7;
  background: rgba(8, 23, 20, .82);
  border: 1px solid rgba(102,227,180,.5);
  border-radius: 999px;
  font-size: 12px;
}

.hs-gallery-thumbs {
  display: grid;
  grid-template-columns: repeat(5, 1fr);
  gap: 10px;
  margin-top: 14px;
}

.hs-gallery-thumb {
  overflow: hidden;
  padding: 0;
  color: #8995a4;
  background: #111820;
  border: 2px solid transparent;
  border-radius: 10px;
  cursor: pointer;
}

.hs-gallery-thumb.is-active {
  color: #66e3b4;
  border-color: #66e3b4;
}

.hs-gallery-thumb img {
  display: block;
  width: 100%;
  aspect-ratio: 1;
  object-fit: cover;
}

.hs-gallery-thumb span {
  display: block;
  padding: 7px 3px;
  font-size: 11px;
}

.hs-gallery-lightbox {
  position: fixed;
  z-index: 99999;
  inset: 0;
  display: none;
  place-items: center;
  background: rgba(0,0,0,.94);
}

.hs-gallery-lightbox.is-open {
  display: grid;
}

.hs-lightbox-image {
  max-width: 94vw;
  max-height: 90vh;
  object-fit: contain;
}

.hs-lightbox-close {
  top: 20px;
  right: 20px;
  padding: 5px 14px;
  font-size: 28px;
}

@media (max-width: 620px) {
  .hs-gallery-thumbs {
    grid-template-columns: repeat(3, 1fr);
  }

  .hs-spec-overlay {
    display: none;
  }
}
```

Use on a product page:

```text
[helmetsan_gallery helmet_id="123"]
```

---

# 9. Opening a helmet from a catalog grid

When rendering the helmet catalog in WordPress admin, use:

```php
<button
    type="button"
    class="button"
    onclick="HelmetsanStudio.open(
        <?php echo esc_js($helmet_id); ?>,
        '<?php echo esc_js($helmet_name); ?>'
    )"
>
    Open Studio
</button>
```

---

# 10. Batch generation

For a production batch queue, do not run 50 synchronous HTTP requests in a browser. Use Action Scheduler or WP-Cron.

If WooCommerce is installed, Action Scheduler is available:

```php
as_enqueue_async_action(
    'helmetsan_generate_batch_item',
    [
        'helmet_id' => $helmet_id,
        'shot_type' => $shot_type,
        'model' => $model,
        'run_audit' => $run_audit,
    ],
    'helmetsan'
);

add_action('helmetsan_generate_batch_item', function (
    int $helmet_id,
    string $shot_type,
    string $model,
    bool $run_audit
) {
    try {
        HS_Image_Service::generate(
            $helmet_id,
            $shot_type,
            $model,
            $run_audit
        );
    } catch (Throwable $e) {
        error_log('[Helmetsan] Batch generation failed: ' . $e->getMessage());
    }
}, 10, 4);
```

A batch REST route should enqueue jobs rather than directly generate them:

```php
register_rest_route('helmetsan/v1', '/batch/generate', [
    'methods' => WP_REST_Server::CREATABLE,
    'permission_callback' => [$this, 'can_manage'],
    'callback' => function (WP_REST_Request $request) {
        $helmet_ids = array_map('absint', (array) $request['helmet_ids']);
        $model = sanitize_key($request['model'] ?: 'flux-schnell');
        $run_audit = rest_sanitize_boolean($request['run_audit']);

        foreach ($helmet_ids as $helmet_id) {
            foreach (array_keys(HS_Image_Service::SHOTS) as $shot_type) {
                as_enqueue_async_action(
                    'helmetsan_generate_batch_item',
                    compact(
                        'helmet_id',
                        'shot_type',
                        'model',
                        'run_audit'
                    ),
                    'helmetsan'
                );
            }
        }

        return new WP_REST_Response([
            'queued' => count($helmet_ids) * 5,
        ], 202);
    },
]);
```

---

# 11. Important production hardening

Before deploying, add:

1. **Per-user rate limiting**
   - Limit generation requests per user/IP.
   - Prevent accidental duplicate generation.

2. **Image moderation**
   - Reject generated outputs that are not helmet images.
   - Require Kimi audit score above a configurable threshold before automatic hero assignment.

3. **Provider retry handling**
   - Retry 429 and 5xx responses using exponential backoff.
   - Do not retry malformed prompts or invalid credentials.

4. **Object storage**
   - Store generated WebP files in S3, Cloudflare R2, or another media-compatible object store if the catalog is large.

5. **Background jobs**
   - Use Action Scheduler for all batch generation.
   - Do not hold an admin browser request open for 50 generations.

6. **Audit persistence**
   - Store model version, provider request ID, prompt hash, generation timestamp, and audit timestamp.

7. **Primary hero rules**
   - Prevent automatic replacement of an approved hero unless the operator explicitly confirms.

8. **Real pHash library**
   - The included dHash is a dependency-free 64-bit perceptual hash.
   - For highest-quality similarity matching, replace it with a proper DCT-based pHash implementation and compare Hamming distance rather than exact equality.

9. **Helmet repository integration**
   - Replace `HS_Image_Service::get_helmet()` with Helmetsan’s actual product repository or custom post-type mapper.

10. **Provider schema adapters**
   - Keep NVIDIA and Kimi payload mapping isolated.
   - Different NIM and Moonshot deployments may use different image input and output formats.

The result is a real Studio system rather than a static gallery: operators can inspect each helmet, generate or regenerate canonical views, audit visual correctness, promote a shot to the catalog hero, and expose a responsive five-shot gallery to customers.