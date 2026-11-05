<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class Service extends Model
{
    protected string $table = 'services';

    protected array $fillable = [
        'slug', 'name', 'category', 'brand_color', 'logo_mark', 'tagline',
        'description', 'sort_order', 'is_active', 'show_in_home', 'popular',
    ];

    /**
     * Everything the homepage grid needs in one query: each active service with
     * its cheapest live plan and how many plans it offers.
     *
     * @return list<array<string, mixed>>
     */
    public function catalogue(int $limit = 0, string $category = '', string $search = ''): array
    {
        $sql = 'select s.id, s.slug, s.name, s.category, s.brand_color, s.logo_mark, s.tagline,
                       s.description, s.show_in_home, s.popular,
                       count(p.id) as plan_count,
                       coalesce(min(p.price), 0) as from_price,
                       coalesce(max(p.compare_at_price), 0) as max_compare
                  from services s
                  left join plans p on p.service_id = s.id and p.is_active = 1
                 where s.is_active = 1 and length(s.category) > 0';
        $bindings = [];

        if ($category !== '') {
            $sql .= ' and s.category = :category';
            $bindings[':category'] = $category;
        }

        if ($search !== '') {
            $sql .= ' and (s.name like :search or s.tagline like :search_two or s.description like :search_three)';
            $like = '%' . str_replace(['%', '_'], ['\%', '\_'], trim($search)) . '%';
            // Distinct names: a named placeholder cannot be reused while
            // emulated prepares are off.
            $bindings[':search'] = $like;
            $bindings[':search_two'] = $like;
            $bindings[':search_three'] = $like;
        }

        $sql .= ' group by s.id order by s.popular desc, s.sort_order asc, s.name asc';

        if ($limit > 0) {
            $sql .= ' limit ' . (int) $limit;
        }

        return Database::select($sql, $bindings);
    }

    /** @return list<array<string, mixed>> */
    public function withCheapestPlan(string $slug): array
    {
        return Database::select(
            'select p.* from plans p
              inner join services s on s.id = p.service_id
             where s.slug = :slug and p.is_active = 1
             order by p.duration_months asc, p.price asc',
            [':slug' => $slug]
        );
    }

    /** @return list<string> */
    public function categories(): array
    {
        $rows = Database::select(
            'select category, count(*) as total from services
              where is_active = 1 and category <> ""
              group by category order by total desc'
        );

        return array_map(static fn (array $row): string => (string) $row['category'], $rows);
    }

    /** @return array<string, mixed>|null */
    public function bySlug(string $slug): ?array
    {
        return $this->query()->where('slug', $slug)->where('is_active', 1)->first();
    }

    /** @return list<array<string, mixed>> */
    public function activeOrdered(): array
    {
        return $this->query()->where('is_active', 1)->orderBy(['name' => 'asc'])->get();
    }
}
