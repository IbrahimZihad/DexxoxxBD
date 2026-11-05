<?php

declare(strict_types=1);

/**
 * The customer directory. One GET form narrows it (search is query-string only,
 * so a filtered view can be pasted into a chat and still works with JavaScript
 * off); one row per account. Nothing here mutates state - to change a person you
 * open their orders or the team page.
 *
 * Names, emails and phone numbers are customer data: printed for staff, escaped
 * with e(), and never folded into a URL beyond the email handed to the orders
 * search box (which is itself GET, not a mutation).
 *
 * @var App\Core\View $view
 * @var array{items: list<array<string, mixed>>, total: int, pages: int, page: int, per_page: int} $paging
 * @var string $search
 */
$brandName = setting('brand.name', 'ZapSub');

$paging = (array) ($paging ?? []);
$rows = array_values(array_map(static fn (mixed $row): array => (array) $row, (array) ($paging['items'] ?? [])));
$total = (int) ($paging['total'] ?? 0);
$pages = max(1, (int) ($paging['pages'] ?? 1));
$page = max(1, (int) ($paging['page'] ?? 1));
$perPage = max(1, (int) ($paging['per_page'] ?? 20));

$search = trim((string) ($search ?? ''));

$from = $total === 0 ? 0 : (($page - 1) * $perPage) + 1;
$to = min($total, $page * $perPage);

/** Rebuild /admin/customers?search=…&page=… keeping the active search. */
$customersUrl = static function (int $target) use ($search): string {
    $query = $search === '' ? [] : ['search' => $search];

    if ($target > 1) {
        $query['page'] = $target;
    }

    return route('admin.customers', [], $query);
};

$view->extend('layouts/admin');
$view->setSection('title', 'Customers - ' . $brandName);
$view->setSection('page_title', 'Customers');
$view->setSection('page_actions', '<a href="' . e(route('admin.orders')) . '" class="inline-flex items-center gap-2 rounded-brand border border-brand-200 bg-white px-4 py-2 text-sm font-bold text-brand-700 transition-colors duration-200 hover:bg-brand-50">'
    . '<svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path d="M6 4h12v16l-3-2-3 2-3-2-3 2z" stroke-linejoin="round"/></svg>'
    . 'Orders</a>');
?>

<form method="get" action="<?= e(route('admin.customers')) ?>" class="rounded-card border border-brand-100 bg-white p-4 shadow-soft sm:p-5">
    <div class="flex flex-wrap items-end gap-3">
        <label class="block min-w-[16rem] flex-1">
            <span class="mb-1 block text-xs font-black uppercase tracking-[0.12em] text-ink-400">Search</span>
            <input type="search" name="search" value="<?= e($search) ?>"
                   placeholder="Name, email or phone number"
                   class="w-full rounded-brand border border-brand-200 bg-white px-3.5 py-2.5 text-sm text-ink outline-none transition duration-200 placeholder:text-ink-400 focus:border-brand-400 focus:ring-2 focus:ring-brand-300">
        </label>
        <div class="flex items-end gap-2">
            <button type="submit" class="rounded-brand bg-brand-600 px-4 py-2.5 text-sm font-black text-white shadow-glow transition-colors duration-200 hover:bg-brand-700">Search</button>
            <?php if ($search !== ''): ?>
                <a href="<?= e(route('admin.customers')) ?>" class="rounded-brand border border-brand-200 bg-white px-4 py-2.5 text-sm font-bold text-ink-400 transition-colors duration-200 hover:text-brand-700">Clear</a>
            <?php endif; ?>
        </div>
    </div>
    <p class="mt-3 text-xs font-semibold text-ink-400">
        <?= e(number_format($total)) ?> customer<?= $total === 1 ? '' : 's' ?><?= $search === '' ? ' on file' : ' match this search' ?>.
    </p>
</form>

