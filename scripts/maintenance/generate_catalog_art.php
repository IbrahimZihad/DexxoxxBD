<?php

/**
 * Catalogue artwork generator.
 *
 * Draws flat, original inline-SVG artwork for the catalogue and writes it at the
 * paths the database already stores, relative to public/assets/img:
 * products/{slug}.svg for published products, brands/{slug}.svg for active
 * brands, categories/{slug}.svg for root categories, banners/{file}.svg for the
 * campaign banners the CMS points at, plus a neutral placeholder.svg for
 * anything that has no artwork yet.
 *
 * Every shape comes from a Park-Miller LCG seeded with sha256(slug) - never from
 * mt_rand(), random_int(), time() or uniqid() - because these files are build
 * artefacts: a second run must write byte-identical bytes, otherwise every rerun
 * shows sixty changed images in a diff for no visual reason.
 *
 * Usage: php scripts/maintenance/generate_catalog_art.php [--quiet]
 */

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$quiet = in_array('--quiet', array_slice($argv ?? [], 1), true);

/** @var \App\Core\Database $db */
$db = require $root . '/bootstrap/database.php';

$imgRoot = $root . '/public/assets/img';
$font = 'Arial, Helvetica, sans-serif';

/**
 * The whole brand palette; nothing below draws a colour outside these nine hexes.
 *
 * @var array<string, string> $palette
 */
$palette = [
    'ink' => '#08201d',
    'deep' => '#0b5d50',
    'mid' => '#12897a',
    'light' => '#6fd3c2',
    'mango' => '#f4a11a',
    'ember' => '#c76a12',
    'panel' => '#f4f7f5',
    'line' => '#d7e2dd',
    'white' => '#ffffff',
];

$escape = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');

/**
 * Slug-seeded integer picker. Lehmer/Park-Miller modulo 2^31-1 is exactly
 * reproducible on every PHP build, which the platform RNGs are not.
 *
 * @return \Closure(int): int
 */
$rng = static function (string $seedText): \Closure {
    $state = ((int) hexdec(substr(hash('sha256', $seedText), 0, 8))) % 2147483646 + 1;

    return static function (int $bound) use (&$state): int {
        $state = ($state * 16807) % 2147483647;

        return $bound > 0 ? $state % $bound : 0;
    };
};

/**
 * Two-letter monogram: one letter from each of the first two words, or the first
 * two letters of a one-word name - "Nexora Assistant Pro" is "NA", "Vaultic" is
 * "VA".
 */
$monogram = static function (string $name): string {
    /** @var list<string> $words */
    $words = preg_split('/[^\p{L}\p{N}]+/u', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    if (count($words) < 2) {
        return mb_strtoupper(mb_substr($words[0] ?? 'DX', 0, 2));
    }

    return mb_strtoupper(mb_substr($words[0], 0, 1)) . mb_strtoupper(mb_substr($words[1], 0, 1));
};

/**
 * Wrap shapes in an accessible document: role="img" plus a <title> that
 * aria-labelledby resolves to, so the tile is announced as a named image and
 * never as raw geometry.
 *
 * @param list<string> $body
 */
$svgDocument = static function (
    int $width,
    int $height,
    string $titleId,
    string $title,
    array $body
) use ($escape): string {
    return implode("\n", [
        '<?xml version="1.0" encoding="UTF-8"?>',
        sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" width="%d" height="%d" viewBox="0 0 %d %d"'
            . ' role="img" aria-labelledby="%s">',
            $width,
            $height,
            $width,
            $height,
            $escape($titleId)
        ),
        sprintf('<title id="%s">%s</title>', $escape($titleId), $escape($title)),
        implode("\n", $body),
        '</svg>',
    ]) . "\n";
};

/**
 * The seeded motif: rings, arcs, chevrons, dots or bars drawn around the origin,
 * so the caller only decides where the cluster sits and how wide it may grow.
 *
 * @param list<string> $colors
 */
