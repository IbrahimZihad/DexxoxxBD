<?php

declare(strict_types=1);

namespace App\Helpers;

use App\Core\Config;
use App\Core\Env;
use App\Services\LoginRateLimiter;
use DateTimeInterface;
use PDO;
use Phinx\Db\Adapter\PdoAdapter;
use Phinx\Db\Adapter\WrapperInterface;
use Phinx\Seed\AbstractSeed;
use RuntimeException;

/**
 * SQL layer for the database seeders.
 *
 * Seeders must be repeatable: running `phinx seed:run` twice has to leave the
 * same number of rows behind. Everything therefore goes through upsert(), which
 * looks a row up by its natural key and updates or inserts it, so a re-run
 * refreshes demo content instead of duplicating it.
 *
 * The seeder writes on the same PDO connection Phinx opened for the environment,
 * which also means it participates in the transaction Phinx wraps around each
 * seeder run.
 */
final class Seed
{
    private static bool $configured = false;

    private function __construct()
    {
    }

    /**
     * Phinx does not boot the application, so seeders load .env and the config
     * files themselves. That keeps password hashing, timezone and env-driven
     * admin credentials identical to the running app.
     */
    public static function configure(): void
    {
        if (self::$configured) {
            return;
        }

        $basePath = base_path();
        if (is_file($basePath . '/.env')) {
            Env::load($basePath . '/.env');
            Config::load($basePath . '/config');
            Config::flushEnvCache();
        }

        date_default_timezone_set((string) Config::get('app.timezone', 'Asia/Dhaka'));
        self::$configured = true;
    }

    /**
     * Load a demo-data file from database/factories.
     *
     * @return array<string, mixed>
     */
    public static function factory(string $name): array
    {
        $path = base_path('database/factories/' . $name . '.php');
        if (!is_file($path)) {
            throw new RuntimeException('Seeder factory [' . $name . '] does not exist at ' . $path . '.');
        }

        $data = require $path;

        if (!is_array($data)) {
            throw new RuntimeException('Seeder factory [' . $name . '] must return an array.');
        }

        return $data;
    }

    /**
     * Load one keyed list out of a factory file, failing loudly when the file
     * was edited and the section disappeared.
     *
     * @return list<array<string, mixed>>
     */
    public static function list(string $name, string $key): array
    {
        $data = self::factory($name);
        if (!isset($data[$key]) || !is_array($data[$key])) {
            throw new RuntimeException('Seeder factory [' . $name . '] has no list [' . $key . '].');
        }

        /** @var list<array<string, mixed>> $list */
        $list = array_values($data[$key]);

        return $list;
    }

    /**
     * Run the seeder class that this seeder depends on, if it is available.
     */
    public static function ensure(AbstractSeed $seed, string $class): void
    {
        if (!class_exists($class)) {
            throw new RuntimeException(
                'Seeder [' . $seed->getName() . '] needs [' . $class . '], which is not loaded. '
                . 'Seeders live in database/seeders and are loaded by Phinx.'
            );
        }

        /** @var AbstractSeed $child */
        $child = new $class();
        $child->setEnvironment($seed->getEnvironment());
        $child->setAdapter($seed->getAdapter());
        $child->setInput($seed->getInput());
        $child->setOutput($seed->getOutput());
        $child->run();
    }

    /**
     * Hash with the same algorithm and cost the running application uses, so a
     * seeded account is not silently weaker than one created through the form.
     */
    public static function hashPassword(string $password): string
    {
        self::configure();

        $hash = password_hash($password, LoginRateLimiter::preferredAlgorithm(), LoginRateLimiter::hashOptions());
        if ($hash === false) {
            throw new RuntimeException('Password hashing failed.');
        }

        return $hash;
    }

    /**
     * A username is unique, so fall back to a numbered variant instead of
     * failing the whole seed because another account already holds the name.
     */
    public static function uniqueUsername(AbstractSeed $seed, string $username, string $email): string
    {
        $candidate = $username;
        $suffix = 1;

        while (true) {
            $owner = self::id($seed, 'users', ['username' => $candidate]);
            if ($owner === null) {
                return $candidate;
            }

            $heldBy = (string) self::scalar(
                $seed,
                'SELECT email FROM `users` WHERE `id` = ? LIMIT 1',
                [$owner]
            );
            if (strcasecmp($heldBy, $email) === 0) {
                return $candidate;
            }

            $candidate = $username . '-' . (++$suffix);
        }
    }

    /**
     * Find a row by natural key.
     */
    public static function id(AbstractSeed $seed, string $table, array $keys): ?int
    {
        [$where, $params] = self::conditions($keys);
        $sql = 'SELECT `id` FROM ' . self::wrap($table) . ' WHERE ' . $where . ' LIMIT 1';
        $row = self::first($seed, $sql, $params);

        return $row === null ? null : (int) $row['id'];
    }

