<?php

declare(strict_types=1);

namespace Helmetsan\Core\Tests\API;

use Helmetsan\Core\API\CdnController;
use PHPUnit\Framework\TestCase;
use WP_REST_Request;
use WP_REST_Response;

final class CdnControllerTest extends TestCase
{
    private CdnController $controller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->controller = new CdnController();
    }

    public function testGetRatesReturnsCacheControlAndVaryHeaders(): void
    {
        $exchangeRatesMock = new class {
            public function getRates(): array {
                return ['USD' => 1.0, 'EUR' => 0.92, 'INR' => 83.5];
            }
        };
        $geoMock = new class {
            public function getCountry(): string {
                return 'DE';
            }
        };
        $GLOBALS['mock_helmetsan_core'] = new class($exchangeRatesMock, $geoMock) {
            public function __construct(private $rates, private $geo) {}
            public function exchangeRates() { return $this->rates; }
            public function geo() { return $this->geo; }
        };

        $request = new WP_REST_Request();
        $response = $this->controller->get_rates($request);

        $this->assertInstanceOf(WP_REST_Response::class, $response);
        $this->assertSame(200, $response->get_status());

        $data = $response->get_data();
        $this->assertIsArray($data);
        $this->assertArrayHasKey('rates', $data);
        $this->assertArrayHasKey('detected_country', $data);
        $this->assertSame('DE', $data['detected_country']);

        $headers = $response->get_headers();
        $this->assertArrayHasKey('Cache-Control', $headers);
        $this->assertStringContainsString('no-cache', $headers['Cache-Control']);
        $this->assertStringContainsString('no-store', $headers['Cache-Control']);
        $this->assertArrayHasKey('Vary', $headers);
        $this->assertStringContainsString('CF-IPCountry', $headers['Vary']);
    }
}
