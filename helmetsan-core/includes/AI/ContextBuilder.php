<?php

declare(strict_types=1);

namespace Helmetsan\Core\AI;

/**
 * Builds prompt context for AI tasks (Phase 1 SEO, Phase 2 fill missing data, Phase 3 integrity).
 * Context-aware: uses entity type and existing fields so the model can generate accurately.
 */
final class ContextBuilder
{
    /**
     * Build context for Phase 1: SEO meta description.
     * Returns a prompt string and optional options; AiService will use this to call the provider.
     */
    public static function forSeoDescription(string $entityType, array $data, int $postId): string
    {
        if ($entityType === 'helmet') {
            $product = ($data['brand'] !== '' ? $data['brand'] . ' ' : '') . ($data['title'] ?? '');
            $type = $data['type'] ?? 'motorcycle helmet';
            $certs = $data['certifications'] ?? [];
            $price = $data['price'] ?? null;
            $family = $data['helmet_family'] ?? null;
            $features = $data['feature_tags'] ?? [];
            $useCase = $data['use_case'] ?? null;
            $prompt = "Write exactly one meta description for a motorcycle helmet product page. "
                . "Product: {$product}. Type: {$type}. ";
            if ($certs !== []) {
                $prompt .= "Certifications: " . implode(', ', array_slice($certs, 0, 3)) . ". ";
            }
            if ($family !== null && $family !== '') {
                $prompt .= "Series/family: {$family}. ";
            }
            if ($features !== []) {
                $prompt .= "Features: " . implode(', ', array_slice($features, 0, 4)) . ". ";
            }
            if ($useCase !== null && $useCase !== '') {
                $prompt .= "Use case: {$useCase}. ";
            }
            if ($price !== null && $price !== '') {
                $prompt .= "Price: {$price}. ";
            }
            $prompt .= "Requirements: 150-160 characters total. Include product name and type. "
                . "Professional, benefit-led. End with a CTA like 'Compare at Helmetsan' or 'Find the best deal at Helmetsan'. "
                . "Output ONLY the meta description, no quotes or explanation.";
            return $prompt;
        }
        if ($entityType === 'brand') {
            $brand = $data['brand'] ?? 'Brand';
            $country = $data['country'] ?? '';
            $motto = $data['motto'] ?? null;
            $story = $data['story_snippet'] ?? null;
            $prompt = "Write exactly one meta description for a motorcycle helmet brand hub page. "
                . "Brand: {$brand}. " . ($country !== '' ? "Origin: {$country}. " : '');
            if ($motto !== null && $motto !== '') {
                $prompt .= "Tagline: {$motto}. ";
            }
            if ($story !== null && $story !== '') {
                $prompt .= "Context: {$story}. ";
            }
            $prompt .= "Requirements: 150-160 characters. Mention full face, modular, adventure helmets; compare prices and certifications; "
                . "end with CTA like 'Official reviews at Helmetsan'. Output ONLY the meta description, no quotes or explanation.";
            return $prompt;
        }
        if ($entityType === 'accessory') {
            $title = $data['title'] ?? 'Product';
            $category = $data['category'] ?? 'Motorcycle Accessory';
            return "Write exactly one meta description for a motorcycle helmet accessory product page. "
                . "Product: {$title}. Category: {$category}. "
                . "Requirements: 150-160 characters. Mention compatibility and buying guide. "
                . "End with CTA like 'at Helmetsan'. Output ONLY the meta description, no quotes or explanation.";
        }
        return '';
    }

