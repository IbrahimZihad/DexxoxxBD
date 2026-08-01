<?php
/** JSON helpers */
function json_input(): array
{
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function respond($data, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($data);
    exit;
}

function fail(string $message, int $status = 400): void
{
    respond(['error' => $message], $status);
}

/** Auth helpers (session-based) */
function current_user(): ?array
{
    if (empty($_SESSION['user_id'])) return null;
    static $user = null;
    if ($user === null) {
        $stmt = get_db()->prepare('SELECT id, name, email, phone, role, status FROM users WHERE id = ?');
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch() ?: null;
    }
    return $user;
}

function require_login(): array
{
    $user = current_user();
    if (!$user) fail('You must be logged in.', 401);
    if ($user['status'] !== 'active') fail('This account has been suspended.', 403);
    return $user;
}

function require_admin(): array
{
    $user = require_login();
    if ($user['role'] !== 'admin') fail('Admin access required.', 403);
    return $user;
}

/** Misc */
function slugify(string $text): string
{
    $text = strtolower(trim($text));
    $text = preg_replace('/[^a-z0-9]+/', '-', $text);
    return trim($text, '-');
}

function gen_order_number(): string
{
    return 'SP-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
}

function gen_tran_id(): string
{
    return 'TXN' . time() . strtoupper(bin2hex(random_bytes(4)));
}

function gen_pass_code(): string
{
    return 'PASS-' . strtoupper(bin2hex(random_bytes(4)));
}

function method(): string
{
    return $_SERVER['REQUEST_METHOD'];
}

/**
 * Called once an order's payment is confirmed valid.
 * Marks the order paid and issues one "pass" (user_subscriptions row) per unit purchased.
 */
function grant_passes_for_order(PDO $db, int $orderId): void
{
    $order = $db->prepare('SELECT * FROM orders WHERE id = ?');
    $order->execute([$orderId]);
    $order = $order->fetch();
    if (!$order || $order['status'] === 'paid') return; // already processed / not found

    $db->prepare("UPDATE orders SET status = 'paid' WHERE id = ?")->execute([$orderId]);

    $items = $db->prepare('SELECT * FROM order_items WHERE order_id = ?');
    $items->execute([$orderId]);

    $insert = $db->prepare('INSERT INTO user_subscriptions (user_id, order_item_id, item_type, item_name, pass_code, starts_at, ends_at)
        VALUES (?,?,?,?,?,?,?)');

    foreach ($items->fetchAll() as $item) {
        for ($i = 0; $i < (int)$item['quantity']; $i++) {
            $start = date('Y-m-d');
            $end = date('Y-m-d', strtotime("+{$item['duration_days']} days"));
            $insert->execute([
                $order['user_id'], $item['id'], $item['item_type'], $item['item_name'],
                gen_pass_code(), $start, $end,
            ]);
        }
    }
}
