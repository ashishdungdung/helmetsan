<?php

declare(strict_types=1);

namespace Helmetsan\Core\Seo;

use WP_Post;
use WP_Term;

/**
 * Seeds Yoast SEO meta (title, meta description, focus keyword) for CPTs and taxonomy terms.
 * Compatible with Yoast SEO; follows best practices for length and keyword placement.
 * Focus keyphrase is always stored in lowercase. Meta descriptions should be SEO-strong (primary keyword, CTA).
 *
 * @see docs/seo-seed-plan.md
 * @see docs/seo-and-ai-coverage.md
 */
final class YoastSeoSeeder
{
    private const TITLE_MAX = 60;
    private const META_DESC_MAX = 160;

    private const YOAST_TITLE = '_yoast_wpseo_title';
    private const YOAST_METADESC = '_yoast_wpseo_metadesc';
    private const YOAST_FOCUSKW = '_yoast_wpseo_focuskw';

    public function __construct(
        private readonly ?AiSeoDescriptionProvider $aiProvider = null
    ) {
    }

    public function seedSinglePost(int $postId): bool
    {
        $post = get_post($postId);
        if (! $post instanceof WP_Post) {
            return false;
        }

        $type = $post->post_type;
        $triple = null;

        if ($type === 'helmet') {
            $triple = $this->buildHelmetSeo($postId, $post);
        } elseif ($type === 'brand') {
            $triple = $this->buildBrandSeo($postId, $post);
        } elseif ($type === 'accessory') {
            $triple = $this->buildAccessorySeo($postId, $post);
        }

        if ($triple === null) {
            return false;
        }

        update_post_meta($postId, self::YOAST_TITLE, $triple['title']);
        update_post_meta($postId, self::YOAST_METADESC, $triple['metadesc']);
        
        $focuskw = $this->normalizeFocusKw(trim((string) ($triple['focuskw'] ?? '')));
        if ($focuskw === '') {
            $focuskw = $this->normalizeFocusKw($this->truncate((string) $post->post_title, 60));
        }
        update_post_meta($postId, self::YOAST_FOCUSKW, $focuskw);

        return true;
    }

    public function seedHelmets(int $limit = 0, int $offset = 0, bool $dryRun = false): array
    {
        $query = new \WP_Query([
            'post_type'      => 'helmet',
            'post_status'    => 'publish',
            'posts_per_page' => $limit > 0 ? $limit : -1,
            'offset'         => $offset,
            'fields'         => 'ids',
            'orderby'        => 'ID',
            'order'         => 'ASC',
        ]);
        $ids = is_array($query->posts) ? array_map('intval', $query->posts) : [];
        $updated = 0;
        $errors = [];

        foreach ($ids as $postId) {
            $post = get_post($postId);
            if (! $post instanceof WP_Post) {
                continue;
            }
            $triple = $this->buildHelmetSeo($postId, $post);
            if ($triple === null) {
                continue;
            }
            if (! $dryRun) {
                update_post_meta($postId, self::YOAST_TITLE, $triple['title']);
                update_post_meta($postId, self::YOAST_METADESC, $triple['metadesc']);
                $focuskw = $this->normalizeFocusKw(trim((string) ($triple['focuskw'] ?? '')));
                if ($focuskw === '') {
                    $focuskw = $this->normalizeFocusKw($this->truncate((string) $post->post_title, 60));
                }
                update_post_meta($postId, self::YOAST_FOCUSKW, $focuskw);
            }
            $updated++;
        }

        return ['updated' => $updated, 'total' => count($ids), 'dry_run' => $dryRun];
    }

    public function seedBrands(int $limit = 0, int $offset = 0, bool $dryRun = false): array
    {
        $query = new \WP_Query([
            'post_type'      => 'brand',
            'post_status'    => 'publish',
            'posts_per_page' => $limit > 0 ? $limit : -1,
            'offset'         => $offset,
            'fields'         => 'ids',
            'orderby'        => 'title',
            'order'         => 'ASC',
        ]);
        $ids = is_array($query->posts) ? array_map('intval', $query->posts) : [];
        $updated = 0;

        foreach ($ids as $postId) {
            $post = get_post($postId);
            if (! $post instanceof WP_Post) {
                continue;
            }
            $triple = $this->buildBrandSeo($postId, $post);
            if ($triple === null) {
                continue;
            }
            if (! $dryRun) {
                update_post_meta($postId, self::YOAST_TITLE, $triple['title']);
                update_post_meta($postId, self::YOAST_METADESC, $triple['metadesc']);
                $focuskw = $this->normalizeFocusKw(trim((string) ($triple['focuskw'] ?? '')));
                if ($focuskw === '') {
                    $focuskw = $this->normalizeFocusKw($this->truncate((string) $post->post_title . ' helmets', 60));
                }
                update_post_meta($postId, self::YOAST_FOCUSKW, $focuskw);
            }
            $updated++;
        }

        return ['updated' => $updated, 'total' => count($ids), 'dry_run' => $dryRun];
    }

