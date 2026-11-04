<?php

declare(strict_types=1);

use App\Helpers\Seed;
use Phinx\Seed\AbstractSeed;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Runs the demo data in dependency order.
 *
 * `vendor/bin/phinx seed:run` executes every seeder in this folder, and each one
 * resolves its own dependencies, so the orchestrator is only needed when it is
 * asked for by name - that is what `database:reset` and a fresh install use:
 *
 *   vendor/bin/phinx seed:run -s DatabaseSeeder
 */
final class DatabaseSeeder extends AbstractSeed
{
    private const ORDER = [
        RoleSeeder::class,
        PermissionSeeder::class,
        AdminUserSeeder::class,
        DemoUserSeeder::class,
        CategorySeeder::class,
        BrandSeeder::class,
        ProductSeeder::class,
        ProductVariationSeeder::class,
        CouponSeeder::class,
        FAQSeeder::class,
        SettingSeeder::class,
        CMSSeeder::class,
    ];

    /**
     * @return list<string>
     */
    public function getDependencies(): array
    {
        return self::ORDER;
    }

    public function shouldExecute(): bool
    {
        $selected = (array) $this->getInput()->getOption('seed');

        return in_array(self::class, $selected, true);
    }

    public function run(): void
    {
        Seed::configure();

        foreach (self::ORDER as $class) {
            Seed::ensure($this, $class);
        }

        foreach (['users', 'categories', 'brands', 'products', 'product_variations', 'coupons', 'pages', 'settings'] as $table) {
            $count = (int) Seed::scalar($this, 'SELECT COUNT(*) FROM `' . $table . '`');
            $this->getOutput()->writeln(sprintf('  %-20s %6d rows', $table, $count), OutputInterface::VERBOSITY_NORMAL);
        }
    }
}
