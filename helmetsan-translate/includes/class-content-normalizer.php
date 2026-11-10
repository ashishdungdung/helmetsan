<?php
declare(strict_types=1);

namespace HelmetsanTranslate;

defined('ABSPATH') || exit;

class ContentNormalizer {
    public static function normalize(string $html, string $target_lang): string {
        $html = self::rewrite_internal_links($html, $target_lang);
        $html = self::preserve_affiliate_links($html);
        return $html;
    }

    public static function rewrite_internal_links(string $html, string $target_lang): string {
        $home_url = home_url();
        $domain = parse_url($home_url, PHP_URL_HOST);
        if (!$domain) return $html;

        // Rewrite relative links like href="/brands/shoei/" into href="/de/brands/shoei/"
        $supported = 'de|fr|es|it|nl|pl|pt|ja|zh';
        $pattern = '~href="(/(?!' . $supported . '/|wp-content|wp-includes|wp-admin|api|feed)([^"#?]+)/?)"~i';

        $result = preg_replace_callback($pattern, function($matches) use ($target_lang) {
            $path = trim($matches[1], '/');
            return 'href="/' . $target_lang . '/' . $path . '/"';
        }, $html);

        return $result ?? $html;
    }

    public static function preserve_affiliate_links(string $html): string {
        // Enforce rel="nofollow sponsored noopener" on monetization outbound links
        $affiliate_domains = 'revzilla\.com|fc-moto\.de|amazon\.[a-z.]+|sportsbikeshop\.co\.uk';
        $pattern = '~<a\s+([^>]*href=["\']https?://[^"\']*(' . $affiliate_domains . ')[^"\']*["\'][^>]*)>~i';

        $result = preg_replace_callback($pattern, function($matches) {
            $tag = $matches[0];
            if (!str_contains($tag, 'rel=')) {
                return str_replace('<a ', '<a rel="nofollow sponsored noopener" ', $tag);
            }
            return $tag;
        }, $html);

        return $result ?? $html;
    }
}
