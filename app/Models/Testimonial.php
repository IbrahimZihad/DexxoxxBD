<?php

declare(strict_types=1);

namespace App\Models;

final class Testimonial extends Model
{
    protected string $table = 'testimonials';

    protected array $fillable = [
        'name', 'location', 'service_label', 'rating', 'quote', 'is_active',
        'sort_order', 'created_at', 'updated_at',
    ];

    /** @return list<array<string, mixed>> */
    public function published(int $limit = 6): array
    {
        return $this->query()
            ->where('is_active', 1)
            ->orderBy(['sort_order' => 'asc', 'id' => 'desc'])
            ->limit($limit)
            ->get();
    }

    /** @return list<array<string, mixed>> */
    public function allForAdmin(): array
    {
        return $this->query()->orderBy(['id' => 'desc'])->limit(200)->get();
    }
}
