<?php

declare(strict_types=1);

namespace Tests\Unit\Seo;

use Helmetsan\Core\Seo\AutoSeoObserver;
use Helmetsan\Core\Seo\YoastSeoSeeder;
use PHPUnit\Framework\TestCase;
use WP_Post;
use WP_Query;

final class AutoSeoObserverTest extends TestCase
{
    private AutoSeoObserver $observer;
    private YoastSeoSeeder $seeder;

    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['wp_mock_posts'] = [];
        $GLOBALS['wp_post_meta'] = [];
        $GLOBALS['wp_mock_singular_post_type'] = null;
        $GLOBALS['wp_mock_queried_object_id'] = 0;
        $GLOBALS['wp_mock_queried_object'] = null;
        $GLOBALS['wp_mock_permalinks'] = [];
        $GLOBALS['wp_pll_current_lang'] = 'en';
        $GLOBALS['wp_pll_translations'] = [];
        $GLOBALS['wp_query_vars'] = [];
        $_GET = [];
        $_SERVER['REQUEST_URI'] = '';

        $this->seeder = new YoastSeoSeeder();
        $this->observer = new AutoSeoObserver($this->seeder);
    }

    public function testFilterHelmetCanonicalUrlReturnsDefaultWhenNotHelmet(): void
    {
        $GLOBALS['wp_mock_singular_post_type'] = 'post';
        $canonical = $this->observer->filterHelmetCanonicalUrl('https://helmetsan.com/sample-post/');
        $this->assertSame('https://helmetsan.com/sample-post/', $canonical);
    }

    public function testFilterHelmetCanonicalUrlCanonicalizesChildVariantToParent(): void
    {
        $parent = new WP_Post();
        $parent->ID = 100;
        $parent->post_type = 'helmet';
        $parent->post_name = 'shoei-rf-1400';

        $child = new WP_Post();
        $child->ID = 101;
        $child->post_type = 'helmet';
        $child->post_name = 'shoei-rf-1400-matte-black';
        $child->post_parent = 100;

        $GLOBALS['wp_mock_posts'][100] = $parent;
        $GLOBALS['wp_mock_posts'][101] = $child;
        $GLOBALS['wp_mock_singular_post_type'] = 'helmet';
        $GLOBALS['wp_mock_queried_object_id'] = 101;
        $GLOBALS['wp_mock_permalinks'][100] = 'https://helmetsan.com/helmets/shoei-rf-1400/';

        $canonical = $this->observer->filterHelmetCanonicalUrl('https://helmetsan.com/helmets/shoei-rf-1400-matte-black/');
        $this->assertSame('https://helmetsan.com/helmets/shoei-rf-1400/', $canonical);
    }

    public function testFilterHelmetCanonicalUrlPreservesPolylangLanguagePrefix(): void
    {
        $enParent = new WP_Post();
        $enParent->ID = 100;
        $enParent->post_type = 'helmet';
        $enParent->post_name = 'shoei-rf-1400';

        $esParent = new WP_Post();
        $esParent->ID = 200;
        $esParent->post_type = 'helmet';
        $esParent->post_name = 'shoei-rf-1400';

        $esChild = new WP_Post();
        $esChild->ID = 201;
        $esChild->post_type = 'helmet';
        $esChild->post_name = 'shoei-rf-1400-negro-mate';
        $esChild->post_parent = 100; // child points to base parent

        $GLOBALS['wp_mock_posts'][100] = $enParent;
        $GLOBALS['wp_mock_posts'][200] = $esParent;
        $GLOBALS['wp_mock_posts'][201] = $esChild;
        $GLOBALS['wp_mock_singular_post_type'] = 'helmet';
        $GLOBALS['wp_mock_queried_object_id'] = 201;

        $GLOBALS['wp_mock_permalinks'][100] = 'https://helmetsan.com/helmets/shoei-rf-1400/';
        $GLOBALS['wp_mock_permalinks'][200] = 'https://helmetsan.com/es/helmets/shoei-rf-1400/';

        // Polylang Spanish locale active, parent 100 translates to 200 in 'es'
        $GLOBALS['wp_pll_current_lang'] = 'es';
        $GLOBALS['wp_pll_translations'][100]['es'] = 200;

        $canonical = $this->observer->filterHelmetCanonicalUrl('https://helmetsan.com/es/helmets/shoei-rf-1400-negro-mate/');
        $this->assertSame('https://helmetsan.com/es/helmets/shoei-rf-1400/', $canonical);
    }

    public function testFilterHelmetCanonicalUrlFallsBackToBaseParentIfNoTranslation(): void
    {
        $enParent = new WP_Post();
        $enParent->ID = 100;
        $enParent->post_type = 'helmet';

        $deChild = new WP_Post();
        $deChild->ID = 301;
        $deChild->post_type = 'helmet';
        $deChild->post_parent = 100;

        $GLOBALS['wp_mock_posts'][100] = $enParent;
        $GLOBALS['wp_mock_posts'][301] = $deChild;
        $GLOBALS['wp_mock_singular_post_type'] = 'helmet';
        $GLOBALS['wp_mock_queried_object_id'] = 301;

        $GLOBALS['wp_mock_permalinks'][100] = 'https://helmetsan.com/helmets/shoei-rf-1400/';

        // German locale, but no German translation exists for parent 100
        $GLOBALS['wp_pll_current_lang'] = 'de';
        $GLOBALS['wp_pll_translations'][100] = [];

        $canonical = $this->observer->filterHelmetCanonicalUrl('https://helmetsan.com/de/helmets/shoei-rf-1400-matt-schwarz/');
        $this->assertSame('https://helmetsan.com/helmets/shoei-rf-1400/', $canonical);
    }

    public function testIsQualityMotorcycleApprovesBikeWithMakeAndModelEvenIfEngineCcZero(): void
    {
        $moto = new WP_Post();
        $moto->ID = 501;
        $moto->post_type = 'motorcycle';

        $GLOBALS['wp_post_meta'][501] = [
            'motorcycle_make'  => 'Honda',
            'motorcycle_model' => 'Hornet 2.0',
            'engine_cc'        => 0,
            'bike_segment'     => '',
        ];

        $this->assertTrue(AutoSeoObserver::isQualityMotorcycle($moto));
    }

    public function testIsQualityMotorcycleApprovesBikeWithBrandFallbackAndModel(): void
    {
        $moto = new WP_Post();
        $moto->ID = 502;
        $moto->post_type = 'motorcycle';

        $GLOBALS['wp_post_meta'][502] = [
            'brand'            => 'TVS',
            'motorcycle_model' => 'Apache RR 310',
            'engine_cc'        => '',
        ];

        $this->assertTrue(AutoSeoObserver::isQualityMotorcycle($moto));
    }

    public function testIsQualityMotorcycleApprovesBikeWithMakeAndEngineDisplacement(): void
    {
        $moto = new WP_Post();
        $moto->ID = 503;
        $moto->post_type = 'motorcycle';

        $GLOBALS['wp_post_meta'][503] = [
            'motorcycle_make' => 'Yamaha',
            'engine_cc'       => 155,
        ];

        $this->assertTrue(AutoSeoObserver::isQualityMotorcycle($moto));
    }

    public function testIsQualityMotorcycleApprovesBikeWithMakeAndSegment(): void
    {
        $moto = new WP_Post();
        $moto->ID = 504;
        $moto->post_type = 'motorcycle';

        $GLOBALS['wp_post_meta'][504] = [
            'motorcycle_make' => 'Ducati',
            'bike_segment'    => 'Superbike',
        ];

        $this->assertTrue(AutoSeoObserver::isQualityMotorcycle($moto));
    }

    public function testIsQualityMotorcycleRejectsThinMotorcycle(): void
    {
        $moto = new WP_Post();
        $moto->ID = 505;
        $moto->post_type = 'motorcycle';

        // Missing model, missing engine, missing segment
        $GLOBALS['wp_post_meta'][505] = [
            'motorcycle_make' => 'Generic',
        ];

        $this->assertFalse(AutoSeoObserver::isQualityMotorcycle($moto));
    }

    public function testIsQualityMotorcycleRejectsWhenMakeMissing(): void
    {
        $moto = new WP_Post();
        $moto->ID = 506;
        $moto->post_type = 'motorcycle';

        $GLOBALS['wp_post_meta'][506] = [
            'motorcycle_model' => 'Mystery Bike',
            'engine_cc'        => 650,
        ];

        $this->assertFalse(AutoSeoObserver::isQualityMotorcycle($moto));
    }

    public function testHandleQualityAndNoindexGovernanceDoesNotNoindexQualityMotorcycleWithZeroCc(): void
    {
        global $wp_query;
        $wp_query = new WP_Query();

        $moto = new WP_Post();
        $moto->ID = 601;
        $moto->post_type = 'motorcycle';

        $GLOBALS['wp_post_meta'][601] = [
            'motorcycle_make'  => 'Zero',
            'motorcycle_model' => 'SR/F',
            'engine_cc'        => 0, // Electric bike with zero displacement
        ];

        $GLOBALS['wp_mock_posts'][601] = $moto;
        $GLOBALS['wp_mock_singular_post_type'] = 'motorcycle';
        $GLOBALS['wp_mock_queried_object_id'] = 601;
        $GLOBALS['wp_mock_queried_object'] = $moto;

        // Run governance redirect handler
        $this->observer->handleQualityAndNoindexGovernance();

        // In wp_robots filter, noindex should NOT have been registered
        $robots = apply_filters('wp_robots', ['follow' => true]);
        $this->assertArrayNotHasKey('noindex', $robots);
    }
}
