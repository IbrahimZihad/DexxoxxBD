<?php

declare(strict_types=1);

/**
 * The append-only audit ledger. One row per change, newest first, paged with real
 * GET links so a page can be shared and still works with JavaScript off.
 *
 * Descriptions are staff-authored text and actor names/IPs are operational data:
 * everything is printed escaped with e() and never interpolated into a URL. There
 * is no edit or delete on this page on purpose - the table is append-only.
 *
 * @var App\Core\View $view
 * @var array{items: list<array<string, mixed>>, total: int, pages: int, page: int, per_page: int} $paging
 */
$brandName = setting('brand.name', 'DexxozzBD');

$paging = (array) ($paging ?? []);
$rows = array_values(array_map(static fn (mixed $row): array => (array) $row, (array) ($paging['items'] ?? [])));
$total = (int) ($paging['total'] ?? 0);
$pages = max(1, (int) ($paging['pages'] ?? 1));
$page = max(1, (int) ($paging['page'] ?? 1));
$perPage = max(1, (int) ($paging['per_page'] ?? 40));

$from = $total === 0 ? 0 : (($page - 1) * $perPage) + 1;
$to = min($total, $page * $perPage);

/** Rebuild /admin/audit?page=… . */
$auditUrl = static fn (int $target): string => route('admin.audit', [], $target > 1 ? ['page' => $target] : []);

/** "order.paid" / "service_label" read as "Order Paid" / "Service Label". */
$pretty = static fn (string $value): string => ucwords(str_replace(['.', '_'], ' ', trim($value)));

$toneFor = static fn (string $action): string => match (true) {
    str_starts_with($action, 'settings') => 'bg-amber-100 text-amber-800 ring-1 ring-amber-200',
    str_contains($action, 'deleted') => 'bg-rose-100 text-rose-800 ring-1 ring-rose-200',
    str_contains($action, 'created') || str_contains($action, 'answered') => 'bg-emerald-100 text-emerald-800 ring-1 ring-emerald-200',
    default => 'bg-brand-100 text-brand-800 ring-1 ring-brand-200',
};

$view->extend('layouts/admin');
$view->setSection('title', 'Audit log - ' . $brandName);
$view->setSection('page_title', 'Audit log');
$view->setSection('page_actions', '<a href="' . e(route('admin.dashboard')) . '" class="inline-flex items-center gap-2 rounded-brand border border-brand-200 bg-white px-4 py-2 text-sm font-bold text-brand-700 transition-colors duration-200 hover:bg-brand-50">'
    . '<svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path d="M5 4h11l3 3v13H5z" stroke-linejoin="round"/><path d="M8 9h8M8 13h8M8 17h5" stroke-linecap="round"/></svg>'
    . 'Dashboard</a>');
?>

<div class="flex items-start gap-3 rounded-card border border-brand-100 bg-white p-4 text-sm shadow-soft sm:p-5">
    <svg class="mt-0.5 h-5 w-5 shrink-0 text-brand-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 3 4 6v6c0 4.5 3.2 7.5 8 9 4.8-1.5 8-4.5 8-9V6z" stroke-linejoin="round"/><path d="m9 12 2 2 4-4" stroke-linecap="round" stroke-linejoin="round"/></svg>
    <p class="text-ink-700">
        <span class="font-black text-ink">Append-only.</span>
        Every change staff make through this panel is written here and nowhere else - rows are never edited or deleted by the app, so this ledger is a faithful history of who did what, when, and from which IP.
    </p>
</div>

<?php if ($rows === []): ?>
    <div class="mt-5">
        <?= $view->component('empty-state', [
            'title' => 'No changes logged yet',
            'text' => 'As soon as someone edits an order, a service or a setting, the entry appears here.',
            'action_url' => null,
            'action_label' => null,
            'icon' => 'check',
        ]) ?>
    </div>
