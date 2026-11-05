<?php

declare(strict_types=1);

/**
 * Inside of the live status slot [data-order-status]. The poll script rewrites
 * only the data-status-* cells from the closed wordings below, and the
 * controllers swap this whole file in as status_html, so the customer always
 * sees server-written truth - never a client-side guess.
 *
 * @var array<string, mixed> $order
 */
$order = (array) ($order ?? []);
$paymentStatus = (string) ($order['payment_status'] ?? 'unpaid');
$status = (string) ($order['status'] ?? 'pending');
$fulfillment = (string) ($order['fulfillment_status'] ?? 'unassigned');

$note = match ($paymentStatus) {
    'paid' => $fulfillment === 'delivered'
        ? 'Verified and delivered. Check the credentials we sent you.'
        : 'Payment verified - your plan is with the delivery desk now.',
    'pending_verification' => 'Your reference is in the queue. A person checks the statement by hand, usually within minutes during open hours.',
    'refunded' => 'This order was refunded. Message us on WhatsApp if that was not expected.',
    default => 'No payment recorded yet. Send the amount and submit your reference so we can match it.',
};

if ($status === 'cancelled') {
    $note = 'This order was cancelled. Nothing is owed - start a new one whenever you are ready.';
}

$dotClass = match ($paymentStatus) {
    'paid' => 'bg-emerald-500',
    'pending_verification' => 'bg-amber-500 animate-pulse-ring',
    'refunded' => 'bg-rose-500',
    default => 'bg-slate-400 animate-pulse-ring',
};
?>
<div class="flex flex-wrap items-center gap-2.5">
    <span data-status-dot class="h-3 w-3 shrink-0 rounded-full <?= e($dotClass) ?>" aria-hidden="true"></span>
    <span class="text-sm font-black text-ink">Payment: <span data-status-payment><?= e(status_label($paymentStatus)) ?></span></span>
    <span class="text-xs font-semibold text-ink-400">Order <span data-status-order><?= e(status_label($status)) ?></span></span>
    <span class="text-xs font-semibold text-ink-400">Delivery <span data-status-fulfillment><?= e(status_label($fulfillment)) ?></span></span>
</div>

<p data-status-note class="mt-2 text-xs leading-relaxed text-ink-400"><?= e($note) ?></p>

<div data-status-paid-badge <?= e($paymentStatus === 'paid' ? '' : 'hidden') ?> class="mt-3 flex items-center gap-2 rounded-brand bg-emerald-50 p-3 text-sm font-bold text-emerald-800 ring-1 ring-emerald-200">
    <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true">
        <circle cx="12" cy="12" r="9"/><path d="m8.5 12.5 2.5 2.5 4.5-5.5" stroke-linecap="round" stroke-linejoin="round"/>
    </svg>
    Verified by our team.
</div>
