<?php

declare(strict_types=1);

namespace App\Core;

/**
 * AES-256-GCM for the few genuinely secret values the app stores in MySQL - the
 * login details an agent attaches to a delivered order. The key comes from
 * APP_KEY in .env, which lives outside the database, so a SQL injection or a
 * leaked dump does not hand over usable credentials.
 *
 * If APP_KEY is missing the value is stored with a plain: marker instead of
 * being lost, and the admin panel shows a warning about it.
 */
final class Crypto
{
    private const PREFIX = 'enc:v1$';

    private const PLAIN_PREFIX = 'plain:';

    private const IV_LENGTH = 12;

    private const TAG_LENGTH = 16;

    public static function keyPresent(): bool
    {
        return self::key() !== null;
    }

    public static function encrypt(?string $plain): string
    {
        $plain = (string) $plain;

        if ($plain === '') {
            return '';
        }

        $key = self::key();

        if ($key === null) {
            Logger::warning('crypto_missing_key', ['action' => 'stored_unencrypted']);

            return self::PLAIN_PREFIX . $plain;
        }

        $iv = random_bytes(self::IV_LENGTH);
        $tag = '';

        $cipher = openssl_encrypt($plain, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag, '', self::TAG_LENGTH);

        if ($cipher === false) {
            throw new HttpException(500, 'Encryption failed.');
        }

        return self::PREFIX . base64_encode($iv . $tag . $cipher);
    }

    /**
     * Returns the stored value as-is when it is not encrypted, so a plaintext
     * note written before APP_KEY existed still displays.
     */
    public static function decrypt(?string $stored): string
    {
        $stored = (string) $stored;

        if ($stored === '') {
            return '';
        }

        if (str_starts_with($stored, self::PLAIN_PREFIX)) {
            return substr($stored, strlen(self::PLAIN_PREFIX));
        }

        if (!str_starts_with($stored, self::PREFIX)) {
            return $stored;
        }

        $key = self::key();

        if ($key === null) {
            return '(encrypted, but APP_KEY is missing)';
        }

        $raw = base64_decode(substr($stored, strlen(self::PREFIX)), true);

        if ($raw === false || strlen($raw) <= self::IV_LENGTH + self::TAG_LENGTH) {
            return '(unreadable)';
        }

        $iv = substr($raw, 0, self::IV_LENGTH);
        $tag = substr($raw, self::IV_LENGTH, self::TAG_LENGTH);
        $cipher = substr($raw, self::IV_LENGTH + self::TAG_LENGTH);

        $plain = openssl_decrypt($cipher, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);

        return $plain === false ? '(tampered)' : $plain;
    }

    /** @return 32-byte key or null when APP_KEY is unset */
    private static function key(): ?string
    {
        $configured = trim((string) Config::get('app.key', ''));

        if ($configured === '') {
            return null;
        }

        // Accept both raw hex and any passphrase; both are stretched to exactly
        // 32 bytes so the cipher parameters never vary.
        if (preg_match('/^[0-9a-f]{64}$/i', $configured) === 1) {
            return hex2bin($configured);
        }

        return hash('sha256', $configured, true);
    }
}