<?php else: ?>
    <div class="mt-5 overflow-hidden rounded-card border border-brand-100 bg-white shadow-soft">
        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-brand-100 px-4 py-3 text-xs font-bold text-ink-400">
            <p>Showing <?= e((string) $from) ?>-&nbsp;<?= e((string) $to) ?> of <?= e(number_format($total)) ?></p>
            <p>Newest first</p>
        </div>

        <div class="overflow-x-auto">
            <table class="a-table">
                <caption class="sr-only">Staff actions with the record touched, the actor, their IP address and the timestamp</caption>
                <thead>
                    <tr>
                        <th scope="col">When</th>
                        <th scope="col">Action</th>
                        <th scope="col">Record</th>
                        <th scope="col">Details</th>
                        <th scope="col">By</th>
                        <th scope="col">IP</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $log): ?>
                        <?php
                        $action = (string) ($log['action'] ?? '');
                        $entity = trim((string) ($log['entity'] ?? ''));
                        $entityId = (int) ($log['entity_id'] ?? 0);
                        $description = trim((string) ($log['description'] ?? ''));
                        $createdAt = (string) ($log['created_at'] ?? '');
                        ?>
                        <tr>
                            <td class="whitespace-nowrap text-xs font-semibold text-ink-400" title="<?= e($createdAt) ?>"><?= e($createdAt === '' ? '—' : time_ago($createdAt)) ?></td>
                            <td class="whitespace-nowrap"><span class="inline-flex items-center rounded-pill px-2.5 py-1 text-xs font-black <?= e($toneFor($action)) ?>"><?= e($pretty($action)) ?></span></td>
                            <td class="whitespace-nowrap text-sm font-bold text-ink">
                                <?= $entity === '' ? '<span class="text-ink-300">—</span>' : e($pretty($entity)) ?>
                                <?php if ($entityId > 0): ?>
                                    <span class="ml-1 font-mono text-xs text-ink-400">#<?= e((string) $entityId) ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="max-w-[28rem] text-sm text-ink-700"><?= e($description === '' ? 'No further detail recorded.' : $description) ?></td>
                            <td class="whitespace-nowrap text-sm font-bold text-ink"><?= e((string) ($log['user_name'] ?? 'system')) ?></td>
                            <td class="whitespace-nowrap font-mono text-xs text-ink-400"><?= e((string) ($log['ip_address'] ?? '')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php if ($pages > 1): ?>
        <?php $windowStart = max(1, min($page - 2, $pages - 4)); ?>
        <?php $windowEnd = min($pages, $windowStart + 4); ?>
        <nav aria-label="Audit log pages" class="mt-6 flex flex-wrap items-center justify-center gap-2">
            <?php if ($page > 1): ?>
                <a href="<?= e($auditUrl($page - 1)) ?>" rel="prev" class="rounded-pill border border-brand-200 bg-white px-4 py-2 text-sm font-bold text-brand-700 transition-colors duration-200 hover:bg-brand-50">Previous</a>
            <?php endif; ?>

            <?php if ($windowStart > 1): ?>
                <a href="<?= e($auditUrl(1)) ?>" class="rounded-pill border border-brand-200 bg-white px-3.5 py-2 text-sm font-bold text-ink-400 hover:text-brand-700">1</a>
                <?php if ($windowStart > 2): ?><span class="text-sm font-bold text-ink-400" aria-hidden="true">&hellip;</span><?php endif; ?>
            <?php endif; ?>

            <?php for ($number = $windowStart; $number <= $windowEnd; $number++): ?>
                <?php if ($number === $page): ?>
                    <span aria-current="page" class="rounded-pill bg-gradient-to-r from-brand-600 to-brand-500 px-4 py-2 text-sm font-black text-white shadow-pop"><?= e((string) $number) ?></span>
                <?php else: ?>
                    <a href="<?= e($auditUrl($number)) ?>" class="rounded-pill border border-brand-200 bg-white px-4 py-2 text-sm font-bold text-brand-700 transition-colors duration-200 hover:bg-brand-50"><?= e((string) $number) ?></a>
                <?php endif; ?>
            <?php endfor; ?>

            <?php if ($windowEnd < $pages): ?>
                <?php if ($windowEnd < $pages - 1): ?><span class="text-sm font-bold text-ink-400" aria-hidden="true">&hellip;</span><?php endif; ?>
                <a href="<?= e($auditUrl($pages)) ?>" class="rounded-pill border border-brand-200 bg-white px-3.5 py-2 text-sm font-bold text-ink-400 hover:text-brand-700"><?= e((string) $pages) ?></a>
            <?php endif; ?>

            <?php if ($page < $pages): ?>
                <a href="<?= e($auditUrl($page + 1)) ?>" rel="next" class="rounded-pill bg-ink px-4 py-2 text-sm font-bold text-white transition-colors duration-200 hover:bg-brand-700">Next</a>
            <?php endif; ?>
        </nav>
    <?php endif; ?>
<?php endif; ?>