<?php if ($rows === []): ?>
    <div class="mt-5">
        <?= $view->component('empty-state', [
            'title' => $search === '' ? 'No customers yet' : 'Nobody matches that search',
            'text' => $search === ''
                ? 'Registered buyers and guest checkout emails land here as orders come in.'
                : 'Try a shorter name, the full email, or a phone number.',
            'action_url' => $search === '' ? null : route('admin.customers'),
            'action_label' => $search === '' ? null : 'Clear search',
            'icon' => 'search',
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
                <caption class="sr-only">Customer accounts with their order count, lifetime spend and last order</caption>
                <thead>
                    <tr>
                        <th scope="col">Customer</th>
                        <th scope="col">Orders</th>
                        <th scope="col">Lifetime spend</th>
                        <th scope="col">Last order</th>
                        <th scope="col">Joined</th>
                        <th scope="col"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $customer): ?>
                        <?php
                        $email = trim((string) ($customer['email'] ?? ''));
                        $phone = trim((string) ($customer['phone'] ?? ''));
                        $ordersCount = (int) ($customer['orders_count'] ?? 0);
                        $lifetime = (float) ($customer['lifetime_spend'] ?? 0);
                        $lastOrder = $customer['last_order'] ?? null;
                        $joined = (string) ($customer['created_at'] ?? '');
                        ?>
                        <tr>
                            <td>
                                <span class="block font-black text-ink"><?= e((string) ($customer['name'] ?? '')) ?></span>
                                <?php if ($email !== ''): ?>
                                    <span class="block max-w-[18rem] truncate text-xs text-ink-400"><?= e($email) ?></span>
                                <?php endif; ?>
                                <?php if ($phone !== ''): ?>
                                    <span class="block font-mono text-xs text-ink-400"><?= e($phone) ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="whitespace-nowrap font-black"><?= e(number_format($ordersCount)) ?></td>
                            <td class="whitespace-nowrap font-black text-brand-700"><?= e(taka($lifetime)) ?></td>
                            <td class="whitespace-nowrap text-xs font-semibold text-ink-400" title="<?= e($lastOrder === null ? '' : (string) $lastOrder) ?>">
                                <?= $lastOrder === null ? '<span class="text-ink-300">No orders yet</span>' : e(time_ago((string) $lastOrder)) ?>
                            </td>
                            <td class="whitespace-nowrap text-xs font-semibold text-ink-400" title="<?= e($joined) ?>"><?= e($joined === '' ? '—' : time_ago($joined)) ?></td>
                            <td class="text-right whitespace-nowrap">
                                <?php if ($email !== ''): ?>
                                    <a href="<?= e(route('admin.orders', [], ['search' => $email])) ?>" class="inline-flex items-center gap-1.5 rounded-brand bg-brand-50 px-3 py-1.5 text-xs font-black text-brand-700 transition-colors duration-200 hover:bg-brand-100">
                                        <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5" stroke-linecap="round"/></svg>
                                        View orders
                                    </a>
                                <?php else: ?>
                                    <a href="<?= e(route('admin.orders', [], ['search' => (string) ($customer['name'] ?? '')])) ?>" class="inline-flex items-center gap-1.5 rounded-brand bg-brand-50 px-3 py-1.5 text-xs font-black text-brand-700 transition-colors duration-200 hover:bg-brand-100">
                                        <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5" stroke-linecap="round"/></svg>
                                        View orders
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php if ($pages > 1): ?>
        <?php $windowStart = max(1, min($page - 2, $pages - 4)); ?>
        <?php $windowEnd = min($pages, $windowStart + 4); ?>
        <nav aria-label="Customer pages" class="mt-6 flex flex-wrap items-center justify-center gap-2">
            <?php if ($page > 1): ?>
                <a href="<?= e($customersUrl($page - 1)) ?>" rel="prev" class="rounded-pill border border-brand-200 bg-white px-4 py-2 text-sm font-bold text-brand-700 transition-colors duration-200 hover:bg-brand-50">Previous</a>
            <?php endif; ?>

            <?php if ($windowStart > 1): ?>
                <a href="<?= e($customersUrl(1)) ?>" class="rounded-pill border border-brand-200 bg-white px-3.5 py-2 text-sm font-bold text-ink-400 hover:text-brand-700">1</a>
                <?php if ($windowStart > 2): ?><span class="text-sm font-bold text-ink-400" aria-hidden="true">&hellip;</span><?php endif; ?>
            <?php endif; ?>

            <?php for ($number = $windowStart; $number <= $windowEnd; $number++): ?>
                <?php if ($number === $page): ?>
                    <span aria-current="page" class="rounded-pill bg-gradient-to-r from-brand-600 to-brand-500 px-4 py-2 text-sm font-black text-white shadow-pop"><?= e((string) $number) ?></span>
                <?php else: ?>
                    <a href="<?= e($customersUrl($number)) ?>" class="rounded-pill border border-brand-200 bg-white px-4 py-2 text-sm font-bold text-brand-700 transition-colors duration-200 hover:bg-brand-50"><?= e((string) $number) ?></a>
                <?php endif; ?>
            <?php endfor; ?>

            <?php if ($windowEnd < $pages): ?>
                <?php if ($windowEnd < $pages - 1): ?><span class="text-sm font-bold text-ink-400" aria-hidden="true">&hellip;</span><?php endif; ?>
                <a href="<?= e($customersUrl($pages)) ?>" class="rounded-pill border border-brand-200 bg-white px-3.5 py-2 text-sm font-bold text-ink-400 hover:text-brand-700"><?= e((string) $pages) ?></a>
            <?php endif; ?>

            <?php if ($page < $pages): ?>
                <a href="<?= e($customersUrl($page + 1)) ?>" rel="next" class="rounded-pill bg-ink px-4 py-2 text-sm font-bold text-white transition-colors duration-200 hover:bg-brand-700">Next</a>
            <?php endif; ?>
        </nav>
    <?php endif; ?>
<?php endif; ?>
