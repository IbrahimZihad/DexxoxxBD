<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Query;

final class Plan extends Model
{
    protected string $table = 'plans';

    protected array $fillable = [
        'service_id', 'title', 'duration_months', 'seats', 'quality', 'device',
        'price', 'compare_at_price', 'stock', 'badge', 'description',
        'sort_order', 'is_active', 'is_featured',
    ];

    /** @return array<string, mixed>|null the plan joined to its service */
    public function withService(int $id): ?array
    {
        return Database::selectOne(
            'select p.*, s.name as service_name, s.slug as service_slug, s.brand_color, s.logo_mark,
                    s.category, s.tagline
               from plans p
               inner join services s on s.id = p.service_id
              where p.id = :id and p.is_active = 1 and s.is_active = 1',
            [':id' => $id]
        );
    }

    /** @return list<array<string, mixed>> */
    public function tiersForService(int $serviceId): array
    {
        return $this->query()
            ->where('service_id', $serviceId)
            ->where('is_active', 1)
            ->orderBy(['duration_months' => 'asc', 'price' => 'asc'])
            ->get();
    }

    /**
     * Featured plans for the homepage, one price point per service so the grid
     * does not repeat the same brand three times.
     *
     * @return list<array<string, mixed>>
     */
    public function featured(int $limit = 8): array
    {
        return Database::select(
            'select p.id, p.title, p.price, p.compare_at_price, p.duration_months, p.seats,
                    p.badge, p.quality, p.stock, s.name as service_name, s.slug as service_slug,
                    s.brand_color, s.logo_mark
               from plans p
               inner join services s on s.id = p.service_id
               inner join (
                    select service_id, min(id) as keep_id from plans
                     where is_active = 1 group by service_id
               ) first on first.keep_id = p.id
              where p.is_active = 1 and s.is_active = 1
              order by s.popular desc, p.price asc
              limit ' . max(1, (int) $limit)
        );
    }

    /**
     * Filtered, sorted, paginated list for /plans.
     *
     * @param array<string, mixed> $filters
     * @return array{items: list<array<string, mixed>>, total: int, pages: int, page: int, per_page: int}
     */
    public function browse(array $filters, int $page, int $perPage = 12): array
    {
        $query = $this->filteredQuery($filters);
        $this->applySort($query, (string) ($filters['sort'] ?? 'popular'));

        return $query->paginate($page, $perPage);
    }

    /**
     * @param array<string, mixed> $filters
     * @return list<array<string, mixed>>
     */
    public function browseAll(array $filters, int $limit = 60): array
    {
        $query = $this->filteredQuery($filters);
        $this->applySort($query, (string) ($filters['sort'] ?? 'popular'));

        return $query->limit($limit)->get();
    }

    /** @param array<string, mixed> $filters */
    private function filteredQuery(array $filters): Query
    {
        $query = $this->query()
            ->select(
                'plans.*',
                'services.name as service_name',
                'services.slug as service_slug',
                'services.brand_color',
                'services.logo_mark',
                'services.category'
            )
            ->join('services', 'plans.service_id = services.id')
            ->where('plans.is_active', 1)
            ->where('services.is_active', 1);

        if (($filters['category'] ?? '') !== '') {
            $query->where('services.category', (string) $filters['category']);
        }

        if (($filters['service'] ?? '') !== '') {
            $query->where('services.slug', (string) $filters['service']);
        }

        if (($filters['duration'] ?? '') !== '' && is_numeric($filters['duration'])) {
            $query->where('plans.duration_months', (int) $filters['duration']);
        }

        if (($filters['seats'] ?? '') !== '') {
            $query->where('plans.seats', (int) $filters['seats']);
        }

        if (($filters['max_price'] ?? '') !== '' && is_numeric($filters['max_price'])) {
            $query->where('plans.price', '<=', (float) $filters['max_price']);
        }

        if (($filters['search'] ?? '') !== '') {
            $query->search(['services.name', 'plans.title', 'plans.description', 'services.category'], (string) $filters['search']);
        }

        return $query;
    }

    /**
     * Sort keys are mapped to fixed column lists, so nothing from ?sort= reaches
     * the ORDER BY clause directly.
     */
    private function applySort(Query $query, string $sort): void
    {
        $columns = match ($sort) {
            'price_low' => ['plans.price' => 'asc'],
            'price_high' => ['plans.price' => 'desc'],
            'newest' => ['plans.id' => 'desc'],
            'duration' => ['plans.duration_months' => 'desc'],
            'longest_value' => ['plans.price' => 'asc'],
            default => ['services.popular' => 'desc', 'plans.price' => 'asc'],
        };

        $query->orderBy($columns);
    }

    /** @return list<array<string, mixed>> */
    public function allForAdmin(string $search = ''): array
    {
        $query = $this->query()
            ->select('plans.*', 'services.name as service_name', 'services.brand_color', 'services.logo_mark')
            ->join('services', 'plans.service_id = services.id')
            ->orderBy(['services.name' => 'asc', 'plans.duration_months' => 'asc']);

        if ($search !== '') {
            $query->search(['services.name', 'plans.title', 'plans.description'], $search);
        }

        return $query->limit(300)->get();
    }

    /**
     * Reduce stock only when the plan is actually stock-limited (a negative
     * number means unlimited, which is how the seed data sells most plans).
     */
    public function takeStock(int $planId, int $quantity): bool
    {
        $affected = Database::execute(
            'update plans set stock = stock - :amount
              where id = :id and stock >= :need',
            [':amount' => $quantity, ':id' => $planId, ':need' => $quantity]
        );

        return $affected === 1;
    }

    public function releaseStock(int $planId, int $quantity): void
    {
        Database::execute(
            'update plans set stock = least(stock + :amount, 9999) where id = :id',
            [':amount' => $quantity, ':id' => $planId]
        );
    }

    /** @return list<array<string, mixed>> */
    public function lowStock(int $threshold = 3): array
    {
        return $this->query()
            ->select('plans.id', 'plans.title', 'plans.stock', 'services.name as service_name')
            ->join('services', 'plans.service_id = services.id')
            ->where('plans.is_active', 1)
            ->where('plans.stock', '>=', 0)
            ->where('plans.stock', '<=', $threshold)
            ->orderBy(['plans.stock' => 'asc'])
            ->limit(20)
            ->get();
    }

    /** @return array{min: float, max: float} */
    public function priceRange(): array
    {
        $row = Database::selectOne(
            'select coalesce(min(price), 0) as minimum, coalesce(max(price), 0) as maximum
               from plans where is_active = 1'
        );

        return [
            'min' => (float) ($row['minimum'] ?? 0),
            'max' => (float) ($row['maximum'] ?? 0),
        ];
    }

    /** @return list<int> distinct durations that actually have live plans */
    public function durations(): array
    {
        $rows = Database::select(
            'select distinct duration_months from plans where is_active = 1 order by duration_months asc'
        );

        return array_map(static fn (array $row): int => (int) $row['duration_months'], $rows);
    }
}
