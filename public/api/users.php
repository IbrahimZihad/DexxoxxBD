<?php
require_once __DIR__ . '/../../config/config.php';
$db = get_db();
$m = method();
require_admin();

if ($m === 'GET') {
    $stmt = $db->query('SELECT id, name, email, phone, role, status, created_at FROM users ORDER BY created_at DESC');
    respond(['users' => $stmt->fetchAll()]);
}

if ($m === 'PUT') {
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) fail('Missing user id.');
    $in = json_input();

    $sets = []; $params = [];
    if (isset($in['role']) && in_array($in['role'], ['customer', 'admin'], true)) { $sets[] = 'role = ?'; $params[] = $in['role']; }
    if (isset($in['status']) && in_array($in['status'], ['active', 'suspended'], true)) { $sets[] = 'status = ?'; $params[] = $in['status']; }
    if (!$sets) fail('Nothing to update.');

    $params[] = $id;
    $db->prepare('UPDATE users SET ' . implode(', ', $sets) . ' WHERE id = ?')->execute($params);
    respond(['ok' => true]);
}

fail('Method not allowed.', 405);
