<?php

declare(strict_types=1);

/**
 * The three-step tracker shared by the payment page and the order page: send it,
 * we verify it, we hand it over. States come from the order row only, so the
 * tracker can never run ahead of what a human has actually done.
 *
 * @var string $paymentStatus
 * @var string $fulfillmentStatus
 */
$paymentStatus = (string) ($paymentStatus ?? 'unpaid');
$fulfillmentStatus = (string) ($fulfillmentStatus ?? 'unassigned');

$states = [
    'send' => $paymentStatus === 'unpaid' ? 'active' : 'done',
    'verify' => match ($paymentStatus) {
        'paid' => 'done',
        'pending_verification' => 'active',
        'refunded' => 'done',
        default => 'todo',
    },
    'deliver' => match (true) {
        $fulfillmentStatus === 'delivered' => 'done',
        $paymentStatus === 'paid' => 'active',
        default => 'todo',
    },
];

$steps = [
    ['key' => 'send', 'label' => 'You send it', 'hint' => 'Reference submitted on this page'],
    ['key' => 'verify', 'label' => 'We verify', 'hint' => 'A person reads their own wallet statement'],
    ['key' => 'deliver', 'label' => 'Delivered', 'hint' => 'Login details arrive on WhatsApp'],
];

$schema = [
    'done' => ['chip' => 'bg-gradient-to-br from-brand-600 to-accent-500 text-white shadow-glow', 'text' => 'text-brand-700', 'mark' => 'done'],
    'active' => ['chip' => 'bg-white text-amber-600 ring-2 ring-amber-300 animate-pulse-ring', 'text' => 'text-ink', 'mark' => 'now'],
    'todo' => ['chip' => 'bg-white text-ink-400 ring-1 ring-brand-100', 'text' => 'text-ink-400', 'mark' => 'next'],
];
?>
<ol class="grid gap-3 sm:grid-cols-3">
    <?php foreach ($steps as $index => $step): ?>
        <?php
        $state = $schema[$states[$step['key']]];
        $isNow = $states[$step['key']] === 'active';
        ?>
        <li <?= e($isNow ? 'aria-current="step"' : '') ?> class="flex items-center gap-3 rounded-card border border-brand-100 bg-white p-3 shadow-soft">
            <span class="grid h-9 w-9 shrink-0 place-items-center rounded-full text-sm font-black <?= e($state['chip']) ?>" aria-hidden="true">
                <?php if ($state['mark'] === 'done'): ?>
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" aria-hidden="true"><path d="m8 12.5 2.6 2.6L16.5 9" stroke-linecap="round" stroke-linejoin="round"/></svg>
                <?php else: ?>
                    <?= e((string) ($index + 1)) ?>
                <?php endif; ?>
            </span>
            <span class="min-w-0">
                <span class="block text-sm font-black leading-tight <?= e($state['text']) ?>"><?= e($step['label']) ?></span>
                <span class="mt-0.5 block text-[11px] leading-snug text-ink-400"><?= e($step['hint']) ?></span>
            </span>
        </li>
    <?php endforeach; ?>
</ol>
