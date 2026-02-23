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
            $prompt = "Write exactly one meta description for a motorcycle helmet product page. "
                . "Product: {$product}. Type: {$type}. ";
            if ($certs !== []) {
                $prompt .= "Certifications: " . implode(', ', array_slice($certs, 0, 3)) . ". ";
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
            return "Write exactly one meta description for a motorcycle helmet brand hub page. "
                . "Brand: {$brand}. " . ($country !== '' ? "Origin: {$country}. " : '')
                . "Requirements: 150-160 characters. Mention full face, modular, adventure helmets; compare prices and certifications; "
                . "end with CTA like 'Official reviews at Helmetsan'. Output ONLY the meta description, no quotes or explanation.";
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
        $context = json_encode($existingData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $out = "You are a data assistant for a motorcycle gear catalog. Entity type: {$entityLabel}. "
            . "Existing data (JSON): {$context}. "
            . "The entity is missing the field: {$fieldLabel}. ";
        if ($allowedValues !== null && $allowedValues !== []) {
            $out .= "You MUST reply with exactly one of these values (no other text): " . implode(', ', $allowedValues) . ". Output ONLY that value, nothing else.";
        } else {
            $out .= "Generate a single, concise value for this field. ";
            if ($maxLength !== null && $maxLength > 0) {
                $out .= "Use at most {$maxLength} characters. ";
            }
            $out .= "Output ONLY the value, no explanation or quotes. Keep it short and factual.";
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
}
