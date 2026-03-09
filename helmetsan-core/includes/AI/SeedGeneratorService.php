<?php

declare(strict_types=1);

namespace Helmetsan\Core\AI;

/**
 * Generates helmet catalog data (master format) using the AI module.
 * Output can be written to a file and merged into data/helmets/master.json
 * or used as --source-json with create_helmets_seed.php (after merge).
 *
 * Helmets only. Accessories use a different schema and ingest path (per-file JSON
 * under data/accessories); accessory generation would require a separate command or service.
 *
 * Uses the same provider credentials as fill-missing and SEO seed.
 */
final class SeedGeneratorService
{
    /** @var list<string> Allowed brand names for generation (reduces hallucination). 100+ helmet brands. */
    private const ALLOWED_BRANDS = [
        '100%', '509', '6D Helmets', 'AFX', 'AGV', 'Acerbis', 'Adiva', 'Airoh', 'Alpinestars', 'Answer Racing', 'Apex',
        'Arai', 'Arashi', 'Axxis', 'Axor', 'Bell', 'Bering', 'BILT', 'Biltwell', 'Bilmola', 'Blauer', 'BMW', 'Bohn',
        'Brett', 'Brigade', 'Caberg', 'Cairbull', 'Cardo Systems', 'CKX', 'Dainese', 'DMD', 'Dragon', 'Eagle', 'EVS',
        'Eudoxie', 'Fasthouse', 'Fitwell', 'Fly Racing', 'Fox Racing', 'Freedconn', 'Furygan', 'FXR', 'GMAX', 'Gear',
        'Givi', 'Grex', 'Hedon', 'Held', 'Highway 21', 'HJC', 'Icon', 'ILM', 'IXS', 'Jett', 'JFG', 'Kabuto',
        'Kali Protectives', 'KBC', 'Kini Red Bull', 'Klim', 'Knox', 'LaZer', 'Leatt', 'LS2', 'Macna', 'Marushin',
        'MT Helmets', 'Nexx', 'Nitro', 'Nolan', "O'Neal", 'Ogio', 'One Industries', 'Orina', 'Oxtar', 'Pando', 'Pilot',
        'Premier', 'Racer', 'Rjays', 'RST', 'Ruby', 'Ruroc', 'Schuberth', 'Scorpion EXO', 'Scoyco', 'Sedici', 'Segura',
        'Sena', 'Shark', 'Shoei', 'Simpson', 'Skid Lid', 'SMK', 'Speed and Strength', 'SparX', 'Spada', 'Spyke',
        'Stadium', 'Steelbird', 'Studds', 'Suomy', 'TCX', 'Thor', 'Torc', 'Troy Lee Designs', 'Tucano', 'Uclear',
        'Urge', 'Vega', 'Vemar', 'Venum', 'Vespa', 'Voss', 'Vozz', 'Wicked', 'Woolridge', 'WRS', 'X-Lite', 'Yamaha',
        'Yoko', 'Z1R', 'Zamp', 'Zandona', 'Zeus',
    ];

    private const COLOR_FAMILIES = ['Black', 'White', 'Red', 'Blue', 'Green', 'Yellow', 'Orange', 'Grey', 'Multi', 'Carbon'];

    private const HELMET_TYPES = ['Full Face', 'Modular', 'Open Face', 'Half', 'Dirt/MX', 'Adventure/Dual Sport', 'Touring', 'Track/Race'];

    public function __construct(
        private readonly AiService $aiService
    ) {
    }