$motifGroup = static function (
    \Closure $roll,
    array $colors,
    int $span
): string {
    $colorCount = count($colors);
    $shapes = [];
    $variant = $roll(5);

    if ($variant === 0) {
        $count = 3 + $roll(3);
        $step = intdiv($span, $count);
        for ($i = 0; $i < $count; $i++) {
            $shapes[] = sprintf(
                '<circle cx="0" cy="0" r="%d" fill="none" stroke="%s" stroke-width="%d"/>',
                max(14, $span - $i * $step - $roll(6)),
                $colors[$roll($colorCount)],
                4 + $roll(9)
            );
        }
        $shapes[] = sprintf(
            '<circle cx="0" cy="0" r="%d" fill="%s"/>',
            8 + $roll(14),
            $colors[$roll($colorCount)]
        );
    } elseif ($variant === 1) {
        $count = 3 + $roll(3);
        for ($i = 0; $i < $count; $i++) {
            $radius = (int) round($span * (0.45 + 0.16 * $i));
            $shapes[] = sprintf(
                '<path d="M -%d 0 A %d %d 0 0 1 %d 0" transform="rotate(%d)" fill="none" stroke="%s"'
                . ' stroke-width="%d" stroke-linecap="round"/>',
                $radius,
                $radius,
                $radius,
                $radius,
                $roll(360),
                $colors[$roll($colorCount)],
                5 + $roll(9)
            );
        }
    } elseif ($variant === 2) {
        $count = 3 + $roll(3);
        $gap = (int) round(2 * $span / $count);
        for ($i = 0; $i < $count; $i++) {
            $y = -$span + $gap * $i + intdiv($gap, 2);
            $half = (int) round($span * (0.6 + 0.08 * $roll(5)));
            $shapes[] = sprintf(
                '<path d="M -%d %d L 0 %d L %d %d" fill="none" stroke="%s" stroke-width="%d"'
                . ' stroke-linecap="round" stroke-linejoin="round"/>',
                $half,
                $y - 20,
                $y + 20,
                $half,
                $y - 20,
                $colors[$roll($colorCount)],
                6 + $roll(8)
            );
        }
    } elseif ($variant === 3) {
        $columns = 4;
        $rows = 3;
        $halfHeight = intdiv($span, 2);
        for ($row = 0; $row < $rows; $row++) {
            for ($column = 0; $column < $columns; $column++) {
                $x = -$span + (int) round($column * 2 * $span / ($columns - 1));
                $y = -$halfHeight + (int) round($row * $span / ($rows - 1));
                $radius = 9 + $roll(13);
                $fill = $colors[$roll($colorCount)];
                $shapes[] = $roll(5) > 0
                    ? sprintf('<circle cx="%d" cy="%d" r="%d" fill="%s"/>', $x, $y, $radius, $fill)
                    : sprintf(
                        '<circle cx="%d" cy="%d" r="%d" fill="none" stroke="%s" stroke-width="%d"/>',
                        $x,
                        $y,
                        $radius,
                        $fill,
                        3 + $roll(6)
                    );
            }
        }
    } else {
        $count = 5 + $roll(3);
        $barWidth = max(10, intdiv(2 * $span, $count * 2));
        for ($i = 0; $i < $count; $i++) {
            $barHeight = (int) round($span * (0.5 + 0.14 * $roll(7)));
            $shapes[] = sprintf(
                '<rect x="%d" y="%d" width="%d" height="%d" rx="%d" fill="%s"/>',
                -$span + (int) round($i * 2 * $span / $count),
                $span - $barHeight,
                $barWidth,
                $barHeight,
                intdiv($barWidth, 2),
                $colors[$roll($colorCount)]
            );
        }
    }

    return implode("\n", $shapes);
};

/**
 * One 800x600 product or category tile: soft panel, seeded motif on the right,
 * monogram and kind label on the left.
 */
