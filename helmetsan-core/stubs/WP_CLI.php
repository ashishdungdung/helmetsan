<?php

/**
 * Stub for WP-CLI & Polylang symbols (IDE / Static Analysis only).
 * Real WP_CLI and functions are provided at runtime in WordPress environments.
 */

namespace {
    if (! defined('WP_CLI')) {
        define('WP_CLI', false);
    }

    if (! class_exists('WP_CLI', false)) {
        class WP_CLI
        {
            public static function add_command(string $name, $callable, array $args = []): void
            {
            }

            public static function error(string $message, bool $exit = true): void
            {
            }

            public static function success(string $message, bool $exit = false): void
            {
            }

            public static function warning(string $message): void
            {
            }

            public static function log(string $message): void
            {
            }

            public static function line(string $message = ''): void
            {
            }

            public static function debug(string $message, string|false $group = false): void
            {
            }

            public static function confirm(string $question, array $assoc_args = []): void
            {
            }

            public static function colorize(string $string): string
            {
                return $string;
            }

            public static function get_config(?string $key = null): mixed
            {
                return null;
            }
        }
    }

    if (! function_exists('pll_get_post_language')) {
        function pll_get_post_language(int $post_id, string $field = 'slug'): string|false
        {
            return false;
        }
    }

    if (! function_exists('pll_set_post_language')) {
        function pll_set_post_language(int $post_id, string $lang): bool
        {
            return true;
        }
    }

    if (! function_exists('pll_default_language')) {
        function pll_default_language(string $field = 'slug'): string
        {
            return 'en';
        }
    }

    if (! function_exists('pll_get_post')) {
        function pll_get_post(int $post_id, string $lang = ''): int|false|null
        {
            return $post_id;
        }
    }

    if (! function_exists('pll_get_post_translations')) {
        function pll_get_post_translations(int $post_id): array
        {
            return [];
        }
    }

    if (! function_exists('pll_save_post_translations')) {
        function pll_save_post_translations(array $arr): void
        {
        }
    }

    if (! function_exists('pll_get_term_language')) {
        function pll_get_term_language(int $term_id, string $field = 'slug'): string|false
        {
            return false;
        }
    }

    if (! function_exists('pll_set_term_language')) {
        function pll_set_term_language(int $term_id, string $lang): bool
        {
            return true;
        }
    }

    if (! function_exists('pll_languages_list')) {
        function pll_languages_list(array $args = []): array
        {
            return ['en'];
        }
    }

    if (! function_exists('pll_register_string')) {
        function pll_register_string(string $name, string $string, string $group = '', bool $multiline = false): void
        {
        }
    }

    if (! function_exists('pll__')) {
        function pll__(string $string): string
        {
            return $string;
        }
    }

    if (! function_exists('pll_e')) {
        function pll_e(string $string): void
        {
            echo $string;
        }
    }

    if (! function_exists('helmetsan_get_country_flag')) {
        function helmetsan_get_country_flag(string $code): string
        {
            return '';
        }
    }
}

namespace WP_CLI\Utils {
    /**
     * @param string $format
     * @param array<int|string, mixed> $items
     * @param array<int, string>|string $fields
     */
    function format_items(string $format, array $items, $fields): void
    {
    }

    /**
     * @param string $message
     * @param int $count
     * @return object
     */
    function make_progress_bar(string $message, int $count): object
    {
        return new class {
            public function tick(): void {}
            public function finish(): void {}
        };
    }
}
