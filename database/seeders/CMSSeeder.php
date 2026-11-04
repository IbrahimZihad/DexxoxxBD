<?php

declare(strict_types=1);

use App\Helpers\Seed;
use Phinx\Seed\AbstractSeed;

/**
 * Static pages, banners and header/footer navigation. Menus resolve their label
 * targets to real category and page ids, which is how the header stays correct
 * when a slug is renamed later.
 */
final class CMSSeeder extends AbstractSeed
{
    public function run(): void
    {
        Seed::ensure($this, CategorySeeder::class);

        $this->pages();
        $this->banners();
        $this->testimonials();
        $this->menus();
    }

    private function pages(): void
    {
        foreach (Seed::list('content', 'pages') as $page) {
            Seed::upsert($this, 'pages', ['slug' => $page['slug']], [
                'title' => $page['title'],
                'excerpt' => $page['excerpt'],
                'content' => $page['content'],
                'meta_title' => $page['meta_title'],
                'meta_description' => $page['meta_description'],
                // pages.status is an enum, so the integer 1 would silently store
                // the first label ('draft') and every content page would 404.
                'status' => 'published',
                'is_footer' => (bool) ($page['footer'] ?? true),
                'sort_order' => (int) ($page['sort'] ?? 0),
            ]);
        }
    }

    private function banners(): void
    {
        foreach (Seed::list('content', 'banners') as $banner) {
            Seed::upsert($this, 'banners', ['placement' => $banner['placement'], 'title' => $banner['title']], [
                'subtitle' => $banner['subtitle'],
                'description' => $banner['description'] ?? null,
                'badge' => $banner['badge'] ?? null,
                'image' => 'banners/' . $banner['placement'] . '.svg',
                'image_alt' => $banner['title'],
                'link' => $banner['link'],
                'button_text' => $banner['button_text'] ?? null,
                'sort_order' => (int) ($banner['sort'] ?? 0),
                'status' => 1,
            ]);
        }
    }

    private function testimonials(): void
    {
        foreach (Seed::list('content', 'testimonials') as $testimonial) {
            Seed::upsert(
                $this,
                'testimonials',
                ['customer_name' => $testimonial['customer_name']],
                [
                    'customer_title' => $testimonial['customer_title'],
                    'customer_location' => $testimonial['customer_location'],
                    'quote' => $testimonial['quote'],
                    'rating' => (int) $testimonial['rating'],
                    'status' => 1,
                    'sort_order' => (int) ($testimonial['sort'] ?? 0),
                ]
            );
        }
    }

    private function menus(): void
    {
        foreach (Seed::list('content', 'menus') as $item) {
            $data = [
                'location' => $item['location'],
                'label' => $item['label'],
                'target_type' => $item['target_type'],
                'target_id' => null,
                'url' => null,
                'sort_order' => (int) ($item['sort'] ?? 0),
                'status' => 1,
            ];

            if ($item['target_type'] === 'url') {
                $data['url'] = (string) $item['url'];
            } else {
                $table = $item['target_type'] === 'category' ? 'categories' : 'pages';
                $data['target_id'] = Seed::id($this, $table, ['slug' => $item['target_slug']]);
            }

            Seed::upsert($this, 'menus', ['location' => $data['location'], 'label' => $data['label']], $data);
        }
    }
}
