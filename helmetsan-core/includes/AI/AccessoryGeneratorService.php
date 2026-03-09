<?php

declare(strict_types=1);

namespace Helmetsan\Core\AI;

use Helmetsan\Core\Validation\Validator;

/**
 * Generates accessory catalog data (entity, id, title, type, parent_category, etc.)
 * for ingest via wp helmetsan ingest --path=data/accessories.
 *
 * Uses the same provider credentials as fill-missing and SEO seed.
 * When Validator is provided, output is validated against accessory schema before return.
 */
final class AccessoryGeneratorService
{
    /** @var list<string> Allowed accessory_category-style names for type/parent_category. */
    private const ALLOWED_CATEGORIES = [
        'Visors & Shields', 'Face Shields', 'Pinlock Inserts', 'Tear-Offs', 'Goggles', 'Replacement Lenses',
        'Anti-Fog Solutions', 'Sun Visors', 'Communications', 'Bluetooth Headsets', 'Mesh Intercoms', 'Helmet Cameras',
        'Audio Kits', 'GPS Navigation', 'Smart Helmet Add-ons', 'Maintenance & Care', 'Electronics', 'Inner Liners',
        'Cheek Pads', 'Liners', 'Helmet Cleaners', 'Visor Cleaners', 'Helmet Bags', 'Balaclavas', 'Breath Guards',
        'Breath Boxes', 'Peak Visors', 'Replacement Vents', 'Pivot Kits', 'Chin Curtains', 'Reflective Stickers',
    ];

    public function __construct(
        private readonly AiService $aiService,
        private readonly ?Validator $validator = null
    ) {
    }

    /**
     * Generate accessory items. Duplicates (by normalized title) are excluded.
     *
     * @param int $count Number of accessories to generate
     * @param list<string> $categories Restrict to these categories (empty = any from ALLOWED_CATEGORIES)
     * @param string|null $providerId Optional provider (e.g. groq)
     * @param list<string> $existingTitles Titles already in catalog (case-insensitive dedup)
     * @return array{success: bool, data: list<array<string, mixed>>, generated: int, errors: list<string>}
     */
    public function generate(int $count, array $categories, ?string $providerId = null, array $existingTitles = []): array
    {
        $errors = [];

        if (! $this->aiService->hasAnyConfiguredProvider()) {
            return [
                'success' => false,
                'data' => [],
                'generated' => 0,
                'errors' => ['No AI provider is configured. Configure at least one under Helmetsan → AI.'],
            ];
        }

        $allowed = $categories !== [] ? $this->filterAllowedCategories($categories) : self::ALLOWED_CATEGORIES;
        if ($categories !== [] && $allowed === []) {
            return [
                'success' => false,
                'data' => [],
                'generated' => 0,
                'errors' => ['None of the requested categories are allowed. Use categories from accessory taxonomy.'],
            ];
        }

        $prompt = $this->buildPrompt($count, $allowed, $existingTitles);

        $options = ['max_tokens' => 4096, 'temperature' => 0.4];
        $response = $this->aiService->generate($prompt, $count, $providerId, $options);

        if ($response === null || trim($response) === '') {
            return [
                'success' => false,
                'data' => [],
                'generated' => 0,
                'errors' => ['AI returned no response. Check API key and model.'],
            ];
        }

        $parsed = $this->parseJsonFromResponse($response);
        if (! is_array($parsed) || ! isset($parsed[0])) {
            $parsed = $parsed !== null && is_array($parsed) ? [$parsed] : [];
        }
        $validated = $this->validateAndSanitize($parsed, $errors);
        $existingSet = array_flip(array_map([$this, 'normalizeTitle'], $existingTitles));
        $out = [];
        foreach ($validated as $item) {
            $title = (string) ($item['title'] ?? '');
            if ($title === '') {
                continue;
            }
            if (isset($existingSet[$this->normalizeTitle($title)])) {
                $errors[] = "Skipped duplicate: {$title}";
                continue;
            }
            $out[] = $item;
        }

        return [
            'success' => count($out) > 0,
            'data' => $out,
            'generated' => count($out),
            'errors' => $errors,
        ];
    }

    private function normalizeTitle(string $s): string
    {
        return strtolower(trim(preg_replace('/\s+/', ' ', $s)));
    }

