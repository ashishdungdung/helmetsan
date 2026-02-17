<?php

declare(strict_types=1);

namespace Helmetsan\Core\Media;

use Helmetsan\Core\Support\Config;
use WP_Post;

final class MediaEngine
{
    public function __construct(private readonly Config $config)
    {
    }

    public function register(): void
    {
        // Register after the core Helmetsan menu is added.
        add_action('admin_menu', [$this, 'registerMenu'], 20);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
        add_action('media_buttons', [$this, 'renderEditorButton'], 20);
        add_action('add_meta_boxes', [$this, 'registerMetaBoxes']);
        add_action('save_post', [$this, 'saveMetaBox'], 10, 2);
        add_action('admin_post_helmetsan_media_apply_logo', [$this, 'handleApplyLogo']);
        add_action('admin_post_helmetsan_media_delete_logo_attachment', [$this, 'handleDeleteLogoAttachment']);
    }

    public function registerMenu(): void
    {
        add_submenu_page(
            'helmetsan-dashboard',
            'Media Engine',
            'Media Engine',
            'manage_options',
            'helmetsan-media-engine',
            [$this, 'mediaPage']
        );

        // Also expose inside the native Media menu so editors can use it alongside Media Library.
        add_submenu_page(
            'upload.php',
            'Helmetsan Logo Finder',
            'Helmetsan Logos',
            'upload_files',
            'helmetsan-media-engine',
            [$this, 'mediaPage']
        );
    }

    public function enqueueAssets(string $hook): void
    {
        if (! in_array($hook, ['post.php', 'post-new.php'], true)) {
            return;
        }

        wp_enqueue_media();
        wp_enqueue_script(
            'helmetsan-media-engine-admin',
            plugins_url('assets/js/media-engine-admin.js', HELMETSAN_CORE_FILE),
            ['jquery'],
            HELMETSAN_CORE_VERSION,
            true
        );
    }

