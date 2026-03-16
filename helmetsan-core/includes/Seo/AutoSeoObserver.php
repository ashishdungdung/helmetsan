<?php

declare(strict_types=1);

namespace Helmetsan\Core\Seo;

use Helmetsan\Core\Support\Config;

/**
 * Observes post saves and triggers asynchronous SEO generation via WP-Cron
 * if the setting is enabled and no meta description exists.
 */
final class AutoSeoObserver
{
    private const ALLOWED_POST_TYPES = ['helmet', 'brand', 'accessory'];

    public function __construct(
        private readonly YoastSeoSeeder $seeder
    ) {
    }

    public function init(): void
    {
        add_action('save_post', [$this, 'onSavePost'], 20, 3);
        add_action('helmetsan_generate_seo_for_post', [$this, 'generateSeoForPost']);
    }

    public function onSavePost(int $postId, \WP_Post $post, bool $update): void
    {
        if (wp_is_post_revision($postId)) {
            return;
        }

        if (! in_array($post->post_type, self::ALLOWED_POST_TYPES, true)) {
            return;
        }

        // Check if auto SEO is enabled
        $options = get_option(Config::OPTION_AI, []);
        $enabled = (bool) ($options['phase1_seo_enabled'] ?? false);
        if (! $enabled) {
            return;
        }

        // Check if Yoast meta description is already set
        $existingMetaDesc = get_post_meta($postId, '_yoast_wpseo_metadesc', true);
        if ($existingMetaDesc !== '') {
            return;
        }

        // Schedule async event to generate SEO
        if (! wp_next_scheduled('helmetsan_generate_seo_for_post', [$postId])) {
            wp_schedule_single_event(time(), 'helmetsan_generate_seo_for_post', [$postId]);
        }
    }

    public function generateSeoForPost(int $postId): void
    {
        // Re-check conditions inside worker
        $existingMetaDesc = get_post_meta($postId, '_yoast_wpseo_metadesc', true);
        if ($existingMetaDesc !== '') {
            return;
        }

        $this->seeder->seedSinglePost($postId);
    }
}
