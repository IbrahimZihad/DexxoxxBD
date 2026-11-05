<?php

declare(strict_types=1);

/**
 * The desk a staff member opens every morning. Everything on it is read-only and
 * every number is server-rendered: the 14-day strip is plain divs whose heights
 * come from Order::lastDaysSeries(), so the page tells the truth with no script
 * and with no chart library.
 *
 * @var App\Core\View $view
 * @var array<string, mixed> $stats
 * @var list<array<string, mixed>> $series
 * @var list<array<string, mixed>> $bestSellers
 * @var list<array<string, mixed>> $recentOrders
 * @var list<array<string, mixed>> $pendingPayments
 * @var list<array<string, mixed>> $lowStock
 * @var int $unreadMessages
 * @var array<string, int> $userCounts
 * @var int $expiredCount
 * @var int $holdMinutes
 */
$brandName = setting('brand.name', 'ZapSub');

$stats = (array) ($stats ?? []);
$series = array_values(array_map(static fn (mixed $row): array => (array) $row, (array) ($series ?? [])));
$bestSellers = array_values(array_map(static fn (mixed $row): array => (array) $row, (array) ($bestSellers ?? [])));
$recentOrders = array_values(array_map(static fn (mixed $row): array => (array) $row, (array) ($recentOrders ?? [])));
$pendingPayments = array_values(array_map(static fn (mixed $row): array => (array) $row, (array) ($pendingPayments ?? [])));
$lowStock = array_values(array_map(static fn (mixed $row): array => (array) $row, (array) ($lowStock ?? [])));
$userCounts = (array) ($userCounts ?? []);
$expiredCount = max(0, (int) ($expiredCount ?? 0));
$holdMinutes = max(5, (int) ($holdMinutes ?? 90));
$unreadMessages = max(0, (int) ($unreadMessages ?? 0));

/** Money is counted in taka; the same text is printed for the JS counter to overwrite. */
$amountText = static fn (float $value): string => number_format($value, $value % 1.0 === 0.0 ? 0 : 2);

$todayRevenue = (float) ($stats['today_revenue'] ?? 0);
$monthRevenue = (float) ($stats['month_revenue'] ?? 0);

/** @var list<array{label: string, value: string, plain: string, caption: string, href: string, icon: string, tone: string}> $kpis */
$kpis = [
    [
        'label' => 'Collected today',
        'value' => sprintf('%.2f', $todayRevenue),
        'plain' => '৳ ' . $amountText($todayRevenue),
        'caption' => 'Paid orders created today',
        'href' => route('admin.orders', [], ['payment_status' => 'paid']),
        'icon' => 'wallet',
        'tone' => 'text-emerald-600',
    ],
    [
        'label' => 'Orders today',
        'value' => sprintf('%d', (int) ($stats['today_orders'] ?? 0)),
        'plain' => (string) (int) ($stats['today_orders'] ?? 0),
        'caption' => 'All statuses, since midnight',
        'href' => route('admin.orders'),
        'icon' => 'bag',
        'tone' => 'text-brand-600',
    ],
    [
        'label' => 'Waiting for payment check',
        'value' => sprintf('%d', (int) ($stats['pending_payments'] ?? 0)),
        'plain' => (string) (int) ($stats['pending_payments'] ?? 0),
        'caption' => 'TrxIDs a human must confirm',
        'href' => route('admin.orders', [], ['payment_status' => 'pending_verification']),
        'icon' => 'clock',
        'tone' => 'text-amber-600',
    ],
    [
        'label' => 'Awaiting delivery',
        'value' => sprintf('%d', (int) ($stats['awaiting_delivery'] ?? 0)),
        'plain' => (string) (int) ($stats['awaiting_delivery'] ?? 0),
        'caption' => 'Paid lines with no account sent yet',
        'href' => route('admin.delivery'),
        'icon' => 'truck',
        'tone' => 'text-accent-600',
    ],
    [
        'label' => 'This month',
        'value' => sprintf('%.2f', $monthRevenue),
        'plain' => '৳ ' . $amountText($monthRevenue),
        'caption' => number_format((int) ($stats['month_orders'] ?? 0)) . ' orders since the 1st',
        'href' => route('admin.orders'),
        'icon' => 'chart',
        'tone' => 'text-brand-600',
    ],
    [
        'label' => 'Unread messages',
        'value' => sprintf('%d', $unreadMessages),
        'plain' => (string) $unreadMessages,
        'caption' => 'From the contact form',
        'href' => route('admin.messages'),
        'icon' => 'inbox',
        'tone' => $unreadMessages > 0 ? 'text-rose-600' : 'text-ink-400',
    ],
];

