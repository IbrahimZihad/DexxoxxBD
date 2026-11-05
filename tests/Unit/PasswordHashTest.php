<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Auth;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(Auth::class)]
final class PasswordHashTest extends TestCase
{
    public function test_it_hashes_and_verifies_through_password_verify(): void
    {
        $hash = Auth::hash('correct horse battery staple');

        self::assertNotSame('correct horse battery staple', $hash);
        self::assertStringStartsWith('$', $hash);
        self::assertTrue(password_verify('correct horse battery staple', $hash));
        self::assertFalse(password_verify('Correct horse battery staple', $hash));
    }

    public function test_two_hashes_of_the_same_password_differ(): void
    {
        self::assertNotSame(Auth::hash('same-passphrase'), Auth::hash('same-passphrase'));
    }

    public function test_the_hash_is_strong_enough_to_store(): void
    {
        $hash = Auth::hash('another-passphrase');

        // Argon2id whenever the build has it, bcrypt only as the fallback.
        self::assertMatchesRegularExpression('/^\\$(argon2id|2y)\\$/', $hash);

        if (str_starts_with($hash, '$argon2id$')) {
            // m=64 MiB, t=4 iterations, p=2 lanes: the parameters are read back
            // out of the stored hash so a quiet downgrade fails the suite.
            self::assertMatchesRegularExpression('/\$m=(\d{5,}),t=([4-9]|1\d),p=[1-9]/', $hash);
        } else {
            self::assertMatchesRegularExpression('/^\$2y\$1[0-2]\$/', $hash);
        }
    }

    public function test_a_stored_hash_never_contains_the_secret(): void
    {
        $secret = 'never-store-this-plainly';

        self::assertStringNotContainsString($secret, Auth::hash($secret));
    }
}
