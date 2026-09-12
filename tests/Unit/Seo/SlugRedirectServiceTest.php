<?php

declare(strict_types=1);

namespace Tests\Unit\Seo;

use Helmetsan\Core\Seo\SlugRedirectService;
use PHPUnit\Framework\TestCase;
use WP_Post;

final class SlugRedirectServiceTest extends TestCase
{
    private SlugRedirectService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new SlugRedirectService();
        $GLOBALS['wp_post_meta'] = [];
        $GLOBALS['wp_mock_posts'] = [];

        $wpdbMock = new class {
            public string $prefix = 'wp_';
            public array $replaced = [];

            public function prepare(string $query, ...$args): string
            {
                return $query;
            }

            public function get_var($query)
            {
                return 'wp_helmetsan_slug_redirects';
            }

            public function get_row($query, $output = OBJECT)
            {
                return null;
            }

            public function replace($table, $data, $format = null)
            {
                $this->replaced[] = $data;
                return 1;
            }

            public function query($query)
            {
                return 1;
            }
        };

        $GLOBALS['wpdb'] = $wpdbMock;
    }

    public function testOnBeforeDeletePostIgnoresUnsupportedPostType(): void
    {
        $post = new WP_Post();
        $post->ID = 101;
        $post->post_type = 'page';
        $post->post_name = 'about-us';

        $GLOBALS['wp_mock_posts'][101] = $post;

        $this->service->onBeforeDeletePost(101);
        $this->assertEmpty($GLOBALS['wpdb']->replaced);
    }

    public function testOnBeforeDeletePostRecordsHelmetRedirect(): void
    {
        $post = new WP_Post();
        $post->ID = 105;
        $post->post_type = 'helmet';
        $post->post_name = 'shoei-rf-1400';

        $GLOBALS['wp_mock_posts'][105] = $post;

        $this->service->onBeforeDeletePost(105);
        $this->assertNotEmpty($GLOBALS['wpdb']->replaced);
        $this->assertSame('shoei-rf-1400', $GLOBALS['wpdb']->replaced[0]['source_slug']);
    }

    public function testOnPostUpdatedDetectsSlugChange(): void
    {
        $postBefore = new WP_Post();
        $postBefore->ID = 202;
        $postBefore->post_type = 'helmet';
        $postBefore->post_name = 'old-shoei-helmet';

        $postAfter = new WP_Post();
        $postAfter->ID = 202;
        $postAfter->post_type = 'helmet';
        $postAfter->post_name = 'new-shoei-helmet';

        $GLOBALS['wp_mock_posts'][202] = $postAfter;

        $this->service->onPostUpdated(202, $postAfter, $postBefore);
        $this->assertNotEmpty($GLOBALS['wpdb']->replaced);
        $this->assertSame('old-shoei-helmet', $GLOBALS['wpdb']->replaced[0]['source_slug']);
    }

    public function testAddRedirectStoresIntoTable(): void
    {
        $added = $this->service->addRedirect('shoei-gt-air-2', 'https://helmetsan.com/helmets/shoei-gt-air-3/', 'helmet', 301);
        $this->assertTrue($added);
        $this->assertSame('shoei-gt-air-2', $GLOBALS['wpdb']->replaced[0]['source_slug']);
        $this->assertSame(301, $GLOBALS['wpdb']->replaced[0]['redirect_status']);
    }
}
