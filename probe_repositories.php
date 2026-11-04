<?php

declare(strict_types=1);

$root = __DIR__;
require $root . '/vendor/autoload.php';

$app = App\Core\App::bootstrap($root);

$categories = new App\Repositories\CategoryRepository();
$brands = new App\Repositories\BrandRepository();
$content = new App\Repositories\ContentRepository();
$products = new App\Repositories\ProductRepository();
$variations = new App\Models\ProductVariation();

$tree = $categories->tree();
echo 'tree roots: ' . count($tree) . ' | first children: ' . count($tree[0]['children'] ?? []) . "\n";
echo 'topLevel: ' . count($categories->topLevel(12)) . ' | featured: ' . count($categories->featured(8)) . "\n";

$ai = $categories->bySlug('ai-productivity');
echo 'bySlug ai-productivity: ' . ($ai === null ? 'MISSING' : $ai['name'] . ' children=' . count($ai['children'])
    . ' products=' . $ai['product_count']) . "\n";
echo 'siblings: ' . count($categories->siblings($ai['parent_id'] ?? null, (int) $ai['id'])) . "\n";
echo 'sitemap slugs: ' . count($categories->slugsForSitemap()) . '/' . count($brands->slugsForSitemap()) . "\n";

echo 'brands all: ' . count($brands->allWithCounts()) . ' | popular: ';
$popular = $brands->popular(10);
echo count($popular) . ' top=' . $popular[0]['name'] . '(' . $popular[0]['product_count'] . ")\n";

echo 'banners hero: ' . count($content->banners('home_hero', 'en'))
    . ' promo: ' . count($content->banners('home_promo', 'en'))
    . ' announce: ' . count($content->banners('announcement', 'en')) . "\n";
echo 'testimonials: ' . count($content->testimonials('en')) . ' | faqs home: ' . count($content->faqs('en', 10, true))
    . ' | all: ' . count($content->faqs('en', 20)) . "\n";
$about = $content->page('about', 'en');
echo 'page about: ' . ($about === null ? 'MISSING' : $about['title']) . "\n";
echo 'footer pages: ' . count($content->footerPages()) . "\n";
$menu = $content->menu('header');
echo 'header menu: ' . count($menu) . ' first=' . $menu[0]['label'] . ' -> ' . $menu[0]['href'] . "\n";

$first = $products->findBySlug('nexora-assistant-pro');
echo 'product: ' . $first['name'] . ' cat=' . $first['category_name'] . ' brand=' . $first['brand_name'] . "\n";
echo 'images: ' . count($products->imagesFor((int) $first['id']))
    . ' features: ' . count($products->featuresFor((int) $first['id']))
    . ' faqs: ' . count($products->faqsFor((int) $first['id']))
    . ' reviews: ' . count($products->reviewsFor((int) $first['id'])) . "\n";
echo 'variations: ' . count($variations->listForProduct((int) $first['id']))
    . ' related: ' . count($products->related((int) $first['id'], (int) $first['category_id'], $first['brand_id'] === null ? null : (int) $first['brand_id']))
    . "\n";
echo 'breakdown: ' . json_encode($products->ratingBreakdown((int) $first['id'])) . "\n";
echo 'stats: ' . json_encode($products->marketplaceStats()) . "\n";
$page = $products->paginate(['category' => 'ai-productivity', 'sort' => 'price_asc', 'per_page' => 6]);
echo 'paginate ai: total=' . $page['total'] . ' rows=' . count($page['rows'])
    . ' first=' . $page['rows'][0]['name'] . ' @ ' . $page['rows'][0]['from_price'] . "\n";
echo 'suggestions: ' . count($products->suggestions('nex')) . ' | onSale: ' . count($products->onSale(8))
    . ' | new: ' . count($products->newArrivals(8)) . ' | best: ' . count($products->bestSellers(8))
    . ' | featured: ' . count($products->featured(8)) . "\n";