$tileSvg = static function (
    string $slug,
    string $name,
    string $noun,
    int $width,
    int $height
) use (
    $rng,
    $monogram,
    $palette,
    $escape,
    $font,
    $svgDocument,
    $motifGroup
): string {
    $roll = $rng($slug . '|tile');
    $dark = $roll(4) === 0;
    $base = $dark ? $palette['ink'] : $palette['panel'];
    $frame = $dark ? $palette['deep'] : $palette['line'];
    $accent = $dark ? $palette['mango'] : $palette['ember'];
    $lettering = $dark ? $palette['light'] : $palette['deep'];
    $motifColors = $dark
        ? [$palette['mid'], $palette['light'], $palette['mango'], $palette['line']]
        : [$palette['deep'], $palette['mid'], $palette['ember'], $palette['ink']];

    $span = 128;
    $body = [];
    $body[] = sprintf(
        '<rect x="0" y="0" width="%d" height="%d" rx="28" fill="%s"/>',
        $width,
        $height,
        $base
    );
    $body[] = sprintf(
        '<rect x="20" y="20" width="%d" height="%d" rx="22" fill="none" stroke="%s" stroke-width="%d"/>',
        $width - 40,
        $height - 40,
        $frame,
        2 + $roll(3)
    );
    $body[] = sprintf(
        '<rect x="%d" y="34" width="%d" height="10" rx="5" fill="%s"/>',
        $width - 248,
        120 + 16 * $roll(6),
        $accent
    );
    $body[] = sprintf(
        '<rect x="48" y="%d" width="12" height="%d" rx="6" fill="%s"/>',
        176 + 8 * $roll(5),
        132 + 12 * $roll(8),
        $accent
    );
    $body[] = sprintf(
        '<text x="84" y="332" font-family="%s" font-size="%d" font-weight="700" letter-spacing="6"'
        . ' fill="%s">%s</text>',
        $font,
        138 + 6 * $roll(4),
        $lettering,
        $escape($monogram($name))
    );
    $body[] = sprintf(
        '<text x="86" y="390" font-family="%s" font-size="22" font-weight="600" letter-spacing="12"'
        . ' fill="%s">%s</text>',
        $font,
        $palette['mid'],
        $escape(strtoupper($noun))
    );
    $body[] = sprintf('<g transform="translate(%d,%d)">', $width - 196, intdiv($height, 2) - 14);
    $body[] = $motifGroup($roll, $motifColors, $span);
    $body[] = '</g>';
    $body[] = sprintf(
        '<circle cx="%d" cy="%d" r="%d" fill="none" stroke="%s" stroke-width="4"/>',
        $width - 58,
        $height - 58,
        16 + 2 * $roll(6),
        $frame
    );

    return $svgDocument(
        $width,
        $height,
        'tile-' . $slug,
        $name . ' ' . $noun . ' artwork',
        $body
    );
};

/**
 * One 400x400 brand mark: circular or hexagonal plate carrying the monogram, an
 * orbit rule and one seeded accent shape.
 */
