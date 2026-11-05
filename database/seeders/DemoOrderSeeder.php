<?php

declare(strict_types=1);

use App\Core\Config;
use App\Core\Crypto;
use Phinx\Seed\AbstractSeed;

/**
 * Six orders that between them cover every state the admin panel can show:
 * paid and delivered, awaiting payment verification, paid and waiting in the
 * delivery queue, rejected payment, a cancelled order, and an abandoned basket
 * inside the pending hold. Every number is obviously a demo (ZS-DEMO-nn) so
 * nobody mistakes them for real sales; delete them with
 *   delete from orders where order_no like 'ZS-DEMO%';
 */
final class DemoOrderSeeder extends AbstractSeed
{
    /** @var list<array<string, mixed>> joined plan + service rows */
    private array $plans = [];

    /** @var array<string, int> email => user id */
    private array $users = [];

    /** @var array<string, array<string, mixed>> code => coupon row */
    private array $coupons = [];

    /** @var array<string, int> code => times used by the demo orders */
    private array $couponUsage = [];

    public function getDependencies(): array
    {
        return ['CatalogSeeder', 'UserSeeder'];
    }

    public function run(): void
    {
        // Crypto reads config/app.php for APP_KEY. Phinx only boots the .env
        // parser, so without this the demo delivery notes would be stored plain.
        if (Config::all() === []) {
            Config::load(dirname(__DIR__, 2) . '/config');
        }

        $this->plans = $this->fetchAll(
            'select p.id, p.title, p.price, p.duration_months, p.seats, s.name as service_name, s.slug as service_slug
               from plans p join services s on s.id = p.service_id
              order by p.id'
        );

        $this->users = [];

        foreach ($this->fetchAll('select id, email from users') as $user) {
            $this->users[(string) $user['email']] = (int) $user['id'];
        }

        foreach ($this->fetchAll('select code, type, value, used_count from coupons') as $coupon) {
            $this->coupons[(string) $coupon['code']] = $coupon;
        }

        // Only the rows this seeder owns. The child tables have ON DELETE
        // CASCADE, so items, payments and history go with them - a real order
        // placed from the storefront is never touched.
        $this->execute("delete from orders where order_no like 'ZS-DEMO%'");

        foreach ($this->spec() as $order) {
            $this->writeOrder($order);
        }

        foreach ($this->couponUsage as $code => $extra) {
            $current = (int) ($this->coupons[$code]['used_count'] ?? 0);

            // Bound parameters, because AbstractSeed::execute() has no bind API.
            $statement = $this->getAdapter()->getConnection()->prepare(
                'update coupons set used_count = :total where code = :code'
            );
            $statement->bindValue(':total', $current + $extra, PDO::PARAM_INT);
            $statement->bindValue(':code', $code, PDO::PARAM_STR);
            $statement->execute();
        }
    }

