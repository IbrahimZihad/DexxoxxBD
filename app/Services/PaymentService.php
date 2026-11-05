<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Settings;

/**
 * Reads the payment rails defined in config/payments.php and resolves whatever
 * account numbers the shop has configured, so the checkout page and the WhatsApp
 * message always agree with what an admin set in the panel.
 */
final class PaymentService
{
    /** @return array<string, array<string, mixed>> */
    public static function available(): array
    {
        $methods = (array) Config::get('payments.methods', []);

        return array_filter(
            $methods,
            static fn (array $method): bool => (bool) ($method['available'] ?? false)
                && self::numberFor((string) ($method['number_key'] ?? '')) !== ''
                || (bool) ($method['available'] ?? false) && (string) ($method['kind'] ?? '') !== 'wallet' && (string) ($method['kind'] ?? '') !== 'bank'
        );
    }

    /** @return array<string, mixed>|null */
    public static function method(string $key): ?array
    {
        $methods = self::available();

        return $methods[$key] ?? null;
    }

    public static function keyIsValid(string $key): bool
    {
        return self::method($key) !== null;
    }

    public static function requiresTxn(string $key): bool
    {
        return in_array($key, (array) Config::get('payments.require_txn_for', []), true);
    }

    /**
     * Format-only reference check. It rejects empty or junk values; the actual
     * money is confirmed by a person reading their own wallet statement, which is
     * why no order can be paid from here.
     */
    public static function txnLooksReal(string $key, string $txn): bool
    {
        $method = self::method($key);

        if ($method === null) {
            return false;
        }

        $pattern = $method['txn_pattern'] ?? null;

        if (!is_string($pattern) || $pattern === '') {
            return true;
        }

        return @preg_match($pattern, trim($txn)) === 1;
    }

    public static function numberFor(string $settingKey): string
    {
        if ($settingKey === '') {
            return '';
        }

        return trim(Settings::get($settingKey, ''));
    }

    public static function label(string $key): string
    {
        return (string) (self::method($key)['label'] ?? $key);
    }

    /**
     * The line a customer can paste into WhatsApp if they would rather not use
     * the form at all.
     *
     * @param array<string, mixed> $order
     * @param list<array<string, mixed>> $items
     */
    public static function whatsappSummary(array $order, array $items): string
    {
        $lines = [];

        foreach ($items as $item) {
            $lines[] = sprintf(
                '%s %s x%d',
                (string) $item['service_name'],
                (string) $item['plan_title'],
                (int) $item['quantity']
            );
        }

        return sprintf(
            "Hi %s, my order is %s.\n%s\nTotal: %s\nI have sent %s (reference: %s). Please deliver it.",
            Settings::get('brand.name', 'ZapSub'),
            (string) $order['order_no'],
            implode("\n", $lines),
            taka((float) $order['total']),
            self::label((string) ($order['payment_method'] ?? '')),
            (string) ($order['txn_id'] ?? '-')
        );
    }
}
