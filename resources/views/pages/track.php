<?php

declare(strict_types=1);

/**
 * Track an order without an account: order number plus the email it was written
 * with. Both halves have to match, and when they do not the page says exactly
 * what it says when the number itself is unknown, so this form cannot be used to
 * find out whether an order exists.
 *
 * @var App\Core\View $view
 * @var array<string, mixed>|null $order
 * @var string $orderNo
 * @var string $email
 * @var string $failure
 * @var list<array<string, mixed>> $items
 * @var list<array<string, mixed>> $payments
 * @var list<array<string, mixed>> $history
 * @var string $whatsappUrl
 */
$brandName = setting('brand.name', 'ZapSub');
$order = ($order ?? null) === null ? null : (array) $order;
$orderNo = strtoupper(trim((string) ($orderNo ?? '')));
$email = trim((string) ($email ?? ''));
$failure = trim((string) ($failure ?? ''));
$items = (array) ($items ?? []);
$payments = (array) ($payments ?? []);
$history = (array) ($history ?? []);
$whatsappUrl = (string) ($whatsappUrl ?? '');

$view->extend('layouts/app');
$view->setSection('title', 'Track your order - ' . $brandName);
$view->setSection('meta_description', 'Enter your ZapSub order number and email to see payment, verification and delivery status.');
$view->setSection('canonical', route('track'));
?>
<div class="relative bg-gradient-to-b from-brand-50 via-white to-white">
    <span class="blob -left-24 top-24 animate-blob opacity-35" aria-hidden="true"></span>
    <span class="blob -right-24 top-96 animate-blob opacity-30 [animation-delay:-8s]" aria-hidden="true"></span>

    <div class="relative mx-auto max-w-7xl px-4 py-10 lg:px-6 lg:py-14">
        <?= $view->component('section-heading', [
            'eyebrow' => 'Order tracking',
            'title' => 'Where is my order?',
            'subtitle' => 'The number from your confirmation page and the email you typed at checkout. That pair is the only key - nothing about another order opens with it.',
        ]) ?>

        <div class="mt-8 grid gap-8 lg:grid-cols-[1fr_1.35fr] lg:items-start">
            {{-- ------------------------------- lookup ------------------------------ --}}
            <section class="reveal rounded-card border border-brand-100 bg-white p-5 shadow-card sm:p-6" data-reveal aria-label="Order lookup">
                <h2 class="text-base font-black text-ink">Look it up</h2>
                <p class="mt-1 text-xs leading-relaxed text-ink-400">
                    Ordered through WhatsApp instead of the site? Use the number we sent you and the email you gave us.
                </p>

                <form method="post" action="<?= e(route('track.lookup')) ?>" class="mt-5 space-y-4">
                    <?= csrf_field() ?>

                    <?= $view->component('field', [
                        'name' => 'order_no',
                        'label' => 'Order number',
                        'type' => 'text',
                        'value' => $orderNo,
                        'placeholder' => 'ZS-1105-AB12CD',
                        'required' => true,
                        'autocomplete' => 'off',
                        'hint' => 'Printed on your confirmation page, in the payment link, and in every message we send you.',
                    ]) ?>

                    <?= $view->component('field', [
                        'name' => 'email',
                        'label' => 'Email used at checkout',
                        'type' => 'email',
                        'value' => $email,
                        'placeholder' => 'you@example.com',
                        'required' => true,
                        'autocomplete' => 'email',
                    ]) ?>

                    <button type="submit" class="group relative w-full overflow-hidden rounded-brand bg-gradient-to-r from-brand-600 via-brand-500 to-accent-500 px-5 py-3 text-sm font-black text-white shadow-glow transition-transform duration-200 hover:-translate-y-0.5">
                        <span class="animate-shimmer absolute inset-0 bg-gradient-to-r from-white/0 via-white/40 to-white/0" aria-hidden="true"></span>
                        <span class="relative">Find my order</span>
                    </button>
                </form>

                <?php if ($failure !== ''): ?>
                    <div class="animate-pop mt-5 rounded-card border border-rose-200 bg-rose-50 p-4" role="alert">
                        <p class="flex items-start gap-2 text-sm font-bold leading-relaxed text-rose-800">
                            <svg class="mt-0.5 h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 8v4M12 15.5h.01" stroke-linecap="round"/></svg>
                            <span><?= e($failure) ?></span>
                        </p>
                    </div>
                <?php endif; ?>

                <ul class="mt-5 space-y-2 border-t border-dashed border-brand-100 pt-5 text-xs leading-relaxed text-ink-400">
                    <li class="flex gap-2">
                        <span class="mt-0.5 text-accent-500" aria-hidden="true">&#10003;</span>
                        Both fields are needed: a number alone never opens an order page.
                    </li>
                    <li class="flex gap-2">
                        <span class="mt-0.5 text-accent-500" aria-hidden="true">&#10003;</span>
                        We show the same message for a wrong email as for a number that does not exist.
                    </li>
                    <li class="flex gap-2">
                        <span class="mt-0.5 text-accent-500" aria-hidden="true">&#10003;</span>
                        Nothing here is ever used to guess prices or stock - it only reads your own order.
                    </li>
                </ul>
            </section>

            {{-- ------------------------------- result ------------------------------ --}}
            <section aria-label="Order status" class="space-y-6">
                <?php if ($order === null): ?>
                    <?= $view->component('empty-state', [
                        'icon' => 'search',
                        'title' => $orderNo === '' ? 'Nothing looked up yet' : 'No order to show for that pair',
                        'text' => 'Type the order number and the checkout email on the left and the full timeline appears here - payment, verification and delivery, step by step.',
                    ]) ?>
                <?php else: ?>
                    <?php
                    $orderNo = (string) $order['order_no'];
                    $total = (float) $order['total'];
                    $paymentStatus = (string) ($order['payment_status'] ?? 'unpaid');
                    $orderStatus = (string) ($order['status'] ?? 'pending');
                    $fulfillmentStatus = (string) ($order['fulfillment_status'] ?? 'unassigned');
                    ?>
                <div class="reveal overflow-hidden rounded-card border border-brand-100 bg-white shadow-card" data-reveal>
                    <div class="relative overflow-hidden bg-gradient-to-r from-brand-700 via-brand-600 to-accent-500 px-5 py-4 text-white">
                        <span class="animate-shimmer absolute inset-0 bg-gradient-to-r from-white/0 via-white/25 to-white/0" aria-hidden="true"></span>
                        <p class="relative text-[11px] font-black uppercase tracking-[0.22em] text-white/75">Found it</p>
                        <p class="relative mt-1 flex flex-wrap items-center gap-3">
                            <span class="text-2xl font-black tracking-tight"><?= e($orderNo) ?></span>
                            <button type="button" data-copy data-copy-value="<?= e($orderNo) ?>"
                                    class="rounded-pill bg-white/15 px-3 py-1 text-[11px] font-black uppercase tracking-wide text-white ring-1 ring-white/40 transition-colors duration-200 hover:bg-white/25">
                                Copy number
                            </button>
                        </p>
                        <p class="relative mt-2 flex flex-wrap gap-2">
                            <?php
                            $status = $orderStatus;
                            echo $view->component('status-pill', ['status' => $status]);
                            $status = $paymentStatus;
                            echo $view->component('status-pill', ['status' => $status]);
                            $status = $fulfillmentStatus;
                            echo $view->component('status-pill', ['status' => $status]);
                            ?>
                        </p>
                    </div>

                    <div class="space-y-5 p-5">
                        <?php include $view->path('pages.partials.order-steps'); ?>

                        <?php if ($items !== []): ?>
                            <div>
                                <h3 class="mb-2 text-[11px] font-black uppercase tracking-widest text-ink-400">Lines</h3>
                                <?php $showStatus = true; include $view->path('pages.partials.order-items'); ?>
                            </div>
                        <?php endif; ?>

                        <dl class="space-y-1.5 rounded-brand bg-brand-50/70 p-4 text-sm">
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
                            <div class="flex items-center justify-between gap-3 border-t border-brand-200/70 pt-2">
                                <dt class="font-black text-ink">Total</dt>
                                <dd class="text-xl font-black tracking-tight text-brand-700"><?= e(taka($total)) ?></dd>
                            </div>
                        </dl>

                        <?php if ($payments !== []): ?>
                            <div>
                                <h3 class="mb-2 text-[11px] font-black uppercase tracking-widest text-ink-400">Payments you declared</h3>
                                <?php include $view->path('pages.partials.order-payments'); ?>
                            </div>
                        <?php endif; ?>

                        <div>
                            <h3 class="mb-3 text-[11px] font-black uppercase tracking-widest text-ink-400">Every step so far</h3>
                            <?php include $view->path('pages.partials.order-timeline'); ?>
                        </div>

                        <?php include $view->path('pages.partials.order-status'); ?>

                        <div class="flex flex-wrap gap-3">
                            <a href="<?= e(route('order.show', ['orderNo' => $orderNo])) ?>" class="inline-flex items-center gap-2 rounded-brand bg-gradient-to-r from-brand-600 to-brand-500 px-5 py-3 text-sm font-black text-white shadow-pop transition-all duration-200 hover:from-brand-700 hover:to-brand-600">
                                Open the full order page
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            </a>
                            <?php if ($whatsappUrl !== ''): ?>
                                <a href="<?= e($whatsappUrl) ?>" target="_blank" rel="noopener" class="inline-flex items-center gap-2 rounded-brand border-2 border-[#128c7e]/30 bg-[#128c7e]/10 px-5 py-3 text-sm font-black text-[#0b6b5f] transition-colors duration-200 hover:bg-[#128c7e]/20">
                                    Ask about it on WhatsApp
                                </a>
                            <?php endif; ?>
                            <a href="<?= e(route('track')) ?>" class="inline-flex items-center gap-2 rounded-brand border-2 border-brand-200 bg-white px-5 py-3 text-sm font-bold text-brand-700 transition-colors duration-200 hover:border-brand-400 hover:bg-brand-50">
                                Track another order
                            </a>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </section>
        </div>
    </div>
</div>
