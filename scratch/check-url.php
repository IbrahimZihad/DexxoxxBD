<?php
declare(strict_types=1);

// Temp check for Sanitizer::url(). Deleted right after the run.
require __DIR__ . '/../src/Security/Sanitizer.php';

use App\Security\Sanitizer;

$cases = [
    '/assets/img/products/netflix-premium-1-month.svg',
    '/',
    '//evil.example/x.svg',
    "/assets/../secret.php",
    '/assets/img/a b.svg',
    '/assets/img/a"onload="alert(1).svg',
    "javascript:alert(1)",
    'data:image/svg+xml;base64,AAAA',
    'ftp://example.com/a.png',
    'https://example.com/a.png',
    'http://localhost:8123/assets/a.svg',
    '   ',
    str_repeat('/a', 300),
];

foreach ($cases as $case) {
    $out = Sanitizer::url($case);
    printf("%-52s => %s\n", substr(str_replace("\n", '\n', $case), 0, 52), $out === null ? 'NULL' : $out);
}
