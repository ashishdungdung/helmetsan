<?php

declare(strict_types=1);

namespace Tests\Unit\API;

use Helmetsan\Core\API\ApiGateway;
use Helmetsan\Core\API\DataApiController;
use Helmetsan\Core\Price\PriceService;
use Helmetsan\Core\Reviews\ReviewService;
use PHPUnit\Framework\TestCase;
use WP_Post;

final class DataApiControllerTest extends TestCase
{
    private DataApiController $controller;

    protected function setUp(): void
    {
        parent::setUp();
        $priceService = (new \ReflectionClass(PriceService::class))->newInstanceWithoutConstructor();
        $reviewService = (new \ReflectionClass(ReviewService::class))->newInstanceWithoutConstructor();
        $gateway = (new \ReflectionClass(ApiGateway::class))->newInstanceWithoutConstructor();

        $this->controller = new DataApiController($priceService, $reviewService, $gateway);
        $GLOBALS['wp_mock_posts'] = [];
        $GLOBALS['wp_post_meta'] = [];
    }

    public function testBuildComparisonMarkdownOutputsHeadToHeadTable(): void
    {
        $h1 = new WP_Post();
        $h1->ID = 601;
        $h1->post_type = 'helmet';
        $h1->post_name = 'shoei-x-fifteen';
        $h1->post_title = 'Shoei X-Fifteen';
        $h1->post_status = 'publish';

        $h2 = new WP_Post();
        $h2->ID = 602;
        $h2->post_type = 'helmet';
        $h2->post_name = 'agv-pista-gp-rr';
        $h2->post_title = 'AGV Pista GP RR';
        $h2->post_status = 'publish';

        $GLOBALS['wp_mock_posts'][601] = $h1;
        $GLOBALS['wp_mock_posts'][602] = $h2;
        $GLOBALS['wp_post_meta'][601]['spec_weight_g'] = 1395;
        $GLOBALS['wp_post_meta'][601]['noise_db_at_100kph'] = 98;
        $GLOBALS['wp_post_meta'][601]['price_retail_usd'] = 899.99;

        $GLOBALS['wp_post_meta'][602]['spec_weight_g'] = 1450;
        $GLOBALS['wp_post_meta'][602]['noise_db_at_100kph'] = 104;
        $GLOBALS['wp_post_meta'][602]['price_retail_usd'] = 1599.95;

        $md = $this->controller->buildComparisonMarkdown([$h1, $h2]);

        $this->assertStringContainsString('# Shoei X-Fifteen vs AGV Pista GP RR — Side-by-Side Technical Comparison', $md);
        $this->assertStringContainsString('| Specification | **Shoei X-Fifteen** | **AGV Pista GP RR** |', $md);
        $this->assertStringContainsString('1395 g', $md);
        $this->assertStringContainsString('1450 g', $md);
        $this->assertStringContainsString('98 dB', $md);
        $this->assertStringContainsString('104 dB', $md);
        $this->assertStringContainsString('## Comparative Verdict', $md);
        $this->assertStringContainsString('Weight Advantage', $md);
        $this->assertStringContainsString('Acoustic Advantage', $md);
        $this->assertStringContainsString('Helmetsan Motorcycle Intelligence Matrix', $md);
    }

    public function testBuildComparisonMarkdownHandlesInsufficientHelmetsGracefully(): void
    {
        $h1 = new WP_Post();
        $h1->ID = 601;
        $h1->post_type = 'helmet';
        $h1->post_title = 'Solo Helmet';

        $md = $this->controller->buildComparisonMarkdown([$h1]);

        $this->assertStringContainsString('Comparison Matrix', $md);
        $this->assertStringContainsString('?format=md&ids=slug1,slug2', $md);
    }

    public function testBuildMarkdownPayloadIncludesQuickAnswerAndVerdict(): void
    {
        $h = new WP_Post();
        $h->ID = 701;
        $h->post_type = 'helmet';
        $h->post_name = 'shoei-rf-1400';
        $h->post_title = 'Shoei RF-1400';
        $h->post_status = 'publish';
        $h->post_modified_gmt = '2026-09-10 12:00:00';

        $GLOBALS['wp_mock_posts'][701] = $h;
        $GLOBALS['wp_post_meta'][701]['spec_weight_g'] = 1680;
        $GLOBALS['wp_post_meta'][701]['noise_db_at_100kph'] = 98;
        $GLOBALS['wp_post_meta'][701]['price_retail_usd'] = 579.99;

        $payload = [
            'post_type'      => 'helmet',
            'metadata'       => ['title' => 'Shoei RF-1400', 'canonical_url' => 'https://helmetsan.com/helmets/shoei-rf-1400/'],
            'specifications' => ['brand' => 'Shoei', 'homologation_standard' => 'Snell M2020D / DOT', 'weight_g' => 1680],
            'pricing'        => ['retail_price_usd' => 579.99],
            'reviews'        => [],
        ];

        $md = $this->controller->buildMarkdownPayload($h, $payload);

        $this->assertStringContainsString('# Shoei RF-1400 — Review & Technical Specifications', $md);
        $this->assertStringContainsString('## Quick Answer', $md);
        $this->assertStringContainsString('## Helmetsan Verdict', $md);
        $this->assertStringContainsString('Verified Advantages', $md);
        $this->assertStringContainsString('## Frequently Asked Questions', $md);
        $this->assertStringContainsString('### Is the Shoei RF-1400 worth buying?', $md);
        $this->assertStringContainsString('Verified Last Updated', $md);
        $this->assertStringContainsString('2026-09-10', $md);
        $this->assertStringContainsString('Citation & Authority Reference', $md);
    }
}
