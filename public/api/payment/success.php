<?php
require_once __DIR__ . '/../../../config/config.php';
$db = get_db();

// SSLCommerz posts these fields to the success_url
$tranId = $_POST['tran_id'] ?? '';
$valId  = $_POST['val_id'] ?? '';
$amount = $_POST['amount'] ?? '';
$orderId = (int)($_POST['value_a'] ?? 0);

$frontendBase = FRONTEND_URL;

if (!$tranId || !$valId) {
    header('Location: ' . rtrim($frontendBase, '/') . '/checkout.html?payment=fail');
    exit;
}

// Validate with SSLCommerz to prevent spoofed callbacks
$query = http_build_query([
    'val_id' => $valId,
    'store_id' => SSLCZ_STORE_ID,
    'store_passwd' => SSLCZ_STORE_PASSWORD,
    'format' => 'json',
]);
$ch = curl_init(SSLCZ_VALIDATION_URL . '?' . $query);
curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_SSL_VERIFYPEER => true, CURLOPT_TIMEOUT => 20]);
$response = curl_exec($ch);
curl_close($ch);
$result = json_decode($response, true);

$paymentStmt = $db->prepare('SELECT * FROM payments WHERE tran_id = ?');
$paymentStmt->execute([$tranId]);
$payment = $paymentStmt->fetch();

$isValid = $result && in_array($result['status'] ?? '', ['VALID', 'VALIDATED'], true);

if ($payment && $isValid) {
    $db->prepare('UPDATE payments SET status = "valid", val_id = ?, card_type = ?, bank_tran_id = ?, raw_response = ? WHERE id = ?')
       ->execute([
           $valId,
           $result['card_type'] ?? null,
           $result['bank_tran_id'] ?? null,
           json_encode($result),
           $payment['id'],
       ]);

    grant_passes_for_order($db, (int)$payment['order_id']);

    header('Location: ' . rtrim($frontendBase, '/') . '/dashboard.html?payment=success&order=' . $payment['order_id']);
    exit;
}

if ($payment) {
    $db->prepare('UPDATE payments SET status = "failed", raw_response = ? WHERE id = ?')
       ->execute([json_encode($result ?: []), $payment['id']]);
}
header('Location: ' . rtrim($frontendBase, '/') . '/checkout.html?payment=fail');
exit;