    public function mediaPage(): void
    {
        if (! current_user_can('upload_files')) {
            wp_die('Unauthorized');
        }

        $name   = isset($_GET['name']) ? sanitize_text_field((string) $_GET['name']) : '';
        $domain = isset($_GET['domain']) ? sanitize_text_field((string) $_GET['domain']) : '';
        $type   = isset($_GET['entity']) ? sanitize_key((string) $_GET['entity']) : 'brand';
        $provider = isset($_GET['provider']) ? sanitize_key((string) $_GET['provider']) : 'all';
        $postId = isset($_GET['post_id']) ? (int) $_GET['post_id'] : 0;
        $saved  = isset($_GET['saved']) ? (int) $_GET['saved'] : 0;
        $imported = isset($_GET['imported']) ? (int) $_GET['imported'] : 0;
        $assigned = isset($_GET['assigned']) ? (int) $_GET['assigned'] : 0;

        $candidates = [];
        $diagnostics = [];
        $searchContext = [];
        if ($name !== '' || $domain !== '') {
            $searchContext = $this->buildSearchContext($name, $domain);
            $candidates = $this->resolveCandidates($name, $domain, $type, $provider, $diagnostics);
        }

        echo '<div class="wrap helmetsan-wrap">';
        echo '<h1>Media Engine</h1>';
        if ($saved === 1) {
            echo '<div class="notice notice-success is-dismissible"><p>Logo saved successfully.</p></div>';
        }
        if ($imported === 1) {
            echo '<div class="notice notice-success is-dismissible"><p>Logo imported to Media Library successfully.</p></div>';
        }
        if ($assigned === 1) {
            echo '<div class="notice notice-success is-dismissible"><p>Logo assigned to post successfully.</p></div>';
        }
        echo '<p>Resolve logos from Simple Icons, Brandfetch, Logo.dev, and Wikimedia. Apply selected logo to any post/page/CPT.</p>';

        echo '<form method="get" class="hs-inline-form" style="margin:12px 0;">';
        echo '<input type="hidden" name="page" value="helmetsan-media-engine" />';
        echo '<label for="hs-logo-name">Name</label>';
        echo '<input id="hs-logo-name" type="text" name="name" value="' . esc_attr($name) . '" placeholder="Shoei" />';
        echo '<label for="hs-logo-domain">Domain</label>';
        echo '<input id="hs-logo-domain" type="text" name="domain" value="' . esc_attr($domain) . '" placeholder="shoei.com" />';
        echo '<label for="hs-logo-entity">Entity</label>';
        echo '<select id="hs-logo-entity" name="entity">';
        foreach (['brand', 'helmet', 'accessory', 'motorcycle', 'safety_standard', 'dealer', 'page', 'post'] as $opt) {
            echo '<option value="' . esc_attr($opt) . '" ' . selected($type, $opt, false) . '>' . esc_html($opt) . '</option>';
        }
        echo '</select>';
        echo '<label for="hs-logo-provider">Provider</label>';
        echo '<select id="hs-logo-provider" name="provider">';
        foreach (['all', 'logodev', 'brandfetch', 'simpleicons', 'wikimedia'] as $opt) {
            echo '<option value="' . esc_attr($opt) . '" ' . selected($provider, $opt, false) . '>' . esc_html($opt) . '</option>';
        }
        echo '</select>';
        echo '<label for="hs-logo-post">Post ID (optional)</label>';
        echo '<input id="hs-logo-post" type="number" min="1" name="post_id" value="' . esc_attr($postId > 0 ? (string) $postId : '') . '" />';
        submit_button('Find Logos', 'secondary', '', false);
        echo '</form>';

        if (($name !== '' || $domain !== '') && is_array($searchContext)) {
            $brandKey = isset($searchContext['brand_key']) ? (string) $searchContext['brand_key'] : '';
            $domains  = isset($searchContext['domains']) && is_array($searchContext['domains']) ? $searchContext['domains'] : [];
            $slugs    = isset($searchContext['slugs']) && is_array($searchContext['slugs']) ? $searchContext['slugs'] : [];
            $quick    = isset($searchContext['quick_links']) && is_array($searchContext['quick_links']) ? $searchContext['quick_links'] : [];

            echo '<section class="hs-panel" style="margin:12px 0;">';
            echo '<h2 style="margin-top:0;">Search Context</h2>';
            echo '<p>Context generated for provider lookups and fallbacks.</p>';
            echo '<p><strong>Detected brand key:</strong> ' . esc_html($brandKey !== '' ? $brandKey : '-') . '</p>';
            echo '<p><strong>Suggested domains:</strong> ' . esc_html($domains !== [] ? implode(', ', array_map('strval', $domains)) : '-') . '</p>';
            echo '<p><strong>Suggested slugs:</strong> ' . esc_html($slugs !== [] ? implode(', ', array_map('strval', $slugs)) : '-') . '</p>';
            if ($quick !== []) {
                echo '<p><strong>Quick checks:</strong> ';
                $links = [];
                foreach ($quick as $item) {
                    if (! is_array($item)) {
                        continue;
                    }
                    $label = isset($item['label']) ? (string) $item['label'] : '';
                    $url = isset($item['url']) ? (string) $item['url'] : '';
                    if ($label === '' || $url === '') {
                        continue;
                    }
                    $links[] = '<a class="hs-link" target="_blank" rel="noopener noreferrer" href="' . esc_url($url) . '">' . esc_html($label) . '</a>';
                }
                echo wp_kses_post(implode(' | ', $links));
                echo '</p>';
            }
            echo '</section>';
        }

        if ($candidates !== []) {
            echo '<div class="hs-grid hs-grid--2">';
            foreach ($candidates as $item) {
                $url = isset($item['url']) ? (string) $item['url'] : '';
                if ($url === '') {
                    continue;
                }
                $provider = isset($item['provider']) ? (string) $item['provider'] : 'provider';
                $label = isset($item['label']) ? (string) $item['label'] : $provider;
                echo '<article class="hs-panel">';
                echo '<p><strong>' . esc_html($label) . '</strong> <code>' . esc_html($provider) . '</code></p>';
                echo '<div style="min-height:72px;display:flex;align-items:center;justify-content:center;background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:12px;">';
                echo '<img src="' . esc_url($url) . '" alt="' . esc_attr($label) . '" style="max-height:64px;max-width:100%;object-fit:contain;" />';
                echo '</div>';
                echo '<p><a class="hs-link" href="' . esc_url($url) . '" target="_blank" rel="noopener noreferrer">Open source URL</a></p>';
                echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
                wp_nonce_field('helmetsan_media_apply_logo', 'helmetsan_media_nonce');
                echo '<input type="hidden" name="action" value="helmetsan_media_apply_logo" />';
                echo '<input type="hidden" name="logo_url" value="' . esc_attr($url) . '" />';
                echo '<input type="hidden" name="provider" value="' . esc_attr($provider) . '" />';
                echo '<input type="hidden" name="return" value="' . esc_attr((string) wp_get_referer()) . '" />';
                echo '<p><label for="hs_apply_post_' . esc_attr((string) md5($url)) . '"><strong>Post ID</strong> (optional)</label></p>';
                echo '<input id="hs_apply_post_' . esc_attr((string) md5($url)) . '" type="number" min="1" class="small-text" name="post_id" value="' . esc_attr($postId > 0 ? (string) $postId : '') . '" />';
                $defaultSideload = ! empty($this->config->mediaConfig()['auto_sideload_enabled']);
                echo '<p><label><input type="checkbox" name="sideload" value="1" ' . checked($defaultSideload, true, false) . ' /> Import to Media Library</label></p>';
                echo '<p><label><input type="checkbox" name="assign" value="1" ' . checked($postId > 0, true, false) . ' /> Assign to post (if Post ID provided)</label></p>';
                submit_button('Import Selected Logo', 'small', '', false);
                echo '</form>';
                echo '</article>';
            }
            echo '</div>';
        } elseif ($name !== '' || $domain !== '') {
            echo '<div class="notice notice-warning"><p>No logo candidates found for the selected provider/input. Try setting a domain (e.g. brand.com) and retry.</p></div>';
        }

        if (($name !== '' || $domain !== '') && is_array($diagnostics)) {
            $domains = isset($diagnostics['attempted_domains']) && is_array($diagnostics['attempted_domains']) ? $diagnostics['attempted_domains'] : [];
            $slugs = isset($diagnostics['attempted_slugs']) && is_array($diagnostics['attempted_slugs']) ? $diagnostics['attempted_slugs'] : [];
            $checks = isset($diagnostics['checks']) && is_array($diagnostics['checks']) ? $diagnostics['checks'] : [];

            echo '<section class="hs-panel" style="margin-top:16px;">';
            echo '<h2 style="margin-top:0;">Provider Diagnostics</h2>';
            echo '<p>Attempted provider checks for this search.</p>';
            echo '<p><strong>Domains:</strong> ' . esc_html($domains !== [] ? implode(', ', array_map('strval', $domains)) : '-') . '</p>';
            echo '<p><strong>Slugs:</strong> ' . esc_html($slugs !== [] ? implode(', ', array_map('strval', $slugs)) : '-') . '</p>';

            if ($checks !== []) {
                echo '<table class="widefat striped"><thead><tr><th>Provider</th><th>Target</th><th>Status</th><th>Result</th><th>Notes</th></tr></thead><tbody>';
                foreach ($checks as $check) {
                    if (! is_array($check)) {
                        continue;
                    }
                    $p = isset($check['provider']) ? (string) $check['provider'] : '-';
                    $t = isset($check['target']) ? (string) $check['target'] : '-';
                    $s = isset($check['status']) ? (string) $check['status'] : '-';
                    $r = isset($check['result']) ? (string) $check['result'] : '-';
                    $n = isset($check['note']) ? (string) $check['note'] : '-';
                    echo '<tr>';
                    echo '<td><code>' . esc_html($p) . '</code></td>';
                    echo '<td>' . esc_html($t) . '</td>';
                    echo '<td>' . esc_html($s) . '</td>';
                    echo '<td>' . esc_html($r) . '</td>';
                    echo '<td>' . esc_html($n) . '</td>';
                    echo '</tr>';
                }
                echo '</tbody></table>';
            } else {
                echo '<p>No checks executed.</p>';
            }

            echo '</section>';
        }
        echo '</div>';
    }

