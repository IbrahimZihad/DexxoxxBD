<?php
declare(strict_types=1);

/**
 * One-off asset generator: writes the local product artwork that the seeder points at.
 * Run once, then delete this script — the SVGs are committed assets.
 *   F:/Xamp/php/php.exe scratch/make-product-art.php
 */

$outDir = dirname(__DIR__) . '/public/assets/img/products';

if (!is_dir($outDir)) {
    mkdir($outDir, 0775, true);
}

/** slug => [monogram, category motif, from colour, to colour, accent] */
$art = [
    'netflix-premium-1-month'              => ['N',  'play',  '#2b0f14', '#7a1020', '#e50914'],
    'netflix-standard-1-month'             => ['N',  'play',  '#200a12', '#5c0f22', '#f25c6c'],
    'amazon-prime-video-1-month'           => ['P',  'play',  '#0b1a2b', '#14406b', '#37c2f3'],
    'spotify-premium-1-month'              => ['S',  'bars',  '#07231b', '#0d5c3a', '#1ed760'],
    'youtube-premium-1-month'              => ['Y',  'play',  '#2a0b0f', '#8a121c', '#ff4d5e'],
    'canva-pro-1-month'                    => ['C',  'grid',  '#0a1a2d', '#124a7a', '#00c4cc'],
    'chatgpt-plus-1-month'                 => ['AI', 'grid',  '#0b1616', '#123f3a', '#5df2c6'],
    'microsoft-365-personal-12-months'     => ['M',  'grid',  '#101a2e', '#1d3f77', '#4aa3ff'],
    'playstation-plus-essential-1-month'   => ['P',  'pad',   '#0a1230', '#16307c', '#4c7dff'],
    'xbox-game-pass-ultimate-1-month'      => ['X',  'pad',   '#0a1a12', '#14502a', '#6be15b'],
    'linkedin-premium-career-1-month'      => ['In', 'book',  '#0a1a2a', '#0f5f96', '#38b6ff'],
    'coursera-plus-3-months'               => ['Co', 'book',  '#0a1428', '#123a72', '#2b7cff'],
];

$motif = static function (string $kind, string $accent): string {
    return match ($kind) {
        'play' => <<<SVG
              <circle cx="560" cy="250" r="118" fill="none" stroke="$accent" stroke-opacity=".5" stroke-width="3"/>
              <path d="M534 196l100 54-100 54z" fill="$accent" fill-opacity=".82"/>
        SVG,
        'bars' => <<<SVG
              <g fill="$accent" fill-opacity=".8">
                <rect x="486" y="212" width="26" height="76" rx="13"/>
                <rect x="526" y="176" width="26" height="112" rx="13"/>
                <rect x="566" y="200" width="26" height="88" rx="13"/>
                <rect x="606" y="164" width="26" height="124" rx="13"/>
              </g>
        SVG,
        'grid' => <<<SVG
              <g fill="none" stroke="$accent" stroke-opacity=".55" stroke-width="3">
                <rect x="492" y="182" width="68" height="68" rx="16"/>
                <rect x="576" y="182" width="68" height="68" rx="16" stroke-opacity=".35"/>
                <rect x="492" y="266" width="68" height="68" rx="16" stroke-opacity=".35"/>
                <circle cx="610" cy="300" r="34" fill="$accent" fill-opacity=".6" stroke="none"/>
              </g>
        SVG,
        'pad' => <<<SVG
              <g stroke="$accent" stroke-opacity=".6" stroke-width="4" fill="none">
                <path d="M470 236q-34 0-46 34t16 44q22 10 34-14l14-26h44l14 26q12 24 34 14 28-10 16-44t-46-34z"/>
                <path d="M520 250v34M503 267h34"/>
              </g>
              <g fill="$accent" fill-opacity=".75"><circle cx="606" cy="252" r="9"/><circle cx="628" cy="274" r="9"/></g>
        SVG,
        'book' => <<<SVG
              <g fill="none" stroke="$accent" stroke-opacity=".6" stroke-width="4" stroke-linecap="round">
                <path d="M482 300q52-42 104-10 52-32 104 10"/>
                <path d="M482 254q52-42 104-10 52-32 104 10" stroke-opacity=".38"/>
                <path d="M482 208q52-42 104-10 52-32 104 10" stroke-opacity=".2"/>
              </g>
        SVG,
        default => '',
    };
};

$written = [];

foreach ($art as $slug => [$mono, $kind, $from, $to, $accent]) {
    $svg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 800 500" width="800" height="500" role="img" aria-hidden="true">
  <defs>
    <linearGradient id="bg" x1="0" y1="0" x2="1" y2="1">
      <stop offset="0" stop-color="$from"/>
      <stop offset="1" stop-color="$to"/>
    </linearGradient>
    <radialGradient id="glow" cx="0.2" cy="0.15" r="0.9">
      <stop offset="0" stop-color="$accent" stop-opacity=".38"/>
      <stop offset="1" stop-color="$accent" stop-opacity="0"/>
    </radialGradient>
  </defs>
  <rect width="800" height="500" fill="url(#bg)"/>
  <rect width="800" height="500" fill="url(#glow)"/>
  <g opacity=".9">
    <text x="64" y="352" font-family="Segoe UI, Helvetica, Arial, sans-serif" font-size="228" font-weight="700" fill="#ffffff" fill-opacity=".14">$mono</text>
  </g>
  {$motif($kind, $accent)}
  <g stroke="#ffffff" stroke-opacity=".1" stroke-width="2">
    <path d="M0 440h800M0 468h800"/>
  </g>
</svg>
SVG;

    $path = $outDir . '/' . $slug . '.svg';
    file_put_contents($path, $svg . "\n");
    $written[] = $slug . '.svg';
}

echo 'wrote ' . count($written) . ' files to ' . $outDir . PHP_EOL;
echo implode(PHP_EOL, $written) . PHP_EOL;
