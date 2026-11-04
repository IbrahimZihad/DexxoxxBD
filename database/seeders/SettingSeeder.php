<?php

declare(strict_types=1);

use App\Helpers\Seed;
use Phinx\Seed\AbstractSeed;

/**
 * Editable site settings (brand, contact, homepage copy, checkout window). The
 * admin settings screen reads the same rows, so nothing is hardcoded in views.
 */
final class SettingSeeder extends AbstractSeed
{
    public function run(): void
    {
        $sort = 0;

        foreach (Seed::list('content', 'settings') as $setting) {
            $sort += 10;

            Seed::upsert($this, 'settings', ['key' => $setting['key']], [
                'value' => (string) $setting['value'],
                'type' => $setting['type'],
                'group_name' => $setting['group'],
                'label' => $setting['label'],
                'is_public' => (bool) $setting['public'],
                'sort_order' => $sort,
            ]);
        }
    }
}
