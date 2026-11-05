<?php

declare(strict_types=1);

/**
 * The customer's own dashboard. Every row here was fetched by user id, and the
 * controller already stripped the columns a customer has no business seeing
 * (staff ids, stored IPs, encrypted delivery blobs).
 *
 * @var App\Core\View $view
 * @var array{name: string, email: string, phone: string, joined_at: string} $user
 * @var list<array<string, mixed>> $orders
 * @var array<string, list<array<string, mixed>>> $lines
 * @var list<array<string, mixed>> $deliveries
 * @var array{orders: int, spent: float} $summary
 * @var array{awaiting_payment: int, in_delivery: int, delivered: int, unpaid_total: float} $counters
 * @var string $whatsappUrl
 */
$brandName = setting('brand.name', 'DexxozzBD');
$user = (array) ($user ?? []);
$orders = (array) ($orders ?? []);
$lines = (array) ($lines ?? []);
$deliveries = (array) ($deliveries ?? []);
$summary = (array) ($summary ?? ['orders' => 0, 'spent' => 0.0]);
$counters = (array) ($counters ?? ['awaiting_payment' => 0, 'in_delivery' => 0, 'delivered' => 0, 'unpaid_total' => 0.0]);
$whatsappUrl = (string) ($whatsappUrl ?? '');

$firstName = trim(explode(' ', (string) ($user['name'] ?? ''))[0]);
$initial = mb_strtoupper(mb_substr($firstName === '' ? (string) ($user['email'] ?? 'Y') : $firstName, 0, 1));

$view->extend('layouts/app');
$view->setSection('title', 'My account - ' . $brandName);
$view->setSection('meta_description', 'Your ' . $brandName . ' orders, payment states and delivery history in one place.');
$view->setSection('canonical', route('account'));

