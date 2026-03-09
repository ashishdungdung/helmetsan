<?php

/**
 * Stub for WP-CLI (IDE/Intelephense only). Do not require this file at runtime.
 * Real WP_CLI constant and class are provided by the wp-cli package when running under `wp`.
 *
 * @see https://github.com/wp-cli/wp-cli
 */
if (! defined('WP_CLI')) {
    define('WP_CLI', false);
}

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
}
