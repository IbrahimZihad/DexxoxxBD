<?php

declare(strict_types=1);

/**
 * Choose a new password with a token that came by email. The token is echoed
 * back in a hidden field (never in a URL the browser will keep) and the
 * controller re-checks it against the stored SHA-256 hash before anything is
 * written.
 *
 * @var App\Core\View $view
 * @var string $token
 */
$brandName = setting('brand.name', 'DexxozzBD');
$token = (string) ($token ?? '');

$view->extend('layouts/app');
$view->setSection('title', 'Choose a new password - ' . $brandName);
// Deliberately not canonical to this URL: it carries a live token.
$view->setSection('meta_description', 'Set a new ' . $brandName . ' password using the link that arrived in your email.');
$view->setSection('canonical', route('password.forgot'));
?>
<div class="relative overflow-hidden bg-gradient-to-b from-accent-50 via-white to-white">
    <span class="blob -left-24 top-16 animate-blob opacity-35" aria-hidden="true"></span>
    <span class="blob -right-24 bottom-[-8rem] animate-blob opacity-30 [animation-delay:-8s]" aria-hidden="true"></span>

    <div class="relative mx-auto max-w-xl px-4 py-12 lg:px-6 lg:py-16">
        <div class="reveal rounded-card border border-brand-100 bg-white p-6 shadow-card sm:p-8" data-reveal>
            <span class="animate-pop inline-flex items-center gap-2 rounded-pill border border-emerald-200 bg-emerald-50 px-3 py-1.5 text-xs font-black uppercase tracking-widest text-emerald-800">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true">
                    <path d="m5 13 4 4L19 7" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                Link verified
            </span>

            <h1 class="mt-4 text-2xl font-black tracking-tight text-ink md:text-3xl">Pick a new password</h1>
            <p class="mt-2 text-sm leading-relaxed text-ink-400">
                This link was good for one use. Once you save a new password it is spent, and anything
                older stops working too - so a link someone forwarded cannot be reused later.
            </p>

            <form method="post" action="<?= e(route('password.reset.store')) ?>" class="mt-6 space-y-5">
                <?= csrf_field() ?>
                <?php /* The token is a secret: it rides in the body, never in a link or a log. */ ?>
                <input type="hidden" name="token" value="<?= e($token) ?>">

                <?= $view->component('field', [
                    'name' => 'password',
                    'label' => 'New password',
                    'type' => 'password',
                    'placeholder' => 'At least 8 characters',
                    'required' => true,
                    'autocomplete' => 'new-password',
                    'hint' => '8-200 characters, with at least one letter and one number.',
                ]) ?>

                <?= $view->component('field', [
                    'name' => 'password_confirmation',
                    'label' => 'Repeat the new password',
                    'type' => 'password',
                    'placeholder' => 'Same again',
                    'required' => true,
                    'autocomplete' => 'new-password',
                ]) ?>

                <button type="submit" class="group relative w-full overflow-hidden rounded-brand bg-gradient-to-r from-brand-600 via-brand-500 to-accent-500 px-6 py-3.5 text-sm font-black text-white shadow-glow transition-transform duration-200 hover:-translate-y-0.5">
                    <span class="animate-shimmer absolute inset-0 bg-gradient-to-r from-white/0 via-white/35 to-white/0" aria-hidden="true"></span>
                    <span class="relative inline-flex items-center justify-center gap-2">
                        Save the new password
                        <svg class="h-4 w-4 transition-transform duration-200 group-hover:translate-x-1" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true">
                            <path d="M5 12h14M13 6l6 6-6 6" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </span>
                </button>
            </form>

            <ul class="mt-7 space-y-2 border-t border-dashed border-brand-100 pt-5 text-xs leading-relaxed text-ink-400">
                <li class="flex gap-2">
                    <span class="mt-0.5 text-accent-500" aria-hidden="true">&#10003;</span>
                    We store a hash, not the password - so not even staff can read it back.
                </li>
                <li class="flex gap-2">
                    <span class="mt-0.5 text-accent-500" aria-hidden="true">&#10003;</span>
                    Your subscriptions stay exactly as they were; only the site login changes.
                </li>
                <li class="flex gap-2">
                    <span class="mt-0.5 text-accent-500" aria-hidden="true">&#10003;</span>
                    After saving, sign in again - the reset link does not log you in by itself.
                </li>
            </ul>
        </div>

        <p class="mt-6 text-center text-sm">
            <a href="<?= e(route('login')) ?>" class="font-bold text-brand-700 underline decoration-accent-400 decoration-2 underline-offset-4">Back to sign in</a>
            <span class="text-ink-400"> &middot; </span>
            <a href="<?= e(wa_link('Hi ' . $brandName . ', my reset link is not working.')) ?>" target="_blank" rel="noopener" class="font-semibold text-emerald-700 hover:text-emerald-800">
                Link not working?
            </a>
        </p>
    </div>
</div>
