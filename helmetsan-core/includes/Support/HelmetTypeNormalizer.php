<?php

declare(strict_types=1);

namespace Helmetsan\Core\Support;

/**
 * Shared helmet-type normalization logic.
 *
 * Converts any raw string (from JSON payloads, admin input, etc.) into a
 * canonical helmet-type slug/label used across the platform.
 *
 * @package HelmetsanCore
 */
final class HelmetTypeNormalizer
{
    /**
     * Canonical map: lowercase input → display label.
     *
     * @var array<string,string>
     */
    private const CANONICAL = [
        'full face'                  => 'Full Face',
        'full-face'                  => 'Full Face',
        'modular'                    => 'Modular',
        'open face'                  => 'Open Face',
        'open-face'                  => 'Open Face',
        'half'                       => 'Half',
        'half helmet'                => 'Half',
        'half-helmet'                => 'Half',
        'dirt'                       => 'Dirt / MX',
        'mx'                         => 'Dirt / MX',
        'dirt mx'                    => 'Dirt / MX',
        'dirt / mx'                  => 'Dirt / MX',
        'dirt / motocross'           => 'Dirt / MX',
        'dirt motocross'             => 'Dirt / MX',
        'off road'                   => 'Dirt / MX',
        'off-road'                   => 'Dirt / MX',
        'motocross'                  => 'Dirt / MX',
        'adventure'                  => 'Adventure / Dual Sport',
        'dual sport'                 => 'Adventure / Dual Sport',
        'adventure dual sport'       => 'Adventure / Dual Sport',
        'adventure / dual sport'     => 'Adventure / Dual Sport',
        'adventure and dual sport'   => 'Adventure / Dual Sport',
        'touring'                    => 'Touring',
        'track'                      => 'Track / Race',
        'race'                       => 'Track / Race',
        'track race'                 => 'Track / Race',
        'track / race'               => 'Track / Race',
        'youth'                      => 'Youth',
        'snow'                       => 'Snow',
        'snowmobile'                 => 'Snow',
        'carbon fiber'               => 'Carbon Fiber',
        'carbon-fiber'               => 'Carbon Fiber',
        'graphics'                   => 'Graphics',
        'graphic'                    => 'Graphics',
        'sale'                       => 'Sale',
        'closeout'                   => 'Sale',
    ];

    /**
     * Normalize a raw string to a canonical display label.
     * Returns '' if the value cannot be mapped.
     */
    public static function toLabel(string $raw): string
    {
        $value = strtolower(trim(sanitize_text_field($raw)));
        if ($value === '') {
            return '';
        }

        // Collapse whitespace and strip trailing "helmets"
        $value = (string) preg_replace('/\s+/', ' ', $value);
        $value = trim(str_replace('helmets', '', $value));

        if (isset(self::CANONICAL[$value])) {
            return self::CANONICAL[$value];
        }

        // Fuzzy fallbacks
        if (str_contains($value, 'full') && str_contains($value, 'face')) {
            return 'Full Face';
        }
        if (str_contains($value, 'modular')) {
            return 'Modular';
        }
        if (str_contains($value, 'open') && str_contains($value, 'face')) {
            return 'Open Face';
        }
        if (str_contains($value, 'adventure') || str_contains($value, 'dual sport')) {
            return 'Adventure / Dual Sport';
        }
        if (str_contains($value, 'dirt') || str_contains($value, 'motocross') || str_contains($value, 'mx')) {
            return 'Dirt / MX';
        }
        if (str_contains($value, 'tour')) {
            return 'Touring';
        }
        if (str_contains($value, 'track') || str_contains($value, 'race')) {
            return 'Track / Race';
        }
        if (str_contains($value, 'youth')) {
            return 'Youth';
        }
        if (str_contains($value, 'snow')) {
            return 'Snow';
        }
        if (str_contains($value, 'carbon')) {
            return 'Carbon Fiber';
        }
        if (str_contains($value, 'graphic')) {
            return 'Graphics';
        }
        if (str_contains($value, 'sale') || str_contains($value, 'closeout')) {
            return 'Sale';
        }

        return '';
    }

    /**
     * Normalize a raw string to a URL-safe slug (e.g. "Full Face" → "full-face").
     * Returns '' if the value cannot be mapped.
     */
    public static function toSlug(string $raw): string
    {
        $label = self::toLabel($raw);
        if ($label === '') {
            return '';
        }

        return sanitize_title($label);
    }

    /**
     * Normalize an array of raw strings to unique canonical labels.
     *
     * @param  array<mixed> $items
     * @return array<string>
     */
    public static function normalizeArray(array $items): array
    {
        $result = [];
        foreach ($items as $item) {
            $label = self::toLabel((string) $item);
            if ($label !== '') {
                $result[] = $label;
            }
        }

        return array_values(array_unique($result));
    }

    /**
     * All canonical display labels.
     *
     * @return array<string>
     */
    public static function allLabels(): array
    {
        return array_values(array_unique(self::CANONICAL));
    }
}
