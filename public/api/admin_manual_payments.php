<?php
require_once __DIR__ . '/../../config/config.php';
$db = get_db();
$m  = method();
require_admin();

if ($m === 'GET') {
    // List all manual payments (optionally filter by status)
    $status = $_GET['status'] ?? '';
    $where  = '1=1';
    $params = [];
    if ($status && in_array($status, ['pending','approved','rejected'], true)) {
        $where    = 'mp.status = ?';
        $params[] = $status;
    }

    $stmt = $db->prepare("
        SELECT mp.*,
               o.order_number, o.total_amount,
               u.name AS user_name, u.email AS user_email
        FROM manual_payments mp
        JOIN orders o ON o.id = mp.order_id
        JOIN users  u ON u.id = o.user_id
        WHERE $where
        ORDER BY mp.created_at DESC
        LIMIT 300
    ");
    $stmt->execute($params);
    respond(['payments' => $stmt->fetchAll()]);
}

if ($m === 'PUT') {
    // Approve or reject a manual payment
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) fail('Missing payment id.');

    $in        = json_input();
    $action    = $in['action']     ?? '';   // 'approve' | 'reject'
    $adminNote = trim($in['admin_note'] ?? '');

    if (!in_array($action, ['approve','reject'], true)) fail('Invalid action. Use "approve" or "reject".');

    $stmt = $db->prepare('SELECT * FROM manual_payments WHERE id = ?');
    $stmt->execute([$id]);
    $payment = $stmt->fetch();
    if (!$payment) fail('Payment record not found.', 404);
    if ($payment['status'] !== 'pending') fail('This payment has already been reviewed.');

    $newStatus = $action === 'approve' ? 'approved' : 'rejected';

    $db->beginTransaction();
    try {
        $db->prepare(
            'UPDATE manual_payments SET status = ?, admin_note = ?, reviewed_at = NOW() WHERE id = ?'
        )->execute([$newStatus, $adminNote ?: null, $id]);

        if ($action === 'approve') {
            // grant_passes_for_order marks order as paid AND issues passes
            grant_passes_for_order($db, $payment['order_id']);
        } else {
            // Mark order as failed
            $db->prepare('UPDATE orders SET status = "failed" WHERE id = ?')
               ->execute([$payment['order_id']]);
        }

        $db->commit();
        respond(['ok' => true, 'message' => 'Payment ' . $newStatus . '.']);
    } catch (Exception $e) {
        $db->rollBack();
        fail('Could not process action: ' . $e->getMessage(), 500);
    }
}

fail('Method not allowed.', 405);
