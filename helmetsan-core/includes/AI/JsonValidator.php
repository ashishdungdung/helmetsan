<?php

declare(strict_types=1);

namespace Helmetsan\Core\AI;

/**
 * Validates AI-generated JSON strings against basic structural expectations.
 * Prevents saving malformed strings or incorrect types (e.g. array vs object) to post meta.
 */
final class JsonValidator
{
    /**
     * Validate if a string is a valid JSON and matches expected type.
     * 
     * @param string $json The raw string from AI (normalized)
     * @param string $expectedType 'array' or 'object'
     * @param string $fieldName The meta key name
     * @return array|object|null Decoded data if valid, null otherwise
     */
    public function validate(string $json, string $expectedType, string $fieldName): mixed
    {
        $json = trim($json);
        if ($json === '' || strtolower($json) === 'not specified' || strtolower($json) === 'n/a') {
            return null;
        }

        // Strip Markdown
        if (str_starts_with($json, '```')) {
            $json = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', $json);
            $json = trim($json);
        }

        $decoded = json_decode($json, true);
        if (json_last_error() !== JSON_ERROR_NONE || $decoded === null) {
            // Robust fallback: if we got a comma list but expected structured data
            if (str_contains($json, ',') && !str_starts_with($json, '[')) {
                $parts = array_map('trim', explode(',', $json));
                if ($expectedType === 'array') return $parts;
                if ($expectedType === 'object') return $this->mapArrayToObject($parts, $fieldName);
            }
            return null;
        }

        if ($expectedType === 'array') {
            return (is_array($decoded) && array_is_list($decoded)) ? $decoded : (is_array($decoded) ? array_values($decoded) : null);
        }

        if ($expectedType === 'object') {
            if (is_array($decoded) && ! array_is_list($decoded)) return $decoded;
            
            // If we got a list but expected an object, try to map it by position
            if (is_array($decoded) && array_is_list($decoded)) {
                return $this->mapArrayToObject($decoded, $fieldName);
            }
            return null;
        }

        return $decoded;
    }

    private function mapArrayToObject(array $items, string $fieldName): ?array
    {
        if ($fieldName === 'safety_intelligence_json') {
            $res = [
                'homologation_standard' => $items[0] ?? null,
                'rotational_mitigation' => null,
                'sharp_rating' => null,
            ];
            // Check if items[1] is a number (SHARP) or text (MIPS)
            if (isset($items[1])) {
                if (is_numeric($items[1]) || strlen($items[1]) <= 2) {
                    $res['sharp_rating'] = $items[1];
                } else {
                    $res['rotational_mitigation'] = $items[1];
                }
            }
            if (isset($items[2]) && $res['sharp_rating'] === null) {
                $res['sharp_rating'] = $items[2];
            }
            return $res;
        }
        if ($fieldName === 'aero_acoustic_profile_json') {
            return [
                'noise_db_at_100kph' => $items[0] ?? null,
                'ventilation_efficiency_score' => $items[1] ?? null,
            ];
        }
        return null;
    }

    /**
     * Infers expected type from field name or config pattern.
     */
    public function getExpectedTypeFromField(string $fieldName): string
    {
        if (str_ends_with($fieldName, '_json')) {
            $arrays = [
                'features_json', 
                'certification_documents_json', 
                'outgoing_internal_links_json',
                'visor_features_json',
                'liner_features_json'
            ];
            if (in_array($fieldName, $arrays, true)) {
                return 'array';
            }
            return 'object';
        }
        return 'scalar';
    }
}