    public function registerMetaBoxes(): void
    {
        if (! is_admin()) {
            return;
        }
        $types = get_post_types(['public' => true], 'names');
        if (! is_array($types)) {
            return;
        }
        foreach ($types as $type) {
            if (! is_string($type) || in_array($type, ['attachment'], true)) {
                continue;
            }
            add_meta_box(
                'helmetsan_media_logo',
                'Helmetsan Logo Media',
                [$this, 'renderLogoMetaBox'],
                $type,
                'side',
                'default'
            );
        }
    }

    public function renderLogoMetaBox(WP_Post $post): void
    {
        wp_nonce_field('helmetsan_logo_meta', 'helmetsan_logo_meta_nonce');
        $logo = (string) get_post_meta($post->ID, '_helmetsan_logo_url', true);
        $provider = (string) get_post_meta($post->ID, '_helmetsan_logo_provider', true);
        $domain = (string) get_post_meta($post->ID, '_helmetsan_logo_domain', true);
        $attachmentId = (int) get_post_meta($post->ID, '_helmetsan_logo_attachment_id', true);
        $engineUrl = add_query_arg([
            'page' => 'helmetsan-media-engine',
            'name' => $post->post_title,
            'domain' => $domain,
            'entity' => $post->post_type,
            'post_id' => $post->ID,
        ], admin_url('admin.php'));

        if ($logo !== '') {
            echo '<p><img src="' . esc_url($logo) . '" alt="" style="max-width:100%;max-height:60px;object-fit:contain;" /></p>';
        }
        echo '<p><label for="helmetsan_logo_domain"><strong>Logo Domain</strong></label>';
        echo '<input id="helmetsan_logo_domain" type="text" class="widefat" name="helmetsan_logo_domain" value="' . esc_attr($domain) . '" placeholder="brand.com" /></p>';
        echo '<p><label for="helmetsan_logo_url"><strong>Logo URL</strong></label>';
        echo '<input id="helmetsan_logo_url" type="url" class="widefat" name="helmetsan_logo_url" value="' . esc_attr($logo) . '" placeholder="https://..." /></p>';
        echo '<p><label for="helmetsan_logo_provider"><strong>Provider</strong></label>';
        echo '<input id="helmetsan_logo_provider" type="text" class="widefat" name="helmetsan_logo_provider" value="' . esc_attr($provider) . '" placeholder="simpleicons" /></p>';
        echo '<input type="hidden" id="helmetsan_logo_attachment_id" name="helmetsan_logo_attachment_id" value="' . esc_attr((string) $attachmentId) . '" />';
        echo '<p><button type="button" class="button helmetsan-select-logo-media">Select from Media Library</button> ';
        echo '<button type="button" class="button-link-delete helmetsan-clear-logo-media">Clear</button></p>';
        if ($attachmentId > 0) {
            $editUrl = get_edit_post_link($attachmentId, '');
            $deleteUrl = add_query_arg(
                [
                    'action' => 'helmetsan_media_delete_logo_attachment',
                    'post_id' => $post->ID,
                    'attachment_id' => $attachmentId,
                    '_wpnonce' => wp_create_nonce('helmetsan_media_delete_logo_attachment'),
                ],
                admin_url('admin-post.php')
            );
            echo '<p>';
            if (is_string($editUrl) && $editUrl !== '') {
                echo '<a class="button button-secondary" href="' . esc_url($editUrl) . '">Open Imported Media</a> ';
            }
            echo '<a class="button button-link-delete" href="' . esc_url($deleteUrl) . '">Delete Imported Media</a>';
            echo '</p>';
        }
        echo '<p><a class="button button-secondary" href="' . esc_url($engineUrl) . '">Find in Media Engine</a></p>';
    }

    public function renderEditorButton(): void
    {
        if (! current_user_can('upload_files')) {
            return;
        }

        $postId = 0;
        if (isset($_GET['post'])) {
            $postId = (int) $_GET['post'];
        }
        if ($postId <= 0) {
            global $post;
            if ($post instanceof WP_Post) {
                $postId = (int) $post->ID;
            }
        }

        $url = add_query_arg(
            [
                'page' => 'helmetsan-media-engine',
                'post_id' => $postId > 0 ? $postId : null,
            ],
            admin_url('upload.php')
        );
        echo '<a href="' . esc_url($url) . '" class="button">Helmetsan Logos</a>';
    }

