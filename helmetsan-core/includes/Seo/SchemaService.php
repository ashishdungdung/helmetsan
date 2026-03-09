<?php

declare(strict_types=1);

namespace Helmetsan\Core\Seo;

/**
 * Outputs JSON-LD structured data for rich results: Product (helmet, accessory, motorcycle),
 * BreadcrumbList, WebSite, Organization.
 */
final class SchemaService
{
    private const OFFER_VALID_DAYS_DEFAULT = 30;

    public function register(): void
    {
        add_action('wp_head', [$this, 'printProductSchema'], 30);
        add_action('wp_head', [$this, 'printBreadcrumbListSchema'], 31);
        add_action('wp_head', [$this, 'printWebSiteSchema'], 32);
        add_action('wp_head', [$this, 'printOrganizationSchema'], 33);
        add_action('wp_head', [$this, 'printItemListSchema'], 34);
    }

    public function printProductSchema(): void
    {
        if (! is_singular(['helmet', 'accessory', 'motorcycle'])) {
            return;
        }

        $postId = (int) get_queried_object_id();
        if ($postId <= 0) {
            return;
        }

        $post = get_post($postId);
        if (! $post instanceof \WP_Post) {
            return;
        }

        $schema = null;
        if ($post->post_type === 'helmet') {
            $schema = $this->buildProductSchemaHelmet($postId);
        } elseif ($post->post_type === 'accessory') {
            $schema = $this->buildProductSchemaAccessory($postId);
        } elseif ($post->post_type === 'motorcycle') {
            $schema = $this->buildProductSchemaMotorcycle($postId);
        }

        if ($schema === null || $schema === []) {
            return;
        }

        echo '<script type="application/ld+json">' . wp_json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>' . "\n";
    }

    /**
     * Build Offer with priceValidUntil (required for rich results).
     *
     * @param array<string,mixed> $offerMeta best_offer_json decode or similar
     * @param float|string        $price     Fallback price if offer has none
     * @param string              $currency  e.g. USD
     * @param string              $url       Offer URL (e.g. permalink)
     * @return array<string,mixed>
     */
    private function buildOfferSchema(array $offerMeta, $price, string $currency, string $url): array
    {
        $offerPrice = isset($offerMeta['offer_price']) ? (float) $offerMeta['offer_price'] : (float) $price;
        $offerCurrency = ! empty($offerMeta['currency']) ? (string) $offerMeta['currency'] : $currency;
        $validUntil = $this->resolvePriceValidUntil($offerMeta);

        $offer = [
            '@type'          => 'Offer',
            'priceCurrency'  => $offerCurrency,
            'price'          => $offerPrice,
            'availability'   => 'https://schema.org/InStock',
            'url'            => $url,
            'priceValidUntil' => $validUntil,
        ];

        return $offer;
    }

    private function resolvePriceValidUntil(array $offerMeta): string
    {
        $raw = (string) ($offerMeta['valid_until'] ?? '');
        if ($raw !== '' && strtotime($raw) !== false) {
            return gmdate('c', strtotime($raw));
        }

        return gmdate('c', strtotime('+' . self::OFFER_VALID_DAYS_DEFAULT . ' days'));
    }

    /**
     * @return array<string,mixed>|null
     */
    public function buildProductSchemaHelmet(int $postId): ?array
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
        $sharpRating = get_post_meta($postId, 'safety_sharp_rating', true);
        $weight = get_post_meta($postId, 'spec_weight_g', true);
        $bestOfferRaw = (string) get_post_meta($postId, 'best_offer_json', true);
        $bestOffer = $bestOfferRaw !== '' ? json_decode($bestOfferRaw, true) : null;
        $bestOffer = is_array($bestOffer) ? $bestOffer : [];

        $schema = [
            '@context'    => 'https://schema.org',
            '@type'       => 'Product',
            'name'        => (string) $post->post_title,
            'url'         => get_permalink($postId),
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

        $offerPrice = isset($bestOffer['offer_price']) ? (float) $bestOffer['offer_price'] : (is_numeric((string) $price) ? (float) $price : 0.0);
        $offerCurrency = ! empty($bestOffer['currency']) ? (string) $bestOffer['currency'] : 'USD';
        if ($offerPrice > 0) {
            $schema['offers'] = $this->buildOfferSchema(
                $bestOffer,
                $offerPrice,
                $offerCurrency,
                get_permalink($postId)
            );
        }

        $this->appendAggregateRatingAndReviews($schema, $postId);

        $additionalProps = [];
        if (is_numeric((string) $weight)) {
            $additionalProps[] = [
                '@type' => 'PropertyValue',
                'name'  => 'Weight',
                'value' => (int) $weight . ' g',
            ];
        }
        if (is_numeric((string) $sharpRating)) {
            $additionalProps[] = [
                '@type' => 'PropertyValue',
                'name'  => 'SHARP safety rating',
                'value' => (string) $sharpRating,
            ];
        }
        if ($additionalProps !== []) {
            $schema['additionalProperty'] = $additionalProps;
        }

        $schema = apply_filters('helmetsan_schema_product', $schema, $postId, 'helmet');

        return is_array($schema) ? $schema : null;
    }

