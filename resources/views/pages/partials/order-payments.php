<?php

declare(strict_types=1);

/**
 * Every payment the customer has declared on this order, newest first.
 *
 * Only the customer's own figures are printed here: method, their reference,
 * the amount they claimed and the row state. admin_note / verified_by belong to
 * the staff desk and are never rendered on a customer page.
 *
 * @var list<array<string, mixed>> $payments
 */
$payments = (array) ($payments ?? []);
?>
<ul class="space-y-2.5">
    <?php foreach ($payments as $payment): ?>
        <?php
        $payment = (array) $payment;
        $txn = trim((string) ($payment['txn_id'] ?? ''));
        $status = (string) ($payment['status'] ?? 'pending');
        $createdAt = (string) ($payment['created_at'] ?? '');
        ?>
        <li class="flex flex-wrap items-center gap-x-3 gap-y-1.5 rounded-brand bg-brand-50/70 p-3">
            <span class="min-w-0">
                <span class="block text-sm font-black text-ink"><?= e((string) ($payment['method_label'] ?? 'Payment')) ?></span>
                <span class="mt-0.5 block font-mono text-xs tracking-wide text-ink-400">
                    <?= $txn === '' ? 'No reference given' : e('Ref ' . $txn) ?>
                    <?php if ($createdAt !== ''): ?>
                        &middot; <?= e(time_ago($createdAt)) ?>
                    <?php endif; ?>
                </span>
            </span>
            <span class="ml-auto flex items-center gap-2.5">
                <span class="text-sm font-black text-ink"><?= e(taka((float) ($payment['amount'] ?? 0))) ?></span>
                <span><?php echo $view->component('status-pill', ['status' => $status]) ?></span>
            </span>
        </li>
    <?php endforeach; ?>
</ul>
