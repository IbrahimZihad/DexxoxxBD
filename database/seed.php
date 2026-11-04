<?php
declare(strict_types=1);

/**
 * Seeder.
 *
 *   php database/seed.php            insert whatever is missing
 *   php database/seed.php --force    also refresh prices, passwords and demo orders
 *
 * Re-running is safe: catalogue rows are matched on their slug, settings on their
 * key, accounts on their email.
 */

use App\Core\Database;
use App\Repositories\OrderRepository;
use App\Repositories\PassRepository;
use App\Repositories\ProductRepository;
use App\Repositories\UserRepository;
use App\Security\Hash;
use App\Support\OrderNumber;

['pdo' => $pdo, 'database' => $database] = require __DIR__ . '/cli-boot.php';

$force = in_array('--force', $_SERVER['argv'] ?? [], true);
$report = [];

function seed(string $name): array
{
    return require __DIR__ . '/seeds/' . $name . '.php';
}

function slugOf(string $text): string
{
    $slug = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($text)) ?? '', '-');

    return $slug === '' ? 'item' : $slug;
}

// ---------------------------------------------------------------- categories
$categories = 0;

foreach (seed('categories') as $row) {
    Database::statement(
        'INSERT INTO categories (name, slug, icon, sort_order) VALUES (?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE name = VALUES(name), icon = VALUES(icon), sort_order = VALUES(sort_order)',
        [$row['name'], $row['slug'], $row['icon'], $row['sort_order']]
    );
    $categories++;
}

/** @var array<string,int> $categoryIds */
$categoryIds = [];

foreach (Database::select('SELECT id, slug FROM categories') as $row) {
    $categoryIds[(string) $row['slug']] = (int) $row['id'];
}

$report[] = "categories  {$categories}";

// ------------------------------------------------------------------ products
$created = 0;
$updated = 0;
/** @var array<string,array<string,mixed>> $productRows keyed by slug */
$productRows = [];

foreach (seed('products') as $row) {
    $slug = slugOf((string) $row['name']);
    $data = [
        'category_id' => $categoryIds[$row['category']] ?? null,
        'name' => $row['name'],
        'slug' => $slug,
        'description' => $row['description'],
        'image_url' => $row['image_url'],
        'price' => (float) $row['price'],
        'compare_at_price' => $row['compare_at_price'] === null ? null : (float) $row['compare_at_price'],
        'duration_days' => (int) $row['duration_days'],
        'stock' => (int) $row['stock'],
        'status' => 'active',
        'is_featured' => (int) $row['is_featured'],
    ];

    $existing = ProductRepository::findBySlug($slug);

    if ($existing === null) {
        $data['slug'] = ProductRepository::uniqueSlug((string) $row['name']);
        $data['id'] = ProductRepository::create($data);
        $created++;
    } else {
        $data['id'] = (int) $existing['id'];

        if ($force) {
            // Stock is operational data once the shop is live — never overwrite it.
            unset($data['stock']);
            ProductRepository::update($data['id'], $data);
            $updated++;
        }
    }

    $productRows[$data['slug']] = $data;
}

$report[] = "products    {$created} created, {$updated} refreshed";

// --------------------------------------------------------------------- plans
$planRows = [];
$planCreated = 0;

foreach (seed('plans') as $row) {
    $slug = slugOf((string) $row['name']);
    $data = [
        'name' => $row['name'],
        'slug' => $slug,
        'description' => $row['description'],
        'price' => (float) $row['price'],
        'duration_days' => (int) $row['duration_days'],
        'features' => (array) $row['features'],
        'badge' => $row['badge'],
        'status' => 'active',
        'sort_order' => (int) $row['sort_order'],
    ];

    $found = Database::selectOne('SELECT id FROM plans WHERE slug = ?', [$slug]);

    if ($found === null) {
        $data['id'] = Database::insert(
            'INSERT INTO plans (name, slug, description, price, duration_days, features, badge, status, sort_order)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['name'], $data['slug'], $data['description'], $data['price'],
                $data['duration_days'], json_encode($data['features'], JSON_UNESCAPED_UNICODE),
                $data['badge'], $data['status'], $data['sort_order'],
            ]
        );
        $planCreated++;
    } else {
        $data['id'] = (int) $found['id'];
    }

    $planRows[$slug] = $data;
}

$report[] = "plans       {$planCreated} created";

// ------------------------------------------------------------------ settings
$settingsWritten = 0;
$settingsSql = 'INSERT INTO settings (key_name, value, type, group_name, label, is_public) VALUES (?, ?, ?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE type = VALUES(type), group_name = VALUES(group_name),
        label = VALUES(label), is_public = VALUES(is_public)';

if ($force) {
    $settingsSql .= ', value = VALUES(value)';
}

