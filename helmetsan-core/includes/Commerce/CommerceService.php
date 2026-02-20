<?php

declare(strict_types=1);

namespace Helmetsan\Core\Commerce;

use WP_Post;

final class CommerceService
{
    public static function readMarketplacesIndex(): array
    {
        return self::readFileIndex('marketplaces');
    }

    public static function readCurrenciesIndex(): array
    {
        return self::readFileIndex('currencies');
    }

    private static function readFileIndex(string $name): array
    {
        $dir = wp_upload_dir()['basedir'] . '/helmetsan_indexes';
        $file = $dir . '/' . $name . '.json';
        if (file_exists($file)) {
            $data = json_decode((string) file_get_contents($file), true);
            return is_array($data) ? $data : [];
        }
        return get_option('helmetsan_' . $name . '_index', []);
    }

    private static function writeFileIndex(string $name, array $data): void
    {
        $dir = wp_upload_dir()['basedir'] . '/helmetsan_indexes';
        if (!is_dir($dir)) {
            wp_mkdir_p($dir);
        }
        file_put_contents($dir . '/' . $name . '.json', wp_json_encode($data));
        delete_option('helmetsan_' . $name . '_index');
    }

    /**
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    public function upsertFromPayload(array $data, string $sourceFile = '', bool $dryRun = false): array
    {
        $entity = sanitize_key((string) ($data['entity'] ?? ''));
        return match ($entity) {
            'currency' => $this->upsertCurrency($data, $sourceFile, $dryRun),
            'marketplace' => $this->upsertMarketplace($data, $sourceFile, $dryRun),
            'pricing' => $this->upsertPricing($data, $sourceFile, $dryRun),
            'offer' => $this->upsertOffer($data, $sourceFile, $dryRun),
            default => ['ok' => false, 'message' => 'Unsupported commerce entity'],
        };
    }

    /**
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    private function upsertCurrency(array $data, string $sourceFile, bool $dryRun): array
    {
        $code = strtoupper(sanitize_text_field((string) ($data['code'] ?? '')));
        if ($code === '' || strlen($code) !== 3) {
            return ['ok' => false, 'message' => 'Currency code missing/invalid'];
        }

        $index = self::readCurrenciesIndex();

        $record = [
            'entity' => 'currency',
            'code' => $code,
            'name' => sanitize_text_field((string) ($data['name'] ?? '')),
            'symbol' => sanitize_text_field((string) ($data['symbol'] ?? '')),
            'decimal_places' => isset($data['decimal_places']) ? (int) $data['decimal_places'] : 2,
            'source_file' => $sourceFile,
            'updated_at' => gmdate('c'),
        ];

        $json = wp_json_encode($record);
        $newHash = hash('sha256', is_string($json) ? $json : serialize($record));
        $oldHash = isset($index[$code]['_hash']) ? (string) $index[$code]['_hash'] : '';
        if ($oldHash !== '' && hash_equals($oldHash, $newHash)) {
            return ['ok' => true, 'action' => 'skipped', 'key' => $code];
        }

        if ($dryRun) {
            return ['ok' => true, 'action' => 'dry-run', 'key' => $code];
        }

        $exists = isset($index[$code]);
        $record['_hash'] = $newHash;
        $index[$code] = $record;
        self::writeFileIndex('currencies', $index);

        return ['ok' => true, 'action' => $exists ? 'updated' : 'created', 'key' => $code];
    }

    /**
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    private function upsertMarketplace(array $data, string $sourceFile, bool $dryRun): array
    {
        $id = sanitize_title((string) ($data['id'] ?? ''));
        if ($id === '') {
            return ['ok' => false, 'message' => 'Marketplace id missing'];
        }

        $index = self::readMarketplacesIndex();

        $record = [
            'entity' => 'marketplace',
            'id' => $id,
            'name' => sanitize_text_field((string) ($data['name'] ?? $id)),
            'website' => esc_url_raw((string) ($data['website'] ?? '')),
            'country_codes' => isset($data['country_codes']) && is_array($data['country_codes']) ? array_values(array_map('strtoupper', array_map('strval', $data['country_codes']))) : [],
            'supports_online' => ! empty($data['supports_online']),
            'supports_offline' => ! empty($data['supports_offline']),
            'source_file' => $sourceFile,
            'updated_at' => gmdate('c'),
        ];

        $json = wp_json_encode($record);
        $newHash = hash('sha256', is_string($json) ? $json : serialize($record));
        $oldHash = isset($index[$id]['_hash']) ? (string) $index[$id]['_hash'] : '';
        if ($oldHash !== '' && hash_equals($oldHash, $newHash)) {
            return ['ok' => true, 'action' => 'skipped', 'key' => $id];
        }

        if ($dryRun) {
            return ['ok' => true, 'action' => 'dry-run', 'key' => $id];
        }

        $exists = isset($index[$id]);
        $record['_hash'] = $newHash;
        $index[$id] = $record;
        self::writeFileIndex('marketplaces', $index);

        return ['ok' => true, 'action' => $exists ? 'updated' : 'created', 'key' => $id];
    }

    /**
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    private function upsertPricing(array $data, string $sourceFile, bool $dryRun): array
    {
        $helmetId = $this->resolveHelmetPostId((string) ($data['helmet_id'] ?? ''));
        if ($helmetId <= 0) {
            return ['ok' => false, 'message' => 'Pricing helmet_id not found'];
        }

        $country = strtoupper(sanitize_text_field((string) ($data['country_code'] ?? '')));
        $marketplace = sanitize_title((string) ($data['marketplace_id'] ?? 'global'));
        if ($country === '') {
            return ['ok' => false, 'message' => 'Pricing country_code missing'];
        }

        $entry = [
            'country_code' => $country,
            'marketplace_id' => $marketplace,
            'currency' => strtoupper(sanitize_text_field((string) ($data['currency'] ?? 'USD'))),
            'launch_price' => isset($data['launch_price']) ? (float) $data['launch_price'] : null,
            'current_price' => isset($data['current_price']) ? (float) $data['current_price'] : null,
            'mrp' => isset($data['mrp']) ? (float) $data['mrp'] : null,
            'price_type' => sanitize_text_field((string) ($data['price_type'] ?? 'online')),
            'source_url' => esc_url_raw((string) ($data['source_url'] ?? '')),
            'captured_at' => sanitize_text_field((string) ($data['captured_at'] ?? gmdate('c'))),
            'source_file' => $sourceFile,
        ];

        $existing = $this->readJsonMetaArray($helmetId, 'pricing_records_json');
        $key = $country . '|' . $marketplace;
        $byKey = [];
        foreach ($existing as $row) {
            if (! is_array($row)) {
                continue;
            }
            $rowCountry = strtoupper((string) ($row['country_code'] ?? ''));
            $rowMarket = sanitize_title((string) ($row['marketplace_id'] ?? 'global'));
            if ($rowCountry === '') {
                continue;
            }
            $byKey[$rowCountry . '|' . $rowMarket] = $row;
        }

        $oldJson = wp_json_encode($byKey[$key] ?? []);
        $newJson = wp_json_encode($entry);
        if (is_string($oldJson) && is_string($newJson) && $oldJson !== '' && $oldJson === $newJson) {
            return ['ok' => true, 'action' => 'skipped', 'post_id' => $helmetId, 'key' => $key];
        }

        if ($dryRun) {
            return ['ok' => true, 'action' => 'dry-run', 'post_id' => $helmetId, 'key' => $key];
        }

        $byKey[$key] = $entry;
        update_post_meta($helmetId, 'pricing_records_json', wp_json_encode(array_values($byKey), JSON_UNESCAPED_SLASHES));

        $geoPricing = $this->readJsonMetaAssoc($helmetId, 'geo_pricing_json');
        $geoPricing[$country] = [
            'country_code'   => $country,
            'marketplace_id' => $marketplace,
            'current_price'  => $entry['current_price'] ?? $entry['mrp'],
            'currency'       => $entry['currency'],
            'availability'   => sanitize_text_field((string) ($data['availability'] ?? 'unknown')),
            'updated_at'     => gmdate('Y-m-d'),
        ];
        update_post_meta($helmetId, 'geo_pricing_json', wp_json_encode($geoPricing, JSON_UNESCAPED_SLASHES));

        if (isset($entry['current_price']) && $entry['current_price'] !== null) {
            if ($entry['currency'] === 'USD') {
                update_post_meta($helmetId, 'price_retail_usd', (string) $entry['current_price']);
            } else {
                $currencyMetaMap = [
                    'EUR' => 'price_eur',
                    'GBP' => 'price_gbp',
                    'INR' => 'price_inr',
                    'CAD' => 'price_cad',
                    'AUD' => 'price_aud',
                    'JPY' => 'price_jpy',
                    'MXN' => 'price_mxn',
                    'PLN' => 'price_pln',
                    'NGN' => 'price_ngn',
                ];
                $metaKey = $currencyMetaMap[$entry['currency']] ?? 'price_' . strtolower($entry['currency']);
                update_post_meta($helmetId, $metaKey, (string) $entry['current_price']);
            }
        }

        return ['ok' => true, 'action' => 'updated', 'post_id' => $helmetId, 'key' => $key];
    }

    /**
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    private function upsertOffer(array $data, string $sourceFile, bool $dryRun): array
    {
        $helmetId = $this->resolveHelmetPostId((string) ($data['helmet_id'] ?? ''));
        if ($helmetId <= 0) {
            return ['ok' => false, 'message' => 'Offer helmet_id not found'];
        }

        $offerId = sanitize_title((string) ($data['id'] ?? ''));
        if ($offerId === '') {
            $offerId = sanitize_title((string) ($data['country_code'] ?? 'xx') . '-' . (string) ($data['marketplace_id'] ?? 'market') . '-' . (string) ($data['shop_name'] ?? 'shop'));
        }

        $entry = [
            'id' => $offerId,
            'country_code' => strtoupper(sanitize_text_field((string) ($data['country_code'] ?? ''))),
            'region_code' => sanitize_text_field((string) ($data['region_code'] ?? '')),
            'shop_name' => sanitize_text_field((string) ($data['shop_name'] ?? '')),
            'marketplace_id' => sanitize_title((string) ($data['marketplace_id'] ?? '')),
            'currency' => strtoupper(sanitize_text_field((string) ($data['currency'] ?? 'USD'))),
            'offer_price' => isset($data['offer_price']) ? (float) $data['offer_price'] : null,
            'mrp' => isset($data['mrp']) ? (float) $data['mrp'] : null,
            'discount_percent' => isset($data['discount_percent']) ? (float) $data['discount_percent'] : null,
            'offer_type' => sanitize_text_field((string) ($data['offer_type'] ?? 'price_drop')),
            'url' => esc_url_raw((string) ($data['url'] ?? '')),
            'valid_until' => sanitize_text_field((string) ($data['valid_until'] ?? '')),
            'source_file' => $sourceFile,
        ];

        $existing = $this->readJsonMetaArray($helmetId, 'offers_json');
        $byId = [];
        foreach ($existing as $row) {
            if (! is_array($row)) {
                continue;
            }
            $id = sanitize_title((string) ($row['id'] ?? ''));
            if ($id === '') {
                continue;
            }
            $byId[$id] = $row;
        }

        $oldJson = wp_json_encode($byId[$offerId] ?? []);
        $newJson = wp_json_encode($entry);
        if (is_string($oldJson) && is_string($newJson) && $oldJson !== '' && $oldJson === $newJson) {
            return ['ok' => true, 'action' => 'skipped', 'post_id' => $helmetId, 'key' => $offerId];
        }

        if ($dryRun) {
            return ['ok' => true, 'action' => 'dry-run', 'post_id' => $helmetId, 'key' => $offerId];
        }

        $byId[$offerId] = $entry;
        update_post_meta($helmetId, 'offers_json', wp_json_encode(array_values($byId), JSON_UNESCAPED_SLASHES));

        $best = $this->computeBestOffer(array_values($byId));
        if ($best !== []) {
            update_post_meta($helmetId, 'best_offer_json', wp_json_encode($best, JSON_UNESCAPED_SLASHES));
        }

        return ['ok' => true, 'action' => 'updated', 'post_id' => $helmetId, 'key' => $offerId];
    }

    private function resolveHelmetPostId(string $helmetRef): int
    {
        $helmetRef = sanitize_title($helmetRef);
        if ($helmetRef === '') {
            return 0;
        }

        $posts = get_posts([
            'post_type' => 'helmet',
            'post_status' => 'any',
            'numberposts' => 1,
            'meta_key' => '_helmet_unique_id',
            'meta_value' => $helmetRef,
            'fields' => 'ids',
        ]);
        if (is_array($posts) && $posts !== []) {
            return (int) $posts[0];
        }

        $byPath = get_page_by_path($helmetRef, OBJECT, 'helmet');
        if ($byPath instanceof WP_Post) {
            return (int) $byPath->ID;
        }

        return 0;
    }

    /**
     * @return array<int,mixed>
     */
    private function readJsonMetaArray(int $postId, string $metaKey): array
    {
        $raw = (string) get_post_meta($postId, $metaKey, true);
        if ($raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @return array<string,mixed>
     */
    private function readJsonMetaAssoc(int $postId, string $metaKey): array
    {
        $raw = (string) get_post_meta($postId, $metaKey, true);
        if ($raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param array<int,mixed> $offers
     * @return array<string,mixed>
     */
    private function computeBestOffer(array $offers): array
    {
        $best = [];
        $bestPrice = null;

        foreach ($offers as $offer) {
            if (! is_array($offer)) {
                continue;
            }
            $price = isset($offer['offer_price']) ? (float) $offer['offer_price'] : null;
            if ($price === null || $price <= 0) {
                continue;
            }
            if ($bestPrice === null || $price < $bestPrice) {
                $bestPrice = $price;
                $best = $offer;
            }
        }

        return $best;
    }
}
