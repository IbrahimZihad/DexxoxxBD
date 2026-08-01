<?php
require_once __DIR__ . '/../../../config/config.php';
$db = get_db();

$tranId = $_POST['tran_id'] ?? '';
$valId  = $_POST['val_id'] ?? '';
$status = $_POST['status'] ?? '';

if (!$tranId || !$valId || $status !== 'VALID') {
    http_response_code(200); // acknowledge receipt regardless, per SSLCommerz spec
    exit;
}

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

if ($result && in_array($result['status'] ?? '', ['VALID', 'VALIDATED'], true)) {
    $paymentStmt = $db->prepare('SELECT * FROM payments WHERE tran_id = ?');
    $paymentStmt->execute([$tranId]);
    $payment = $paymentStmt->fetch();
    if ($payment) {
        $db->prepare('UPDATE payments SET status = "valid", val_id = ?, raw_response = ? WHERE id = ?')
           ->execute([$valId, json_encode($result), $payment['id']]);
        grant_passes_for_order($db, (int)$payment['order_id']);
    }
}

http_response_code(200);
