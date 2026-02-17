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
        add_action('add_meta_boxes', [$this, 'registerMetaBoxes']);
        add_action('save_post', [$this, 'saveMetaBox'], 10, 2);
        add_action('admin_post_helmetsan_media_apply_logo', [$this, 'handleApplyLogo']);
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
    }

    public function mediaPage(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        $name   = isset($_GET['name']) ? sanitize_text_field((string) $_GET['name']) : '';
        $domain = isset($_GET['domain']) ? sanitize_text_field((string) $_GET['domain']) : '';
        $type   = isset($_GET['entity']) ? sanitize_key((string) $_GET['entity']) : 'brand';
        $postId = isset($_GET['post_id']) ? (int) $_GET['post_id'] : 0;
        $saved  = isset($_GET['saved']) ? (int) $_GET['saved'] : 0;

        $candidates = [];
        if ($name !== '' || $domain !== '') {
            $candidates = $this->resolveCandidates($name, $domain, $type);
        }

        echo '<div class="wrap helmetsan-wrap">';
        echo '<h1>Media Engine</h1>';
        if ($saved === 1) {
            echo '<div class="notice notice-success is-dismissible"><p>Logo saved successfully.</p></div>';
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
        echo '<label for="hs-logo-post">Post ID (optional)</label>';
        echo '<input id="hs-logo-post" type="number" min="1" name="post_id" value="' . esc_attr($postId > 0 ? (string) $postId : '') . '" />';
        submit_button('Find Logos', 'secondary', '', false);
        echo '</form>';

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
                if ($postId > 0) {
                    echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
                    wp_nonce_field('helmetsan_media_apply_logo', 'helmetsan_media_nonce');
                    echo '<input type="hidden" name="action" value="helmetsan_media_apply_logo" />';
                    echo '<input type="hidden" name="post_id" value="' . esc_attr((string) $postId) . '" />';
                    echo '<input type="hidden" name="logo_url" value="' . esc_attr($url) . '" />';
                    echo '<input type="hidden" name="provider" value="' . esc_attr($provider) . '" />';
                    echo '<input type="hidden" name="return" value="' . esc_attr((string) wp_get_referer()) . '" />';
                    submit_button('Use for Post #' . $postId, 'small', '', false);
                    echo '</form>';
                }
                echo '</article>';
            }
            echo '</div>';
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
        echo '<p><a class="button button-secondary" href="' . esc_url($engineUrl) . '">Find in Media Engine</a></p>';
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
    }

    public function handleApplyLogo(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        $nonce = isset($_POST['helmetsan_media_nonce']) ? (string) $_POST['helmetsan_media_nonce'] : '';
        if (! wp_verify_nonce($nonce, 'helmetsan_media_apply_logo')) {
            wp_die('Invalid nonce');
        }
        $postId = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;
        $url = isset($_POST['logo_url']) ? esc_url_raw((string) $_POST['logo_url']) : '';
        $provider = isset($_POST['provider']) ? sanitize_text_field((string) $_POST['provider']) : '';
        if ($postId > 0 && $url !== '') {
            update_post_meta($postId, '_helmetsan_logo_url', $url);
            update_post_meta($postId, '_helmetsan_logo_provider', $provider);
        }

        $return = isset($_POST['return']) ? esc_url_raw((string) $_POST['return']) : '';
        if ($return === '') {
            $return = add_query_arg(['page' => 'helmetsan-media-engine', 'saved' => 1], admin_url('admin.php'));
        } else {
            $return = add_query_arg(['saved' => 1], $return);
        }
        wp_safe_redirect($return);
        exit;
    }

    /**
     * @return array<int,array{provider:string,label:string,url:string}>
     */
    public function resolveCandidates(string $name, string $domain = '', string $entity = 'brand'): array
    {
        $cfg = $this->config->mediaConfig();
        $ttl = max(1, (int) ($cfg['cache_ttl_hours'] ?? 12)) * HOUR_IN_SECONDS;
        $cacheKey = 'helmetsan_media_' . md5(strtolower(trim($name . '|' . $domain . '|' . $entity)));
        $cached = get_transient($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }

        $out = [];
        $slug = sanitize_title($name);
        if (! empty($cfg['simpleicons_enabled']) && $slug !== '') {
            $out[] = [
                'provider' => 'simpleicons',
                'label' => 'Simple Icons',
                'url' => 'https://cdn.simpleicons.org/' . rawurlencode($slug),
            ];
        }

        $normDomain = strtolower(trim($domain));
        $normDomain = preg_replace('#^https?://#', '', $normDomain ?? '');
        $normDomain = trim((string) $normDomain, '/');

        if (! empty($cfg['logodev_enabled']) && $normDomain !== '') {
            $url = 'https://img.logo.dev/' . rawurlencode($normDomain);
            $token = isset($cfg['logodev_token']) ? (string) $cfg['logodev_token'] : '';
            if ($token !== '') {
                $url = add_query_arg(['token' => $token], $url);
            }
            $out[] = [
                'provider' => 'logodev',
                'label' => 'Logo.dev',
                'url' => $url,
            ];
        }

        if (! empty($cfg['brandfetch_enabled']) && $normDomain !== '') {
            $brandfetch = $this->fetchBrandfetch($normDomain, (string) ($cfg['brandfetch_token'] ?? ''));
            $out = array_merge($out, $brandfetch);
        }

        if (! empty($cfg['wikimedia_enabled']) && $name !== '') {
            $wiki = $this->fetchWikimedia($name);
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
        return $deduped;
    }

    /**
     * @return array<int,array{provider:string,label:string,url:string}>
     */
    private function fetchBrandfetch(string $domain, string $token): array
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
            return [];
        }
        $code = (int) wp_remote_retrieve_response_code($resp);
        $body = wp_remote_retrieve_body($resp);
        if ($code < 200 || $code > 299 || ! is_string($body) || $body === '') {
            return [];
        }
        $decoded = json_decode($body, true);
        if (! is_array($decoded)) {
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
        return $out;
    }

    /**
     * @return array<int,array{provider:string,label:string,url:string}>
     */
    private function fetchWikimedia(string $name): array
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
            return [];
        }
        $body = wp_remote_retrieve_body($resp);
        if (! is_string($body) || $body === '') {
            return [];
        }
        $decoded = json_decode($body, true);
        if (! is_array($decoded)) {
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
        return $out;
    }
}
