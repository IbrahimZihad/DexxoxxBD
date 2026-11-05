<?php

declare(strict_types=1);

/**
 * The order page a customer lands on after declaring a payment.
 *
 * This controller only ever gets here for an order the browser proved it owns
 * (placed, paid or tracked in this session, or the signed-in account's email
 * matches), so the contact details shown are the customer's own. Credentials we
 * hand over stay encrypted and are never printed - the timeline, the payment
 * rows and the amounts are the whole story.
 *
 * @var App\Core\View $view
 * @var array<string, mixed>|null $order
 * @var string $orderNo
 * @var list<array<string, mixed>> $items
 * @var list<array<string, mixed>> $payments
 * @var list<array<string, mixed>> $history
 * @var string $whatsappUrl
 */
$brandName = setting('brand.name', 'ZapSub');
$order = ($order ?? null) === null ? null : (array) $order;
$orderNo = strtoupper(trim((string) ($orderNo ?? '')));
$items = (array) ($items ?? []);
$payments = (array) ($payments ?? []);
$history = (array) ($history ?? []);
$whatsappUrl = (string) ($whatsappUrl ?? '');

$view->extend('layouts/app');
$view->setSection('title', 'Order not found - ' . $brandName);
$view->setSection('meta_description', 'Track your order, see every step our team has taken and message us about it on WhatsApp.');
$view->setSection('canonical', route('track'));

if ($order === null) {
    ?>
    <div class="relative bg-gradient-to-b from-brand-50 via-white to-white">
        <span class="blob -right-24 top-24 animate-blob opacity-30" aria-hidden="true"></span>

        <div class="relative mx-auto max-w-3xl px-4 py-14 lg:px-6">
            <?= $view->component('section-heading', [
                'eyebrow' => 'Order page',
                'title' => 'We cannot open that order here',
                'align' => 'center',
            ]) ?>

            <div class="mt-8">
                <?= $view->component('empty-state', [
                    'icon' => 'inbox',
                    'title' => $orderNo === '' ? 'No order number in this link' : 'Nothing to show for ' . $orderNo,
                    'text' => 'Order pages only open in the browser that placed the order. If this is yours, confirm it once with the email you ordered with and everything will appear.',
                    'action_url' => $orderNo === '' ? route('track') : route('track', [], ['order_no' => $orderNo]),
                    'action_label' => 'Track it with your email',
                ]) ?>
            </div>

            <p class="mt-8 text-center text-xs leading-relaxed text-ink-400">
                Wrong number, or ordered a while ago?
                <a href="<?= e(wa_link('I need help finding my order.')) ?>" target="_blank" rel="noopener" class="font-bold text-brand-700 underline decoration-accent-400 decoration-2 underline-offset-4">Message us on WhatsApp</a>
                and we will find it together.
            </p>
        </div>
    </div>
    <?php
    return;
}

$orderNo = (string) $order['order_no'];
$total = (float) $order['total'];
$paymentStatus = (string) ($order['payment_status'] ?? 'unpaid');
$orderStatus = (string) ($order['status'] ?? 'pending');
$fulfillmentStatus = (string) ($order['fulfillment_status'] ?? 'unassigned');
$createdAt = (string) ($order['created_at'] ?? '');
$paidAt = (string) ($order['paid_at'] ?? '');
$note = trim((string) ($order['note'] ?? ''));
$moment = $createdAt === '' ? false : strtotime($createdAt);

$view->setSection('title', 'Order ' . $orderNo . ' - ' . $brandName);
$view->setSection('canonical', route('order.show', ['orderNo' => $orderNo]));

$deliveryTitle = match ($fulfillmentStatus) {
    'delivered' => 'Delivered',
    'assigned' => 'With our delivery desk',
    default => 'Waiting to be assigned',
};

