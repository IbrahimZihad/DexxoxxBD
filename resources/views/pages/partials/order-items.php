<?php

declare(strict_types=1);

/**
 * The lines of an order, read from the order_items rows that were copied when
 * the order was written - so a later rename or reprice never rewrites history.
 *
 * delivery_note is deliberately never touched here: what we hand over stays
 * encrypted and is only ever shown to staff.
 *
 * @var list<array<string, mixed>> $items
 * @var bool                       $showStatus
 */
$items = (array) ($items ?? []);
$showStatus = (bool) ($showStatus ?? true);
?>
<ul class="divide-y divide-brand-100/70">
    <?php foreach ($items as $item): ?>
        <?php
        $item = (array) $item;
        $quantity = max(1, (int) ($item['quantity'] ?? 1));
        $status = (string) ($item['status'] ?? 'pending');
        $deliveredAt = (string) ($item['delivered_at'] ?? '');
        ?>
        <li class="flex items-start gap-3 py-3.5 first:pt-0 last:pb-0">
            <span class="grid h-10 w-10 shrink-0 place-items-center rounded-brand bg-gradient-to-br from-brand-600 to-accent-500 text-white shadow-soft" aria-hidden="true">
                <span class="text-xs font-black">&times;<?= e((string) $quantity) ?></span>
            </span>

            <span class="min-w-0 grow">
                <span class="block text-sm font-black leading-snug text-ink">
                    <?= e(plan_label((string) ($item['service_name'] ?? ''), (string) ($item['plan_title'] ?? ''))) ?>
                </span>
                <span class="mt-0.5 block text-xs text-ink-400">
                    <?= e(taka((float) ($item['unit_price'] ?? 0))) ?> each
                    <?php if ($deliveredAt !== ''): ?>
                        &middot; delivered <?= e(time_ago($deliveredAt)) ?>
                    <?php endif; ?>
                </span>
            </span>

            <span class="shrink-0 text-right">
                <span class="block text-sm font-black text-ink"><?= e(taka((float) ($item['line_total'] ?? 0))) ?></span>
                <?php if ($showStatus): ?>
                    <span class="mt-1 inline-block"><?php echo $view->component('status-pill', ['status' => $status]) ?></span>
                <?php endif; ?>
            </span>
        </li>
    <?php endforeach; ?>
</ul>
