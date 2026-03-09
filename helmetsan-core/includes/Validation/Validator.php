<?php

declare(strict_types=1);

namespace Helmetsan\Core\Validation;

final class Validator
{
    /**
     * @return array{ok: bool, errors: array<int, string>}
     */
    public function validateSchema(array $data): array
    {
        $errors = [];

        if (empty($data['id']) || ! is_string($data['id'])) {
            $errors[] = 'Missing or invalid id';
        }

        if (isset($data['specs']['weight_g']) && ! is_int($data['specs']['weight_g'])) {
            $errors[] = 'specs.weight_g must be integer';
        }

        return [
            'ok'     => $errors === [],
            'errors' => $errors,
        ];
    }

    /**
     * @return array{ok: bool, errors: array<int, string>, warnings: array<int, string>}
     */
    public function validateLogic(array $data): array
    {
        $errors   = [];
        $warnings = [];

        if (isset($data['specs']['weight_g']) && is_int($data['specs']['weight_g'])) {
            $weight = $data['specs']['weight_g'];
            if ($weight < 800 || $weight > 3000) {
                $warnings[] = 'Suspicious weight range: ' . (string) $weight;
            }
        }

        if (! empty($data['legal_status']) && is_array($data['legal_status'])) {
            foreach ($data['legal_status'] as $region => $status) {
                if (! is_array($status) || empty($status['status'])) {
                    $errors[] = 'legal_status.' . (string) $region . ' missing status';
                }
            }
        }

        return [
            'ok'       => $errors === [],
            'errors'   => $errors,
            'warnings' => $warnings,
        ];
    }

    /**
     * @return array{ok: bool, errors: array<int, string>}
     */
    public function validateIntegrity(): array
    {
        return [
            'ok'     => true,
            'errors' => [],
        ];
    }

    /**
     * Validate accessory item against schema (required fields and types).
     * Used by ingestion and AI-generated accessory data.
     *
     * @return array{ok: bool, errors: array<int, string>}
     */
    public function validateAccessorySchema(array $data): array
    {
        $errors = [];

        if (isset($data['entity']) && $data['entity'] !== 'accessory') {
            $errors[] = 'entity must be "accessory"';
        }
        if (empty($data['id']) || ! is_string($data['id'])) {
            $errors[] = 'Missing or invalid id (string)';
        }
        if (empty($data['title']) || ! is_string($data['title'])) {
            $errors[] = 'Missing or invalid title (string)';
        }
        if (empty($data['type']) || ! is_string($data['type'])) {
            $errors[] = 'Missing or invalid type (string)';
        }
        if (isset($data['price']) && ! is_array($data['price'])) {
            $errors[] = 'price must be an object if set';
        }
        if (isset($data['price']['current']) && ! is_numeric($data['price']['current'])) {
            $errors[] = 'price.current must be numeric';
        }
        if (isset($data['identifiers']) && ! is_array($data['identifiers'])) {
            $errors[] = 'identifiers must be an object if set';
        }
        if (isset($data['features']) && ! is_array($data['features'])) {
            $errors[] = 'features must be an array if set';
        }

        return [
            'ok'     => $errors === [],
            'errors' => $errors,
        ];
    }

    /**
     * Validate accessory item logic (ranges, consistency).
     *
     * @return array{ok: bool, errors: array<int, string>, warnings: array<int, string>}
     */
    public function validateAccessoryLogic(array $data): array
    {
        $errors   = [];
        $warnings = [];

        if (isset($data['price']['current']) && is_numeric($data['price']['current'])) {
            $current = (float) $data['price']['current'];
            if ($current < 0) {
                $errors[] = 'price.current must be >= 0';
            }
            if ($current > 999999.99) {
                $warnings[] = 'price.current unusually high: ' . $current;
            }
        }
        if (! empty($data['id']) && is_string($data['id']) && strlen($data['id']) > 200) {
            $warnings[] = 'id very long; consider shorter slug';
        }
        if (! empty($data['title']) && is_string($data['title']) && strlen($data['title']) > 500) {
            $warnings[] = 'title very long';
        }

        return [
            'ok'       => $errors === [],
            'errors'   => $errors,
            'warnings' => $warnings,
        ];
    }
}
