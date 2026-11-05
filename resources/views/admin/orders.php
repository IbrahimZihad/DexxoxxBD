<?php

declare(strict_types=1);

/**
 * The order ledger: one GET form to narrow it, one row per order, one link per
 * row. Nothing here mutates anything - filters are query-string only, so a filter
 * state can be pasted into a chat and it still works with JavaScript off.
 *
 * @var App\Core\View $view
 * @var array{items: list<array<string, mixed>>, total: int, pages: int, page: int, per_page: int} $paging
 * @var array<string, string> $filters
 * @var list<string> $statuses
 * @var list<string> $paymentStatuses
 * @var list<string> $fulfillmentStates
 */
$brandName = setting('brand.name', 'DexxozzBD');

$paging = (array) ($paging ?? []);
$rows = array_values(array_map(static fn (mixed $row): array => (array) $row, (array) ($paging['items'] ?? [])));
$total = (int) ($paging['total'] ?? 0);
$pages = max(1, (int) ($paging['pages'] ?? 1));
$page = max(1, (int) ($paging['page'] ?? 1));
$perPage = max(1, (int) ($paging['per_page'] ?? 25));

$filters = array_map('strval', (array) ($filters ?? []));
$statuses = array_values((array) ($statuses ?? []));
$paymentStatuses = array_values((array) ($paymentStatuses ?? []));
$fulfillmentStates = array_values((array) ($fulfillmentStates ?? []));

$activeCount = count(array_filter($filters, static fn (string $value): bool => $value !== ''));
$from = $total === 0 ? 0 : (($page - 1) * $perPage) + 1;
$to = min($total, $page * $perPage);

/** Rebuild /admin/orders?… keeping whatever filter is already chosen. */
$ordersUrl = static function (array $overrides) use ($filters): string {
    $query = array_merge($filters, $overrides);
    $target = max(1, (int) ($query['page'] ?? 1));
    unset($query['page']);
    $query = array_filter($query, static fn (string $value): bool => $value !== '');

    if ($target > 1) {
        $query['page'] = $target;
    }

    return route('admin.orders', [], $query);
};

$toOptions = static fn (array $values): array => array_combine(
    $values,
    array_map(static fn (string $value): string => status_label($value), $values)
);

$view->extend('layouts/admin');
$view->setSection('title', 'Orders - ' . $brandName);
$view->setSection('page_title', 'Orders');
$view->setSection('page_actions', '<a href="' . e(route('admin.delivery')) . '" class="inline-flex items-center gap-2 rounded-brand border border-brand-200 bg-white px-4 py-2 text-sm font-bold text-brand-700 transition-colors duration-200 hover:bg-brand-50">'
    . '<svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path d="M3 7h10v9H3zM13 10h4l3 3v3h-7z" stroke-linejoin="round"/><circle cx="7" cy="18" r="1.6"/><circle cx="17" cy="18" r="1.6"/></svg>'
    . 'Delivery queue</a>');
?>