    /**
     * @return array<string,mixed>|null
     */
    public function buildProductSchemaAccessory(int $postId): ?array
    {
        $post = get_post($postId);
        if (! $post instanceof \WP_Post || $post->post_type !== 'accessory') {
            return null;
        }

        $image = get_the_post_thumbnail_url($postId, 'full');
        $priceJson = (string) get_post_meta($postId, 'price_json', true);
        $priceData = $priceJson !== '' ? json_decode($priceJson, true) : null;
        $price = is_array($priceData) && isset($priceData['value']) ? (float) $priceData['value'] : 0.0;
        $currency = is_array($priceData) && ! empty($priceData['currency']) ? (string) $priceData['currency'] : 'USD';

        $schema = [
            '@context'    => 'https://schema.org',
            '@type'       => 'Product',
            'name'        => (string) $post->post_title,
            'url'         => get_permalink($postId),
            'description' => wp_strip_all_tags((string) get_the_excerpt($postId)),
        ];

        if (is_string($image) && $image !== '') {
            $schema['image'] = [$image];
        }

        if ($price > 0) {
            $schema['offers'] = $this->buildOfferSchema([], $price, $currency, get_permalink($postId));
        }

        $this->appendAggregateRatingAndReviews($schema, $postId);

        $schema = apply_filters('helmetsan_schema_product', $schema, $postId, 'accessory');

        return is_array($schema) ? $schema : null;
    }

    /**
     * @return array<string,mixed>|null
     */
    public function buildProductSchemaMotorcycle(int $postId): ?array
    {
        $post = get_post($postId);
        if (! $post instanceof \WP_Post || $post->post_type !== 'motorcycle') {
            return null;
        }

        $image = get_the_post_thumbnail_url($postId, 'full');
        $make = (string) get_post_meta($postId, 'motorcycle_make', true);
        $model = (string) get_post_meta($postId, 'motorcycle_model', true);

        $schema = [
            '@context'    => 'https://schema.org',
            '@type'       => 'Product',
            'name'        => (string) $post->post_title,
            'url'         => get_permalink($postId),
            'description' => wp_strip_all_tags((string) get_the_excerpt($postId)),
        ];

        if (is_string($image) && $image !== '') {
            $schema['image'] = [$image];
        }

        if ($make !== '' || $model !== '') {
            $schema['brand'] = [
                '@type' => 'Brand',
                'name'  => $make !== '' ? $make : $model,
            ];
        }

        $this->appendAggregateRatingAndReviews($schema, $postId);

        $schema = apply_filters('helmetsan_schema_product', $schema, $postId, 'motorcycle');

        return is_array($schema) ? $schema : null;
    }

    /**
     * Add aggregateRating and review from meta when present (consumer reviews only).
     *
     * @param array<string,mixed> $schema
     */
    private function appendAggregateRatingAndReviews(array &$schema, int $postId): void
    {
        $aggRaw = (string) get_post_meta($postId, 'aggregate_rating_json', true);
        $agg = $aggRaw !== '' ? json_decode($aggRaw, true) : null;
        if (is_array($agg) && isset($agg['ratingValue']) && isset($agg['reviewCount']) && (int) $agg['reviewCount'] > 0) {
            $schema['aggregateRating'] = [
                '@type'       => 'AggregateRating',
                'ratingValue' => (float) $agg['ratingValue'],
                'reviewCount' => (int) $agg['reviewCount'],
                'bestRating'  => isset($agg['bestRating']) ? (float) $agg['bestRating'] : 5,
            ];
        }

        $reviewsRaw = (string) get_post_meta($postId, 'reviews_json', true);
        $reviews = $reviewsRaw !== '' ? json_decode($reviewsRaw, true) : null;
        if (is_array($reviews) && $reviews !== []) {
            $list = [];
            foreach ($reviews as $r) {
                if (! is_array($r) || empty($r['reviewBody'])) {
                    continue;
                }
                $list[] = [
                    '@type'        => 'Review',
                    'author'       => ['@type' => 'Person', 'name' => (string) ($r['author']['name'] ?? 'Anonymous')],
                    'datePublished' => (string) ($r['datePublished'] ?? gmdate('c')),
                    'reviewBody'   => (string) $r['reviewBody'],
                    'reviewRating' => [
                        '@type'       => 'Rating',
                        'ratingValue' => (float) ($r['reviewRating']['ratingValue'] ?? 5),
                        'bestRating'  => (float) ($r['reviewRating']['bestRating'] ?? 5),
                    ],
                ];
            }
            if ($list !== []) {
                $schema['review'] = $list;
            }
        }
    }

