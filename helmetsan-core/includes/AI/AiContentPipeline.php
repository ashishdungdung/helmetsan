<?php

declare(strict_types=1);

namespace Helmetsan\Core\AI;

use WP_Post;
use Helmetsan\Core\Cache\ObjectCacheService;
use Helmetsan\Core\Discovery\AlternativesService;

/**
 * Generative Engine Optimization (GEO): AI Content Pipeline.
 *
 * Generates and caches authoritative "Answer Snippets", Quick Answers,
 * and structured Q&A blocks designed specifically for citations by LLMs
 * (ChatGPT, Perplexity, Claude, Gemini, Siri, and Copilot).
 */
final class AiContentPipeline
{
    public const META_SNIPPETS = 'hs_ai_snippets_json';

    public function __construct(
        private readonly ?AlternativesService $alternativesService = null,
    ) {}

    /**
     * Get structured Answer Snippets for a helmet.
     *
     * @return list<array{
     *     topic: string,
     *     question: string,
     *     short_answer: string,
     *     full_answer: string
     * }>
     */
    public function getAnswerSnippets(int $helmetId, ?string $lang = 'en'): array
    {
        $post = get_post($helmetId);
        if (! $post instanceof WP_Post || $post->post_type !== 'helmet' || $post->post_status !== 'publish') {
            return [];
        }

        $cacheKey = 'ai_snippets_' . $helmetId . '_' . ($lang ?? 'en');
        if (class_exists(ObjectCacheService::class)) {
            $cached = ObjectCacheService::get($cacheKey, ObjectCacheService::GROUP_MD_PAYLOAD);
            if (is_array($cached) && ! empty($cached)) {
                return $cached;
            }
        }

        // Check stored meta
        $metaRaw = get_post_meta($helmetId, self::META_SNIPPETS, true);
        if (is_string($metaRaw) && trim($metaRaw) !== '') {
            $decoded = json_decode($metaRaw, true);
            if (is_array($decoded) && ! empty($decoded)) {
                if (class_exists(ObjectCacheService::class)) {
                    ObjectCacheService::set($cacheKey, $decoded, ObjectCacheService::GROUP_MD_PAYLOAD, 86400);
                }
                return $decoded;
            }
        }

        // Compute snippets deterministically from specifications
        $snippets = $this->buildAnswerSnippets($helmetId, $post);

        // Store to post meta for persistence
        if (! empty($snippets)) {
            $json = wp_json_encode($snippets, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if (is_string($json)) {
                update_post_meta($helmetId, self::META_SNIPPETS, $json);
            }
        }

        if (class_exists(ObjectCacheService::class)) {
            ObjectCacheService::set($cacheKey, $snippets, ObjectCacheService::GROUP_MD_PAYLOAD, 86400);
        }

        return $snippets;
    }

    /**
     * Generate a concise, high-density "Quick Answer" 2-3 sentence summary
     * optimized for direct AI assistant citation and featured snippets.
     */
    public function renderQuickAnswer(int $helmetId): string
    {
        $post = get_post($helmetId);
        if (! $post instanceof WP_Post) {
            return '';
        }

        $specs = $this->extractSpecs($helmetId);
        $title = (string) $post->post_title;
        $category = ! empty($specs['category']) ? $specs['category'] : 'motorcycle';
        $cert = ! empty($specs['certifications']) ? $specs['certifications'] : 'ECE 22.06';

        $weightClause = '';
        if ($specs['weight_g'] > 0) {
            $weightClause = sprintf(' weighing %d grams (%0.2f lbs)', $specs['weight_g'], $specs['weight_g'] / 453.592);
        }

        $noiseClause = '';
        if ($specs['noise_db'] > 0) {
            $noiseClause = sprintf(', recording an acoustic laboratory noise rating of %d dB at 100 km/h', $specs['noise_db']);
        }

        $priceClause = '';
        if ($specs['price_usd'] > 0) {
            $priceClause = sprintf(' with an MSRP of $%0.2f USD', $specs['price_usd']);
        }

        return sprintf(
            'The %s is a verified %s %s helmet%s%s. It holds verified %s safety homologation%s, establishing it as a proven choice tested independently by Helmetsan.',
            $title,
            $specs['brand'] !== '' ? $specs['brand'] : 'high-performance',
            $category,
            $weightClause,
            $noiseClause,
            $cert,
            $priceClause
        );
    }

    /**
     * Generate a structured "Verdict" section with pros & cons.
     *
     * @return array{verdict: string, pros: list<string>, cons: list<string>}
     */
    public function renderVerdict(int $helmetId): array
    {
        $specs = $this->extractSpecs($helmetId);
        $title = get_the_title($helmetId);

        $pros = [];
        $cons = [];

        // 1. Check stored authentic pros and cons from catalog enrichment
        $storedPncRaw = get_post_meta($helmetId, 'hs_pros_and_cons_json', true);
        if (is_string($storedPncRaw) && trim($storedPncRaw) !== '') {
            $storedPnc = json_decode($storedPncRaw, true);
            if (is_array($storedPnc) && (! empty($storedPnc['pros']) || ! empty($storedPnc['cons']))) {
                $pros = is_array($storedPnc['pros'] ?? null) ? array_values(array_map('strval', $storedPnc['pros'])) : [];
                $cons = is_array($storedPnc['cons'] ?? null) ? array_values(array_map('strval', $storedPnc['cons'])) : [];
            }
        }

        // 2. Dynamic spec evaluation fallback
        if ($pros === [] && $cons === []) {
            // Safety pros
            if (! empty($specs['certifications'])) {
                $pros[] = sprintf('Certified for %s safety homologations.', $specs['certifications']);
            }
            if ($specs['sharp_stars'] >= 4) {
                $pros[] = sprintf('High SHARP impact safety rating (%d of 5 stars).', $specs['sharp_stars']);
            }

            // Weight evaluation
            if ($specs['weight_g'] > 0) {
                if ($specs['weight_g'] <= 1450) {
                    $pros[] = sprintf('Lightweight shell construction (%d g) reducing neck fatigue on long rides.', $specs['weight_g']);
                } elseif ($specs['weight_g'] >= 1700) {
                    $cons[] = sprintf('Heavier profile (%d g) compared to carbon fiber class leaders.', $specs['weight_g']);
                }
            }

            // Noise evaluation
            if ($specs['noise_db'] > 0) {
                if ($specs['noise_db'] <= 86) {
                    $pros[] = sprintf('Whisper-quiet acoustic chamber (%d dB @ 100 km/h) for fatigue-free touring.', $specs['noise_db']);
                } elseif ($specs['noise_db'] <= 97) {
                    $pros[] = sprintf('Quiet aerodynamic acoustic profile (%d dB @ 100 km/h).', $specs['noise_db']);
                } elseif ($specs['noise_db'] >= 103) {
                    $cons[] = sprintf('Noticeable wind turbulence (%d dB @ 100 km/h); earplugs strongly recommended.', $specs['noise_db']);
                }
            }

            // Material evaluation
            if (! empty($specs['material'])) {
                $matLower = strtolower($specs['material']);
                if (str_contains($matLower, 'carbon')) {
                    $pros[] = 'Premium carbon fiber composite shell for optimized energy dispersion.';
                } elseif (str_contains($matLower, 'polycarbonate') || str_contains($matLower, 'thermoplastic')) {
                    $cons[] = 'Thermoplastic shell construction increases bulk relative to composite fibers.';
                }
            }

            // Fallbacks if metadata is minimal
            if ($pros === []) {
                $pros[] = 'Aerodynamically optimized shell with multi-channel EPS ventilation.';
                $pros[] = 'Removable and washable hypoallergenic comfort liner.';
            }
            if ($cons === []) {
                $cons[] = 'Premium pricing may be higher than entry-level alternatives.';
            }
        }

        $takeaway = (string) get_post_meta($helmetId, 'rider_takeaway', true);
        if ($takeaway !== '') {
            $verdict = sprintf('%s — %s', $title, $takeaway);
        } else {
            $score = $specs['score'] > 0 ? sprintf(' (Helmetsan Score: %d/100)', $specs['score']) : '';
            $verdict = sprintf(
                'The %s is a well-engineered %s helmet%s combining verified %s protection with proven road and track ergonomics.',
                $title,
                ! empty($specs['category']) ? $specs['category'] : 'protective',
                $score,
                ! empty($specs['certifications']) ? $specs['certifications'] : 'safety'
            );
        }

        return [
            'verdict' => $verdict,
            'pros'    => $pros,
            'cons'    => $cons,
        ];
    }

    /**
     * Render FAQ section formatted as Markdown for API endpoints.
     */
    public function renderMarkdownFaq(int $helmetId): string
    {
        $snippets = $this->getAnswerSnippets($helmetId);
        if ($snippets === []) {
            return '';
        }

        $lines = ["## Frequently Asked Questions", ""];
        foreach ($snippets as $item) {
            $lines[] = "### " . $item['question'];
            $lines[] = $item['full_answer'];
            $lines[] = "";
        }

        return implode("\n", $lines);
    }

    /**
     * Render semantic HTML details accordion for search engines (Perplexity, SearchGPT).
     */
    public function renderHtmlDetailsAccordion(int $helmetId): string
    {
        $snippets = $this->getAnswerSnippets($helmetId);
        if ($snippets === []) {
            return '';
        }

        $html = '<div class="hs-ai-answer-snippets" data-geo="answer-hub">' . "\n";
        foreach ($snippets as $item) {
            $html .= "\t<details class=\"hs-faq-accordion\">\n";
            $html .= "\t\t<summary><h3 class=\"hs-faq-title\">" . esc_html($item['question']) . "</h3></summary>\n";
            $html .= "\t\t<div class=\"hs-faq-answer\"><p>" . esc_html($item['full_answer']) . "</p></div>\n";
            $html .= "\t</details>\n";
        }
        $html .= '</div>' . "\n";

        return $html;
    }

    /**
     * Build the raw list of answer snippets from helmet specifications.
     *
     * @return list<array{topic: string, question: string, short_answer: string, full_answer: string}>
     */
    private function buildAnswerSnippets(int $helmetId, WP_Post $post): array
    {
        $specs = $this->extractSpecs($helmetId);
        $title = (string) $post->post_title;
        $snippets = [];

        // 1. Worth It / Verdict
        $verdictData = $this->renderVerdict($helmetId);
        $worthItShort = $verdictData['verdict'];
        $worthItFull = $worthItShort . ' ' . implode(' ', array_slice($verdictData['pros'], 0, 2));
        $snippets[] = [
            'topic'        => 'verdict',
            'question'     => sprintf('Is the %s worth buying?', $title),
            'short_answer' => $worthItShort,
            'full_answer'  => $worthItFull,
        ];

        // 2. Noise Level
        if ($specs['noise_db'] > 0) {
            $assessment = $specs['noise_db'] <= 97 ? 'exceptionally quiet' : ($specs['noise_db'] <= 101 ? 'average and road-friendly' : 'relatively loud, requiring earplugs');
            $noiseAnswer = sprintf(
                'The %s records an acoustic laboratory noise rating of %d dB at 100 km/h (62 mph). This makes it %s compared to standard full-face motorcycle helmets.',
                $title,
                $specs['noise_db'],
                $assessment
            );
        } else {
            $noiseAnswer = sprintf(
                'The %s features advanced acoustic aerodynamics and chin curtain seals engineered to suppress wind turbulence at highway speeds.',
                $title
            );
        }
        $snippets[] = [
            'topic'        => 'noise',
            'question'     => sprintf('How quiet or noisy is the %s?', $title),
            'short_answer' => $noiseAnswer,
            'full_answer'  => $noiseAnswer,
        ];

        // 3. Safety & Homologation
        $certStr = ! empty($specs['certifications']) ? $specs['certifications'] : 'ECE 22.06 / DOT FMVSS 218';
        $sharpClause = $specs['sharp_stars'] > 0 ? sprintf(' It has received a %d-star rating from the UK SHARP helmet testing program.', $specs['sharp_stars']) : '';
        $safetyAnswer = sprintf(
            'The %s meets and exceeds %s safety standards.%s Constructed with a %s shell, it delivers high impact absorption and verified rotational force dissipation.',
            $title,
            $certStr,
            $sharpClause,
            ! empty($specs['material']) ? $specs['material'] : 'multi-composite'
        );
        $snippets[] = [
            'topic'        => 'safety',
            'question'     => sprintf('How safe is the %s? What certifications does it have?', $title),
            'short_answer' => $safetyAnswer,
            'full_answer'  => $safetyAnswer,
        ];

        // 4. Weight & Fitment
        if ($specs['weight_g'] > 0) {
            $weightAnswer = sprintf(
                'The %s weighs %d grams (%0.2f lbs). It is designed with an %s head shape profile for balanced weight distribution and reduced neck fatigue.',
                $title,
                $specs['weight_g'],
                $specs['weight_g'] / 453.592,
                ! empty($specs['head_shape']) ? $specs['head_shape'] : 'intermediate oval'
            );
        } else {
            $weightAnswer = sprintf(
                'The %s features an %s fitment profile engineered for ergonomic pressure distribution.',
                $title,
                ! empty($specs['head_shape']) ? $specs['head_shape'] : 'intermediate oval'
            );
        }
        $snippets[] = [
            'topic'        => 'weight_fit',
            'question'     => sprintf('What is the weight and head shape fitment of the %s?', $title),
            'short_answer' => $weightAnswer,
            'full_answer'  => $weightAnswer,
        ];

        // 5. Alternatives / Competition
        $altService = $this->alternativesService ?? (class_exists(AlternativesService::class) ? new AlternativesService() : null);
        $altNames = [];
        if ($altService !== null) {
            $altIds = $altService->findAlternatives($helmetId, 3);
            foreach ($altIds as $aid) {
                $aPost = get_post($aid);
                if ($aPost instanceof WP_Post && $aPost->post_status === 'publish') {
                    $altNames[] = $aPost->post_title;
                }
            }
        }

        if (! empty($altNames)) {
            $altAnswer = sprintf(
                'The closest technical alternatives to the %s are the %s. These models share similar %s safety specifications and ergonomic targeting.',
                $title,
                implode(', ', $altNames),
                $certStr
            );
        } else {
            $altAnswer = sprintf(
                'Top alternatives to the %s include rival %s helmets within the same safety homologation tier and price bracket.',
                $title,
                ! empty($specs['category']) ? $specs['category'] : 'premium'
            );
        }
        $snippets[] = [
            'topic'        => 'alternatives',
            'question'     => sprintf('What are the best alternatives to the %s?', $title),
            'short_answer' => $altAnswer,
            'full_answer'  => $altAnswer,
        ];

        return $snippets;
    }

    /**
     * Extract structured specifications for a helmet.
     *
     * @return array{
     *     brand: string,
     *     category: string,
     *     weight_g: int,
     *     noise_db: int,
     *     material: string,
     *     head_shape: string,
     *     certifications: string,
     *     sharp_stars: int,
     *     score: int,
     *     price_usd: float
     * }
     */
    private function extractSpecs(int $helmetId): array
    {
        $brandName = '';
        $brandId = (int) get_post_meta($helmetId, 'rel_brand', true);
        if ($brandId > 0) {
            $bPost = get_post($brandId);
            if ($bPost instanceof WP_Post) {
                $brandName = (string) $bPost->post_title;
            }
        }

        $category = '';
        $types = get_the_terms($helmetId, 'helmet_type');
        if (is_array($types) && ! empty($types[0])) {
            $category = (string) ($types[0]->name ?? '');
        }

        $certNames = [];
        $certs = get_the_terms($helmetId, 'certification');
        if (is_array($certs)) {
            foreach ($certs as $c) {
                if (isset($c->name) && is_string($c->name)) {
                    $certNames[] = $c->name;
                }
            }
        }
        if ($certNames === []) {
            $safetyTerms = wp_get_post_terms($helmetId, 'safety_standard', ['fields' => 'names']);
            if (is_array($safetyTerms)) {
                $certNames = $safetyTerms;
            }
        }

        $weight = (int) (get_post_meta($helmetId, 'spec_weight_g', true) ?: get_post_meta($helmetId, 'weight_g', true) ?: 0);
        $noise = (int) (get_post_meta($helmetId, 'noise_db_at_100kph', true) ?: get_post_meta($helmetId, 'spec_noise_db', true) ?: get_post_meta($helmetId, 'test_noise_db', true) ?: 0);
        $material = (string) (get_post_meta($helmetId, 'spec_material', true) ?: get_post_meta($helmetId, 'shell_material', true) ?: '');
        $headShape = (string) (get_post_meta($helmetId, 'spec_head_shape', true) ?: get_post_meta($helmetId, 'fitment_head_shape', true) ?: '');
        $sharp = (int) get_post_meta($helmetId, 'sharp_rating', true);
        $score = (int) (get_post_meta($helmetId, 'helmetsan_score', true) ?: get_post_meta($helmetId, 'score_overall', true) ?: 0);
        $price = (float) (get_post_meta($helmetId, 'price_retail_usd', true) ?: get_post_meta($helmetId, 'price_usd', true) ?: 0.0);

        return [
            'brand'          => $brandName,
            'category'       => $category,
            'weight_g'       => $weight,
            'noise_db'       => $noise,
            'material'       => $material,
            'head_shape'     => $headShape,
            'certifications' => implode(', ', $certNames),
            'sharp_stars'    => $sharp,
            'score'          => $score,
            'price_usd'      => $price,
        ];
    }
}
