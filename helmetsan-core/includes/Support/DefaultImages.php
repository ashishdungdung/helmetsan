<?php

declare(strict_types=1);

namespace Helmetsan\Core\Support;

/**
 * Resolves site-wide default image URLs for helmet, brand, and accessory placeholders.
 * Uses saved settings (attachment or bundled SVG) or built-in defaults.
 */
final class DefaultImages
{
    public const TYPE_HELMET = 'helmet';
    public const TYPE_BRAND = 'brand';
    public const TYPE_ACCESSORY = 'accessory';

    /** @var array<string, array{slug: string, label: string, type: string}> */
    private const BUNDLED_SVG = [
        'helmet-modern' => ['slug' => 'helmet-modern', 'label' => 'Helmet (modern)', 'type' => self::TYPE_HELMET],
        'helmet-sport'  => ['slug' => 'helmet-sport', 'label' => 'Helmet (sport)', 'type' => self::TYPE_HELMET],
        'brand-shield'  => ['slug' => 'brand-shield', 'label' => 'Brand (shield)', 'type' => self::TYPE_BRAND],
        'brand-badge'   => ['slug' => 'brand-badge', 'label' => 'Brand (badge)', 'type' => self::TYPE_BRAND],
        'accessory-gear'=> ['slug' => 'accessory-gear', 'label' => 'Accessory (gear)', 'type' => self::TYPE_ACCESSORY],
        'accessory-parts'=> ['slug' => 'accessory-parts', 'label' => 'Accessory (parts)', 'type' => self::TYPE_ACCESSORY],
    ];

    private const RELATIVE_DEFAULT_HELMET = 'assets/defaults/helmet-default.png';
    private const RELATIVE_SVG_DIR = 'assets/defaults/svg';

    public function __construct(
        private readonly Config $config
    ) {
    }

    /**
     * Default image URL for a given entity type when no entity-specific image is set.
     *
     * @param string $type One of self::TYPE_HELMET, self::TYPE_BRAND, self::TYPE_ACCESSORY
     * @return string URL to use (never empty for valid type)
     */
    public function getDefaultImageUrl(string $type): string
    {
        $cfg = $this->config->defaultImagesConfig();

        if ($type === self::TYPE_HELMET) {
            $attachmentId = (int) ($cfg['helmet_attachment_id'] ?? 0);
            if ($attachmentId > 0) {
                $url = wp_get_attachment_url($attachmentId);
                if (is_string($url) && $url !== '') {
                    return $url;
                }
            }
            $svgSlug = (string) ($cfg['helmet_svg_slug'] ?? '');
            if ($svgSlug !== '' && $this->svgExists($svgSlug)) {
                return $this->svgUrl($svgSlug);
            }
            return $this->builtInHelmetDefaultUrl();
        }

        if ($type === self::TYPE_BRAND) {
            $attachmentId = (int) ($cfg['brand_attachment_id'] ?? 0);
            if ($attachmentId > 0) {
                $url = wp_get_attachment_url($attachmentId);
                if (is_string($url) && $url !== '') {
                    return $url;
                }
            }
            $svgSlug = (string) ($cfg['brand_svg_slug'] ?? '');
            if ($svgSlug !== '' && $this->svgExists($svgSlug)) {
                return $this->svgUrl($svgSlug);
            }
            return $this->svgUrl('brand-shield');
        }

        if ($type === self::TYPE_ACCESSORY) {
            $attachmentId = (int) ($cfg['accessory_attachment_id'] ?? 0);
            if ($attachmentId > 0) {
                $url = wp_get_attachment_url($attachmentId);
                if (is_string($url) && $url !== '') {
                    return $url;
                }
            }
            $svgSlug = (string) ($cfg['accessory_svg_slug'] ?? '');
            if ($svgSlug !== '' && $this->svgExists($svgSlug)) {
                return $this->svgUrl($svgSlug);
            }
            return $this->svgUrl('accessory-gear');
        }

        return '';
    }

    /**
     * List of bundled SVG logos for admin dropdown (slug => label), optionally filtered by type.
     *
     * @param string $type Optional. One of self::TYPE_HELMET, self::TYPE_BRAND, self::TYPE_ACCESSORY to filter.
     * @return array<string, string>
     */
    public function getAvailableSvgLogos(string $type = ''): array
    {
        $out = [];
        foreach (self::BUNDLED_SVG as $slug => $info) {
            if ($type !== '' && ($info['type'] ?? '') !== $type) {
                continue;
            }
            $out[$slug] = $info['label'];
        }
        return $out;
    }

    public function builtInHelmetDefaultUrl(): string
    {
        $path = HELMETSAN_CORE_DIR . self::RELATIVE_DEFAULT_HELMET;
        if (file_exists($path)) {
            return HELMETSAN_CORE_URL . self::RELATIVE_DEFAULT_HELMET;
        }
        return $this->svgUrl('helmet-modern');
    }

    private function svgExists(string $slug): bool
    {
        $path = HELMETSAN_CORE_DIR . self::RELATIVE_SVG_DIR . '/' . $slug . '.svg';
        return file_exists($path);
    }

    private function svgUrl(string $slug): string
    {
        return HELMETSAN_CORE_URL . self::RELATIVE_SVG_DIR . '/' . $slug . '.svg';
    }
}
