<?php

declare(strict_types=1);

/**
 * Sticky glass header. [data-header] condenses on scroll, the mobile panel is
 * toggled by app.js through [data-mobile-nav-btn] + [data-mobile-nav-panel].
 */
$links = [
    ['label' => 'Plans', 'route' => 'plans', 'path' => 'plans'],
    ['label' => 'How it works', 'route' => 'how', 'path' => 'how-it-works'],
    ['label' => 'FAQ', 'route' => 'faq', 'path' => 'faq'],
    ['label' => 'Track order', 'route' => 'track', 'path' => 'track'],
    ['label' => 'Contact', 'route' => 'contact', 'path' => 'contact'],
];

$activeClass = 'bg-brand-600 text-white shadow-pop';
$idleClass = 'text-ink-700 hover:bg-brand-50 hover:text-brand-700';

$currentRequest = $request ?? app()->request();
$searchValue = (string) $currentRequest->queryValue('search', '');
?>
<header data-header class="glass sticky top-0 z-50 border-b border-brand-100">
    <div data-mobile-nav class="mx-auto flex max-w-7xl items-center gap-3 px-4 py-3 lg:px-6">
        <a href="<?= e(route('home')) ?>" class="flex shrink-0 items-center gap-2.5" aria-label="<?= e(setting('brand.name', 'DexxozzBD')) ?> home">
            <?= $view->component('brand-mark', ['size' => 'h-10 w-10', 'radius' => 'rounded-brand']) ?>
            <span class="flex flex-col leading-none">
                <span class="text-lg font-black tracking-tight text-ink"><?= e(setting('brand.name', 'DexxozzBD')) ?></span>
                <span class="mt-1 hidden text-[11px] font-semibold uppercase tracking-widest text-brand-600 sm:block">Delivered on WhatsApp</span>
            </span>
        </a>

        <nav class="ml-2 hidden items-center gap-1 lg:flex" aria-label="Main">
            <?php foreach ($links as $link): ?>
                <a
                    href="<?= e(route($link['route'])) ?>"
                    class="whitespace-nowrap rounded-pill px-3 py-2 text-[15px] font-semibold transition-colors duration-200 <?= e(is_current_path($link['path']) ? $activeClass : $idleClass) ?>"
                    <?php if (is_current_path($link['path'])): ?> aria-current="page"<?php endif; ?>
                ><?= e($link['label']) ?></a>
            <?php endforeach; ?>
        </nav>

        <form method="get" action="<?= e(route('plans')) ?>" class="ml-auto hidden items-center md:flex" role="search">
            <label for="header-search" class="sr-only">Search plans</label>
            <div class="flex items-center gap-1 rounded-pill border border-brand-200 bg-white/80 px-3 py-1.5 focus-within:ring-2 focus-within:ring-brand-400">
                <svg class="h-4 w-4 text-ink-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5" stroke-linecap="round"/>
                </svg>
                <input
                    id="header-search"
                    type="search"
                    name="search"
                    value="<?= e($searchValue) ?>"
                    placeholder="Netflix, Canva, ChatGPT..."
                    class="w-36 bg-transparent text-sm outline-none placeholder:text-ink-400 lg:w-48"
                >
                <button type="submit" class="rounded-pill bg-brand-600 px-3 py-1 text-xs font-bold text-white transition-colors duration-200 hover:bg-brand-700">Go</button>
            </div>
        </form>

        <a href="<?= e(route('cart')) ?>" class="relative ml-1 grid h-10 w-10 shrink-0 place-items-center rounded-brand border border-brand-200 bg-white/80 text-ink-700 transition-colors duration-200 hover:border-brand-400 hover:text-brand-700" aria-label="Cart">
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                <path d="M6 8h12l-1 12H7L6 8Z" stroke-linejoin="round"/><path d="M9 8a3 3 0 0 1 6 0" stroke-linecap="round"/>
            </svg>
            <span data-cart-count class="absolute -right-1.5 -top-1.5 grid h-5 min-w-5 place-items-center rounded-pill bg-accent-500 px-1 text-[11px] font-black text-white"><?= e(cart_count()) ?></span>
        </a>

        <?php if (auth_check()): ?>
            <a href="<?= e(route('account')) ?>" class="hidden shrink-0 items-center gap-2 rounded-pill border border-brand-200 bg-white/80 px-3 py-2 text-sm font-semibold text-ink-700 transition-colors duration-200 hover:border-brand-400 hover:text-brand-700 sm:flex">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                    <circle cx="12" cy="8.5" r="3.5"/><path d="M5 20c1.6-3.2 4-4.6 7-4.6s5.4 1.4 7 4.6" stroke-linecap="round"/>
                </svg>
                My account
            </a>
        <?php else: ?>
            <a href="<?= e(route('login')) ?>" class="hidden shrink-0 rounded-pill border border-brand-200 bg-white/80 px-3.5 py-2 text-sm font-semibold text-ink-700 transition-colors duration-200 hover:border-brand-400 hover:text-brand-700 sm:block">Sign in</a>
        <?php endif; ?>

        <?php if (is_staff()): ?>
            <a href="<?= e(route('admin.dashboard')) ?>" class="hidden shrink-0 rounded-pill bg-ink px-3.5 py-2 text-sm font-semibold text-white transition-colors duration-200 hover:bg-brand-700 lg:block">Admin</a>
        <?php endif; ?>

        <button type="button" data-mobile-nav-btn aria-expanded="false" class="grid h-10 w-10 shrink-0 place-items-center rounded-brand border border-brand-200 bg-white/80 text-ink-700 lg:hidden" aria-label="Open menu">
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path d="M4 7h16M4 12h16M4 17h16" stroke-linecap="round"/>
            </svg>
        </button>
    </div>

    <div data-mobile-nav-panel hidden class="border-t border-brand-100 bg-white/90 px-4 py-4 lg:hidden">
        <form method="get" action="<?= e(route('plans')) ?>" class="mb-4 flex items-center gap-2" role="search">
            <label for="mobile-search" class="sr-only">Search plans</label>
            <input id="mobile-search" type="search" name="search" value="<?= e($searchValue) ?>" placeholder="Search a brand or plan" class="w-full rounded-brand border border-brand-200 bg-white px-3 py-2 text-sm outline-none focus:ring-2 focus:ring-brand-400">
            <button type="submit" class="shrink-0 rounded-brand bg-brand-600 px-4 py-2 text-sm font-bold text-white">Search</button>
        </form>

        <nav class="grid gap-1" aria-label="Mobile">
            <?php foreach ($links as $link): ?>
                <a href="<?= e(route($link['route'])) ?>" class="rounded-brand px-3 py-2 text-base font-semibold <?= e(is_current_path($link['path']) ? 'bg-brand-600 text-white' : 'text-ink-700 hover:bg-brand-50') ?>">
                    <?= e($link['label']) ?>
                </a>
            <?php endforeach; ?>
            <a href="<?= e(route('home')) ?>" class="rounded-brand px-3 py-2 text-sm font-semibold text-ink-700 hover:bg-brand-50">Home</a>
            <a href="<?= e(route('cart')) ?>" class="rounded-brand px-3 py-2 text-sm font-semibold text-ink-700 hover:bg-brand-50">Cart (<span data-cart-count><?= e(cart_count()) ?></span>)</a>
            <?php if (auth_check()): ?>
                <a href="<?= e(route('account')) ?>" class="rounded-brand bg-brand-50 px-3 py-2 text-sm font-bold text-brand-700">My account</a>
            <?php else: ?>
                <a href="<?= e(route('login')) ?>" class="rounded-brand bg-brand-50 px-3 py-2 text-sm font-bold text-brand-700">Sign in</a>
                <a href="<?= e(route('register')) ?>" class="rounded-brand bg-brand-600 px-3 py-2 text-sm font-bold text-white">Create an account</a>
            <?php endif; ?>
            <?php if (is_staff()): ?>
                <a href="<?= e(route('admin.dashboard')) ?>" class="rounded-brand bg-ink px-3 py-2 text-sm font-bold text-white">Admin panel</a>
            <?php endif; ?>
        </nav>
    </div>
</header>
