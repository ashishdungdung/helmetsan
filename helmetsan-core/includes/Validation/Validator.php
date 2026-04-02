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

        $entity = isset($data['entity']) ? (string) $data['entity'] : '';

        if (empty($data['id']) || ! is_string($data['id'])) {
            $errors[] = 'Missing or invalid id';
        }

        // If this is an exported/seeded helmet payload, enforce the full “minimum helmet contract”.
        if ($entity === 'helmet') {
            if (empty($data['title']) || ! is_string($data['title'])) {
                $errors[] = 'Missing or invalid title (string)';
            }
            if (empty($data['brand']) || ! is_string($data['brand'])) {
                $errors[] = 'Missing or invalid brand (string)';
            }
            if (empty($data['type']) || ! is_string($data['type'])) {
                $errors[] = 'Missing or invalid type (string)';
            }
            if (! isset($data['specs']) || ! is_array($data['specs'])) {
                $errors[] = 'Missing or invalid specs (object)';
            }
        } elseif ($entity === 'brand') {
            if (empty($data['title']) || ! is_string($data['title'])) {
                $errors[] = 'Missing or invalid title (string)';
            }
            if (isset($data['profile']) && ! is_array($data['profile'])) {
                $errors[] = 'profile must be an object if set';
            }
        } elseif ($entity === 'offer') {
            if (empty($data['id']) || ! is_string($data['id'])) {
                $errors[] = 'Missing or invalid id (string)';
            }
            if (empty($data['url']) || ! is_string($data['url'])) {
                $errors[] = 'Missing or invalid url (string)';
            }
        } elseif ($entity === 'distributor') {
            if (empty($data['id']) || ! is_string($data['id'])) {
                $errors[] = 'Missing or invalid id (string)';
            }
            if (empty($data['name']) || ! is_string($data['name'])) {
                $errors[] = 'Missing or invalid name (string)';
            }
        } elseif (isset($data['specs']) && ! is_array($data['specs'])) {
            $errors[] = 'specs must be an object if set';
        }

        if (isset($data['specs']) && is_array($data['specs'])) {
            if (array_key_exists('weight_g', $data['specs']) && $data['specs']['weight_g'] !== null && ! is_int($data['specs']['weight_g'])) {
                $errors[] = 'specs.weight_g must be integer (grams)';
            }
            if (array_key_exists('weight_lbs', $data['specs']) && $data['specs']['weight_lbs'] !== null && ! is_numeric($data['specs']['weight_lbs'])) {
                $errors[] = 'specs.weight_lbs must be numeric';
            }
            if (isset($data['specs']['certifications']) && ! is_array($data['specs']['certifications'])) {
                $errors[] = 'specs.certifications must be an array';
            }
        }

        if (isset($data['price']) && ! is_array($data['price'])) {
            $errors[] = 'price must be an object if set';
        }
        if (isset($data['price']) && is_array($data['price']) && array_key_exists('current', $data['price']) && $data['price']['current'] !== null && ! is_numeric($data['price']['current'])) {
            $errors[] = 'price.current must be numeric';
        }

        if (isset($data['parent_id']) && ! is_string($data['parent_id'])) {
            $errors[] = 'parent_id must be a string if set';
        }

        if (isset($data['helmet_types']) && ! is_array($data['helmet_types'])) {
            $errors[] = 'helmet_types must be an array if set';
        }

        if (isset($data['product_details']) && ! is_array($data['product_details'])) {
            $errors[] = 'product_details must be an object if set';
        }
        if (isset($data['product_details']) && is_array($data['product_details']) && array_key_exists('description', $data['product_details']) && $data['product_details']['description'] !== null && ! is_string($data['product_details']['description'])) {
            $errors[] = 'product_details.description must be a string';
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

        if (isset($data['price']['current']) && is_numeric($data['price']['current'])) {
            $current = (float) $data['price']['current'];
            if ($current < 0) {
                $errors[] = 'price.current must be >= 0';
            }
        }

        // --- NEW: Safety Standard Semantic Logic (ECE 22.06 vs 22.05) ---
        if ($data['entity'] === 'helmet' && isset($data['specs']['certifications']) && is_array($data['specs']['certifications'])) {
            $certs = array_map('strtolower', $data['specs']['certifications']);
            $is2206 = false;
            foreach ($certs as $c) {
                if (str_contains($c, '22.06') || str_contains($c, '2206')) {
                    $is2206 = true;
                    break;
                }
            }

            if ($is2206 && isset($data['specs']['weight_g']) && is_int($data['specs']['weight_g'])) {
                // ECE 22.06 helmets are generally heavier due to stricter rotation/impact tests.
                // Full face 22.06 rarely goes below 1350g unless it's pure Carbon.
                $weight = $data['specs']['weight_g'];
                $isCarbon = false;
                if (isset($data['specs']['shell_material']) && str_contains(strtolower((string)$data['specs']['shell_material']), 'carbon')) {
                    $isCarbon = true;
                }

                if ($weight < 1250 && ! $isCarbon) {
                    $warnings[] = 'Potentially unrealistic weight for ECE 22.06 non-carbon helmet: ' . $weight . 'g';
                }
            }
        }

        // --- NEW: Marketing Description Presence & Quality ---
        if ($data['entity'] === 'helmet') {
            $desc = $data['product_details']['description'] ?? $data['marketing_description'] ?? $data['description'] ?? '';
            if (empty($desc) || trim((string) $desc) === '') {
                $warnings[] = 'Missing marketing description';
            } elseif ($this->isFallbackDescription($data, (string) $desc)) {
                $warnings[] = 'Using generic fallback description; needs AI enrichment';
            } elseif (strlen((string) $desc) < 60) {
                $warnings[] = 'Marketing description too short (under 60 chars); needs enrichment';
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

        if (empty($data['entity']) || $data['entity'] !== 'accessory') {
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
        if (empty($data['price']) || ! is_array($data['price'])) {
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

        // Description validation for accessories
        $desc = $data['description'] ?? $data['product_details']['description'] ?? '';
        if (empty($desc) || trim((string) $desc) === '') {
            $warnings[] = 'Missing accessory description';
        } elseif (strlen((string) $desc) < 30) {
            $warnings[] = 'Accessory description too short';
        }

        // --- NEW: Accessory Compatibility Logic ---
        if (isset($data['compatibility']) && is_array($data['compatibility'])) {
            if (empty($data['compatibility'])) {
                $warnings[] = 'Compatibility list is empty for an accessory';
            } else {
                foreach ($data['compatibility'] as $comp) {
                    if (is_array($comp)) {
                        if (empty($comp['model']) && empty($comp['brand'])) {
                            $errors[] = 'Compatibility entry missing both brand and model';
                        }
                    } elseif (! is_string($comp)) {
                        $errors[] = 'Compatibility entry must be a string or model-object';
                    }
                }
            }
        } else {
            $warnings[] = 'No compatibility field found for accessory';
        }

        return [
            'ok'       => $errors === [],
            'errors'   => $errors,
            'warnings' => $warnings,
        ];
    }

    /**
     * @return array{ok: bool, errors: array<int, string>, warnings: array<int, string>}
     */
    public function validateBrandLogic(array $data): array
    {
        $errors   = [];
        $warnings = [];

        if (empty($data['profile']['origin_country'])) {
            $warnings[] = 'Origin country not specified';
        }

        if (isset($data['profile']['total_models']) && (int) $data['profile']['total_models'] < 1) {
            $warnings[] = 'Brand has 0 models listed? (Check integrity)';
        }

        $desc = $data['profile']['description'] ?? $data['description'] ?? '';
        if (empty($desc) || trim((string) $desc) === '') {
            $warnings[] = 'Missing brand description';
        }

        return [
            'ok'       => $errors === [],
            'errors'   => $errors,
            'warnings' => $warnings,
        ];
    }

    /**
     * @return array{ok: bool, errors: array<int, string>, warnings: array<int, string>}
     */
    public function validateOfferLogic(array $data): array
    {
        $errors   = [];
        $warnings = [];

        if (isset($data['offer_price'], $data['mrp']) && (float) $data['offer_price'] > (float) $data['mrp']) {
            $errors[] = 'offer_price cannot be greater than mrp';
        }

        // --- NEW: Price Rationality (Massive drops or spikes) ---
        if (isset($data['offer_price'], $data['mrp']) && (float) $data['mrp'] > 0) {
            $discount = 1 - ((float) $data['offer_price'] / (float) $data['mrp']);
            if ($discount > 0.85) {
                $warnings[] = 'Suspiciously deep discount (>85%): ' . round($discount * 100, 2) . '%';
            }
            if ($discount < -0.5) {
                $warnings[] = 'Offer price significantly higher than MRP (>50%): ' . round(abs($discount) * 100, 2) . '% markup';
            }
        }

        return [
            'ok'       => $errors === [],
            'errors'   => $errors,
            'warnings' => $warnings,
        ];
    }

    /**
     * @return array{ok: bool, errors: array<int, string>, warnings: array<int, string>}
     */
    public function validateDistributorLogic(array $data): array
    {
        $errors   = [];
        $warnings = [];

        if (empty($data['brands']) || ! is_array($data['brands'])) {
            $warnings[] = 'Distributor has no associated brands listed';
        }

        if (empty($data['countries']) || ! is_array($data['countries'])) {
            $warnings[] = 'Distributor has no associated countries listed';
        }

        return [
            'ok'       => $errors === [],
            'errors'   => $errors,
            'warnings' => $warnings,
        ];
    }

    /**
     * Check for massive price drift (e.g. 30% jump) against previous baseline.
     *
     * @return array{ok: bool, warnings: array<int, string>}
     */
    public function validatePriceDrift(float $newPrice, ?float $oldPrice): array
    {
        $warnings = [];
        if ($oldPrice !== null && $oldPrice > 0) {
            $drift = abs(($newPrice - $oldPrice) / $oldPrice);
            if ($drift > 0.30) {
                $warnings[] = sprintf(
                    'Significant price drift detected: %s%% change (from %s to %s)',
                    round($drift * 100, 2),
                    $oldPrice,
                    $newPrice
                );
            }
        }
        return [
            'ok'       => true,
            'warnings' => $warnings,
        ];
    }

    /**
     * Check if a description matches the generic fallback pattern:
     * "Title | Type: X | Brand: Y"
     */
    private function isFallbackDescription(array $data, string $desc): bool
    {
        $title = (string) ($data['title'] ?? '');
        $type = (string) ($data['type'] ?? '');
        $brand = (string) ($data['brand'] ?? '');

        if ($title === '') return false;

        // Check for common patterns generated by IngestionService::buildDescription
        $patterns = [
            $title . ' | Type: ' . $type . ' | Brand: ' . $brand,
            $title . ' | Brand: ' . $brand,
            $title . ' | Type: ' . $type,
            $title . ' | ' . $type . ' | ' . $brand,
        ];

        foreach ($patterns as $pattern) {
            if (trim($desc) === trim($pattern)) {
                return true;
            }
        }

        return false;
    }
}
