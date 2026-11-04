<?php
declare(strict_types=1);

// Test-only JSON formatter: reads stdin, prints compact-ish tree with dotted paths.
$raw = stream_get_contents(STDIN);
// Tolerate the "[METHOD path] -> 200" status lines the shell helpers print.
$from = strpos($raw, '{');
$to = strrpos($raw, '}');
if ($from !== false && $to !== false && $to > $from) {
    $raw = substr($raw, $from, $to - $from + 1);
}
$data = json_decode($raw, true);
if (!is_array($data)) {
    fwrite(STDERR, "not-json: " . substr($raw, 0, 400) . "\n");
    exit(1);
}

$want = $argv[1] ?? '';
if ($want !== '') {
    foreach (explode('.', $want) as $seg) {
        if (is_array($data) && array_key_exists($seg, $data)) {
            $data = $data[$seg];
            continue;
        }
        if (is_array($data) && ctype_digit($seg) && array_key_exists((int) $seg, $data)) {
            $data = $data[(int) $seg];
            continue;
        }
        fwrite(STDERR, "missing path segment: $seg\n");
        exit(2);
    }
}

echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
