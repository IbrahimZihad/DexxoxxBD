<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Query;

/**
 * Thin array-returning model base. Rows come back as arrays because the whole
 * app treats a record as data, not an object graph.
 */
abstract class Model
{
    protected string $table = '';

    protected string $primaryKey = 'id';

    /** Columns that create()/update() are allowed to write. */
    protected array $fillable = [];

    protected bool $timestamps = true;

    public function table(): string
    {
        return $this->table;
    }

    public function query(): Query
    {
        return Database::table($this->table);
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        return $this->query()->where($this->primaryKey, $id)->first();
    }

    /** @return array<string, mixed>|null */
    public function findBy(string $column, mixed $value): ?array
    {
        return $this->query()->where($column, $value)->first();
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed> only the keys listed in $fillable
     */
    public function filterFillable(array $data): array
    {
        if ($this->fillable === []) {
            return $data;
        }

        $clean = [];

        foreach ($data as $key => $value) {
            if (in_array($key, $this->fillable, true)) {
                $clean[$key] = $value;
            }
        }

        return $clean;
    }

    /** @param array<string, mixed> $data */
    public function create(array $data): int
    {
        $row = $this->filterFillable($data);

        if ($this->timestamps) {
            $now = date('Y-m-d H:i:s');
            $row['created_at'] = $row['created_at'] ?? $now;
            $row['updated_at'] = $row['updated_at'] ?? $now;
        }

        if ($row === []) {
            return 0;
        }

        return $this->query()->insert($row);
    }

    /** @param array<string, mixed> $data */
    public function update(int $id, array $data): int
    {
        $row = $this->filterFillable($data);

        if ($row === []) {
            return 0;
        }

        if ($this->timestamps) {
            $row['updated_at'] = date('Y-m-d H:i:s');
        }

        return $this->query()->where($this->primaryKey, $id)->update($row);
    }

    public function delete(int $id): int
    {
        return $this->query()->where($this->primaryKey, $id)->delete();
    }

    public function countAll(): int
    {
        return $this->query()->count();
    }
}
