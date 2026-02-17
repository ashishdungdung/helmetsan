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
}
