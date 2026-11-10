<?php

declare(strict_types=1);

namespace Helmetsan\Core\Tests\API;

require_once dirname(__DIR__, 3) . '/helmetsan-core/includes/CompatibilityEngine.php';

use Helmetsan\Core\API\FinderController;
use PHPUnit\Framework\TestCase;
use WP_REST_Request;
use WP_REST_Response;

final class FinderControllerTest extends TestCase
{
    private FinderController $controller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->controller = new FinderController();
    }

    public function testSearchBikesReturnsList(): void
    {
        $request = new WP_REST_Request('GET', '/hs/v1/finder/bikes');
        $request->set_param('q', 'Himalayan');

        $response = $this->controller->searchBikes($request);
        $this->assertInstanceOf(WP_REST_Response::class, $response);
        $this->assertSame(200, $response->get_status());

        $data = $response->get_data();
        $this->assertIsArray($data);
        $this->assertArrayHasKey('bikes', $data);
        $this->assertNotEmpty($data['bikes']);
    }

    public function testCalculateMatchReturnsRankedRecommendations(): void
    {
        $request = new WP_REST_Request('GET', '/hs/v1/finder/match');
        $request->set_param('bike', 'Royal Enfield Himalayan 450');
        $request->set_param('riding_style', 'adventure');
        $request->set_param('head_shape', 'intermediate_oval');
        $request->set_param('safety_priority', 'max_safety');
        $request->set_param('features', 'sun_visor,bluetooth_ready');

        $response = $this->controller->calculateMatch($request);
        $this->assertInstanceOf(WP_REST_Response::class, $response);
        $this->assertSame(200, $response->get_status());

        $data = $response->get_data();
        $this->assertIsArray($data);
        $this->assertArrayHasKey('profile_summary', $data);
        $this->assertArrayHasKey('matches', $data);
        $this->assertArrayHasKey('total_candidates_analyzed', $data);

        if (!empty($data['matches'])) {
            $first = $data['matches'][0];
            $this->assertArrayHasKey('title', $first);
            $this->assertArrayHasKey('match_score', $first);
            $this->assertArrayHasKey('sub_scores', $first);
            $this->assertArrayHasKey('specs', $first);
            $this->assertArrayHasKey('compatible_gear', $first);
            $this->assertGreaterThanOrEqual(50, $first['match_score']);
        }
    }
}