    /** @return list<array<string, mixed>> */
    private function spec(): array
    {
        return [
            [
                'no' => 'ZS-DEMO-01',
                'email' => 'tanvir@example.com',
                'name' => 'Tanvir Ahmed',
                'phone' => '01912345678',
                'age_days' => 6,
                'status' => 'completed',
                'payment_status' => 'paid',
                'fulfillment_status' => 'delivered',
                'coupon' => 'ZAP10',
                'payment' => ['method' => 'bkash', 'txn' => 'BKS7H2K4Q9', 'status' => 'verified', 'sender' => '01912345678'],
                'lines' => [
                    ['netflix-premium', 3, 'Premium 4K', "Login: netflix.demo.4k@mailinator.com\nPassword: Zs#Premium2026\nProfile: \"Tanvir\" is yours. Change the password after the first sign-in."],
                ],
            ],
            [
                'no' => 'ZS-DEMO-02',
                'email' => 'rakibul@example.com',
                'name' => 'Rakibul Islam',
                'phone' => '01777112233',
                'age_days' => 0,
                'status' => 'pending',
                'payment_status' => 'pending_verification',
                'fulfillment_status' => 'unassigned',
                'coupon' => null,
                'payment' => ['method' => 'nagad', 'txn' => 'NGD88P1Z30', 'status' => 'pending', 'sender' => '01777112233'],
                'note' => 'Please send the Disney+ login to my WhatsApp as well, I do not check email often.',
                'lines' => [
                    ['disney-plus', 1, 'Superb Premium', null],
                ],
            ],
            [
                'no' => 'ZS-DEMO-03',
                'email' => 'farhana@example.com',
                'name' => 'Farhana Akter',
                'phone' => '01855443322',
                'age_days' => 1,
                'status' => 'processing',
                'payment_status' => 'paid',
                'fulfillment_status' => 'unassigned',
                'coupon' => 'FIRST50',
                'payment' => ['method' => 'rocket', 'txn' => 'RKT55A7X2B', 'status' => 'verified', 'sender' => '01855443322-1'],
                'lines' => [
                    ['youtube-premium', 6, 'Family', null],
                ],
            ],
            [
                'no' => 'ZS-DEMO-04',
                'email' => 'shahriar@example.com',
                'name' => 'Shahriar Kabir',
                'phone' => '01688776655',
                'age_days' => 3,
                'status' => 'failed',
                'payment_status' => 'unpaid',
                'fulfillment_status' => 'unassigned',
                'coupon' => null,
                'payment' => ['method' => 'bkash', 'txn' => 'BKS00000000', 'status' => 'rejected', 'sender' => '01688776655', 'admin_note' => 'Transaction ID does not appear in our statement. Asked the customer to re-check.'],
                'lines' => [
                    ['canva-pro', 1, 'Pro', null],
                ],
            ],
            [
                'no' => 'ZS-DEMO-05',
                'email' => 'imran@example.com',
                'name' => 'Imran Hossain',
                'phone' => '01555129876',
                'age_days' => 9,
                'status' => 'completed',
                'payment_status' => 'paid',
                'fulfillment_status' => 'delivered',
                'coupon' => 'ANNUAL100',
                'payment' => ['method' => 'card', 'txn' => 'VISA-774219045', 'status' => 'verified', 'sender' => ''],
                'lines' => [
                    ['microsoft-365', 12, 'Personal', 'Added to imran@example.com. Subscription is live under account > Services.'],
                    ['google-one-2tb', 6, '2TB', 'Applied to imran@example.com. Storage shows 2 TB in Google One now.'],
                ],
            ],
            [
                'no' => 'ZS-DEMO-06',
                'email' => 'nadia@example.com',
                'name' => 'Nadia Rahman',
                'phone' => '01311223344',
                'age_days' => 0,
                'status' => 'pending',
                'payment_status' => 'unpaid',
                'fulfillment_status' => 'unassigned',
                'coupon' => null,
                'payment' => null,
                'lines' => [
                    ['spotify-premium', 1, 'Personal', null],
                ],
            ],
            [
                'no' => 'ZS-DEMO-07',
                'email' => 'jubayer@example.com',
                'name' => 'Md Jubayer Alam',
                'phone' => '01722334455',
                'age_days' => 14,
                'status' => 'cancelled',
                'payment_status' => 'unpaid',
                'fulfillment_status' => 'unassigned',
                'coupon' => 'STUDENT15',
                'payment' => ['method' => 'bank', 'txn' => '', 'status' => 'pending', 'sender' => ''],
                'lines' => [
                    ['chatgpt-plus', 3, 'Plus', null],
                ],
            ],
        ];
    }

