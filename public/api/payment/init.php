<?php
require_once __DIR__ . '/../../../config/config.php';
$db = get_db();
$user = require_login();

if (method() !== 'POST') fail('Method not allowed.', 405);

$in = json_input();
$orderId = (int)($in['order_id'] ?? 0);
if (!$orderId) fail('Missing order_id.');

$stmt = $db->prepare('SELECT * FROM orders WHERE id = ? AND user_id = ?');
$stmt->execute([$orderId, $user['id']]);
$order = $stmt->fetch();
if (!$order) fail('Order not found.', 404);
if ($order['status'] !== 'pending') fail('This order has already been processed.');

$tranId = gen_tran_id();
$db->prepare('INSERT INTO payments (order_id, tran_id, amount, status) VALUES (?,?,?,"initiated")')
   ->execute([$orderId, $tranId, $order['total_amount']]);

$postData = [
    'store_id'    => SSLCZ_STORE_ID,
    'store_passwd'=> SSLCZ_STORE_PASSWORD,
    'total_amount'=> $order['total_amount'],
    'currency'    => 'BDT',
    'tran_id'     => $tranId,
    'success_url' => API_BASE_URL . '/payment/success.php',
    'fail_url'    => API_BASE_URL . '/payment/fail.php',
    'cancel_url'  => API_BASE_URL . '/payment/cancel.php',
    'ipn_url'     => API_BASE_URL . '/payment/ipn.php',

    'cus_name'    => $order['customer_name'],
    'cus_email'   => $order['customer_email'],
    'cus_phone'   => $order['customer_phone'],
    'cus_add1'    => 'N/A',
    'cus_city'    => 'Dhaka',
    'cus_country' => 'Bangladesh',

    'shipping_method' => 'NO',
    'product_name'     => 'Digital Subscription — ' . $order['order_number'],
    'product_category' => 'Digital Goods',
    'product_profile'  => 'general',

    'value_a' => $orderId, // custom field to recover order id in callbacks
];

$ch = curl_init(SSLCZ_API_URL);
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query($postData),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_TIMEOUT => 20,
]);
$response = curl_exec($ch);
$curlErr = curl_error($ch);
curl_close($ch);

if ($curlErr) fail('Could not reach the payment gateway: ' . $curlErr, 502);

$result = json_decode($response, true);
if (!$result || ($result['status'] ?? '') !== 'SUCCESS') {
    fail('Payment gateway rejected the request: ' . ($result['failedreason'] ?? 'Unknown error.'), 502);
}

respond(['gateway_url' => $result['GatewayPageURL'], 'tran_id' => $tranId]);
