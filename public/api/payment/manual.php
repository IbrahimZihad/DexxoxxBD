<?php
require_once __DIR__ . '/../../../config/config.php';
$db = get_db();
$user = require_login();

if (method() !== 'POST') fail('Method not allowed.', 405);

$in = json_input();

$orderId       = (int)($in['order_id'] ?? 0);
$payMethod     = trim($in['payment_method'] ?? '');
$senderNumber  = trim($in['sender_number'] ?? '');
$transactionId = trim($in['transaction_id'] ?? '');
$note          = trim($in['note'] ?? '');

if (!$orderId)       fail('Missing order_id.');
if (!in_array($payMethod, ['bkash','nagad','rocket','bank'], true)) fail('Invalid payment method.');
if (!$transactionId) fail('Transaction ID is required.');

$stmt = $db->prepare('SELECT * FROM orders WHERE id = ? AND user_id = ?');
$stmt->execute([$orderId, $user['id']]);
$order = $stmt->fetch();
if (!$order) fail('Order not found.', 404);
if ($order['status'] !== 'pending') fail('This order has already been processed.');

// Check no manual payment already submitted for this order
$dup = $db->prepare('SELECT id FROM manual_payments WHERE order_id = ? AND status = "pending"');
$dup->execute([$orderId]);
if ($dup->fetch()) fail('A payment submission for this order is already pending admin review.');

$db->prepare(
    'INSERT INTO manual_payments (order_id, payment_method, sender_number, transaction_id, amount, note)
     VALUES (?, ?, ?, ?, ?, ?)'
)->execute([$orderId, $payMethod, $senderNumber ?: null, $transactionId, $order['total_amount'], $note ?: null]);

// Update order payment_method field
$db->prepare('UPDATE orders SET payment_method = ? WHERE id = ?')
   ->execute([$payMethod, $orderId]);

respond([
    'ok'      => true,
    'message' => 'Payment details submitted. Your order will be activated once the admin verifies your payment.',
]);