    public function seedAccessories(int $limit = 0, int $offset = 0, bool $dryRun = false): array
    {
        $query = new \WP_Query([
            'post_type'      => 'accessory',
            'post_status'    => 'publish',
            'posts_per_page' => $limit > 0 ? $limit : -1,
            'offset'         => $offset,
            'fields'         => 'ids',
            'orderby'        => 'title',
            'order'         => 'ASC',
        ]);
        $ids = is_array($query->posts) ? array_map('intval', $query->posts) : [];
        $updated = 0;

        foreach ($ids as $postId) {
            $post = get_post($postId);
            if (! $post instanceof WP_Post) {
                continue;
            }
            $triple = $this->buildAccessorySeo($postId, $post);
            if ($triple === null) {
                continue;
            }
            if (! $dryRun) {
                update_post_meta($postId, self::YOAST_TITLE, $triple['title']);
                update_post_meta($postId, self::YOAST_METADESC, $triple['metadesc']);
                $focuskw = $this->normalizeFocusKw(trim((string) ($triple['focuskw'] ?? '')));
                if ($focuskw === '') {
                    $focuskw = $this->normalizeFocusKw($this->truncate((string) $post->post_title, 60));
                }
                update_post_meta($postId, self::YOAST_FOCUSKW, $focuskw);
            }
            $updated++;
        }

        return ['updated' => $updated, 'total' => count($ids), 'dry_run' => $dryRun];
    }

    /** Focus keyphrase: always lowercase for consistency. */
    private function normalizeFocusKw(string $s): string
    {
        return strtolower(trim($s));
    }

    /**
     * Seed Yoast SEO meta for all terms in a taxonomy (archive pages).
     * Uses update_term_meta (Yoast 14+ reads from wp_termmeta).
     *
     * @return array{updated: int, total: int, dry_run: bool}
     */
    public function seedTermsForTaxonomy(string $taxonomy, int $limit = 0, int $offset = 0, bool $dryRun = false): array
    {
        $terms = get_terms([
            'taxonomy'   => $taxonomy,
            'hide_empty' => false,
            'number'     => $limit > 0 ? $limit : 0,
            'offset'     => $offset,
        ]);
        if (is_wp_error($terms) || ! is_array($terms)) {
            return ['updated' => 0, 'total' => 0, 'dry_run' => $dryRun];
        }
        $total = count($terms);
        $updated = 0;
        foreach ($terms as $term) {
            if (! $term instanceof WP_Term) {
                continue;
            }
            $triple = $this->buildTermSeo($term, $taxonomy);
            if ($triple === null) {
                continue;
            }
            if (! $dryRun) {
                update_term_meta($term->term_id, self::YOAST_TITLE, $triple['title']);
                update_term_meta($term->term_id, self::YOAST_METADESC, $triple['metadesc']);
                update_term_meta($term->term_id, self::YOAST_FOCUSKW, $this->normalizeFocusKw($triple['focuskw']));
            }
            $updated++;
        }
        return ['updated' => $updated, 'total' => $total, 'dry_run' => $dryRun];
    }

    /**
     * @return array{title: string, metadesc: string, focuskw: string}|null
     */
    private function buildTermSeo(WP_Term $term, string $taxonomy): ?array
    {
        $name = (string) $term->name;
        $slug = (string) $term->slug;
        $taxLabel = $this->taxonomySeoLabel($taxonomy);
        $seoTitle = $name . ' | ' . $taxLabel . ' | Helmetsan';
        $seoTitle = $this->truncate($seoTitle, self::TITLE_MAX);
        $metadesc = 'Browse ' . $name . ' – compare and find the best options at Helmetsan.';
        $metadesc = $this->truncate($metadesc, self::META_DESC_MAX);
        $focuskw = $this->normalizeFocusKw($name . ' ' . str_replace('-', ' ', $slug));
        $focuskw = $this->truncate($focuskw, 60);
        return ['title' => $seoTitle, 'metadesc' => $metadesc, 'focuskw' => $focuskw];
    }

