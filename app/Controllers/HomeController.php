<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Settings;
use App\Models\Faq;
use App\Models\Order;
use App\Models\Plan;
use App\Models\Service;
use App\Models\Testimonial;

/**
 * Homepage: the animated shelf window. Everything here is read through the
 * models so the page follows whatever the owner has actually published.
 */
final class HomeController extends Controller
{
    public function index(Request $request): Response
    {
        $services = new Service();
        $plans = new Plan();

        $catalogue = $services->catalogue();
        $featured = $plans->featured(8);

        return $this->view('pages.home', [
            'services' => array_slice($catalogue, 0, 8),
            'featured' => $featured,
            'testimonials' => (new Testimonial())->published(9),
            'faqs' => (new Faq())->published('', 6),
            'bestSellers' => (new Order())->bestSellers(4),
            'priceRange' => $plans->priceRange(),
            'stats' => $this->stats($catalogue, $plans->browse([], 1, 1)['total']),
            'rating' => Settings::get('trust.rating', '4.8'),
            'jsonLd' => $this->jsonLd($featured),
        ]);
    }

    /**
     * Counters are integers because app.js renders decimals with two places, and
     * "12,400+" reads better on a hero strip than "12,400.00".
     *
     * @param list<array<string, mixed>> $catalogue
     * @return list<array<string, mixed>>
     */
    private function stats(array $catalogue, int $planTotal): array
    {
        return [
            [
                'label' => 'Orders delivered',
                'to' => Settings::int('trust.orders_delivered', 0),
                'suffix' => '+',
                'note' => 'across all 64 districts',
            ],
            [
                'label' => 'Average delivery',
                'to' => max(1, Settings::int('trust.delivery_minutes', 10)),
                'suffix' => ' min',
                'note' => '9 AM to 11 PM, every day',
            ],
            [
                'label' => 'Brands on the shelf',
                'to' => count($catalogue),
                'suffix' => '',
                'note' => 'streaming, music, AI and design',
            ],
            [
                'label' => 'Plans ready to buy',
                'to' => $planTotal,
                'suffix' => '',
                'note' => 'in 1, 3, 6 and 12-month tiers',
            ],
        ];
    }

    /**
     * Organization + an ItemList of the plans the homepage actually shows.
     * JSON_HEX_TAG keeps a "</script>" inside a brand description harmless, which
     * is what lets the layout print this raw.
     *
     * @param list<array<string, mixed>> $featured
     */
    private function jsonLd(array $featured): string
    {
        $brand = Settings::get('brand.name', 'ZapSub');

        $items = [];
        $position = 0;

        foreach ($featured as $plan) {
            $position++;
            $serviceSlug = (string) ($plan['service_slug'] ?? '');

            $items[] = [
                '@type' => 'ListItem',
                'position' => $position,
                'item' => [
                    '@type' => 'Product',
                    'name' => trim((string) $plan['service_name'] . ' ' . (string) $plan['title']),
                    'brand' => ['@type' => 'Brand', 'name' => (string) $plan['service_name']],
                    'description' => (string) ($plan['service_name'] ?? '') . ' - ' . (string) $plan['title']
                        . ' (' . (int) $plan['duration_months'] . ' months)',
                    'offers' => [
                        '@type' => 'Offer',
                        'url' => route($serviceSlug === '' ? 'plans' : 'service.show', ['slug' => $serviceSlug]),
                        'price' => number_format((float) $plan['price'], 2, '.', ''),
                        'priceCurrency' => (string) app()->config('app.currency', 'BDT'),
                        'availability' => (int) ($plan['stock'] ?? -1) === 0
                            ? 'https://schema.org/SoldOut'
                            : 'https://schema.org/InStock',
                    ],
                ],
            ];
        }

        $graph = [
            [
                '@type' => 'Organization',
                '@id' => route('home') . '#organization',
                'name' => $brand,
                'url' => route('home'),
                'description' => Settings::get('meta.description'),
                'slogan' => Settings::get('brand.tagline'),
                'email' => Settings::get('contact.email'),
                'telephone' => Settings::get('contact.phone'),
                'address' => [
                    '@type' => 'PostalAddress',
                    'addressLocality' => 'Dhaka',
                    'addressCountry' => 'BD',
                    'streetAddress' => Settings::get('contact.address'),
                ],
                'areaServed' => 'BD',
                'paymentAccepted' => 'bKash, Nagad, Rocket, Bank transfer, Card',
            ],
            [
                '@type' => 'ItemList',
                'name' => $brand . ' popular subscription plans',
                'itemListElement' => $items,
            ],
        ];

        $encoded = json_encode(
            ['@context' => 'https://schema.org', '@graph' => $graph],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP
        );

        return $encoded === false ? '{}' : $encoded;
    }
}
