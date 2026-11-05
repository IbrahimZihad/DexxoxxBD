<?php

declare(strict_types=1);

/**
 * Sign in. Split screen: a moving brand panel on the left, the form on the
 * right. Nothing here prints demo credentials - a failed attempt says one
 * generic sentence, whatever the reason was.
 *
 * @var App\Core\View $view
 * @var string $reason
 */
$brandName = setting('brand.name', 'ZapSub');
$reason = trim((string) ($reason ?? ''));

$view->extend('layouts/app');
$view->setSection('title', 'Sign in - ' . $brandName);
$view->setSection('meta_description', 'Sign in to ' . $brandName . ' to see your orders, payment status and delivery history in one place.');
$view->setSection('canonical', route('login'));

$perks = [
    ['Every order in one place', 'Payment, verification and delivery states, kept per order.'],
    ['Faster checkout', 'Your name, email and number are already filled in when you order again.'],
    ['Replacement proof', 'Your order number is the warranty - it is what we ask for on WhatsApp.'],
];
?>
<div class="relative overflow-hidden bg-gradient-to-b from-brand-50 via-white to-white">
    <span class="blob -left-28 -top-28 animate-blob opacity-40" aria-hidden="true"></span>
    <span class="blob -right-24 bottom-[-8rem] animate-blob opacity-30 [animation-delay:-7s]" aria-hidden="true"></span>

    <div class="relative mx-auto max-w-6xl px-4 py-10 lg:px-6 lg:py-16">
        <div class="reveal overflow-hidden rounded-card border border-brand-100 bg-white shadow-card grid lg:grid-cols-[1.05fr_1fr]" data-reveal>
            <?php /* ------------------------- brand panel -------------------------*/ ?>
            <aside class="relative isolate overflow-hidden bg-gradient-to-br from-brand-700 via-brand-600 to-accent-600 px-7 py-10 text-white sm:px-9">
                <span class="animate-blob absolute -left-12 top-6 h-44 w-44 rounded-full bg-accent-300/30 blur-2xl" aria-hidden="true"></span>
                <span class="animate-blob absolute right-0 top-40 h-52 w-52 rounded-full bg-white/15 blur-3xl [animation-delay:-6s]" aria-hidden="true"></span>
                <span class="animate-pulse-ring absolute -bottom-10 -right-10 h-36 w-36 rounded-full bg-brand-400/30 blur-2xl" aria-hidden="true"></span>

                <a href="<?= e(route('home')) ?>" class="relative inline-flex items-center gap-2.5">
                    <span class="grid h-10 w-10 place-items-center rounded-brand bg-white/15 ring-1 ring-white/30">
                        <svg class="h-5 w-5 animate-pop text-white" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                            <path d="M13.1 2 4.6 13.2h5.2L8.4 22l9.6-11.9h-5.4L13.1 2Z"/>
                        </svg>
                    </span>
                    <span class="text-lg font-black tracking-tight"><?= e($brandName) ?></span>
                </a>

                <h2 class="relative mt-8 text-2xl font-black leading-tight tracking-tight sm:text-3xl">
                    <span class="animate-gradient bg-gradient-to-r from-white via-accent-200 to-white bg-clip-text text-transparent">
                        Your subscriptions, all in one account.
                    </span>
                </h2>
                <p class="relative mt-3 max-w-sm text-sm leading-relaxed text-white/85">
                    Sign in and every plan you have bought is one screen away - with the order number
                    we need to replace an account for you.
                </p>

                <ul class="relative mt-8 space-y-4">
                    <?php foreach ($perks as $index => [$perkTitle, $perkText]): ?>
                        <li class="flex gap-3">
                            <span class="animate-float grid h-9 w-9 shrink-0 place-items-center rounded-brand bg-white/15 text-sm font-black ring-1 ring-white/25" style="animation-delay:<?= e((string) ($index * 400)) ?>ms">
                                <?= e((string) ($index + 1)) ?>
                            </span>
                            <span>
                                <span class="block text-sm font-bold"><?= e($perkTitle) ?></span>
                                <span class="mt-0.5 block text-xs leading-relaxed text-white/75"><?= e($perkText) ?></span>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>

                <p class="relative mt-9 flex items-start gap-2 rounded-brand bg-white/10 p-3 text-xs leading-relaxed text-white/80">
                    <svg class="mt-0.5 h-4 w-4 shrink-0 text-accent-200" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path d="M12 3 4 6v6c0 4.4 3.2 8.2 8 9 4.8-.8 8-4.6 8-9V6l-8-3Z" stroke-linejoin="round"/>
                        <path d="m9 12 2 2 4-4" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    <span>Passwords are stored with Argon2id, reset links expire in an hour, and an account
                        we disable cannot sign in.</span>
                </p>
            </aside>

            <?php /* ---------------------------- form -----------------------------*/ ?>
            <div class="px-6 py-9 sm:px-9">
                <h1 class="text-2xl font-black tracking-tight text-ink">Welcome back</h1>
                <p class="mt-1.5 text-sm text-ink-400">Sign in with the email you used at checkout.</p>

                <?php if ($reason === 'required'): ?>
                    <p class="animate-pop mt-5 rounded-brand border border-brand-200 bg-brand-50 px-4 py-3 text-sm font-semibold text-brand-800">
                        That page is for signed-in customers. Sign in to continue.
                    </p>
                <?php endif; ?>

                <form method="post" action="<?= e(route('login.attempt')) ?>" class="mt-6 space-y-5">
                    <?= csrf_field() ?>

                    <?= $view->component('field', [
                        'name' => 'email',
                        'label' => 'Email',
                        'type' => 'email',
                        'placeholder' => 'you@example.com',
                        'required' => true,
                        'autocomplete' => 'email',
                    ]) ?>

                    <?= $view->component('field', [
                        'name' => 'password',
                        'label' => 'Password',
                        'type' => 'password',
                        'placeholder' => 'Your password',
                        'required' => true,
                        'autocomplete' => 'current-password',
                    ]) ?>

                    <div class="flex items-center justify-between gap-3 text-sm">
                        <a href="<?= e(route('password.forgot')) ?>" class="font-bold text-brand-700 underline decoration-accent-400 decoration-2 underline-offset-4 transition-colors duration-200 hover:text-brand-800">
                            Forgot password?
                        </a>
                        <span class="text-xs text-ink-400">Takes under a minute</span>
                    </div>

                    <button type="submit" class="group relative w-full overflow-hidden rounded-brand bg-gradient-to-r from-brand-600 via-brand-500 to-accent-500 px-6 py-3.5 text-sm font-black text-white shadow-glow transition-transform duration-200 hover:-translate-y-0.5">
                        <span class="animate-shimmer absolute inset-0 bg-gradient-to-r from-white/0 via-white/35 to-white/0" aria-hidden="true"></span>
                        <span class="relative inline-flex items-center justify-center gap-2">
                            Sign in
                            <svg class="h-4 w-4 transition-transform duration-200 group-hover:translate-x-1" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true">
                                <path d="M5 12h14M13 6l6 6-6 6" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </span>
                    </button>

                    <p class="text-xs leading-relaxed text-ink-400">
                        Ordered as a guest? You do not need an account to track it - use
                        <a href="<?= e(route('track')) ?>" class="font-bold text-brand-700 underline underline-offset-2">the order number and email</a>.
                    </p>
                </form>

                <div class="mt-7 border-t border-dashed border-brand-100 pt-6">
                    <p class="text-sm text-ink-400">
                        New here?
                        <a href="<?= e(route('register')) ?>" class="font-black text-brand-700 underline decoration-accent-400 decoration-2 underline-offset-4">
                            Create an account
                        </a>
                        and keep every plan in one place.
                    </p>
                    <p class="mt-3 text-xs text-ink-400">
                        Prefer to just ask a human?
                        <a href="<?= e(wa_link('Hi ' . $brandName . ', I cannot sign in to my account.')) ?>" target="_blank" rel="noopener" class="font-bold text-emerald-700 underline underline-offset-2">
                            Message us on WhatsApp
                        </a>
                    </p>
                </div>
            </div>
        </div>

        <p class="mt-6 text-center text-xs text-ink-400">
            <a href="<?= e(route('home')) ?>" class="font-bold hover:text-brand-700">&larr; Back to the shop</a>
        </p>
    </div>
</div>
