<?php
require_once __DIR__ . '/../../config/config.php';
$db = get_db();
$m = method();

if ($m === 'GET') {
    if (!empty($_GET['id']) || !empty($_GET['slug'])) {
        if (!empty($_GET['id'])) {
            $stmt = $db->prepare('SELECT p.*, c.name AS category_name FROM products p LEFT JOIN categories c ON c.id = p.category_id WHERE p.id = ?');
            $stmt->execute([(int)$_GET['id']]);
        } else {
            $stmt = $db->prepare('SELECT p.*, c.name AS category_name FROM products p LEFT JOIN categories c ON c.id = p.category_id WHERE p.slug = ?');
            $stmt->execute([$_GET['slug']]);
        }
        $product = $stmt->fetch();
        if (!$product) fail('Product not found.', 404);
        respond(['product' => $product]);
    }

    // List with optional filters: category, search, featured
    $where = ['1=1'];
    $params = [];
    $isAdmin = current_user() && current_user()['role'] === 'admin';
    if (!$isAdmin) $where[] = "p.status = 'active'";

    if (!empty($_GET['category'])) { $where[] = 'c.slug = ?'; $params[] = $_GET['category']; }
    if (!empty($_GET['q'])) { $where[] = '(p.name LIKE ? OR p.description LIKE ?)'; $params[] = '%' . $_GET['q'] . '%'; $params[] = '%' . $_GET['q'] . '%'; }
    if (!empty($_GET['featured'])) { $where[] = 'p.is_featured = 1'; }

    $sql = 'SELECT p.*, c.name AS category_name, c.slug AS category_slug FROM products p
            LEFT JOIN categories c ON c.id = p.category_id
            WHERE ' . implode(' AND ', $where) . ' ORDER BY p.created_at DESC';
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    respond(['products' => $stmt->fetchAll()]);
}

if ($m === 'POST') {
    require_admin();
    $in = json_input();
    $name = trim($in['name'] ?? '');
    $price = $in['price'] ?? null;
    if ($name === '' || $price === null) fail('Name and price are required.');

    $slug = slugify($name) . '-' . substr(bin2hex(random_bytes(2)), 0, 4);
    $stmt = $db->prepare('INSERT INTO products (category_id, name, slug, description, image_url, price, compare_at_price, duration_days, stock, status, is_featured)
        VALUES (?,?,?,?,?,?,?,?,?,?,?)');
    $stmt->execute([
        $in['category_id'] ?? null, $name, $slug, $in['description'] ?? '', $in['image_url'] ?? null,
        $price, $in['compare_at_price'] ?? null, $in['duration_days'] ?? 30, $in['stock'] ?? 999,
        $in['status'] ?? 'active', !empty($in['is_featured']) ? 1 : 0,
    ]);
    respond(['id' => (int)$db->lastInsertId()], 201);
}

if ($m === 'PUT') {
    require_admin();
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) fail('Missing product id.');
    $in = json_input();

    $fields = ['category_id','name','description','image_url','price','compare_at_price','duration_days','stock','status','is_featured'];
    $sets = []; $params = [];
    foreach ($fields as $f) {
        if (array_key_exists($f, $in)) { $sets[] = "$f = ?"; $params[] = $in[$f]; }
    }
    if (!$sets) fail('Nothing to update.');
    $params[] = $id;
    $stmt = $db->prepare('UPDATE products SET ' . implode(', ', $sets) . ' WHERE id = ?');
    $stmt->execute($params);
    respond(['ok' => true]);
}

if ($m === 'DELETE') {
    require_admin();
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) fail('Missing product id.');
    $db->prepare('DELETE FROM products WHERE id = ?')->execute([$id]);
    respond(['ok' => true]);
}

fail('Method not allowed.', 405);