    private function taxonomySeoLabel(string $taxonomy): string
    {
        return match ($taxonomy) {
            'helmet_type' => 'Helmet Types',
            'region' => 'Regions',
            'certification' => 'Certifications',
            'feature_tag' => 'Features',
            'accessory_category' => 'Accessory Categories',
            'helmet_brand' => 'Brands',
            'use_case' => 'Use Cases',
            'price_range' => 'Price Ranges',
            default => ucfirst(str_replace('_', ' ', $taxonomy)),
        };
    }

    /**
     * Taxonomies that have archive pages and get term SEO.
     * @return list<string>
     */
    public static function getTaxonomiesForTermSeo(): array
    {
        return ['helmet_type', 'region', 'certification', 'feature_tag', 'accessory_category', 'helmet_brand', 'use_case', 'price_range'];
    }

    /**
     * @return array{title: string, metadesc: string, focuskw: string}|null
     */
    private function buildHelmetSeo(int $postId, WP_Post $post): ?array
    {
        $brandName = $this->getBrandNameForHelmet($postId);
        $typeLabel = $this->getFirstTermName($postId, 'helmet_type');
        $certs = $this->getTermNames($postId, 'certification');
        $features = $this->getTermNames($postId, 'feature_tag');
        $price = get_post_meta($postId, 'price_retail_usd', true);
        $family = (string) get_post_meta($postId, 'helmet_family', true);
        $useCase = (string) get_post_meta($postId, 'use_case', true);
        $titleRaw = (string) $post->post_title;

        $typePart = $typeLabel !== '' ? $typeLabel : 'Motorcycle';
        if (strtolower($typePart) !== 'helmet' && strpos(strtolower($typePart), 'helmet') === false) {
            $typePart .= ' Helmet';
        }

        $seoTitle = $brandName !== ''
            ? $brandName . ' ' . $titleRaw . ' | ' . $typePart . ' | Helmetsan'
            : $titleRaw . ' | ' . $typePart . ' | Helmetsan';
        $seoTitle = $this->truncate($seoTitle, self::TITLE_MAX);

        $certStr = $certs !== [] ? implode(', ', array_slice($certs, 0, 3)) : '';
        $priceStr = is_numeric((string) $price) ? '$' . number_format((float) $price, 0) : '';
        $productPhrase = ($brandName !== '' ? $brandName . ' ' : '') . $titleRaw . ' ' . strtolower($typePart);

        $metadesc = null;
        if ($this->aiProvider !== null) {
            $metadesc = $this->aiProvider->generateForHelmet($postId, [
                'brand' => $brandName,
                'title' => $titleRaw,
                'type' => strtolower($typePart),
                'certifications' => $certs,
                'price' => $priceStr !== '' ? $priceStr : null,
                'helmet_family' => $family !== '' ? $family : null,
                'feature_tags' => $features,
                'use_case' => $useCase !== '' ? $useCase : null,
            ]);
        }
        if ($metadesc === null || $metadesc === '') {
            $metadesc = 'Compare the ' . $productPhrase . ' – specs, safety ratings & sizes.';
            if ($certStr !== '') {
                $metadesc .= ' ' . $certStr . '.';
            }
            if ($family !== '') {
                $metadesc .= ' ' . $family . ' series.';
            }
            if ($priceStr !== '') {
                $metadesc .= ' From ' . $priceStr . '.';
            }
            $metadesc .= ' Find the best deal at Helmetsan.';
            $metadesc = $this->truncate($metadesc, self::META_DESC_MAX);
        }

        $focuskw = $brandName !== '' ? $brandName . ' ' . $titleRaw : $titleRaw;
        if ($typeLabel !== '' && strlen($focuskw) + strlen($typeLabel) + 1 <= 60) {
            $focuskw = $focuskw . ' ' . $typeLabel;
        }
        $focuskw = $this->normalizeFocusKw($this->truncate($focuskw, 60));

        return ['title' => $seoTitle, 'metadesc' => $metadesc, 'focuskw' => $focuskw];
    }

