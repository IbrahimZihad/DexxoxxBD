<?php

declare(strict_types=1);

use App\Helpers\Seed;
use Phinx\Seed\AbstractSeed;

/**
 * Site-wide FAQs, grouped so the FAQ page can render sections and the homepage
 * can pick the ones marked for it. Per-product questions live in product_faqs
 * and are written by ProductSeeder.
 */
final class FAQSeeder extends AbstractSeed
{
    public function run(): void
    {
        foreach (Seed::list('content', 'faqs') as $faq) {
            Seed::upsert($this, 'faqs', ['question' => $faq['question']], [
                'answer' => $faq['answer'],
                'category' => $faq['category'],
                'sort_order' => (int) $faq['sort'],
                'show_on_home' => (bool) $faq['home'],
                'status' => 1,
            ]);
        }
    }
}
