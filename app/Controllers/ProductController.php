<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Exceptions\HttpException;
use App\Models\ProductVariation;
use App\Repositories\ProductRepository;

/**
 * Product detail page.
 *
 * Prices and stock shown here come from the database at render time and are
 * re-read again when the cart is written - the browser never decides what a plan
 * costs.
 */
final class ProductController extends Controller
{
    private ProductRepository $products;

    private ProductVariation $variations;

    public function __construct()
    {
        $this->products = new ProductRepository();
        $this->variations = new ProductVariation();
    }

    public function show(Request $request, string $slug): Response
    {
        $product = $this->products->findBySlug($slug);
        if ($product === null) {
            throw new HttpException(404, t('error.404.title'));
        }

        $id = (int) $product['id'];
        $this->countView($id);

        $plans = [];
        foreach ($this->variations->listForProduct($id) as $plan) {
            // available_stock already folds in license-key inventory, so the buy
            // box reports what can genuinely be delivered today.
            $plan['stock'] = (int) $plan['available_stock'];
            $plans[] = $plan;
        }

        return $this->view('products.show', [
            'product' => $product,
            'plans' => $plans,
            'images' => $this->products->imagesFor($id),
            'features' => $this->products->featuresFor($id),
            'faqs' => $this->products->faqsFor($id),
            'reviews' => $this->products->reviewsFor($id),
            'breakdown' => $this->products->ratingBreakdown($id),
            'related' => $this->products->related(
                $id,
                (int) $product['category_id'],
                $product['brand_id'] === null ? null : (int) $product['brand_id'],
                4
            ),
            'breadcrumbs' => $this->breadcrumbs($product),
        ]);
    }

    /**
     * Payload for the quick-view dialog. Data only: the client writes it with
     * textContent, so catalogue text can never be parsed as markup.
     *
     * @return array<string, mixed>
     */
    public function quickView(string $slug): array
    {
        $product = $this->products->findBySlug($slug);
        if ($product === null) {
            throw new HttpException(404, t('error.404.title'));
        }

        $id = (int) $product['id'];
        $plans = [];
        foreach ($this->variations->listForProduct($id) as $plan) {
            $plans[] = [
                'id' => (int) $plan['id'],
                'label' => (string) $plan['label'],
                'price' => round((float) $plan['price'], 2),
                'compare_price' => $plan['compare_price'] === null ? null : round((float) $plan['compare_price'], 2),
                'duration_label' => (string) ($plan['duration_label'] ?? ''),
                'stock' => (int) $plan['available_stock'],
                'min_quantity' => (int) ($plan['min_quantity'] ?? 1),
                'max_quantity' => (int) ($plan['max_quantity'] ?? 10),
                'price_display' => price((float) $plan['price']),
            ];
        }

        return [
            'ok' => true,
            'data' => [
                'product_id' => $id,
                'name' => (string) $product['name'],
                'slug' => (string) $product['slug'],
                'url' => url('products/' . (string) $product['slug']),
                'image' => image_url($product['image'] ?? null),
                'image_alt' => (string) $product['name'],
                'brand' => (string) ($product['brand_name'] ?? ''),
                'category' => (string) ($product['category_name'] ?? ''),
                'summary' => mb_substr((string) $product['short_description'], 0, 220),
                'rating' => round((float) $product['rating_avg'], 1),
                'rating_count' => (int) $product['rating_count'],
                'from_price' => price((float) ($plans[0]['price'] ?? 0)),
                'delivery' => t('product.delivery.' . (string) ($product['delivery_method'] ?? 'manual')),
                'plans' => $plans,
            ],
        ];
    }

    /**
     * One view per session per product, so a refresh loop cannot inflate the
     * popularity signal that sort=popular depends on.
     */
    private function countView(int $productId): void
    {
        $seen = (array) Session::get('viewed_products', []);
        if (isset($seen[$productId])) {
            return;
        }

        $seen[$productId] = true;
        if (count($seen) > 200) {
            $seen = array_slice($seen, -150, null, true);
        }
        Session::set('viewed_products', $seen);

        $this->products->incrementViews($productId);
    }

    /**
     * @param array<string, mixed> $product
     * @return list<array<string, string>>
     */
    private function breadcrumbs(array $product): array
    {
        $trail = [
            ['label' => t('nav.home'), 'url' => url('')],
            ['label' => t('nav.products'), 'url' => url('products')],
        ];

        $shelf = (string) ($product['category_slug'] ?? '');
        if ($shelf !== '') {
            if ((int) ($product['category_parent_id'] ?? 0) > 0) {
                $trail[] = ['label' => t('nav.categories'), 'url' => url('categories')];
            }
            $trail[] = ['label' => (string) $product['category_name'], 'url' => url('categories/' . $shelf)];
        }

        $trail[] = ['label' => (string) $product['name']];

        return $trail;
    }
}
