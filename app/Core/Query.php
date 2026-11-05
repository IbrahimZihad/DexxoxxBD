<?php

declare(strict_types=1);

namespace App\Core;

use InvalidArgumentException;
use PDO;

/**
 * A small fluent query builder. Every value goes through a bound placeholder and
 * every column name is checked against an identifier pattern, so callers cannot
 * smuggle SQL in through a filter or a sort parameter.
 */
final class Query
{
    private const OPERATORS = ['=', '!=', '<>', '>', '<', '>=', '<=', 'LIKE', 'NOT LIKE'];

    /** @var list<string> */
    private array $columns = ['*'];

    /** @var list<string> */
    private array $joins = [];

    /** @var list<string> */
    private array $conditions = [];

    /** @var array<string, mixed> */
    private array $bindings = [];

    /** @var list<string> */
    private array $orders = [];

    /** @var list<string> */
    private array $groups = [];

    private ?int $limit = null;

    private int $offset = 0;

    private int $counter = 0;

    public function __construct(private string $table, private PDO $pdo)
    {
        $this->assertIdentifier($table);
    }

    public function select(string ...$columns): self
    {
        $this->columns = [];

        foreach ($columns as $column) {
            $this->columns[] = $this->wrapColumn($column);
        }

        return $this;
    }

    /** Escape hatch for aggregate expressions like "count(*) as total". */
    public function selectRaw(string $expression): self
    {
        $this->columns[] = $expression;

        return $this;
    }

    public function join(string $table, string $on, string $type = 'inner'): self
    {
        $type = strtolower($type);
        if (!in_array($type, ['inner', 'left', 'right'], true)) {
            throw new InvalidArgumentException('Unsupported join type.');
        }

        [$left, $right] = array_map('trim', explode('=', $on, 2));

        $this->joins[] = sprintf(
            '%s JOIN %s ON %s = %s',
            strtoupper($type),
            $this->wrapColumn(trim($table)),
            $this->wrapColumn($left),
            $this->wrapColumn($right)
        );

        return $this;
    }

    public function where(string $column, mixed $operator = null, mixed $value = null): self
    {
        if (func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
        }

        $this->conditions[] = $this->wrapColumn($column) . ' ' . $this->operator((string) $operator)
            . ' ' . $this->bind($value);

        return $this;
    }

    public function orWhere(string $column, mixed $operator = null, mixed $value = null): self
    {
        if (func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
        }

        $clause = $this->wrapColumn($column) . ' ' . $this->operator((string) $operator) . ' ' . $this->bind($value);
        $this->appendCompound($clause, 'OR');

        return $this;
    }

    /**
     * Add "a LIKE %term% OR b LIKE %term%" as one grouped condition. Each column
     * gets its own placeholder: a named placeholder cannot repeat inside one
     * statement while emulated prepares are off.
     *
     * @param list<string> $columns
     */
    public function search(array $columns, string $term): self
    {
        $term = trim($term);
        if ($term === '' || $columns === []) {
            return $this;
        }

        $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $term) . '%';
        $parts = [];

        foreach ($columns as $column) {
            $parts[] = $this->wrapColumn($column) . ' LIKE ' . $this->bind($like);
        }

        $this->conditions[] = '(' . implode(' OR ', $parts) . ')';