    /**
     * Generate helmet models in master format (brand => model => spec with colorways).
     * Duplicates (brand+model already in $existingBrandModels) are excluded and reported in errors.
     *
     * @param int $count Number of models to generate
     * @param list<string> $brands Brand names (must be in ALLOWED_BRANDS or pass empty to let model choose from list)
     * @param string|null $providerId Optional provider (e.g. groq, openai)
     * @param array<string, list<string>> $existingBrandModels Existing catalog: brand name => list of model names (to avoid duplicates)
     * @return array{success: bool, data: array<string, mixed>, models_generated: int, errors: list<string>}
     */
    public function generate(int $count, array $brands, ?string $providerId = null, array $existingBrandModels = []): array
    {
        $errors = [];

        if (! $this->aiService->hasAnyConfiguredProvider()) {
            return [
                'success' => false,
                'data' => [],
                'models_generated' => 0,
                'errors' => ['No AI provider is configured. Configure at least one under Helmetsan → AI.'],
            ];
        }

        $allowed = $brands !== [] ? $this->filterAllowedBrands($brands) : self::ALLOWED_BRANDS;
        if ($brands !== [] && count($allowed) === 0) {
            return [
                'success' => false,
                'data' => [],
                'models_generated' => 0,
                'errors' => ['None of the requested brands are in the allowed list. Allowed: ' . implode(', ', self::ALLOWED_BRANDS)],
            ];
        }

        $prompt = $this->buildPrompt($count, $allowed, $existingBrandModels);

        $options = ['max_tokens' => 4096, 'temperature' => 0.4];
        $response = $this->aiService->generate($prompt, $count, $providerId, $options);

        if ($response === null || trim($response) === '') {
            return [
                'success' => false,
                'data' => [],
                'models_generated' => 0,
                'errors' => ['AI returned no response. Check API key and model.'],
            ];
        }

        $parsed = $this->parseJsonFromResponse($response);
        if ($parsed === null) {
            return [
                'success' => false,
                'data' => [],
                'models_generated' => 0,
                'errors' => ['Could not parse valid JSON from AI response.'],
            ];
        }

        $validated = $this->validateAndSanitizeMaster($parsed, $errors);
        $validated = $this->filterDuplicates($validated, $existingBrandModels, $errors);
        $modelsGenerated = $this->countModels($validated);

        return [
            'success' => $modelsGenerated > 0,
            'data' => $validated,
            'models_generated' => $modelsGenerated,
            'errors' => $errors,
        ];
    }

    /**
     * @return list<string>
     */
    private function filterAllowedBrands(array $brands): array
    {
        $normalized = array_map('trim', $brands);
        $allowed = [];
        foreach (self::ALLOWED_BRANDS as $b) {
            foreach ($normalized as $req) {
                if (strcasecmp($req, $b) === 0) {
                    $allowed[] = $b;
                    break;
                }
            }
        }
        return $allowed;
    }

    /**
     * @param array<string, list<string>> $existingBrandModels brand => [model names]
     */
    private function buildPrompt(int $count, array $brands, array $existingBrandModels = []): string
    {
        $brandList = implode(', ', $brands);
        $colorList = implode(', ', self::COLOR_FAMILIES);
        $typeList = implode(', ', self::HELMET_TYPES);

        $existingBlock = '';
        if ($existingBrandModels !== []) {
            $lines = [];
            foreach ($existingBrandModels as $brand => $models) {
                foreach ($models as $model) {
                    $lines[] = $brand . ' / ' . $model;
                }
            }
            $existingBlock = "\n- Do NOT generate any of these (already in catalog): " . implode(', ', array_slice($lines, 0, 100))
                . (count($lines) > 100 ? ' (and ' . (count($lines) - 100) . ' more)' : '') . ".\n";
        }

        return <<<PROMPT
You are generating motorcycle helmet catalog data. Output ONLY a single valid JSON object, no markdown or explanation.

Rules:
- Generate exactly {$count} helmet model(s). Use only these brands: {$brandList}. Distribute across brands if multiple.
{$existingBlock}- Each brand key maps to an object of model names, each model has: type, price, cert, shape, weight, mat, desc, colorways.
- type: one of {$typeList}.
- price: number in USD (e.g. 299.99 to 899.99).
- cert: array of strings, e.g. ["DOT", "Snell M2020"] or ["DOT", "ECE 22.06"].
- shape: e.g. "Intermediate Oval", "Round Oval", "Long Oval".
- weight: integer grams (1200-1800).
- mat: e.g. "Polycarbonate", "Fiberglass", "AIM", "Carbon Fiber".
- desc: 2-3 sentence product description.
- colorways: array of 2-5 items. Each: name, family, finish, sku, price_adj. family one of: {$colorList}. finish: "matte" or "gloss". price_adj: 0 or positive number. sku format: BRAND-MODEL-COLOR (e.g. HJC-R11-MBK, use 2-4 letter brand/model codes).

Example structure (one model only for brevity):
{"HJC": {"RPHA 11": {"type": "Full Face", "price": 529.99, "cert": ["DOT", "ECE 22.05"], "shape": "Intermediate Oval", "weight": 1300, "mat": "Carbon Fiber", "desc": "Premium track-focused full face.", "colorways": [{"name": "Matte Black", "family": "Black", "finish": "matte", "sku": "HJC-R11-MBK", "price_adj": 0}, {"name": "Gloss White", "family": "White", "finish": "gloss", "sku": "HJC-R11-WHT", "price_adj": 0}]}}}

Output the JSON object now with {$count} model(s):
PROMPT;
    }