$deliveryText = match (true) {
    $fulfillmentStatus === 'delivered' => 'Every line on this order has been handed over. If anything looks wrong, tell us on WhatsApp and we fix it.',
    $fulfillmentStatus === 'assigned' => 'A person is preparing your logins right now.',
    $paymentStatus === 'paid' => 'Payment verified - the order is in the queue for the desk.',
    $paymentStatus === 'pending_verification' => 'Your reference is being checked. Delivery starts the moment it clears.',
    default => 'Delivery starts as soon as a payment is verified.',
};
?>
<div class="relative bg-gradient-to-b from-brand-50 via-white to-white">
    <span class="blob -left-24 top-24 animate-blob opacity-35" aria-hidden="true"></span>
    <span class="blob -right-20 top-72 animate-blob opacity-30 [animation-delay:-6s]" aria-hidden="true"></span>

    <div class="relative mx-auto max-w-7xl px-4 py-10 pb-16 lg:px-6 lg:py-14">
        <?php /* ------------------------------ headline ------------------------------*/ ?>
        <div class="flex flex-wrap items-start justify-between gap-5">
            <div class="min-w-0">
                <p class="flex items-center gap-2 text-xs font-black uppercase tracking-[0.18em] text-accent-600">
                    <span class="h-px w-8 bg-gradient-to-r from-brand-400 to-accent-400" aria-hidden="true"></span>
                    Order page
                </p>
                <h1 class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-3xl font-black tracking-tight text-ink md:text-4xl">
                    <span><?= e($orderNo) ?></span>
                    <button type="button" data-copy data-copy-value="<?= e($orderNo) ?>"
                            class="rounded-pill bg-white px-3 py-1 text-[11px] font-bold text-brand-700 ring-1 ring-brand-200 transition-colors duration-200 hover:bg-brand-100">
                        Copy number
                    </button>
                </h1>
                <?php if ($moment !== false): ?>
                    <p class="mt-2 text-sm text-ink-400">Placed <?= e(date('d M Y, g:i a', $moment)) ?><?= $paidAt === '' ? '' : ' &middot; verified ' . e(time_ago($paidAt)) ?></p>
                <?php endif; ?>

                <div class="mt-3 flex flex-wrap gap-2">
                    <?php
                    $status = $orderStatus;
                    echo $view->component('status-pill', ['status' => $status]);
                    $status = $paymentStatus;
                    echo $view->component('status-pill', ['status' => $status]);
                    $status = $fulfillmentStatus;
                    echo $view->component('status-pill', ['status' => $status]);
                    ?>
                </div>
            </div>

            <div class="flex shrink-0 flex-wrap gap-3 print:hidden">
                <a href="<?= e(route('track')) ?>" class="inline-flex items-center gap-2 rounded-brand border-2 border-brand-200 bg-white px-4 py-2.5 text-sm font-bold text-brand-700 transition-colors duration-200 hover:border-brand-400 hover:bg-brand-50">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.6-3.6" stroke-linecap="round"/></svg>
                    Track another order
                </a>
                <button type="button" data-print class="inline-flex items-center gap-2 rounded-brand border-2 border-brand-200 bg-white px-4 py-2.5 text-sm font-bold text-brand-700 transition-colors duration-200 hover:border-brand-400 hover:bg-brand-50">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M7 9V4h10v5M7 19H5v-6h14v6h-2" stroke-linecap="round" stroke-linejoin="round"/><rect x="7" y="15" width="10" height="5" rx="1"/></svg>
                    Print receipt
                </button>
            </div>
        </div>

        <div class="mt-6">
            <?php include $view->path('pages.partials.order-steps'); ?>
        </div>

        <div class="mt-8 grid gap-8 lg:grid-cols-[1.5fr_1fr] lg:items-start">
            <?php /* ------------------------------- detail ------------------------------*/ ?>
            <div class="space-y-6">
                <section class="reveal rounded-card border border-brand-100 bg-white p-5 shadow-card print:border-0 print:p-0 print:shadow-none" data-reveal>
                    <h2 class="mb-3 text-sm font-black uppercase tracking-widest text-ink-400">What you ordered</h2>
                    <?php $showStatus = true; include $view->path('pages.partials.order-items'); ?>
                </section>

                <section class="reveal rounded-card border border-brand-100 bg-white p-5 shadow-card print:break-inside-avoid" data-reveal>
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <h2 class="text-sm font-black uppercase tracking-widest text-ink-400">Delivery</h2>
                        <span class="rounded-pill px-3 py-1 text-xs font-black <?= e(status_badge($fulfillmentStatus)) ?>"><?= e($deliveryTitle) ?></span>
                    </div>
                    <p class="mt-3 text-sm leading-relaxed text-ink"><?= e($deliveryText) ?></p>

                    <dl class="mt-4 grid gap-3 border-t border-dashed border-brand-100 pt-4 text-sm sm:grid-cols-2">
                        <div>
                            <dt class="text-[11px] font-black uppercase tracking-widest text-ink-400">Sending updates to</dt>
                            <dd class="mt-0.5 font-bold text-ink"><?= e((string) ($order['email'] ?? '')) ?></dd>
                        </div>
                        <div>
                            <dt class="text-[11px] font-black uppercase tracking-widest text-ink-400">WhatsApp number on file</dt>
                            <dd class="mt-0.5 font-mono font-bold text-ink"><?= e((string) ($order['phone'] ?? '')) ?></dd>
                        </div>
                        <?php if ($note !== ''): ?>
                            <div class="sm:col-span-2">
                                <dt class="text-[11px] font-black uppercase tracking-widest text-ink-400">Your note</dt>
                                <dd class="mt-0.5 leading-relaxed text-ink"><?= e($note) ?></dd>
                            </div>
                        <?php endif; ?>
                    </dl>

                    <p class="mt-4 rounded-brand bg-brand-50/70 p-3 text-xs leading-relaxed text-ink-400">
                        Whatever you wrote in the delivery request field is kept encrypted and read only by the person preparing your order. The account details we hand over are sent to you directly, never printed on this page.
                    </p>
                </section>

                <?php if ($payments !== []): ?>
                    <section class="reveal rounded-card border border-brand-100 bg-white p-5 shadow-card print:break-inside-avoid" data-reveal>
                        <h2 class="mb-3 text-sm font-black uppercase tracking-widest text-ink-400">Payments you declared</h2>
                        <?php include $view->path('pages.partials.order-payments'); ?>
                    </section>
                <?php endif; ?>

                <section class="reveal rounded-card border border-brand-100 bg-white p-5 shadow-card print:break-inside-avoid" data-reveal>
                    <h2 class="mb-3 text-sm font-black uppercase tracking-widest text-ink-400">Every step so far</h2>
                    <?php include $view->path('pages.partials.order-timeline'); ?>
                </section>
            </div>

            <?php /* ------------------------------- summary ------------------------------*/ ?>
            <aside class="space-y-5 lg:sticky lg:top-24">
                <div class="reveal rounded-card border border-brand-100 bg-white p-5 shadow-card" data-reveal>
                    <h2 class="text-sm font-black uppercase tracking-widest text-ink-400">Amounts</h2>

                    <dl class="mt-3 space-y-1.5 text-sm">
                        <div class="flex items-center justify-between gap-3">
                            <dt class="text-ink-400">Subtotal</dt>
                            <dd class="font-bold text-ink"><?= e(taka((float) ($order['subtotal'] ?? 0))) ?></dd>
                        </div>
                        <?php if ((float) ($order['discount'] ?? 0) > 0): ?>
                            <div class="flex items-center justify-between gap-3">
                                <dt class="text-ink-400">Coupon<?= ($order['coupon_code'] ?? '') === '' ? '' : ' ' . e((string) $order['coupon_code']) ?></dt>
                                <dd class="font-bold text-lime-700">&minus; <?= e(taka((float) $order['discount'])) ?></dd>
                            </div>
                        <?php endif; ?>
                        <div class="flex items-center justify-between gap-3 border-t border-brand-100 pt-2">
                            <dt class="font-black text-ink">Total</dt>
                            <dd class="text-2xl font-black tracking-tight text-brand-700"><?= e(taka($total)) ?></dd>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <dt class="text-ink-400">Payment</dt>
                            <dd class="font-bold text-ink"><?= e(status_label($paymentStatus)) ?></dd>
                        </div>
                    </dl>

                    <p class="mt-3 text-xs leading-relaxed text-ink-400">
                        These are the figures our server wrote when the order was placed. They do not change with anything typed in a browser.
                    </p>
                </div>

                <?php include $view->path('pages.partials.order-status'); ?>

                <div class="reveal space-y-3 rounded-card border border-brand-100 bg-white p-5 shadow-card print:hidden" data-reveal>
                    <?php if ($paymentStatus === 'unpaid' && !in_array($orderStatus, ['cancelled', 'failed'], true)): ?>
                        <a href="<?= e(route('order.pay', ['orderNo' => $orderNo])) ?>" class="block rounded-brand bg-gradient-to-r from-brand-600 to-brand-500 px-4 py-3 text-center text-sm font-black text-white shadow-pop transition-all duration-200 hover:from-brand-700 hover:to-brand-600">
                            Pay this order
                        </a>
                    <?php elseif (!in_array($orderStatus, ['cancelled', 'failed'], true)): ?>
                        <a href="<?= e(route('order.pay', ['orderNo' => $orderNo])) ?>" class="block rounded-brand border-2 border-brand-200 bg-white px-4 py-2.5 text-center text-sm font-bold text-brand-700 transition-colors duration-200 hover:border-brand-400 hover:bg-brand-50">
                            Open the payment page
                        </a>
                    <?php endif; ?>

                    <?php if ($whatsappUrl !== ''): ?>
                        <a href="<?= e($whatsappUrl) ?>" target="_blank" rel="noopener"
                           class="flex items-center justify-center gap-2 rounded-brand bg-[#128c7e] px-4 py-3 text-sm font-black text-white shadow-pop transition-colors duration-200 hover:bg-[#0f766b]">
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2a10 10 0 0 0-8.5 15.3L2 22l4.8-1.5A10 10 0 1 0 12 2Zm5.3 14.1c-.2.6-1.2 1.2-1.7 1.2-.5.1-1 .2-3.3-.7-2.7-1-4.4-3.7-4.6-3.9-.1-.2-.9-1.2-.9-2.3s.6-1.6.8-1.9c.2-.2.4-.3.6-.3h.5c.2 0 .4 0 .6.5l.8 1.9c.1.2.1.4 0 .6l-.4.6c-.2.2-.3.4-.1.7.2.3.8 1.3 1.7 1.9 1.2.8 1.7.8 2 .7l.6-.7c.2-.3.4-.2.6-.1l1.8.9c.3.1.4.2.5.3v.8Z"/></svg>
                            Ask about this order
                        </a>

                        <p class="text-[11px] leading-relaxed text-ink-400">
                            The message is already filled in with your order number, the items and the reference you gave us.
                        </p>
                    <?php endif; ?>
                </div>

                <p class="text-[11px] leading-relaxed text-ink-400 print:hidden">
                    Keep this page open - it updates on its own while we work.
                    <a href="<?= e(route('plans')) ?>" class="font-bold text-brand-700 underline decoration-accent-400 decoration-2 underline-offset-4">Order something else</a>
                    whenever you are ready.
                </p>
            </aside>
        </div>
    </div>
</div>

<?php include $view->path('pages.partials.order-live'); ?>
