<?php

declare(strict_types=1);

/**
 * Create a customer account. The rules below mirror PASSWORD_RULES in
 * App\Controllers\AuthController exactly - they are printed as a static list, so
 * there is no script pretending to check them while you type.
 *
 * @var App\Core\View $view
 */
$brandName = setting('brand.name', 'ZapSub');

$view->extend('layouts/app');
$view->setSection('title', 'Create an account - ' . $brandName);
$view->setSection('meta_description', 'Open a free ' . $brandName . ' account to keep every subscription order, payment status and delivery note in one place.');
$view->setSection('canonical', route('register'));

$rules = [
    ['At least 8 characters', 'Longer is better - a phrase you can type fast beats a puzzle.'],
    ['One letter and one number', 'Just those two kinds, anywhere in the password.'],
    ['Not your WhatsApp number', 'It is the first thing anyone guessing will try.'],
];

$perks = [
    ['Checkout remembers you', 'Name, email and number are filled in before you start.'],
    ['Delivery history', 'Every account we handed over stays attached to the order.'],
    ['Replacement without an argument', 'We can see the order, so you do not have to prove it.'],
];
?>
<div class="relative overflow-hidden bg-gradient-to-b from-accent-50 via-white to-white">
    <span class="blob -right-28 -top-24 animate-blob opacity-40" aria-hidden="true"></span>
    <span class="blob -left-24 bottom-[-6rem] animate-blob opacity-30 [animation-delay:-9s]" aria-hidden="true"></span>

    <div class="relative mx-auto max-w-6xl px-4 py-10 lg:px-6 lg:py-16">
        <div class="reveal mx-auto max-w-2xl text-center" data-reveal>
            <p class="inline-flex items-center gap-2 rounded-pill border border-brand-200 bg-white/80 px-3.5 py-1.5 text-xs font-black uppercase tracking-widest text-brand-700 shadow-soft">
                <span class="h-2 w-2 animate-pulse-ring rounded-full bg-accent-400" aria-hidden="true"></span>
                Free, and it takes a minute
            </p>
            <h1 class="mt-4 text-3xl font-black tracking-tight md:text-5xl">
                <span class="animate-gradient text-gradient">Keep every subscription in one account</span>
            </h1>
            <p class="mt-3 text-sm leading-relaxed text-ink-400 md:text-base">
                You can still buy as a guest. An account just means your orders, payment states and
                delivery notes stay where you can find them.
            </p>
        </div>

        <div class="mt-10 grid gap-6 lg:grid-cols-[1.25fr_0.75fr] lg:items-start">
            <section class="reveal rounded-card border border-brand-100 bg-white p-6 shadow-card sm:p-8" data-reveal aria-label="Registration form">
                <form method="post" action="<?= e(route('register.attempt')) ?>" class="space-y-5">
                    <?= csrf_field() ?>

                    <?= $view->component('field', [
                        'name' => 'name',
                        'label' => 'Your name',
                        'type' => 'text',
                        'placeholder' => 'Nusrat Jahan',
                        'required' => true,
                        'autocomplete' => 'name',
                        'hint' => 'We use it on your order and when we greet you on WhatsApp.',
                    ]) ?>

                    <div class="grid gap-5 sm:grid-cols-2">
                        <?= $view->component('field', [
                            'name' => 'email',
                            'label' => 'Email',
                            'type' => 'email',
                            'placeholder' => 'you@example.com',
                            'required' => true,
                            'autocomplete' => 'email',
                            'hint' => 'Order confirmations go here, so use an address you can open.',
                        ]) ?>

                        <?= $view->component('field', [
                            'name' => 'phone',
                            'label' => 'Mobile number',
                            'type' => 'tel',
                            'placeholder' => '01712-345678',
                            'required' => true,
                            'autocomplete' => 'tel',
                            'inputmode' => 'tel',
                            'hint' => 'Bangladeshi number - it is what we message when the plan is ready.',
                        ]) ?>
                    </div>

                    <div class="rounded-brand border border-brand-100 bg-brand-50/60 p-4">
                        <p class="text-xs font-black uppercase tracking-widest text-brand-700">Password rules</p>
                        <ul class="mt-3 space-y-2">
                            <?php foreach ($rules as [$ruleTitle, $ruleNote]): ?>
                                <li class="flex items-start gap-2 text-sm">
                                    <svg class="mt-0.5 h-4 w-4 shrink-0 text-accent-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true">
                                        <path d="m5 13 4 4L19 7" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                    <span>
                                        <span class="font-bold text-ink"><?= e($ruleTitle) ?></span>
                                        <span class="block text-xs text-ink-400"><?= e($ruleNote) ?></span>
                                    </span>
                                </li>
                            <?php endforeach; ?>
                        </ul>

                        <div class="mt-4 grid gap-4 sm:grid-cols-2">
                            <?= $view->component('field', [
                                'name' => 'password',
                                'label' => 'Password',
                                'type' => 'password',
                                'placeholder' => 'At least 8 characters',
                                'required' => true,
                                'autocomplete' => 'new-password',
                            ]) ?>

                            <?= $view->component('field', [
                                'name' => 'password_confirmation',
                                'label' => 'Repeat password',
                                'type' => 'password',
                                'placeholder' => 'Same again',
                                'required' => true,
                                'autocomplete' => 'new-password',
                            ]) ?>
                        </div>

                        <p class="mt-3 text-xs leading-relaxed text-ink-400">
                            Both boxes have to match. We store an Argon2id hash - never the password itself,
                            and never in a log.
                        </p>
                    </div>

                    <div class="space-y-3">
                        <?= $view->component('field', [
                            'name' => 'accept_terms',
                            'label' => 'I have read the terms of sale and the privacy policy.',
                            'type' => 'checkbox',
                            'required' => true,
                            'hint' => 'Buying a subscription is a digital purchase, so the refund rules matter - they are short.',
                        ]) ?>

                        <?= $view->component('field', [
                            'name' => 'marketing_opt_in',
                            'label' => 'Message me when a big sale or a new brand lands.',
                            'type' => 'checkbox',
                            'hint' => 'Occasional WhatsApp or email. Say the word once and we stop - no way to unsubscribe into a void.',
                        ]) ?>
                    </div>

                    <p class="text-xs leading-relaxed text-ink-400">
                        By registering you agree to our
                        <a href="<?= e(route('terms')) ?>" class="font-bold text-brand-700 underline underline-offset-2">terms of sale</a>
                        and
                        <a href="<?= e(route('privacy')) ?>" class="font-bold text-brand-700 underline underline-offset-2">privacy policy</a>.
                    </p>

                    <button type="submit" class="group relative w-full overflow-hidden rounded-brand bg-gradient-to-r from-brand-600 via-brand-500 to-accent-500 px-6 py-3.5 text-sm font-black text-white shadow-glow transition-transform duration-200 hover:-translate-y-0.5">
                        <span class="animate-shimmer absolute inset-0 bg-gradient-to-r from-white/0 via-white/35 to-white/0" aria-hidden="true"></span>
                        <span class="relative inline-flex items-center justify-center gap-2">
                            Create my account
                            <svg class="h-4 w-4 transition-transform duration-200 group-hover:translate-x-1" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true">
                                <path d="M5 12h14M13 6l6 6-6 6" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </span>
                    </button>

                    <p class="text-center text-sm text-ink-400">
                        Already have an account?
                        <a href="<?= e(route('login')) ?>" class="font-black text-brand-700 underline decoration-accent-400 decoration-2 underline-offset-4">Sign in</a>
                    </p>
                </form>
            </section>

            <aside class="space-y-5">
                <div class="reveal glass rounded-card p-6" data-reveal style="--reveal-delay:120ms">
                    <p class="text-xs font-black uppercase tracking-widest text-brand-600">What you get</p>
                    <ul class="mt-4 space-y-4">
                        <?php foreach ($perks as $index => $perk): ?>
                            <li class="flex gap-3">
                                <span class="animate-float grid h-8 w-8 shrink-0 place-items-center rounded-brand bg-gradient-to-br from-brand-600 to-accent-500 text-xs font-black text-white shadow-pop" style="animation-delay:<?= e((string) ($index * 350)) ?>ms">
                                    <?= e((string) ($index + 1)) ?>
                                </span>
                                <span class="text-sm leading-relaxed text-ink-700"><?= e($perk[0]) ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>

                <div class="reveal rounded-card border border-emerald-200 bg-emerald-50 p-6" data-reveal style="--reveal-delay:220ms">
                    <p class="text-sm font-black text-emerald-900">No account? Fine.</p>
                    <p class="mt-2 text-sm leading-relaxed text-emerald-900/80">
                        Guest checkout works, and
                        <a href="<?= e(route('track')) ?>" class="font-bold underline underline-offset-2">track an order</a>
                        opens with just your order number and email.
                    </p>
                    <a href="<?= e(wa_link('Hi ' . $brandName . ', I want to order but I do not want to register.')) ?>"
                       target="_blank" rel="noopener"
                       class="mt-4 inline-flex items-center gap-2 rounded-brand bg-white px-4 py-2.5 text-sm font-black text-emerald-800 ring-1 ring-emerald-200 transition-transform duration-200 hover:-translate-y-0.5">
                        <svg class="h-4 w-4 text-emerald-600" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                            <path d="M12 2a10 10 0 0 0-8.6 15L2 22l5.2-1.3A10 10 0 1 0 12 2Zm5.3 14c-.2.6-1.2 1.2-1.7 1.2-.5.1-1 .1-1.7-.1-.9-.3-2.3-1-3.6-2.6-1-1.2-1.6-2.5-1.7-3-.1-.6.3-1.4.8-1.8.2-.2.4-.2.6-.2h.5c.2 0 .3 0 .4.3l.6 1.4c.1.2 0 .4-.1.5l-.4.5c-.1.1-.1.3 0 .4.3.5 1.2 1.5 2.1 1.9.2.1.3.1.5-.1l.5-.6c.1-.2.3-.2.5-.1l1.4.7c.2.1.3.2.3.4 0 .2 0 .7-.3 1.2Z"/>
                        </svg>
                        Order over WhatsApp
                    </a>
                </div>

                <div class="reveal rounded-card border border-brand-100 bg-white p-6" data-reveal style="--reveal-delay:320ms">
                    <p class="text-sm font-black text-ink">How we handle your data</p>
                    <ul class="mt-3 space-y-2 text-xs leading-relaxed text-ink-400">
                        <li>We keep your name, email, number and your orders - nothing else.</li>
                        <li>Login details we hand over are encrypted at rest.</li>
                        <li>Say the word on WhatsApp and we delete the account.</li>
                    </ul>
                    <a href="<?= e(route('privacy')) ?>" class="mt-4 inline-block text-xs font-black text-brand-700 underline decoration-accent-400 decoration-2 underline-offset-4">
                        Read the privacy policy
                    </a>
                </div>
            </aside>
        </div>
    </div>
</div>
