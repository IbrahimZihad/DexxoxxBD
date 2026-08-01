<?php
require_once __DIR__ . '/../../config/config.php';
$db = get_db();
$user = require_login();

if (method() !== 'GET') fail('Method not allowed.', 405);

$stmt = $db->prepare("
    UPDATE user_subscriptions SET status = 'expired'
    WHERE user_id = ? AND status = 'active' AND ends_at < CURDATE()
");
$stmt->execute([$user['id']]);

$stmt = $db->prepare('SELECT * FROM user_subscriptions WHERE user_id = ? ORDER BY created_at DESC');
$stmt->execute([$user['id']]);
respond(['passes' => $stmt->fetchAll()]);
