<?php

declare(strict_types=1);

/**
 * Order timeline from order_status_history.
 *
 * Only the status and the timestamp are printed. Staff write their working notes
 * (including the reason a payment was verified or rejected) into the note column
 * of that table, and that is not something a customer page should ever echo.
 *
 * @var list<array<string, mixed>> $history
 */
$history = (array) ($history ?? []);
$rows = [];

foreach ($history as $entry) {
    $entry = (array) $entry;
    $status = trim((string) ($entry['status'] ?? ''));
    $createdAt = trim((string) ($entry['created_at'] ?? ''));

    if ($status === '') {
        continue;
    }

    $rows[] = ['status' => $status, 'created_at' => $createdAt];
}

$total = count($rows);
?>
<?php if ($rows === []): ?>
    <p class="rounded-brand bg-brand-50/70 p-3 text-xs leading-relaxed text-ink-400">
        Nothing logged yet. The moment a person touches your order the step appears here.
    </p>
<?php else: ?>
    <ol class="relative space-y-4 border-l-2 border-dashed border-brand-200 pl-5">
        <?php foreach ($rows as $index => $row): ?>
            <?php
            $status = (string) $row['status'];
            $createdAt = (string) $row['created_at'];
            $isNow = $index === $total - 1;
            $dot = match ($status) {
                'paid', 'completed', 'delivered', 'verified' => 'bg-emerald-500 ring-emerald-100',
                'cancelled', 'failed', 'rejected', 'refunded' => 'bg-rose-500 ring-rose-100',
                'pending_verification', 'processing', 'assigned' => 'bg-amber-500 ring-amber-100',
                default => 'bg-brand-500 ring-brand-100',
            };
            ?>
            <li class="relative">
                <?php $moment = $createdAt === '' ? false : strtotime($createdAt); ?>
                <span class="absolute -left-[27px] top-1 grid h-4 w-4 place-items-center rounded-full ring-4 <?= e($dot) ?>" aria-hidden="true">
                    <?php if ($isNow): ?><span class="h-1.5 w-1.5 rounded-full bg-white"></span><?php endif; ?>
                </span>
                <p class="text-sm font-black leading-tight text-ink">
                    <?= e(status_label($status)) ?>
                    <?php if ($isNow): ?>
                        <span class="ml-1 rounded-pill bg-brand-100 px-2 py-0.5 align-middle text-[10px] font-black uppercase tracking-wide text-brand-700">Latest</span>
                    <?php endif; ?>
                </p>
                <?php if ($moment !== false): ?>
                    <p class="mt-0.5 text-xs text-ink-400"><?= e(date('d M Y, g:i a', $moment)) ?></p>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ol>
<?php endif; ?>