$badgeSvg = static function (
    string $slug,
    string $name
) use (
    $rng,
    $monogram,
    $palette,
    $escape,
    $font,
    $svgDocument
): string {
    $roll = $rng($slug . '|brand');
    $dark = $roll(3) === 0;
    $base = $dark ? $palette['ink'] : $palette['panel'];
    $plate = $dark ? $palette['deep'] : $palette['white'];
    $ring = $dark ? $palette['mid'] : $palette['line'];
    $lettering = $dark ? $palette['light'] : $palette['deep'];
    $accent = $dark ? $palette['mango'] : $palette['ember'];

    $plateRadius = 128 + 4 * $roll(5);
    $orbit = $plateRadius + 24;
    $body = [];
    $body[] = sprintf('<rect x="0" y="0" width="400" height="400" rx="24" fill="%s"/>', $base);
    $body[] = sprintf(
        '<rect x="14" y="14" width="372" height="372" rx="18" fill="none" stroke="%s" stroke-width="2"/>',
        $ring
    );
    $body[] = sprintf(
        '<circle cx="200" cy="200" r="%d" fill="none" stroke="%s" stroke-width="2"'
        . ' stroke-dasharray="6 11"/>',
        $orbit,
        $ring
    );

    if ($roll(3) === 0) {
        $points = [];
        for ($i = 0; $i < 6; $i++) {
            $angle = deg2rad(-90 + $i * 60);
            $points[] = sprintf(
                '%d,%d',
                (int) round(200 + $plateRadius * cos($angle)),
                (int) round(200 + $plateRadius * sin($angle))
            );
        }
        $body[] = sprintf(
            '<polygon points="%s" fill="%s" stroke="%s" stroke-width="%d"/>',
            implode(' ', $points),
            $plate,
            $accent,
            4 + $roll(5)
        );
    } else {
        $body[] = sprintf(
            '<circle cx="200" cy="200" r="%d" fill="%s" stroke="%s" stroke-width="%d"/>',
            $plateRadius,
            $plate,
            $accent,
            4 + $roll(5)
        );
    }

    $accentShape = $roll(3);
    if ($accentShape === 0) {
        $start = $roll(360);
        for ($i = 0; $i < 3; $i++) {
            $angle = deg2rad($start + $i * 120);
            $body[] = sprintf(
                '<circle cx="%d" cy="%d" r="%d" fill="%s"/>',
                (int) round(200 + $orbit * cos($angle)),
                (int) round(200 + $orbit * sin($angle)),
                9 + $roll(5),
                $accent
            );
        }
    } elseif ($accentShape === 1) {
        $from = deg2rad($roll(360));
        $to = $from + deg2rad(50 + 15 * $roll(5));
        $body[] = sprintf(
            '<path d="M %d %d A %d %d 0 0 1 %d %d" fill="none" stroke="%s" stroke-width="%d"'
            . ' stroke-linecap="round"/>',
            (int) round(200 + $orbit * cos($from)),
            (int) round(200 + $orbit * sin($from)),
            $orbit,
            $orbit,
            (int) round(200 + $orbit * cos($to)),
            (int) round(200 + $orbit * sin($to)),
            $accent,
            7 + $roll(6)
        );
    } else {
        $chevronY = 92 + 8 * $roll(4);
        $body[] = sprintf(
            '<path d="M 152 %d L 200 %d L 248 %d" fill="none" stroke="%s" stroke-width="%d"'
            . ' stroke-linecap="round" stroke-linejoin="round"/>',
            $chevronY,
            $chevronY + 26,
            $chevronY,
            $accent,
            7 + $roll(5)
        );
    }

    $body[] = sprintf(
        '<text x="200" y="238" font-family="%s" font-size="%d" font-weight="700" letter-spacing="4"'
        . ' text-anchor="middle" fill="%s">%s</text>',
        $font,
        96 + 8 * $roll(3),
        $lettering,
        $escape($monogram($name))
    );

    return $svgDocument(400, 400, 'mark-' . $slug, $name . ' brand mark', $body);
};

/**
 * The 800x600 fallback tile: the same frame language as the catalogue, an
 * abstract picture glyph and no monogram, so it never claims to be an entity.
 */
$placeholderSvg = static function () use ($palette, $svgDocument): string {
    return $svgDocument(800, 600, 'placeholder', 'DexxoxxBD catalogue image placeholder', [
        sprintf('<rect x="0" y="0" width="800" height="600" rx="28" fill="%s"/>', $palette['panel']),
        sprintf(
            '<rect x="20" y="20" width="760" height="560" rx="22" fill="none" stroke="%s"'
            . ' stroke-width="3" stroke-dasharray="16 12"/>',
            $palette['line']
        ),
        sprintf(
            '<rect x="290" y="200" width="220" height="170" rx="16" fill="%s" stroke="%s" stroke-width="3"/>',
            $palette['white'],
            $palette['line']
        ),
        sprintf('<circle cx="342" cy="248" r="20" fill="%s"/>', $palette['light']),
        sprintf(
            '<path d="M 306 342 L 372 266 L 438 342" fill="none" stroke="%s" stroke-width="12"'
            . ' stroke-linecap="round" stroke-linejoin="round"/>',
            $palette['mid']
        ),
        sprintf(
            '<path d="M 388 342 L 442 288 L 496 342" fill="none" stroke="%s" stroke-width="10"'
            . ' stroke-linecap="round" stroke-linejoin="round"/>',
            $palette['deep']
        ),
        sprintf('<rect x="340" y="404" width="120" height="10" rx="5" fill="%s"/>', $palette['mango']),
    ]);
};

