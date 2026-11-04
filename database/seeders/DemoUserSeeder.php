<?php

declare(strict_types=1);

use App\Helpers\Seed;
use App\Models\User;
use Phinx\Seed\AbstractSeed;

/**
 * Storefront accounts so the dashboard, wishlist and order screens have an owner
 * to look at. Their emails are marked verified because no mailbox is configured
 * in a demo install; DEMO_USER_PASSWORD in .env sets one password for the group.
 *
 * The last account is left suspended on purpose - the admin customer list needs
 * both statuses to be worth looking at.
 */
final class DemoUserSeeder extends AbstractSeed
{
    private const DEFAULT_PASSWORD = 'Dexxoxx#Demo2026';

    public function run(): void
    {
        Seed::configure();
        Seed::ensure($this, RoleSeeder::class);

        $password = trim((string) env('DEMO_USER_PASSWORD', self::DEFAULT_PASSWORD));
        if ($password === '') {
            $password = self::DEFAULT_PASSWORD;
        }

        $hash = Seed::hashPassword($password);
        $roleId = Seed::id($this, 'roles', ['slug' => 'customer']);
        $defaultLocale = (string) env('DEFAULT_LOCALE', 'en');

        $customers = Seed::list('content', 'demo_customers');
        $last = count($customers) - 1;

        foreach ($customers as $index => $customer) {
            $email = (string) $customer['email'];

            $userId = Seed::upsert($this, 'users', ['email' => $email], [
                'name' => $customer['name'],
                'username' => Seed::uniqueUsername($this, (string) $customer['username'], $email),
                'phone' => $customer['phone'],
                'password' => $hash,
                'status' => $index === $last ? User::STATUS_SUSPENDED : User::STATUS_ACTIVE,
                'email_verified_at' => now(),
                // Half the demo accounts browse in Bengali so the localised
                // storefront has a real session behind it.
                'locale' => $index % 2 === 1 ? 'bn' : $defaultLocale,
                'must_change_password' => false,
            ]);

            Seed::link($this, 'user_roles', ['user_id' => $userId, 'role_id' => $roleId]);
        }
    }
}
