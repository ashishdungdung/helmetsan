<?php
declare(strict_types=1);

namespace HelmetsanTranslate;

defined('ABSPATH') || exit;

class Encryption {
    private const CIPHER = 'aes-256-cbc';

    private static function get_key(): string {
        $salt = defined('AUTH_KEY') ? AUTH_KEY : 'helmetsan-secure-fallback-salt';
        $nonce = defined('NONCE_SALT') ? NONCE_SALT : 'helmetsan-nonce-salt';
        return hash('sha256', $salt . $nonce, true);
    }

    public static function encrypt(string $plain): string {
        if ($plain === '') return '';
        $iv = openssl_random_pseudo_bytes(16);
        $encrypted = openssl_encrypt($plain, self::CIPHER, self::get_key(), OPENSSL_RAW_DATA, $iv);
        if ($encrypted === false) return '';
        return base64_encode($iv . $encrypted);
    }

    public static function decrypt(string $encoded): string {
        if ($encoded === '') return '';
        $raw = base64_decode($encoded, true);
        if ($raw === false || strlen($raw) <= 16) {
            return $encoded; // fallback if unencrypted or plain
        }
        $iv = substr($raw, 0, 16);
        $ciphertext = substr($raw, 16);
        $decrypted = openssl_decrypt($ciphertext, self::CIPHER, self::get_key(), OPENSSL_RAW_DATA, $iv);
        return $decrypted !== false ? $decrypted : $encoded;
    }

    public static function mask(string $key): string {
        $len = strlen($key);
        if ($len <= 8) return '••••••••';
        return substr($key, 0, 6) . '••••••••' . substr($key, -4);
    }
}