/**
 * One 1600x500 campaign banner: the stripe-and-rail language of the tiles
 * stretched wide, two seeded clusters on the right. The headline, subtitle and
 * button come from the banners table and render as HTML over this artwork, so
 * the SVG deliberately carries no lettering - a translated banner would otherwise
 * need its own image file.
 */
$bannerSvg = static function (string $name, string $title) use ($palette, $rng, $svgDocument, $motifGroup): string {
    $roll = $rng($name . '|banner');
    $dark = $roll(2) === 0;
    $base = $dark ? $palette['ink'] : $palette['panel'];
    $rail = $dark ? $palette['deep'] : $palette['line'];
    $motifColors = $dark
        ? [$palette['mid'], $palette['light'], $palette['mango']]
        : [$palette['deep'], $palette['mid'], $palette['ember']];

    $body = [
        sprintf('<rect x="0" y="0" width="1600" height="500" rx="34" fill="%s"/>', $base),
        sprintf('<rect x="54" y="104" width="14" height="292" rx="7" fill="%s"/>', $palette['mango']),
    ];

    for ($i = 0; $i < 3; $i++) {
        $body[] = sprintf(
            '<rect x="96" y="%d" width="%d" height="18" rx="9" fill="%s"/>',
            148 + $i * 76,
            240 + $roll(200),
            $i === 0 ? $motifColors[0] : $rail
        );
    }

    $body[] = sprintf(
        '<rect x="742" y="70" width="768" height="360" rx="28" fill="none" stroke="%s" stroke-width="4"%s/>',
        $rail,
        $roll(2) === 0 ? ' stroke-dasharray="20 14"' : ''
    );
    $body[] = sprintf('<g transform="translate(948,%d)">', 196 + $roll(56));
    $body[] = $motifGroup($roll, $motifColors, 132 + $roll(36));
    $body[] = '</g>';
    $body[] = sprintf('<g transform="translate(1318,%d)">', 214 + $roll(56));
    $body[] = $motifGroup($roll, $motifColors, 84 + $roll(28));
    $body[] = '</g>';
    $body[] = sprintf(
        '<rect x="96" y="424" width="%d" height="10" rx="5" fill="%s"/>',
        120 + $roll(72),
        $dark ? $palette['light'] : $palette['ember']
    );
    $body[] = sprintf(
        '<circle cx="1452" cy="98" r="%d" fill="%s"/>',
        8 + $roll(10),
        $palette['light']
    );

    return $svgDocument(1600, 500, 'banner-' . $name, $title . ' campaign banner', $body);
};

$targets = [];
foreach (['products', 'brands', 'categories', 'banners'] as $subdir) {
    $directory = $imgRoot . '/' . $subdir;
    if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
        fwrite(STDERR, "Could not create {$directory}.\n");
        exit(1);
    }
    $targets[$subdir] = (string) realpath($directory);
}
$imagesBase = (string) realpath($imgRoot);
$writeRoot = $imagesBase . DIRECTORY_SEPARATOR;

/**
 * Render and write one directory's worth of artwork.
 *
 * @param list<array<string, mixed>> $rows
 * @param callable(string, string): string $build slug and name in, SVG out
 * @return array{written: list<string>, skipped: list<string>, mismatched: list<string>}
 */
