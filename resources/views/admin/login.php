<?php

declare(strict_types=1);

/**
 * Staff sign-in. It deliberately does not use layouts/admin: that shell is the
 * chrome for someone already through the door, and it would have to guess what to
 * show for a guest. One self-contained document keeps the pre-auth page honest -
 * no sidebar links, no half-built topbar.
 *
 * Nothing here prints a username, a password or a hint about either. A wrong
 * email, a wrong password, a disabled account and a customer account all answer
 * with the same sentence.
 *
 * @var App\Core\View $view
 */
$brandName = setting('brand.name', 'ZapSub');
$reason = trim((string) ($reason ?? ''));
$documentTitle = 'Staff sign in - ' . $brandName;

$favicon = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E"
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
    <meta name="description" content="Staff sign in for the <?= e($brandName) ?> order desk.">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <meta name="theme-color" content="#1c0b47">
    <link rel="icon" href="<?= e($favicon) ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap">
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body class="min-h-screen bg-ink font-sans text-white antialiased">
    <a href="#main" class="sr-only rounded-brand bg-brand-600 px-4 py-2 font-semibold text-white focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-[9999]">Skip to the form</a>

    <div class="relative isolate flex min-h-screen flex-col items-center justify-center overflow-hidden px-4 py-10">
        <span class="animate-blob absolute -left-24 top-4 h-72 w-72 rounded-full bg-brand-600/35 blur-3xl" aria-hidden="true"></span>
        <span class="animate-blob absolute -right-20 bottom-0 h-80 w-80 rounded-full bg-accent-500/25 blur-3xl [animation-delay:-8s]" aria-hidden="true"></span>

        <?= $view->partial('flash') ?>

        <main id="main" class="relative w-full max-w-md">
            <div class="reveal mb-7 flex flex-col items-center text-center" data-reveal>
                <span class="animate-float grid h-14 w-14 place-items-center rounded-brand bg-gradient-to-br from-brand-600 to-accent-500 shadow-glow">
                    <svg class="h-7 w-7 text-white" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                        <path d="M13.1 2 4.6 13.2h5.2L8.4 22l9.6-11.9h-5.4L13.1 2Z"/>
                    </svg>
                </span>
                <h1 class="mt-4 text-2xl font-black tracking-tight">
                    <span class="animate-gradient bg-gradient-to-r from-white via-accent-200 to-white bg-clip-text text-transparent">
                        <?= e($brandName) ?> order desk
                    </span>
                </h1>
                <p class="mt-2 text-sm leading-relaxed text-white/70">
                    Staff only. Everything you do in here is written to the audit trail with your name on it.
                </p>
            </div>

            <div class="reveal rounded-card border border-white/10 bg-white/[0.06] p-6 backdrop-blur-xl sm:p-8" data-reveal>
                <?php if ($reason === 'required'): ?>
                    <p class="animate-pop mb-5 rounded-brand border border-accent-300/40 bg-accent-500/15 px-4 py-3 text-sm font-semibold text-accent-100">
                        That page needs a staff session. Sign in to continue.
                    </p>
                <?php endif; ?>

                <form method="post" action="<?= e(route('admin.login.attempt')) ?>" class="space-y-5">
                    <?= csrf_field() ?>

                    <div class="space-y-1.5">
                        <label for="admin-email" class="block text-sm font-semibold text-white">Work email</label>
                        <input
                            type="email"
                            id="admin-email"
                            name="email"
                            value="<?= e(old('email')) ?>"
                            required
                            autocomplete="email"
                            spellcheck="false"
                            class="w-full rounded-brand border border-white/20 bg-white/95 px-3.5 py-2.5 text-sm text-ink outline-none transition duration-200 placeholder:text-ink-400 focus:ring-2 focus:ring-accent-300"
                            placeholder="you@company.com"
                        >
                        <?php foreach (field_errors('email') as $message): ?>
                            <p class="text-xs font-semibold text-rose-200"><?= e((string) $message) ?></p>
                        <?php endforeach; ?>
                    </div>

                    <div class="space-y-1.5">
                        <div class="flex items-center justify-between gap-3">
                            <label for="admin-password" class="block text-sm font-semibold text-white">Password</label>
                            <a href="<?= e(route('password.forgot')) ?>" class="text-xs font-bold text-accent-200 underline decoration-accent-300/50 underline-offset-2 hover:text-accent-100">
                                Forgot it?
                            </a>
                        </div>
                        <input
                            type="password"
                            id="admin-password"
                            name="password"
                            required
                            autocomplete="current-password"
                            class="w-full rounded-brand border border-white/20 bg-white/95 px-3.5 py-2.5 text-sm text-ink outline-none transition duration-200 focus:ring-2 focus:ring-accent-300"
                            placeholder="Your password"
                        >
                        <?php foreach (field_errors('password') as $message): ?>
                            <p class="text-xs font-semibold text-rose-200"><?= e((string) $message) ?></p>
                        <?php endforeach; ?>
                    </div>

                    <button
                        type="submit"
                        class="group relative w-full overflow-hidden rounded-brand bg-gradient-to-r from-brand-500 via-brand-600 to-accent-500 px-6 py-3.5 text-sm font-black text-white shadow-glow transition-transform duration-200 hover:-translate-y-0.5"
                    >
                        <span class="animate-shimmer absolute inset-0 bg-gradient-to-r from-white/0 via-white/35 to-white/0" aria-hidden="true"></span>
                        <span class="relative inline-flex items-center justify-center gap-2">
                            Sign in to the panel
                            <svg class="h-4 w-4 transition-transform duration-200 group-hover:translate-x-1" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true">
                                <path d="M5 12h14M13 6l6 6-6 6" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </span>
                    </button>
                </form>

                <p class="mt-6 flex items-start gap-2 rounded-brand bg-black/25 p-3 text-[0.72rem] leading-relaxed text-white/70">
                    <svg class="mt-0.5 h-4 w-4 shrink-0 text-accent-200" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path d="M12 3 4 6v6c0 4.4 3.2 8.2 8 9 4.8-.8 8-4.6 8-9V6l-8-3Z" stroke-linejoin="round"/>
                        <path d="m9 12 2 2 4-4" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    <span>Sign-in attempts are rate limited and logged. Customer accounts cannot open this
                        panel - they sign in on the shop.</span>
                </p>
            </div>

            <p class="mt-6 text-center text-xs text-white/60">
                <a href="<?= e(route('home')) ?>" class="font-bold underline decoration-white/30 underline-offset-4 hover:text-white">
                    &larr; Back to the storefront
                </a>
            </p>
        </main>
    </div>

    <div data-toast-host aria-live="polite" class="fixed inset-x-0 top-4 z-[9999] flex flex-col items-center gap-2 px-4 pointer-events-none"></div>

    <script src="<?= e(asset('js/app.js')) ?>" defer></script>
</body>
</html>