/** The strip is scaled off the biggest day; a flat week still draws readable bars. */
$peak = 0.0;
foreach ($series as $point) {
    $peak = max($peak, (float) ($point['revenue'] ?? 0));
}

$dayLabel = static fn (string $day): string => date('j M', strtotime($day) ?: time());

$view->extend('layouts/admin');
$view->setSection('title', 'Order desk - ' . $brandName);
$view->setSection('page_title', 'Order desk');
$view->setSection('page_actions', '<a href="' . e(route('admin.orders')) . '" class="inline-flex items-center gap-2 rounded-brand bg-brand-600 px-4 py-2 text-sm font-bold text-white shadow-glow transition-colors duration-200 hover:bg-brand-700">'
    . '<svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h10" stroke-linecap="round"/></svg>'
    . 'All orders</a> '
    . '<a href="' . e(route('home')) . '" target="_blank" rel="noopener" class="inline-flex items-center gap-2 rounded-brand border border-brand-200 bg-white px-4 py-2 text-sm font-bold text-brand-700 transition-colors duration-200 hover:bg-brand-50">'
    . '<svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path d="M7 17 17 7M9 7h8v8" stroke-linecap="round" stroke-linejoin="round"/></svg>'
    . 'Open shop</a>');
?>

<?php if ($expiredCount > 0): ?>
    <div class="mb-5 flex items-start gap-3 rounded-brand border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900">
        <svg class="mt-0.5 h-5 w-5 shrink-0 text-amber-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <circle cx="12" cy="12" r="9"/><path d="M12 7.5V12l3 2" stroke-linecap="round"/>
        </svg>
        <p>
            <strong class="font-black"><?= e((string) $expiredCount) ?></strong>
            order<?= $expiredCount === 1 ? '' : 's' ?> still unpaid past the <?= e((string) $holdMinutes) ?>-minute hold
            <?= $expiredCount === 1 ? 'was' : 'were' ?> cancelled while this page built. The customer can always place a new order.
        </p>
    </div>
<?php endif; ?>

<section aria-labelledby="desk-kpis" class="rounded-card border border-brand-100 bg-white p-4 shadow-soft sm:p-5">
    <h2 id="desk-kpis" class="sr-only">Live numbers</h2>
    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
        <?php foreach ($kpis as $kpi): ?>
            <a href="<?= e($kpi['href']) ?>" class="hover-lift block rounded-brand border border-brand-100 bg-brand-50/50 p-4">
                <span class="flex items-center justify-between gap-2 text-xs font-black uppercase tracking-[0.14em] text-ink-400">
                    <?= e($kpi['label']) ?>
                    <svg class="h-4 w-4 <?= e($kpi['tone']) ?>" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <?php
                        /* A closed icon set, so the class list above can only ever
                           come from this file. */
                        echo match ($kpi['icon']) {
                            'wallet' => '<path d="M3 8h15a3 3 0 0 1 3 3v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8Z"/><path d="M3 8V6.5A1.5 1.5 0 0 1 4.5 5H15"/><circle cx="16.5" cy="14" r="1.2"/>',
                            'bag' => '<path d="M6 8h12l-1 12H7L6 8Z" stroke-linejoin="round"/><path d="M9 8a3 3 0 0 1 6 0"/>',
                            'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7.5V12l3 2" stroke-linecap="round"/>',
                            'truck' => '<path d="M3 7h10v9H3zM13 10h4l3 3v3h-7z" stroke-linejoin="round"/><circle cx="7" cy="18" r="1.6"/><circle cx="17" cy="18" r="1.6"/>',
                            'chart' => '<path d="M4 19V5M4 19h16" stroke-linecap="round"/><path d="M8 16v-5M12 16V8M16 16v-3" stroke-linecap="round"/>',
                            default => '<path d="M4 13h4l1.5 3h5L16 13h4" stroke-linejoin="round"/><path d="M4 13 6 5h12l2 8v6H4v-6Z" stroke-linejoin="round"/>',
                        };
                        ?>
                    </svg>
                </span>
                <p class="mt-2 text-3xl font-black tracking-tight text-ink"
                   data-counter
                   data-counter-to="<?= e($kpi['value']) ?>"
                ><?= e($kpi['plain']) ?></p>
                <p class="mt-1 text-xs font-semibold text-ink-400"><?= e($kpi['caption']) ?></p>
            </a>
        <?php endforeach; ?>
    </div>
