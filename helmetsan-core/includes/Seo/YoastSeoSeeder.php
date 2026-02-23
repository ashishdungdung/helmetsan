<?php

declare(strict_types=1);

namespace Helmetsan\Core\Seo;

use WP_Post;
use WP_Term;

/**
 * Seeds Yoast SEO meta (title, meta description, focus keyword) for helmets, brands, and accessories.
 * Compatible with Yoast SEO; follows best practices for length and keyword placement.
 *
 * @see docs/seo-seed-plan.md
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
                update_post_meta($postId, self::YOAST_FOCUSKW, $triple['focuskw']);
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
                update_post_meta($postId, self::YOAST_FOCUSKW, $triple['focuskw']);
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
                update_post_meta($postId, self::YOAST_FOCUSKW, $triple['focuskw']);
            }
            $updated++;
        }

        return ['updated' => $updated, 'total' => count($ids), 'dry_run' => $dryRun];
    }

    /**
     * @return array{title: string, metadesc: string, focuskw: string}|null
     */
    private function buildHelmetSeo(int $postId, WP_Post $post): ?array
    {
        $brandName = $this->getBrandNameForHelmet($postId);
        $typeLabel = $this->getFirstTermName($postId, 'helmet_type');
        $certs = $this->getTermNames($postId, 'certification');
        $price = get_post_meta($postId, 'price_retail_usd', true);
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
            ]);
        }
        if ($metadesc === null || $metadesc === '') {
            $metadesc = 'Compare the ' . $productPhrase . ' – specs, safety ratings & sizes.';
            if ($certStr !== '') {
                $metadesc .= ' ' . $certStr . '.';
            }
            if ($priceStr !== '') {
                $metadesc .= ' From ' . $priceStr . '.';
            }
            $metadesc .= ' Find the best deal at Helmetsan.';
            $metadesc = $this->truncate($metadesc, self::META_DESC_MAX);
        }

        $focuskw = $brandName !== '' ? $brandName . ' ' . $titleRaw : $titleRaw;
        $focuskw = $this->truncate($focuskw, 60);

        return ['title' => $seoTitle, 'metadesc' => $metadesc, 'focuskw' => $focuskw];
    }

    /**
     * @return array{title: string, metadesc: string, focuskw: string}|null
     */
    private function buildBrandSeo(int $postId, WP_Post $post): ?array
    {
        $brandName = (string) $post->post_title;
        $country = (string) get_post_meta($postId, 'brand_origin_country', true);
        $countryPart = $country !== '' ? ' (' . $country . ')' : '';

        $seoTitle = $brandName . ' Helmets | Official Hub & Reviews | Helmetsan';
        $seoTitle = $this->truncate($seoTitle, self::TITLE_MAX);

        $metadesc = null;
        if ($this->aiProvider !== null) {
            $metadesc = $this->aiProvider->generateForBrand($postId, [
                'brand' => $brandName,
                'country' => $country,
            ]);
        }
        if ($metadesc === null || $metadesc === '') {
            $metadesc = 'Your hub for ' . $brandName . $countryPart . ' helmets: full face, modular & more. Compare prices, certifications & where to buy. Official reviews at Helmetsan.';
            $metadesc = $this->truncate($metadesc, self::META_DESC_MAX);
        }

        $focuskw = $brandName . ' helmets';

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

        $focuskw = $category !== '' ? $titleRaw : $titleRaw;

        return ['title' => $seoTitle, 'metadesc' => $metadesc, 'focuskw' => $focuskw];
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

    /**
     * @return list<string>
     */
    private function getTermNames(int $postId, string $taxonomy): array
    {
        $terms = get_the_terms($postId, $taxonomy);
        if (! is_array($terms) || $terms === []) {
            return [];
        }
        $out = [];
        foreach ($terms as $t) {
            if ($t instanceof WP_Term) {
                $out[] = (string) $t->name;
            }
        }
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
}
