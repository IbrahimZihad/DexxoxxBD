<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Config;
use App\Core\Crypto;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(Crypto::class)]
final class CryptoTest extends TestCase
{
    public function test_a_key_is_configured_for_the_test_run(): void
    {
        // Encryption of delivery credentials is only meaningful with APP_KEY,
        // and the key lives in .env - never in the database.
        self::assertTrue(Crypto::keyPresent(), 'APP_KEY must be set for the suite to prove at-rest encryption.');
    }

    public function test_it_round_trips_a_value(): void
    {
        $plain = "mail: demo@example.com\npass: S3cret-Pa55\npin: 9042";

        $stored = Crypto::encrypt($plain);

        self::assertNotSame($plain, $stored);
        self::assertStringStartsWith('enc:v1$', $stored);
        self::assertStringNotContainsString('S3cret-Pa55', $stored);
        self::assertSame($plain, Crypto::decrypt($stored));
    }

    public function test_each_encryption_uses_a_fresh_nonce(): void
    {
        $first = Crypto::encrypt('same input');
        $second = Crypto::encrypt('same input');

        self::assertNotSame($first, $second);
        self::assertSame(Crypto::decrypt($first), Crypto::decrypt($second));
    }

    public function test_tampering_with_the_ciphertext_is_detected(): void
    {
        $stored = Crypto::encrypt('customer password: hunter2');

        $prefix = 'enc:v1$';
        $payload = substr($stored, strlen($prefix));
        $raw = base64_decode($payload, true);
        self::assertIsString($raw);

        // Flip one byte in the middle of the ciphertext; the GCM tag no longer
        // matches, so decrypt() must refuse rather than return anything.
        $middle = intdiv(strlen($raw), 2);
        $raw[$middle] = chr(ord($raw[$middle]) ^ 0xFF);

        $tampered = $prefix . base64_encode($raw);

        self::assertSame('(tampered)', Crypto::decrypt($tampered));
        self::assertStringNotContainsString('hunter2', Crypto::decrypt($tampered));
    }

    public function test_truncated_or_undecodable_payloads_do_not_leak(): void
    {
        self::assertSame('(unreadable)', Crypto::decrypt('enc:v1$not-base64-at-all!!'));
        self::assertSame('(unreadable)', Crypto::decrypt('enc:v1$' . base64_encode(str_repeat('a', 8))));
    }

    public function test_values_stored_before_the_key_existed_still_display(): void
    {
        self::assertSame('legacy plaintext note', Crypto::decrypt('plain:legacy plaintext note'));
        self::assertSame('untouched old row', Crypto::decrypt('untouched old row'));
    }

    public function test_empty_and_null_input_stay_empty(): void
    {
        self::assertSame('', Crypto::encrypt(''));
        self::assertSame('', Crypto::encrypt(null));
        self::assertSame('', Crypto::decrypt(''));
        self::assertSame('', Crypto::decrypt(null));
    }

    public function test_without_a_key_the_fallback_is_marked_and_nothing_leaks(): void
    {
        $configured = Config::get('app.key');
        $encryptedWhileKeyed = Crypto::encrypt('customer password: hunter2');

        Config::set('app.key', '');

        try {
            self::assertFalse(Crypto::keyPresent());
            self::assertStringStartsWith('plain:', Crypto::encrypt('temporarily unencrypted'));

            // A row written while the key existed must not fail open: without
            // the key there is no plaintext, only a stated reason.
            $decrypted = Crypto::decrypt($encryptedWhileKeyed);

            self::assertSame('(encrypted, but APP_KEY is missing)', $decrypted);
            self::assertStringNotContainsString('hunter2', $decrypted);
        } finally {
            Config::set('app.key', $configured);
        }

        self::assertTrue(Crypto::keyPresent());
        self::assertSame('customer password: hunter2', Crypto::decrypt($encryptedWhileKeyed));
    }

    public function test_unicode_survives_the_round_trip(): void
    {
        $plain = "ঠাকুরগাঁও — ১২ মাস — password: গোপন১২৩";

        self::assertSame($plain, Crypto::decrypt(Crypto::encrypt($plain)));
    }
}
