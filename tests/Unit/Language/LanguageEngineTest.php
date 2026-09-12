<?php

declare(strict_types=1);

namespace Helmetsan\Core\Tests\Language;

use Helmetsan\Core\CPT\Registrar;
use Helmetsan\Core\CrossLink\CrossLinkService;
use PHPUnit\Framework\TestCase;

final class LanguageEngineTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        require_once dirname(__DIR__, 3) . '/helmetsan-theme/inc/template-tags.php';
    }

    public function testHelmetsanUrlDefaultLanguage(): void
    {
        $url = helmetsan_url('/helmets/', 'en');
        $this->assertSame('https://helmetsan.com/helmets/', $url);

        $rootUrl = helmetsan_url('/', 'en');
        $this->assertSame('https://helmetsan.com/', $rootUrl);
    }

    public function testHelmetsanUrlLocalizedLanguages(): void
    {
        $deUrl = helmetsan_url('/helmets/', 'de');
        $this->assertSame('https://helmetsan.com/de/helmets/', $deUrl);

        $zhUrl = helmetsan_url('/comparison/', 'zh');
        $this->assertSame('https://helmetsan.com/zh/comparison/', $zhUrl);

        $rootDe = helmetsan_url('/', 'de');
        $this->assertSame('https://helmetsan.com/de/', $rootDe);
    }

    public function testRegistrarPolylangPostTypesFilter(): void
    {
        $registrar = new Registrar();
        $initialTypes = ['helmet' => 'helmet', 'brand' => 'brand'];
        $filtered = $registrar->filterPolylangPostTypes($initialTypes, false);

        $this->assertArrayHasKey('motorcycle', $filtered);
        $this->assertArrayHasKey('safety_standard', $filtered);
        $this->assertArrayHasKey('dealer', $filtered);
        $this->assertArrayHasKey('distributor', $filtered);
        $this->assertArrayHasKey('technology', $filtered);
    }

    public function testRegistrarPolylangTaxonomiesFilter(): void
    {
        $registrar = new Registrar();
        $initialTaxonomies = ['helmet_type' => 'helmet_type'];
        $filtered = $registrar->filterPolylangTaxonomies($initialTaxonomies, false);

        $this->assertArrayHasKey('riding_style', $filtered);
        $this->assertArrayHasKey('certification', $filtered);
        $this->assertArrayHasKey('head_shape', $filtered);
        $this->assertArrayHasKey('accessory_category', $filtered);
        $this->assertArrayHasKey('motorcycle_category', $filtered);
    }

    public function testCrossLinkServiceResolveLocalizedLinks(): void
    {
        $links = [
            ['post_id' => 123, 'url' => 'https://helmetsan.com/helmets/shoei-rf-1400/', 'reason' => 'same_brand'],
            ['post_id' => 0, 'url' => 'https://helmetsan.com/external/', 'reason' => 'custom'],
        ];

        $resolved = CrossLinkService::resolveLocalizedLinks($links, 'en');
        $this->assertCount(2, $resolved);
        $this->assertSame(123, $resolved[0]['post_id']);
        $this->assertSame('https://helmetsan.com/external/', $resolved[1]['url']);
    }

    public function testHelmetsanLocalizeUrl(): void
    {
        // Relative URLs
        $this->assertSame('https://helmetsan.com/helmets/', helmetsan_localize_url('/helmets/', 'en'));
        $this->assertSame('https://helmetsan.com/de/helmets/', helmetsan_localize_url('/helmets/', 'de'));
        $this->assertSame('https://helmetsan.com/zh/brands/', helmetsan_localize_url('https://helmetsan.com/brands/', 'zh'));

        // Query parameters preserved
        $this->assertSame('https://helmetsan.com/de/helmets/?brand=arai', helmetsan_localize_url('/helmets/?brand=arai', 'de'));

        // External URLs untouched
        $this->assertSame('https://example.com/other', helmetsan_localize_url('https://example.com/other', 'de'));
        $this->assertSame('#', helmetsan_localize_url('#', 'de'));
    }

    public function testHelmetsanMegaMenuFooterUrl(): void
    {
        $this->assertSame('https://helmetsan.com/helmets/', helmetsan_mega_menu_footer_url('helmet'));
    }

    public function testHelmetsanFilterNavMenuObjects(): void
    {
        $GLOBALS['wp_pll_current_lang'] = 'de';

        $item1 = (object) ['ID' => 1, 'url' => '/helmets/', 'title' => 'Helmets'];
        $item2 = (object) ['ID' => 2, 'url' => 'https://helmetsan.com/brands/', 'title' => 'Brands'];
        $item3 = (object) ['ID' => 3, 'url' => '#', 'title' => 'Dropdown'];

        $filtered = helmetsan_filter_nav_menu_objects([$item1, $item2, $item3], new \stdClass());

        $this->assertSame('https://helmetsan.com/de/helmets/', $filtered[0]->url);
        $this->assertSame('https://helmetsan.com/de/brands/', $filtered[1]->url);
        $this->assertSame('#', $filtered[2]->url);

        $GLOBALS['wp_pll_current_lang'] = 'en';
    }
}
