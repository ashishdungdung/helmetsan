<?php

declare(strict_types=1);

namespace Helmetsan\Core\Analytics\Tests;

use Helmetsan\Core\Analytics\Tracker;
use Helmetsan\Core\Geo\GeoService;
use PHPUnit\Framework\TestCase;

final class TrackerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['wp_options'] = [];
        $GLOBALS['wp_mock_is_front_page'] = false;
        $GLOBALS['wp_mock_is_home'] = false;
        $GLOBALS['wp_mock_singular_post_type'] = null;
        $GLOBALS['wp_mock_is_page'] = false;
        $GLOBALS['wp_mock_the_id'] = false;
        unset($_COOKIE['helmetsan_geo'], $_COOKIE['helmetsan_currency'], $_GET['ids']);
    }

    public function testGetClarityCustomTagsReturnsCoreTaxonomy(): void
    {
        $geo = new GeoService();
        $tracker = new Tracker($geo);

        $tags = $tracker->getClarityCustomTags();

        $this->assertIsArray($tags);
        $this->assertArrayHasKey('user_country', $tags);
        $this->assertArrayHasKey('user_currency', $tags);
        $this->assertArrayHasKey('site_language', $tags);
        $this->assertArrayHasKey('page_type', $tags);
        $this->assertSame('US', $tags['user_country']);
        $this->assertSame('USD', $tags['user_currency']);
        $this->assertSame('other', $tags['page_type']);
    }

    public function testGetClarityCustomTagsRespectsGeoOverride(): void
    {
        $_COOKIE['helmetsan_geo'] = 'IN';
        $_COOKIE['helmetsan_currency'] = 'INR';

        $geo = new GeoService();
        $tracker = new Tracker($geo);

        $tags = $tracker->getClarityCustomTags();

        $this->assertSame('IN', $tags['user_country']);
        $this->assertSame('INR', $tags['user_currency']);
    }

    public function testGetClarityCustomTagsIdentifiesFrontPage(): void
    {
        $GLOBALS['wp_mock_is_front_page'] = true;
        $tracker = new Tracker();
        $tags = $tracker->getClarityCustomTags();

        $this->assertSame('home', $tags['page_type']);
    }

    public function testGetClarityCustomTagsIdentifiesComparison(): void
    {
        $GLOBALS['wp_mock_is_page'] = true;
        $_GET['ids'] = 'shoei-x-fifteen,agv-pista-gp-rr';

        $tracker = new Tracker();
        $tags = $tracker->getClarityCustomTags();

        $this->assertSame('comparison', $tags['page_type']);
        $this->assertSame('shoei-x-fifteen,agv-pista-gp-rr', $tags['comparison_pair']);
    }

    public function testGetClarityCustomTagsIdentifiesHelmetPdp(): void
    {
        $GLOBALS['wp_mock_singular_post_type'] = 'helmet';
        $GLOBALS['wp_mock_the_id'] = 42;

        $tracker = new Tracker();
        $tags = $tracker->getClarityCustomTags();

        $this->assertSame('helmet_pdp', $tags['page_type']);
    }

    public function testPrintHeadScriptsOutputsConsentModeV2Default(): void
    {
        $GLOBALS['wp_options'][\Helmetsan\Core\Support\Config::OPTION_ANALYTICS] = [
            'enable_analytics'   => true,
            'exclude_admins'     => false,
            'ga4_measurement_id' => 'G-ABC1234567',
        ];

        $tracker = new Tracker();
        ob_start();
        $tracker->printHeadScripts();
        $output = ob_get_clean();

        $this->assertStringContainsString("window.gtag('consent', 'default'", $output);
        $this->assertStringContainsString('"ad_storage":"granted"', $output);
        $this->assertStringContainsString('"analytics_storage":"granted"', $output);
        $this->assertStringContainsString('"ad_user_data":"granted"', $output);
        $this->assertStringContainsString('"ad_personalization":"granted"', $output);
        $this->assertStringContainsString('gtag/js?id=G-ABC1234567', $output);
    }

    public function testPrintHeadScriptsRespectsConsentGate(): void
    {
        $GLOBALS['wp_options'][\Helmetsan\Core\Support\Config::OPTION_ANALYTICS] = [
            'enable_analytics'     => true,
            'exclude_admins'     => false,
            'enable_consent_gate'  => true,
            'consent_cookie_name'  => 'helmetsan_consent_test',
            'ga4_measurement_id'   => 'G-ABC1234567',
        ];

        // Without cookie: suppressed by consent gate
        $tracker = new Tracker();
        ob_start();
        $tracker->printHeadScripts();
        $output = ob_get_clean();
        $this->assertStringContainsString('consent gate active', $output);

        // With cookie: loaded and consent granted
        $_COOKIE['helmetsan_consent_test'] = '1';
        ob_start();
        $tracker->printHeadScripts();
        $output = ob_get_clean();
        $this->assertStringContainsString("window.gtag('consent', 'default'", $output);
        $this->assertStringContainsString('"analytics_storage":"granted"', $output);
    }
}
