<?php

/**
 * Seed-data verifier.
 *
 * verify_schema.php proves the tables exist; this proves the demo shop is
 * actually sellable: a product stored with the wrong enum label, a variation
 * priced at zero or a page left as a draft all pass schema checks and produce
 * an empty or broken storefront.
 *
 * Usage: php scripts/maintenance/verify_seed_data.php [--quiet] [--env=test]
 */

declare(strict_types=1);

$quiet = in_array('--quiet', array_slice($argv, 1), true);
$root = dirname(__DIR__, 2);

// --env=test checks the dedicated PHPUnit schema; anything else keeps the
// configured development connection.
if (in_array('--env=test', array_slice($argv, 1), true)) {
    putenv('DB_CONNECTION_TARGET=mysql_test');
}

/** @var \App\Core\Database $db */
$db = require $root . '/bootstrap/database.php';

/** @var list<string> $failures */
$failures = [];

/**
 * Record a positive expectation; a count that is too low is a failure.
 */
$expect = static function (string $what, int $got, int $atLeast) use (&$failures): void {
    if ($got < $atLeast) {
        $failures[] = sprintf('%s: expected at least %d, found %d', $what, $atLeast, $got);
    }
};

/** @return array<int, mixed> */
$one = static fn (string $sql): array => (array) $db->selectOne($sql);

/** @return array<string, mixed> */
$scalar = static fn (string $sql, array $bindings = []): int | float | string | null => $db->scalar($sql, $bindings);

$schemaName = (string) $scalar('SELECT DATABASE()');
if (!$quiet) {
    echo 'Seed data: ' . $schemaName . "\n";
}

// Catalogue visibility: the enum trap is the one that hides everything.
$draftProducts = (int) $scalar("SELECT COUNT(*) FROM products WHERE status <> 'published'");
if ($draftProducts > 0) {
    $failures[] = sprintf('%d products are not published, so the storefront shows an empty catalogue', $draftProducts);
}
$expect('published products', (int) $scalar(
    "SELECT COUNT(*) FROM products WHERE status = 'published' AND deleted_at IS NULL"
), 30);

$orphans = (int) $scalar(
    "SELECT COUNT(*) FROM products p
     WHERE p.status = 'published' AND p.deleted_at IS NULL
       AND NOT EXISTS (SELECT 1 FROM product_variations v
                        WHERE v.product_id = p.id AND v.status = 1 AND v.deleted_at IS NULL)"
);
if ($orphans > 0) {
    $failures[] = sprintf('%d published products have no sellable variation', $orphans);
}

$badPrices = (int) $scalar(
    'SELECT COUNT(*) FROM product_variations
     WHERE deleted_at IS NULL AND (price <= 0 OR (compare_price IS NOT NULL AND compare_price <= price))'
);
if ($badPrices > 0) {
    $failures[] = sprintf('%d variations have a zero price or a compare price at or below the sell price', $badPrices);
}

$noArtwork = (int) $scalar(
    "SELECT COUNT(*) FROM products WHERE status = 'published' AND (image IS NULL OR image = '')"
);
if ($noArtwork > 0) {
    $failures[] = sprintf('%d published products have no artwork path', $noArtwork);
}

$missingImages = (int) $scalar(
    "SELECT COUNT(*) FROM products p
     WHERE p.status = 'published'
       AND NOT EXISTS (SELECT 1 FROM product_images i WHERE i.product_id = p.id)"
);
if ($missingImages > 0) {
    $failures[] = sprintf('%d published products have no product_images rows', $missingImages);
}

$missingFeatures = (int) $scalar(
    "SELECT COUNT(*) FROM products p
     WHERE p.status = 'published'
       AND NOT EXISTS (SELECT 1 FROM product_features f WHERE f.product_id = p.id)"
);
if ($missingFeatures > 0) {
    $failures[] = sprintf('%d published products list no plan features', $missingFeatures);
}

$expect('categories', (int) $scalar('SELECT COUNT(*) FROM categories WHERE status = 1 AND deleted_at IS NULL'), 40);
$expect(
    'top-level categories',
    (int) $scalar('SELECT COUNT(*) FROM categories WHERE parent_id IS NULL AND status = 1 AND deleted_at IS NULL'),
    12
);

$emptyParents = (int) $scalar(
    'SELECT COUNT(*) FROM categories c
     WHERE c.parent_id IS NULL AND c.status = 1 AND c.deleted_at IS NULL
       AND NOT EXISTS (SELECT 1 FROM products p WHERE p.status = \'published\' AND p.deleted_at IS NULL
                        AND (p.category_id = c.id OR p.category_id IN
                             (SELECT k.id FROM categories k WHERE k.parent_id = c.id)))'
);
if ($emptyParents > 0) {
    $failures[] = sprintf('%d top-level categories contain no published product', $emptyParents);
}

