<?php

declare(strict_types=1);

use App\Helpers\Seed;
use Phinx\Seed\AbstractSeed;

/**
 * The five staff roles plus the storefront customer role.
 *
 * The definitions come from database/factories/rbac.php so the admin role screen
 * and the seeder can never drift apart.
 */
final class RoleSeeder extends AbstractSeed
{
    public function run(): void
    {
        $roles = Seed::list('rbac', 'roles');

        foreach ($roles as $role) {
            Seed::upsert($this, 'roles', ['slug' => $role['slug']], [
                'name' => $role['name'],
                'description' => $role['description'],
                'level' => (int) $role['level'],
                'staff' => (bool) $role['staff'],
                'is_system' => (bool) $role['is_system'],
                'status' => 1,
            ]);
        }
    }
}
