<?php

declare(strict_types=1);

namespace Tests\Unit\CrossLink;

use Helmetsan\Core\CrossLink\CrossLinkService;
use PHPUnit\Framework\TestCase;
use WP_Post;

final class CrossLinkServiceTest extends TestCase
{
    private CrossLinkService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new CrossLinkService();
        $GLOBALS['wp_mock_posts'] = [];
        $GLOBALS['wp_mock_query_posts'] = [];
        $GLOBALS['wp_post_meta'] = [];
        $GLOBALS['wp_object_cache'] = [];

        $wpdbMock = new class {
            public string $prefix = 'wp_';
            public string $postmeta = 'wp_postmeta';

            public function prepare(string $query, ...$args): string
            {
                return $query;
            }

            public function get_results(string $query, string $output = 'OBJECT'): array
            {
                $rows = [];
                foreach ($GLOBALS['wp_post_meta'] as $pid => $meta) {
                    if (isset($meta[CrossLinkService::META_OUTGOING_LINKS])) {
                        $rows[] = [
                            'post_id'    => $pid,
                            'meta_value' => $meta[CrossLinkService::META_OUTGOING_LINKS],
                        ];
                    }
                }
                return $rows;
            }
        };

        $GLOBALS['wpdb'] = $wpdbMock;
    }

    public function testGetStoredOutgoingLinksReturnsEmptyOnMissingMeta(): void
    {
        $links = $this->service->getStoredOutgoingLinks(999);
        $this->assertSame([], $links);
    }

    public function testGetStoredOutgoingLinksDecodesValidJson(): void
    {
        $mockLinks = [
            ['post_id' => 102, 'url' => 'https://helmetsan.com/?p=102', 'reason' => 'same_brand'],
        ];
        $GLOBALS['wp_post_meta'][101][CrossLinkService::META_OUTGOING_LINKS] = json_encode($mockLinks);

        $links = $this->service->getStoredOutgoingLinks(101);
        $this->assertCount(1, $links);
        $this->assertSame(102, $links[0]['post_id']);
        $this->assertSame('same_brand', $links[0]['reason']);
    }

    public function testFindOrphanPagesIdentifiesUnreferencedPublishedPosts(): void
    {
        $orphan = new WP_Post();
        $orphan->ID = 301;
        $orphan->post_type = 'helmet';
        $orphan->post_title = 'Lonely Helmet';
        $orphan->post_status = 'publish';

        $linked = new WP_Post();
        $linked->ID = 302;
        $linked->post_type = 'helmet';
        $linked->post_title = 'Popular Helmet';
        $linked->post_status = 'publish';

        $source = new WP_Post();
        $source->ID = 303;
        $source->post_type = 'helmet';
        $source->post_title = 'Source Helmet';
        $source->post_status = 'publish';

        $GLOBALS['wp_mock_posts'][301] = $orphan;
        $GLOBALS['wp_mock_posts'][302] = $linked;
        $GLOBALS['wp_mock_posts'][303] = $source;

        // Post 303 points to 302
        $GLOBALS['wp_post_meta'][303][CrossLinkService::META_OUTGOING_LINKS] = json_encode([
            ['post_id' => 302, 'url' => 'https://helmetsan.com/?p=302', 'reason' => 'same_brand'],
        ]);

        // Mock query returning all 3
        $GLOBALS['wp_mock_query_posts'] = [$orphan, $linked, $source];

        $orphans = $this->service->findOrphanPages('helmet', 10);
        $orphanIds = array_column($orphans, 'post_id');

        $this->assertContains(301, $orphanIds);
        $this->assertSame('Lonely Helmet', $orphans[0]['title']);
    }

    public function testSuggestBidirectionalLinksIdentifiesMissingReciprocals(): void
    {
        $postA = new WP_Post();
        $postA->ID = 401;
        $postA->post_type = 'helmet';
        $postA->post_title = 'Helmet A';
        $postA->post_status = 'publish';

        $postB = new WP_Post();
        $postB->ID = 402;
        $postB->post_type = 'helmet';
        $postB->post_title = 'Helmet B';
        $postB->post_status = 'publish';

        $GLOBALS['wp_mock_posts'][401] = $postA;
        $GLOBALS['wp_mock_posts'][402] = $postB;

        // A links to B, but B does not link back to A
        $GLOBALS['wp_post_meta'][401][CrossLinkService::META_OUTGOING_LINKS] = json_encode([
            ['post_id' => 402, 'url' => 'https://helmetsan.com/?p=402', 'reason' => 'same_brand'],
        ]);
        $GLOBALS['wp_post_meta'][402][CrossLinkService::META_OUTGOING_LINKS] = json_encode([]);

        $result = $this->service->suggestBidirectionalLinks(401);

        $this->assertSame(401, $result['post_id']);
        $this->assertCount(1, $result['unreciprocated_outgoing']);
        $this->assertSame(402, $result['unreciprocated_outgoing'][0]['target_post_id']);
        $this->assertSame('Helmet B', $result['unreciprocated_outgoing'][0]['target_title']);
        $this->assertCount(1, $result['suggested_reciprocals']);
        $this->assertSame('reciprocal_cluster', $result['suggested_reciprocals'][0]['reason']);
    }
}
