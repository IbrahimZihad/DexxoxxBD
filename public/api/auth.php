<?php
require_once __DIR__ . '/../../config/config.php';

$action = $_GET['action'] ?? '';
$db = get_db();

switch ($action) {

    case 'register': {
        if (method() !== 'POST') fail('Method not allowed.', 405);
        $in = json_input();
        $name = trim($in['name'] ?? '');
        $email = strtolower(trim($in['email'] ?? ''));
        $password = $in['password'] ?? '';
        $phone = trim($in['phone'] ?? '');

        if ($name === '' || $email === '' || strlen($password) < 6) {
            fail('Name, a valid email, and a password of at least 6 characters are required.');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) fail('Please enter a valid email address.');

        $stmt = $db->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->execute([$email]);
        if ($stmt->fetch()) fail('An account with this email already exists.', 409);

        $hash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $db->prepare('INSERT INTO users (name, email, password_hash, phone) VALUES (?, ?, ?, ?)');
        $stmt->execute([$name, $email, $hash, $phone ?: null]);

        $_SESSION['user_id'] = (int)$db->lastInsertId();
        respond(['user' => current_user()], 201);
    }

    case 'login': {
        if (method() !== 'POST') fail('Method not allowed.', 405);
        $in = json_input();
        $email = strtolower(trim($in['email'] ?? ''));
        $password = $in['password'] ?? '';

        $stmt = $db->prepare('SELECT * FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            fail('Incorrect email or password.', 401);
        }
        if ($user['status'] !== 'active') fail('This account has been suspended.', 403);

        $_SESSION['user_id'] = (int)$user['id'];
        unset($user['password_hash']);
        respond(['user' => $user]);
    }

    case 'logout': {
        $_SESSION = [];
        session_destroy();
        respond(['ok' => true]);
    }

    case 'me': {
        $user = current_user();
        if (!$user) fail('Not logged in.', 401);
        respond(['user' => $user]);
    }

    default:
        fail('Unknown auth action.', 404);
}
