<?php
declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;

final class Database
{
    private static ?PDO $pdo = null;
    private static int $depth = 0;

    /** Used by the CLI tools so migrate/seed share one connection with the repositories. */
    public static function adopt(PDO $pdo): void
    {
        self::$pdo = $pdo;
    }

    public static function pdo(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $db = Config::get('db');

        if (empty($db['name']) || empty($db['user'])) {
            Logger::error('Database credentials missing from .env');
            throw new \RuntimeException('Database is not configured.');
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $db['host'],
            $db['port'],
            $db['name'],
            $db['charset']
        );

        try {
            self::$pdo = new PDO($dsn, $db['user'], $db['pass'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_STRINGIFY_FETCHES => false,
            ]);
        } catch (PDOException $e) {
            // The driver message can contain host/user details — log it, never return it.
            Logger::error('Database connection failed', ['code' => $e->getCode()]);
            throw new \RuntimeException('Database connection failed.');
        }

        return self::$pdo;
    }

    /**
     * Runs $callback inside a transaction. Nested calls join the ambient
     * transaction through a savepoint, because PDO cannot begin a second one.
     * Re-throws after rollback so callers decide how to respond.
     */
    public static function transaction(callable $callback): mixed
    {
        $pdo = self::pdo();
        $depth = self::$depth;
        $outermost = $depth === 0;
        $savepoint = 'dx_trans_' . $depth;

        if ($outermost) {
            $pdo->beginTransaction();
        } else {
            $pdo->exec('SAVEPOINT ' . $savepoint);
        }

        self::$depth = $depth + 1;

        try {
            $result = $callback($pdo);
        } catch (\Throwable $e) {
            self::$depth = $depth;

            if ($outermost) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
            } else {
                $pdo->exec('ROLLBACK TO SAVEPOINT ' . $savepoint);
            }

            throw $e;
        }

        self::$depth = $depth;

        if ($outermost) {
            $pdo->commit();
        } else {
            $pdo->exec('RELEASE SAVEPOINT ' . $savepoint);
        }

        return $result;
    }

    public static function select(string $sql, array $bindings = []): array
    {
        $statement = self::pdo()->prepare($sql);
        $statement->execute($bindings);

        return $statement->fetchAll();
    }

    public static function selectOne(string $sql, array $bindings = []): ?array
    {
        $statement = self::pdo()->prepare($sql);
        $statement->execute($bindings);
        $row = $statement->fetch();

        return $row === false ? null : $row;
    }

    public static function statement(string $sql, array $bindings = []): int
    {
        $statement = self::pdo()->prepare($sql);
        $statement->execute($bindings);

        return $statement->rowCount();
    }

    public static function insert(string $sql, array $bindings = []): int
    {
        $pdo = self::pdo();
        $statement = $pdo->prepare($sql);
        $statement->execute($bindings);

        return (int) $pdo->lastInsertId();
    }
}