</section>

<section aria-labelledby="desk-trend" class="mt-5 rounded-card border border-brand-100 bg-white p-4 shadow-soft sm:p-5">
    <div class="flex flex-wrap items-end justify-between gap-2">
        <div>
            <h2 id="desk-trend" class="text-base font-black tracking-tight text-ink">Last 14 days</h2>
            <p class="mt-0.5 text-xs text-ink-400">Money in per day, counted the moment a payment is verified.</p>
        </div>
        <p class="text-xs font-bold text-ink-400">
            Busiest day: <?= $peak > 0 ? e(taka($peak, false)) : e('nothing booked yet') ?>
        </p>
    </div>

    <?php if ($series === [] || $peak <= 0.0): ?>
        <p class="mt-4 rounded-brand bg-brand-50 px-4 py-6 text-center text-sm text-ink-400">
            No paid order has landed in the last fortnight. The bars start drawing themselves with the first verification.
        </p>
    <?php else: ?>
        <div class="mt-4 flex h-44 items-end gap-1 sm:gap-1.5"
             role="img"
             aria-label="Bar strip of collected revenue for the last fourteen days">
            <?php foreach ($series as $point): ?>
                <?php
                $revenue = (float) ($point['revenue'] ?? 0);
                $orders = (int) ($point['orders'] ?? 0);
                $day = (string) ($point['day'] ?? '');
                $percent = $peak > 0.0 ? ($revenue / $peak) * 100 : 0.0;
                $isToday = $day === date('Y-m-d');
                ?>
                <div class="flex h-full flex-1 flex-col justify-end gap-1">
                    <span class="text-center text-[0.6rem] font-bold <?= e($revenue > 0 ? 'text-brand-700' : 'text-ink-400/60') ?>">
                        <?= e($orders > 0 ? (string) $orders : '') ?>
                    </span>
                    <span class="w-full rounded-t-md <?= e($isToday ? 'bg-gradient-to-t from-accent-500 to-accent-300' : ($revenue > 0 ? 'bg-gradient-to-t from-brand-600 to-brand-400' : 'bg-brand-100')) ?>"
                          style="height: <?= e(number_format(max($percent, 1.5), 1, '.', '')) ?>%"></span>
                </div>
            <?php endforeach; ?>
        </div>

        <ul class="sr-only">
            <?php foreach ($series as $point): ?>
                <li><?= e($dayLabel((string) ($point['day'] ?? 'now'))) ?>:
                    <?= e(taka((float) ($point['revenue'] ?? 0))) ?> over
                    <?= e((string) (int) ($point['orders'] ?? 0)) ?> order(s)</li>
            <?php endforeach; ?>
        </ul>

        <div class="mt-2 flex flex-wrap gap-x-3 gap-y-1 border-t border-dashed border-brand-100 pt-2 text-[0.65rem] font-bold text-ink-400">
            <?php foreach ([$series[0] ?? null, $series[(int) floor(count($series) / 2)] ?? null, end($series)] as $tick): ?>
                <?php if (is_array($tick)): ?>
                    <span><?= e($dayLabel((string) ($tick['day'] ?? 'now'))) ?></span>
                <?php endif; ?>
            <?php endforeach; ?>
            <span class="ms-auto">Bar number = orders that day</span>
        </div>
    <?php endif; ?>
</section>

