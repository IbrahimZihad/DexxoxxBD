<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Settings;
use App\Models\ContactMessage;
use App\Models\Faq;
use App\Models\Plan;
use App\Models\Service;
use App\Models\Testimonial;
use App\Services\MailService;
use App\Services\PaymentService;

/**
 * The pages that are not the catalogue: contact, FAQ, the story, and the three
 * policy documents, plus the two machine-readable files (robots.txt,
 * sitemap.xml) and the health probe's neighbours.
 */
final class PageController extends Controller
{
    /** contact_messages.message is TEXT: 65,535 bytes, so 16,000 characters is
     *  still safe in the worst utf8mb4 case (4 bytes per character). */
    private const MESSAGE_LIMIT = 16000;

    public function contact(Request $request): Response
    {
        $user = auth_user();

        return $this->view('pages.contact', [
            // A signed-in customer should not have to type their details again.
            'prefillName' => (string) ($user['name'] ?? ''),
            'prefillEmail' => (string) ($user['email'] ?? ''),
            'prefillPhone' => (string) ($user['phone'] ?? ''),
            'whatsappUrl' => wa_link(),
            'hours' => setting('whatsapp.hours'),
            'deliveryMinutes' => max(1, Settings::int('trust.delivery_minutes', 10)),
            'holdMinutes' => max(5, Settings::int('order.pending_hold_minutes', 90)),
        ]);
    }

    public function sendContact(Request $request): Response
    {
        $data = $this->validate($request, [
            'name' => 'required|min:2|max:120',
            'email' => 'required|email|max:180',
            'phone' => 'required|phone_bd|max:20',
            'subject' => 'max:160',
            'message' => 'required|min:10|max:' . self::MESSAGE_LIMIT,
        ], [
            'name' => 'Name',
            'email' => 'Email',
            'phone' => 'Phone',
            'subject' => 'Subject',
            'message' => 'Message',
        ]);

        $subject = trim((string) ($data['subject'] ?? ''));
        $message = new ContactMessage();

        $message->create([
            'name' => (string) $data['name'],
            'email' => (string) $data['email'],
            'phone' => (string) $data['phone'],
            'subject' => $subject,
            'message' => (string) $data['message'],
            'channel' => 'form',
            'status' => 'new',
            'ip_address' => $request->ip(),
        ]);

        MailService::adminAlert(
            'New contact form message' . ($subject === '' ? '' : ': ' . $subject),
            $this->adminMailBody($data, $subject, $message->unreadCount())
        );

        $thanks = 'Thank you - your message is in the queue. A person reads every thread, so expect a reply '
            . 'during our opening hours: ' . setting('whatsapp.hours', '9:00 AM - 11:00 PM, every day') . '.';

        // The page posts through [data-ajax] when the browser can, and the JS
        // only understands JSON; a plain post still gets the flash + redirect.
        if ($request->wantsJson()) {
            return $this->json(['ok' => true, 'message' => $thanks, 'redirect' => route('contact')]);
        }

        return $this->redirect(route('contact'), 'success', $thanks);
    }

    public function faq(Request $request): Response
    {
        $search = mb_strtolower(trim((string) $request->queryValue('q', '')));
        $search = mb_substr($search, 0, 80);

        $rows = (new Faq())->published('', 200);
        $groups = [];

        foreach ($rows as $row) {
            $row = (array) $row;
            $question = (string) ($row['question'] ?? '');
            $answer = (string) ($row['answer'] ?? '');

            if ($search !== '' && !str_contains(mb_strtolower($question . ' ' . $answer), $search)) {
                continue;
            }

            $category = trim((string) ($row['category'] ?? ''));
            $category = $category === '' ? 'General' : $category;
            $groups[$category][] = ['question' => $question, 'answer' => $answer];
        }

        return $this->view('pages.faq', [
            'groups' => $groups,
            'total' => count($rows),
            'search' => $search,
            'whatsappUrl' => wa_link('Hi ' . setting('brand.name', 'ZapSub') . ', I have a question about ordering.'),
            'deliveryMinutes' => max(1, Settings::int('trust.delivery_minutes', 10)),
        ]);
    }

    public function about(Request $request): Response
    {
        $services = new Service();
        $plans = new Plan();

        return $this->view('pages.about', [
            'stats' => $this->aboutStats(count($services->catalogue()), $plans->countAll()),
            'testimonials' => (new Testimonial())->published(3),
            'note' => setting('footer.note'),
            'whatsappUrl' => wa_link('Hi ' . setting('brand.name', 'ZapSub') . ', I have a question about how you work.'),
        ]);
    }

    public function howItWorks(Request $request): Response
    {
        $rails = [];

        foreach (PaymentService::available() as $key => $method) {
            $method = (array) $method;
            $numberKey = (string) ($method['number_key'] ?? '');
            $rails[] = [
                'key' => (string) $key,
                'label' => (string) ($method['label'] ?? (string) $key),
                'kind' => (string) ($method['kind'] ?? 'manual'),
                'color' => $this->safeColor((string) ($method['color'] ?? '')),
                'number' => $numberKey === '' ? '' : PaymentService::numberFor($numberKey),
                'number_label' => (string) ($method['number_label'] ?? ''),
                'txn_label' => (string) ($method['txn_label'] ?? ''),
                'instructions' => (string) ($method['instructions'] ?? ''),
            ];
        }

        return $this->view('pages.how-it-works', [
            'rails' => $rails,
            'instruction' => setting('payment.instruction'),
            'whatsappUrl' => wa_link(),
        ]);
    }

