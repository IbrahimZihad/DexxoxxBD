<?php
/**
 * Database connection (PDO / MySQL)
 */

define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('DB_NAME') ?: 'tarbdm_subpass');
define('DB_USER', getenv('DB_USER') ?: 'tarbdm_subpass');
define('DB_PASS', getenv('DB_PASS') ?: 'gJ1hTa@h8GaL#6agPfQ');

function get_db(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";

            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $e) {
            http_response_code(500);

            // Temporarily enable this for debugging:
            // die($e->getMessage());

            die(json_encode([
                'error' => 'Database connection failed.'
            ]));
        }
    }

    return $pdo;
}
