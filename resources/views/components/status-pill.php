<?php

declare(strict_types=1);

/**
 * Order / payment status pill. status_badge() returns a closed set of classes,
 * so nothing from the database can reach the class list.
 *
 * @var string|null $status
 */
$status = (string) ($status ?? '');
$dot = match ($status) {
    'paid', 'completed', 'delivered', 'verified', 'active' => 'bg-emerald-600',
    'pending_verification', 'processing', 'assigned', 'pending' => 'bg-amber-500',
    'cancelled', 'failed', 'rejected', 'refunded', 'unpaid' => 'bg-rose-500',
    default => 'bg-slate-400',
};
?>
<span class="inline-flex items-center gap-1.5 rounded-pill px-2.5 py-1 text-xs font-bold <?= e(status_badge($status)) ?>">
    <span class="h-1.5 w-1.5 rounded-full <?= e($dot) ?>"></span>
    <?= e(status_label($status)) ?>
</span>
