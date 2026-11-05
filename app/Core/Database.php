<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;
use PDOStatement;
use Throwable;

/**
 * One shared PDO connection plus the small statement helpers every model uses.
 * Emulated prepares stay off, which means values are bound by the driver and a
 * placeholder can never be reused with a different value in one statement.
 */
final class Database
{
    private static ?PDO $pdo = null;

    private static int $transactionDepth = 0;

    public static function pdo(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $config = (array) Config::get('database');
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            (string) ($config['host'] ?? '127.0.0.1'),
            (int) ($config['port'] ?? 3307),
            (string) ($config['name'] ?? 'zapsub'),
            (string) ($config['charset'] ?? 'utf8mb4')
        );

        self::$pdo = new PDO(
            $dsn,
            (string) ($config['user'] ?? 'root'),
            (string) ($config['pass'] ?? ''),
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_STRINGIFY_FETCHES => false,
                self::initCommandAttr() => 'SET NAMES ' . (string) ($config['charset'] ?? 'utf8mb4'),
            ]
        );

        // MySQL 8 accepts the session time zone by name only through the system
        // tables; the numeric offset is portable and matches Asia/Dhaka.
        self::$pdo->exec("SET time_zone = '+06:00'");

        return self::$pdo;
    }

    /**
     * PDO::MYSQL_ATTR_* constants are deprecated as of PHP 8.5 in favour of
     * Pdo\Mysql::*, so resolve whichever exists at runtime instead of hardcoding
     * one and breaking on the other.
     */
    private static function initCommandAttr(): int
    {
        if (class_exists(\Pdo\Mysql::class)) {
            return (int) constant('Pdo\\Mysql::ATTR_INIT_COMMAND');
        }

        return (int) constant('PDO::MYSQL_ATTR_INIT_COMMAND');
    }

    public static function table(string $table): Query
    {
        return new Query($table, self::pdo());
    }

    /** @param array<string, mixed>|list<mixed> $bindings */
    public static function run(string $sql, array $bindings = []): PDOStatement
    {
        $statement = self::pdo()->prepare($sql);

        foreach ($bindings as $key => $value) {
            $name = is_int($key) ? $key + 1 : ':' . ltrim((string) $key, ':');
            $type = match (true) {
                is_int($value) => PDO::PARAM_INT,
                is_bool($value) => PDO::PARAM_BOOL,
                $value === null => PDO::PARAM_NULL,
                default => PDO::PARAM_STR,
            };
            $statement->bindValue($name, $value, $type);
        }

        $statement->execute();

        return $statement;
    }

    /**
     * @param array<string, mixed>|list<mixed> $bindings
     * @return list<array<string, mixed>>
     */
    public static function select(string $sql, array $bindings = []): array
    {
        return self::run($sql, $bindings)->fetchAll();
    }

    /** @param array<string, mixed>|list<mixed> $bindings */
    public static function selectOne(string $sql, array $bindings = []): ?array
    {
        $row = self::run($sql, $bindings)->fetch();

        return $row === false ? null : $row;
    }

    /** @param array<string, mixed>|list<mixed> $bindings */
    public static function scalar(string $sql, array $bindings = []): mixed
    {
        $value = self::run($sql, $bindings)->fetchColumn();

        return $value === false ? null : $value;
    }

    /** @param array<string, mixed>|list<mixed> $bindings */
    public static function execute(string $sql, array $bindings = []): int
    {
        return self::run($sql, $bindings)->rowCount();
    }

    public static function lastInsertId(): int
    {
        return (int) self::pdo()->lastInsertId();
    }

    /**
     * Run a callback inside a transaction. Nested calls become MySQL savepoints
     * so a service can wrap its own writes without knowing whether the caller
     * already opened a transaction.
     *
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    public static function transaction(callable $callback): mixed
    {
        $pdo = self::pdo();

        if (self::$transactionDepth === 0) {
            $pdo->beginTransaction();
        } else {
            $pdo->exec('SAVEPOINT sp' . self::$transactionDepth);
        }

        self::$transactionDepth++;

        try {
            $result = $callback();
            self::$transactionDepth--;

            if (self::$transactionDepth === 0) {
                $pdo->commit();
            } else {
                $pdo->exec('RELEASE SAVEPOINT sp' . self::$transactionDepth);
            }

            return $result;
        } catch (Throwable $exception) {
            self::$transactionDepth--;

            if (self::$transactionDepth === 0) {
                $pdo->rollBack();
            } else {
                $pdo->exec('ROLLBACK TO SAVEPOINT sp' . self::$transactionDepth);
            }

            throw $exception;
        }
    }

    public static function isAvailable(): bool
    {
        try {
            self::pdo()->query('SELECT 1');

            return true;
        } catch (PDOException) {
            return false;
        }
    }
}