<form method="get" action="<?= e(route('admin.orders')) ?>" class="rounded-card border border-brand-100 bg-white p-4 shadow-soft sm:p-5">
    <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-5">
        <label class="block">
            <span class="mb-1 block text-xs font-black uppercase tracking-[0.12em] text-ink-400">Search</span>
            <input type="search" name="search" value="<?= e($filters['search'] ?? '') ?>"
                   placeholder="Order no, name, email or phone"
                   class="w-full rounded-brand border border-brand-200 bg-white px-3.5 py-2.5 text-sm text-ink outline-none transition duration-200 placeholder:text-ink-400 focus:border-brand-400 focus:ring-2 focus:ring-brand-300">
        </label>

        <label class="block">
            <span class="mb-1 block text-xs font-black uppercase tracking-[0.12em] text-ink-400">Order status</span>
            <select name="status" class="w-full rounded-brand border border-brand-200 bg-white px-3 py-2.5 text-sm text-ink outline-none transition duration-200 focus:border-brand-400 focus:ring-2 focus:ring-brand-300">
                <option value="">Any status</option>
                <?php foreach ($toOptions($statuses) as $value => $label): ?>
                    <option value="<?= e((string) $value) ?>" <?= e((string) $value === ($filters['status'] ?? '') ? 'selected' : '') ?>><?= e((string) $label) ?></option>
                <?php endforeach; ?>
            </select>
        </label>

        <label class="block">
            <span class="mb-1 block text-xs font-black uppercase tracking-[0.12em] text-ink-400">Payment</span>
            <select name="payment_status" class="w-full rounded-brand border border-brand-200 bg-white px-3 py-2.5 text-sm text-ink outline-none transition duration-200 focus:border-brand-400 focus:ring-2 focus:ring-brand-300">
                <option value="">Any payment</option>
                <?php foreach ($toOptions($paymentStatuses) as $value => $label): ?>
                    <option value="<?= e((string) $value) ?>" <?= e((string) $value === ($filters['payment_status'] ?? '') ? 'selected' : '') ?>><?= e((string) $label) ?></option>
                <?php endforeach; ?>
            </select>
        </label>

        <label class="block">
            <span class="mb-1 block text-xs font-black uppercase tracking-[0.12em] text-ink-400">Delivery</span>
            <select name="fulfillment" class="w-full rounded-brand border border-brand-200 bg-white px-3 py-2.5 text-sm text-ink outline-none transition duration-200 focus:border-brand-400 focus:ring-2 focus:ring-brand-300">
                <option value="">Any delivery</option>
                <?php foreach ($toOptions($fulfillmentStates) as $value => $label): ?>
                    <option value="<?= e((string) $value) ?>" <?= e((string) $value === ($filters['fulfillment'] ?? '') ? 'selected' : '') ?>><?= e((string) $label) ?></option>
                <?php endforeach; ?>
            </select>
        </label>

        <div class="flex items-end gap-2">
            <button type="submit" class="w-full rounded-brand bg-brand-600 px-4 py-2.5 text-sm font-black text-white shadow-glow transition-colors duration-200 hover:bg-brand-700">Filter</button>
            <?php if ($activeCount > 0): ?>
                <a href="<?= e(route('admin.orders')) ?>" class="rounded-brand border border-brand-200 bg-white px-4 py-2.5 text-sm font-bold text-ink-400 transition-colors duration-200 hover:text-brand-700">Clear</a>
            <?php endif; ?>
        </div>
    </div>

    <p class="mt-3 flex flex-wrap items-center gap-2 text-xs font-semibold text-ink-400">
        <span><?= e(number_format($total)) ?> order<?= $total === 1 ? '' : 's' ?> match<?= $activeCount > 0 ? ' this filter' : '' ?>.</span>
        <a href="<?= e(route('admin.orders', [], ['payment_status' => 'pending_verification'])) ?>" class="rounded-pill bg-amber-100 px-2.5 py-1 font-bold text-amber-800 transition-colors duration-200 hover:bg-amber-200">To verify</a>
        <a href="<?= e(route('admin.orders', [], ['payment_status' => 'paid', 'fulfillment' => 'unassigned'])) ?>" class="rounded-pill bg-accent-100 px-2.5 py-1 font-bold text-accent-800 transition-colors duration-200 hover:bg-accent-200">Paid, not delivered</a>
        <a href="<?= e(route('admin.orders', [], ['status' => 'cancelled'])) ?>" class="rounded-pill bg-rose-100 px-2.5 py-1 font-bold text-rose-800 transition-colors duration-200 hover:bg-rose-200">Cancelled</a>
    </p>
</form>

<?php if ($rows === []): ?>
    <div class="mt-5">
        <?= $view->component('empty-state', [
            'title' => $total === 0 && $activeCount === 0 ? 'No orders yet' : 'Nothing matches that filter',
            'text' => $total === 0 && $activeCount === 0
                ? 'As soon as someone checks out, the order lands here with its payment waiting for your eye.'
                : 'Widen the search or clear the status choices - an order number read over the phone works too.',
            'action_url' => $activeCount > 0 ? route('admin.orders') : route('admin.delivery'),
            'action_label' => $activeCount > 0 ? 'Reset the filters' : 'Delivery queue',
            'icon' => $activeCount > 0 ? 'search' : 'bag',
        ]) ?>
    </div>
