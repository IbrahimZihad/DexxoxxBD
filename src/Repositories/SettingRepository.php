<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

/** Key/value site settings (payment numbers, support contacts) editable from admin. */
final class SettingRepository
{
    /** @return array<string,mixed> */
    public static function all(bool $publicOnly = false): array
    {
        $sql = 'SELECT key_name, value, type FROM settings';

        if ($publicOnly) {
            $sql .= ' WHERE is_public = 1';
        }

        $settings = [];

        foreach (Database::select($sql . ' ORDER BY group_name, key_name') as $row) {
            $settings[$row['key_name']] = self::cast($row['value'], $row['type']);
        }

        return $settings;
    }

    /** Full rows, including the labels and groups the admin screen renders. */
    public static function definitions(): array
    {
        return Database::select('SELECT key_name, value, type, group_name, label, is_public FROM settings ORDER BY group_name, key_name');
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $row = Database::selectOne('SELECT value, type FROM settings WHERE key_name = ? LIMIT 1', [$key]);

        return $row === null ? $default : self::cast($row['value'], $row['type']);
    }

    /** @param array<string,mixed> $values key => raw value */
    public static function setMany(array $values): int
    {
        $known = [];

        foreach (Database::select('SELECT key_name, type FROM settings') as $row) {
            $known[$row['key_name']] = $row['type'];
        }

        $written = 0;

        foreach ($values as $key => $value) {
            if (!array_key_exists($key, $known)) {
                continue; // only keys defined by a migration can be written
            }

            $stored = match ($known[$key]) {
                'bool' => !empty($value) && !in_array((string) $value, ['false', '0', 'no'], true) ? '1' : '0',
                'number' => (string) (0 + (float) $value),
                'json' => json_encode((array) $value, JSON_UNESCAPED_UNICODE),
                default => is_scalar($value) ? (string) $value : '',
            };

            Database::statement(
                'INSERT INTO settings (key_name, value) VALUES (?, ?)
                 ON DUPLICATE KEY UPDATE value = VALUES(value)',
                [$key, $stored]
            );
            $written++;
        }

        return $written;
    }

    private static function cast(?string $value, string $type): mixed
    {
        return match ($type) {
            'bool' => $value === '1' || $value === 'true',
            'number' => (float) $value,
            'json' => json_decode((string) $value, true) ?: [],
            default => (string) $value,
        };
    }
}
