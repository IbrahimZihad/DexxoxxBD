<?php

declare(strict_types=1);

/**
 * Request a password reset. The page never says whether an address is
 * registered: one sentence covers both cases, and the mail (when there is one)
 * goes out with a single-use link that dies in an hour.
 *
 * @var App\Core\View $view
 */
$brandName = setting('brand.name', 'DexxozzBD');

$view->extend('layouts/app');
$view->setSection('title', 'Forgot password - ' . $brandName);
$view->setSection('meta_description', 'Reset your ' . $brandName . ' password with a single-use email link that expires in one hour.');
$view->setSection('canonical', route('password.forgot'));

$steps = [
    ['Tell us the email', 'The one you used when you ordered - not the one on your Netflix account.'],
    ['Open the link', 'It arrives from ' . setting('contact.email', 'our support address') . ' and works once, within an hour.'],
    ['Pick a new password', 'Eight characters or more. Old passwords stop working at that moment.'],
];
?>
<div class="relative overflow-hidden bg-gradient-to-b from-brand-50 via-white to-white">
    <span class="blob -left-24 top-10 animate-blob opacity-35" aria-hidden="true"></span>
    <span class="blob -right-20 bottom-[-6rem] animate-blob opacity-30 [animation-delay:-6s]" aria-hidden="true"></span>

    <div class="relative mx-auto max-w-3xl px-4 py-12 lg:px-6 lg:py-16">
        <div class="reveal mx-auto max-w-xl text-center" data-reveal>
            <span class="animate-float mx-auto grid h-16 w-16 place-items-center rounded-card bg-gradient-to-br from-brand-600 to-accent-500 shadow-pop">
                <svg class="h-8 w-8 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" aria-hidden="true">
                    <rect x="4" y="10" width="16" height="10" rx="2.5"/>
                    <path d="M8 10V7.5a4 4 0 0 1 8 0V10" stroke-linecap="round"/>
                    <path d="M12 14v2.5" stroke-linecap="round"/>
                </svg>
            </span>
            <h1 class="mt-5 text-3xl font-black tracking-tight md:text-4xl">
                <span class="animate-gradient text-gradient">Reset your password</span>
            </h1>
            <p class="mt-3 text-sm leading-relaxed text-ink-400">
                No account with that address? Then nothing is sent, and this page says the same thing
                either way - that is on purpose, so it cannot be used to find out who orders from us.
            </p>
        </div>

        <div class="reveal mt-9 rounded-card border border-brand-100 bg-white p-6 shadow-card sm:p-8" data-reveal style="--reveal-delay:120ms">
            <form method="post" action="<?= e(route('password.forgot.send')) ?>" class="space-y-5">
                <?= csrf_field() ?>

                <?= $view->component('field', [
                    'name' => 'email',
                    'label' => 'Email on the account',
                    'type' => 'email',
                    'placeholder' => 'you@example.com',
                    'required' => true,
                    'autocomplete' => 'email',
                    'hint' => 'Check the spelling - a link for a different address is a different account.',
                ]) ?>

                <button type="submit" class="group relative w-full overflow-hidden rounded-brand bg-gradient-to-r from-brand-600 via-brand-500 to-accent-500 px-6 py-3.5 text-sm font-black text-white shadow-glow transition-transform duration-200 hover:-translate-y-0.5">
                    <span class="animate-shimmer absolute inset-0 bg-gradient-to-r from-white/0 via-white/35 to-white/0" aria-hidden="true"></span>
                    <span class="relative">Email me a reset link</span>
                </button>

                <p class="rounded-brand bg-brand-50/70 px-4 py-3 text-xs leading-relaxed text-ink-400">
                    If that address has an account, a link is on its way. It expires in one hour and can be
                    used once. Nothing else changes until you actually open it.
                </p>
            </form>

            <ol class="mt-8 space-y-4 border-t border-dashed border-brand-100 pt-6">
                <?php foreach ($steps as $index => [$stepTitle, $stepText]): ?>
                    <li class="flex gap-3">
                        <span class="grid h-8 w-8 shrink-0 place-items-center rounded-pill bg-gradient-to-br from-brand-600 to-brand-500 text-xs font-black text-white shadow-pop">
                            <?= e((string) ($index + 1)) ?>
                        </span>
                        <span>
                            <span class="block text-sm font-bold text-ink"><?= e($stepTitle) ?></span>
                            <span class="mt-0.5 block text-xs leading-relaxed text-ink-400"><?= e($stepText) ?></span>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ol>
        </div>

        <div class="mt-7 flex flex-wrap items-center justify-center gap-x-5 gap-y-2 text-sm">
            <a href="<?= e(route('login')) ?>" class="font-bold text-brand-700 underline decoration-accent-400 decoration-2 underline-offset-4">Back to sign in</a>
            <a href="<?= e(route('register')) ?>" class="font-semibold text-ink-400 hover:text-brand-700">Create an account instead</a>
            <a href="<?= e(wa_link('Hi ' . $brandName . ', I cannot get into my account and I need a reset link.')) ?>"
               target="_blank" rel="noopener"
               class="font-semibold text-emerald-700 hover:text-emerald-800">
                Still stuck? WhatsApp us
            </a>
        </div>
    </div>
</div>
