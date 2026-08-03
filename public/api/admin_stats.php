<?php
require_once __DIR__ . '/../../config/config.php';
$db = get_db();
require_admin();

$stats = [];
$stats['total_revenue'] = (float)$db->query("SELECT COALESCE(SUM(total_amount),0) FROM orders WHERE status = 'paid'")->fetchColumn();
$stats['total_orders'] = (int)$db->query('SELECT COUNT(*) FROM orders')->fetchColumn();
$stats['pending_orders'] = (int)$db->query("SELECT COUNT(*) FROM orders WHERE status = 'pending'")->fetchColumn();
$stats['total_customers'] = (int)$db->query("SELECT COUNT(*) FROM users WHERE role = 'customer'")->fetchColumn();
$stats['total_products'] = (int)$db->query('SELECT COUNT(*) FROM products')->fetchColumn();
$stats['total_plans'] = (int)$db->query('SELECT COUNT(*) FROM plans')->fetchColumn();
$stats['active_passes'] = (int)$db->query("SELECT COUNT(*) FROM user_subscriptions WHERE status = 'active' AND ends_at >= CURDATE()")->fetchColumn();
$stats['pending_manual_payments'] = (int)$db->query("SELECT COUNT(*) FROM manual_payments WHERE status = 'pending'")->fetchColumn();

$recent = $db->query('SELECT o.id, o.order_number, o.total_amount, o.status, o.created_at, u.name AS user_name
    FROM orders o JOIN users u ON u.id = o.user_id ORDER BY o.created_at DESC LIMIT 8')->fetchAll();
$stats['recent_orders'] = $recent;

respond($stats);
