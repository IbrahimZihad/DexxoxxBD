<?php
require_once __DIR__ . '/../../config/config.php';
$db = get_db();
$m = method();
$user = require_login();

if ($m === 'GET') {
    if (!empty($_GET['id'])) {
        $stmt = $db->prepare('SELECT * FROM orders WHERE id = ? AND user_id = ?');
        $stmt->execute([(int)$_GET['id'], $user['id']]);
        $order = $stmt->fetch();
        if (!$order) fail('Order not found.', 404);

        $items = $db->prepare('SELECT * FROM order_items WHERE order_id = ?');
        $items->execute([$order['id']]);
        $order['items'] = $items->fetchAll();

        $passes = $db->prepare('SELECT * FROM user_subscriptions WHERE user_id = ? AND order_item_id IN (SELECT id FROM order_items WHERE order_id = ?)');
        $passes->execute([$user['id'], $order['id']]);
        $order['passes'] = $passes->fetchAll();

        respond(['order' => $order]);
    }

    $stmt = $db->prepare('SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC');
    $stmt->execute([$user['id']]);
    respond(['orders' => $stmt->fetchAll()]);
}

if ($m === 'POST') {
    $in = json_input();
    $name = trim($in['customer_name'] ?? $user['name']);
    $email = trim($in['customer_email'] ?? $user['email']);
    $phone = trim($in['customer_phone'] ?? '');
    if ($phone === '') fail('A contact phone number is required for payment processing.');

    $cartStmt = $db->prepare("
        SELECT ci.item_type, ci.quantity,
               p.id AS product_id, p.name AS product_name, p.price AS product_price, p.duration_days AS product_duration, p.stock,
               pl.id AS plan_id, pl.name AS plan_name, pl.price AS plan_price, pl.duration_days AS plan_duration
        FROM cart_items ci
        LEFT JOIN products p ON p.id = ci.product_id
        LEFT JOIN plans pl ON pl.id = ci.plan_id
        WHERE ci.user_id = ?
    ");
    $cartStmt->execute([$user['id']]);
    $cartRows = $cartStmt->fetchAll();
    if (!$cartRows) fail('Your cart is empty.');

    $db->beginTransaction();
    try {
        $total = 0;
        $lineItems = [];
        foreach ($cartRows as $r) {
            $isProduct = $r['item_type'] === 'product';
            $unit = (float)($isProduct ? $r['product_price'] : $r['plan_price']);
            $qty = (int)$r['quantity'];
            $lineTotal = $unit * $qty;
            $total += $lineTotal;
            $lineItems[] = [
                'item_type' => $r['item_type'],
                'product_id' => $isProduct ? $r['product_id'] : null,
                'plan_id' => $isProduct ? null : $r['plan_id'],
                'item_name' => $isProduct ? $r['product_name'] : $r['plan_name'],
                'unit_price' => $unit,
                'quantity' => $qty,
                'duration_days' => $isProduct ? (int)$r['product_duration'] : (int)$r['plan_duration'],
                'line_total' => $lineTotal,
            ];
        }

        $orderNumber = gen_order_number();
        $stmt = $db->prepare('INSERT INTO orders (order_number, user_id, subtotal, total_amount, status, customer_name, customer_email, customer_phone)
            VALUES (?,?,?,?,"pending",?,?,?)');
        $stmt->execute([$orderNumber, $user['id'], $total, $total, $name, $email, $phone]);
        $orderId = (int)$db->lastInsertId();

        $itemStmt = $db->prepare('INSERT INTO order_items (order_id, item_type, product_id, plan_id, item_name, unit_price, quantity, duration_days, line_total)
            VALUES (?,?,?,?,?,?,?,?,?)');
        foreach ($lineItems as $li) {
            $itemStmt->execute([$orderId, $li['item_type'], $li['product_id'], $li['plan_id'], $li['item_name'], $li['unit_price'], $li['quantity'], $li['duration_days'], $li['line_total']]);
        }

        $db->prepare('DELETE FROM cart_items WHERE user_id = ?')->execute([$user['id']]);
        $db->commit();

        respond(['order_id' => $orderId, 'order_number' => $orderNumber, 'total_amount' => $total], 201);
    } catch (Exception $e) {
        $db->rollBack();
        fail('Could not create order. Please try again.', 500);
    }
}

fail('Method not allowed.', 405);
