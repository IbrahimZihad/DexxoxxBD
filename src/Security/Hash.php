<?php
declare(strict_types=1);

namespace App\Security;

use App\Core\Config;

/**
 * Password hashing. Argon2id would be preferable but this build of PHP has no
 * sodium extension, so bcrypt at a tunable cost is used and old cost-10 hashes
 * are transparently upgraded on the next successful login.
 */
final class Hash
{
    public static function make(#[\SensitiveParameter] string $password): string
    {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => (int) Config::get('security.bcrypt_cost', 12)]);
    }

    public static function verify(#[\SensitiveParameter] string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    public static function needsRehash(string $hash): bool
    {
        return password_needs_rehash($hash, PASSWORD_BCRYPT, ['cost' => (int) Config::get('security.bcrypt_cost', 12)]);
    }
}