    /**
     * @return array{title: string, metadesc: string, focuskw: string}|null
     */
    private function buildBrandSeo(int $postId, WP_Post $post): ?array
    {
        $brandName = (string) $post->post_title;
        $country = (string) get_post_meta($postId, 'brand_origin_country', true);
        $motto = (string) get_post_meta($postId, 'brand_motto', true);
        $story = (string) get_post_meta($postId, 'brand_story', true);
        $countryPart = $country !== '' ? ' (' . $country . ')' : '';

        $seoTitle = $brandName . ' Helmets | Official Hub & Reviews | Helmetsan';
        $seoTitle = $this->truncate($seoTitle, self::TITLE_MAX);

        $metadesc = null;
        if ($this->aiProvider !== null) {
            $metadesc = $this->aiProvider->generateForBrand($postId, [
                'brand' => $brandName,
                'country' => $country,
                'motto' => $motto !== '' ? $motto : null,
                'story_snippet' => $story !== '' ? wp_trim_words($story, 15) : null,
            ]);
        }
        if ($metadesc === null || $metadesc === '') {
            $metadesc = 'Your hub for ' . $brandName . $countryPart . ' helmets: full face, modular & more. Compare prices, certifications & where to buy. Official reviews at Helmetsan.';
            $metadesc = $this->truncate($metadesc, self::META_DESC_MAX);
        }

        $focuskw = $this->normalizeFocusKw($brandName . ' helmets');

        return ['title' => $seoTitle, 'metadesc' => $metadesc, 'focuskw' => $focuskw];
    }

    /**
     * @return array{title: string, metadesc: string, focuskw: string}|null
     */
    private function buildAccessorySeo(int $postId, WP_Post $post): ?array
    {
        $titleRaw = (string) $post->post_title;
        $category = (string) get_post_meta($postId, 'accessory_parent_category', true);
        if ($category === '') {
            $category = (string) get_post_meta($postId, 'accessory_subcategory', true);
        }
        if ($category === '') {
            $category = (string) get_post_meta($postId, 'accessory_type', true);
        }
        if ($category === '') {
            $terms = get_the_terms($postId, 'accessory_category');
            if (is_array($terms) && $terms !== []) {
                $first = reset($terms);
                $category = $first instanceof WP_Term ? (string) $first->name : '';
            }
        }
        $categoryPart = $category !== '' ? $category : 'Motorcycle Accessory';

        $seoTitle = $titleRaw . ' | ' . $categoryPart . ' | Helmetsan';
        $seoTitle = $this->truncate($seoTitle, self::TITLE_MAX);

        $metadesc = null;
        if ($this->aiProvider !== null) {
            $metadesc = $this->aiProvider->generateForAccessory($postId, [
                'title' => $titleRaw,
                'category' => $categoryPart,
            ]);
        }
        if ($metadesc === null || $metadesc === '') {
            $metadesc = 'Shop ' . $titleRaw . ' – ' . $categoryPart . ' for motorcycle helmets. Compatibility, reviews & buying guide at Helmetsan.';
            $metadesc = $this->truncate($metadesc, self::META_DESC_MAX);
        }

        $focuskw = $categoryPart !== '' && $categoryPart !== 'Motorcycle Accessory'
            ? $titleRaw . ' ' . $categoryPart
            : $titleRaw;
        $focuskw = $this->normalizeFocusKw($this->truncate($focuskw, 60));

        return ['title' => $seoTitle, 'metadesc' => $metadesc, 'focuskw' => $focuskw];
    }

    /**
     * Seed Yoast SEO for a generic CPT (safety_standard, dealer, distributor, technology, motorcycle, comparison, recommendation).
     *
     * @return array{updated: int, total: int, dry_run: bool}
     */
    public function seedCpt(string $postType, int $limit = 0, int $offset = 0, bool $dryRun = false): array
    {
        $query = new \WP_Query([
            'post_type'      => $postType,
            'post_status'    => 'publish',
            'posts_per_page' => $limit > 0 ? $limit : -1,
            'offset'         => $offset,
            'fields'         => 'ids',
            'orderby'        => 'title',
            'order'          => 'ASC',
        ]);
        $ids = is_array($query->posts) ? array_map('intval', $query->posts) : [];
        $updated = 0;
        foreach ($ids as $postId) {
            $post = get_post($postId);
            if (! $post instanceof WP_Post) {
                continue;
            }
            $triple = $this->buildSeoForGenericCpt($postId, $post, $postType);
            if ($triple === null) {
                continue;
            }
            if (! $dryRun) {
                update_post_meta($postId, self::YOAST_TITLE, $triple['title']);
                update_post_meta($postId, self::YOAST_METADESC, $triple['metadesc']);
                update_post_meta($postId, self::YOAST_FOCUSKW, $this->normalizeFocusKw($triple['focuskw']));
            }
            $updated++;
        }
        return ['updated' => $updated, 'total' => count($ids), 'dry_run' => $dryRun];
    }

