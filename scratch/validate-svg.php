<?php
// Temp validator for the generated SVG assets. Deleted right after the run.
$dir = dirname(__DIR__) . '/public/assets/img/products';
$bad = 0;

foreach (glob($dir . '/*.svg') as $file) {
    $xml = @simplexml_load_file($file);
    $src = file_get_contents($file);

    if ($xml === false || !str_contains($src, 'viewBox="0 0 800 500"')) {
        $bad++;
        echo 'INVALID ', basename($file), PHP_EOL;
    }
}

echo 'files=', count(glob($dir . '/*.svg')), ' invalid=', $bad, PHP_EOL;
echo 'sample:', PHP_EOL, file_get_contents($dir . '/spotify-premium-1-month.svg'), PHP_EOL;
