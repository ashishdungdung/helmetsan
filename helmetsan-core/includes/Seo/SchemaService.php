<?php

declare(strict_types=1);

namespace Helmetsan\Core\Seo;

final class SchemaService
{
    public function register(): void
    {
        add_action('wp_head', [$this, 'printProductSchema'], 30);
    }

    public function printProductSchema(): void
    {
        if (! is_singular('helmet')) {
            return;
        }

        $postId = (int) get_queried_object_id();
        if ($postId <= 0) {
            return;
        }

        $schema = $this->buildProductSchema($postId);
        if ($schema === null) {
            return;
        }

        echo '<script type="application/ld+json">' . wp_json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>' . "\n";
    }

    /**
     * @return array<string,mixed>|null
     */
    public function buildProductSchema(int $postId): ?array
    {
        $post = get_post($postId);
        if (! $post instanceof \WP_Post || $post->post_type !== 'helmet') {
            return null;
        }

        $brandName = '';
        $brandId = (int) get_post_meta($postId, 'rel_brand', true);
        if ($brandId > 0) {
            $brandPost = get_post($brandId);
            if ($brandPost instanceof \WP_Post) {
                $brandName = (string) $brandPost->post_title;
            }
        }

        $price = get_post_meta($postId, 'price_retail_usd', true);
        $image = get_the_post_thumbnail_url($postId, 'full');
        $rating = get_post_meta($postId, 'safety_sharp_rating', true);
        $weight = get_post_meta($postId, 'spec_weight_g', true);

        $schema = [
            '@context' => 'https://schema.org',
            '@type'    => 'Product',
            'name'     => (string) $post->post_title,
            'url'      => get_permalink($postId),
            'description' => wp_strip_all_tags((string) get_the_excerpt($postId)),
        ];

        if (is_string($image) && $image !== '') {
            $schema['image'] = [$image];
        }

        if ($brandName !== '') {
            $schema['brand'] = [
                '@type' => 'Brand',
                'name'  => $brandName,
            ];
        }

        if (is_numeric((string) $price)) {
            $schema['offers'] = [
                '@type'         => 'Offer',
                'priceCurrency' => 'USD',
                'price'         => (float) $price,
                'availability'  => 'https://schema.org/InStock',
                'url'           => get_permalink($postId),
            ];
        }

        if (is_numeric((string) $rating)) {
            $schema['aggregateRating'] = [
                '@type'       => 'AggregateRating',
                'ratingValue' => (float) $rating,
                'reviewCount' => 1,
            ];
        }

        if (is_numeric((string) $weight)) {
            $schema['additionalProperty'] = [
                [
                    '@type' => 'PropertyValue',
                    'name'  => 'Weight',
                    'value' => (int) $weight . ' g',
                ],
            ];
        }

        /**
         * Allow custom extension of generated schema.
         *
         * @param array<string,mixed> $schema
         */
        $schema = apply_filters('helmetsan_schema_product', $schema, $postId);

        return is_array($schema) ? $schema : null;
    }

    /**
     * @return array<string,mixed>
     */
    public function audit(int $limit = 200, int $offset = 0): array
    {
        $query = new \WP_Query([
            'post_type'      => 'helmet',
            'post_status'    => 'publish',
            'posts_per_page' => max(1, $limit),
            'offset'         => max(0, $offset),
            'fields'         => 'ids',
        ]);

        $checked = 0;
        $valid   = 0;
        $issues  = [];

        foreach ($query->posts as $postId) {
            $postId = (int) $postId;
            if ($postId <= 0) {
                continue;
            }
            $checked++;

            $missing = [];
            if (get_the_title($postId) === '') {
                $missing[] = 'title';
            }
            if (get_permalink($postId) === false) {
                $missing[] = 'permalink';
            }
            if (! is_numeric((string) get_post_meta($postId, 'price_retail_usd', true))) {
                $missing[] = 'price_retail_usd';
            }
            if (get_post_meta($postId, 'rel_brand', true) === '') {
                $missing[] = 'rel_brand';
            }
            if (get_the_post_thumbnail_url($postId, 'full') === false) {
                $missing[] = 'featured_image';
            }

            if ($missing === []) {
                $valid++;
                continue;
            }

            $issues[] = [
                'post_id' => $postId,
                'title'   => get_the_title($postId),
                'missing' => $missing,
            ];
        }

        wp_reset_postdata();

        return [
            'ok'            => true,
            'checked'       => $checked,
            'valid'         => $valid,
            'invalid'       => max(0, $checked - $valid),
            'issues'        => $issues,
            'limit'         => $limit,
            'offset'        => $offset,
        ];
    }
}