    /**
     * @return array{title: string, metadesc: string, focuskw: string}|null
     */
    private function buildSeoForGenericCpt(int $postId, WP_Post $post, string $postType): ?array
    {
        $title = (string) $post->post_title;
        $label = $this->cptSeoLabel($postType);
        $context = $this->getGenericCptSeoContext($postId, $postType);
        $seoTitle = $title . ' | ' . $label . ' | Helmetsan';
        $seoTitle = $this->truncate($seoTitle, self::TITLE_MAX);
        $metadesc = $context !== ''
            ? $title . ' – ' . $context . '. ' . $label . ' at Helmetsan.'
            : 'Browse ' . $title . ' – ' . $label . '. Find the best options at Helmetsan.';
        $metadesc = $this->truncate($metadesc, self::META_DESC_MAX);
        $focuskw = $context !== ''
            ? $this->normalizeFocusKw($this->truncate($title . ' ' . $context . ' ' . strtolower($label), 60))
            : $this->normalizeFocusKw($this->truncate($title . ' ' . str_replace(' ', ' ', strtolower($label)), 60));
        return ['title' => $seoTitle, 'metadesc' => $metadesc, 'focuskw' => $focuskw];
    }

    /**
     * Build a short context string from post meta and taxonomy terms for generic CPT SEO.
     */
    private function getGenericCptSeoContext(int $postId, string $postType): string
    {
        $parts = [];
        switch ($postType) {
            case 'safety_standard':
                $parts = array_merge(
                    $this->getTermNames($postId, 'region'),
                    $this->getTermNames($postId, 'certification')
                );
                if ($parts === []) {
                    $issuing = (string) get_post_meta($postId, 'standard_issuing_body', true);
                    if ($issuing !== '') {
                        $parts[] = $issuing;
                    }
                }
                break;
            case 'dealer':
                $parts = $this->getTermNames($postId, 'region');
                if ($parts === []) {
                    $country = (string) get_post_meta($postId, 'dealer_country_code', true);
                    $regionCode = (string) get_post_meta($postId, 'dealer_region_code', true);
                    if ($country !== '') {
                        $parts[] = $country;
                    }
                    if ($regionCode !== '') {
                        $parts[] = $regionCode;
                    }
                }
                break;
            case 'distributor':
                $parts = $this->getTermNames($postId, 'region');
                if ($parts === []) {
                    $json = get_post_meta($postId, 'distributor_regions_json', true);
                    if (is_string($json)) {
                        $decoded = json_decode($json, true);
                        if (is_array($decoded)) {
                            $parts = array_slice(array_map('strval', $decoded), 0, 3);
                        }
                    }
                    if ($parts === []) {
                        $country = (string) get_post_meta($postId, 'distributor_country_code', true);
                        if ($country !== '') {
                            $parts[] = $country;
                        }
                    }
                }
                break;
            case 'technology':
                $parts = $this->getTermNames($postId, 'feature_tag');
                break;
            case 'comparison':
            case 'recommendation':
                $parts = $this->getTermNames($postId, 'region');
                if ($parts === [] && $postType === 'recommendation') {
                    $region = (string) get_post_meta($postId, 'recommendation_region', true);
                    if ($region !== '') {
                        $parts[] = $region;
                    }
                }
                break;
            case 'motorcycle':
                $parts = $this->getTermNames($postId, 'region');
                break;
            default:
                $parts = $this->getTermNames($postId, 'region');
                break;
        }
        $parts = array_filter(array_map('trim', $parts));
        return implode(', ', array_slice(array_unique($parts), 0, 3));
    }

    private function cptSeoLabel(string $postType): string
    {
        return match ($postType) {
            'safety_standard' => 'Safety Standards',
            'dealer' => 'Dealers',
            'distributor' => 'Distributors',
            'technology' => 'Technologies',
            'motorcycle' => 'Motorcycles',
            'comparison' => 'Comparisons',
            'recommendation' => 'Recommendations',
            default => ucfirst(str_replace('_', ' ', $postType)),
        };
    }

    /** @return list<string> */
    public static function getOtherCptTypesForSeo(): array
    {
        return ['safety_standard', 'dealer', 'distributor', 'technology', 'motorcycle', 'comparison', 'recommendation'];
    }

