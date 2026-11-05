<?php

declare(strict_types=1);

use App\Core\Crypto;

/**
 * The admin shell. Every staff page extends this and hands it four sections:
 *   title       - document title
 *   page_title  - the <h1> the shell prints (plain text, escaped here)
 *   page_actions- raw HTML for the buttons at the right of the header
 *   content     - the page body (filled by View::html from the echoed template)
 *
 * The chrome is split into two partials so a later screen can change one without
 * touching the other: admin/partials/topbar and admin/partials/sidebar.
 *
 * @var App\Core\View $view
 */

// Read the sections before anything is included: an included partial must never
// be able to influence what the shell prints for the page.
$brandName = setting('brand.name', 'DexxozzBD');
$documentTitle = $view->section('title', 'Admin - ' . $brandName);
$pageHeading = $view->section('page_title', $documentTitle);
$pageActions = $view->section('page_actions');
$body = $view->section('content');

// Delivery notes are only readable while APP_KEY exists, so the shell says out
// loud when it does not, instead of every job printing
// "(encrypted, but APP_KEY is missing)".
$encryptionOff = !Crypto::keyPresent();

$favicon = brand_logo() !== '' ? brand_logo() : "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E"
    . "%3Crect width='32' height='32' rx='8' fill='%236a31e0'/%3E"
    . "%3Cpath d='M18.6 4 8.4 18.2h5.1L11.6 28 23.2 13.4h-5.6L18.6 4Z' fill='%2322d3ee'/%3E%3C/svg%3E";
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($documentTitle) ?></title>
    <meta name="robots" content="noindex, nofollow">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <meta name="theme-color" content="#6a31e0">
    <link rel="icon" href="<?= e($favicon) ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap">
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
    <?= $view->partial('tailwind-cdn') ?>
</head>
<body class="min-h-screen bg-brand-50/40 font-sans text-ink antialiased">
    <a href="#main" class="sr-only rounded-brand bg-brand-600 px-4 py-2 font-semibold text-white focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-[9999]">Skip to content</a>

    <header data-header class="glass sticky top-0 z-40 border-x-0 border-t-0">
        <?php include $view->path('admin.partials.topbar'); ?>
    </header>

    <?= $view->partial('flash') ?>

    <div class="mx-auto flex w-full max-w-[110rem] flex-col gap-6 px-4 py-6 md:flex-row md:px-6">
        <aside id="admin-nav-panel" data-mobile-nav-panel hidden class="rounded-card border border-brand-100 bg-white p-3 shadow-soft md:sticky md:top-24 md:block md:w-64 md:shrink-0 md:self-start">
            <?php include $view->path('admin.partials.sidebar'); ?>
        </aside>

        <main id="main" class="min-w-0 flex-1">
            <div class="mb-5 flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-black tracking-tight text-ink sm:text-3xl"><?= e($pageHeading) ?></h1>
                    <p class="mt-1 text-xs font-bold uppercase tracking-[0.18em] text-brand-600">
                        <?= e($brandName) ?> admin
                    </p>
                </div>

                <?php if (trim($pageActions) !== ''): ?>
                    <?php /* Raw on purpose: page_actions is a view-authored button
                              row, never request input - the shell only decides
                              whether to print it. */ ?>
                    <div class="flex flex-wrap items-center gap-2"><?= $pageActions ?></div>
                <?php endif; ?>
            </div>

            <?php if ($encryptionOff): ?>
                <div role="alert" class="animate-pop mb-5 flex items-start gap-3 rounded-brand border-2 border-amber-300 bg-amber-50 p-4 text-sm text-amber-900">
                    <svg class="mt-0.5 h-5 w-5 shrink-0 text-amber-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path d="M12 3 4 6v6c0 4.4 3.2 8.2 8 9 4.8-.8 8-4.6 8-9V6l-8-3Z" stroke-linejoin="round"/>
                        <path d="M12 9v4M12 16h.01" stroke-linecap="round"/>
                    </svg>
                    <p>
                        <strong class="font-black">Encryption is off.</strong> APP_KEY is empty, so delivery
                        notes are stored as plain text and anything already sealed cannot be read here.
                        Run <code class="rounded bg-white px-1.5 py-0.5 font-mono text-xs">composer key</code>
                        and set it in <code class="rounded bg-white px-1.5 py-0.5 font-mono text-xs">.env</code>.
                    </p>
                </div>
            <?php endif; ?>

            <?= $body ?>

            <p class="mt-10 border-t border-dashed border-brand-200 pt-4 text-xs text-ink-400">
                &copy; <span data-year><?= e(date('Y')) ?></span> <?= e($brandName) ?> admin panel.
                Paid status is only ever set by a person on this screen.
            </p>
        </main>
    </div>

    <div data-toast-host aria-live="polite" aria-atomic="false" class="fixed inset-x-0 top-4 z-[9999] flex flex-col items-center gap-2 px-4 pointer-events-none"></div>

    <script src="<?= e(asset('js/app.js')) ?>" defer></script>
</body>
</html>
