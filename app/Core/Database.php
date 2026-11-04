<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;
use PDOStatement;
use RuntimeException;
use Throwable;

/**
 * Thin, safe PDO wrapper. Every read/write goes through prepared statements -
 * there is no API here that accepts interpolated user input.
 */
final class Database
{
    private static ?self $instance = null;

    private ?PDO $pdo = null;

    /** @var array<string, mixed> */
    private array $config;

    private int $transactionLevel = 0;

    /** @var array<int, array{sql: string, bindings: array<string, mixed>, ms: float}> */
    private array $queryLog = [];

    private bool $logQueries = false;

    /**
     * @param array<string, mixed>|null $config connection settings; when null the
     *                                          `database.connections.mysql` config is used
     */
    public function __construct(?array $config = null)
    {
        $this->config = $config ?? (array) Config::get('database.connections.mysql', []);
        $this->logQueries = (bool) Config::get('app.debug_queries', false);
    }

    public static function connection(?array $config = null): self
    {
        if (self::$instance === null) {
            self::$instance = new self($config);
        }

        return self::$instance;
    }

    /** Replace the shared connection (used by the test bootstrap). */
    public static function setConnection(?self $connection): void
    {
        self::$instance = $connection;
    }

    public function pdo(): PDO
    {
        if ($this->pdo instanceof PDO) {
            return $this->pdo;
        }

        $driver = (string) ($this->config['driver'] ?? 'mysql');
        if ($driver !== 'mysql') {
            throw new RuntimeException('This application only supports the MySQL driver.');
        }

        $host = (string) ($this->config['host'] ?? '127.0.0.1');
        $port = (int) ($this->config['port'] ?? 3306);
        $database = (string) ($this->config['database'] ?? '');
        $charset = (string) ($this->config['charset'] ?? 'utf8mb4');

        if ($database === '') {
            throw new RuntimeException('No database name configured (check DB_DATABASE in .env).');
        }

        $dsn = sprintf('%s:host=%s;port=%d;dbname=%s;charset=%s', $driver, $host, $port, $database, $charset);

        try {
            $this->pdo = new PDO(
                $dsn,
                (string) ($this->config['username'] ?? ''),
                (string) ($this->config['password'] ?? ''),
                $this->options($charset)
            );
        } catch (PDOException $exception) {
            // Never leak credentials or DSN details to the caller.
            throw new RuntimeException(
                'Database connection failed. Verify the MySQL service is running and .env is correct.',
                (int) $exception->getCode(),
                $exception
            );
        }

        $timezone = (string) Config::get('app.timezone', 'Asia/Dhaka');
        try {
            $this->pdo->exec("SET time_zone = '" . $this->escapeLiteral($timezone) . "'");
        } catch (PDOException) {
            // Non-fatal: the server default timezone remains in effect.
        }

        return $this->pdo;
    }

    /**
     * @return array<int, mixed>
     */
    private function options(string $charset): array
    {
        return [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            // Real prepared statements: required for injection safety and correct types.
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_STRINGIFY_FETCHES => false,
            $this->initCommand() => 'SET NAMES ' . $this->escapeLiteral($charset)
                . ' COLLATE ' . $this->escapeLiteral($charset . '_unicode_ci')
                . ", sql_mode='STRICT_TRANS_TABLES,NO_ENGINE_SUBSTITUTION'",
        ];
    }

    /**
     * PDO::MYSQL_ATTR_INIT_COMMAND is deprecated in PHP 8.5 in favour of
     * Pdo\Mysql::ATTR_INIT_COMMAND, which does not exist on the 8.2 baseline this
     * project supports - so the constant is resolved at runtime rather than
     * written literally, and neither version emits a deprecation.
     */
    private function initCommand(): int
    {
        return class_exists(\Pdo\Mysql::class)
            ? (int) constant('Pdo\\Mysql::ATTR_INIT_COMMAND')
            : (int) constant('PDO::MYSQL_ATTR_INIT_COMMAND');
    }

    private function escapeLiteral(string $value): string
    {
        return preg_replace('/[^A-Za-z0-9_+-]/', '', $value) ?? '';
    }

    /**
     * @param array<string, mixed>|list<mixed> $bindings
     */
    public function run(string $sql, array $bindings = []): PDOStatement
    {
        $started = microtime(true);
        $statement = $this->pdo()->prepare($sql);

        foreach ($bindings as $key => $value) {
            $name = is_int($key) ? $key + 1 : $key;
            $type = match (true) {
                is_int($value) => PDO::PARAM_INT,
                is_bool($value) => PDO::PARAM_BOOL,
                $value === null => PDO::PARAM_NULL,
                default => PDO::PARAM_STR,
            };
            $statement->bindValue($name, $value, $type);
        }

        $statement->execute();

        if ($this->logQueries) {
            $this->queryLog[] = [
                'sql' => $sql,
                'bindings' => $bindings,
                'ms' => round((microtime(true) - $started) * 1000, 2),
            ];
        }

        return $statement;
    }

