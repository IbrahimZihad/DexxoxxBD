<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\App;
use App\Core\Database;

/**
 * CMS-editable site settings (name, contact details, policies flags, homepage
 * copy) with one cached read per request.
 */
final class SettingService
{
    private const CACHE_KEY = 'settings.all';

    private const CACHE_TTL = 900;

    /** @var array<string, mixed>|null */
    private ?array $resolved = null;

    private Database $db;

    private CacheService $cache;

    public function __construct()
    {
        $this->db = Database::connection();
        /** @var CacheService $cache */
        $cache = App::instance()->make('cache');
        $this->cache = $cache;
    }

    /** @return array<string, mixed> */
    public function all(): array
    {
        if ($this->resolved !== null) {
            return $this->resolved;
        }

        if (!$this->db->tableExists('settings')) {
            return $this->resolved = [];
        }

        $rows = $this->cache->remember(self::CACHE_KEY, self::CACHE_TTL, function (): array {
            return $this->db->select(
                'SELECT `key`, `value`, `type`, `group_name` FROM settings ORDER BY group_name, `key`'
            );
        });

        $settings = [];
        foreach ((array) $rows as $row) {
            $settings[(string) $row['key']] = self::cast((string) $row['type'], $row['value']);
        }

        return $this->resolved = $settings;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->all()[$key] ?? $default;
    }

    /** @return array<string, mixed> */
    public function group(string $group): array
    {
        $rows = $this->db->select(
            'SELECT `key`, `value`, `type` FROM settings WHERE group_name = :group',
            ['group' => $group]
        );

        $out = [];
        foreach ($rows as $row) {
            $out[(string) $row['key']] = self::cast((string) $row['type'], $row['value']);
        }
        return $out;
    }

    public function put(string $key, mixed $value, string $type = 'text', string $group = 'general'): void
    {
        $existing = $this->db->selectOne('SELECT id FROM settings WHERE `key` = :key', ['key' => $key]);

        if ($existing === null) {
            $this->db->insert('settings', [
                'key' => $key,
                'value' => self::serialize($value),
                'type' => $type,
                'group_name' => $group,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            $this->db->statement(
                'UPDATE settings SET `value` = :value, `type` = :type, group_name = :group, updated_at = NOW() WHERE `key` = :key',
                [
                    'value' => self::serialize($value),
                    'type' => $type,
                    'group' => $group,
                    'key' => $key,
                ]
            );
        }

        $this->invalidate();
    }

    /** @param array<string, mixed> $values */
    public function putMany(array $values, string $group = 'general'): void
    {
        $this->db->transaction(function (): void {
            foreach ($values as $key => $value) {
                if (is_array($value)) {
                    $this->put((string) $key, json_encode($value, JSON_UNESCAPED_UNICODE), 'json', $group);
                    continue;
                }
                $type = match (true) {
                    is_bool($value) => 'boolean',
                    is_numeric($value) => 'number',
                    default => 'text',
                };
                $this->putWithoutInvalidating((string) $key, $value, $type, $group);
            }
        });

        $this->invalidate();
    }

    private function putWithoutInvalidating(string $key, mixed $value, string $type, string $group): void
    {
        $existing = $this->db->selectOne('SELECT id FROM settings WHERE `key` = :key', ['key' => $key]);

        if ($existing === null) {
            $this->db->insert('settings', [
                'key' => $key,
                'value' => self::serialize($value),
                'type' => $type,
                'group_name' => $group,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            return;
        }

        $this->db->statement(
            'UPDATE settings SET `value` = :value, `type` = :type, group_name = :group, updated_at = NOW() WHERE `key` = :key',
            ['value' => self::serialize($value), 'type' => $type, 'group' => $group, 'key' => $key]
        );
    }

    public function invalidate(): void
    {
        $this->cache->forget(self::CACHE_KEY);
        $this->resolved = null;
    }

    private static function serialize(mixed $value): ?string
    {
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if ($value === null) {
            return null;
        }
        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE) ?: '{}';
        }
        return (string) $value;
    }

    private static function cast(string $type, mixed $value): mixed
    {
        return match ($type) {
            'boolean' => in_array((string) $value, ['1', 'true', 'yes', 'on'], true),
            'number' => is_numeric($value) ? $value + 0 : 0,
            'json' => (static function (mixed $v): array {
                $decoded = is_string($v) ? json_decode($v, true) : $v;
                return is_array($decoded) ? $decoded : [];
            })($value),
            default => $value,
        };
    }
}