    public function saveMetaBox(int $postId, WP_Post $post): void
    {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if (! current_user_can('edit_post', $postId)) {
            return;
        }
        $nonce = isset($_POST['helmetsan_logo_meta_nonce']) ? (string) $_POST['helmetsan_logo_meta_nonce'] : '';
        if (! wp_verify_nonce($nonce, 'helmetsan_logo_meta')) {
            return;
        }

        if (isset($_POST['helmetsan_logo_url'])) {
            update_post_meta($postId, '_helmetsan_logo_url', esc_url_raw((string) $_POST['helmetsan_logo_url']));
        }
        if (isset($_POST['helmetsan_logo_provider'])) {
            update_post_meta($postId, '_helmetsan_logo_provider', sanitize_text_field((string) $_POST['helmetsan_logo_provider']));
        }
        if (isset($_POST['helmetsan_logo_domain'])) {
            update_post_meta($postId, '_helmetsan_logo_domain', sanitize_text_field((string) $_POST['helmetsan_logo_domain']));
        }
        if (isset($_POST['helmetsan_logo_attachment_id'])) {
            $attachmentId = max(0, (int) $_POST['helmetsan_logo_attachment_id']);
            if ($attachmentId > 0) {
                update_post_meta($postId, '_helmetsan_logo_attachment_id', $attachmentId);
            } else {
                delete_post_meta($postId, '_helmetsan_logo_attachment_id');
            }
        }
    }

    public function handleApplyLogo(): void
    {
        if (! current_user_can('upload_files')) {
            wp_die('Unauthorized');
        }
        $nonce = isset($_POST['helmetsan_media_nonce']) ? (string) $_POST['helmetsan_media_nonce'] : '';
        if (! wp_verify_nonce($nonce, 'helmetsan_media_apply_logo')) {
            wp_die('Invalid nonce');
        }
        $postId = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;
        $url = isset($_POST['logo_url']) ? esc_url_raw((string) $_POST['logo_url']) : '';
        $provider = isset($_POST['provider']) ? sanitize_text_field((string) $_POST['provider']) : '';
        $sideload = isset($_POST['sideload']) && (int) $_POST['sideload'] === 1;
        $assign = isset($_POST['assign']) && (int) $_POST['assign'] === 1;
        $didImport = false;
        $didAssign = false;

        if ($url !== '') {
            if ($assign && $postId > 0 && ! current_user_can('edit_post', $postId)) {
                wp_die('Unauthorized post access');
            }

            $finalUrl = $url;
            $attachmentId = 0;
            if ($sideload) {
                $media = $this->sideloadToMediaLibrary($url, $postId > 0 ? $postId : 0, $provider);
                if (! empty($media['url'])) {
                    $finalUrl = (string) $media['url'];
                    $didImport = true;
                }
                if (! empty($media['attachment_id'])) {
                    $attachmentId = (int) $media['attachment_id'];
                }
            }

            if ($assign && $postId > 0) {
                update_post_meta($postId, '_helmetsan_logo_url', $finalUrl);
                update_post_meta($postId, '_helmetsan_logo_provider', $provider !== '' ? $provider : 'media-engine');
                if ($attachmentId > 0) {
                    update_post_meta($postId, '_helmetsan_logo_attachment_id', $attachmentId);
                }
                $didAssign = true;
            }
        }

        $return = isset($_POST['return']) ? esc_url_raw((string) $_POST['return']) : '';
        if ($return === '') {
            $return = add_query_arg(['page' => 'helmetsan-media-engine', 'saved' => $didAssign ? 1 : 0, 'imported' => $didImport ? 1 : 0, 'assigned' => $didAssign ? 1 : 0], admin_url('admin.php'));
        } else {
            $return = add_query_arg(['saved' => $didAssign ? 1 : 0, 'imported' => $didImport ? 1 : 0, 'assigned' => $didAssign ? 1 : 0], $return);
        }
        wp_safe_redirect($return);
        exit;
    }

    public function handleDeleteLogoAttachment(): void
    {
        if (! current_user_can('upload_files')) {
            wp_die('Unauthorized');
        }
        check_admin_referer('helmetsan_media_delete_logo_attachment');

        $postId = isset($_GET['post_id']) ? (int) $_GET['post_id'] : 0;
        $attachmentId = isset($_GET['attachment_id']) ? (int) $_GET['attachment_id'] : 0;
        if ($postId <= 0 || $attachmentId <= 0) {
            wp_die('Invalid request');
        }
        if (! current_user_can('edit_post', $postId) || ! current_user_can('delete_post', $attachmentId)) {
            wp_die('Unauthorized');
        }

        $boundId = (int) get_post_meta($postId, '_helmetsan_logo_attachment_id', true);
        if ($boundId !== $attachmentId) {
            wp_die('Attachment mismatch');
        }

        $attachmentUrl = (string) wp_get_attachment_url($attachmentId);
        wp_delete_attachment($attachmentId, true);

        delete_post_meta($postId, '_helmetsan_logo_attachment_id');
        if ($attachmentUrl !== '' && (string) get_post_meta($postId, '_helmetsan_logo_url', true) === $attachmentUrl) {
            delete_post_meta($postId, '_helmetsan_logo_url');
            delete_post_meta($postId, '_helmetsan_logo_provider');
        }

        $return = get_edit_post_link($postId, '');
        if (! is_string($return) || $return === '') {
            $return = add_query_arg(['page' => 'helmetsan-media-engine'], admin_url('admin.php'));
        }
        $return = add_query_arg(['deleted' => 1], $return);
        wp_safe_redirect($return);
        exit;
    }