    private function getBrandNameForHelmet(int $helmetId): string
    {
        $brandId = (int) get_post_meta($helmetId, 'rel_brand', true);
        if ($brandId <= 0) {
            return '';
        }
        $brand = get_post($brandId);
        return $brand instanceof WP_Post ? (string) $brand->post_title : '';
    }

    private function getFirstTermName(int $postId, string $taxonomy): string
    {
        $terms = get_the_terms($postId, $taxonomy);
        if (! is_array($terms) || $terms === []) {
            return '';
        }
        $first = reset($terms);
        return $first instanceof WP_Term ? (string) $first->name : '';
    }

    /** @var array<string, list<string>> request cache: "postId_taxonomy" => term names */
    private array $termNamesCache = [];

    /**
     * @return list<string>
     */
    private function getTermNames(int $postId, string $taxonomy): array
    {
        $key = $postId . '_' . $taxonomy;
        if (isset($this->termNamesCache[$key])) {
            return $this->termNamesCache[$key];
        }
        $terms = get_the_terms($postId, $taxonomy);
        if (! is_array($terms) || $terms === []) {
            $this->termNamesCache[$key] = [];
            return [];
        }
        $out = [];
        foreach ($terms as $t) {
            if ($t instanceof WP_Term) {
                $out[] = (string) $t->name;
            }
        }
        $this->termNamesCache[$key] = $out;
        return $out;
    }

    private function truncate(string $s, int $maxLen): string
    {
        $s = trim($s);
        if (strlen($s) <= $maxLen) {
            return $s;
        }
        $s = substr($s, 0, $maxLen - 3);
        $last = strrpos($s, ' ');
        if ($last !== false && $last > (int) ($maxLen * 0.6)) {
            return substr($s, 0, $last) . '...';
        }
        return $s . '...';
    }

    /**
     * Run SEO check across posts and terms with a limit; return summary for admin UI.
     * Caps total items to avoid timeouts (e.g. max 200 posts + 80 terms).
     *
     * @return array{total_checked: int, total_with_issues: int, by_issue: array<string, int>, message: string}
     */
    public function runCheckSummary(int $postLimit = 200, int $termLimitPerTax = 50): array
    {
        $seeder = new self(null);
        $totalChecked = 0;
        $totalWithIssues = 0;
        $byIssue = [];
        $primary = ['helmet', 'brand', 'accessory'];
        $other = self::getOtherCptTypesForSeo();
        $postTypes = array_merge($primary, $other);
        $perType = max(1, (int) floor($postLimit / count($postTypes)));
        foreach ($postTypes as $type) {
            if ($totalChecked >= $postLimit) {
                break;
            }
            $query = new \WP_Query([
                'post_type' => $type,
                'post_status' => 'publish',
                'posts_per_page' => $perType,
                'fields' => 'ids',
            ]);
            $ids = is_array($query->posts) ? array_map('intval', $query->posts) : [];
            foreach ($ids as $id) {
                if ($totalChecked >= $postLimit) {
                    break;
                }
                $totalChecked++;
                $check = $seeder->checkPostSeo($id);
                if ($check['issues'] !== []) {
                    $totalWithIssues++;
                    foreach ($check['issues'] as $issue) {
                        $byIssue[$issue] = ($byIssue[$issue] ?? 0) + 1;
                    }
                }
            }
        }
        $taxonomies = self::getTaxonomiesForTermSeo();
        $termCap = min(80, count($taxonomies) * $termLimitPerTax);
        $termsChecked = 0;
        foreach ($taxonomies as $tax) {
            if ($termsChecked >= $termCap) {
                break;
            }
            $terms = get_terms(['taxonomy' => $tax, 'hide_empty' => false, 'number' => $termLimitPerTax, 'fields' => 'ids']);
            if (! is_array($terms)) {
                continue;
            }
            foreach ($terms as $termId) {
                if ($termsChecked >= $termCap) {
                    break;
                }
                $termsChecked++;
                $totalChecked++;
                $check = $seeder->checkTermSeo((int) $termId, $tax);
                if ($check['issues'] !== []) {
                    $totalWithIssues++;
                    foreach ($check['issues'] as $issue) {
                        $byIssue[$issue] = ($byIssue[$issue] ?? 0) + 1;
                    }
                }
            }
        }
        $message = $totalWithIssues === 0
            ? __('No SEO issues found in sampled items.', 'helmetsan-core')
            : sprintf(
                /* translators: 1: number with issues, 2: total checked */
                __('%1$d of %2$d sampled items have SEO issues. Run "Fix SEO" or use CLI: wp helmetsan seo update --scope=all', 'helmetsan-core'),
                $totalWithIssues,
                $totalChecked
            );
        return ['total_checked' => $totalChecked, 'total_with_issues' => $totalWithIssues, 'by_issue' => $byIssue, 'message' => $message];
    }

