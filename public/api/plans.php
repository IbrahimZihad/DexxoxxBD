<?php
require_once __DIR__ . '/../../config/config.php';
$db = get_db();
$m = method();

if ($m === 'GET') {
    if (!empty($_GET['id']) || !empty($_GET['slug'])) {
        if (!empty($_GET['id'])) {
            $stmt = $db->prepare('SELECT * FROM plans WHERE id = ?');
            $stmt->execute([(int)$_GET['id']]);
        } else {
            $stmt = $db->prepare('SELECT * FROM plans WHERE slug = ?');
            $stmt->execute([$_GET['slug']]);
        }
        $plan = $stmt->fetch();
        if (!$plan) fail('Plan not found.', 404);
        $plan['features'] = json_decode($plan['features'] ?? '[]', true);
        respond(['plan' => $plan]);
    }

    $isAdmin = current_user() && current_user()['role'] === 'admin';
    $sql = 'SELECT * FROM plans' . ($isAdmin ? '' : " WHERE status = 'active'") . ' ORDER BY sort_order ASC';
    $rows = $db->query($sql)->fetchAll();
    foreach ($rows as &$r) $r['features'] = json_decode($r['features'] ?? '[]', true);
    respond(['plans' => $rows]);
}

if ($m === 'POST') {
    require_admin();
    $in = json_input();
    $name = trim($in['name'] ?? '');
    $price = $in['price'] ?? null;
    $duration = $in['duration_days'] ?? null;
    if ($name === '' || $price === null || !$duration) fail('Name, price, and duration are required.');

    $slug = slugify($name) . '-' . substr(bin2hex(random_bytes(2)), 0, 4);
    $stmt = $db->prepare('INSERT INTO plans (name, slug, description, price, duration_days, features, badge, status, sort_order)
        VALUES (?,?,?,?,?,?,?,?,?)');
    $stmt->execute([
        $name, $slug, $in['description'] ?? '', $price, $duration,
        json_encode($in['features'] ?? []), $in['badge'] ?? null,
        $in['status'] ?? 'active', $in['sort_order'] ?? 0,
    ]);
    respond(['id' => (int)$db->lastInsertId()], 201);
}

if ($m === 'PUT') {
    require_admin();
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) fail('Missing plan id.');
    $in = json_input();

    $fields = ['name','description','price','duration_days','badge','status','sort_order'];
    $sets = []; $params = [];
    foreach ($fields as $f) {
        if (array_key_exists($f, $in)) { $sets[] = "$f = ?"; $params[] = $in[$f]; }
    }
    if (array_key_exists('features', $in)) { $sets[] = 'features = ?'; $params[] = json_encode($in['features']); }
    if (!$sets) fail('Nothing to update.');
    $params[] = $id;
    $stmt = $db->prepare('UPDATE plans SET ' . implode(', ', $sets) . ' WHERE id = ?');
    $stmt->execute($params);
    respond(['ok' => true]);
}

if ($m === 'DELETE') {
    require_admin();
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) fail('Missing plan id.');
    $db->prepare('DELETE FROM plans WHERE id = ?')->execute([$id]);
    respond(['ok' => true]);
}

fail('Method not allowed.', 405);
