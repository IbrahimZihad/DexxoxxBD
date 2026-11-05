<?php

declare(strict_types=1);

use Phinx\Seed\AbstractSeed;

/**
 * Services and their duration tiers. Prices are derived from a monthly figure so
 * the catalogue stays internally consistent, and the multi-month tiers are priced
 * with the kind of discount a real reseller offers (a year is not 12x a month).
 */
final class CatalogSeeder extends AbstractSeed
{
    public function getDependencies(): array
    {
        return ['SettingsSeeder'];
    }

    public function run(): void
    {
        // Truncating plans would leave existing order items pointing at rows
        // that no longer exist, so this only runs on a shop without real sales.
        $realOrders = (int) $this->fetchRow(
            "select count(*) as total from orders where order_no not like 'ZS-DEMO%'"
        )['total'];

        if ($realOrders > 0) {
            throw new RuntimeException(
                'Refusing to rebuild the catalogue: ' . $realOrders . ' non-demo order(s) exist. '
                . 'Edit services and plans from the admin panel instead.'
            );
        }

        // Turn checks back on after the truncate so the demo catalogue can be
        // rewritten in any order without fighting the foreign keys.
        $this->execute('SET FOREIGN_KEY_CHECKS = 0');
        $this->table('plans')->truncate();
        $this->table('services')->truncate();
        $this->execute('SET FOREIGN_KEY_CHECKS = 1');

        $now = date('Y-m-d H:i:s');
        $servicesTable = $this->table('services');
        $plansTable = $this->table('plans');

        $sort = 0;

        foreach ($this->catalogue() as $service) {
            $sort++;

            $serviceId = $this->insertService($servicesTable, $service, $sort, $now);

            foreach ($service['variants'] as $variant) {
                foreach ($this->tiers() as $months => $definition) {
                    $price = $this->price($variant['monthly'], $definition['factor']);
                    $compare = $this->price($variant['monthly'], $definition['factor'] * 1.35);

                    $plansTable->insert([
                        'service_id' => $serviceId,
                        'title' => $variant['label'] === ''
                            ? $definition['title']
                            : $variant['label'] . ' - ' . $definition['title'],
                        'duration_months' => $months,
                        'seats' => $variant['seats'],
                        'quality' => $variant['quality'],
                        'device' => $variant['device'],
                        'price' => $price,
                        'compare_at_price' => $compare,
                        'stock' => $variant['stock'] ?? -1,
                        'badge' => $definition['badge'],
                        'description' => $variant['note'],
                        'sort_order' => $months,
                        'is_featured' => $months === 3 ? 1 : 0,
                        'is_active' => 1,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ])->saveData();
                }
            }
        }
    }

    /**
     * @param array<string, mixed> $service
     * @return int the new service id
     */
    private function insertService($table, array $service, int $sort, string $now): int
    {
        $table->insert([
            'slug' => $service['slug'],
            'name' => $service['name'],
            'category' => $service['category'],
            'brand_color' => $service['color'],
            'logo_mark' => $service['mark'],
            'tagline' => $service['tagline'],
            'description' => $service['description'],
            'show_in_home' => 1,
            'popular' => $service['popular'],
            'sort_order' => $sort,
            'is_active' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ])->saveData();

        // The table is truncated immediately before this runs and rows go in one
        // at a time, so the newest id is always the service just inserted.
        return (int) $this->fetchRow('select id from services order by id desc limit 1')['id'];
    }

