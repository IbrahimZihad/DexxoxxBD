<?php

declare(strict_types=1);

/**
 * One-off migration: Blade-style {{-- ... --}} comments were written into plain
 * PHP views, so they print as literal text. Replace each with a real comment,
 * choosing the form from whether the offset sits inside or outside a PHP block.
 */

$root = dirname(__DIR__);

$files = [];

foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/resources/views')) as $spot) {
    if ($spot->isFile() && $spot->getExtension() === 'php') {
        $files[] = $spot->getPathname();
    }
}

sort($files);

/** One char per byte: H while printing HTML, P while inside <?php ... ?>. */
$modes = static function (string $source): string {
    $map = str_repeat('H', strlen($source));
    $inPhp = false;
    $length = strlen($source);
    $i = 0;

    while ($i < $length) {
        if (!$inPhp && $i + 1 < $length && $source[$i] === '<' && $source[$i + 1] === '?') {
            $inPhp = true;
            $i += 2;

            continue;
        }

        if ($inPhp && $i + 1 < $length && $source[$i] === '?' && $source[$i + 1] === '>') {
            $inPhp = false;
            $i += 2;

            continue;
        }

        $map[$i] = $inPhp ? 'P' : 'H';
        $i++;
    }

    return $map;
};

$changedFiles = 0;
$changedTotal = 0;
$problems = [];

foreach ($files as $file) {
    $source = (string) file_get_contents($file);

    if (!str_contains($source, '{{--')) {
        continue;
    }

    $map = $modes($source);

    if (preg_match_all('/\{\{--(.*?)--\}\}/s', $source, $found, PREG_SET_ORDER | PREG_OFFSET_CAPTURE) === false) {
        $problems[] = $file . ' (scan failed)';
        continue;
    }

    $output = '';
    $cursor = 0;

    foreach ($found as $match) {
        $start = $match[0][1];
        $full = $match[0][0];
        $body = $match[1][0];

        if (str_contains($body, '*/') || str_contains($body, '?>')) {
            $problems[] = $file . ' (body carries a comment terminator)';
            continue;
        }

        $output .= substr($source, $cursor, $start - $cursor);
        $cursor = $start + strlen($full);

        $output .= $map[$start] === 'P'
            ? '/*' . rtrim($body) . '*/'
            : '<?php /*' . rtrim($body) . '*/ ?>';
    }

    $output .= substr($source, $cursor);

    if ($output !== $source) {
        file_put_contents($file, $output);
        $changedFiles++;
        $changedTotal += count($found);
    }
}

fwrite(STDERR, "files_changed=$changedFiles comments_replaced=$changedTotal problems=" . count($problems) . PHP_EOL);

foreach ($problems as $problem) {
    fwrite(STDERR, '  ! ' . $problem . PHP_EOL);
}