        return $this;
    }

    public function whereIn(string $column, array $values): self
    {
        if ($values === []) {
            $this->conditions[] = '1 = 0';

            return $this;
        }

        $placeholders = array_map(fn (mixed $value): string => $this->bind($value), $values);
        $this->conditions[] = $this->wrapColumn($column) . ' IN (' . implode(', ', $placeholders) . ')';

        return $this;
    }

    public function whereNull(string $column): self
    {
        $this->conditions[] = $this->wrapColumn($column) . ' IS NULL';

        return $this;
    }

    public function whereNotNull(string $column): self
    {
        $this->conditions[] = $this->wrapColumn($column) . ' IS NOT NULL';

        return $this;
    }

    /** @param array<string, string> $columns column => "asc"|"desc" */
    public function orderBy(array $columns): self
    {
        foreach ($columns as $column => $direction) {
            $direction = strtolower((string) $direction) === 'desc' ? 'DESC' : 'ASC';
            $this->orders[] = $this->wrapColumn((string) $column) . ' ' . $direction;
        }

        return $this;
    }

    public function groupBy(string ...$columns): self
    {
        foreach ($columns as $column) {
            $this->groups[] = $this->wrapColumn($column);
        }

        return $this;
    }

    public function limit(int $limit): self
    {
        $this->limit = max(0, $limit);

        return $this;
    }

    public function offset(int $offset): self
    {
        $this->offset = max(0, $offset);

        return $this;
    }

    /** @return list<array<string, mixed>> */
    public function get(): array
    {
        return Database::select($this->compileSelect(), $this->bindings);
    }

    /** @return array<string, mixed>|null */
    public function first(): ?array
    {
        $this->limit(1)->offset(0);

        return Database::selectOne($this->compileSelect(), $this->bindings);
    }

    public function value(string $column): mixed
    {
        $this->columns = [$this->wrapColumn($column)];
        $row = $this->first();

        return $row === null ? null : reset($row);
    }

    public function count(string $expression = '*'): int
    {
        $this->columns = ['count(' . ($expression === '*' ? '*' : $this->wrapColumn($expression)) . ') as aggregate'];
        $row = $this->first();

        return (int) ($row['aggregate'] ?? 0);
    }

    public function sum(string $column): float
    {
        $this->columns = ['coalesce(sum(' . $this->wrapColumn($column) . '), 0) as aggregate'];
        $row = $this->first();

        return (float) ($row['aggregate'] ?? 0);
    }

    public function exists(): bool
    {
        return $this->count() > 0;
    }

    /**
     * @return array{items: list<array<string, mixed>>, total: int, pages: int, page: int, per_page: int}
     */
    public function paginate(int $page, int $perPage): array
    {
        $page = max(1, $page);
        $perPage = min(100, max(1, $perPage));

        $counter = clone $this;
        $total = $counter->count();

        $items = (clone $this)
            ->limit($perPage)
            ->offset(($page - 1) * $perPage)
            ->get();

        return [
            'items' => $items,
            'total' => $total,
            'pages' => (int) ceil($total / $perPage),
            'page' => $page,
            'per_page' => $perPage,
        ];
    }

    /** @param array<string, mixed> $data */
    public function insert(array $data): int
    {
        $columns = [];
        $placeholders = [];

        foreach ($data as $column => $value) {
            $this->assertIdentifier((string) $column);
            $columns[] = '`' . $column . '`';
            $placeholders[] = $this->bind($value);
        }

        $sql = sprintf(
            'insert into %s (%s) values (%s)',
            $this->wrapColumn($this->table),
            implode(', ', $columns),
            implode(', ', $placeholders)
        );

        Database::run($sql, $this->bindings);

        return Database::lastInsertId();
    }

    /** @param array<string, mixed> $data */
    public function update(array $data): int
    {
        $sets = [];
        // The where bindings must survive, so build the assignment values into a
        // separate list and merge them in the compiled statement order.
        $assignments = [];

        foreach ($data as $column => $value) {
            $this->assertIdentifier((string) $column);
            $key = ':set_' . (++$this->counter);
            $this->bindings[$key] = $value;
            $assignments[$key] = $value;
            $sets[] = '`' . $column . '` = ' . $key;
        }

        $sql = sprintf(
            'update %s set %s%s',
            $this->wrapColumn($this->table),
            implode(', ', $sets),
            $this->compileWhere()
        );

        return Database::execute($sql, $this->bindings);
    }

    public function delete(): int
    {
        $sql = sprintf('delete from %s%s', $this->wrapColumn($this->table), $this->compileWhere());

        return Database::execute($sql, $this->bindings);
    }

    public function increment(string $column, int|float $amount = 1, array $extra = []): int
    {
        $this->assertIdentifier($column);
        $sets = ['`' . $column . '` = `' . $column . '` + ' . $this->bind($amount)];

        foreach ($extra as $extraColumn => $value) {
            $this->assertIdentifier((string) $extraColumn);
            $sets[] = '`' . $extraColumn . '` = ' . $this->bind($value);
        }

        $sql = sprintf('update %s set %s%s', $this->wrapColumn($this->table), implode(', ', $sets), $this->compileWhere());

        return Database::execute($sql, $this->bindings);
    }

    public function sql(): string
    {
        return $this->compileSelect();
    }

    /** @return array<string, mixed> */
    public function bindings(): array
    {
        return $this->bindings;
    }

    private function appendCompound(string $clause, string $glue): void
    {
        if ($this->conditions === []) {
            $this->conditions[] = $clause;

            return;
        }

        $last = array_pop($this->conditions);
        $this->conditions[] = '(' . $last . ') ' . $glue . ' (' . $clause . ')';
    }

    private function compileSelect(): string
    {
        $sql = 'select ' . implode(', ', $this->columns) . ' from ' . $this->wrapColumn($this->table);

        if ($this->joins !== []) {
            $sql .= ' ' . implode(' ', $this->joins);
        }

        $sql .= $this->compileWhere();

        if ($this->groups !== []) {
            $sql .= ' group by ' . implode(', ', $this->groups);
        }

        if ($this->orders !== []) {
            $sql .= ' order by ' . implode(', ', $this->orders);
        }

        if ($this->limit !== null) {
            // Limits are integers produced by this class, never user input, so
            // they are interpolated rather than bound (MySQL rejects placeholders
            // in some LIMIT positions with emulated prepares off).
            $sql .= ' limit ' . $this->limit;

            if ($this->offset > 0) {
                $sql .= ' offset ' . $this->offset;
            }
        }

        return $sql;
    }

    private function compileWhere(): string
    {
        if ($this->conditions === []) {
            return '';
        }

        return ' where ' . implode(' and ', $this->conditions);
    }

    private function operator(string $operator): string
    {
        $normalized = strtoupper(trim($operator));
        if (!in_array($normalized, self::OPERATORS, true)) {
            throw new InvalidArgumentException('Unsupported operator: ' . $operator);
        }

        return $normalized;
    }

    private function bind(mixed $value): string
    {
        $name = ':p' . (++$this->counter);
        $this->bindings[$name] = $value;

        return $name;
    }

    private function wrapColumn(string $column): string
    {
        $column = trim($column);

        if ($column === '*') {
            return '*';
        }

        // "<expr> as <alias>" is wrapped on both sides so "plans as p" works in a
        // join without falling through to the raw-expression branch.
        if (preg_match('/^(.+)\s+as\s+([a-zA-Z_][a-zA-Z0-9_]*)$/i', $column, $matches) === 1) {
            return $this->wrapColumn($matches[1]) . ' as ' . $matches[2];
        }

        if (preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $column) === 1) {
            return '`' . $column . '`';
        }

        if (preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*(\.[a-zA-Z_][a-zA-Z0-9_]*|\.\*)$/', $column) === 1) {
            $parts = explode('.', $column);
            foreach ($parts as $index => $part) {
                if ($part !== '*') {
                    $this->assertIdentifier($part);
                    $parts[$index] = '`' . $part . '`';
                }
            }

            return implode('.', $parts);
        }

        // Aggregates such as count(*) or sum(total). They only ever originate in
        // this codebase; request input reaches this class through whitelists in
        // the controllers, never as a raw column expression.
        if (preg_match("/^[a-zA-Z_][a-zA-Z0-9_]*\([\w.,*\s]*\)$/", $column) === 1) {
            return $column;
        }

        throw new InvalidArgumentException('Invalid column name: ' . $column);
    }

    private function assertIdentifier(string $identifier): void
    {
        if (preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $identifier) !== 1) {
            throw new InvalidArgumentException('Invalid SQL identifier: ' . $identifier);
        }
    }
}
