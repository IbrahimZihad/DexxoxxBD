<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Settings;
use App\Exceptions\HttpException;
use App\Models\Faq;
use App\Models\Plan;
use App\Models\Service;

/**
 * The catalogue: /plans (filtered, sorted, paginated) and /plans/{slug}.
 * Every filter key below is one Plan::browse() already understands.
 */
final class CatalogController extends Controller
{
    /** Mirrors the whitelist inside Plan::applySort(). */
    private const SORTS = ['popular', 'price_low', 'price_high', 'newest', 'duration', 'longest_value'];

    private const PER_PAGE = 12;

    public function index(Request $request): Response
    {
        $filters = [
            'search' => $this->scalar($request, 'search'),
            'category' => $this->scalar($request, 'category'),
            'service' => $this->scalar($request, 'service'),
            'duration' => $this->scalar($request, 'duration'),
            'seats' => $this->scalar($request, 'seats'),
            'max_price' => $this->scalar($request, 'max_price'),
            'sort' => $this->sort($request),
        ];

        $page = max(1, $request->integer('page', 1));
        $result = (new Plan())->browse($filters, $page, self::PER_PAGE);
        $services = new Service();
        $plans = new Plan();

        return $this->view('pages.plans', [
            'plans' => $result['items'],
            'total' => $result['total'],
            'pages' => $result['pages'],
            'page' => $result['page'],
            'perPage' => $result['per_page'],
            'filters' => $filters,
            'categories' => $services->categories(),
            'durations' => $plans->durations(),
            'priceRange' => $plans->priceRange(),
            'serviceSlugs' => array_values(array_map(
                static fn (array $row): string => (string) $row['slug'],
                $services->catalogue()
            )),
        ]);
    }

    public function show(Request $request): Response
    {
        $slug = $request->parameter('slug');
        $services = new Service();
        $service = $services->bySlug($slug);

        if ($service === null) {
            throw new HttpException(404, 'We do not sell that service right now - check the full plan list.');
        }

        $plans = new Plan();
        $tiers = $plans->tiersForService((int) $service['id']);

        return $this->view('pages.service', [
            'service' => $service,
            // browseAll() returns plans joined with the service columns the card
            // component needs; tiersForService() only returns the plan row.
            'plans' => $plans->browseAll(['service' => $slug, 'sort' => 'duration'], 24),
            'comparison' => $this->comparison($tiers),
            'related' => array_slice(array_values(array_filter(
                $services->catalogue(),
                static fn (array $row): bool => (string) $row['slug'] !== $slug
            )), 0, 4),
            'faqs' => (new Faq())->published('', 5),
            'priceRange' => [
                'min' => $this->lowest($tiers),
                'max' => $this->highest($tiers),
            ],
            'jsonLd' => $this->jsonLd($service, $tiers),
        ]);
    }

    private function scalar(Request $request, string $key): string
    {
        $value = $request->input($key, '');

        return is_scalar($value) ? mb_substr(trim((string) $value), 0, 120) : '';
    }

    private function sort(Request $request): string
    {
        $sort = $this->scalar($request, 'sort');

        return in_array($sort, self::SORTS, true) ? $sort : 'popular';
    }

    /**
     * Plans are seeded as "<variant> - <duration>", so the variant label is what
     * the comparison table tabs between.
     *
     * @param list<array<string, mixed>> $tiers
     * @return list<array<string, mixed>>
     */
    private function comparison(array $tiers): array
    {
        $groups = [];

        foreach ($tiers as $tier) {
            $title = (string) $tier['title'];
            $position = mb_strrpos($title, ' - ');
            $variant = trim($position === false ? 'Standard' : (string) mb_substr($title, 0, $position));
            $months = max(1, (int) $tier['duration_months']);
            $price = (float) $tier['price'];
            $compare = (float) ($tier['compare_at_price'] ?? 0);

            $groups[$variant]['rows'][$months] = [
                'plan_id' => (int) $tier['id'],
                'title' => $title,
                'months' => $months,
                'price' => $price,
                'per_month' => $price / $months,
                'saving' => $compare > $price ? $compare - $price : 0.0,
                'seats' => (int) ($tier['seats'] ?? 1),
                'stock' => (int) ($tier['stock'] ?? -1),
                'badge' => (string) ($tier['badge'] ?? ''),
            ];
        }

        $tables = [];

        foreach ($groups as $variant => $group) {
            $rows = $group['rows'];
            ksort($rows);
            $tables[] = ['variant' => (string) $variant, 'rows' => array_values($rows)];
        }

        return $tables;
    }

    private function lowest(array $tiers): float
    {
        $prices = array_map(static fn (array $tier): float => (float) $tier['price'], $tiers);

        return $prices === [] ? 0.0 : min($prices);
    }

    private function highest(array $tiers): float
    {
        $prices = array_map(static fn (array $tier): float => (float) $tier['price'], $tiers);

        return $prices === [] ? 0.0 : max($prices);
    }

    /**
     * @param array<string, mixed> $service
     * @param list<array<string, mixed>> $tiers
     */
    private function jsonLd(array $service, array $tiers): string
    {
        $offers = [];

        foreach ($tiers as $tier) {
            $offers[] = [
                '@type' => 'Offer',
                'price' => number_format((float) $tier['price'], 2, '.', ''),
                'priceCurrency' => (string) app()->config('app.currency', 'BDT'),
                'availability' => (int) ($tier['stock'] ?? -1) === 0
                    ? 'https://schema.org/SoldOut'
                    : 'https://schema.org/InStock',
                'url' => route('service.show', ['slug' => (string) $service['slug']]),
            ];
        }

        $brand = Settings::get('brand.name', 'ZapSub');

        $graph = [
            [
                '@type' => 'Product',
                'name' => trim($brand . ' ' . (string) $service['name']),
                'category' => (string) ($service['category'] ?? ''),
                'description' => (string) ($service['description'] ?? $service['tagline'] ?? ''),
                'brand' => ['@type' => 'Brand', 'name' => (string) $service['name']],
                'offers' => $offers,
            ],
            [
                '@type' => 'BreadcrumbList',
                'itemListElement' => [
                    ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => route('home')],
                    ['@type' => 'ListItem', 'position' => 2, 'name' => 'Plans', 'item' => route('plans')],
                    [
                        '@type' => 'ListItem',
                        'position' => 3,
                        'name' => (string) $service['name'],
                        'item' => route('service.show', ['slug' => (string) $service['slug']]),
                    ],
                ],
            ],
        ];

        $encoded = json_encode(
            ['@context' => 'https://schema.org', '@graph' => $graph],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP
        );

        return $encoded === false ? '{}' : $encoded;
    }
}