$stats = [
    ['label' => 'Orders on file', 'value' => (string) (int) ($summary['orders'] ?? 0), 'note' => 'placed with this email', 'tone' => 'from-brand-600 to-brand-500'],
    ['label' => 'Total spent', 'value' => taka((float) ($summary['spent'] ?? 0), false), 'note' => 'verified payments only', 'tone' => 'from-accent-500 to-accent-600'],
    ['label' => 'Delivered', 'value' => (string) (int) ($counters['delivered'] ?? 0), 'note' => 'on the ' . count($orders) . ' newest orders', 'tone' => 'from-emerald-500 to-emerald-600'],
    ['label' => 'Waiting on you', 'value' => (string) (int) ($counters['awaiting_payment'] ?? 0), 'note' => taka((float) ($counters['unpaid_total'] ?? 0), false) . ' still unpaid', 'tone' => 'from-amber-500 to-rose-500'],
];
?>
<div class="bg-gradient-to-b from-brand-50 via-white to-white">
    <?php /* ------------------------------ header ------------------------------*/ ?>
    <section class="relative overflow-hidden">
        <div class="animate-gradient absolute inset-0 bg-gradient-to-r from-brand-700 via-brand-500 to-accent-500" aria-hidden="true"></div>
        <span class="blob -left-16 -top-16 opacity-40" aria-hidden="true"></span>
        <span class="blob right-0 bottom-[-6rem] opacity-30 [animation-delay:-7s]" aria-hidden="true"></span>

        <div class="relative mx-auto max-w-6xl px-4 py-10 lg:px-6 lg:py-12">
            <div class="flex flex-wrap items-center gap-5">
                <span class="animate-pop grid h-16 w-16 shrink-0 place-items-center rounded-card bg-white/15 text-2xl font-black text-white ring-1 ring-white/30 backdrop-blur">
                    <?= e($initial) ?>
                </span>
                <div class="min-w-0 grow">
                    <p class="text-[11px] font-black uppercase tracking-[0.22em] text-white/75">My account</p>
                    <h1 class="mt-1 text-2xl font-black tracking-tight text-white md:text-4xl">
                        Hello<?= $firstName === '' ? '' : ', ' . e($firstName) ?>
                    </h1>
                    <p class="mt-1.5 flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-white/85">
                        <span><?= e((string) ($user['email'] ?? '')) ?></span>
                        <?php if (($user['phone'] ?? '') !== ''): ?>
                            <span><?= e((string) $user['phone']) ?></span>
                        <?php endif; ?>
                        <?php if (($user['joined_at'] ?? '') !== ''): ?>
                            <span>Member since <?= e(date('M Y', strtotime((string) $user['joined_at']) ?: time())) ?></span>
                        <?php endif; ?>
                    </p>
                </div>
                <div class="flex shrink-0 flex-wrap gap-2">
                    <a href="<?= e(route('plans')) ?>" class="rounded-brand bg-white px-5 py-3 text-sm font-black text-brand-700 shadow-card transition-transform duration-200 hover:-translate-y-0.5">
                        Buy another plan
                    </a>
                    <form method="post" action="<?= e(route('logout')) ?>">
                        <?= csrf_field() ?>
                        <button type="submit" class="rounded-brand border-2 border-white/40 px-5 py-3 text-sm font-bold text-white transition-colors duration-200 hover:bg-white/15">
                            Sign out
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </section>

    <div class="mx-auto max-w-6xl px-4 py-10 lg:px-6">
        <?php /* ------------------------------ stats ------------------------------*/ ?>
        <section class="reveal grid gap-4 sm:grid-cols-2 lg:grid-cols-4" data-reveal aria-label="Account summary">
            <?php foreach ($stats as $index => $stat): ?>
                <div class="hover-lift rounded-card border border-brand-100 bg-white p-5 shadow-soft" style="--reveal-delay:<?= e((string) ($index * 80)) ?>ms">
                    <p class="text-xs font-black uppercase tracking-widest text-ink-400"><?= e((string) $stat['label']) ?></p>
                    <p class="mt-2 text-2xl font-black tracking-tight text-ink"><?= e((string) $stat['value']) ?></p>
                    <p class="mt-1 text-xs text-ink-400"><?= e((string) $stat['note']) ?></p>
                    <span class="mt-4 block h-1.5 w-full rounded-pill bg-gradient-to-r <?= e((string) $stat['tone']) ?>"></span>
                </div>
            <?php endforeach; ?>
        </section>

        <div class="mt-10 grid gap-8 lg:grid-cols-[1.4fr_1fr] lg:items-start">
            <?php /* --------------------------- orders ---------------------------*/ ?>
            <section aria-label="Order history">
                <?= $view->component('section-heading', [
                    'eyebrow' => 'Order history',
                    'title' => 'Everything you have bought',
                    'subtitle' => 'The newest ' . count($orders) . ' orders, newest first. Open one for the payment reference, the lines and the full timeline.',
                ]) ?>

                <?php if ($orders === []): ?>
                    <div class="mt-6">
                        <?= $view->component('empty-state', [
                            'icon' => 'bag',
                            'title' => 'No orders on this account yet',
                            'text' => 'Order as a guest and the moment you use this email again, the order shows up here. Or start with a plan and we will walk you through the bKash step.',
                            'action_url' => route('plans'),
                            'action_label' => 'Browse plans',
                        ]) ?>
                    </div>
                <?php else: ?>
                    <div class="mt-6 space-y-5">
                        <?php foreach ($orders as $index => $order): ?>
                            <?php
                            $order = (array) $order;
                            $orderNo = strtoupper(trim((string) ($order['order_no'] ?? '')));
                            $orderLines = (array) ($lines[$orderNo] ?? []);
                            $needsPayment = (string) ($order['payment_status'] ?? '') !== 'paid' && (string) ($order['status'] ?? '') !== 'cancelled';
                            ?>
                            <article class="reveal hover-lift rounded-card border border-brand-100 bg-white p-5 shadow-soft" data-reveal style="--reveal-delay:<?= e((string) (min(4, $index) * 70)) ?>ms">
                                <div class="flex flex-wrap items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="flex flex-wrap items-center gap-2">
                                            <span class="text-lg font-black tracking-tight text-ink"><?= e($orderNo) ?></span>
                                            <?php if ($needsPayment): ?>
                                                <span class="rounded-pill bg-amber-100 px-2 py-0.5 text-[10px] font-black uppercase tracking-wide text-amber-800">Action needed</span>
                                            <?php endif; ?>
                                        </p>
                                        <p class="mt-1 text-xs text-ink-400">
                                            <?php if (($order['created_at'] ?? '') !== ''): ?>
                                                Placed <?= e(date('d M Y, g:i a', (int) strtotime((string) $order['created_at']))) ?>
                                            <?php endif; ?>
                                            <?php if (($order['paid_at'] ?? '') !== ''): ?>
                                                &middot; paid <?= e(time_ago((string) $order['paid_at'])) ?>
                                            <?php endif; ?>
                                        </p>
                                    </div>
                                    <p class="text-right">
                                        <span class="block text-xl font-black tracking-tight text-brand-700"><?= e(taka((float) ($order['total'] ?? 0))) ?></span>
                                        <span class="text-[11px] font-bold uppercase tracking-widest text-ink-400">Total</span>
                                    </p>
                                </div>

                                <p class="mt-4 flex flex-wrap gap-2">
                                    <?php foreach (['status' => (string) ($order['status'] ?? ''), 'payment_status' => (string) ($order['payment_status'] ?? ''), 'fulfillment_status' => (string) ($order['fulfillment_status'] ?? '')] as $pillStatus): ?>
                                        <?= $view->component('status-pill', ['status' => $pillStatus]) ?>
                                    <?php endforeach; ?>
                                </p>

                                <?php if ($orderLines !== []): ?>
                                    <ul class="mt-4 divide-y divide-brand-50 rounded-brand bg-brand-50/60">
                                        <?php foreach ($orderLines as $line): ?>
                                            <li class="flex flex-wrap items-center gap-2 px-3.5 py-2.5 text-sm">
                                                <span class="font-bold text-ink"><?= e((string) ($line['service_name'] ?? '')) ?></span>
                                                <span class="text-ink-400"><?= e((string) ($line['plan_title'] ?? '')) ?></span>
                                                <?php if ((int) ($line['quantity'] ?? 1) > 1): ?>
                                                    <span class="text-xs font-bold text-brand-700">&times;<?= e((string) (int) $line['quantity']) ?></span>
                                                <?php endif; ?>
                                                <span class="ml-auto flex items-center gap-2">
                                                    <?= $view->component('status-pill', ['status' => (string) ($line['status'] ?? 'pending')]) ?>
                                                    <?php if (($line['delivered_at'] ?? '') !== ''): ?>
                                                        <span class="text-[11px] text-ink-400"><?= e(time_ago((string) $line['delivered_at'])) ?></span>
                                                    <?php endif; ?>
                                                </span>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php else: ?>
                                    <p class="mt-4 rounded-brand bg-brand-50/60 px-3.5 py-2.5 text-xs text-ink-400">
                                        Open the order to see its lines and the login we handed over.
                                    </p>
                                <?php endif; ?>

                                <p class="mt-4 flex flex-wrap gap-2">
                                    <a href="<?= e(route('order.show', ['orderNo' => $orderNo])) ?>" class="inline-flex items-center gap-2 rounded-brand bg-gradient-to-r from-brand-600 to-brand-500 px-4 py-2.5 text-sm font-black text-white shadow-pop transition-all duration-200 hover:from-brand-700 hover:to-brand-600">
                                        Open order
                                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                    </a>
                                    <?php if ($needsPayment): ?>
                                        <a href="<?= e(route('order.pay', ['orderNo' => $orderNo])) ?>" class="inline-flex items-center gap-2 rounded-brand border-2 border-amber-300 bg-amber-50 px-4 py-2.5 text-sm font-black text-amber-900 transition-colors duration-200 hover:bg-amber-100">
                                            Send payment details
                                        </a>
                                    <?php endif; ?>
                                    <a href="<?= e(route('track', [], ['order_no' => $orderNo])) ?>" class="inline-flex items-center gap-2 rounded-brand border border-brand-200 px-4 py-2.5 text-sm font-bold text-brand-700 transition-colors duration-200 hover:bg-brand-50">
                                        Track it
                                    </a>
                                </p>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>

            <?php /* --------------------- deliveries + support --------------------*/ ?>
            <aside class="space-y-6">
                <section class="reveal rounded-card border border-brand-100 bg-white p-5 shadow-soft" data-reveal style="--reveal-delay:120ms" aria-label="Latest deliveries">
                    <p class="text-xs font-black uppercase tracking-widest text-brand-600">Latest delivery states</p>
                    <?php if ($deliveries === []): ?>
                        <p class="mt-3 text-sm leading-relaxed text-ink-400">
                            Nothing has been handed over on these orders yet. Delivery happens one line at a
                            time - a person sends the login on WhatsApp and marks it here.
                        </p>
                    <?php else: ?>
                        <ul class="mt-4 space-y-3">
                            <?php foreach ($deliveries as $delivery): ?>
                                <li class="flex items-start gap-3">
                                    <span class="mt-1 h-2.5 w-2.5 shrink-0 rounded-full <?= e((string) ($delivery['status'] ?? '') === 'delivered' ? 'animate-pulse-ring bg-emerald-500' : 'bg-amber-400') ?>" aria-hidden="true"></span>
                                    <span class="min-w-0 grow">
                                        <span class="block truncate text-sm font-bold text-ink">
                                            <?= e((string) ($delivery['service_name'] ?? '')) ?>
                                            <span class="font-normal text-ink-400"><?= e((string) ($delivery['plan_title'] ?? '')) ?></span>
                                        </span>
                                        <span class="block text-[11px] text-ink-400">
                                            <?= e(strtoupper((string) ($delivery['order_no'] ?? ''))) ?>
                                            &middot; <?= e((string) ($delivery['status'] ?? '') === 'delivered' && ($delivery['delivered_at'] ?? '') !== '' ? 'delivered ' . time_ago((string) $delivery['delivered_at']) : status_label((string) ($delivery['status'] ?? 'pending'))) ?>
                                        </span>
                                    </span>
                                    <?= $view->component('status-pill', ['status' => (string) ($delivery['status'] ?? 'pending')]) ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                    <a href="<?= e(route('track')) ?>" class="mt-4 block rounded-brand bg-brand-50 px-4 py-2.5 text-center text-sm font-bold text-brand-700 transition-colors duration-200 hover:bg-brand-100">
                        Track an older order
                    </a>
                </section>

                <section class="reveal overflow-hidden rounded-card bg-gradient-to-br from-emerald-600 to-emerald-700 p-5 text-white shadow-card" data-reveal style="--reveal-delay:200ms" aria-label="Support">
                    <h3 class="text-base font-black tracking-tight">Something not right?</h3>
                    <p class="mt-2 text-sm leading-relaxed text-white/85">
                        <?= e(setting('whatsapp.agent_name', 'DexxozzBD Support')) ?> &middot;
                        <?= e(setting('whatsapp.agent_role', 'Order desk')) ?>. Quote your order number and
                        we can see the whole history without asking you to explain it.
                    </p>
                    <p class="mt-3 text-xs text-white/70"><?= e(setting('whatsapp.hours', 'Open 9:00 AM - 11:00 PM (Dhaka time), every day')) ?></p>
                    <?php if ($whatsappUrl !== ''): ?>
                        <a href="<?= e($whatsappUrl) ?>" target="_blank" rel="noopener" class="mt-4 inline-flex items-center gap-2 rounded-brand bg-white px-5 py-3 text-sm font-black text-emerald-800 transition-transform duration-200 hover:-translate-y-0.5">
                            <svg class="h-5 w-5 text-emerald-600" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                <path d="M12 2a10 10 0 0 0-8.6 15L2 22l5.2-1.3A10 10 0 1 0 12 2Zm5.3 14c-.2.6-1.2 1.2-1.7 1.2-.5.1-1 .1-1.7-.1-.9-.3-2.3-1-3.6-2.6-1-1.2-1.6-2.5-1.7-3-.1-.6.3-1.4.8-1.8.2-.2.4-.2.6-.2h.5c.2 0 .3 0 .4.3l.6 1.4c.1.2 0 .4-.1.5l-.4.5c-.1.1-.1.3 0 .4.3.5 1.2 1.5 2.1 1.9.2.1.3.1.5-.1l.5-.6c.1-.2.3-.2.5-.1l1.4.7c.2.1.3.2.3.4 0 .2 0 .7-.3 1.2Z"/>
                            </svg>
                            Message the order desk
                        </a>
                    <?php endif; ?>
                </section>

                <section class="reveal rounded-card border border-brand-100 bg-brand-50/60 p-5" data-reveal style="--reveal-delay:280ms">
                    <p class="text-xs font-black uppercase tracking-widest text-brand-700">Account housekeeping</p>
                    <ul class="mt-3 space-y-2 text-xs leading-relaxed text-ink-400">
                        <li>Guest orders join this account as soon as you check out with this email.</li>
                        <li>Login details we send are encrypted at rest and only ever shown to you.</li>
                        <li>Want the account removed? Ask on WhatsApp or by email and we delete it.</li>
                    </ul>
                    <a href="<?= e(route('contact')) ?>" class="mt-4 inline-block text-xs font-black text-brand-700 underline decoration-accent-400 decoration-2 underline-offset-4">
                        Contact the team
                    </a>
                </section>
            </aside>
        </div>
    </div>
</div>