$generate = static function (
    string $subdir,
    array $rows,
    callable $build
) use (
    $targets,
    $writeRoot
): array {
    $written = [];
    $skipped = [];
    $mismatched = [];
    $directory = $targets[$subdir];

    foreach ($rows as $row) {
        $slug = (string) ($row['slug'] ?? '');
        $name = trim((string) ($row['name'] ?? ''));
        $relative = $subdir . '/' . $slug . '.svg';

        // The stem *is* the filename, so anything that could climb out of the
        // image root - a slash, a dot, an uppercase byte - is refused, not
        // sanitised: a silent rewrite would break the stored path it must match.
        // Underscores are allowed because banners are named by placement
        // (home_hero) in the database, not by slug.
        if (preg_match('/^[a-z0-9_-]+$/', $slug) !== 1) {
            $skipped[] = $relative;
            fwrite(STDERR, sprintf("Warning: skipped '%s' - unsafe slug '%s'.\n", $name, $slug));
            continue;
        }

        $target = $directory . DIRECTORY_SEPARATOR . $slug . '.svg';
        $parent = realpath(dirname($target));
        if ($parent !== $directory || !str_starts_with($target, $writeRoot)) {
            $skipped[] = $relative;
            fwrite(STDERR, sprintf("Warning: skipped '%s' - path escapes the image root.\n", $name));
            continue;
        }

        $stored = trim((string) ($row['stored_path'] ?? ''));
        if ($stored !== '' && $stored !== $relative) {
            $mismatched[] = $relative . ' (database holds ' . $stored . ')';
        }

        if (file_put_contents($target, $build($slug, $name)) === false) {
            $skipped[] = $relative;
            fwrite(STDERR, sprintf("Warning: could not write %s.\n", $target));
            continue;
        }

        $written[] = $relative;
    }

    return ['written' => $written, 'skipped' => $skipped, 'mismatched' => $mismatched];
};

/**
 * Proof that a written file is still an image a browser will accept: parseable
 * XML, role="img", and an aria-labelledby that resolves to a non-empty title.
 *
 * @return list<string> problems found, empty when the file is sound
 */
$inspect = static function (string $path): array {
    $xml = simplexml_load_file($path);
    if ($xml === false) {
        return ['is not well-formed XML'];
    }

    $attributes = $xml->attributes();
    $labelled = $attributes === null ? '' : (string) $attributes['aria-labelledby'];
    $titleElement = $xml->title;
    $titleText = trim((string) $titleElement);
    // attributes() is null when the element is absent, so the id is only read
    // for a title that actually has text.
    $titleAttributes = $titleText === '' ? null : $titleElement->attributes();
    $titleId = $titleAttributes === null ? '' : (string) $titleAttributes['id'];
    $problems = [];

    if ($attributes === null || (string) $attributes['role'] !== 'img') {
        $problems[] = 'has no role="img"';
    }
    if ($titleText === '') {
        $problems[] = 'has an empty <title>';
    }
    if ($labelled === '' || $labelled !== $titleId) {
        $problems[] = 'aria-labelledby does not resolve to the title id';
    }

    return $problems;
};

$productRows = $db->select(
    "SELECT slug, name, image AS stored_path FROM products
     WHERE status = 'published' AND deleted_at IS NULL ORDER BY id"
);
$productScope = 'published';

if ($productRows === []) {
    // Nothing is published yet - the seeded demo catalogue ships as draft. Each
    // row already points at products/{slug}.svg, so leaving the folder empty
    // would 404 regardless of status: draw every live row and name the scope in
    // the summary instead of pretending the run found nothing.
    $productRows = $db->select(
        'SELECT slug, name, image AS stored_path FROM products WHERE deleted_at IS NULL ORDER BY id'
    );
    $productScope = 'non-deleted (nothing published)';
    fwrite(STDERR, "Warning: no published products; drawing every non-deleted row instead.\n");
}

