<?php

declare(strict_types=1);

use App\Helpers\Seed;
use Phinx\Seed\AbstractSeed;

/**
 * The service brands behind the demo catalog. Every name is invented: a real
 * marketplace may only list access rights it is authorised to distribute, so the
 * demo ships with fictional suppliers instead of trademarked ones.
 */
final class BrandSeeder extends AbstractSeed
{
    public function run(): void
    {
        $sort = 0;

        foreach (Seed::list('catalog', 'brands') as $brand) {
            $sort += 10;

            Seed::upsert($this, 'brands', ['slug' => $brand['slug']], [
                'name' => $brand['name'],
                'description' => $brand['description'],
                'country' => $brand['country'],
                'logo' => 'brands/' . $brand['slug'] . '.svg',
                'sort_order' => $sort,
                'status' => 1,
            ]);
        }
    }
}