<div class="mt-5 grid gap-5 xl:grid-cols-3">
    <section aria-labelledby="desk-money" class="rounded-card border border-brand-100 bg-white p-4 shadow-soft sm:p-5 xl:col-span-2">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h2 id="desk-money" class="text-base font-black tracking-tight text-ink">Payment checks in the queue</h2>
            <a href="<?= e(route('admin.orders', [], ['payment_status' => 'pending_verification'])) ?>" class="text-xs font-bold text-brand-700 underline underline-offset-4 hover:text-brand-800">See all</a>
        </div>

        <?php if ($pendingPayments === []): ?>
            <?= $view->component('empty-state', [
                'title' => 'Nothing to verify',
                'text' => 'Every declared payment has been checked against the statement. New ones show up here.',
                'icon' => 'check',
            ]) ?>
        <?php else: ?>
            <ul class="mt-3 space-y-2">
                <?php foreach ($pendingPayments as $payment): ?>
                    <?php
                    $orderId = (int) ($payment['order_id'] ?? 0);
                    $amount = (float) ($payment['amount'] ?? 0);
                    ?>
                    <li class="flex flex-wrap items-center gap-2 rounded-brand border border-amber-200 bg-amber-50/70 p-3">
                        <div class="min-w-0 flex-1">
                            <p class="flex flex-wrap items-center gap-2 text-sm font-black text-ink">
                                <a href="<?= e(route('admin.order.show', ['id' => $orderId])) ?>" class="underline decoration-amber-300 underline-offset-4 hover:text-brand-800">
                                    <?= e((string) ($payment['order_no'] ?? 'Order')) ?>
                                </a>
                                <span class="rounded-pill bg-white px-2 py-0.5 text-[0.7rem] font-bold text-ink-400">
                                    <?= e(status_label((string) ($payment['method_label'] ?? $payment['method'] ?? ''))) ?>
                                </span>
                            </p>
                            <p class="mt-1 truncate text-xs text-ink-400">
                                <?= e((string) ($payment['customer_name'] ?? '')) ?> &middot;
                                <?= e((string) ($payment['phone'] ?? '')) ?> &middot;
                                TrxID <?= e((string) ($payment['txn_id'] ?? 'not given')) ?>
                                <?php if (($payment['sender_number'] ?? '') !== null && (string) ($payment['sender_number'] ?? '') !== ''): ?>
                                    &middot; from <?= e((string) $payment['sender_number']) ?>
                                <?php endif; ?>
                            </p>
                        </div>
                        <p class="text-sm font-black text-ink"><?= e(taka($amount)) ?></p>
                        <p class="w-full text-[0.7rem] font-semibold text-ink-400 sm:w-auto"><?= e(time_ago((string) ($payment['created_at'] ?? 'now'))) ?></p>
                        <a href="<?= e(route('admin.order.show', ['id' => $orderId])) ?>" class="rounded-brand bg-amber-500 px-3 py-1.5 text-xs font-black text-white transition-colors duration-200 hover:bg-amber-600">Check</a>
                    </li>
                <?php endforeach; ?>
            </ul>
            <p class="mt-3 text-xs font-semibold text-ink-400">
                Nobody is paid until a person has matched this against the wallet statement, right here in the panel.
            </p>
        <?php endif; ?>
    </section>

    <section aria-labelledby="desk-stock" class="rounded-card border border-brand-100 bg-white p-4 shadow-soft sm:p-5">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h2 id="desk-stock" class="text-base font-black tracking-tight text-ink">Running low</h2>
            <a href="<?= e(route('admin.plans')) ?>" class="text-xs font-bold text-brand-700 underline underline-offset-4 hover:text-brand-800">Catalogue</a>
        </div>

        <?php if ($lowStock === []): ?>
            <p class="mt-3 rounded-brand bg-emerald-50 px-4 py-5 text-sm font-semibold text-emerald-800">
                Every active plan still has stock. Nothing to restock today.
            </p>
        <?php else: ?>
            <ul class="mt-3 space-y-2">
                <?php foreach ($lowStock as $plan): ?>
                    <?php $stock = (int) ($plan['stock'] ?? 0); ?>
                    <li class="flex items-center gap-3 rounded-brand border border-rose-200 bg-rose-50/70 p-3">
                        <span class="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-white text-sm font-black <?= e($stock <= 0 ? 'text-rose-700' : 'text-amber-700') ?>">
                            <?= e((string) $stock) ?>
                        </span>
                        <span class="min-w-0">
                            <span class="block truncate text-sm font-bold text-ink"><?= e((string) ($plan['title'] ?? '')) ?></span>
                            <span class="block truncate text-xs text-ink-400"><?= e((string) ($plan['service_name'] ?? '')) ?></span>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <dl class="mt-4 grid grid-cols-3 gap-2 border-t border-dashed border-brand-100 pt-4 text-center">
            <?php foreach (['customers' => 'Customers', 'staff' => 'Staff', 'active' => 'Active'] as $key => $caption): ?>
                <div>
                    <dt class="text-[0.65rem] font-black uppercase tracking-[0.12em] text-ink-400"><?= e($caption) ?></dt>
                    <dd class="text-lg font-black text-ink"><?= e(number_format((int) ($userCounts[$key] ?? 0))) ?></dd>
                </div>
            <?php endforeach; ?>
        </dl>
    </section>
</div>

