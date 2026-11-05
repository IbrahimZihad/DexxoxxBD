<?php

declare(strict_types=1);

/**
 * Contact. Data comes from PageController::contact():
 * $prefillName, $prefillEmail, $prefillPhone, $whatsappUrl, $hours,
 * $deliveryMinutes, $holdMinutes.
 *
 * The form posts to the same URL it renders on. It is marked [data-ajax] so the
 * shipped JS can toast the answer, but it degrades to a normal post + flash if
 * the bundle never loads.
 *
 * @var App\Core\View $view
 * @var string $prefillName
 * @var string $prefillEmail
 * @var string $prefillPhone
 * @var string $whatsappUrl
 * @var string $hours
 * @var int $deliveryMinutes
 * @var int $holdMinutes
 */
$brandName = setting('brand.name', 'ZapSub');
$email = setting('contact.email', 'our support address');
$phone = setting('contact.phone');
$address = setting('contact.address');
$agentName = setting('whatsapp.agent_name', 'the order desk');

$view->extend('layouts/app');
$view->setSection('title', 'Contact - ' . $brandName);
$view->setSection('meta_description', 'Talk to ' . $brandName . ': WhatsApp during opening hours, email, or the contact form. A person answers every thread - no ticket numbers.');
$view->setSection('canonical', route('contact'));

$channels = array_values(array_filter([
    ['label' => 'WhatsApp (fastest)', 'value' => $hours, 'href' => $whatsappUrl, 'icon' => 'chat'],
    $email === '' ? null : ['label' => 'Email', 'value' => $email, 'href' => 'mailto:' . $email, 'icon' => 'mail'],
    $phone === '' ? null : ['label' => 'Phone', 'value' => $phone, 'href' => 'tel:' . preg_replace('/[^0-9+]/', '', $phone), 'icon' => 'phone'],
    $address === '' ? null : ['label' => 'Where we work from', 'value' => $address, 'href' => '', 'icon' => 'pin'],
], static fn (?array $row): bool => $row !== null));

$icons = [
    'chat' => '<path d="M4 5h16v10H8l-4 4V5Z" stroke-linejoin="round"/>',
    'mail' => '<rect x="3" y="6" width="18" height="12" rx="2.5"/><path d="m4 8 8 5 8-5"/>',
    'phone' => '<path d="M6 4h3l1.6 4-2 1.4a11 11 0 0 0 5 5l1.4-2 4 1.6V18a2 2 0 0 1-2.2 2A15 15 0 0 1 4 6.2 2 2 0 0 1 6 4Z" stroke-linejoin="round"/>',
    'pin' => '<path d="M12 21s6-6.2 6-10.4A6 6 0 0 0 6 10.6C6 14.8 12 21 12 21Z"/><circle cx="12" cy="10.4" r="2.2"/>',
];