    /** @return list<array<string, mixed>> */
    public function select(string $sql, array $bindings = []): array
    {
        return $this->run($sql, $bindings)->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array<string, mixed>|null */
    public function selectOne(string $sql, array $bindings = []): ?array
    {
        $row = $this->run($sql, $bindings)->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    public function scalar(string $sql, array $bindings = []): mixed
    {
        $value = $this->run($sql, $bindings)->fetchColumn();
        return $value === false ? null : $value;
    }

    public function statement(string $sql, array $bindings = []): int
    {
        return $this->run($sql, $bindings)->rowCount();
    }

    public function insert(string $table, array $values): int
    {
        $columns = array_keys($values);

        $sql = 'INSERT INTO `' . $table . '` (`' . implode('`, `', $columns) . '`) VALUES ('
            . implode(', ', array_map(static fn (string $c): string => ':' . $c, $columns)) . ')';

        $bindings = [];
        foreach ($values as $column => $value) {
            $bindings[$column] = $value;
        }

        $this->run($sql, $bindings);

        return (int) $this->pdo()->lastInsertId();
    }

    /**
     * @param array<string, mixed> $values
     * @param array<string, mixed> $bindings where keys are prefixed with `w_`
     */
    public function update(string $table, array $values, string $where, array $bindings = []): int
    {
        if ($values === []) {
            return 0;
        }

        $assignments = [];
        $merged = [];
        foreach ($values as $column => $value) {
            $placeholder = 'set_' . $column;
            $assignments[] = '`' . $column . '` = :' . $placeholder;
            $merged[$placeholder] = $value;
        }

        $sql = 'UPDATE `' . $table . '` SET ' . implode(', ', $assignments) . ' WHERE ' . $where;

        return $this->statement($sql, array_merge($merged, $bindings));
    }

    public function delete(string $table, string $where, array $bindings = []): int
    {
        return $this->statement('DELETE FROM `' . $table . '` WHERE ' . $where, $bindings);
    }

    /**
     * Run a callback inside a transaction, with savepoint support for nesting.
     *
     * @template T
     * @param callable(self): T $callback
     * @return T
     */
    public function transaction(callable $callback): mixed
    {
        $this->begin();

        try {
            $result = $callback($this);
            $this->commit();
            return $result;
        } catch (Throwable $exception) {
            $this->rollBack();
            throw $exception;
        }
    }

    public function begin(): void
    {
        if ($this->transactionLevel === 0) {
            $this->pdo()->beginTransaction();
        } else {
            $this->pdo()->exec('SAVEPOINT trans' . $this->transactionLevel);
        }
        $this->transactionLevel++;
    }

    public function commit(): void
    {
        $this->transactionLevel--;
        if ($this->transactionLevel <= 0) {
            $this->transactionLevel = 0;
            if ($this->pdo()->inTransaction()) {
                $this->pdo()->commit();
            }
            return;
        }
        $this->pdo()->exec('RELEASE SAVEPOINT trans' . $this->transactionLevel);
    }

    public function rollBack(): void
    {
        $this->transactionLevel--;
        if ($this->transactionLevel <= 0) {
            $this->transactionLevel = 0;
            if ($this->pdo()->inTransaction()) {
                $this->pdo()->rollBack();
            }
            return;
        }
        $this->pdo()->exec('ROLLBACK TO SAVEPOINT trans' . $this->transactionLevel);
    }

    public function inTransaction(): bool
    {
        return $this->transactionLevel > 0;
    }

    public function tableExists(string $table): bool
    {
        $count = (int) $this->scalar(
            'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :table',
            ['table' => $table]
        );
        return $count > 0;
    }

    /** @return list<array{sql: string, bindings: array<string, mixed>, ms: float}> */
    public function queryLog(): array
    {
        return $this->queryLog;
    }

    public function enableQueryLog(bool $enabled = true): void
    {
        $this->logQueries = $enabled;
    }

    /**
     * Lock a row for the current transaction so two concurrent requests cannot
     * consume the same digital inventory record.
     *
     * @return array<string, mixed>|null
     */
    public function selectForUpdate(string $sql, array $bindings = []): ?array
    {
        $row = $this->run($sql . ' FOR UPDATE', $bindings)->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }
}