    /**
     * Phase 2: Build prompt for filling a missing field (context-aware).
     * @param list<string>|null $allowedValues If set, output must be exactly one of these (e.g. long-oval).
     * @param int|null $maxLength Hint for max character length.
     */
    public static function forFillField(
        string $entityType,
        string $fieldName,
        string $fieldLabel,
        array $existingData,
        ?int $maxLength = null,
        ?array $allowedValues = null
    ): string {
        $entityLabel = $entityType === 'helmet' ? 'helmet' : ($entityType === 'brand' ? 'brand' : 'accessory');
        $productInfo = '';
        if ($entityType === 'helmet') {
            $brand = $existingData['brand'] ?? '';
            $title = $existingData['title'] ?? '';
            $productInfo = "Product: " . ($brand ? $brand . ' ' : '') . $title . ". ";
        }
        $context = json_encode($existingData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $out = "You are a specialized motorcycle gear data expert. Entity: {$entityLabel}. {$productInfo}"
            . "Full Context (JSON): {$context}. "
            . "Task: Provide the value for the missing field: \"{$fieldLabel}\". ";
        if ($allowedValues !== null && $allowedValues !== []) {
            $out .= "You MUST reply with exactly one of these values (no other text): " . implode(', ', $allowedValues) . ". Output ONLY that value, nothing else.";
        } else {
            $out .= "Generate a single, concise value for this field. ";
            if ($maxLength !== null && $maxLength > 0) {
                $out .= "Use at most {$maxLength} characters. ";
            }
            if ($entityType === 'helmet' && $fieldName === 'spec_weight_g') {
                $out .= "Reply with the weight in grams only (integer, e.g. 1450). No unit text.";
            } elseif ($entityType === 'helmet' && $fieldName === 'technical_analysis') {
                $out .= "Write 2-4 sentences: safety tech, comfort, ventilation. Factual and product-specific.";
            } elseif ($entityType === 'helmet' && $fieldName === 'spec_shell_material') {
                $out .= "Reply with the shell material only (e.g. Polycarbonate, Carbon fiber, AIM+, composite).";
            } elseif ($entityType === 'helmet' && in_array($fieldName, ['yoast_title', 'yoast_metadesc', 'yoast_focuskw'], true)) {
                $out .= "SEO value: include product name and type where relevant; factual and search-friendly.";
            } elseif ($entityType === 'brand') {
                $out .= "Brand context: be accurate and factual (origin, story, warranty); avoid marketing fluff.";
            } elseif ($entityType === 'accessory') {
                $out .= "Use values that match standard accessory categories (e.g. Pinlock Inserts, Bluetooth Headsets) when applicable.";
            }
            $out .= " Output ONLY the value, no explanation or quotes. Keep it short and factual.";
        }
        return $out;
    }

    /**
     * Phase 2 retry: prompt when the previous AI reply was invalid (e.g. not in allowed_values).
     * @param list<string> $allowedValues
     */
    public static function forFillFieldRetry(
        string $entityType,
        string $fieldName,
        string $fieldLabel,
        array $existingData,
        array $allowedValues,
        string $previousInvalidValue
    ): string {
        $base = self::forFillField($entityType, $fieldName, $fieldLabel, $existingData, null, $allowedValues);
        return $base . " Your previous reply was invalid: \"" . substr($previousInvalidValue, 0, 200) . "\". You MUST reply with exactly one of: " . implode(', ', $allowedValues) . ". Output ONLY that value.";
    }

    /**
     * Phase 3: Build prompt for checking data integrity / quality of an entity.
     */
    public static function forIntegrityCheck(string $entityType, array $entityData): string
    {
        $context = json_encode($entityData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return "You are a data quality checker for a motorcycle helmet/gear catalog. "
            . "Entity type: {$entityType}. Data (JSON): {$context}. "
            . "Reply with a short assessment: VALID or INVALID, then one line explaining why (e.g. missing required field, inconsistent type). "
            . "Output format: VALID/INVALID - one line reason.";
    }

    /**
     * Build prompt to resolve a helmet product image source (EAN/GTIN or direct image URL).
     * Used by helmet image enrichment to match our catalog helmets with external product images.
     * Output must be valid JSON: {"ean":"13 digits or empty","image_url":"https://... or empty"}
     */
    public static function forResolveHelmetImageSource(string $title, string $brand): string
    {
        $product = $brand !== '' ? $brand . ' ' . $title : $title;
        return "You are a product data assistant for motorcycle helmet catalogs. "
            . "Helmet: {$product}. "
            . "Reply with a JSON object only (no markdown, no explanation). "
            . "Include exactly two keys: \"ean\" (EAN-13 barcode if you know it for this exact model, else empty string \"\") "
            . "and \"image_url\" (a direct URL to a product image for this helmet from an official or retail source, else empty string \"\"). "
            . "If you do not know the EAN or a reliable image URL, use empty strings. Output ONLY the JSON object.";
    }

    /**
     * Build prompt to resolve a RevZilla product page URL for a helmet (for image fetch or affiliate link).
     * Output: a single URL string to the RevZilla product page, or empty if not found.
     */
    /**
     * Build prompt to resolve a RevZilla product page URL for a helmet (for image fetch or affiliate link).
     * Output: a single URL string to the RevZilla product page, or empty if not found.
     */
    public static function forResolveRevZillaUrl(string $title, string $brand): string
    {
        $product = $brand !== '' ? $brand . ' ' . $title : $title;
        return "You are a product data assistant for motorcycle gear. "
            . "Find the RevZilla.com product page URL for this helmet: {$product}. "
            . "Reply with ONLY the full RevZilla product page URL (https://www.revzilla.com/...), or the word \"none\" if you cannot find a matching RevZilla listing. "
            . "Do not include any other text, markdown, or explanation.";
    }

    /**
     * Build prompt to resolve official manufacturer image URLs for a helmet.
     * Output must be valid JSON: {"images": ["https://...", "https://..."]}
     */
    public static function forResolveManufacturerImages(string $title, string $brand): string
    {
        $product = $brand !== '' ? $brand . ' ' . $title : $title;
        return "You are a product data assistant for motorcycle helmets. "
            . "Find official manufacturer product images for the following helmet: {$product}. "
            . "Reply with a JSON object containing a list of direct URLs to high-quality images from the manufacturer's official website. "
            . "Output format: {\"images\": [\"url1\", \"url2\"]}. "
            . "If no official images can be found with high confidence, return an empty list. "
            . "Output ONLY the JSON object, no explanation.";
    }
}
