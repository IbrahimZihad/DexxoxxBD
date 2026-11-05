<?php

declare(strict_types=1);

use App\Core\App;

/**
 * The only storefront layout. A page renders its body, optionally sets the
 * head sections (title / meta_description / canonical / json_ld / body_scripts)
 * and calls $view->extend('layouts/app').
 *
 * @var App\Core\View $view
 */

// Shared by App::shareViewGlobals(); the static call keeps a page rendered
// outside a booted request (error page, CLI preview) from shipping an empty nonce.
$nonce = (string) ($cspNonce ?? App::cspNonce());

$brandName = setting('brand.name', 'ZapSub');
$pageTitle = $view->section('title', $brandName . ' - ' . setting('brand.tagline', 'Premium subscriptions, delivered in minutes.'));
$pageDescription = $view->section('meta_description', setting('meta.description'));
$canonicalUrl = $view->section('canonical', current_url());
$favicon = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E"
    . "%3Crect width='32' height='32' rx='8' fill='%236a31e0'/%3E"
    . "%3Cpath d='M18.6 4 8.4 18.2h5.1L11.6 28 23.2 13.4h-5.6L18.6 4Z' fill='%2322d3ee'/%3E%3C/svg%3E";
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?></title>
    <meta name="description" content="<?= e($pageDescription) ?>">
    <link rel="canonical" href="<?= e($canonicalUrl) ?>">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <meta name="theme-color" content="#6a31e0">

    <meta property="og:type" content="website">
    <meta property="og:site_name" content="<?= e($brandName) ?>">
    <meta property="og:title" content="<?= e($pageTitle) ?>">
    <meta property="og:description" content="<?= e($pageDescription) ?>">
    <meta property="og:url" content="<?= e($canonicalUrl) ?>">
    <meta property="og:image" content="<?= e(url('assets/img/og-cover.png')) ?>">
    <meta property="og:image:alt" content="<?= e($brandName) ?> - premium subscriptions at Bangladeshi prices">
    <meta property="og:locale" content="en_BD">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= e($pageTitle) ?>">
    <meta name="twitter:description" content="<?= e($pageDescription) ?>">
    <meta name="twitter:image" content="<?= e(url('assets/img/og-cover.png')) ?>">

    <link rel="icon" href="<?= e($favicon) ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap">
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">

    <?php if ($view->hasSection('json_ld')): ?>
        <?php /* Raw on purpose: the controller encodes with JSON_HEX_TAG, so the
                   payload cannot contain a "<" that would break out of this tag. */ ?>
        <script type="application/ld+json" nonce="<?= e($nonce) ?>"><?= $view->section('json_ld') ?></script>
    <?php endif; ?>
</head>
<body class="min-h-screen bg-white font-sans text-ink antialiased">
    <a href="#main" class="sr-only rounded-brand bg-brand-600 px-4 py-2 font-semibold text-white focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-[9999]">Skip to content</a>

    <?= $view->partial('announcement') ?>
    <?= $view->partial('header') ?>
    <?= $view->partial('flash') ?>

    <main id="main"><?= $view->section('content') ?></main>

    <?= $view->partial('footer') ?>
    <?= $view->partial('whatsapp-head') ?>

    <div data-toast-host aria-live="polite" aria-atomic="false" class="fixed inset-x-0 top-4 z-[9999] flex flex-col items-center gap-2 px-4 pointer-events-none"></div>

    <script src="<?= e(asset('js/app.js')) ?>" defer></script>
    <?php if ($view->hasSection('body_scripts')): ?>
        <script nonce="<?= e($nonce) ?>"><?= $view->section('body_scripts') ?></script>
    <?php endif; ?>
</body>
</html>
