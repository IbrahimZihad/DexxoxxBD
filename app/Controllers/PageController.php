<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Exceptions\HttpException;
use App\Repositories\ContentRepository;

/**
 * CMS-authored pages, the FAQ page and the delivery explainer.
 *
 * A page body is staff HTML and only reaches the browser through the whitelist
 * sanitizer, so a redactor cannot plant a script in a terms page.
 */
final class PageController extends Controller
{
    private ContentRepository $content;

    public function __construct()
    {
        $this->content = new ContentRepository();
    }

    public function show(Request $request, string $slug): Response
    {
        unset($request);

        $page = $this->content->page($slug, current_locale());
        if ($page === null) {
            throw new HttpException(404, t('error.404.title'));
        }

        return $this->view('pages.show', [
            'page' => $page,
            'breadcrumbs' => [
                ['label' => t('nav.home'), 'url' => url('')],
                ['label' => (string) $page['title']],
            ],
        ]);
    }

    public function faqs(Request $request): Response
    {
        $query = mb_substr(trim((string) $request->queryValue('q', '')), 0, 120);

        $groups = [];
        foreach ($this->content->faqs(current_locale(), 100) as $faq) {
            if ($query !== '' && !$this->matches($faq, $query)) {
                continue;
            }
            $groups[trim((string) ($faq['category'] ?? ''))][] = $faq;
        }

        ksort($groups);

        return $this->view('pages.faqs', [
            'groups' => $groups,
            'query' => $query,
        ]);
    }

    /**
     * Delivery explainer: the four steps from the translation file, the method
     * summary from settings, and links to the legal pages that are published.
     */
    public function howItWorks(): Response
    {
        $steps = [];
        foreach ([1, 2, 3, 4] as $index) {
            $steps[] = [
                'title' => t('home.how_it_works.step_' . $index . '_title'),
                'text' => t('home.how_it_works.step_' . $index . '_text'),
            ];
        }

        $legal = [];
        foreach ($this->published(['delivery-policy', 'refund-policy', 'terms', 'privacy', 'about']) as $page) {
            $legal[] = ['label' => (string) $page['title'], 'url' => url('page/' . (string) $page['slug'])];
        }

        return $this->view('pages.how-it-works', [
            'steps' => $steps,
            'legal' => $legal,
            'summary' => (string) setting('delivery.method_summary', t('home.how_it_works.subtitle')),
        ]);
    }

    /**
     * @param list<string> $slugs
     * @return list<array<string, mixed>>
     */
    private function published(array $slugs): array
    {
        $locale = current_locale();
        $rows = [];
        foreach ($slugs as $slug) {
            // page() already falls back to English rows inside the query, so an
            // untranslated policy never disappears from the storefront.
            $page = $this->content->page($slug, $locale);
            if ($page !== null) {
                $rows[] = $page;
            }
        }

        return $rows;
    }

    /**
     * @param array<string, mixed> $faq
     */
    private function matches(array $faq, string $query): bool
    {
        $haystack = mb_strtolower((string) $faq['question'] . ' ' . (string) $faq['answer']);
        return str_contains($haystack, mb_strtolower($query));
    }
}
