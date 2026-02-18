<?php

declare(strict_types=1);

namespace Helmetsan\Core\Data;

use WP_Query;

class SyncManager
{
    private string $dataDir;

    public function __construct()
    {
        $this->dataDir = HELMETSAN_CORE_DIR . 'data/';
    }

    /**
     * Export all Brands to a JSON file.
     * 
     * @return string Path to the exported file.
     */
    public function exportBrands(): string
    {
        $args = [
            'post_type'      => 'brand',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'orderby'        => 'title',
            'order'          => 'ASC',
        ];

        $query = new WP_Query($args);
        $data = [];

        if ($query->have_posts()) {
            foreach ($query->posts as $post) {
                $full_data = (new \Helmetsan\Core\Brands\BrandService())->exportPayloadByPostId($post->ID);
                if ($full_data['ok']) {
                    $payload = $full_data['payload'];
                    $data[] = [
                        'id'       => $post->ID,
                        'name'     => $post->post_title,
                        'slug'     => $post->post_name,
                        'url'      => get_permalink($post->ID),
                        'logo_url' => get_the_post_thumbnail_url($post->ID, 'full') ?: null,
                        'profile'  => $payload['profile']
                    ];
                }
            }
        }

        return $this->saveJson('brands.json', $data);
    }

    private function saveJson(string $filename, array $data): string
    {
        if (!file_exists($this->dataDir)) {
            mkdir($this->dataDir, 0755, true);
        }

        $path = $this->dataDir . $filename;
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        if (file_put_contents($path, $json) === false) {
            throw new \RuntimeException("Failed to write to $path");
        }

        return $path;
    }
}
