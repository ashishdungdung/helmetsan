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
     * @return array|object|null Decoded data if valid, null otherwise
     */
    public function validate(string $json, string $expectedType): mixed
    {
        if ($json === '') {
            return null;
        }

        $decoded = json_decode($json, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }

        if ($expectedType === 'array') {
            return is_array($decoded) && array_is_list($decoded) ? $decoded : null;
        }

        if ($expectedType === 'object') {
            return is_array($decoded) && ! array_is_list($decoded) ? $decoded : null;
        }

        return $decoded;
    }

    /**
     * Infers expected type from field name or config pattern.
     */
    public function getExpectedTypeFromField(string $fieldName): string
    {
        if (str_ends_with($fieldName, '_json')) {
            // Most _json fields are objects in this project, but some are explicitly arrays.
            if (in_array($fieldName, ['features_json', 'certification_documents_json', 'outgoing_internal_links_json'], true)) {
                return 'array';
            }
            return 'object';
        }
        return 'scalar';
    }
}