$promises = [
    ['A human, not a bot', 'Every message lands in one inbox that ' . $agentName . ' reads. Replies come during the opening hours on the right.'],
    ['Order number speeds it up', 'Start with your order number (the ZS- style code in your confirmation) and we can look the delivery up immediately.'],
    ['Payment questions are fine here', 'Send the transaction ID and the number you paid from - never your bKash or Nagad PIN, and never your password.'],
];
?>
<div class="bg-white">
    <section class="relative overflow-hidden bg-gradient-to-b from-brand-50 via-white to-white">
        <span class="blob -left-20 -top-16 animate-blob opacity-40"></span>
        <span class="blob -right-16 top-12 animate-blob opacity-35 [animation-delay:-5s]"></span>
        <span class="blob bottom-[-7rem] left-1/3 animate-blob opacity-30 [animation-delay:-9s]"></span>

        <div class="relative mx-auto max-w-7xl px-4 py-12 lg:px-6 lg:py-16">
            <div class="reveal mx-auto max-w-2xl text-center" data-reveal>
                <p class="inline-flex items-center gap-2 rounded-pill border border-brand-200 bg-white/80 px-3.5 py-1.5 text-xs font-black uppercase tracking-[0.18em] text-brand-700 shadow-soft">
                    <span class="h-2 w-2 animate-pulse-ring rounded-full bg-emerald-400"></span>
                    <?= e($hours) ?>
                </p>
                <h1 class="mt-4 text-4xl font-black leading-[1.05] tracking-tight md:text-5xl">
                    <span class="animate-gradient text-gradient">Talk to a real person at <?= e($brandName) ?></span>
                </h1>
                <p class="mt-4 text-base leading-relaxed text-ink-400">
                    Choosing a plan, a payment that did not line up, an account that stopped mid-month -
                    all of it lands in the same inbox, and all of it is answered by whoever is on the
                    order desk. No ticket numbers, no phone-tree.
                </p>
            </div>
        </div>
    </section>

    <section class="mx-auto max-w-7xl px-4 pb-16 lg:px-6">
        <div class="grid gap-8 lg:grid-cols-[1.15fr_0.85fr] lg:items-start">
            {{-- ------------------------------ the form ------------------------------ --}}
            <div class="reveal rounded-card border border-brand-100 bg-white p-6 shadow-card sm:p-8" data-reveal style="--reveal-delay:80ms">
                <h2 class="text-2xl font-black tracking-tight text-ink">Send a message</h2>
                <p class="mt-2 text-sm leading-relaxed text-ink-400">
                    Ten characters is the shortest thing we can usefully act on. Anything longer than a
                    paragraph and we would honestly rather read it on WhatsApp.
                </p>

                <form method="post" action="<?= e(route('contact.send')) ?>" data-ajax class="mt-6 space-y-5">
                    <?= csrf_field() ?>

                    <div class="grid gap-5 sm:grid-cols-2">
                        <?= $view->component('field', [
                            'name' => 'name',
                            'label' => 'Your name',
                            'type' => 'text',
                            'value' => old('name', $prefillName),
                            'placeholder' => 'Nahian',
                            'required' => true,
                            'autocomplete' => 'name',
                        ]) ?>

                        <?= $view->component('field', [
                            'name' => 'email',
                            'label' => 'Email',
                            'type' => 'email',
                            'value' => old('email', $prefillEmail),
                            'placeholder' => 'you@example.com',
                            'required' => true,
                            'autocomplete' => 'email',
                            'hint' => 'Where the answer goes.',
                        ]) ?>
                    </div>

                    <div class="grid gap-5 sm:grid-cols-2">
                        <?= $view->component('field', [
                            'name' => 'phone',
                            'label' => 'Phone (bKash / WhatsApp number)',
                            'type' => 'tel',
                            'value' => old('phone', $prefillPhone),
                            'placeholder' => '01712345678',
                            'required' => true,
                            'autocomplete' => 'tel',
                            'inputmode' => 'tel',
                            'hint' => 'Bangladeshi number, 01700000000 style. We use it to find your payment.',
                        ]) ?>

                        <?= $view->component('field', [
                            'name' => 'subject',
                            'label' => 'Subject (optional)',
                            'type' => 'text',
                            'value' => old('subject'),
                            'placeholder' => 'Order ZS-XXXX - account not working',
                            'autocomplete' => 'off',
                        ]) ?>
                    </div>

                    <?= $view->component('field', [
                        'name' => 'message',
                        'label' => 'What is going on?',
                        'type' => 'textarea',
                        'value' => old('message'),
                        'placeholder' => 'Tell us the plan, when you paid, and what happened. Include the transaction ID if it is a payment question.',
                        'required' => true,
                        'rows' => 6,
                        'autocomplete' => 'off',
                        'hint' => 'Please do not include your password, your bKash or Nagad PIN, or anything else secret.',
                    ]) ?>

                    <div class="flex flex-wrap items-center gap-3">
                        <button type="submit" class="group relative inline-flex items-center gap-2 overflow-hidden rounded-brand bg-gradient-to-r from-brand-600 via-brand-500 to-accent-500 px-6 py-3.5 text-sm font-black text-white shadow-glow transition-transform duration-200 hover:-translate-y-0.5">
                            <span class="animate-shimmer absolute inset-0 bg-gradient-to-r from-white/0 via-white/35 to-white/0" aria-hidden="true"></span>
                            <span class="relative">Send the message</span>
                            <svg class="relative h-4 w-4 transition-transform duration-200 group-hover:translate-x-1" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true">
                                <path d="M5 12h14M13 6l6 6-6 6" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </button>

                        <a href="<?= e($whatsappUrl) ?>" target="_blank" rel="noopener" class="inline-flex items-center gap-2 rounded-brand border-2 border-emerald-200 bg-emerald-50 px-5 py-3 text-sm font-bold text-emerald-800 transition-colors duration-200 hover:border-emerald-400 hover:bg-emerald-100">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" aria-hidden="true">
                                <path d="M4 5h16v10H8l-4 4V5Z" stroke-linejoin="round"/>
                            </svg>
                            WhatsApp instead
                        </a>
                    </div>
                </form>

                <div class="mt-8 grid gap-4 border-t border-dashed border-brand-100 pt-6 sm:grid-cols-3">
                    <?php foreach ($promises as $index => [$promiseTitle, $promiseText]): ?>
                        <div class="reveal rounded-brand bg-brand-50/60 p-4" data-reveal style="--reveal-delay:<?= e((string) (100 + $index * 90)) ?>ms">
                            <p class="text-sm font-bold text-ink"><?= e($promiseTitle) ?></p>
                            <p class="mt-1 text-xs leading-relaxed text-ink-400"><?= e($promiseText) ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            {{-- ------------------------------ the side card ------------------------------ --}}
            <div class="space-y-6">
                <div class="reveal relative overflow-hidden rounded-card bg-gradient-to-br from-brand-700 via-brand-600 to-accent-600 p-6 text-white shadow-pop" data-reveal style="--reveal-delay:140ms">
                    <span class="blob -right-10 -top-12 animate-blob bg-white/15" aria-hidden="true"></span>
                    <p class="relative text-xs font-black uppercase tracking-[0.2em] text-white/70">Fastest route</p>
                    <h3 class="relative mt-2 text-2xl font-black leading-tight">WhatsApp the order desk</h3>
                    <p class="relative mt-2 text-sm leading-relaxed text-white/85">
                        <?= e(setting('whatsapp.greeting', 'Hi! Need a hand choosing a plan?')) ?>
                        One tap opens a chat with <?= e(setting('whatsapp.agent_name', 'ZapSub Support')) ?>
                        (<?= e(setting('whatsapp.agent_role', 'Order desk')) ?>).
                    </p>
                    <a href="<?= e($whatsappUrl) ?>" target="_blank" rel="noopener" class="relative mt-5 flex items-center justify-center gap-2 rounded-brand bg-white px-5 py-3.5 text-sm font-black text-brand-700 shadow-soft transition-transform duration-200 hover:-translate-y-0.5">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path d="M4 5h16v10H8l-4 4V5Z" stroke-linejoin="round"/>
                        </svg>
                        Open WhatsApp - <?= e(wa_number() === '' ? 'chat' : wa_number()) ?>
                    </a>
                    <p class="relative mt-3 text-xs leading-relaxed text-white/75">
                        Open <?= e($hours) ?>. Messages outside those hours get answered when the desk opens.
                    </p>
                </div>

                <div class="reveal rounded-card border border-brand-100 bg-white p-6 shadow-card" data-reveal style="--reveal-delay:200ms">
                    <h3 class="text-lg font-black tracking-tight text-ink">Every way to reach us</h3>
                    <ul class="mt-4 space-y-3">
                        <?php foreach ($channels as $channel): ?>
                            <li class="flex items-start gap-3 rounded-brand border border-brand-50 bg-brand-50/40 p-3">
                                <span class="grid h-9 w-9 shrink-0 place-items-center rounded-brand bg-gradient-to-br from-brand-600 to-accent-500 text-white shadow-pop">
                                    <?php /* This file's own icon whitelist, never request input. */ ?>
                                    <svg class="h-4.5 w-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                        <?= $icons[$channel['icon']] ?? $icons['mail'] ?>
                                    </svg>
                                </span>
                                <span class="min-w-0">
                                    <span class="block text-xs font-black uppercase tracking-wider text-brand-600"><?= e($channel['label']) ?></span>
                                    <?php if ($channel['href'] === ''): ?>
                                        <span class="block text-sm font-semibold text-ink"><?= e($channel['value']) ?></span>
                                    <?php else: ?>
                                        <a href="<?= e($channel['href']) ?>" <?php if (str_starts_with($channel['href'], 'http')): ?>target="_blank" rel="noopener" <?php endif; ?>class="block break-words text-sm font-semibold text-ink hover:text-brand-700"><?= e($channel['value']) ?></a>
                                    <?php endif; ?>
                                </span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>

                <div class="reveal rounded-card border border-amber-200 bg-amber-50/70 p-6" data-reveal style="--reveal-delay:260ms">
                    <h3 class="flex items-center gap-2 text-lg font-black tracking-tight text-amber-900">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" aria-hidden="true">
                            <circle cx="12" cy="12" r="9"/><path d="M12 7v6l4 2" stroke-linecap="round"/>
                        </svg>
                        Delivery hours
                    </h3>
                    <p class="mt-2 text-sm leading-relaxed text-amber-900/80">
                        Accounts go out by hand, so delivery only happens while the desk is open:
                        <strong><?= e($hours) ?></strong>. Inside those hours the average handover is
                        about <?= e((string) $deliveryMinutes) ?> minutes after a payment checks out.
                    </p>
                    <p class="mt-3 text-sm leading-relaxed text-amber-900/80">
                        An unpaid order holds its price and its stock for <?= e((string) $holdMinutes) ?>
                        minutes. If you pay late on purpose, just say so - we would rather reopen it than
                        cancel it.
                    </p>
                    <a href="<?= e(route('track')) ?>" class="mt-4 inline-flex items-center gap-2 rounded-brand bg-amber-600 px-4 py-2.5 text-sm font-black text-white shadow-soft transition-colors duration-200 hover:bg-amber-700">
                        Track an order you already paid for
                    </a>
                </div>

                <div class="reveal rounded-card bg-ink p-6 text-white shadow-card" data-reveal style="--reveal-delay:320ms">
                    <h3 class="text-lg font-black tracking-tight">Rather not write to us at all?</h3>
                    <p class="mt-2 text-sm leading-relaxed text-white/70">
                        Most answers are already on the FAQ page - pricing, replacement windows, which
                        payment numbers are ours, and what we do with your data.
                    </p>
                    <div class="mt-4 flex flex-wrap gap-3">
                        <a href="<?= e(route('faq')) ?>" class="rounded-brand bg-white/10 px-4 py-2.5 text-sm font-bold text-white transition-colors duration-200 hover:bg-white/20">Read the FAQ</a>
                        <a href="<?= e(route('how')) ?>" class="rounded-brand bg-white/10 px-4 py-2.5 text-sm font-bold text-white transition-colors duration-200 hover:bg-white/20">How ordering works</a>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>
