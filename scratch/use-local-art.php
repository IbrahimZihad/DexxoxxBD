<?php
declare(strict_types=1);

/**
 * One-off: point the seeder (and the live database) at the local product artwork
 * instead of remote photos, which the page CSP blocks. Run once, then delete.
 */

$local = [
    'netflix-premium-1-month',
    'netflix-standard-1-month',
    'amazon-prime-video-1-month',
    'spotify-premium-1-month',
    'youtube-premium-1-month',
    'canva-pro-1-month',
    'chatgpt-plus-1-month',
    'microsoft-365-personal-12-months',
    'playstation-plus-essential-1-month',
    'xbox-game-pass-ultimate-1-month',
    'linkedin-premium-career-1-month',
    'coursera-plus-3-months',
];

$file = dirname(__DIR__) . '/database/seeds/products.php';
$src = file_get_contents($file);

$index = 0;
$out = preg_replace_callback(
    "#'image_url' => 'https://images\.unsplash\.com/[^']*'#",
    static function (array $m) use ($local, &$index): string {
        if (!isset($local[$index])) {
            throw new RuntimeException('more image_url rows than mappings');
        }

        $slug = $local[$index];
        $index++;

        return "'image_url' => '/assets/img/products/{$slug}.svg'";
    },
    $src
);

if ($out === null || $index !== count($local)) {
    fwrite(STDERR, "SEEDER PATCH FAILED (replaced {$index})\n");
    exit(1);
}

file_put_contents($file, $out);
echo 'seeder updated: ', $index, ' rows; remote urls left = ', substr_count($out, 'unsplash'), PHP_EOL;

$pdo = new PDO('mysql:host=127.0.0.1;port=3307;dbname=dexxoxxbd;charset=utf8mb4', 'root', '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

$statement = $pdo->prepare('UPDATE products SET image_url = ? WHERE slug = ?');

foreach ($local as $slug) {
    $statement->execute(['/assets/img/products/' . $slug . '.svg', $slug]);
    echo str_pad($slug, 36), ' changed=', $statement->rowCount(), PHP_EOL;
}

$count = (int) $pdo->query("SELECT COUNT(*) FROM products WHERE image_url LIKE 'https://images%'")->fetchColumn();
echo 'remote image_url rows still in db = ', $count, PHP_EOL;
echo 'sample = ', $pdo->query("SELECT image_url FROM products WHERE slug='spotify-premium-1-month'")->fetchColumn(), PHP_EOL;
