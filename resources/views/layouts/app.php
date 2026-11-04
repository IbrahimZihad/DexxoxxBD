<?php

/**
 * Storefront shell: document head with SEO/OG/JSON-LD, chrome partials and the
 * content slot.
 *
 * Page views call $view->extend('layouts.app') and fill 'content' plus any of
 * the optional sections: title, meta_description, meta_robots, canonical,
 * meta_image, json_ld, body_class, before_head, after_main.
 *
 * Vendored CSS/JS is served from public/assets/vendor so the CSP
 * (script-src 'self') never has to allow a third-party origin.
 */

$siteName = (string) setting('brand.name', config('app.name', 'DexxoxxBD'));
$tagline = (string) setting('brand.tagline', config('app.brand.tagline', ''));
$pageTitle = trim($view->section('title', $siteName . ' - ' . $tagline));
$description = trim($view->section('meta_description', $tagline));
$canonical = $view->section('canonical', current_url());
$ogImage = $view->section('meta_image', url('assets/img/og.svg'));
$robots = $view->section('meta_robots', 'index, follow');
$schema = $view->section('json_ld', '');
$localeTag = current_locale() === 'bn' ? 'bn_BD' : 'en_BD';
?>
<!doctype html>
<html lang="<?= e(current_locale()) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?></title>
    <meta name="description" content="<?= e(mb_substr($description, 0, 300)) ?>">
    <meta name="robots" content="<?= e($robots) ?>">
    <link rel="canonical" href="<?= e($canonical) ?>">
    <meta name="theme-color" content="#08201d">

    <meta property="og:site_name" content="<?= e($siteName) ?>">
    <meta property="og:type" content="<?= e($view->section('og_type', 'website')) ?>">
    <meta property="og:title" content="<?= e($pageTitle) ?>">
    <meta property="og:description" content="<?= e(mb_substr($description, 0, 300)) ?>">
    <meta property="og:url" content="<?= e($canonical) ?>">
    <meta property="og:image" content="<?= e($ogImage) ?>">
    <meta property="og:image:alt" content="<?= e($siteName) ?>">
    <meta property="og:locale" content="<?= e($localeTag) ?>">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= e($pageTitle) ?>">
    <meta name="twitter:description" content="<?= e(mb_substr($description, 0, 300)) ?>">
    <meta name="twitter:image" content="<?= e($ogImage) ?>">

    <link rel="icon" type="image/svg+xml" href="<?= e(asset('img/favicon.svg')) ?>">
    <link rel="apple-touch-icon" href="<?= e(asset('img/og.svg')) ?>">
    <link rel="stylesheet" href="<?= e(asset('vendor/bootstrap/css/bootstrap.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('vendor/bootstrap-icons/bootstrap-icons.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
    <?php if ($schema !== ''): ?>
        <script type="application/ld+json"><?= $schema ?></script>
    <?php endif; ?>
    <?= $view->section('before_head') ?>
</head>
<body class="<?= e($view->section('body_class', 'dx-shop')) ?>">
<a class="dx-skip-link" href="#main"><?= e(t('common.skip_to_content')) ?></a>

<?= $view->partial('partials.topbar') ?>
<?= $view->partial('partials.header') ?>
<?= $view->partial('partials.catstrip') ?>

<main id="main" class="dx-main">
    <?= $view->partial('partials.flash') ?>
    <?= $view->section('content') ?>
</main>

<?= $view->partial('partials.footer') ?>
<?= $view->partial('partials.quick-view') ?>

<script src="<?= e(asset('vendor/sweetalert2/sweetalert2.all.min.js')) ?>" defer></script>
<script src="<?= e(asset('vendor/bootstrap/js/bootstrap.bundle.min.js')) ?>" defer></script>
<script src="<?= e(asset('js/app.js')) ?>" defer></script>
<?= $view->section('after_main') ?>
</body>
</html>
