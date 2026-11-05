<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Models\Order;
use App\Models\Plan;

/**
 * Two small JSON endpoints for the widgets in the page chrome.
 *
 * suggest: read-only catalogue autocomplete for the header search.
 * orderStatus: the "has my payment been verified yet?" poll. It answers with
 * statuses and nothing else - no transaction id, no phone, no delivery note -
 * and only for an order this very session has already been shown.
 */
final class ApiController extends Controller
{
    private const MAX_SUGGESTIONS = 8;

    public function suggest(Request $request): Response
    {
        $term = $this->term($request);

        if (mb_strlen($term) < 2) {
            return $this->json(['ok' => true, 'message' => '', 'count' => 0, 'results' => []]);
        }

        $rows = (new Plan())->browseAll(['search' => $term, 'sort' => 'popular'], self::MAX_SUGGESTIONS);
        $results = [];

        foreach ($rows as $row) {
            if (count($results) >= self::MAX_SUGGESTIONS) {
                break;
            }

            $results[] = [
                'label' => plan_label(
                    (string) $row['service_name'],
                    (string) $row['title']
                ),
                'url' => route('service.show', ['slug' => (string) $row['service_slug']]),
                'kind' => (string) ($row['category'] ?? 'plan'),
            ];
        }

        return $this->json([
            'ok' => true,
            'message' => '',
            'count' => count($results),
            'results' => $results,
        ]);
    }

    public function orderStatus(Request $request): Response
    {
        $orderNo = strtoupper(trim($request->parameter('orderNo')));
        $seen = array_values(array_filter(
            (array) Session::get('viewed_orders', []),
            static fn (mixed $value): bool => is_string($value)
        ));

        if ($orderNo === '' || !in_array($orderNo, $seen, true)) {
            return $this->json([
                'ok' => false,
                'message' => 'Open your order page first, then this updates on its own.',
            ], 403);
        }

        $order = (new Order())->findByOrderNo($orderNo);

        if ($order === null) {
            return $this->json([
                'ok' => false,
                'message' => 'Open your order page first, then this updates on its own.',
            ], 403);
        }

        $paymentStatus = (string) $order['payment_status'];

        return $this->json([
            'ok' => true,
            'status' => (string) $order['status'],
            'payment_status' => $paymentStatus,
            'fulfillment_status' => (string) $order['fulfillment_status'],
            'status_label' => status_label((string) $order['status']),
            'paid' => $paymentStatus === 'paid',
        ]);
    }

    /** The header search box has used a couple of names over time; all work. */
    private function term(Request $request): string
    {
        foreach (['q', 'search', 'term'] as $key) {
            $value = $request->queryValue($key, '');

            if (is_scalar($value) && trim((string) $value) !== '') {
                return mb_substr(trim((string) $value), 0, 60);
            }
        }

        return '';
    }
}
