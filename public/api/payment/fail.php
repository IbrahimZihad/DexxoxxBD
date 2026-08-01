<?php
require_once __DIR__ . '/../../../config/config.php';
$db = get_db();

$tranId = $_POST['tran_id'] ?? $_GET['tran_id'] ?? '';
if ($tranId) {
    $db->prepare('UPDATE payments SET status = "failed" WHERE tran_id = ?')->execute([$tranId]);
}

$frontendBase = FRONTEND_URL;
header('Location: ' . rtrim($frontendBase, '/') . '/checkout.html?payment=fail');
exit;