$expect('brands', (int) $scalar('SELECT COUNT(*) FROM brands WHERE status = 1 AND deleted_at IS NULL'), 15);
$unusedBrands = (int) $scalar(
    'SELECT COUNT(*) FROM brands b
     WHERE b.status = 1 AND b.deleted_at IS NULL
       AND NOT EXISTS (SELECT 1 FROM products p WHERE p.brand_id = b.id
                        AND p.status = \'published\' AND p.deleted_at IS NULL)'
);
if ($unusedBrands > 0) {
    $failures[] = sprintf('%d active brands sell nothing', $unusedBrands);
}

// CMS visibility uses the same enum, and a draft page means a 404 in the footer.
$draftPages = (int) $scalar("SELECT COUNT(*) FROM pages WHERE status <> 'published'");
if ($draftPages > 0) {
    $failures[] = sprintf('%d demo pages are still drafts, so /page links return 404', $draftPages);
}
$expect('published pages', (int) $scalar(
    "SELECT COUNT(*) FROM pages WHERE status = 'published' AND deleted_at IS NULL"
), 6);
$expect('footer pages', (int) $scalar(
    "SELECT COUNT(*) FROM pages WHERE status = 'published' AND is_footer = 1 AND deleted_at IS NULL"
), 5);

$liveHero = (int) $scalar(
    "SELECT COUNT(*) FROM banners WHERE placement = 'home_hero' AND status = 1
      AND (starts_at IS NULL OR starts_at <= NOW()) AND (ends_at IS NULL OR ends_at >= NOW())"
);
if ($liveHero < 1) {
    $failures[] = 'no live home_hero banner: the homepage hero has nothing to show';
}
$expect('live promo banners', (int) $scalar(
    "SELECT COUNT(*) FROM banners WHERE placement = 'home_promo' AND status = 1
      AND (starts_at IS NULL OR starts_at <= NOW()) AND (ends_at IS NULL OR ends_at >= NOW())"
), 2);

$expect('faqs', (int) $scalar('SELECT COUNT(*) FROM faqs WHERE status = 1'), 10);
$expect('homepage faqs', (int) $scalar('SELECT COUNT(*) FROM faqs WHERE status = 1 AND show_on_home = 1'), 4);
$expect('testimonials', (int) $scalar('SELECT COUNT(*) FROM testimonials WHERE status = 1'), 5);
$expect('header menu items', (int) $scalar("SELECT COUNT(*) FROM menus WHERE location = 'header' AND status = 1"), 5);

// Broken menu targets would render dead links in the navigation.
$deadMenuTargets = (int) $scalar(
    "SELECT COUNT(*) FROM menus m
     WHERE m.status = 1
       AND (
            (m.target_type = 'page' AND NOT EXISTS (SELECT 1 FROM pages p WHERE p.id = m.target_id
                 AND p.status = 'published' AND p.deleted_at IS NULL))
         OR (m.target_type = 'category' AND NOT EXISTS (SELECT 1 FROM categories c WHERE c.id = m.target_id
                 AND c.status = 1 AND c.deleted_at IS NULL))
         OR (m.target_type = 'product' AND NOT EXISTS (SELECT 1 FROM products pr WHERE pr.id = m.target_id
                 AND pr.status = 'published' AND pr.deleted_at IS NULL))
       )"
);
if ($deadMenuTargets > 0) {
    $failures[] = sprintf('%d menu items point at a draft or deleted record', $deadMenuTargets);
}

// Accounts and access control.
$expect('roles', (int) $scalar('SELECT COUNT(*) FROM roles WHERE deleted_at IS NULL'), 5);
$expect('permissions', (int) $scalar('SELECT COUNT(*) FROM permissions'), 40);
$expect('role-permission grants', (int) $scalar('SELECT COUNT(*) FROM role_permissions'), 100);

$admins = $one("SELECT COUNT(*) AS total FROM users u
    INNER JOIN user_roles ur ON ur.user_id = u.id
    INNER JOIN roles r ON r.id = ur.role_id
    WHERE r.slug = 'super_admin'");
if ((int) ($admins['total'] ?? 0) < 1) {
    $failures[] = 'no user holds the super_admin role, so the admin dashboard is unreachable';
}

$customers = (int) $scalar(
    'SELECT COUNT(*) FROM users WHERE status = 1 AND deleted_at IS NULL'
);
$expect('usable demo accounts', $customers, 4);

$unverified = (int) $scalar(
    'SELECT COUNT(*) FROM users WHERE deleted_at IS NULL AND email_verified_at IS NULL'
);
if ($unverified > 0) {
    $failures[] = sprintf('%d demo accounts are not email-verified, so they cannot reach the dashboard', $unverified);
}

// Coupons must be redeemable or the checkout demo looks broken.
$liveCoupons = (int) $scalar(
    'SELECT COUNT(*) FROM coupons WHERE status = 1 AND deleted_at IS NULL
      AND (starts_at IS NULL OR starts_at <= NOW())
      AND (expires_at IS NULL OR expires_at >= NOW())'
);
$expect('live coupons', $liveCoupons, 1);

$badCouponType = (int) $scalar(
    "SELECT COUNT(*) FROM coupons WHERE type = 'percent' AND (value <= 0 OR value > 100)"
);
if ($badCouponType > 0) {
    $failures[] = sprintf('%d percent coupons are outside 1-100', $badCouponType);
}

// Settings the storefront renders on every page.
foreach (['brand.name', 'brand.tagline', 'contact.email'] as $key) {
    $value = $scalar('SELECT `value` FROM settings WHERE `key` = :key LIMIT 1', ['key' => $key]);
    if (!is_string($value) || trim($value) === '') {
        $failures[] = sprintf('setting %s is missing or empty', $key);
    }
}

if ($failures !== []) {
    fwrite(STDERR, sprintf("Seed data FAILED in %s (%d problem%s):\n", $schemaName, count($failures), count($failures) === 1 ? '' : 's'));
    foreach ($failures as $failure) {
        fwrite(STDERR, ' - ' . $failure . "\n");
    }
    exit(1);
}

if (!$quiet) {
    echo sprintf("Seed data OK in %s: the demo catalogue, CMS content, accounts and coupons are sellable.\n", $schemaName);
}
exit(0);
