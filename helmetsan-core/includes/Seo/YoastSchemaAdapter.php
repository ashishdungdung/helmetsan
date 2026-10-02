<?php

declare(strict_types=1);

namespace Helmetsan\Core\Seo;

use WP_Post;

/**
 * Bridges Helmetsan domain structured data into Yoast SEO's unified Schema.org @graph.
 *
 * Eliminates duplicate WebSite/Organization/BreadcrumbList scripts by injecting
 * rich Product (helmet, accessory, motorcycle), Offer, AggregateRating, Brand,
 * and FAQPage nodes directly into Yoast's primary JSON-LD graph.
 *
 * @see https://developer.yoast.com/features/schema/integration-guidelines/
 */
final class YoastSchemaAdapter
{
    public function __construct(
        private readonly SchemaService $schemaService
    ) {}

    public function register(): void
    {
        add_filter('wpseo_schema_graph', [$this, 'filterSchemaGraph'], 20, 2);
    }

    /**
     * Filter Yoast's complete Schema @graph array to wire Helmetsan domain entities.
     *
     * @param array<int, array<string, mixed>> $graph Yoast @graph array of nodes.
     * @param mixed                            $context Yoast Schema Context object (if available).
     * @return array<int, array<string, mixed>>
     */
    public function filterSchemaGraph(array $graph, mixed $context = null): array
    {
        if (! is_singular(['helmet', 'accessory', 'motorcycle'])) {
            return $graph;
        }

        $postId = (int) get_queried_object_id();
        if ($postId <= 0) {
            return $graph;
        }

        $post = get_post($postId);
        if (! $post instanceof WP_Post) {
            return $graph;
        }

        $canonicalUrl = (string) get_permalink($postId);

        // Find existing WebPage and WebSite node IDs for graph relation linking
        $webPageNodeId = null;
        $webPageNodeIndex = null;
        foreach ($graph as $index => $node) {
            $type = $node['@type'] ?? '';
            $id = (string) ($node['@id'] ?? '');
            if ($type === 'WebPage' || str_ends_with($id, '#webpage')) {
                $webPageNodeId = $id;
                $webPageNodeIndex = $index;
                break;
            }
        }

        // Build domain product schema node based on post type
        $productNode = null;
        if ($post->post_type === 'helmet') {
            $productNode = $this->schemaService->buildProductSchemaHelmet($postId);
        } elseif ($post->post_type === 'accessory') {
            $productNode = $this->schemaService->buildProductSchemaAccessory($postId);
        } elseif ($post->post_type === 'motorcycle') {
            $productNode = $this->schemaService->buildProductSchemaMotorcycle($postId);
        }

        if ($productNode === null || $productNode === []) {
            return $graph;
        }

        // Strip standalone context since it will be part of Yoast's unified @graph
        unset($productNode['@context']);

        $productId = $canonicalUrl . '#product';
        $productNode['@id'] = $productId;

        // Wire offers to offer node ID
        if (isset($productNode['offers']) && is_array($productNode['offers'])) {
            $productNode['offers']['@id'] = $canonicalUrl . '#offer';
        }

        // Wire aggregate rating node ID
        if (isset($productNode['aggregateRating']) && is_array($productNode['aggregateRating'])) {
            $productNode['aggregateRating']['@id'] = $canonicalUrl . '#aggregate-rating';
        }

        // Link WebPage to Product as its mainEntity
        if ($webPageNodeIndex !== null && isset($graph[$webPageNodeIndex])) {
            $graph[$webPageNodeIndex]['mainEntity'] = ['@id' => $productId];
        }

        // Append rich Product node to Yoast's graph
        $graph[] = $productNode;

        // GEO Enrichment: Append FAQPage node to Yoast graph if helmet has FAQs
        if ($post->post_type === 'helmet') {
            $faqNode = $this->schemaService->buildFaqSchemaHelmet($postId);
            if ($faqNode !== null && !empty($faqNode['mainEntity'])) {
                unset($faqNode['@context']);
                $faqNode['@id'] = $canonicalUrl . '#faq';
                if ($webPageNodeId !== null) {
                    $faqNode['isPartOf'] = ['@id' => $webPageNodeId];
                }
                $graph[] = $faqNode;
            }
        }

        return $graph;
    }
}