foreach (seed('settings') as $row) {
    Database::statement($settingsSql, [
        $row['key'], $row['value'], $row['type'], $row['group'], $row['label'], (int) $row['public'],
    ]);
    $settingsWritten++;
}

$report[] = "settings    {$settingsWritten} keys ensured";

// --------------------------------------------------------------------- users
/** @var array<string,array<string,mixed>> $users keyed by email */
$users = [];
$userCreated = 0;

foreach (seed('users') as $row) {
    $email = strtolower((string) $row['email']);
    $existing = UserRepository::findByEmailWithHash($email);

    if ($existing === null) {
        $id = UserRepository::create((string) $row['name'], $email, Hash::make((string) $row['password']), $row['phone'] ?? null, (string) $row['role']);
        $userCreated++;
    } else {
        $id = (int) $existing['id'];

        if ($force) {
            UserRepository::updatePassword($id, Hash::make((string) $row['password']));
            Database::statement('UPDATE users SET role = ?, status = \'active\' WHERE id = ?', [$row['role'], $id]);
        }
    }

    $users[$email] = UserRepository::find($id) ?? [];
}

$report[] = "accounts    {$userCreated} created";

// -------------------------------------------------------------- demo orders
$orderCount = (int) (Database::selectOne('SELECT COUNT(*) AS c FROM orders')['c'] ?? 0);

if ($orderCount > 0 && !$force) {
    $report[] = 'demo orders skipped (orders already exist — use --force to rebuild them)';
} else {
    if ($force && $orderCount > 0) {
        Database::statement('DELETE FROM orders');
        Database::statement('DELETE FROM cart_items');
    }

    $demo = seedDemoOrders($users, $productRows, $planRows);
    $report[] = "demo orders {$demo}";
}

foreach ($report as $line) {
    echo '  ' . $line . "\n";
}

echo "Seeded {$database}.\n";

/**
 * Builds one order in each state the shop has to handle, so the admin screens and
 * the customer dashboard both have something real to render.
 *
 * @param array<string,array<string,mixed>> $users
 * @param array<string,array<string,mixed>> $products
 * @param array<string,array<string,mixed>> $plans
 */
