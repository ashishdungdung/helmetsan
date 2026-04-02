<?php

declare(strict_types=1);

namespace Helmetsan\Core\ImportExport;

use Helmetsan\Core\Brands\BrandService;
use Helmetsan\Core\Support\Config;

final class ExportService
{
    public function __construct(
        private readonly Config $config,
        private readonly BrandService $brands
    )
    {
    }

    public function exportByPostId(int $postId, string $entity = 'helmet', ?string $outputFile = null): array
    {
        return $entity === 'brand'
            ? $this->exportBrandByPostId($postId, $outputFile)
            : $this->exportHelmetByPostId($postId, $outputFile);
    }

    public function exportHelmetByPostId(int $postId, ?string $outputFile = null): array
    {
        $post = get_post($postId);
        if (! $post instanceof \WP_Post || $post->post_type !== 'helmet') {
            return [
                'ok'      => false,
                'message' => 'Helmet post not found',
            ];
        }

        $payload = $this->buildHelmetPayload($post);
        $target  = $outputFile ?: $this->defaultOutputPath((string) $payload['id']);

        $dir = dirname($target);
        if (! is_dir($dir)) {
            wp_mkdir_p($dir);
        }

        $json = wp_json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if (! is_string($json)) {
            return [
                'ok'      => false,
                'message' => 'Failed to encode export payload',
            ];
        }

        file_put_contents($target, $json);

        return [
            'ok'        => true,
            'post_id'   => $postId,
            'file'      => $target,
            'entity'    => 'helmet',
            'external_id' => $payload['id'],
        ];
    }

    public function exportBrandByPostId(int $postId, ?string $outputFile = null): array
    {
        $built = $this->brands->exportPayloadByPostId($postId);
        if (! ($built['ok'] ?? false)) {
            return [
                'ok' => false,
                'message' => (string) ($built['message'] ?? 'Brand export failed'),
            ];
        }

        $payload = isset($built['payload']) && is_array($built['payload']) ? $built['payload'] : [];
        $externalId = isset($payload['id']) ? (string) $payload['id'] : 'brand-' . (string) $postId;
        $target = $outputFile ?: $this->defaultOutputPath($externalId, 'brand');

        $dir = dirname($target);
        if (! is_dir($dir)) {
            wp_mkdir_p($dir);
        }

        $json = wp_json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if (! is_string($json)) {
            return [
                'ok' => false,
                'message' => 'Failed to encode export payload',
            ];
        }
        file_put_contents($target, $json);

        return [
            'ok' => true,
            'post_id' => $postId,
            'file' => $target,
            'entity' => 'brand',
            'external_id' => $externalId,
        ];
    }

    private function defaultOutputPath(string $externalId, string $entity = 'helmet'): string
    {
        $base = trailingslashit($this->config->dataRoot()) . '_exports';
        $folder = 'helmets';

        if ($entity === 'brand') {
            $base = $this->config->dataRoot();
            $folder = 'brands';
        }

        return trailingslashit($base . '/' . $folder) . sanitize_file_name($externalId) . '.json';
    }