    /**
     * Insert when the natural key is unknown, update when it is already there.
     *
     * @param array<string, mixed> $keys natural key columns
     * @param array<string, mixed> $data columns to write
     */
    public static function upsert(AbstractSeed $seed, string $table, array $keys, array $data): int
    {
        $existing = self::id($seed, $table, $keys);

        if ($existing !== null) {
            if ($data !== []) {
                $assignments = [];
                $params = [];
                foreach ($data as $column => $value) {
                    $assignments[] = self::column((string) $column) . ' = ?';
                    $params[] = self::value($value);
                }
                $params[] = $existing;
                self::statement(
                    $seed,
                    'UPDATE ' . self::wrap($table) . ' SET ' . implode(', ', $assignments) . ' WHERE `id` = ?',
                    $params
                );
            }

            return $existing;
        }

        $row = array_merge($keys, $data);
        $columns = [];
        $placeholders = [];
        $params = [];
        foreach ($row as $column => $value) {
            $columns[] = self::column((string) $column);
            $placeholders[] = '?';
            $params[] = self::value($value);
        }

        self::statement(
            $seed,
            'INSERT INTO ' . self::wrap($table) . ' (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $placeholders) . ')',
            $params
        );

        $id = (int) self::pdo($seed)->lastInsertId();
        if ($id === 0) {
            throw new RuntimeException('Insert into ' . $table . ' did not produce an id.');
        }

        return $id;
    }

    /**
     * Add a pivot row when it is missing. Every column forms part of the key,
     * so a second run finds the row and changes nothing.
     *
     * @param array<string, mixed> $row
     */
    public static function link(AbstractSeed $seed, string $table, array $row): int
    {
        return self::upsert($seed, $table, $row, []);
    }

    /**
     * Make a pivot relation exactly match the supplied rows.
     *
     * @param array<int, array<string, mixed>> $rows
     */
    public static function sync(AbstractSeed $seed, string $table, string $ownerColumn, int $ownerId, array $rows): void
    {
        self::statement(
            $seed,
            'DELETE FROM ' . self::wrap($table) . ' WHERE ' . self::column($ownerColumn) . ' = ?',
            [$ownerId]
        );

        foreach ($rows as $row) {
            self::link($seed, $table, $row);
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function rows(AbstractSeed $seed, string $sql, array $params = []): array
    {
        $statement = self::pdo($seed)->prepare($sql);
        $statement->execute(self::bind($params));

        /** @var list<array<string, mixed>> $rows */
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

        return $rows;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function first(AbstractSeed $seed, string $sql, array $params = []): ?array
    {
        $statement = self::pdo($seed)->prepare($sql);
        $statement->execute(self::bind($params));
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    public static function scalar(AbstractSeed $seed, string $sql, array $params = []): mixed
    {
        $statement = self::pdo($seed)->prepare($sql);
        $statement->execute(self::bind($params));
        $value = $statement->fetchColumn();

        return $value === false ? null : $value;
    }

    /**
     * @param list<mixed> $params
     */
    public static function statement(AbstractSeed $seed, string $sql, array $params = []): int
    {
        $statement = self::pdo($seed)->prepare($sql);
        $statement->execute(self::bind($params));

        return $statement->rowCount();
    }

    public static function pdo(AbstractSeed $seed): PDO
    {
        // Phinx wraps the real adapter (timing, verbosity) before handing it to
        // a seeder, so the PDO connection has to be unwrapped first.
        $adapter = $seed->getAdapter();
        while ($adapter instanceof WrapperInterface) {
            $adapter = $adapter->getAdapter();
        }

        if (!$adapter instanceof PdoAdapter) {
            throw new RuntimeException('Seed helpers expect a PDO-backed Phinx adapter.');
        }

        return $adapter->getConnection();
    }

    /**
     * Build "col = ?" / "col IS NULL" conditions with their positional values.
     *
     * @param array<string, mixed> $keys
     * @return array{0: string, 1: list<mixed>}
     */
    private static function conditions(array $keys): array
    {
        if ($keys === []) {
            throw new RuntimeException('A natural key is required to look up a row.');
        }

        $parts = [];
        $params = [];
        foreach ($keys as $column => $value) {
            $name = self::column((string) $column);
            if ($value === null) {
                $parts[] = $name . ' IS NULL';
                continue;
            }
            $parts[] = $name . ' = ?';
            $params[] = self::value($value);
        }

        return [implode(' AND ', $parts), $params];
    }

    /**
     * Positional parameters need the same type coercion as named writes.
     *
     * @param array<mixed> $params
     * @return list<mixed>
     */
    private static function bind(array $params): array
    {
        $out = [];
        foreach ($params as $param) {
            $out[] = self::value($param);
        }

        return $out;
    }

    private static function value(mixed $value): mixed
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        // PDO sends array parameters as strings, and false would become an empty
        // string - which a strict-mode integer column rejects.
        if (is_bool($value)) {
            return $value ? 1 : 0;
        }

        return $value;
    }

    private static function column(string $name): string
    {
        return self::wrap($name, 'column');
    }

    private static function wrap(string $name, string $kind = 'table'): string
    {
        if (preg_match('/^[a-z][a-z0-9_]*$/', $name) !== 1) {
            throw new RuntimeException('Unsafe ' . $kind . ' name [' . $name . '].');
        }

        return '`' . $name . '`';
    }
}