$brandRows = $db->select(
    'SELECT slug, name, logo AS stored_path FROM brands WHERE status = 1 ORDER BY id'
);
$categoryRows = $db->select(
    'SELECT slug, name, image AS stored_path FROM categories WHERE parent_id IS NULL AND status = 1 ORDER BY id'
);

// Banners are keyed by the filename already stored in banners.image, because the
// placement plus locale - not a slug - decides the artwork, and several rows can
// share one file.
$bannerFiles = $db->select(
    'SELECT image, MIN(title) AS name FROM banners
     WHERE status = 1 AND image IS NOT NULL GROUP BY image ORDER BY image'
);

$bannerRows = [];
foreach ($bannerFiles as $row) {
    $stored = trim((string) $row['image']);
    $safe = preg_match('/^banners\/([a-z0-9_-]+)\.svg$/', $stored, $m) === 1;
    if (!$safe) {
        fwrite(STDERR, sprintf("Warning: skipped banner path '%s' - not a banners/<name>.svg file.\n", $stored));
        continue;
    }
    $bannerRows[] = ['slug' => $m[1], 'name' => (string) $row['name'], 'stored_path' => $stored];
}

// Named so each job below stays a single line; arrow functions capture the
// builders without a use clause.
$productBuild = static fn (string $slug, string $name): string => $tileSvg($slug, $name, 'plan', 800, 600);
$brandBuild = static fn (string $slug, string $name): string => $badgeSvg($slug, $name);
$categoryBuild = static fn (string $slug, string $name): string => $tileSvg($slug, $name, 'category', 800, 600);
$bannerBuild = static fn (string $slug, string $name): string => $bannerSvg($slug, $name);

$reports = [
    'products' => $generate('products', $productRows, $productBuild),
    'brands' => $generate('brands', $brandRows, $brandBuild),
    'categories' => $generate('categories', $categoryRows, $categoryBuild),
    'banners' => $generate('banners', $bannerRows, $bannerBuild),
];

$placeholderRelative = 'placeholder.svg';
$placeholderTarget = $writeRoot . 'placeholder.svg';
if (file_put_contents($placeholderTarget, $placeholderSvg()) === false) {
    fwrite(STDERR, "Could not write {$placeholderTarget}.\n");
    exit(1);
}

$paths = [$placeholderTarget];
foreach ($reports as $report) {
    foreach ($report['written'] as $relative) {
        $paths[] = $writeRoot . str_replace('/', DIRECTORY_SEPARATOR, $relative);
    }
}

$invalid = [];
foreach ($paths as $path) {
    foreach ($inspect($path) as $problem) {
        $invalid[] = $path . ': ' . $problem;
    }
}

$skipped = [];
$mismatched = [];
foreach ($reports as $report) {
    foreach ($report['skipped'] as $relative) {
        $skipped[] = $relative;
    }
    foreach ($report['mismatched'] as $note) {
        $mismatched[] = $note;
    }
}

if ($invalid !== []) {
    fwrite(STDERR, 'Invalid artwork:' . "\n  - " . implode("\n  - ", $invalid) . "\n");
    exit(1);
}

if (!$quiet) {
    foreach ($reports as $report) {
        foreach ($report['written'] as $relative) {
            echo '  wrote ' . $relative . "\n";
        }
    }
    echo "  wrote {$placeholderRelative}\n";
}

printf("Products scope: %s\n", $productScope);
foreach ($reports as $subdir => $report) {
    printf(
        "%-11s %2d file(s) -> public/assets/img/%s\n",
        $subdir,
        count($report['written']),
        $subdir
    );
}
printf("%-11s %2d file(s) -> public/assets/img/%s\n", 'placeholder', 1, $placeholderRelative);
printf(
    "Validated %d SVG file(s) with simplexml_load_file | skipped %d | stored-path mismatches %d\n",
    count($paths),
    count($skipped),
    count($mismatched)
);

if ($mismatched !== []) {
    fwrite(STDERR, 'The database points elsewhere for: ' . implode(', ', $mismatched) . "\n");
}

exit(0);
