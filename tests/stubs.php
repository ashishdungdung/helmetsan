<?php

declare(strict_types=1);

// Stubs so we can load FillMissingService without WordPress or full AI provider chain.
namespace {
    if (! defined('ABSPATH')) {
        define('ABSPATH', dirname(__DIR__) . '/');
    }
    if (! defined('ARRAY_A')) {
        define('ARRAY_A', 'ARRAY_A');
    }
    if (! defined('ARRAY_N')) {
        define('ARRAY_N', 'ARRAY_N');
    }
    if (! defined('OBJECT')) {
        define('OBJECT', 'OBJECT');
    }
    if (! function_exists('wp_get_post_terms')) {
        function wp_get_post_terms($postId, $taxonomy, $args = []): array {
            return $GLOBALS['wp_post_terms'][$postId][$taxonomy] ?? [];
        }
    }
    if (! function_exists('get_page_by_path')) {
        function get_page_by_path($page_path, $output = OBJECT, $post_type = 'page') {
            return null;
        }
    }
    if (! function_exists('wp_get_attachment_url')) {
        function wp_get_attachment_url(int $attachmentId): string {
            return 'https://helmetsan.com/wp-content/uploads/' . $attachmentId . '.jpg';
        }
    }
    if (! function_exists('get_the_post_thumbnail_url')) {
        function get_the_post_thumbnail_url($post = null, $size = 'post-thumbnail'): string {
            $id = $post instanceof WP_Post ? $post->ID : (int) $post;
            return 'https://helmetsan.com/wp-content/uploads/thumb-' . $id . '.jpg';
        }
    }
    if (! function_exists('esc_html')) {
        function esc_html(mixed $text): string {
            return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
        }
    }
    if (! function_exists('esc_attr')) {
        function esc_attr(mixed $text): string {
            return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
        }
    }
    if (! function_exists('is_admin')) {
        function is_admin(): bool { return false; }
    }
    if (! function_exists('add_filter')) {
        function add_filter($tag, $callback, $priority = 10, $accepted_args = 1) { return true; }
    }
    if (! function_exists('apply_filters')) {
        function apply_filters($tag, $value, ...$args) { return $value; }
    }
    if (! function_exists('add_action')) {
        function add_action($tag, $callback, $priority = 10, $accepted_args = 1) { return true; }
    }
    if (! function_exists('__')) {
        function __(string $text, string $domain = 'default'): string { return $text; }
    }
    if (! function_exists('esc_html__')) {
        function esc_html__(string $text, string $domain = 'default'): string { return $text; }
    }
    if (! function_exists('get_permalink')) {
        function get_permalink($post = 0, $leavename = false) {
            $id = $post instanceof WP_Post ? $post->ID : (int) $post;
            return 'https://helmetsan.com/?p=' . $id;
        }
    }
    if (! function_exists('get_post_type_archive_link')) {
        function get_post_type_archive_link(string $postType): string {
            return 'https://helmetsan.com/' . $postType . 's/';
        }
    }
    if (! function_exists('remove_filter')) {
        function remove_filter($tag, $callback, $priority = 10) { return true; }
    }
    if (! function_exists('get_post_status')) {
        function get_post_status($post = null) { return 'publish'; }
    }
    if (! function_exists('pll_current_language')) {
        function pll_current_language(): string { return $GLOBALS['wp_pll_current_lang'] ?? 'en'; }
    }
    if (! function_exists('pll_home_url')) {
        function pll_home_url(string $slug = ''): string {
            return 'https://helmetsan.com/' . ($slug ? $slug . '/' : '');
        }
    }
    if (! function_exists('pll_get_post')) {
        function pll_get_post($postId, $slug = '') {
            return $GLOBALS['wp_pll_translations'][$postId][$slug] ?? $postId;
        }
    }
    if (! class_exists('WP_Post', false)) {
        class WP_Post
        {
            public int $ID = 0;
            public string $post_type = 'post';
            public string $post_name = '';
            public string $post_status = 'publish';
            public string $post_title = '';
            public int $post_parent = 0;
        }
    }
    if (! function_exists('get_post')) {
        function get_post($post = null) {
            if ($post instanceof WP_Post) return $post;
            $id = (int) $post;
            return $GLOBALS['wp_mock_posts'][$id] ?? null;
        }
    }
    if (! function_exists('get_option')) {
        function get_option($option, $default = false) {
            return $GLOBALS['wp_options'][$option] ?? $default;
        }
    }
    if (! function_exists('update_option')) {
        function update_option($option, $value, $autoload = null): bool {
            $GLOBALS['wp_options'][$option] = $value;
            return true;
        }
    }
    if (! function_exists('wp_using_ext_object_cache')) {
        function wp_using_ext_object_cache(): bool {
            return $GLOBALS['wp_using_ext_cache'] ?? true;
        }
    }
    if (! function_exists('wp_cache_get')) {
        function wp_cache_get($key, $group = '', $force = false, &$found = null) {
            $found = isset($GLOBALS['wp_object_cache'][$group][$key]);
            return $GLOBALS['wp_object_cache'][$group][$key] ?? false;
        }
    }
    if (! function_exists('wp_cache_set')) {
        function wp_cache_set($key, $data, $group = '', $expire = 0): bool {
            $GLOBALS['wp_object_cache'][$group][$key] = $data;
            return true;
        }
    }
    if (! function_exists('wp_cache_delete')) {
        function wp_cache_delete($key, $group = ''): bool {
            unset($GLOBALS['wp_object_cache'][$group][$key]);
            return true;
        }
    }
    if (! function_exists('get_transient')) {
        function get_transient($transient) {
            return $GLOBALS['wp_transients'][$transient] ?? false;
        }
    }
    if (! function_exists('set_transient')) {
        function set_transient($transient, $value, $expiration = 0): bool {
            $GLOBALS['wp_transients'][$transient] = $value;
            return true;
        }
    }
    if (! function_exists('delete_transient')) {
        function delete_transient($transient): bool {
            unset($GLOBALS['wp_transients'][$transient]);
            return true;
        }
    }
    if (! function_exists('current_time')) {
        function current_time(string $type, $gmt = 0): string {
            return date('Y-m-d H:i:s');
        }
    }
    if (! function_exists('sanitize_title')) {
        function sanitize_title(string $title, string $fallback = '', string $context = 'save'): string {
            $raw = strtolower(trim(preg_replace('/[^a-zA-Z0-9\-]+/', '-', $title), '-'));
            return $raw !== '' ? $raw : $fallback;
        }
    }
    if (! class_exists('WP_Error', false)) {
        class WP_Error
        {
            public function __construct(private string $code = '', private string $message = '', private mixed $data = null) {}
            public function get_error_message(): string { return $this->message; }
            public function get_error_code(): string { return $this->code; }
        }
    }
    if (! function_exists('is_wp_error')) {
        function is_wp_error($thing): bool {
            return $thing instanceof WP_Error;
        }
    }
    if (! function_exists('home_url')) {
        function home_url($path = '', $scheme = null): string {
            return 'https://helmetsan.com' . ($path ? '/' . ltrim($path, '/') : '');
        }
    }
    if (! function_exists('wp_json_encode')) {
        function wp_json_encode($data, $options = 0, $depth = 512) {
            return json_encode($data, $options, $depth);
        }
    }
    if (! function_exists('wp_parse_args')) {
        function wp_parse_args($args, $defaults = []) {
            if (is_object($args)) {
                $r = get_object_vars($args);
            } elseif (is_array($args)) {
                $r = $args;
            } else {
                parse_str((string) $args, $r);
            }
            if (is_array($defaults)) {
                return array_merge($defaults, $r);
            }
            return $r;
        }
    }
    if (! function_exists('sanitize_key')) {
        function sanitize_key(string $key): string {
            return preg_replace('/[^a-z0-9_-]/', '', strtolower($key));
        }
    }
    if (! class_exists('WP_REST_Controller', false)) {
        class WP_REST_Controller {}
    }
    if (! class_exists('WP_REST_Request', false)) {
        class WP_REST_Request implements \ArrayAccess {
            public function __construct(private array $params = []) {}
            public function offsetExists(mixed $offset): bool { return isset($this->params[$offset]); }
            public function offsetGet(mixed $offset): mixed { return $this->params[$offset] ?? null; }
            public function offsetSet(mixed $offset, mixed $value): void { $this->params[$offset] = $value; }
            public function offsetUnset(mixed $offset): void { unset($this->params[$offset]); }
        }
    }
    if (! class_exists('WP_REST_Response', false)) {
        class WP_REST_Response {
            private array $headers = [];
            public function __construct(private mixed $data = null, private int $status = 200, array $headers = []) {
                $this->headers = $headers;
            }
            public function header(string $key, string $value): void { $this->headers[$key] = $value; }
            public function get_headers(): array { return $this->headers; }
            public function get_data(): mixed { return $this->data; }
            public function get_status(): int { return $this->status; }
        }
    }
    if (! class_exists('WP_REST_Server', false)) {
        class WP_REST_Server {
            public const READABLE = 'GET';
        }
    }
    if (! function_exists('helmetsan_core')) {
        function helmetsan_core() {
            return $GLOBALS['mock_helmetsan_core'] ?? null;
        }
    }
    if (! function_exists('add_query_arg')) {
        function add_query_arg(...$args): string {
            if (is_array($args[0])) {
                $newParams = $args[0];
                $url = $args[1] ?? '';
            } else {
                $newParams = [$args[0] => $args[1]];
                $url = $args[2] ?? '';
            }
            $parts = parse_url($url);
            $query = [];
            if (!empty($parts['query'])) {
                parse_str($parts['query'], $query);
            }
            foreach ($newParams as $k => $v) {
                $query[$k] = $v;
            }
            $GLOBALS['last_redirect_query'] = $query;
            $scheme = isset($parts['scheme']) ? $parts['scheme'] . '://' : '';
            $host = $parts['host'] ?? '';
            $path = $parts['path'] ?? '';
            $base = $scheme . $host . $path;
            $qs = http_build_query($query);
            return $qs !== '' ? $base . '?' . $qs : $base;
        }
    }
    if (! function_exists('esc_url_raw')) {
        function esc_url_raw(string $url): string {
            return filter_var($url, FILTER_SANITIZE_URL) ?: $url;
        }
    }
    if (! defined('MINUTE_IN_SECONDS')) {
        define('MINUTE_IN_SECONDS', 60);
    }
    if (! defined('HOUR_IN_SECONDS')) {
        define('HOUR_IN_SECONDS', 3600);
    }
    if (! defined('DAY_IN_SECONDS')) {
        define('DAY_IN_SECONDS', 86400);
    }
    if (! defined('WEEK_IN_SECONDS')) {
        define('WEEK_IN_SECONDS', 604800);
    }
    if (! defined('MONTH_IN_SECONDS')) {
        define('MONTH_IN_SECONDS', 2592000);
    }
    if (! defined('YEAR_IN_SECONDS')) {
        define('YEAR_IN_SECONDS', 31536000);
    }
    if (! function_exists('wp_parse_url')) {
        function wp_parse_url(string $url, int $component = -1) {
            return parse_url($url, $component);
        }
    }
    if (! function_exists('get_post_meta')) {
        function get_post_meta(int $postId, string $key = '', bool $single = false) {
            return $GLOBALS['wp_post_meta'][$postId][$key] ?? ($single ? '' : []);
        }
    }
    if (! function_exists('get_post_field')) {
        function get_post_field(string $field, $post) {
            return $GLOBALS['wp_post_fields'][$post][$field] ?? '';
        }
    }
    if (! function_exists('get_the_terms')) {
        function get_the_terms($post, $taxonomy) {
            return $GLOBALS['wp_post_terms'][$post][$taxonomy] ?? false;
        }
    }
    if (! function_exists('get_term_meta')) {
        function get_term_meta(int $termId, string $key = '', bool $single = false) {
            return $GLOBALS['wp_term_meta'][$termId][$key] ?? ($single ? '' : []);
        }
    }
    if (! function_exists('pll_languages_list')) {
        function pll_languages_list(): array {
            return $GLOBALS['wp_pll_languages'] ?? ['en', 'de', 'zh'];
        }
    }
    if (! function_exists('pll_default_language')) {
        function pll_default_language(): string {
            return $GLOBALS['wp_pll_default_lang'] ?? 'en';
        }
    }
    if (! class_exists('WP_Query', false)) {
        class WP_Query
        {
            public array $posts = [];
            public int $found_posts = 0;
            public array $query_vars = [];

            public function __construct(array $args = []) {
                $this->query_vars = $args;
                $raw = $GLOBALS['wp_mock_query_posts'] ?? [];
                if (! empty($args['post__not_in']) && is_array($args['post__not_in'])) {
                    $exclude = array_flip($args['post__not_in']);
                    $raw = array_values(array_filter($raw, function ($p) use ($exclude) {
                        $id = $p instanceof \WP_Post ? $p->ID : (int) $p;
                        return ! isset($exclude[$id]);
                    }));
                }
                if (($args['fields'] ?? '') === 'ids') {
                    $this->posts = array_map(static fn($p) => $p instanceof \WP_Post ? (int) $p->ID : (int) $p, $raw);
                } else {
                    $this->posts = $raw;
                }
                $this->found_posts = count($this->posts);
            }

            public function have_posts(): bool {
                return ! empty($this->posts);
            }

            public function is_main_query(): bool {
                return true;
            }

            public function get(string $var, $default = '') {
                return $this->query_vars[$var] ?? $default;
            }
        }
    }
    if (! function_exists('wp_reset_postdata')) {
        function wp_reset_postdata(): void {}
    }
    if (! function_exists('update_post_meta')) {
        function update_post_meta(int $postId, string $key, $value): bool {
            $GLOBALS['wp_post_meta'][$postId][$key] = $value;
            return true;
        }
    }
    if (! function_exists('get_the_title')) {
        function get_the_title($post = 0): string {
            if ($post instanceof WP_Post) return $post->post_title;
            $id = (int) $post;
            return $GLOBALS['wp_mock_posts'][$id]->post_title ?? ('Post ' . $id);
        }
    }
    if (! function_exists('esc_url')) {
        function esc_url(string $url): string {
            return htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
        }
    }
    if (! function_exists('esc_xml')) {
        function esc_xml(string $text): string {
            return htmlspecialchars($text, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        }
    }
    if (! function_exists('get_query_var')) {
        function get_query_var(string $var, $default = '') {
            return $GLOBALS['wp_query_vars'][$var] ?? $default;
        }
    }
    if (! function_exists('status_header')) {
        function status_header(int $code): void {
            $GLOBALS['wp_status_header'] = $code;
        }
    }
    if (! function_exists('add_rewrite_rule')) {
        function add_rewrite_rule(string $regex, string $query, string $after = 'bottom'): void {
            $GLOBALS['wp_rewrite_rules'][] = [$regex, $query, $after];
        }
    }
    if (! function_exists('get_post_thumbnail_id')) {
        function get_post_thumbnail_id($post = null): int {
            $id = $post instanceof WP_Post ? $post->ID : (int) $post;
            return (int) ($GLOBALS['wp_post_thumbnail_id'][$id] ?? 0);
        }
    }
    if (! function_exists('wp_get_attachment_image_src')) {
        function wp_get_attachment_image_src(int $attachmentId, $size = 'thumbnail') {
            return $GLOBALS['wp_attachment_image_src'][$attachmentId] ?? ['https://helmetsan.com/img/' . $attachmentId . '.jpg', 800, 600, false];
        }
    }
    if (! function_exists('admin_url')) {
        function admin_url(string $path = '', string $scheme = 'admin'): string {
            return 'https://helmetsan.com/wp-admin/' . ltrim($path, '/');
        }
    }
    if (! function_exists('get_edit_post_link')) {
        function get_edit_post_link($post = 0, string $context = 'display'): ?string {
            $id = $post instanceof WP_Post ? $post->ID : (int) $post;
            return 'https://helmetsan.com/wp-admin/post.php?post=' . $id . '&action=edit';
        }
    }
}

namespace Helmetsan\Core\AI {
    if (! class_exists('Helmetsan\Core\AI\AiService', false)) {
        class AiService
        {
        }
    }
}
