<?php

declare(strict_types=1);

namespace Helmetsan\Core\Seo;

/**
 * Instant Search Engine Indexing Engine via IndexNow Protocol.
 * Pushes updated helmet specifications, reviews, and prices directly to Bing, Copilot, and Yandex.
 */
final class IndexNowService
{
    public const API_KEY = 'c9a72e8140db4e5fb3d6812975ef83a0';
    public const HOST = 'helmetsan.com';

    public function register(): void
    {
        add_action('save_post_helmet', [$this, 'onPostSave'], 20, 2);
        add_action('save_post_post', [$this, 'onPostSave'], 20, 2);
        add_action('save_post_accessory', [$this, 'onPostSave'], 20, 2);
    }

    public function onPostSave(int $postId, \WP_Post $post): void
    {
        if (wp_is_post_revision($postId) || wp_is_post_autosave($postId) || $post->post_status !== 'publish') {
            return;
        }

        $url = get_permalink($postId);
        if (is_string($url) && $url !== '') {
            $this->submitUrl($url);
        }
    }

    public function submitUrl(string $url): bool
    {
        $payload = [
            'host'        => self::HOST,
            'key'         => self::API_KEY,
            'keyLocation' => 'https://' . self::HOST . '/' . self::API_KEY . '.txt',
            'urlList'     => [$url],
        ];

        $response = wp_remote_post('https://api.indexnow.org/indexnow', [
            'headers'     => ['Content-Type' => 'application/json; charset=utf-8'],
            'body'        => wp_json_encode($payload),
            'timeout'     => 5,
            'blocking'    => false, // Asynchronous execution
            'httpversion' => '2.0',
        ]);

        return !is_wp_error($response);
    }

    /**
     * @param array<int,string> $urls
     */
    public function submitBatch(array $urls): bool
    {
        if ($urls === []) {
            return false;
        }

        $payload = [
            'host'        => self::HOST,
            'key'         => self::API_KEY,
            'keyLocation' => 'https://' . self::HOST . '/' . self::API_KEY . '.txt',
            'urlList'     => array_values(array_slice($urls, 0, 10000)),
        ];

        $response = wp_remote_post('https://api.indexnow.org/indexnow', [
            'headers'     => ['Content-Type' => 'application/json; charset=utf-8'],
            'body'        => wp_json_encode($payload),
            'timeout'     => 10,
            'blocking'    => true,
            'httpversion' => '2.0',
        ]);

        return !is_wp_error($response);
    }

    /**
     * Submit catalog URLs to IndexNow in batches of up to 10,000 URLs.
     *
     * @param array<string> $postTypes
     * @return array{ok:bool, total_urls:int, batches:int, success_count:int, failed_count:int, sample_urls:array<string>, errors:array<string>}
     */
    public function submitCatalog(array $postTypes = ['helmet', 'accessory', 'motorcycle', 'post'], int $limit = 10000, bool $dryRun = false): array
    {
        $query = new \WP_Query([
            'post_type'      => $postTypes,
            'post_status'    => 'publish',
            'posts_per_page' => max(1, min($limit, 50000)),
            'fields'         => 'ids',
            'no_found_rows'  => true,
        ]);

        $urls = [];
        foreach ($query->posts as $postId) {
            $permalink = get_permalink((int) $postId);
            if (is_string($permalink) && $permalink !== '') {
                $urls[] = $permalink;
            }
        }
        wp_reset_postdata();

        $urls = array_values(array_unique($urls));
        $total = count($urls);

        if ($total === 0) {
            return [
                'ok'            => true,
                'total_urls'    => 0,
                'batches'       => 0,
                'success_count' => 0,
                'failed_count'  => 0,
                'sample_urls'   => [],
                'errors'        => [],
            ];
        }

        if ($dryRun) {
            return [
                'ok'            => true,
                'total_urls'    => $total,
                'batches'       => (int) ceil($total / 10000),
                'success_count' => $total,
                'failed_count'  => 0,
                'sample_urls'   => array_slice($urls, 0, 5),
                'errors'        => [],
            ];
        }

        $batches = array_chunk($urls, 10000);
        $success = 0;
        $failed = 0;
        $errors = [];

        foreach ($batches as $chunk) {
            $payload = [
                'host'        => self::HOST,
                'key'         => self::API_KEY,
                'keyLocation' => 'https://' . self::HOST . '/' . self::API_KEY . '.txt',
                'urlList'     => $chunk,
            ];

            $response = wp_remote_post('https://api.indexnow.org/indexnow', [
                'headers'     => ['Content-Type' => 'application/json; charset=utf-8'],
                'body'        => wp_json_encode($payload),
                'timeout'     => 15,
                'blocking'    => true,
                'httpversion' => '2.0',
            ]);

            if (is_wp_error($response)) {
                $failed += count($chunk);
                $errors[] = $response->get_error_message();
            } else {
                $code = (int) wp_remote_retrieve_response_code($response);
                // IndexNow returns 200 (OK), 202 (Accepted)
                if ($code === 200 || $code === 202) {
                    $success += count($chunk);
                } else {
                    $failed += count($chunk);
                    $errors[] = "HTTP {$code}: " . wp_remote_retrieve_body($response);
                }
            }
        }

        return [
            'ok'            => $failed === 0,
            'total_urls'    => $total,
            'batches'       => count($batches),
            'success_count' => $success,
            'failed_count'  => $failed,
            'sample_urls'   => array_slice($urls, 0, 5),
            'errors'        => $errors,
        ];
    }
}
