<?php

/**
 * Stub for WP-CLI (IDE/Intelephense only). Do not require this file at runtime.
 * Real WP_CLI is provided by the wp-cli package when running under `wp`.
 *
 * @see https://github.com/wp-cli/wp-cli
 */
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
