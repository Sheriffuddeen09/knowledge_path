<?php

namespace App\Services;

class MessageCryptoService
{
    /**
     * Encrypt message using AES-256-CBC
     */
    public static function encrypt(string $text, string $key): array
        {
            $iv = random_bytes(16);

            $encrypted = openssl_encrypt(
                $text,
                'AES-256-CBC',
                self::formatKey($key),
                OPENSSL_RAW_DATA,
                $iv
            );

            return [
                'data' => base64_encode($encrypted),
                'iv'   => base64_encode($iv),
            ];
        }

    /**
     * Decrypt message
     */
    public static function decrypt(string $encrypted, string $iv, string $key): string
        {
            return openssl_decrypt(
                base64_decode($encrypted),
                'AES-256-CBC',
                self::formatKey($key),
                OPENSSL_RAW_DATA,
                base64_decode($iv)
            ) ?: '';
        }
    /**
     * Make sure key is 32 bytes (AES-256 requirement)
     */
    private static function formatKey(string $key): string
    {
        return hash('sha256', $key, true);
    }
}