    public function terms(Request $request): Response
    {
        return $this->view('pages.terms', []);
    }

    public function privacy(Request $request): Response
    {
        return $this->view('pages.privacy', []);
    }

    public function refund(Request $request): Response
    {
        return $this->view('pages.refund', []);
    }

    public function robots(Request $request): Response
    {
        $body = implode("\n", [
            'User-agent: *',
            'Allow: /',
            '',
            '# Crawl the catalogue, the policies and the FAQ. Sign-in and account',
            '# pages never hold public content, so there is nothing to hide and',
            '# nothing to fetch but a login form.',
            '',
            'Sitemap: ' . url('sitemap.xml'),
            '',
        ]);

        return (new Response($body, 200))->withHeader('Content-Type', 'text/plain; charset=utf-8');
    }

    public function sitemap(Request $request): Response
    {
        $today = date('Y-m-d');

        $urls = [
            ['loc' => route('home'), 'changefreq' => 'daily', 'priority' => '1.0'],
            ['loc' => route('plans'), 'changefreq' => 'daily', 'priority' => '0.9'],
            ['loc' => route('how'), 'changefreq' => 'monthly', 'priority' => '0.7'],
            ['loc' => route('faq'), 'changefreq' => 'weekly', 'priority' => '0.7'],
            ['loc' => route('track'), 'changefreq' => 'monthly', 'priority' => '0.5'],
            ['loc' => route('contact'), 'changefreq' => 'monthly', 'priority' => '0.6'],
            ['loc' => route('about'), 'changefreq' => 'yearly', 'priority' => '0.5'],
            ['loc' => route('terms'), 'changefreq' => 'yearly', 'priority' => '0.3'],
            ['loc' => route('privacy'), 'changefreq' => 'yearly', 'priority' => '0.3'],
            ['loc' => route('refund'), 'changefreq' => 'yearly', 'priority' => '0.3'],
        ];

        foreach ((new Service())->activeOrdered() as $service) {
            $service = (array) $service;
            $slug = trim((string) ($service['slug'] ?? ''));

            if ($slug === '' || preg_match('/\A[a-z0-9-]{1,80}\z/', $slug) !== 1) {
                continue;
            }

            $urls[] = [
                'loc' => route('service.show', ['slug' => $slug]),
                'changefreq' => 'daily',
                'priority' => '0.8',
            ];
        }

        $lines = ['<?xml version="1.0" encoding="UTF-8"?>'];
        $lines[] = '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';

        foreach ($urls as $url) {
            $lines[] = '  <url>';
            $lines[] = '    <loc>' . e((string) $url['loc']) . '</loc>';
            $lines[] = '    <lastmod>' . $today . '</lastmod>';
            $lines[] = '    <changefreq>' . e((string) $url['changefreq']) . '</changefreq>';
            $lines[] = '    <priority>' . e((string) $url['priority']) . '</priority>';
            $lines[] = '  </url>';
        }

        $lines[] = '</urlset>';

        // route()/url() already produce absolute addresses; nothing here is model
        // output printed raw - every value passes through e().
        return (new Response(implode("\n", $lines) . "\n", 200))
            ->withHeader('Content-Type', 'application/xml; charset=utf-8');
    }

    /**
     * Plain-text body for the inbox mail. The IP is only kept in the database
     * column, never forwarded by email.
     *
     * @param array<string, mixed> $data
     */
    private function adminMailBody(array $data, string $subject, int $unread): string
    {
        $brand = setting('brand.name', 'ZapSub');

        return "New message from the contact form.\n\n"
            . 'Name: ' . (string) $data['name'] . "\n"
            . 'Email: ' . (string) $data['email'] . "\n"
            . 'Phone: ' . (string) $data['phone'] . "\n"
            . 'Subject: ' . ($subject === '' ? '(none given)' : $subject) . "\n\n"
            . "Message\n-------\n" . wordwrap((string) $data['message'], 74, "\n", true) . "\n\n"
            . 'Open the inbox to answer it. Unread threads in the queue right now: ' . $unread . ".\n\n-- " . $brand;
    }

    /**
     * Counters for the about page, all sourced from settings and the live
     * catalogue so nothing on the page is invented.
     *
     * @return list<array<string, mixed>>
     */
    private function aboutStats(int $serviceCount, int $planCount): array
    {
        return [
            [
                'label' => 'Orders delivered',
                'to' => max(1, Settings::int('trust.orders_delivered', 0)),
                'suffix' => '+',
                'note' => 'paid by hand, verified by hand',
            ],
            [
                'label' => 'Minutes to deliver',
                'to' => max(1, Settings::int('trust.delivery_minutes', 10)),
                'suffix' => ' min',
                'note' => 'average, during opening hours',
            ],
            [
                'label' => 'Brands listed',
                'to' => $serviceCount,
                'suffix' => '',
                'note' => 'streaming, music, AI, design and storage',
            ],
            [
                'label' => 'Plans on sale',
                'to' => $planCount,
                'suffix' => '',
                'note' => 'in 1, 3, 6 and 12-month tiers',
            ],
        ];
    }

    /** An inline style must never carry anything but a hex colour. */
    private function safeColor(string $color): string
    {
        return preg_match('/\A#[0-9a-fA-F]{3,8}\z/', $color) === 1 ? $color : '#7c50f2';
    }
}