<div class="mt-5 grid gap-5 xl:grid-cols-3">
    <section aria-labelledby="desk-recent" class="rounded-card border border-brand-100 bg-white p-4 shadow-soft sm:p-5 xl:col-span-2">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h2 id="desk-recent" class="text-base font-black tracking-tight text-ink">Latest orders</h2>
            <a href="<?= e(route('admin.orders')) ?>" class="text-xs font-bold text-brand-700 underline underline-offset-4 hover:text-brand-800">Filter the lot</a>
        </div>

        <?php if ($recentOrders === []): ?>
            <div class="mt-3">
                <?= $view->component('empty-state', [
                    'title' => 'No orders yet',
                    'text' => 'The first order a customer places will appear on this table.',
                    'action_url' => route('home'),
                    'action_label' => 'Look at the shop',
                    'icon' => 'bag',
                ]) ?>
            </div>
        <?php else: ?>
            <div class="mt-3 overflow-x-auto">
                <table class="a-table">
                    <thead>
                        <tr>
                            <th scope="col">Order</th>
                            <th scope="col">Customer</th>
                            <th scope="col">Pay</th>
                            <th scope="col">Total</th>
                            <th scope="col">Placed</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentOrders as $order): ?>
                            <?php $orderId = (int) ($order['id'] ?? 0); ?>
                            <tr>
                                <td>
                                    <a href="<?= e(route('admin.order.show', ['id' => $orderId])) ?>" class="font-black text-brand-700 underline decoration-brand-200 underline-offset-4 hover:text-brand-800">
                                        <?= e((string) ($order['order_no'] ?? '')) ?>
                                    </a>
                                    <span class="mt-1 block"><?= $view->component('status-pill', ['status' => (string) ($order['payment_status'] ?? '')]) ?></span>
                                </td>
                                <td>
                                    <span class="block max-w-[14rem] truncate font-semibold"><?= e((string) ($order['customer_name'] ?? '')) ?></span>
                                    <span class="block max-w-[14rem] truncate text-xs text-ink-400"><?= e((string) ($order['phone'] ?? '')) ?></span>
                                </td>
                                <td><?= $view->component('status-pill', ['status' => (string) ($order['status'] ?? '')]) ?></td>
                                <td class="whitespace-nowrap font-black"><?= e(taka((float) ($order['total'] ?? 0))) ?></td>
                                <td class="whitespace-nowrap text-xs font-semibold text-ink-400"><?= e(time_ago((string) ($order['created_at'] ?? 'now'))) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <section aria-labelledby="desk-best" class="rounded-card border border-brand-100 bg-white p-4 shadow-soft sm:p-5">
        <h2 id="desk-best" class="text-base font-black tracking-tight text-ink">What actually sells</h2>
        <p class="mt-0.5 text-xs text-ink-400">Paid orders only, by brand.</p>

        <?php if ($bestSellers === []): ?>
            <p class="mt-3 rounded-brand bg-brand-50 px-4 py-5 text-sm text-ink-400">
                No sales counted yet - this fills in after the first verified payment.
            </p>
        <?php else: ?>
            <ol class="mt-3 space-y-2">
                <?php foreach ($bestSellers as $index => $seller): ?>
                    <li class="flex items-center gap-3 rounded-brand bg-brand-50/60 p-3">
                        <span class="grid h-8 w-8 shrink-0 place-items-center rounded-full bg-white text-xs font-black text-brand-700"><?= e((string) ($index + 1)) ?></span>
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-sm font-bold text-ink"><?= e((string) ($seller['service_name'] ?? '')) ?></span>
                            <span class="block text-xs text-ink-400"><?= e((string) (int) ($seller['times'] ?? 0)) ?> sold</span>
                        </span>
                        <span class="whitespace-nowrap text-xs font-black text-ink"><?= e(taka((float) ($seller['revenue'] ?? 0), false)) ?></span>
                    </li>
                <?php endforeach; ?>
            </ol>
        <?php endif; ?>
    </section>
</div>

<section aria-labelledby="desk-note" class="mt-5 rounded-card border border-dashed border-brand-200 bg-brand-50/50 p-4 sm:p-5">
    <h2 id="desk-note" class="text-sm font-black text-ink">How this desk works</h2>
    <ul class="mt-2 grid gap-2 text-xs font-semibold text-ink-400 sm:grid-cols-3">
        <li class="rounded-brand bg-white p-3">A customer pays by hand. Nothing is ever marked paid by the site - only by a person pressing Verify on the order page.</li>
        <li class="rounded-brand bg-white p-3">Delivered account details are encrypted at rest and shown only on this panel, never sent back through a redirect.</li>
        <li class="rounded-brand bg-white p-3">Every change you make is stamped in the audit trail with your name, so a handover at midnight is readable.</li>
    </ul>
</section>