    private function parseJsonFromResponse(string $response): ?array
    {
        $text = trim($response);
        if (preg_match('/```(?:json)?\s*([\s\S]*?)```/', $text, $m)) {
            $text = trim($m[1]);
        }
        $decoded = json_decode($text, true);
        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Validate master structure and return only valid brand => model => spec. Pushes warnings to $errors.
     * @param array<string, mixed> $data
     * @param list<string> $errors
     * @return array<string, mixed>
     */
    private function validateAndSanitizeMaster(array $data, array &$errors): array
    {
        $out = [];
        foreach ($data as $brand => $models) {
            if ($brand === '_comment' || ! is_array($models)) {
                continue;
            }
            foreach ($models as $modelName => $spec) {
                if (! is_array($spec) || empty($spec['colorways']) || ! is_array($spec['colorways'])) {
                    $errors[] = "Skipped {$brand} / {$modelName}: missing or invalid colorways.";
                    continue;
                }
                $required = ['type', 'price', 'cert', 'shape', 'weight', 'mat', 'desc'];
                foreach ($required as $key) {
                    if (! isset($spec[$key])) {
                        $errors[] = "Skipped {$brand} / {$modelName}: missing {$key}.";
                        continue 2;
                    }
                }
                $sanitized = [
                    'type' => (string) $spec['type'],
                    'price' => (float) $spec['price'],
                    'cert' => is_array($spec['cert']) ? array_map('strval', $spec['cert']) : [(string) $spec['cert']],
                    'shape' => (string) $spec['shape'],
                    'weight' => (int) $spec['weight'],
                    'mat' => (string) $spec['mat'],
                    'desc' => (string) $spec['desc'],
                    'colorways' => [],
                ];
                foreach ($spec['colorways'] as $cw) {
                    if (! is_array($cw) || empty($cw['name']) || empty($cw['sku'])) {
                        continue;
                    }
                    $sanitized['colorways'][] = [
                        'name' => (string) $cw['name'],
                        'family' => isset($cw['family']) ? (string) $cw['family'] : 'Multi',
                        'finish' => isset($cw['finish']) ? (string) $cw['finish'] : 'gloss',
                        'sku' => (string) $cw['sku'],
                        'price_adj' => isset($cw['price_adj']) ? (int) $cw['price_adj'] : 0,
                    ];
                }
                if ($sanitized['colorways'] === []) {
                    $errors[] = "Skipped {$brand} / {$modelName}: no valid colorway entries.";
                    continue;
                }
                if (! isset($out[$brand])) {
                    $out[$brand] = [];
                }
                $out[$brand][$modelName] = $sanitized;
            }
        }
        return $out;
    }

    /**
     * Remove (brand, model) that already exist in catalog. Case-insensitive comparison.
     * @param array<string, mixed> $validated
     * @param array<string, list<string>> $existingBrandModels
     * @param list<string> $errors
     * @return array<string, mixed>
     */
    private function filterDuplicates(array $validated, array $existingBrandModels, array &$errors): array
    {
        if ($existingBrandModels === []) {
            return $validated;
        }

        $existingNormalized = [];
        foreach ($existingBrandModels as $brand => $models) {
            $key = $this->normalizeKey($brand);
            foreach ($models as $model) {
                $existingNormalized[$key . '|' . $this->normalizeKey($model)] = true;
            }
        }

        $out = [];
        foreach ($validated as $brand => $models) {
            if (! is_array($models)) {
                continue;
            }
            foreach ($models as $modelName => $spec) {
                $k = $this->normalizeKey($brand) . '|' . $this->normalizeKey($modelName);
                if (isset($existingNormalized[$k])) {
                    $errors[] = "Skipped duplicate: {$brand} / {$modelName} (already in catalog).";
                    continue;
                }
                if (! isset($out[$brand])) {
                    $out[$brand] = [];
                }
                $out[$brand][$modelName] = $spec;
            }
        }
        return $out;
    }

    private function normalizeKey(string $s): string
    {
        return strtolower(trim(preg_replace('/\s+/', ' ', $s)));
    }

    /**
     * @param array<string, mixed> $master
     */
    private function countModels(array $master): int
    {
        $n = 0;
        foreach ($master as $models) {
            if (is_array($models)) {
                $n += count($models);
            }
        }
        return $n;
    }

    /**
     * Return the list of allowed brands (for CLI help).
     * @return list<string>
     */
    public static function getAllowedBrands(): array
    {
        return self::ALLOWED_BRANDS;
    }
}