    public function printBreadcrumbListSchema(): void
    {
        $items = $this->buildBreadcrumbItems();
        if ($items === []) {
            return;
        }

        $schema = [
            '@context'        => 'https://schema.org',
            '@type'           => 'BreadcrumbList',
            'itemListElement' => $items,
        ];

        echo '<script type="application/ld+json">' . wp_json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>' . "\n";
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function buildBreadcrumbItems(): array
    {
        $out = [];
        $pos = 1;

        $out[] = [
            '@type'    => 'ListItem',
            'position' => $pos++,
            'name'     => get_bloginfo('name'),
            'item'     => home_url('/'),
        ];

        if (is_singular(['helmet', 'accessory', 'motorcycle', 'brand'])) {
            $post = get_queried_object();
            if ($post instanceof \WP_Post) {
                $type = $post->post_type;
                $archiveSlug = $type === 'brand' ? 'brands' : $type . 's';
                $archiveUrl = get_post_type_archive_link($type) ?: home_url('/' . $archiveSlug . '/');
                $out[] = [
                    '@type'    => 'ListItem',
                    'position' => $pos++,
                    'name'     => ucfirst($archiveSlug),
                    'item'     => $archiveUrl,
                ];
                $out[] = [
                    '@type'    => 'ListItem',
                    'position' => $pos,
                    'name'     => get_the_title($post),
                    'item'     => get_permalink($post),
                ];
            }
        } elseif (is_post_type_archive('helmet')) {
            $out[] = [
                '@type'    => 'ListItem',
                'position' => $pos,
                'name'     => 'Helmets',
                'item'     => get_post_type_archive_link('helmet') ?: home_url('/helmets/'),
            ];
        } elseif (is_post_type_archive('accessory')) {
            $out[] = [
                '@type'    => 'ListItem',
                'position' => $pos,
                'name'     => 'Accessories',
                'item'     => get_post_type_archive_link('accessory') ?: home_url('/accessories/'),
            ];
        } elseif (is_post_type_archive('motorcycle')) {
            $out[] = [
                '@type'    => 'ListItem',
                'position' => $pos,
                'name'     => 'Motorcycles',
                'item'     => get_post_type_archive_link('motorcycle') ?: home_url('/motorcycles/'),
            ];
        } elseif (is_singular()) {
            $post = get_queried_object();
            if ($post instanceof \WP_Post) {
                $out[] = [
                    '@type'    => 'ListItem',
                    'position' => $pos,
                    'name'     => get_the_title($post),
                    'item'     => get_permalink($post),
                ];
            }
        }

        return $out;
    }

    public function printWebSiteSchema(): void
    {
        if (! is_front_page()) {
            return;
        }

        $schema = [
            '@context'      => 'https://schema.org',
            '@type'         => 'WebSite',
            'name'          => get_bloginfo('name'),
            'url'           => home_url('/'),
            'description'   => get_bloginfo('description'),
            'potentialAction' => [
                '@type'       => 'SearchAction',
                'target'      => [
                    '@type'       => 'EntryPoint',
                    'urlTemplate' => home_url('/?s={search_term_string}'),
                ],
                'query-input' => 'required name=search_term_string',
            ],
        ];

        $schema = apply_filters('helmetsan_schema_website', $schema);
        if (! is_array($schema)) {
            return;
        }

        echo '<script type="application/ld+json">' . wp_json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>' . "\n";
    }

    public function printOrganizationSchema(): void
    {
        if (! is_front_page()) {
            return;
        }

        $schema = [
            '@context' => 'https://schema.org',
            '@type'    => 'Organization',
            'name'    => get_bloginfo('name'),
            'url'     => home_url('/'),
            'description' => get_bloginfo('description'),
        ];

        $schema = apply_filters('helmetsan_schema_organization', $schema);
        if (! is_array($schema)) {
            return;
        }

        echo '<script type="application/ld+json">' . wp_json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>' . "\n";
    }

    /**
     * ItemList for post type archives (helmets, accessories, motorcycles) for list/carousel rich results.
     */
    public function printItemListSchema(): void
    {
        $postTypes = ['helmet', 'accessory', 'motorcycle'];
        $current = null;
        foreach ($postTypes as $pt) {
            if (is_post_type_archive($pt)) {
                $current = $pt;
                break;
            }
        }
        if ($current === null) {
            return;
        }

        $query = new \WP_Query([
            'post_type'      => $current,
            'post_status'    => 'publish',
            'posts_per_page' => 20,
            'orderby'        => 'title',
            'order'          => 'ASC',
            'fields'         => 'ids',
        ]);

        $items = [];
        foreach ($query->posts as $id) {
            $url = get_permalink($id);
            $items[] = [
                '@type' => 'ListItem',
                'position' => count($items) + 1,
                'name'    => get_the_title($id),
                'url'     => $url !== false ? $url : '',
            ];
        }
        wp_reset_postdata();

        if ($items === []) {
            return;
        }

        $schema = [
            '@context'        => 'https://schema.org',
            '@type'           => 'ItemList',
            'name'            => ucfirst($current) . ' catalog',
            'numberOfItems'   => count($items),
            'itemListElement' => $items,
        ];

        $schema = apply_filters('helmetsan_schema_item_list', $schema, $current);
        if (! is_array($schema)) {
            return;
        }

        echo '<script type="application/ld+json">' . wp_json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>' . "\n";
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
            'ok'      => true,
            'checked' => $checked,
            'valid'   => $valid,
            'invalid' => max(0, $checked - $valid),
            'issues'  => $issues,
            'limit'   => $limit,
            'offset'  => $offset,
        ];
    }
}
