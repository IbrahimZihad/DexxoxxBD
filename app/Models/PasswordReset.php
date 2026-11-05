<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Password reset tokens. Only the SHA-256 of the token is stored, so a leaked
 * table cannot be used to take over accounts.
 */
final class PasswordReset extends Model
{
    protected string $table = 'password_resets';

    protected bool $timestamps = false;

    protected array $fillable = ['email', 'token_hash', 'expires_at', 'used_at', 'ip_address', 'created_at'];

    public static function issue(string $email, string $plainToken, string $ip): int
    {
        // Invalidate anything previously outstanding for this address first.
        Database::execute(
            'update password_resets set used_at = now() where email = :email and used_at is null',
            [':email' => $email]
        );

        Database::execute(
            'insert into password_resets (email, token_hash, expires_at, ip_address, created_at)
             values (:email, :hash, :expires, :ip, :created)',
            [
                ':email' => $email,
                ':hash' => hash('sha256', $plainToken),
                ':expires' => date('Y-m-d H:i:s', time() + 3600),
                ':ip' => $ip,
                ':created' => date('Y-m-d H:i:s'),
            ]
        );

        return (int) Database::lastInsertId();
    }

    public static function findValid(string $plainToken): ?array
    {
        return Database::selectOne(
            'select * from password_resets
              where token_hash = :hash and used_at is null and expires_at > now()
              limit 1',
            [':hash' => hash('sha256', $plainToken)]
        );
    }

    public static function consume(int $id): void
    {
        Database::execute('update password_resets set used_at = now() where id = :id', [':id' => $id]);
    }
}
