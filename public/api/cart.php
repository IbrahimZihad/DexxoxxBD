<?php
require_once __DIR__ . '/../../config/config.php';
$db = get_db();
$m = method();
$user = require_login();

function cart_rows(PDO $db, int $userId): array
{
    $stmt = $db->prepare("
        SELECT ci.id, ci.item_type, ci.quantity,
               p.id AS product_id, p.name AS product_name, p.price AS product_price, p.image_url AS product_image, p.duration_days AS product_duration,
               pl.id AS plan_id, pl.name AS plan_name, pl.price AS plan_price, pl.duration_days AS plan_duration
        FROM cart_items ci
        LEFT JOIN products p ON p.id = ci.product_id
        LEFT JOIN plans pl ON pl.id = ci.plan_id
        WHERE ci.user_id = ?
        ORDER BY ci.id DESC
    ");
    $stmt->execute([$userId]);
    $rows = $stmt->fetchAll();

    $items = [];
    $total = 0;
    foreach ($rows as $r) {
        $isProduct = $r['item_type'] === 'product';
        $unit = (float)($isProduct ? $r['product_price'] : $r['plan_price']);
        $lineTotal = $unit * (int)$r['quantity'];
        $total += $lineTotal;
        $items[] = [
            'cart_item_id' => (int)$r['id'],
            'type' => $r['item_type'],
            'id' => $isProduct ? (int)$r['product_id'] : (int)$r['plan_id'],
            'name' => $isProduct ? $r['product_name'] : $r['plan_name'],
            'image' => $isProduct ? $r['product_image'] : null,
            'unit_price' => $unit,
            'duration_days' => $isProduct ? (int)$r['product_duration'] : (int)$r['plan_duration'],
            'quantity' => (int)$r['quantity'],
            'line_total' => $lineTotal,
        ];
    }
    return ['items' => $items, 'total' => $total];
}

if ($m === 'GET') {
    respond(cart_rows($db, $user['id']));
}

if ($m === 'POST') {
    $in = json_input();
    $type = $in['type'] ?? '';
    $itemId = (int)($in['id'] ?? 0);
    $qty = max(1, (int)($in['quantity'] ?? 1));
    if (!in_array($type, ['product', 'plan'], true) || !$itemId) fail('Invalid cart item.');

    if ($type === 'product') {
        $exists = $db->prepare('SELECT id FROM products WHERE id = ? AND status = "active"');
    } else {
        $exists = $db->prepare('SELECT id FROM plans WHERE id = ? AND status = "active"');
    }
    $exists->execute([$itemId]);
    if (!$exists->fetch()) fail('Item not found or unavailable.', 404);

    $col = $type === 'product' ? 'product_id' : 'plan_id';
    $stmt = $db->prepare("SELECT id, quantity FROM cart_items WHERE user_id = ? AND item_type = ? AND $col = ?");
    $stmt->execute([$user['id'], $type, $itemId]);
    $existing = $stmt->fetch();

    if ($existing) {
        $db->prepare('UPDATE cart_items SET quantity = quantity + ? WHERE id = ?')->execute([$qty, $existing['id']]);
    } else {
        $db->prepare("INSERT INTO cart_items (user_id, item_type, $col, quantity) VALUES (?,?,?,?)")
           ->execute([$user['id'], $type, $itemId, $qty]);
    }
    respond(cart_rows($db, $user['id']), 201);
}

if ($m === 'PUT') {
    $in = json_input();
    $cartItemId = (int)($_GET['id'] ?? 0);
    $qty = (int)($in['quantity'] ?? 0);
    if (!$cartItemId || $qty < 1) fail('Invalid quantity.');
    $db->prepare('UPDATE cart_items SET quantity = ? WHERE id = ? AND user_id = ?')->execute([$qty, $cartItemId, $user['id']]);
    respond(cart_rows($db, $user['id']));
}

if ($m === 'DELETE') {
    $cartItemId = (int)($_GET['id'] ?? 0);
    if ($cartItemId) {
        $db->prepare('DELETE FROM cart_items WHERE id = ? AND user_id = ?')->execute([$cartItemId, $user['id']]);
    } else {
        $db->prepare('DELETE FROM cart_items WHERE user_id = ?')->execute([$user['id']]); // clear all
    }
    respond(cart_rows($db, $user['id']));
}

fail('Method not allowed.', 405);
