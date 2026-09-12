<?php

declare(strict_types=1);

namespace Tests\Unit\AI;

use Helmetsan\Core\AI\AiContentPipeline;
use Helmetsan\Core\Discovery\AlternativesService;
use PHPUnit\Framework\TestCase;
use WP_Post;

final class AiContentPipelineTest extends TestCase
{
    private AiContentPipeline $pipeline;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pipeline = new AiContentPipeline();
        $GLOBALS['wp_mock_posts'] = [];
        $GLOBALS['wp_mock_query_posts'] = [];
        $GLOBALS['wp_post_meta'] = [];
        $GLOBALS['wp_object_cache'] = [];
    }

    public function testRenderQuickAnswerGeneratesAuthoritativeSummary(): void
    {
        $helmet = new WP_Post();
        $helmet->ID = 501;
        $helmet->post_type = 'helmet';
        $helmet->post_title = 'Shoei X-Fifteen';
        $helmet->post_status = 'publish';

        $GLOBALS['wp_mock_posts'][501] = $helmet;
        $GLOBALS['wp_post_meta'][501]['spec_weight_g'] = 1395;
        $GLOBALS['wp_post_meta'][501]['noise_db_at_100kph'] = 98;
        $GLOBALS['wp_post_meta'][501]['price_retail_usd'] = 899.99;

        $answer = $this->pipeline->renderQuickAnswer(501);

        $this->assertStringContainsString('Shoei X-Fifteen', $answer);
        $this->assertStringContainsString('1395 grams', $answer);
        $this->assertStringContainsString('98 dB', $answer);
        $this->assertStringContainsString('899.99', $answer);
        $this->assertStringContainsString('Helmetsan', $answer);
    }

    public function testRenderVerdictEvaluatesProsAndCons(): void
    {
        $helmet = new WP_Post();
        $helmet->ID = 502;
        $helmet->post_type = 'helmet';
        $helmet->post_title = 'AGV Pista GP RR';
        $helmet->post_status = 'publish';

        $GLOBALS['wp_mock_posts'][502] = $helmet;
        $GLOBALS['wp_post_meta'][502]['spec_weight_g'] = 1450;
        $GLOBALS['wp_post_meta'][502]['noise_db_at_100kph'] = 104;
        $GLOBALS['wp_post_meta'][502]['spec_material'] = '100% Carbon Fiber';
        $GLOBALS['wp_post_meta'][502]['sharp_rating'] = 5;

        $verdict = $this->pipeline->renderVerdict(502);

        $this->assertNotEmpty($verdict['verdict']);
        $this->assertNotEmpty($verdict['pros']);
        $this->assertNotEmpty($verdict['cons']);

        $prosStr = implode(' ', $verdict['pros']);
        $consStr = implode(' ', $verdict['cons']);

        $this->assertStringContainsString('carbon fiber', strtolower($prosStr));
        $this->assertStringContainsString('104 dB', $consStr);
    }

    public function testGetAnswerSnippetsReturnsStructuredFaq(): void
    {
        $helmet = new WP_Post();
        $helmet->ID = 503;
        $helmet->post_type = 'helmet';
        $helmet->post_title = 'Arai Corsair-X';
        $helmet->post_status = 'publish';

        $GLOBALS['wp_mock_posts'][503] = $helmet;
        $GLOBALS['wp_post_meta'][503]['spec_weight_g'] = 1590;
        $GLOBALS['wp_post_meta'][503]['noise_db_at_100kph'] = 99;

        $snippets = $this->pipeline->getAnswerSnippets(503);

        $this->assertCount(5, $snippets);
        $topics = array_column($snippets, 'topic');
        $this->assertContains('verdict', $topics);
        $this->assertContains('noise', $topics);
        $this->assertContains('safety', $topics);
        $this->assertContains('weight_fit', $topics);
        $this->assertContains('alternatives', $topics);

        $this->assertStringContainsString('Is the Arai Corsair-X worth buying?', $snippets[0]['question']);
    }

    public function testRenderHtmlDetailsAccordionOutputsSemanticMarkup(): void
    {
        $helmet = new WP_Post();
        $helmet->ID = 504;
        $helmet->post_type = 'helmet';
        $helmet->post_title = 'HJC RPHA 1N';
        $helmet->post_status = 'publish';

        $GLOBALS['wp_mock_posts'][504] = $helmet;

        $html = $this->pipeline->renderHtmlDetailsAccordion(504);

        $this->assertStringContainsString('<details class="hs-faq-accordion">', $html);
        $this->assertStringContainsString('<summary><h3 class="hs-faq-title">', $html);
        $this->assertStringContainsString('data-geo="answer-hub"', $html);
    }

    public function testRenderMarkdownFaqOutputsFormattedQuestions(): void
    {
        $helmet = new WP_Post();
        $helmet->ID = 505;
        $helmet->post_type = 'helmet';
        $helmet->post_title = 'Shark Aeron GP';
        $helmet->post_status = 'publish';

        $GLOBALS['wp_mock_posts'][505] = $helmet;

        $md = $this->pipeline->renderMarkdownFaq(505);

        $this->assertStringContainsString('## Frequently Asked Questions', $md);
        $this->assertStringContainsString('### Is the Shark Aeron GP worth buying?', $md);
    }

    public function testRenderVerdictPrioritizesEnrichedProsConsAndTakeaways(): void
    {
        $helmet = new WP_Post();
        $helmet->ID = 506;
        $helmet->post_type = 'helmet';
        $helmet->post_title = 'Shoei Neotec 3';
        $helmet->post_status = 'publish';

        $GLOBALS['wp_mock_posts'][506] = $helmet;
        $GLOBALS['wp_post_meta'][506]['hs_pros_and_cons_json'] = json_encode([
            'pros' => [
                'Single-button flip-up chin bar mechanism provides effortless touring convenience',
                'Dual P/J homologation certified for legal riding in open and closed configurations'
            ],
            'cons' => [
                'Chin bar hinge hardware adds weight compared to single-piece full-face helmets'
            ]
        ]);
        $GLOBALS['wp_post_meta'][506]['rider_takeaway'] = 'A premier modular touring helmet delivering certified dual P/J homologation and refined high-speed aero.';

        $verdict = $this->pipeline->renderVerdict(506);

        $this->assertCount(2, $verdict['pros']);
        $this->assertCount(1, $verdict['cons']);
        $this->assertStringContainsString('flip-up chin bar', $verdict['pros'][0]);
        $this->assertStringContainsString('hinge hardware', $verdict['cons'][0]);
        $this->assertStringContainsString('Shoei Neotec 3 — A premier modular touring helmet', $verdict['verdict']);
    }
}
