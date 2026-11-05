<?php

declare(strict_types=1);

namespace App\Models;

final class Faq extends Model
{
    protected string $table = 'faqs';

    protected array $fillable = [
        'question', 'answer', 'category', 'sort_order', 'is_active', 'created_at', 'updated_at',
    ];

    /** @return list<array<string, mixed>> */
    public function published(string $category = '', int $limit = 20): array
    {
        $query = $this->query()->where('is_active', 1)->orderBy(['sort_order' => 'asc', 'id' => 'asc']);

        if ($category !== '') {
            $query->where('category', $category);
        }

        return $query->limit($limit)->get();
    }

    /** @return list<array<string, mixed>> */
    public function allForAdmin(): array
    {
        return $this->query()->orderBy(['sort_order' => 'asc'])->limit(200)->get();
    }
}
