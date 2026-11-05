<?php

declare(strict_types=1);

/**
 * Admin topbar: what page you are on, how to get back to the shop, who you are,
 * and the only POST form in the chrome (sign out).
 *
 * Rendered inside the <header> of layouts/admin, so it inherits the sticky glass
 * shell. The hamburger carries data-mobile-nav-btn; app.js pairs it with the
 * sidebar panel in layouts/admin and does nothing on desktop.
 *
 * @var array<string, mixed>|null $authUser
 */
$staff = (array) ($authUser ?? []);
$staffName = trim((string) ($staff['name'] ?? ''));
$staffRole = (string) ($staff['role'] ?? 'staff');

// Two words of the name give the avatar initials; a name we cannot read falls
// back to the brand rather than an empty circle.
$words = array_values(array_filter(preg_split('/\s+/', $staffName) ?: [], static fn (string $word): bool => $word !== ''));
$initials = $words === []
    ? 'ZS'
    : mb_strtoupper(mb_substr($words[0], 0, 1) . mb_substr($words[1] ?? '', 0, 1));
?>
<div class="mx-auto flex w-full max-w-[110rem] items-center gap-3 px-4 py-3 lg:px-6">
    <button
        type="button"
        data-mobile-nav-btn
        class="grid h-10 w-10 shrink-0 place-items-center rounded-brand border border-brand-200 bg-white text-brand-700 transition-colors duration-200 hover:bg-brand-50 lg:hidden"
        aria-label="Show admin menu"
    >
        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true">
            <path d="M4 7h16M4 12h16M4 17h16" stroke-linecap="round"/>
        </svg>
    </button>

    <a href="<?= e(route('admin.dashboard')) ?>" class="flex min-w-0 items-center gap-2.5">
        <span class="grid h-10 w-10 shrink-0 place-items-center rounded-brand bg-gradient-to-br from-brand-600 to-accent-500 shadow-glow">
            <svg class="h-5 w-5 text-white" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                <path d="M13.1 2 4.6 13.2h5.2L8.4 22l9.6-11.9h-5.4L13.1 2Z"/>
            </svg>
        </span>
        <span class="min-w-0">
            <span class="block truncate text-sm font-black tracking-tight text-ink">
                <?= e(setting('brand.name', 'DexxozzBD')) ?>
            </span>
            <span class="block text-[0.7rem] font-bold uppercase tracking-[0.18em] text-brand-600">Order desk</span>
        </span>
    </a>

    <div class="ms-auto flex items-center gap-2 sm:gap-3">
        <a
            href="<?= e(route('home')) ?>"
            target="_blank"
            rel="noopener"
            class="hidden items-center gap-1.5 rounded-brand border border-brand-200 bg-white px-3 py-2 text-xs font-bold text-brand-800 transition-colors duration-200 hover:bg-brand-50 sm:inline-flex"
        >
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path d="M14 4h6v6M20 4l-9 9" stroke-linecap="round" stroke-linejoin="round"/>
                <path d="M18 14v5a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1V8a1 1 0 0 1 1-1h5" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            Live storefront
        </a>

        <span class="hidden items-center gap-2.5 rounded-pill bg-white/80 px-3 py-1.5 ring-1 ring-brand-100 md:inline-flex">
            <span class="grid h-8 w-8 place-items-center rounded-full bg-brand-100 text-xs font-black text-brand-800" aria-hidden="true">
                <?= e($initials) ?>
            </span>
            <span class="leading-tight">
                <span class="block text-xs font-black text-ink"><?= e($staffName === '' ? 'Signed in as staff' : $staffName) ?></span>
                <span class="block text-[0.7rem] font-bold uppercase tracking-wider text-ink-400"><?= e(status_label($staffRole)) ?></span>
            </span>
        </span>

        <form method="post" action="<?= e(route('admin.logout')) ?>" class="contents">
            <?= csrf_field() ?>
            <button
                type="submit"
                class="inline-flex items-center gap-1.5 rounded-brand bg-ink px-3 py-2 text-xs font-black text-white transition-colors duration-200 hover:bg-ink-700"
                aria-label="Sign out of the admin panel"
            >
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path d="M15 4h3a1 1 0 0 1 1 1v14a1 1 0 0 1-1 1h-3" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M10 8 6 12l4 4M6 12h9" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                Sign out
            </button>
        </form>
    </div>
</div>