<?php else: ?>
    <div class="mt-5 overflow-hidden rounded-card border border-brand-100 bg-white shadow-soft">
        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-brand-100 px-4 py-3 text-xs font-bold text-ink-400">
            <p>Showing <?= e((string) $from) ?>-&nbsp;<?= e((string) $to) ?> of <?= e(number_format($total)) ?></p>
            <p>Sorted newest first</p>
        </div>

        <div class="overflow-x-auto">
            <table class="a-table">
                <caption class="sr-only">Orders, newest first, with payment and delivery state</caption>
                <thead>
                    <tr>
                        <th scope="col">Order</th>
                        <th scope="col">Customer</th>
                        <th scope="col">Total</th>
                        <th scope="col">Payment</th>
                        <th scope="col">Order</th>
                        <th scope="col">Delivery</th>
                        <th scope="col">Placed</th>
                        <th scope="col"><span class="sr-only">Open</span></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $order): ?>
                        <?php $orderId = (int) ($order['id'] ?? 0); ?>
                        <tr>
                            <td class="whitespace-nowrap">
                                <a href="<?= e(route('admin.order.show', ['id' => $orderId])) ?>" class="font-black text-brand-700 underline decoration-brand-200 underline-offset-4 hover:text-brand-800">
                                    <?= e((string) ($order['order_no'] ?? '')) ?>
                                </a>
                                <?php if (($order['coupon_code'] ?? '') !== null && (string) ($order['coupon_code'] ?? '') !== ''): ?>
                                    <span class="mt-1 inline-flex rounded-pill bg-lime-100 px-2 py-0.5 text-[0.65rem] font-black text-lime-800"><?= e((string) $order['coupon_code']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="block max-w-[15rem] truncate font-semibold"><?= e((string) ($order['customer_name'] ?? '')) ?></span>
                                <span class="block max-w-[15rem] truncate text-xs text-ink-400">
                                    <?= e((string) ($order['phone'] ?? '')) ?> &middot; <?= e((string) ($order['email'] ?? '')) ?>
                                </span>
                            </td>
                            <td class="whitespace-nowrap font-black"><?= e(taka((float) ($order['total'] ?? 0))) ?></td>
                            <td><?= $view->component('status-pill', ['status' => (string) ($order['payment_status'] ?? '')]) ?></td>
                            <td><?= $view->component('status-pill', ['status' => (string) ($order['status'] ?? '')]) ?></td>
                            <td><?= $view->component('status-pill', ['status' => (string) ($order['fulfillment_status'] ?? '')]) ?></td>
                            <td class="whitespace-nowrap text-xs font-semibold text-ink-400" title="<?= e((string) ($order['created_at'] ?? '')) ?>">
                                <?= e(time_ago((string) ($order['created_at'] ?? 'now'))) ?>
                            </td>
                            <td class="whitespace-nowrap text-right">
                                <a href="<?= e(route('admin.order.show', ['id' => $orderId])) ?>" class="inline-flex items-center gap-1.5 rounded-brand bg-brand-50 px-3 py-1.5 text-xs font-black text-brand-700 transition-colors duration-200 hover:bg-brand-100">
                                    Open
                                    <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" aria-hidden="true"><path d="M9 6l6 6-6 6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                </a>
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
        <nav aria-label="Order pages" class="mt-6 flex flex-wrap items-center justify-center gap-2">
            <?php if ($page > 1): ?>
                <a href="<?= e($ordersUrl(['page' => $page - 1])) ?>" rel="prev" class="inline-flex items-center gap-1.5 rounded-pill border border-brand-200 bg-white px-4 py-2 text-sm font-bold text-brand-700 transition-colors duration-200 hover:bg-brand-50">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true"><path d="M15 6l-6 6 6 6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    Previous
                </a>
            <?php endif; ?>

            <?php if ($windowStart > 1): ?>
                <a href="<?= e($ordersUrl(['page' => 1])) ?>" class="rounded-pill border border-brand-200 bg-white px-3.5 py-2 text-sm font-bold text-ink-400 hover:text-brand-700">1</a>
                <?php if ($windowStart > 2): ?>
                    <span class="text-sm font-bold text-ink-400" aria-hidden="true">&hellip;</span>
                <?php endif; ?>
            <?php endif; ?>

            <?php for ($number = $windowStart; $number <= $windowEnd; $number++): ?>
                <?php if ($number === $page): ?>
                    <span aria-current="page" class="rounded-pill bg-gradient-to-r from-brand-600 to-brand-500 px-4 py-2 text-sm font-black text-white shadow-pop"><?= e((string) $number) ?></span>
                <?php else: ?>
                    <a href="<?= e($ordersUrl(['page' => $number])) ?>" class="rounded-pill border border-brand-200 bg-white px-4 py-2 text-sm font-bold text-brand-700 transition-colors duration-200 hover:bg-brand-50"><?= e((string) $number) ?></a>
                <?php endif; ?>
            <?php endfor; ?>

            <?php if ($windowEnd < $pages): ?>
                <?php if ($windowEnd < $pages - 1): ?>
                    <span class="text-sm font-bold text-ink-400" aria-hidden="true">&hellip;</span>
                <?php endif; ?>
                <a href="<?= e($ordersUrl(['page' => $pages])) ?>" class="rounded-pill border border-brand-200 bg-white px-3.5 py-2 text-sm font-bold text-ink-400 hover:text-brand-700"><?= e((string) $pages) ?></a>
            <?php endif; ?>

            <?php if ($page < $pages): ?>
                <a href="<?= e($ordersUrl(['page' => $page + 1])) ?>" rel="next" class="inline-flex items-center gap-1.5 rounded-pill bg-ink px-4 py-2 text-sm font-bold text-white transition-colors duration-200 hover:bg-brand-700">
                    Next
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true"><path d="M9 6l6 6-6 6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </a>
            <?php endif; ?>
        </nav>
    <?php endif; ?>
<?php endif; ?>
