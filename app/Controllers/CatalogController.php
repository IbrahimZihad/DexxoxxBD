<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Exceptions\HttpException;
use App\Helpers\Filters;
use App\Repositories\BrandRepository;
use App\Repositories\CategoryRepository;
use App\Repositories\ProductRepository;

/**
 * Every catalog listing: the full shelf, a search, a category, a brand and the
 * deals view. They share one template because the toolbar, filter panel and grid
 * are identical - only the heading and the preset scope differ.
 */
final class CatalogController extends Controller
{
    private ProductRepository $products;

    private CategoryRepository $categories;

    private BrandRepository $brands;

    public function __construct()
    {
        $this->products = new ProductRepository();
        $this->categories = new CategoryRepository();
        $this->brands = new BrandRepository();
    }

    public function index(Request $request): Response
    {
        $filters = Filters::fromRequest($request);
        $term = (string) $filters['search'];

        if ($term !== '') {
            $heading = t('catalog.search_title', ['term' => $term]);
            $subheading = t('catalog.search_subtitle', ['count' => 0]);
            $breadcrumbs = [
                ['label' => t('nav.home'), 'url' => url('')],
                ['label' => t('common.search')],
            ];
        } else {
            $heading = t('catalog.title');
            $subheading = t('catalog.subtitle');
            $breadcrumbs = [
                ['label' => t('nav.home'), 'url' => url('')],
                ['label' => t('nav.products')],
            ];
        }

        $result = $this->products->paginate($filters);

        if ($term !== '') {
            $subheading = t('catalog.search_subtitle', ['count' => (int) $result['total']]);
        }

        return $this->listing($request, $filters, $result, $heading, $subheading, $breadcrumbs, url('products'));
    }

    public function deals(Request $request): Response
    {
        $filters = Filters::with(Filters::fromRequest($request), ['on_sale' => true]);
        $result = $this->products->paginate($filters);

        return $this->listing(
            $request,
            $filters,
            $result,
            t('catalog.deals_title'),
            t('catalog.deals_subtitle'),
            [['label' => t('nav.home'), 'url' => url('')], ['label' => t('nav.deals')]],
            url('deals')
        );
    }

    public function category(Request $request, string $slug): Response
    {
        $shelf = $this->categories->bySlug($slug);
        if ($shelf === null) {
            throw new HttpException(404, t('error.404.title'));
        }

        $filters = Filters::with(Filters::fromRequest($request), ['category' => $slug]);
        $result = $this->products->paginate($filters);

        $breadcrumbs = [['label' => t('nav.home'), 'url' => url('')]];
        if (is_array($shelf['parent'] ?? null)) {
            $breadcrumbs[] = ['label' => (string) $shelf['parent']['name'],
                'url' => url('categories/' . (string) $shelf['parent']['slug'])];
        } else {
            $breadcrumbs[] = ['label' => t('nav.categories'), 'url' => url('categories')];
        }
        $breadcrumbs[] = ['label' => (string) $shelf['name']];

        return $this->listing(
            $request,
            $filters,
            $result,
            t('catalog.category_title', ['name' => (string) $shelf['name']]),
            t('catalog.category_subtitle', ['count' => (int) $result['total']]),
            $breadcrumbs,
            url('categories/' . $slug),
            [
                'shelf' => $shelf,
                'children' => $shelf['children'] ?? [],
                'seo_title' => (string) ($shelf['meta_title'] ?: $shelf['name']) . ' | '
                    . (string) setting('brand.name', config('app.name')),
                'seo_description' => (string) ($shelf['meta_description'] ?: trim((string) $shelf['description'])),
            ]
        );
    }

    public function brand(Request $request, string $slug): Response
    {
        $brand = $this->brands->bySlug($slug);
        if ($brand === null) {
            throw new HttpException(404, t('error.404.title'));
        }

        $filters = Filters::with(Filters::fromRequest($request), ['brand' => [$slug]]);
        $result = $this->products->paginate($filters);

        return $this->listing(
            $request,
            $filters,
            $result,
            t('catalog.brand_title', ['name' => (string) $brand['name']]),
            t('catalog.brand_subtitle', ['name' => (string) $brand['name']]),
            [['label' => t('nav.home'), 'url' => url('')], ['label' => t('nav.brands'), 'url' => url('brands')],
                ['label' => (string) $brand['name']]],
            url('brands/' . $slug),
            [
                'seo_title' => (string) ($brand['name']) . ' plans | '
                    . (string) setting('brand.name', config('app.name')),
                'seo_description' => mb_substr((string) ($brand['description'] ?? ''), 0, 155),
            ]
        );
    }

    /**
     * @param array<string, mixed>        $filters
     * @param array<string, mixed>        $result
     * @param list<array<string, string>> $breadcrumbs
     * @param array<string, mixed>        $extra
     * @return Response
     */
    private function listing(
        Request $request,
        array $filters,
        array $result,
        string $heading,
        string $subheading,
        array $breadcrumbs,
        string $canonical,
        array $extra = []
    ): Response {
        return $this->view('catalog.index', array_merge([
            'result' => $result,
            'filters' => $filters,
            'categories' => $this->categories->allWithCounts(),
            'brands' => $this->brands->allWithCounts(80),
            'heading' => $heading,
            'subheading' => $subheading,
            'breadcrumbs' => $breadcrumbs,
            'shelf' => null,
            'children' => [],
            'canonical' => (int) $filters['page'] > 1 ? $request->fullUrlWithoutQuery() : $canonical,
        ], $extra));
    }
}