    /** @param array<string, mixed> $order */
    private function writeOrder(array $order): void
    {
        $createdAt = date('Y-m-d H:i:s', time() - ((int) $order['age_days'] * 86400) - 7200);
        $lines = [];
        $subtotal = 0.0;

        foreach ($order['lines'] as [$slug, $months, $fragment, $deliveryNote]) {
            $plan = $this->pick((string) $slug, (int) $months, (string) $fragment);
            $subtotal += (float) $plan['price'];

            $lines[] = [
                'plan' => $plan,
                'delivery_note' => $deliveryNote,
            ];
        }

        $subtotal = round($subtotal, 2);
        $discount = $this->discount((string) $order['coupon'], $subtotal);
        $total = round($subtotal - $discount, 2);

        $ordersTable = $this->table('orders');
        $ordersTable->insert([
            'order_no' => $order['no'],
            'user_id' => $this->users[(string) $order['email']] ?? null,
            'status' => $order['status'],
            'payment_status' => $order['payment_status'],
            'fulfillment_status' => $order['fulfillment_status'],
            'subtotal' => $subtotal,
            'discount' => $discount,
            'total' => $total,
            'coupon_code' => $order['coupon'],
            'customer_name' => $order['name'],
            'email' => $order['email'],
            'phone' => $order['phone'],
            'note' => $order['note'] ?? null,
            'delivery_request' => Crypto::encrypt($this->deliveryRequest($order['lines'], $order['email'])),
            'ip_address' => '103.108.15.' . random_int(2, 250),
            'verified_by' => $order['payment_status'] === 'paid' ? ($this->users['rafi@zapsub.test'] ?? null) : null,
            'paid_at' => $order['payment_status'] === 'paid'
                ? date('Y-m-d H:i:s', strtotime($createdAt) + random_int(300, 1800))
                : null,
            'created_at' => $createdAt,
            'updated_at' => date('Y-m-d H:i:s', strtotime($createdAt) + 900),
        ])->saveData();

        $orderId = (int) $this->fetchRow('select id from orders order by id desc limit 1')['id'];

        $itemsTable = $this->table('order_items');
        $historyTable = $this->table('order_status_history');
        $paymentsTable = $this->table('payments');

        foreach ($lines as $index => $line) {
            $plan = $line['plan'];
            $delivered = $line['delivery_note'] !== null;

            $itemsTable->insert([
                'order_id' => $orderId,
                'plan_id' => (int) $plan['id'],
                'service_name' => $plan['service_name'],
                'plan_title' => $plan['title'],
                'unit_price' => $plan['price'],
                'quantity' => 1,
                'line_total' => $plan['price'],
                'status' => $delivered ? 'delivered' : ($order['status'] === 'cancelled' ? 'failed' : 'pending'),
                'delivery_note' => $delivered ? Crypto::encrypt((string) $line['delivery_note']) : null,
                'delivered_at' => $delivered ? date('Y-m-d H:i:s', strtotime($createdAt) + 1500) : null,
                'created_at' => $createdAt,
                'updated_at' => date('Y-m-d H:i:s', strtotime($createdAt) + ($index + 1) * 600),
            ])->saveData();
        }

        if ($order['payment'] !== null) {
            $payment = $order['payment'];

            $paymentsTable->insert([
                'order_id' => $orderId,
                'method' => $payment['method'],
                'txn_id' => $payment['txn'] === '' ? null : $payment['txn'],
                'amount' => $total,
                'status' => $payment['status'],
                'sender_number' => $payment['sender'] === '' ? null : $payment['sender'],
                'admin_note' => $payment['admin_note'] ?? null,
                'verified_by' => $payment['status'] === 'verified' ? ($this->users['rafi@zapsub.test'] ?? null) : null,
                'verified_at' => $payment['status'] === 'verified'
                    ? date('Y-m-d H:i:s', strtotime($createdAt) + 1200)
                    : null,
                'created_at' => date('Y-m-d H:i:s', strtotime($createdAt) + 600),
                'updated_at' => date('Y-m-d H:i:s', strtotime($createdAt) + 1200),
            ])->saveData();
        }

        $historyTable->insert([
            ['order_id' => $orderId, 'status' => 'pending', 'note' => 'Order created from the storefront.', 'created_at' => $createdAt],
            [
                'order_id' => $orderId,
                'status' => (string) $order['status'],
                'note' => $this->historyNote($order),
                'created_at' => date('Y-m-d H:i:s', strtotime($createdAt) + 1500),
            ],
        ])->saveData();

        // The coupon counters in CouponSeeder are demo fiction; make them agree
        // with the orders that actually exist after this seeder runs.
        if ($order['coupon'] !== null) {
            $code = (string) $order['coupon'];
            $this->couponUsage[$code] = ($this->couponUsage[$code] ?? 0) + 1;
        }
    }

    /** @return array<string, mixed> */
    private function pick(string $slug, int $months, string $fragment): array
    {
        foreach ($this->plans as $plan) {
            if ((string) $plan['service_slug'] !== $slug
                || (int) $plan['duration_months'] !== $months
                || !str_contains((string) $plan['title'], $fragment)) {
                continue;
            }

            return $plan;
        }

        throw new RuntimeException(sprintf('Catalogue is missing "%s" %d-month "%s". Run CatalogSeeder first.', $slug, $months, $fragment));
    }

    private function discount(string $code, float $subtotal): float
    {
        $coupon = $this->coupons[$code] ?? null;

        if ($coupon === null) {
            return 0.0;
        }

        $raw = (string) $coupon['type'] === 'percent'
            ? $subtotal * ((float) $coupon['value'] / 100)
            : (float) $coupon['value'];

        return round(min($subtotal, max(0, $raw)), 2);
    }

    /** @param list<array<int, mixed>> $lines */
    private function deliveryRequest(array $lines, string $email): string
    {
        $wanted = [];

        foreach ($lines as [$slug, $months, $fragment, $note]) {
            $wanted[] = $fragment . ' (' . $months . ' month' . ($months > 1 ? 's' : '') . ')';
        }

        return 'Deliver to: ' . $email . "\nPlans: " . implode(', ', $wanted);
    }

    /** @param array<string, mixed> $order */
    private function historyNote(array $order): string
    {
        return match ((string) $order['status']) {
            'completed' => 'Payment verified and account details sent on WhatsApp.',
            'processing' => 'Payment verified; waiting in the delivery queue.',
            'cancelled' => 'Cancelled at the customer request before delivery.',
            'failed' => 'Payment rejected: transaction ID not found in the statement.',
            default => 'Awaiting payment verification.',
        };
    }
}
