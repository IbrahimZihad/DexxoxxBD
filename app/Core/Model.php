<?php

declare(strict_types=1);

namespace App\Core;

use App\Exceptions\HttpException;
use RuntimeException;

/**
 * Minimal active-record-ish base. Deliberately explicit: subclasses write their
 * own SQL with bound parameters, which keeps MySQL tuning visible and auditable.
 */
abstract class Model
{
    protected string $table = '';

    protected string $primaryKey = 'id';

    protected bool $timestamps = true;

    protected bool $softDeletes = false;

    protected string $deletedAtColumn = 'deleted_at';

    public function db(): Database
    {
        return Database::connection();
    }

    public function table(): string
    {
        if ($this->table === '') {
            throw new RuntimeException('Model has no table name.');
        }
        return $this->table;
    }

    /** @return array<string, mixed>|null */
    public function find(int|string $id): ?array
    {
        return $this->db()->selectOne(
            'SELECT * FROM `' . $this->table() . '` WHERE `' . $this->primaryKey . '` = :id' . $this->aliveClause(),
            ['id' => $id]
        );
    }

    /** @return array<string, mixed> */
    public function findOrFail(int|string $id): array
    {
        $row = $this->find($id);
        if ($row === null) {
            throw new HttpException(404, 'The requested record no longer exists.');
        }
        return $row;
    }

    /** @return array<string, mixed>|null */
    public function findBy(string $column, mixed $value): ?array
    {
        return $this->db()->selectOne(
            'SELECT * FROM `' . $this->table() . '` WHERE `' . $column . '` = :value'
                . $this->aliveClause() . ' LIMIT 1',
            ['value' => $value]
        );
    }

    /** @return list<array<string, mixed>> */
    public function all(array $orderBy = [], ?int $limit = null): array
    {
        $sql = 'SELECT * FROM `' . $this->table() . '`' . $this->aliveClause() . $this->orderBySql($orderBy);
        if ($limit !== null) {
            $sql .= ' LIMIT ' . (int) $limit;
        }
        return $this->db()->select($sql);
    }

    /** @return list<array<string, mixed>> */
    public function where(
        string $sql,
        array $bindings = [],
        array $orderBy = [],
        ?int $limit = null,
        int $offset = 0
    ): array
    {
        $query = 'SELECT * FROM `' . $this->table() . '` WHERE ' . $sql . $this->aliveAnd($sql)
            . $this->orderBySql($orderBy);

        if ($limit !== null) {
            $query .= ' LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset;
        }

        return $this->db()->select($query, $bindings);
    }

    public function count(string $sql = '1 = 1', array $bindings = []): int
    {
        $query = 'SELECT COUNT(*) FROM `' . $this->table() . '` WHERE ' . $sql . $this->aliveAnd($sql);
        return (int) $this->db()->scalar($query, $bindings);
    }

    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): int
    {
        if ($this->timestamps && !array_key_exists('created_at', $attributes)) {
            $attributes['created_at'] = now();
        }
        if ($this->timestamps && !array_key_exists('updated_at', $attributes)) {
            $attributes['updated_at'] = now();
        }

        return $this->db()->insert($this->table(), $attributes);
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public function updateById(int|string $id, array $attributes): int
    {
        if ($attributes === []) {
            return 0;
        }
        if ($this->timestamps && !array_key_exists('updated_at', $attributes)) {
            $attributes['updated_at'] = now();
        }

        return $this->db()->update(
            $this->table(),
            $attributes,
            '`' . $this->primaryKey . '` = :pk_id',
            ['pk_id' => $id]
        );
    }

    public function deleteById(int|string $id): int
    {
        if ($this->softDeletes) {
            return $this->db()->statement(
                'UPDATE `' . $this->table() . '` SET `' . $this->deletedAtColumn . '` = NOW() WHERE `'
                . $this->primaryKey . '` = :id AND `' . $this->deletedAtColumn . '` IS NULL',
                ['id' => $id]
            );
        }

        return $this->db()->delete($this->table(), '`' . $this->primaryKey . '` = :id', ['id' => $id]);
    }

    public function restoreById(int|string $id): int
    {
        if (!$this->softDeletes) {
            return 0;
        }
        return $this->db()->statement(
            'UPDATE `' . $this->table() . '` SET `' . $this->deletedAtColumn . '` = NULL WHERE `'
            . $this->primaryKey . '` = :id',
            ['id' => $id]
        );
    }

    protected function aliveClause(): string
    {
        return $this->softDeletes ? ' WHERE `' . $this->deletedAtColumn . '` IS NULL' : '';
    }

    private function aliveAnd(string $sql): string
    {
        if (!$this->softDeletes) {
            return '';
        }
        return ' AND `' . $this->deletedAtColumn . '` IS NULL';
    }

    /** @param array<string, string> $orderBy column => asc|desc */
    protected function orderBySql(array $orderBy): string
    {
        if ($orderBy === []) {
            return '';
        }
        $parts = [];
        foreach ($orderBy as $column => $direction) {
            $direction = strtolower((string) $direction) === 'desc' ? 'DESC' : 'ASC';
            // Column names cannot be bound: whitelist the characters instead.
            $safe = preg_replace('/[^A-Za-z0-9_.]/', '', (string) $column);
            if ($safe === null || $safe === '') {
                continue;
            }
            $parts[] = $safe . ' ' . $direction;
        }
        return $parts === [] ? '' : ' ORDER BY ' . implode(', ', $parts);
    }
}