    /** @return array{monthly: float, label: string, seats: int, quality: string, device: string, note: string, stock?: int}[] */
    private function catalogue(): array
    {
        return [
            [
                'slug' => 'netflix-premium',
                'name' => 'Netflix Premium',
                'category' => 'Streaming',
                'color' => '#e50914',
                'mark' => 'N',
                'tagline' => '4K Ultra HD with HDR, watch on two screens at once.',
                'description' => 'Ad-free 4K streaming on the catalogue you already know. You get your own login and can change the password after the first sign-in.',
                'popular' => 10,
                'variants' => [
                    [
                        'monthly' => 480,
                        'label' => 'Premium 4K',
                        'seats' => 2,
                        'quality' => '4K Ultra HD + HDR',
                        'device' => 'TV, laptop, phone, tablet',
                        'note' => 'Two simultaneous screens, downloads on 6 devices, spatial audio.',
                    ],
                    [
                        'monthly' => 290,
                        'label' => 'Standard HD',
                        'seats' => 1,
                        'quality' => '1080p Full HD',
                        'device' => 'Any two devices',
                        'note' => 'One screen at a time, perfect for a phone or a single monitor.',
                        'stock' => 6,
                    ],
                ],
            ],
            [
                'slug' => 'spotify-premium',
                'name' => 'Spotify Premium',
                'category' => 'Music',
                'color' => '#1db954',
                'mark' => 'S',
                'tagline' => 'Music without ads, offline downloads, full skip.',
                'description' => 'Personal account on your own email. Includes offline listening and lossless on supported devices.',
                'popular' => 9,
                'variants' => [
                    [
                        'monthly' => 130,
                        'label' => 'Personal',
                        'seats' => 1,
                        'quality' => '320 kbps',
                        'device' => 'Phone, desktop, speaker',
                        'note' => 'Registered to your own email address, so your playlists stay yours.',
                    ],
                ],
            ],
            [
                'slug' => 'youtube-premium',
                'name' => 'YouTube Premium',
                'category' => 'Streaming',
                'color' => '#ff0000',
                'mark' => 'Y',
                'tagline' => 'No ads, background play and YouTube Music included.',
                'description' => 'Ad-free YouTube plus YouTube Music, downloads and background playback on mobile.',
                'popular' => 9,
                'variants' => [
                    [
                        'monthly' => 150,
                        'label' => 'Individual',
                        'seats' => 1,
                        'quality' => '1080p + downloads',
                        'device' => 'Phone, TV, web',
                        'note' => 'Works on your Google account. Age verification handled by us.',
                    ],
                    [
                        'monthly' => 260,
                        'label' => 'Family (up to 5)',
                        'seats' => 5,
                        'quality' => '1080p + downloads',
                        'device' => 'Phone, TV, web',
                        'note' => 'Five accounts under one household, each with its own recommendations.',
                        'stock' => 3,
                    ],
                ],
            ],
            [
                'slug' => 'chatgpt-plus',
                'name' => 'ChatGPT Plus',
                'category' => 'AI Tools',
                'color' => '#10a37f',
                'mark' => 'G',
                'tagline' => 'Priority access to the latest GPT models and tools.',
                'description' => 'Full Plus access with priority speed, the newest reasoning models and higher upload limits.',
                'popular' => 10,
                'variants' => [
                    [
                        'monthly' => 1750,
                        'label' => 'Plus',
                        'seats' => 1,
                        'quality' => 'Priority access',
                        'device' => 'Web, iOS, Android',
                        'note' => 'Added on your existing account or a fresh one, your choice.',
                        'stock' => 4,
                    ],
                ],
            ],
            [
                'slug' => 'claude-pro',
                'name' => 'Claude Pro',
                'category' => 'AI Tools',
                'color' => '#d97757',
                'mark' => 'C',
                'tagline' => 'Longer conversations, projects and file analysis.',
                'description' => 'Pro tier with higher usage limits, projects, custom instructions and early model access.',
                'popular' => 7,
                'variants' => [
                    [
                        'monthly' => 1900,
                        'label' => 'Pro',
                        'seats' => 1,
                        'quality' => 'Extended usage',
                        'device' => 'Web, iOS',
                        'note' => 'Delivered as an invite to your own email where possible.',
                        'stock' => 2,
                    ],
                ],
            ],
            [
                'slug' => 'canva-pro',
                'name' => 'Canva Pro',
                'category' => 'Design',
                'color' => '#00c4cc',
                'mark' => 'Ca',
                'tagline' => 'Every premium template, brand kits and background remover.',
                'description' => 'Pro design tools including Magic Resize, brand kit, 100GB storage and premium stock.',
                'popular' => 8,
                'variants' => [
                    [
                        'monthly' => 160,
                        'label' => 'Pro (joined to your email)',
                        'seats' => 1,
                        'quality' => 'Full Pro features',
                        'device' => 'Web, app',
                        'note' => 'We add your address to a Pro team - you keep your own designs.',
                    ],
                ],
            ],
            [
                'slug' => 'adobe-creative-cloud',
                'name' => 'Adobe Creative Cloud',
                'category' => 'Design',
                'color' => '#fa0f00',
                'mark' => 'Ad',
                'tagline' => 'Photoshop, Illustrator, Premiere Pro and the full app set.',
                'description' => 'All apps plan with 100GB cloud storage, Adobe Fonts and Firefly credits.',
                'popular' => 6,
                'variants' => [
                    [
                        'monthly' => 1450,
                        'label' => 'All Apps',
                        'seats' => 1,
                        'quality' => 'Desktop + cloud',
                        'device' => 'Windows, macOS',
                        'note' => 'Registered to your Adobe ID; we handle the plan upgrade.',
                        'stock' => 5,
                    ],
                ],
            ],
            [
                'slug' => 'microsoft-365',
                'name' => 'Microsoft 365',
                'category' => 'Productivity',
                'color' => '#f25022',
                'mark' => 'M',
                'tagline' => 'Office apps, 1TB OneDrive and Copilot in Word.',
                'description' => 'Personal subscription with desktop Office apps, terabyte cloud storage and security tools.',
                'popular' => 7,
                'variants' => [
                    [
                        'monthly' => 320,
                        'label' => 'Personal',
                        'seats' => 1,
                        'quality' => 'Desktop apps + 1TB',
                        'device' => 'PC, Mac, mobile',
                        'note' => 'Added on your Microsoft account so your files stay with you.',
                    ],
                ],
            ],
            [
                'slug' => 'google-one-2tb',
                'name' => 'Google One 2TB',
                'category' => 'Cloud',
                'color' => '#4285f4',
                'mark' => 'Go',
                'tagline' => 'Two terabytes for Gmail, Drive and Photos.',
                'description' => 'Storage shared across your Google account, with photo editing and AI overviews included.',
                'popular' => 5,
                'variants' => [
                    [
                        'monthly' => 250,
                        'label' => '2TB',
                        'seats' => 1,
                        'quality' => '2 TB storage',
                        'device' => 'Any Google account',
                        'note' => 'Applied directly to the email you give us at checkout.',
                    ],
                ],
            ],
            [
                'slug' => 'disney-plus',
                'name' => 'Disney+ Hotstar',
                'category' => 'Streaming',
                'color' => '#0b64bc',
                'mark' => 'D',
                'tagline' => 'Disney, Marvel, and live cricket in Full HD.',
                'description' => 'Superb Premium bundle with sports, movies and series on two devices.',
                'popular' => 8,
                'variants' => [
                    [
                        'monthly' => 190,
                        'label' => 'Superb Premium',
                        'seats' => 2,
                        'quality' => 'Full HD + sports',
                        'device' => 'TV, mobile, web',
                        'note' => 'Live cricket and PPV events included where the region allows.',
                        'stock' => 8,
                    ],
                ],
            ],
            [
                'slug' => 'prime-video',
                'name' => 'Amazon Prime Video',
                'category' => 'Streaming',
                'color' => '#00a8e1',
                'mark' => 'P',
                'tagline' => 'Movies, series and sports in Up to 4K.',
                'description' => 'Prime Video membership with downloads and offline viewing on up to three devices.',
                'popular' => 6,
                'variants' => [
                    [
                        'monthly' => 210,
                        'label' => 'Prime Video',
                        'seats' => 3,
                        'quality' => 'Up to 4K',
                        'device' => 'TV, mobile, web',
                        'note' => 'Three simultaneous streams, good for a shared flat.',
                    ],
                ],
            ],
        ];
    }

    /** @return array<int, array{title: string, factor: float, badge: string}> */
    private function tiers(): array
    {
        return [
            1 => ['title' => '1 month', 'factor' => 1.0, 'badge' => ''],
            3 => ['title' => '3 months', 'factor' => 2.7, 'badge' => 'Popular'],
            6 => ['title' => '6 months', 'factor' => 4.8, 'badge' => 'Save 20%'],
            12 => ['title' => '12 months', 'factor' => 8.4, 'badge' => 'Best value'],
        ];
    }

    /** Rounded to the nearest taka so prices read cleanly on the page. */
    private function price(float $monthly, float $factor): float
    {
        return round($monthly * $factor, 0);
    }
}