    /**
     * @return array{url:string,attachment_id:int,error:string}
     */
    private function sideloadToMediaLibrary(string $url, int $postId, string $provider = ''): array
    {
        if ($url === '' || ! filter_var($url, FILTER_VALIDATE_URL)) {
            return ['url' => '', 'attachment_id' => 0, 'error' => 'invalid_url'];
        }

        $existing = $this->findExistingAttachmentBySourceUrl($url);
        if ($existing > 0) {
            $existingUrl = (string) wp_get_attachment_url($existing);
            if ($existingUrl !== '') {
                return ['url' => $existingUrl, 'attachment_id' => $existing, 'error' => ''];
            }
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $tmp = download_url($url, 10);
        if (is_wp_error($tmp)) {
            return ['url' => '', 'attachment_id' => 0, 'error' => 'download_error: ' . $tmp->get_error_message()];
        }

        $path = wp_parse_url($url, PHP_URL_PATH);
        $name = is_string($path) ? basename($path) : 'logo-image';
        if ($name === '' || $name === '/') {
            $name = 'logo-image';
        }
        $name = $this->ensureFilenameExtension($name, $url);

        $fileArray = [
            'name'     => sanitize_file_name($name),
            'tmp_name' => $tmp,
        ];
        $attachmentId = media_handle_sideload($fileArray, $postId);
        if (is_wp_error($attachmentId)) {
            @unlink($tmp);
            return ['url' => '', 'attachment_id' => 0, 'error' => 'sideload_error: ' . $attachmentId->get_error_message()];
        }

        update_post_meta((int) $attachmentId, '_helmetsan_source_url', esc_url_raw($url));
        if ($provider !== '') {
            update_post_meta((int) $attachmentId, '_helmetsan_source_provider', sanitize_text_field($provider));
        }

        $final = (string) wp_get_attachment_url((int) $attachmentId);
        return ['url' => $final, 'attachment_id' => (int) $attachmentId, 'error' => ''];
    }

    private function findExistingAttachmentBySourceUrl(string $url): int
    {
        global $wpdb;

        $metaTable = $wpdb->postmeta;
        $postsTable = $wpdb->posts;
        $sql = "SELECT p.ID
                FROM {$postsTable} p
                INNER JOIN {$metaTable} pm ON pm.post_id = p.ID
                WHERE p.post_type = 'attachment'
                  AND pm.meta_key = '_helmetsan_source_url'
                  AND pm.meta_value = %s
                ORDER BY p.ID DESC
                LIMIT 1";
        $id = (int) $wpdb->get_var($wpdb->prepare($sql, $url));
        return $id > 0 ? $id : 0;
    }

    private function ensureFilenameExtension(string $name, string $url): string
    {
        $ext = strtolower((string) pathinfo($name, PATHINFO_EXTENSION));
        if ($ext !== '') {
            return $name;
        }

        $resp = wp_remote_head($url, ['timeout' => 6]);
        $ctype = '';
        if (! is_wp_error($resp)) {
            $ctype = strtolower((string) wp_remote_retrieve_header($resp, 'content-type'));
        }

        $map = [
            'image/svg+xml' => 'svg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/jpeg' => 'jpg',
            'image/jpg' => 'jpg',
            'image/gif' => 'gif',
        ];
        $picked = 'png';
        foreach ($map as $type => $candidateExt) {
            if (str_contains($ctype, $type)) {
                $picked = $candidateExt;
                break;
            }
        }

        return $name . '.' . $picked;
    }

    /**
     * @return array<string,mixed>
     */
    public function backfillBrandLogos(int $limit = 0, bool $force = false, bool $dryRun = false): array
    {
        $queryArgs = [
            'post_type' => 'brand',
            'post_status' => ['publish', 'draft', 'pending', 'private'],
            'posts_per_page' => $limit > 0 ? $limit : -1,
            'orderby' => 'title',
            'order' => 'ASC',
            'fields' => 'ids',
        ];
        $q = new \WP_Query($queryArgs);
        $ids = is_array($q->posts) ? $q->posts : [];
        wp_reset_postdata();

        $processed = 0;
        $imported = 0;
        $updated = 0;
        $skipped = 0;
        $failed = 0;
        $details = [];

        foreach ($ids as $idRaw) {
            $brandId = (int) $idRaw;
            if ($brandId <= 0) {
                continue;
            }
            $processed++;

            $existingAttachmentId = (int) get_post_meta($brandId, '_helmetsan_logo_attachment_id', true);
            $existingUrl = (string) get_post_meta($brandId, '_helmetsan_logo_url', true);
            if (! $force && $existingAttachmentId > 0 && $existingUrl !== '') {
                $skipped++;
                $details[] = ['brand_id' => $brandId, 'status' => 'skipped', 'reason' => 'already_bound'];
                continue;
            }

            $sourceUrl = $existingUrl;
            if ($sourceUrl === '') {
                $supportUrl = (string) get_post_meta($brandId, 'brand_support_url', true);
                $domain = (string) wp_parse_url($supportUrl, PHP_URL_HOST);
                $domain = strtolower(trim($domain));
                $domain = preg_replace('#^www\.#', '', $domain) ?? $domain;
                if ($domain !== '') {
                    $cfg = $this->config->mediaConfig();
                    $token = isset($cfg['logodev_publishable_key']) ? (string) $cfg['logodev_publishable_key'] : '';
                    if ($token === '') {
                        $token = isset($cfg['logodev_token']) ? (string) $cfg['logodev_token'] : '';
                    }
                    $sourceUrl = 'https://img.logo.dev/' . rawurlencode($domain);
                    if ($token !== '') {
                        $sourceUrl = (string) add_query_arg(['token' => $token], $sourceUrl);
                    }
                }
            }

            if ($sourceUrl === '') {
                $failed++;
                $details[] = ['brand_id' => $brandId, 'status' => 'failed', 'reason' => 'no_source_url'];
                continue;
            }

            if ($dryRun) {
                $details[] = ['brand_id' => $brandId, 'status' => 'dry-run', 'source' => $sourceUrl];
                continue;
            }

            $result = $this->sideloadToMediaLibrary($sourceUrl, $brandId, 'brand-backfill');
            $attachmentId = (int) ($result['attachment_id'] ?? 0);
            $finalUrl = (string) ($result['url'] ?? '');
            if ($attachmentId <= 0 || $finalUrl === '') {
                $failed++;
                $details[] = [
                    'brand_id' => $brandId,
                    'status' => 'failed',
                    'reason' => 'import_failed',
                    'error' => (string) ($result['error'] ?? ''),
                    'source' => $sourceUrl,
                ];
                continue;
            }

            update_post_meta($brandId, '_helmetsan_logo_attachment_id', $attachmentId);
            update_post_meta($brandId, '_helmetsan_logo_url', $finalUrl);
            update_post_meta($brandId, '_helmetsan_logo_provider', 'media-library');
            $updated++;

            if ($existingAttachmentId !== $attachmentId) {
                $imported++;
            }

            $details[] = [
                'brand_id' => $brandId,
                'status' => 'ok',
                'attachment_id' => $attachmentId,
                'url' => $finalUrl,
            ];
        }

        return [
            'ok' => true,
            'processed' => $processed,
            'updated' => $updated,
            'imported' => $imported,
            'skipped' => $skipped,
            'failed' => $failed,
            'dry_run' => $dryRun,
            'force' => $force,
            'details' => $details,
        ];
    }

    /**
     * @return array<int,array{provider:string,label:string,url:string}>
     */
    public function resolveCandidates(string $name, string $domain = '', string $entity = 'brand', string $provider = 'all', ?array &$diagnostics = null): array
    {
        $diagnostics = [
            'attempted_domains' => [],
            'attempted_slugs' => [],
            'checks' => [],
        ];
        $cfg = $this->config->mediaConfig();
        $ttl = max(1, (int) ($cfg['cache_ttl_hours'] ?? 12)) * HOUR_IN_SECONDS;
        $cacheKey = 'helmetsan_media_' . md5(strtolower(trim($name . '|' . $domain . '|' . $entity . '|' . $provider)));
        $cached = get_transient($cacheKey);
        $cachedDiag = get_transient($cacheKey . '_diag');
        if (is_array($cached)) {
            if (is_array($cachedDiag)) {
                $diagnostics = $cachedDiag;
            }
            return $cached;
        }

        $out = [];
        $slug = sanitize_title($name);
        if (($provider === 'all' || $provider === 'simpleicons') && ! empty($cfg['simpleicons_enabled']) && $slug !== '') {
            $simple = $this->buildSimpleIconCandidates($name, $diagnostics);
            $out = array_merge($out, $simple);
        }

        $domains = $this->domainCandidates($domain, $name);
        $diagnostics['attempted_domains'] = $domains;

        if (($provider === 'all' || $provider === 'logodev') && ! empty($cfg['logodev_enabled']) && $domains !== []) {
            $token = isset($cfg['logodev_publishable_key']) ? (string) $cfg['logodev_publishable_key'] : '';
            if ($token === '') {
                $token = isset($cfg['logodev_token']) ? (string) $cfg['logodev_token'] : '';
            }
            foreach ($domains as $d) {
                $url = 'https://img.logo.dev/' . rawurlencode($d);
                if ($token !== '') {
                    $url = add_query_arg(['token' => $token], $url);
                }
                $probe = $this->probeUrlImage($url);
                $diagnostics['checks'][] = [
                    'provider' => 'logodev',
                    'target' => $d,
                    'status' => (string) ($probe['status'] ?? '-'),
                    'result' => ! empty($probe['ok']) ? 'ok' : 'fail',
                    'note' => isset($probe['note']) ? (string) $probe['note'] : '',
                ];
                if (empty($probe['ok'])) {
                    continue;
                }
                $out[] = [
                    'provider' => 'logodev',
                    'label' => 'Logo.dev (' . $d . ')',
                    'url' => $url,
                ];
                break;
            }
        }

        if (($provider === 'all' || $provider === 'brandfetch') && ! empty($cfg['brandfetch_enabled']) && $domains !== []) {
            foreach ($domains as $d) {
                $brandfetch = $this->fetchBrandfetch($d, (string) ($cfg['brandfetch_token'] ?? ''), $diagnostics);
                if ($brandfetch !== []) {
                    $out = array_merge($out, $brandfetch);
                    break;
                }
            }
        }

        if (($provider === 'all' || $provider === 'wikimedia') && ! empty($cfg['wikimedia_enabled']) && $name !== '') {
            $wiki = $this->fetchWikimedia($name, $diagnostics);
            $out = array_merge($out, $wiki);
        }

        $deduped = [];
        $seen = [];
        foreach ($out as $item) {
            $url = isset($item['url']) ? (string) $item['url'] : '';
            if ($url === '' || isset($seen[$url])) {
                continue;
            }
            $seen[$url] = true;
            $deduped[] = $item;
        }

        set_transient($cacheKey, $deduped, $ttl);
        set_transient($cacheKey . '_diag', $diagnostics, $ttl);
        return $deduped;
    }

    /**
     * @return array<int,string>
     */
    private function domainCandidates(string $domain, string $name): array
    {
        $items = [];
        $brandKey = $this->brandKey($name);
        $normDomain = strtolower(trim($domain));
        $normDomain = (string) preg_replace('#^https?://#', '', $normDomain);
        $normDomain = trim($normDomain, '/');
        if ($normDomain !== '') {
            $normDomain = preg_replace('#^www\.#', '', $normDomain) ?? $normDomain;
            $items[] = $normDomain;
        }
        foreach ($this->brandDomainOverrides($brandKey) as $candidate) {
            $items[] = strtolower(trim($candidate));
        }

        $base = $brandKey;
        if ($base !== '') {
            if ($base !== '') {
                $items[] = $base . '.com';
            }
        }

        $items = array_values(array_unique(array_filter($items, static fn ($d): bool => is_string($d) && $d !== '')));
        return array_slice($items, 0, 3);
    }

    /**
     * @return array<int,array{provider:string,label:string,url:string}>
     */
    private function buildSimpleIconCandidates(string $name, array &$diagnostics): array
    {
        $slugs = $this->simpleIconSlugCandidates($name);
        $diagnostics['attempted_slugs'] = $slugs;

        $out = [];
        foreach ($slugs as $slug) {
            $url = 'https://cdn.simpleicons.org/' . rawurlencode($slug);
            $probe = $this->probeUrlImage($url);
            $diagnostics['checks'][] = [
                'provider' => 'simpleicons',
                'target' => $slug,
                'status' => (string) ($probe['status'] ?? '-'),
                'result' => ! empty($probe['ok']) ? 'ok' : 'fail',
                'note' => isset($probe['note']) ? (string) $probe['note'] : '',
            ];
            if (empty($probe['ok'])) {
                continue;
            }
            $out[] = [
                'provider' => 'simpleicons',
                'label' => 'Simple Icons (' . $slug . ')',
                'url' => $url,
            ];
            break;
        }

        return $out;
    }

    /**
     * @return array<string,mixed>
     */
    private function buildSearchContext(string $name, string $domain): array
    {
        $brandKey = $this->brandKey($name);
        $domains = $this->domainCandidates($domain, $name);
        $slugs = $this->simpleIconSlugCandidates($name);
        $quick = [];

        if ($domains !== []) {
            $quick[] = [
                'label' => 'Logo.dev (' . $domains[0] . ')',
                'url' => 'https://img.logo.dev/' . rawurlencode($domains[0]),
            ];
        }
        if ($name !== '') {
            $quick[] = [
                'label' => 'Wikimedia Search',
                'url' => 'https://commons.wikimedia.org/w/index.php?search=' . rawurlencode($name . ' logo'),
            ];
        }
        $quick[] = [
            'label' => 'Brandfetch API Docs',
            'url' => 'https://docs.brandfetch.com/reference/brand-api',
        ];

        return [
            'brand_key' => $brandKey,
            'domains' => $domains,
            'slugs' => $slugs,
            'quick_links' => $quick,
        ];
    }

    private function brandKey(string $name): string
    {
        $base = strtolower(trim($name));
        $base = str_replace(['helmet', 'helmets'], '', $base);
        $base = preg_replace('/[^a-z0-9]+/i', '', $base) ?? $base;
        return trim($base);
    }

    /**
     * @return array<int,string>
     */
    private function brandDomainOverrides(string $brandKey): array
    {
        $map = [
            'ls2' => ['ls2helmets.com', 'ls2.com'],
            'shoei' => ['shoei.com'],
            'arai' => ['araiamericas.com', 'araihelmet.eu'],
            'agv' => ['agv.com'],
            'hjc' => ['hjchelmets.com'],
            'bell' => ['bellhelmets.com'],
            'shark' => ['shark-helmets.com'],
            'nolan' => ['nolan-helmets.com'],
            'schuberth' => ['schuberth.com'],
            'scorpionexo' => ['scorpionusa.com'],
            'icon' => ['rideicon.com'],
            'smk' => ['smkhelmets.com'],
            'studds' => ['studds.com'],
            'mthelmets' => ['mthelmets.com'],
            'caberg' => ['caberg.it'],
        ];

        return $map[$brandKey] ?? [];
    }

    /**
     * @return array<int,string>
     */
    private function simpleIconSlugCandidates(string $name): array
    {
        $slugs = [];
        $titleSlug = sanitize_title($name);
        if ($titleSlug !== '') {
            $slugs[] = $titleSlug;
            $slugs[] = str_replace('-', '', $titleSlug);
        }
        $raw = strtolower(trim($name));
        $raw = preg_replace('/[^a-z0-9]+/i', '', $raw) ?? $raw;
        if ($raw !== '') {
            $slugs[] = $raw;
        }

        return array_values(array_unique(array_filter($slugs)));
    }

    /**
     * @return array{ok:bool,status:string,note:string}
     */
    private function probeUrlImage(string $url): array
    {
        if ($url === '') {
            return ['ok' => false, 'status' => '-', 'note' => 'Empty URL'];
        }

        $resp = wp_remote_head($url, ['timeout' => 6]);
        if (is_wp_error($resp)) {
            return ['ok' => false, 'status' => 'error', 'note' => $resp->get_error_message()];
        }
        $code = (int) wp_remote_retrieve_response_code($resp);
        if ($code < 200 || $code > 299) {
            return ['ok' => false, 'status' => (string) $code, 'note' => 'Non-2xx'];
        }
        $ctype = (string) wp_remote_retrieve_header($resp, 'content-type');
        if ($ctype === '') {
            return ['ok' => true, 'status' => (string) $code, 'note' => 'No content-type header'];
        }
        $isImage = str_contains(strtolower($ctype), 'image') || str_contains(strtolower($ctype), 'svg');
        return [
            'ok' => $isImage,
            'status' => (string) $code,
            'note' => $isImage ? $ctype : ('Unexpected content-type: ' . $ctype),
        ];
    }

    /**
     * @return array<int,array{provider:string,label:string,url:string}>
     */
    private function fetchBrandfetch(string $domain, string $token, array &$diagnostics): array
    {
        $headers = ['Accept' => 'application/json'];
        if ($token !== '') {
            $headers['Authorization'] = 'Bearer ' . $token;
        }
        $resp = wp_remote_get('https://api.brandfetch.io/v2/brands/' . rawurlencode($domain), [
            'timeout' => 8,
            'headers' => $headers,
        ]);
        if (is_wp_error($resp)) {
            $diagnostics['checks'][] = [
                'provider' => 'brandfetch',
                'target' => $domain,
                'status' => 'error',
                'result' => 'fail',
                'note' => $resp->get_error_message(),
            ];
            return [];
        }
        $code = (int) wp_remote_retrieve_response_code($resp);
        $body = wp_remote_retrieve_body($resp);
        if ($code < 200 || $code > 299 || ! is_string($body) || $body === '') {
            $diagnostics['checks'][] = [
                'provider' => 'brandfetch',
                'target' => $domain,
                'status' => (string) $code,
                'result' => 'fail',
                'note' => 'Empty/invalid response',
            ];
            return [];
        }
        $decoded = json_decode($body, true);
        if (! is_array($decoded)) {
            $diagnostics['checks'][] = [
                'provider' => 'brandfetch',
                'target' => $domain,
                'status' => (string) $code,
                'result' => 'fail',
                'note' => 'Invalid JSON payload',
            ];
            return [];
        }
        $out = [];
        $logos = isset($decoded['logos']) && is_array($decoded['logos']) ? $decoded['logos'] : [];
        foreach ($logos as $logo) {
            if (! is_array($logo) || ! isset($logo['formats']) || ! is_array($logo['formats'])) {
                continue;
            }
            foreach ($logo['formats'] as $fmt) {
                if (! is_array($fmt)) {
                    continue;
                }
                $src = isset($fmt['src']) ? esc_url_raw((string) $fmt['src']) : '';
                $format = isset($fmt['format']) ? (string) $fmt['format'] : '';
                if ($src === '') {
                    continue;
                }
                $out[] = [
                    'provider' => 'brandfetch',
                    'label' => 'Brandfetch ' . strtoupper($format),
                    'url' => $src,
                ];
            }
        }
        $diagnostics['checks'][] = [
            'provider' => 'brandfetch',
            'target' => $domain,
            'status' => (string) $code,
            'result' => $out !== [] ? 'ok' : 'fail',
            'note' => $out !== [] ? ('Formats: ' . (string) count($out)) : 'No logos in payload',
        ];
        return $out;
    }

    /**
     * @return array<int,array{provider:string,label:string,url:string}>
     */
    private function fetchWikimedia(string $name, array &$diagnostics): array
    {
        $url = add_query_arg([
            'action' => 'query',
            'format' => 'json',
            'generator' => 'search',
            'gsrsearch' => $name . ' logo filetype:svg',
            'gsrlimit' => 5,
            'prop' => 'imageinfo',
            'iiprop' => 'url',
        ], 'https://commons.wikimedia.org/w/api.php');
        $resp = wp_remote_get($url, [
            'timeout' => 8,
            'headers' => ['Accept' => 'application/json'],
        ]);
        if (is_wp_error($resp)) {
            $diagnostics['checks'][] = [
                'provider' => 'wikimedia',
                'target' => $name,
                'status' => 'error',
                'result' => 'fail',
                'note' => $resp->get_error_message(),
            ];
            return [];
        }
        $code = (int) wp_remote_retrieve_response_code($resp);
        $body = wp_remote_retrieve_body($resp);
        if (! is_string($body) || $body === '') {
            $diagnostics['checks'][] = [
                'provider' => 'wikimedia',
                'target' => $name,
                'status' => (string) $code,
                'result' => 'fail',
                'note' => 'Empty response body',
            ];
            return [];
        }
        $decoded = json_decode($body, true);
        if (! is_array($decoded)) {
            $diagnostics['checks'][] = [
                'provider' => 'wikimedia',
                'target' => $name,
                'status' => (string) $code,
                'result' => 'fail',
                'note' => 'Invalid JSON payload',
            ];
            return [];
        }
        $pages = isset($decoded['query']['pages']) && is_array($decoded['query']['pages']) ? $decoded['query']['pages'] : [];
        $out = [];
        foreach ($pages as $page) {
            if (! is_array($page)) {
                continue;
            }
            $title = isset($page['title']) ? (string) $page['title'] : 'Wikimedia';
            $info = isset($page['imageinfo'][0]) && is_array($page['imageinfo'][0]) ? $page['imageinfo'][0] : [];
            $src = isset($info['url']) ? esc_url_raw((string) $info['url']) : '';
            if ($src === '') {
                continue;
            }
            $out[] = [
                'provider' => 'wikimedia',
                'label' => $title,
                'url' => $src,
            ];
        }
        $diagnostics['checks'][] = [
            'provider' => 'wikimedia',
            'target' => $name,
            'status' => (string) $code,
            'result' => $out !== [] ? 'ok' : 'fail',
            'note' => $out !== [] ? ('Files: ' . (string) count($out)) : 'No image results',
        ];
        return $out;
    }
}