function seedDemoOrders(array $users, array $products, array $plans): int
{
    $customer = $users['customer@dexxoxxbd.test']['id'] ?? null;
    $nusrat = $users['nusrat@dexxoxxbd.test']['id'] ?? null;
    $tanvir = $users['tanvir@dexxoxxbd.test']['id'] ?? null;
    $adminId = $users['admin@dexxoxxbd.test']['id'] ?? null;

    if ($customer === null || $nusrat === null || $tanvir === null || $adminId === null) {
        echo "  demo orders skipped (seed accounts missing)\n";

        return 0;
    }

    $ttl = (int) \App\Core\Config::get('orders.pending_ttl_minutes', 360);

    $scenarios = [
        [
            'user' => (int) $customer,
            'status' => 'paid',
            'method' => 'sslcommerz',
            'days_ago' => 12,
            'lines' => [
                ['product', 'netflix-premium-1-month', 1],
                ['product', 'spotify-premium-1-month', 2],
            ],
        ],
        [
            'user' => (int) $customer,
            'status' => 'pending',
            'method' => 'sslcommerz',
            'days_ago' => 0,
            'lines' => [['plan', 'plus-pass', 1]],
        ],
        [
            'user' => (int) $nusrat,
            'status' => 'paid',
            'method' => 'bkash',
            'days_ago' => 4,
            'lines' => [['product', 'chatgpt-plus-1-month', 1]],
        ],
        [
            'user' => (int) $nusrat,
            'status' => 'paid',
            'method' => 'nagad',
            'days_ago' => 120,
            'lines' => [['product', 'canva-pro-1-month', 1]],
        ],
        [
            'user' => (int) $tanvir,
            'status' => 'pending',
            'method' => 'rocket',
            'days_ago' => 1,
            'lines' => [['product', 'playstation-plus-essential-1-month', 1]],
        ],
        [
            'user' => (int) $tanvir,
            'status' => 'cancelled',
            'method' => 'sslcommerz',
            'days_ago' => 30,
            'lines' => [['product', 'youtube-premium-1-month', 1]],
        ],
    ];

    $granted = 0;

    foreach ($scenarios as $scenario) {
        Database::transaction(static function ($pdo) use ($scenario, $products, $plans, $ttl, $adminId, &$granted) {
            $lines = [];
            $subtotal = 0.0;

            foreach ($scenario['lines'] as [$type, $slug, $quantity]) {
                $row = $type === 'product' ? $products[$slug] : $plans[$slug];
                $lineTotal = round((float) $row['price'] * $quantity, 2);
                $subtotal += $lineTotal;

                $lines[] = [
                    'item_type' => $type,
                    'product_id' => $type === 'product' ? (int) $row['id'] : null,
                    'plan_id' => $type === 'plan' ? (int) $row['id'] : null,
                    'item_name' => (string) $row['name'],
                    'unit_price' => (float) $row['price'],
                    'quantity' => $quantity,
                    'duration_days' => (int) $row['duration_days'],
                    'line_total' => $lineTotal,
                ];
            }

            $placedAt = date('Y-m-d H:i:s', time() - 86400 * (int) $scenario['days_ago']);
            $buyer = UserRepository::find((int) $scenario['user']);

            $orderId = OrderRepository::create([
                'order_number' => OrderNumber::generate(),
                'user_id' => (int) $scenario['user'],
                'subtotal' => round($subtotal, 2),
                'discount_amount' => 0,
                'total_amount' => round($subtotal, 2),
                'status' => 'pending',
                'payment_method' => $scenario['method'],
                'customer_name' => $buyer['name'] ?? '',
                'customer_email' => $buyer['email'] ?? '',
                'customer_phone' => $buyer['phone'] ?? null,
                'ip_address' => '127.0.0.1',
                'pending_ttl_minutes' => $ttl,
            ]);

            Database::statement('UPDATE orders SET created_at = ?, updated_at = ? WHERE id = ?', [$placedAt, $placedAt, $orderId]);

            $itemIds = [];

            foreach ($lines as $line) {
                $itemIds[] = OrderRepository::addItem($pdo, $orderId, $line);
            }

            // Reserved stock: cancelled orders give it straight back.
            if ($scenario['status'] !== 'cancelled') {
                foreach ($lines as $line) {
                    if ($line['product_id'] !== null) {
                        Database::statement(
                            'UPDATE products SET stock = GREATEST(stock - ?, 0), sold_count = sold_count + ? WHERE id = ?',
                            [$line['quantity'], $line['quantity'], $line['product_id']]
                        );
                    }
                }
            } else {
                Database::statement('UPDATE order_items SET stock_released = 1 WHERE order_id = ?', [$orderId]);
            }

            $isManual = in_array($scenario['method'], ['bkash', 'nagad', 'rocket', 'bank'], true);

            if ($scenario['status'] === 'paid') {
                $paidAt = date('Y-m-d H:i:s', strtotime($placedAt) + 600);
                Database::statement('UPDATE orders SET status = \'paid\', paid_at = ? WHERE id = ?', [$paidAt, $orderId]);

                if ($isManual) {
                    Database::statement(
                        'INSERT INTO manual_payments (order_id, payment_method, sender_number, transaction_id, amount, note,
                                status, reviewed_by, reviewed_at, created_at)
                         VALUES (?, ?, ?, ?, ?, ?, \'approved\', ?, ?, ?)',
                        [
                            $orderId, $scenario['method'], '017' . random_int(10000000, 99999999),
                            strtoupper(substr(md5((string) $orderId), 0, 10)), round($subtotal, 2),
                            'Sent from my personal account.', $adminId, $paidAt, $placedAt,
                        ]
                    );
                } else {
                    Database::statement(
                        'INSERT INTO payments (order_id, tran_id, val_id, amount, currency, store_id, bank_tran_id, card_type,
                                status, validated_at, created_at)
                         VALUES (?, ?, ?, ?, \'BDT\', ?, ?, \'Visa\', \'valid\', ?, ?)',
                        [
                            $orderId, 'TXN' . strtoupper(substr(md5((string) $orderId), 0, 10)), 'VAL' . random_int(100000, 999999),
                            round($subtotal, 2), (string) \App\Core\Config::get('payment.sslcommerz.store_id') ?: 'demo_store',
                            'BTRN' . random_int(1000000, 9999999), $paidAt, $placedAt,
                        ]
                    );
                }

                foreach ($lines as $index => $line) {
                    $starts = date('Y-m-d', strtotime($paidAt));
                    $ends = date('Y-m-d', strtotime($starts . ' +' . $line['duration_days'] . ' days'));

                    for ($unit = 0; $unit < $line['quantity']; $unit++) {
                        $code = PassRepository::insert($pdo, [
                            'user_id' => (int) $scenario['user'],
                            'order_item_id' => $itemIds[$index],
                            'item_type' => $line['item_type'],
                            'item_name' => $line['item_name'],
                            'starts_at' => $starts,
                            'ends_at' => $ends,
                        ]);

                        if ($code === null) {
                            throw new RuntimeException('Could not allocate a pass code.');
                        }

                        $granted++;
                    }
                }
            } elseif ($scenario['status'] === 'cancelled') {
                Database::statement('UPDATE orders SET status = \'cancelled\' WHERE id = ?', [$orderId]);
            }

            return $orderId;
        });
    }

    // The Canva order was placed 120 days ago for a 30-day pass, so the sweep in
    // PassRepository::expireDue() already has a genuinely expired pass to flip.
    return $granted;
}
