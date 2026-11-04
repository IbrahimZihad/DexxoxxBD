<?php

declare(strict_types=1);

use App\Helpers\Seed;
use Phinx\Seed\AbstractSeed;

/**
 * Twelve top-level shelves, each with two or three child categories, so the
 * category tree, breadcrumbs and nested filters all have real data to work on.
 */
final class CategorySeeder extends AbstractSeed
{
    public function run(): void
    {
        $sort = 0;

        foreach (Seed::list('catalog', 'categories') as $category) {
            $sort += 10;
            $parentId = $this->store($category, null, $sort, true);

            foreach ($category['children'] ?? [] as $childIndex => $child) {
                $this->store($child, $parentId, ($sort + $childIndex + 1) * 10, false);
            }
        }
    }

    /**
     * @param array<string, mixed> $category
     */
    private function store(array $category, ?int $parentId, int $sortOrder, bool $isParent): int
    {
        $description = (string) ($category['description'] ?? $category['name'] . ' plans available for monthly and annual terms.');

        return Seed::upsert($this, 'categories', ['slug' => $category['slug']], [
            'parent_id' => $parentId,
            'name' => $category['name'],
            'description' => $description,
            'icon' => $category['icon'] ?? null,
            'sort_order' => $sortOrder,
            'is_featured' => $isParent,
            'status' => 1,
            'meta_title' => $category['name'] . ' subscriptions - DexxoxxBD',
            'meta_description' => mb_substr($description, 0, 155),
        ]);
    }
}
