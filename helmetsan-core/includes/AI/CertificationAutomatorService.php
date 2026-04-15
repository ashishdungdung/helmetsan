<?php

declare(strict_types=1);

namespace Helmetsan\Core\AI;

use WP_Post;
use WP_Term;

/**
 * Automates the creation and enrichment of Safety Standard authority pages
 * based on the certification taxonomy.
 */
final class CertificationAutomatorService
{
    private const TAXONOMY = 'certification';
    private const POST_TYPE = 'safety_standard';

    public function __construct(
        private readonly AiServiceInterface $aiService
    ) {
    }

    /**
     * Audit: Find certifications that need authority pages or content enrichment.
     */
    public function audit(): array
    {
        $terms = get_terms([
            'taxonomy'   => self::TAXONOMY,
            'hide_empty' => false,
        ]);

        if (is_wp_error($terms) || empty($terms)) {
            return [];
        }

        $report = [];
        foreach ($terms as $term) {
            if (! $term instanceof WP_Term) continue;

            $post = $this->getMatchingPost($term->name);
            $status = 'missing';
            $wordCount = 0;
            $postId = 0;

            if ($post instanceof WP_Post) {
                $postId = $post->ID;
                $wordCount = str_word_count(strip_tags((string)$post->post_content));
                $status = ($wordCount < 300) ? 'thin' : 'ready';
            }

            $report[] = [
                'term_id'    => $term->term_id,
                'name'       => $term->name,
                'slug'       => $term->slug,
                'status'     => $status,
                'post_id'    => $postId,
                'word_count' => $wordCount,
                'usage'      => (int)$term->count
            ];
        }

        // Sort by usage descending
        usort($report, fn($a, $b) => $b['usage'] <=> $a['usage']);

        return $report;
    }

    /**
     * Create/Sync a single certification authority page.
     */
    public function syncSingle(int $termId): array
    {
        $term = get_term($termId, self::TAXONOMY);
        if (! $term instanceof WP_Term) {
            return ['ok' => false, 'message' => 'Term not found.'];
        }

        $post = $this->getMatchingPost($term->name);
        if ($post instanceof WP_Post) {
            return ['ok' => true, 'id' => $post->ID, 'message' => 'Post already exists.'];
        }

        $postId = wp_insert_post([
            'post_title'   => $term->name,
            'post_type'    => self::POST_TYPE,
            'post_status'  => 'publish',
            'post_content' => '', // Start empty for AI enrichment
        ]);

        if (is_wp_error($postId)) {
            return ['ok' => false, 'message' => $postId->get_error_message()];
        }

        // Add taxonomy link
        wp_set_object_terms($postId, [$term->term_id], self::TAXONOMY);

        return ['ok' => true, 'id' => $postId, 'message' => 'Authority page created.'];
    }

    /**
     * Enrich an authority page with AI-generated technical content.
     */
    public function enrich(int $postId): array
    {
        $post = get_post($postId);
        if (! $post instanceof WP_Post || $post->post_type !== self::POST_TYPE) {
            return ['ok' => false, 'message' => 'Invalid post.'];
        }

        $content = $this->aiService->generateTechnicalGuide($post->post_title);
        if ($content === null || $content === '') {
            return ['ok' => false, 'message' => 'AI failed to generate content.'];
        }

        // Clean up the output (remove common AI prefixes if any)
        $content = trim($content);
        
        wp_update_post([
            'ID'           => $postId,
            'post_content' => $content,
        ]);

        // Attempt to extract issuing body for meta
        $this->enrichMeta($postId, $post->post_title);

        return ['ok' => true, 'message' => 'Content enriched successfully.'];
    }

    private function getMatchingPost(string $title): ?WP_Post
    {
        $posts = get_posts([
            'post_type'      => self::POST_TYPE,
            'title'          => $title,
            'posts_per_page' => 1,
            'post_status'    => 'any',
        ]);

        return ! empty($posts) ? $posts[0] : null;
    }

    private function enrichMeta(int $postId, string $standardName): void
    {
        // Simple extraction for now; can be expanded to more AI fields later
        if (stripos($standardName, 'DOT') !== false) {
            update_post_meta($postId, 'standard_issuing_body', 'NHTSA (USA)');
            update_post_meta($postId, 'standard_region', 'North America');
        } elseif (stripos($standardName, 'ECE') !== false) {
            update_post_meta($postId, 'standard_issuing_body', 'United Nations Economic Commission for Europe');
            update_post_meta($postId, 'standard_region', 'Global / EU');
        }
    }
}
