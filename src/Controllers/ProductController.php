<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Auth\Auth;
use App\Exceptions\HttpException;
use App\Http\Controller;
use App\Http\Response;
use App\Repositories\CategoryRepository;
use App\Repositories\ProductRepository;
use App\Security\Sanitizer;
use App\Security\Validator;

final class ProductController extends Controller
{
    public function index(): Response
    {
        $id = $this->request->int('id');
        $slug = (string) ($this->request->query('slug') ?? '');

        if ($id > 0 || $slug !== '') {
            return $this->show($id, $slug);
        }

        $page = ProductRepository::paginate([
            'category' => (string) ($this->request->query('category') ?? ''),
            'q' => (string) ($this->request->query('q') ?? ''),
            'featured' => $this->request->query('featured'),
            'on_sale' => $this->request->query('on_sale'),
            'in_stock' => $this->request->query('in_stock'),
            'sort' => (string) ($this->request->query('sort') ?? 'newest'),
            'page' => $this->page(),
            'per_page' => $this->perPage(12, 60),
        ], Auth::isAdmin());

        return $this->json([
            'products' => array_map($this->publicShape(...), $page['data']),
            'meta' => [
                'total' => $page['total'],
                'page' => $page['page'],
                'per_page' => $page['per_page'],
                'last_page' => max(1, (int) ceil($page['total'] / $page['per_page'])),
            ],
        ]);
    }

    public function show(): Response
    {
        $id = $this->request->int('id');
        $slug = (string) ($this->request->query('slug') ?? '');
        $activeOnly = !Auth::isAdmin();

        $product = $id > 0
            ? ProductRepository::find($id, $activeOnly)
            : ProductRepository::findBySlug($slug, $activeOnly);

        if ($product === null) {
            throw new HttpException(404, 'Product not found.');
        }

        return $this->json(['product' => $this->publicShape($product)]);
    }

    public function store(): Response
    {
        $data = $this->catalogueInput(new: true);
        $data['slug'] = ProductRepository::uniqueSlug($data['name']);

        return $this->json(['id' => ProductRepository::create($data), 'slug' => $data['slug']], 201);
    }

    public function update(): Response
    {
        $id = $this->idFromQuery();
        $existing = ProductRepository::find($id);

        if ($existing === null) {
            throw new HttpException(404, 'Product not found.');
        }

        $changed = $this->catalogueInput(new: false);
        $changed = array_filter(
            $changed,
            static fn ($value, $column) => (string) $value !== (string) ($existing[$column] ?? ''),
            ARRAY_FILTER_USE_BOTH
        );

        if (isset($changed['name'])) {
            $changed['slug'] = ProductRepository::uniqueSlug((string) $changed['name'], $id);
        }

        ProductRepository::update($id, $changed);

        return $this->json(['ok' => true, 'slug' => $changed['slug'] ?? $existing['slug']]);
    }

    public function destroy(): Response
    {
        $id = $this->idFromQuery();

        if (ProductRepository::find($id) === null) {
            throw new HttpException(404, 'Product not found.');
        }

        ProductRepository::delete($id);

        return $this->json(['ok' => true]);
    }

    /**
     * @return array<string,mixed> column => value, containing only fields the client sent
     */
    private function catalogueInput(bool $new): array
    {
        $rules = [
            'name' => ($new ? 'required' : 'nullable') . '|string|min:2|max:160',
            'description' => 'nullable|string|max:5000',
            'image_url' => 'nullable|url',
            'price' => ($new ? 'required' : 'nullable') . '|numeric|min:1|max:10000000',
            'compare_at_price' => 'nullable|numeric|min:1|max:10000000',
            'duration_days' => 'nullable|integer|min:1|max:3650',
            'stock' => 'nullable|integer|min:0|max:1000000',
            'status' => 'nullable|in:active,inactive',
            'is_featured' => 'nullable|boolean',
            'category_id' => 'nullable|integer|min:1',
        ];

        $body = $this->request->body();

        // A PUT may send one field; validate exactly the fields that arrived.
        $scoped = [];

        foreach ($rules as $field => $rule) {
            if ($new || $this->has($field)) {
                $scoped[$field] = $rule;
            }
        }

        $input = array_intersect_key($body, $scoped);
        $validated = Validator::make($input, $scoped, [
            'compare_at_price' => 'compare-at price',
            'duration_days' => 'duration',
            'image_url' => 'image URL',
            'category_id' => 'category',
        ])->validated();

        $clean = [];

        if (array_key_exists('name', $validated)) {
            $clean['name'] = Sanitizer::text($validated['name'], 160);
        }

        if (array_key_exists('description', $validated)) {
            $clean['description'] = Sanitizer::multiline($validated['description'], 5000);
        }

        if (array_key_exists('image_url', $validated)) {
            $clean['image_url'] = Sanitizer::url($validated['image_url']);
        }

        if (array_key_exists('price', $validated)) {
            $clean['price'] = round((float) $validated['price'], 2);
        }

        if (array_key_exists('compare_at_price', $validated)) {
            $compare = $validated['compare_at_price'] === null ? null : round((float) $validated['compare_at_price'], 2);
            $price = $clean['price'] ?? null;

            if ($compare !== null && $price !== null && $compare < $price) {
                throw new HttpException(422, 'Please check the highlighted fields.', [
                    'compare_at_price' => 'Compare-at price must be at least the selling price.',
                ]);
            }

            $clean['compare_at_price'] = $compare;
        }

        foreach (['duration_days', 'stock'] as $intField) {
            if (array_key_exists($intField, $validated) && $validated[$intField] !== null) {
                $clean[$intField] = (int) $validated[$intField];
            }
        }

        if (array_key_exists('status', $validated) && $validated['status'] !== null) {
            $clean['status'] = (string) $validated['status'];
        }

        if (array_key_exists('is_featured', $validated)) {
            $clean['is_featured'] = !empty($validated['is_featured']) ? 1 : 0;
        }

        if (array_key_exists('category_id', $validated)) {
            if ($validated['category_id'] === null) {
                $clean['category_id'] = null;
            } elseif (!CategoryRepository::exists((int) $validated['category_id'])) {
                throw new HttpException(422, 'Please check the highlighted fields.', ['category_id' => 'Unknown category.']);
            } else {
                $clean['category_id'] = (int) $validated['category_id'];
            }
        }

        if ($new) {
            $clean += ['description' => '', 'duration_days' => 30, 'stock' => 0, 'status' => 'active', 'is_featured' => 0];
        }

        return $clean;
    }

    /** @param array<string,mixed> $product @return array<string,mixed> */
    private function publicShape(array $product): array
    {
        $price = round((float) $product['price'], 2);
        $compare = $product['compare_at_price'] === null ? null : round((float) $product['compare_at_price'], 2);

        return [
            'id' => (int) $product['id'],
            'name' => (string) $product['name'],
            'slug' => (string) $product['slug'],
            'description' => (string) ($product['description'] ?? ''),
            'image_url' => $product['image_url'],
            'price' => $price,
            'compare_at_price' => $compare,
            'discount_percent' => $compare !== null && $compare > $price ? (int) round((1 - $price / $compare) * 100) : null,
            'duration_days' => (int) $product['duration_days'],
            'stock' => (int) $product['stock'],
            'in_stock' => (int) $product['stock'] > 0,
            'status' => (string) $product['status'],
            'is_featured' => (int) $product['is_featured'] === 1,
            'sold_count' => (int) ($product['sold_count'] ?? 0),
            'category' => $product['category_name'] ?? null,
            'category_slug' => $product['category_slug'] ?? null,
            'created_at' => $product['created_at'] ?? null,
        ];
    }
}