    /**
     * Run SEO fix across posts and terms with a limit; return count fixed for admin UI.
     *
     * @return array{fixed: int, total_processed: int, message: string}
     */
    public function runFixSummary(int $postLimit = 200, int $termLimitPerTax = 50): array
    {
        $seeder = new self(null);
        $fixed = 0;
        $totalProcessed = 0;
        $opts = ['lowercase_focuskw' => true, 'truncate_metadesc' => true, 'truncate_title' => true];
        $primary = ['helmet', 'brand', 'accessory'];
        $other = self::getOtherCptTypesForSeo();
        $postTypes = array_merge($primary, $other);
        $perType = max(1, (int) floor($postLimit / count($postTypes)));
        foreach ($postTypes as $type) {
            if ($totalProcessed >= $postLimit) {
                break;
            }
            $query = new \WP_Query([
                'post_type' => $type,
                'post_status' => 'publish',
                'posts_per_page' => $perType,
                'fields' => 'ids',
            ]);
            $ids = is_array($query->posts) ? array_map('intval', $query->posts) : [];
            foreach ($ids as $id) {
                if ($totalProcessed >= $postLimit) {
                    break;
                }
                $totalProcessed++;
                $result = $seeder->fixPostSeo($id, $opts);
                $fixed += count($result['fixed']);
            }
        }
        $termCap = min(80, count(self::getTaxonomiesForTermSeo()) * $termLimitPerTax);
        $termsProcessed = 0;
        foreach (self::getTaxonomiesForTermSeo() as $tax) {
            if ($termsProcessed >= $termCap) {
                break;
            }
            $terms = get_terms(['taxonomy' => $tax, 'hide_empty' => false, 'number' => $termLimitPerTax, 'fields' => 'ids']);
            if (! is_array($terms)) {
                continue;
            }
            foreach ($terms as $termId) {
                if ($termsProcessed >= $termCap) {
                    break;
                }
                $termsProcessed++;
                $totalProcessed++;
                $result = $seeder->fixTermSeo((int) $termId, $tax, $opts);
                $fixed += count($result['fixed']);
            }
        }
        $message = sprintf(
            /* translators: 1: number of fields fixed, 2: total items processed */
            __('Fixed %1$d SEO meta fields across %2$d items.', 'helmetsan-core'),
            $fixed,
            $totalProcessed
        );
        return ['fixed' => $fixed, 'total_processed' => $totalProcessed, 'message' => $message];
    }

    /**
     * Check Yoast SEO meta for a post. Returns issues and current values.
     *
     * @return array{issues: list<string>, title: string, metadesc: string, focuskw: string}
     */
    public function checkPostSeo(int $postId): array
    {
        $title = (string) get_post_meta($postId, self::YOAST_TITLE, true);
        $metadesc = (string) get_post_meta($postId, self::YOAST_METADESC, true);
        $focuskw = (string) get_post_meta($postId, self::YOAST_FOCUSKW, true);
        $issues = [];
        if ($title === '') {
            $issues[] = 'missing_title';
        } elseif (strlen($title) > self::TITLE_MAX) {
            $issues[] = 'title_too_long';
        }
        if ($metadesc === '') {
            $issues[] = 'missing_metadesc';
        } elseif (strlen($metadesc) > self::META_DESC_MAX) {
            $issues[] = 'metadesc_too_long';
        }
        if ($focuskw === '') {
            $issues[] = 'missing_focuskw';
        } elseif ($focuskw !== $this->normalizeFocusKw($focuskw)) {
            $issues[] = 'focuskw_not_lowercase';
        }
        return ['issues' => $issues, 'title' => $title, 'metadesc' => $metadesc, 'focuskw' => $focuskw];
    }