    /**
     * @return array<string,mixed>
     */
    private function buildHelmetPayload(\WP_Post $post): array
    {
        $postId      = (int) $post->ID;
        $externalId  = (string) get_post_meta($postId, '_helmet_unique_id', true);
        $weight      = get_post_meta($postId, 'spec_weight_g', true);
        $material    = (string) get_post_meta($postId, 'spec_shell_material', true);
        $weightLbs   = get_post_meta($postId, 'spec_weight_lbs', true);
        $price       = get_post_meta($postId, 'price_retail_usd', true);
        $asin        = (string) get_post_meta($postId, 'affiliate_asin', true);
        $helmetFamily = (string) get_post_meta($postId, 'helmet_family', true);
        $headShape = (string) get_post_meta($postId, 'head_shape', true);
        $brandId     = (int) get_post_meta($postId, 'rel_brand', true);
        $brandName   = '';

        if ($brandId > 0) {
            $brand = get_post($brandId);
            if ($brand instanceof \WP_Post) {
                $brandName = $brand->post_title;
            }
        }

        // Fallback: if rel_brand meta isn't set, use helmet_brand taxonomy term.
        if ($brandName === '') {
            $brandTerms = get_the_terms($postId, 'helmet_brand');
            if (is_array($brandTerms) && isset($brandTerms[0]) && $brandTerms[0] instanceof \WP_Term) {
                $brandName = (string) $brandTerms[0]->name;
            }
        }

        $helmetType = '';
        $types = get_the_terms($postId, 'helmet_type');
        if (is_array($types) && isset($types[0])) {
            $helmetType = (string) $types[0]->name;
        }

        $certifications = [];
        $certTerms = get_the_terms($postId, 'certification');
        if (is_array($certTerms)) {
            foreach ($certTerms as $term) {
                $certifications[] = (string) $term->name;
            }
        }

        if ($externalId === '') {
            $externalId = sanitize_title($post->post_title) . '-' . (string) $postId;
        }

        $variants = $this->decodeJsonMeta($postId, 'variants_json');
        $productDetails = $this->decodeJsonMeta($postId, 'product_details_json');
        $partNumbers = $this->decodeJsonMeta($postId, 'part_numbers_json');
        $sizingFit = $this->decodeJsonMeta($postId, 'sizing_fit_json');
        $relatedVideos = $this->decodeJsonMeta($postId, 'related_videos_json');
        $geoPricing = $this->decodeJsonMeta($postId, 'geo_pricing_json');
        $geoLegality = $this->decodeJsonMeta($postId, 'geo_legality_json');
        $certDocs = $this->decodeJsonMeta($postId, 'certification_documents_json');
        $features = $this->decodeJsonMeta($postId, 'features_json');

        // Prefer taxonomy tags for features when they exist (fill-missing enriches feature_tag terms,
        // while features_json meta might be empty or stale).
        $featureTagTerms = get_the_terms($postId, 'feature_tag');
        if (is_array($featureTagTerms) && $featureTagTerms !== []) {
            $featureNames = [];
            foreach ($featureTagTerms as $term) {
                if ($term instanceof \WP_Term && $term->name !== '') {
                    $featureNames[] = (string) $term->name;
                }
            }
            $features = array_values(array_unique(array_merge(is_array($features) ? $features : [], $featureNames)));
        }

        $helmetTypes = $this->decodeJsonMeta($postId, 'helmet_types_json');
        $geoMedia = $this->decodeJsonMeta($postId, 'geo_media_json');
        $keySpecs = $this->decodeJsonMeta($postId, 'key_specs_json');
        $compatibleAccessories = $this->decodeJsonMeta($postId, 'compatible_accessories_json');
        $safetyIntelligence = $this->decodeJsonMeta($postId, 'safety_intelligence_json');
        $aeroAcoustic = $this->decodeJsonMeta($postId, 'aero_acoustic_profile_json');
        $techIntegration = $this->decodeJsonMeta($postId, 'tech_integration_json');
        $fitmentCoordinates = $this->decodeJsonMeta($postId, 'fitment_coordinates_json');

        $useCaseTerms = get_the_terms($postId, 'use_case');
        $useCases = [];
        if (is_array($useCaseTerms)) {
            foreach ($useCaseTerms as $term) {
                if ($term instanceof \WP_Term && $term->name !== '') {
                    $useCases[] = (string) $term->name;
                }
            }
        }

        $regionTerms = get_the_terms($postId, 'region');
        $regions = [];
        if (is_array($regionTerms)) {
            foreach ($regionTerms as $term) {
                if ($term instanceof \WP_Term && $term->name !== '') {
                    $regions[] = (string) $term->name;
                }
            }
        }

        $priceRangeTerms = get_the_terms($postId, 'price_range');
        $priceRange = '';
        if (is_array($priceRangeTerms) && isset($priceRangeTerms[0]) && $priceRangeTerms[0] instanceof \WP_Term) {
            $priceRange = (string) $priceRangeTerms[0]->name;
        }

        $marketingDescription = (string) get_post_meta($postId, 'marketing_description', true);
        $technicalAnalysis    = (string) get_post_meta($postId, 'technical_analysis', true);
        $outgoingLinks        = $this->decodeJsonMeta($postId, 'outgoing_internal_links_json');

        $shellSizes = (string) get_post_meta($postId, 'spec_shell_sizes', true);
        $modelYear = (string) get_post_meta($postId, 'model_year', true);

        $specs = [
            'weight_g'       => is_numeric((string) $weight) ? (int) $weight : null,
            'weight_lbs'     => is_numeric((string) $weightLbs) ? (float) $weightLbs : null,
            'material'       => $material,
            'certifications' => $certifications,
        ];
        if ($shellSizes !== '') {
            $specs['shell_sizes'] = is_numeric($shellSizes) ? (int) $shellSizes : $shellSizes;
        }

        $identifiers = [];
        foreach (['ean', 'upc', 'gtin', 'sku', 'mpn', 'fsn'] as $key) {
            $v = (string) get_post_meta($postId, $key, true);
            if ($v !== '') {
                $identifiers[$key] = $v;
            }
        }
        if ($asin !== '') {
            $identifiers['asin'] = $asin;
        }

        $payload = [
            'entity' => 'helmet',
            'id'    => $externalId,
            'title' => (string) $post->post_title,
            'brand' => $brandName,
            'type'  => $helmetType,
            'helmet_family' => $helmetFamily,
            'head_shape' => $headShape,
            'helmet_types' => is_array($helmetTypes) ? $helmetTypes : [],
            'features' => is_array($features) ? $features : [],
            'specs' => $specs,
            'price' => [
                'current'  => is_numeric((string) $price) ? (float) $price : null,
                'currency' => 'USD',
            ],
            'affiliate' => [
                'amazon_asin' => $asin,
            ],
            'variants' => is_array($variants) ? $variants : [],
            'product_details' => is_array($productDetails) ? $productDetails : [],
            'part_numbers' => is_array($partNumbers) ? $partNumbers : [],
            'sizing_fit' => is_array($sizingFit) ? $sizingFit : [],
            'related_videos' => is_array($relatedVideos) ? $relatedVideos : [],
            'geo_pricing' => is_array($geoPricing) ? $geoPricing : [],
            'geo_legality' => is_array($geoLegality) ? $geoLegality : [],
            'certification_documents' => is_array($certDocs) ? $certDocs : [],
        ];

        if ($modelYear !== '') {
            $payload['model_year'] = $modelYear;
        }
        if ($identifiers !== []) {
            $payload['identifiers'] = $identifiers;
        }

        if ($useCases !== []) {
            // Ingestion supports `use_cases` array.
            $payload['use_cases'] = $useCases;
        }
        if ($regions !== []) {
            // Ingestion supports `regions` array.
            $payload['regions'] = $regions;
        }
        if ($priceRange !== '') {
            // Ingestion expects price_range as a string.
            $payload['price_range'] = $priceRange;
        }
        if ($marketingDescription !== '') {
            $payload['marketing_description'] = $marketingDescription;
        }
        if ($technicalAnalysis !== '') {
            $payload['technical_analysis'] = $technicalAnalysis;
        }
        if ($outgoingLinks !== []) {
            $payload['outgoing_internal_links_json'] = $outgoingLinks;
        }

        $affiliateLinks = $this->decodeJsonMeta($postId, 'affiliate_links_json');
        if (is_array($affiliateLinks) && $affiliateLinks !== []) {
            $marketplaceLinks = [];
            foreach ($affiliateLinks as $mpId => $entry) {
                $url = is_array($entry) && isset($entry['url']) ? trim((string) $entry['url']) : '';
                if ($url !== '') {
                    $marketplaceLinks[str_replace('-', '_', (string) $mpId)] = $url;
                }
            }
            if ($marketplaceLinks !== []) {
                $payload['marketplace_links'] = $marketplaceLinks;
            }
        }
        if ($geoMedia !== [] && $geoMedia !== null) {
            $payload['geo_media'] = $geoMedia;
        }
        if ($keySpecs !== [] && is_array($keySpecs)) {
            $payload['key_specs'] = $keySpecs;
        }
        if ($compatibleAccessories !== [] && is_array($compatibleAccessories)) {
            $payload['compatible_accessories'] = $compatibleAccessories;
        }
        if ($safetyIntelligence !== []) {
            $payload['safety_intelligence'] = $safetyIntelligence;
        }
        if ($aeroAcoustic !== []) {
            $payload['aero_acoustic_profile'] = $aeroAcoustic;
        }
        if ($techIntegration !== []) {
            $payload['tech_integration'] = $techIntegration;
        }
        if ($fitmentCoordinates !== []) {
            $payload['fitment_coordinates'] = $fitmentCoordinates;
        }

        return $payload;
    }

    /**
     * @return array<int|string,mixed>
     */
    private function decodeJsonMeta(int $postId, string $metaKey): array
    {
        $raw = (string) get_post_meta($postId, $metaKey, true);
        if ($raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }
}
