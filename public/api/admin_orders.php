<?php
require_once __DIR__ . '/../../config/config.php';
$db = get_db();
$m = method();
require_admin();

if ($m === 'GET') {
    if (!empty($_GET['id'])) {
        $stmt = $db->prepare('SELECT o.*, u.name AS user_name, u.email AS user_email FROM orders o JOIN users u ON u.id = o.user_id WHERE o.id = ?');
        $stmt->execute([(int)$_GET['id']]);
        $order = $stmt->fetch();
        if (!$order) fail('Order not found.', 404);
        $items = $db->prepare('SELECT * FROM order_items WHERE order_id = ?');
        $items->execute([$order['id']]);
        $order['items'] = $items->fetchAll();
        respond(['order' => $order]);
    }

    $where = '1=1'; $params = [];
    if (!empty($_GET['status'])) { $where = 'o.status = ?'; $params[] = $_GET['status']; }

    $stmt = $db->prepare("SELECT o.*, u.name AS user_name, u.email AS user_email FROM orders o JOIN users u ON u.id = o.user_id WHERE $where ORDER BY o.created_at DESC LIMIT 200");
    $stmt->execute($params);
    respond(['orders' => $stmt->fetchAll()]);
}

if ($m === 'PUT') {
    $id = (int)($_GET['id'] ?? 0);
    $in = json_input();
    $status = $in['status'] ?? '';
    if (!$id || !in_array($status, ['pending','paid','failed','cancelled'], true)) fail('Invalid update.');
    $db->prepare('UPDATE orders SET status = ? WHERE id = ?')->execute([$status, $id]);
    if ($status === 'paid') grant_passes_for_order($db, $id);
    respond(['ok' => true]);
}

fail('Method not allowed.', 405);
