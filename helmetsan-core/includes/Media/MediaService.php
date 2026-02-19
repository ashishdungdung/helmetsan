<?php

declare(strict_types=1);

namespace Helmetsan\Core\Media;

use WP_Post;

final class MediaService
{
    public function register(): void
    {
        add_action('add_meta_boxes', [$this, 'registerMetaBoxes']);
        add_action('save_post', [$this, 'saveMetaBoxes'], 10, 2);
    }

    public function registerMetaBoxes(): void
    {
        add_meta_box(
            'helmetsan_brand_video',
            'Brand Spotlight Video',
            [$this, 'renderBrandVideoMetaBox'],
            'brand',
            'normal',
            'high'
        );

        add_meta_box(
            'helmetsan_helmet_videos',
            'Product Videos (JSON)',
            [$this, 'renderHelmetVideosMetaBox'],
            'helmet',
            'normal',
            'high'
        );
    }

    public function renderBrandVideoMetaBox(WP_Post $post): void
    {
        wp_nonce_field('helmetsan_media_meta', 'helmetsan_media_meta_nonce');
        $videoUrl = get_post_meta($post->ID, 'brand_video_url', true);
        
        echo '<p><label for="brand_video_url"><strong>YouTube URL</strong></label></p>';
        echo '<input type="url" id="brand_video_url" name="brand_video_url" value="' . esc_attr($videoUrl) . '" class="widefat" placeholder="https://www.youtube.com/watch?v=..." />';
        echo '<p class="description">Primary brand video to be featured on the Brand Hub.</p>';
        
        if ($videoUrl) {
            echo '<div style="margin-top: 10px;">';
            echo $this->getEmbedCode($videoUrl);
            echo '</div>';
        }
    }

    public function renderHelmetVideosMetaBox(WP_Post $post): void
    {
        wp_nonce_field('helmetsan_media_meta', 'helmetsan_media_meta_nonce');
        $json = get_post_meta($post->ID, 'helmet_videos_json', true);
        
        echo '<p><label for="helmet_videos_json"><strong>Video URLs (JSON Array)</strong></label></p>';
        echo '<textarea id="helmet_videos_json" name="helmet_videos_json" class="widefat" rows="5" placeholder=\'["https://youtube.com/...", "https://vimeo.com/..."]\'>' . esc_textarea($json) . '</textarea>';
        echo '<p class="description">Enter a valid JSON array of video URLs.</p>';
    }

    public function saveMetaBoxes(int $postId, WP_Post $post): void
    {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        $nonce = $_POST['helmetsan_media_meta_nonce'] ?? '';
        if (!wp_verify_nonce($nonce, 'helmetsan_media_meta')) {
            return;
        }

        if (isset($_POST['brand_video_url'])) {
            update_post_meta($postId, 'brand_video_url', esc_url_raw($_POST['brand_video_url']));
        }

        if (isset($_POST['helmet_videos_json'])) {
            // Validate JSON
            $json = wp_unslash($_POST['helmet_videos_json']);
            if (empty($json) || json_decode($json) !== null) {
                update_post_meta($postId, 'helmet_videos_json', $json);
            }
        }
    }

    public function getBrandVideo(int $brandId): ?string
    {
        $url = get_post_meta($brandId, 'brand_video_url', true);
        if ($url) {
            return $url;
        }
        // Fallback logic could go here (e.g. site-wide channel)
        return null;
    }

    public function getProductGallery(int $helmetId): array
    {
        $gallery = [];

        // 1. Featured Image
        $thumbId = get_post_thumbnail_id($helmetId);
        if ($thumbId) {
            $gallery[] = [
                'type' => 'image',
                'url' => wp_get_attachment_url($thumbId),
                'thumb' => get_the_post_thumbnail_url($helmetId, 'thumbnail'),
                'alt' => get_post_meta($thumbId, '_wp_attachment_image_alt', true),
            ];
        }

        // 2. Gallery Images (geo_media_json)
        $geoMediaJson = get_post_meta($helmetId, 'geo_media_json', true);
        if ($geoMediaJson) {
            $media = json_decode($geoMediaJson, true);
            if (is_array($media)) {
                foreach ($media as $imageUrl) {
                    if (!is_string($imageUrl) || $imageUrl === '') continue;
                    $gallery[] = [
                        'type' => 'image',
                        'url' => $imageUrl,
                        'thumb' => $imageUrl, // External placeholder, use as thumb
                        'alt' => get_the_title($helmetId),
                    ];
                }
            }
        }

        // 3. Videos
        $videoJson = get_post_meta($helmetId, 'helmet_videos_json', true);
        if ($videoJson) {
            $videos = json_decode($videoJson, true);
            if (is_array($videos)) {
                foreach ($videos as $videoUrl) {
                    $gallery[] = [
                        'type' => 'video',
                        'url' => $videoUrl,
                        'embed' => $this->getEmbedCode($videoUrl),
                        'thumb' => $this->getVideoThumbnail($videoUrl), // Placeholder
                    ];
                }
            }
        }

        return $gallery;
    }

    public function getEmbedCode(string $url): string
    {
        // Simple YouTube Parser
        if (strpos($url, 'youtube.com') !== false || strpos($url, 'youtu.be') !== false) {
            $videoId = $this->getYoutubeId($url);
            if ($videoId) {
                return '<iframe width="100%" height="315" src="https://www.youtube.com/embed/' . esc_attr($videoId) . '" title="YouTube video player" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>';
            }
        }
        return '';
    }

    private function getYoutubeId(string $url): ?string
    {
        $pattern = '%(?:youtube(?:-nocookie)?\.com/(?:[^/]+/.+/|(?:v|e(?:mbed)?)/|.*[?&]v=)|youtu\.be/)([^"&?/ ]{11})%i';
        if (preg_match($pattern, $url, $match)) {
            return $match[1];
        }
        return null;
    }

    private function getVideoThumbnail(string $url): string
    {
        $videoId = $this->getYoutubeId($url);
        if ($videoId) {
            return "https://img.youtube.com/vi/{$videoId}/0.jpg";
        }
        return ''; // Default placeholder?
    }
}