    /**
     * Fix Yoast SEO meta for a post (lowercase focuskw, truncate overlong meta).
     *
     * @param array{lowercase_focuskw?: bool, truncate_metadesc?: bool, truncate_title?: bool} $opts
     * @return array{fixed: list<string>}
     */
    public function fixPostSeo(int $postId, array $opts = []): array
    {
        $lowercase = $opts['lowercase_focuskw'] ?? true;
        $truncateDesc = $opts['truncate_metadesc'] ?? true;
        $truncateTitle = $opts['truncate_title'] ?? true;
        $fixed = [];
        $focuskw = (string) get_post_meta($postId, self::YOAST_FOCUSKW, true);
        if ($focuskw !== '' && $focuskw !== $this->normalizeFocusKw($focuskw)) {
            if ($lowercase) {
                update_post_meta($postId, self::YOAST_FOCUSKW, $this->normalizeFocusKw($focuskw));
                $fixed[] = 'focuskw';
            }
        }
        $metadesc = (string) get_post_meta($postId, self::YOAST_METADESC, true);
        if ($metadesc !== '' && strlen($metadesc) > self::META_DESC_MAX && $truncateDesc) {
            update_post_meta($postId, self::YOAST_METADESC, $this->truncate($metadesc, self::META_DESC_MAX));
            $fixed[] = 'metadesc';
        }
        $title = (string) get_post_meta($postId, self::YOAST_TITLE, true);
        if ($title !== '' && strlen($title) > self::TITLE_MAX && $truncateTitle) {
            update_post_meta($postId, self::YOAST_TITLE, $this->truncate($title, self::TITLE_MAX));
            $fixed[] = 'title';
        }
        return ['fixed' => $fixed];
    }

    /**
     * Check Yoast SEO meta for a term.
     *
     * @return array{issues: list<string>, title: string, metadesc: string, focuskw: string}
     */
    public function checkTermSeo(int $termId, string $taxonomy): array
    {
        $title = (string) get_term_meta($termId, self::YOAST_TITLE, true);
        $metadesc = (string) get_term_meta($termId, self::YOAST_METADESC, true);
        $focuskw = (string) get_term_meta($termId, self::YOAST_FOCUSKW, true);
        $issues = [];
        if ($title === '') {
            $issues[] = 'missing_title';
        } elseif (strlen($title) > self::TITLE_MAX) {
            $issues[] = 'title_too_long';
        }
        if ($metadesc === '') {
            $issues[] = 'missing_metadesc';
        } elseif (strlen($metadesc) > self::META_DESC_MAX) {
            $issues[] = 'metadesc_too_long';
        }
        if ($focuskw === '') {
            $issues[] = 'missing_focuskw';
        } elseif ($focuskw !== $this->normalizeFocusKw($focuskw)) {
            $issues[] = 'focuskw_not_lowercase';
        }
        return ['issues' => $issues, 'title' => $title, 'metadesc' => $metadesc, 'focuskw' => $focuskw];
    }

    /**
     * Fix Yoast SEO meta for a term.
     *
     * @param array{lowercase_focuskw?: bool, truncate_metadesc?: bool, truncate_title?: bool} $opts
     * @return array{fixed: list<string>}
     */
    public function fixTermSeo(int $termId, string $taxonomy, array $opts = []): array
    {
        $lowercase = $opts['lowercase_focuskw'] ?? true;
        $truncateDesc = $opts['truncate_metadesc'] ?? true;
        $truncateTitle = $opts['truncate_title'] ?? true;
        $fixed = [];
        $focuskw = (string) get_term_meta($termId, self::YOAST_FOCUSKW, true);
        if ($focuskw !== '' && $focuskw !== $this->normalizeFocusKw($focuskw)) {
            if ($lowercase) {
                update_term_meta($termId, self::YOAST_FOCUSKW, $this->normalizeFocusKw($focuskw));
                $fixed[] = 'focuskw';
            }
        }
        $metadesc = (string) get_term_meta($termId, self::YOAST_METADESC, true);
        if ($metadesc !== '' && strlen($metadesc) > self::META_DESC_MAX && $truncateDesc) {
            update_term_meta($termId, self::YOAST_METADESC, $this->truncate($metadesc, self::META_DESC_MAX));
            $fixed[] = 'metadesc';
        }
        $title = (string) get_term_meta($termId, self::YOAST_TITLE, true);
        if ($title !== '' && strlen($title) > self::TITLE_MAX && $truncateTitle) {
            update_term_meta($termId, self::YOAST_TITLE, $this->truncate($title, self::TITLE_MAX));
            $fixed[] = 'title';
        }
        return ['fixed' => $fixed];
    }
}