    /**
     * @return list<string>
     */
    private function filterAllowedCategories(array $categories): array
    {
        $normalized = array_map('trim', $categories);
        $allowed = [];
        foreach (self::ALLOWED_CATEGORIES as $c) {
            foreach ($normalized as $req) {
                if (strcasecmp($req, $c) === 0) {
                    $allowed[] = $c;
                    break;
                }
            }
        }
        return $allowed;
    }

    private function buildPrompt(int $count, array $categories, array $existingTitles): string
    {
        $catList = implode(', ', array_slice($categories, 0, 25));
        $existingBlock = '';
        if ($existingTitles !== []) {
            $existingBlock = "\n- Do NOT generate products with these exact titles: " . implode(', ', array_slice($existingTitles, 0, 50)) . ".\n";
        }

        return <<<PROMPT
You are generating motorcycle helmet accessory catalog data for a structured ingest pipeline. Output ONLY a valid JSON array of objects. No markdown, no code fence, no explanation.

Schema (each object):
- entity: always "accessory"
- id: unique lowercase slug with hyphens (e.g. pinlock-pro-insert, sena-50s)
- title: specific product name (e.g. "Pinlock Pro Insert", "Sena 50S Bluetooth Headset")
- type: one of the allowed categories below (exact string)
- parent_category: same as type or a broader category from the list
- subcategory: optional; leave "" or omit if not applicable
- price: object with "current" (number) and "currency" ("USD")
- features: array of 2-5 short feature strings

Allowed categories for type and parent_category: {$catList}.
Generate exactly {$count} distinct accessory product(s).{$existingBlock}

Example (one item): {"entity":"accessory","id":"pinlock-pro-insert","title":"Pinlock Pro Insert","type":"Pinlock Inserts","parent_category":"Visors & Shields","subcategory":"","price":{"current":29.99,"currency":"USD"},"features":["Anti-fog","Fits OEM visors","Dual lens"]}

Output the JSON array now:
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
     * @param list<mixed> $items
     * @param list<string> $errors
     * @return list<array<string, mixed>>
     */
    private function validateAndSanitize(array $items, array &$errors): array
    {
        $out = [];
        foreach ($items as $i => $item) {
            if (! is_array($item)) {
                continue;
            }
            $title = isset($item['title']) ? trim((string) $item['title']) : '';
            $type = isset($item['type']) ? trim((string) $item['type']) : '';
            if ($title === '' || $type === '') {
                $errors[] = "Item " . ($i + 1) . ": missing title or type.";
                continue;
            }
            $id = isset($item['id']) ? trim((string) $item['id']) : '';
            if ($id === '') {
                $id = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $title));
                $id = trim($id, '-');
            }
            $built = [
                'entity' => 'accessory',
                'id' => $id,
                'title' => $title,
                'type' => $type,
                'parent_category' => isset($item['parent_category']) ? (string) $item['parent_category'] : $type,
                'subcategory' => isset($item['subcategory']) ? (string) $item['subcategory'] : '',
                'price' => isset($item['price']) && is_array($item['price'])
                    ? ['current' => (float) ($item['price']['current'] ?? 0), 'currency' => (string) ($item['price']['currency'] ?? 'USD')]
                    : ['current' => 0, 'currency' => 'USD'],
                'features' => isset($item['features']) && is_array($item['features'])
                    ? array_values(array_map('strval', $item['features']))
                    : [],
            ];
            if ($this->validator !== null) {
                $schemaResult = $this->validator->validateAccessorySchema($built);
                if (! $schemaResult['ok']) {
                    $errors[] = "Item " . ($i + 1) . " (" . $title . "): " . implode('; ', $schemaResult['errors']);
                    continue;
                }
                $logicResult = $this->validator->validateAccessoryLogic($built);
                if (! $logicResult['ok']) {
                    $errors[] = "Item " . ($i + 1) . " (" . $title . "): " . implode('; ', $logicResult['errors']);
                    continue;
                }
            }
            $out[] = $built;
        }
        return $out;
    }

    /** @return list<string> */
    public static function getAllowedCategories(): array
    {
        return self::ALLOWED_CATEGORIES;
    }
}
