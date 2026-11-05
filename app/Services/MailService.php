<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Logger;
use App\Core\Settings;
use Throwable;

/**
 * Outbound email. The default driver writes the message to storage/logs/mail.log
 * so a local XAMPP box can exercise the flow without an SMTP server; setting
 * MAIL_DRIVER=mail uses PHP's mail() with no other change.
 */
final class MailService
{
    public static function send(string $to, string $subject, string $body): bool
    {
        $from = (string) Config::get('app.mail.from_address', 'no-reply@localhost');
        $fromName = (string) Config::get('app.mail.from_name', Settings::get('brand.name', 'ZapSub'));
        $driver = (string) Config::get('app.mail.driver', 'log');

        if (filter_var($to, FILTER_VALIDATE_EMAIL) === false) {
            Logger::warning('mail_skipped_bad_recipient', ['to' => mb_substr($to, 0, 80)]);

            return false;
        }

        if ($driver === 'mail') {
            $headers = implode("\r\n", [
                'From: ' . sprintf('%s <%s>', $fromName, $from),
                'Reply-To: ' . Settings::get('contact.email', $from),
                'Content-Type: text/plain; charset=UTF-8',
                'X-Mailer: ZapSub',
            ]);

            try {
                return @mail($to, $subject, $body, $headers);
            } catch (Throwable $exception) {
                Logger::error('mail_failed', ['reason' => $exception->getMessage(), 'to' => $to]);

                return false;
            }
        }

        Logger::mail($subject, $to, $body);

        return true;
    }

    /**
     * @param array<string, mixed>       $order
     * @param list<array<string, mixed>> $items
     */
    public static function orderPlaced(array $order, array $items): void
    {
        $brand = Settings::get('brand.name', 'ZapSub');

        $lines = [];

        foreach ($items as $item) {
            $lines[] = sprintf(
                '  - %s (%s) x%d  %s',
                (string) $item['service_name'],
                (string) $item['plan_title'],
                (int) $item['quantity'],
                taka((float) $item['line_total'])
            );
        }

        $itemBlock = implode("\n", $lines);
        $wa = wa_link('Order ' . $order['order_no'] . ': I have sent the payment.');

        $body = "Hi " . $order['customer_name'] . ",\n\n"
            . 'Your order ' . $order['order_no'] . " is reserved. Here is what you asked for:\n\n"
            . $itemBlock . "\n\n"
            . 'Total payable: ' . taka((float) $order['total']) . "\n"
            . "Payment status: unpaid - finish the payment step to notify us.\n\n"
            . "Next step\n---------\n"
            . "Send the amount with bKash, Nagad, Rocket or card and submit the transaction ID\n"
            . "on the payment page, or just message us and we will finish it with you:\n"
            . $wa . "\n\n"
            . "We verify payments by hand, so nothing is marked paid until a person has confirmed\n"
            . "it. Delivery usually takes a few minutes.\n\n-- " . $brand . "\n"
            . Settings::get('contact.phone', '');

        self::send((string) $order['email'], 'Your ' . $brand . ' order ' . $order['order_no'], $body);
    }

    public static function resetLink(string $to, string $link, string $name = ''): void
    {
        $brand = Settings::get('brand.name', 'ZapSub');
        $greeting = $name === '' ? 'Hi' : 'Hi ' . $name;

        $body = $greeting . ",\n\n"
            . "Someone asked to reset the password for this account. Open this link within one hour:\n"
            . $link . "\n\n"
            . "If you did not ask for it, ignore this email - the link does nothing unless it is used.\n\n-- "
            . $brand;

        self::send($to, $brand . ' password reset', $body);
    }

    public static function adminAlert(string $subject, string $body): void
    {
        $to = Settings::get('contact.email', (string) Config::get('app.mail.from_address', 'no-reply@localhost'));

        self::send($to, $subject, $body);
    }
}
