<?php

declare(strict_types=1);

use App\Core\Auth;
use App\Core\Env;
use Phinx\Seed\AbstractSeed;

/**
 * The three accounts the app needs to exist before anyone registers: an owner,
 * an agent who works the delivery queue, and a customer for the demo orders.
 *
 * Passwords are never written into this file. They come from .env, and a missing
 * one stops the seed rather than silently falling back to a guessable default.
 */
final class UserSeeder extends AbstractSeed
{
    public function getDependencies(): array
    {
        return [];
    }

    public function run(): void
    {
        $adminPassword = $this->required('SEED_ADMIN_PASSWORD');
        $staffPassword = $this->required('SEED_STAFF_PASSWORD');
        $customerPassword = $this->required('SEED_CUSTOMER_PASSWORD');

        $now = date('Y-m-d H:i:s');

        $rows = [
            [
                'name' => 'Zahid Ibrahim',
                'email' => 'admin@zapsub.test',
                'phone' => '01712345678',
                'password_hash' => Auth::hash($adminPassword),
                'role' => 'admin',
                'is_active' => 1,
                'marketing_opt_in' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Rafi Ahmed',
                'email' => 'rafi@zapsub.test',
                'phone' => '01812345678',
                'password_hash' => Auth::hash($staffPassword),
                'role' => 'staff',
                'is_active' => 1,
                'marketing_opt_in' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Tanvir Ahmed',
                'email' => 'tanvir@example.com',
                'phone' => '01912345678',
                'password_hash' => Auth::hash($customerPassword),
                'role' => 'customer',
                'is_active' => 1,
                'marketing_opt_in' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Nusrat Jahan',
                'email' => 'nusrat@example.com',
                'phone' => '01612345678',
                // Deactivated account: the login screen must reject it with the
                // same generic message as a wrong password.
                'password_hash' => Auth::hash($customerPassword),
                'role' => 'customer',
                'is_active' => 0,
                'marketing_opt_in' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ];

        $table = $this->table('users');
        $table->truncate();
        $table->insert($rows)->saveData();
    }

    private function required(string $key): string
    {
        $value = trim(Env::get($key, ''));

        if ($value === '') {
            throw new RuntimeException(
                $key . ' is not set in .env. Add it there, then run "composer seed" again. '
                . 'Seed passwords are never hardcoded in the seeder.'
            );
        }

        if (strlen($value) < 8) {
            throw new RuntimeException($key . ' must be at least 8 characters.');
        }

        return $value;
    }
}
