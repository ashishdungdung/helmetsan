<?php

declare(strict_types=1);

namespace Helmetsan\Tests\Unit\Geo;

use Helmetsan\Core\Geo\GeoService;
use PHPUnit\Framework\TestCase;

class GeoServiceTest extends TestCase
{
    private ?string $originalUri = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalUri = $_SERVER['REQUEST_URI'] ?? null;
        unset($_GET['country'], $_COOKIE['helmetsan_geo'], $_SERVER['HTTP_CF_IPCOUNTRY'], $_SERVER['REQUEST_URI']);
    }

    protected function tearDown(): void
    {
        unset($_GET['country'], $_COOKIE['helmetsan_geo'], $_SERVER['HTTP_CF_IPCOUNTRY']);
        if ($this->originalUri !== null) {
            $_SERVER['REQUEST_URI'] = $this->originalUri;
        } else {
            unset($_SERVER['REQUEST_URI']);
        }
        parent::tearDown();
    }

    public function testGetCountryDefaultIsIndia(): void
    {
        $service = new GeoService();
        $country = $service->getCountry();
        $this->assertSame('IN', $country);
    }

    public function testGetCountryFromCloudflareHeader(): void
    {
        $_SERVER['HTTP_CF_IPCOUNTRY'] = 'IN';
        $service = new GeoService();
        $country = $service->getCountry();
        $this->assertSame('IN', $country);
    }

    public function testGetCountryFromCookie(): void
    {
        $_COOKIE['helmetsan_geo'] = 'IN';
        $_SERVER['HTTP_CF_IPCOUNTRY'] = 'FR'; // Cookie should take priority over edge auto-detection
        $service = new GeoService();
        $country = $service->getCountry();
        $this->assertSame('IN', $country);
    }

    public function testGetCountryFromQueryParam(): void
    {
        $_GET['country'] = 'in';
        $_COOKIE['helmetsan_geo'] = 'US';
        $service = new GeoService();
        $country = $service->getCountry();
        $this->assertSame('IN', $country);
    }

    public function testLanguageRouteDoesNotOverrideCountryToFrance(): void
    {
        $_SERVER['REQUEST_URI'] = '/fr/helmets/shoei-gt-air-3';
        $_SERVER['HTTP_CF_IPCOUNTRY'] = 'IN';
        $service = new GeoService();
        $country = $service->getCountry();
        $this->assertSame('IN', $country, 'Language URL must not decouple or override the user country/currency');
    }

    public function testGetSupportedCountriesContainsAllUiCountries(): void
    {
        $countries = GeoService::getSupportedCountries();
        $this->assertArrayHasKey('NO', $countries);
        $this->assertArrayHasKey('CH', $countries);
        $this->assertArrayHasKey('SE', $countries);
        $this->assertArrayHasKey('KR', $countries);
        $this->assertArrayHasKey('NZ', $countries);
        $this->assertArrayHasKey('SG', $countries);
        $this->assertArrayHasKey('SA', $countries);
        $this->assertSame('NOK', $countries['NO']['currency']);
        $this->assertSame('CHF', $countries['CH']['currency']);
        $this->assertSame('KRW', $countries['KR']['currency']);
    }

    public function testInvalidCookieValueIsRejected(): void
    {
        $_COOKIE['helmetsan_geo'] = 'ZZ'; // Invalid country code
        $service = new GeoService();
        $country = $service->getCountry();
        $this->assertSame('IN', $country, 'Invalid 2-letter cookie should be ignored and fall back to default IN');
    }
}
