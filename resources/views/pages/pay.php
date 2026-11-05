<?php

declare(strict_types=1);

/**
 * The payment step (step 2 of 3).
 *
 * Everything money-shaped on this page is read off the order row that
 * OrderService wrote, never off the request. Submitting the form declares a
 * reference; the order only ever moves to pending_verification here. A human
 * with the wallet statement in hand is what makes it paid.
 *
 * @var App\Core\View $view
 * @var array<string, mixed> $order
 * @var list<array<string, mixed>> $items
 * @var list<array<string, mixed>> $payments
 * @var array<string, mixed>|null $declared
 * @var list<array<string, mixed>> $rails
 * @var string $selected
 * @var string $instruction
 */
$brandName = setting('brand.name', 'DexxozzBD');
$order = (array) ($order ?? []);
$items = (array) ($items ?? []);
$payments = (array) ($payments ?? []);
$declared = ($declared ?? null) === null ? null : (array) $declared;
$rails = (array) ($rails ?? []);
$selected = (string) ($selected ?? '');
$instruction = trim((string) ($instruction ?? ''));

$orderNo = (string) ($order['order_no'] ?? '');
$total = (float) ($order['total'] ?? 0);
$paymentStatus = (string) ($order['payment_status'] ?? 'unpaid');
$orderStatus = (string) ($order['status'] ?? 'pending');
$fulfillmentStatus = (string) ($order['fulfillment_status'] ?? 'unassigned');

// The figure to copy into the wallet app: the same server-computed total without
// the currency symbol or a thousands separator.
$amountCopy = number_format($total, 2, '.', '');

if (str_ends_with($amountCopy, '.00')) {
    $amountCopy = substr($amountCopy, 0, -3);
}

$closed = in_array($orderStatus, ['cancelled', 'failed'], true);
$alreadyPaid = $paymentStatus === 'paid';

// The reference field is described by whichever rail is selected on load; the
// payment script keeps that description in step with the picked card.
$selectedRail = null;

foreach ($rails as $rail) {
    if ((string) ($rail['key'] ?? '') === $selected) {
        $selectedRail = (array) $rail;
        break;
    }
}

$selectedRail = $selectedRail === null ? [] : $selectedRail;
$txnLabel = trim((string) ($selectedRail['txn_label'] ?? ''));
$txnLabel = $txnLabel === '' ? 'Transaction ID' : $txnLabel;
$txnHint = trim((string) ($selectedRail['txn_hint'] ?? ''));
$needsTxn = (bool) ($selectedRail['requires_txn'] ?? false);
$selectedLabel = trim((string) ($selectedRail['label'] ?? ''));

$view->extend('layouts/app');
$view->setSection('title', 'Pay for order ' . $orderNo . ' - ' . $brandName);
$view->setSection('meta_description', 'Send the exact amount and submit your transaction reference so our team can verify it by hand.');
$view->setSection('canonical', route('order.pay', ['orderNo' => $orderNo]));
?>
<div class="relative bg-gradient-to-b from-brand-50 via-white to-white">
    <span class="blob -left-24 top-32 animate-blob opacity-35" aria-hidden="true"></span>
    <span class="blob -right-20 top-80 animate-blob opacity-30 [animation-delay:-7s]" aria-hidden="true"></span>

    <div class="relative mx-auto max-w-7xl px-4 py-10 lg:px-6 lg:py-14">
        <?= $view->component('section-heading', [
            'eyebrow' => 'Step 2 of 3',
            'title' => 'Send the money, tell us you did',
            'subtitle' => 'Order ' . $orderNo . ' is reserved for you. Pick a method, send the exact amount to the number shown, then paste the reference below.',
        ]) ?>

        <div class="mt-6">
            <?php
            $paymentStatusForSteps = $paymentStatus;
            $fulfillmentStatusForSteps = $fulfillment;
            include $view->path('pages.partials.order-steps');
            ?>
        </div>

        <div class="mt-8 grid gap-8 lg:grid-cols-[1.4fr_1fr] lg:items-start">
            <?php /* ---------------------------- the rails ----------------------------*/ ?>
            <section aria-label="Payment instructions" class="space-y-5">
                <div class="reveal relative overflow-hidden rounded-card bg-gradient-to-br from-brand-700 via-brand-600 to-accent-500 p-5 text-white shadow-card" data-reveal>
                    <span class="animate-shimmer absolute inset-0 bg-gradient-to-r from-white/0 via-white/25 to-white/0" aria-hidden="true"></span>
                    <p class="relative text-[11px] font-black uppercase tracking-[0.22em] text-white/75">Send exactly</p>
                    <p class="relative mt-1.5 flex flex-wrap items-end gap-3">
                        <span class="text-4xl font-black leading-none tracking-tight sm:text-5xl"><?= e(taka($total)) ?></span>
                        <button type="button" data-copy data-copy-value="<?= e($amountCopy) ?>"
                                class="rounded-pill bg-white/15 px-3.5 py-1.5 text-[11px] font-black uppercase tracking-wide text-white ring-1 ring-white/40 transition-colors duration-200 hover:bg-white/25">
                            Copy amount
                        </button>
                    </p>
                    <?php if ($instruction !== ''): ?>
                        <p class="relative mt-3 max-w-prose text-sm leading-relaxed text-white/85"><?= e($instruction) ?></p>
                    <?php endif; ?>
                </div>

                <?php if ($closed): ?>
                    <div class="rounded-card border border-rose-200 bg-rose-50 p-5">
                        <h2 class="text-base font-black text-rose-800">This order is closed</h2>
                        <p class="mt-2 text-sm leading-relaxed text-rose-700">
                            The status is <strong class="font-black"><?= e(status_label($orderStatus)) ?></strong>, so nothing is owed on it and no payment can be attached.
                            Build a new basket whenever you are ready - it takes a minute.
                        </p>
                        <a href="<?= e(route('plans')) ?>" class="mt-4 inline-flex rounded-brand bg-rose-600 px-5 py-2.5 text-sm font-black text-white transition-colors duration-200 hover:bg-rose-700">
                            Browse plans
                        </a>
                    </div>
                <?php else: ?>
                    <?php if ($alreadyPaid): ?>
                        <div class="rounded-card border border-emerald-200 bg-emerald-50 p-5">
                            <h2 class="flex items-center gap-2 text-base font-black text-emerald-800">
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="m8.5 12.5 2.5 2.5 4.5-5.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                Verified as paid
                            </h2>
                            <p class="mt-2 text-sm leading-relaxed text-emerald-800/90">
                                Our team has already confirmed your payment. There is nothing left to submit - the form below is closed for this order.
                            </p>
                        </div>
                    <?php elseif ($declared !== null): ?>
                        <div class="flex flex-wrap items-center gap-x-3 gap-y-1 rounded-card border border-amber-200 bg-amber-50 p-4">
                            <p class="text-sm font-bold text-amber-900">
                                We already have a <?= e((string) ($declared['method_label'] ?? 'payment')) ?>
                                reference<?= e(trim((string) ($declared['txn_id'] ?? '')) === '' ? '' : ': ' . trim((string) $declared['txn_id'])) ?>
                                for this order, waiting for verification.
                            </p>
                            <p class="text-xs text-amber-800">Paying again only slows us down - unless you sent the money a second time, in which case submit the new reference.</p>
                        </div>
                    <?php endif; ?>

                    <form data-ajax data-pay-form method="post" action="<?= e(route('order.pay.store', ['orderNo' => $orderNo])) ?>" class="space-y-6">
                        <?= csrf_field() ?>

                        <fieldset class="space-y-3">
                            <legend class="text-sm font-black text-ink">1. Choose how you are paying</legend>
                            <div class="grid gap-3 sm:grid-cols-2">
                                <?php
                                $showNumber = true;
                                $idPrefix = 'pay-rail';

                                foreach ($rails as $rail):
                                    ?>
                                    <?php include $view->path('pages.partials.rail-option'); ?>
                                <?php endforeach; ?>
                            </div>
                        </fieldset>

                        <fieldset class="space-y-3">
                            <legend class="text-sm font-black text-ink">2. Tell us the reference</legend>

                            <div class="space-y-1.5">
                                <label for="txn-input" class="block text-sm font-semibold text-ink">
                                    <span data-txn-label><?= e($txnLabel) ?></span>
                                    <span data-txn-star class="text-rose-500" <?= e($needsTxn ? '' : 'hidden') ?> aria-hidden="true">*</span>
                                </label>
                                <input type="text"
                                       id="txn-input"
                                       data-txn-input
                                       name="txn_id"
                                       maxlength="60"
                                       autocomplete="off"
                                       spellcheck="false"
                                       placeholder="<?= e($txnLabel) ?>"
                                       class="w-full rounded-brand border border-brand-200 bg-white px-3.5 py-2.5 font-mono text-sm uppercase tracking-[0.08em] text-ink outline-none transition duration-200 placeholder:font-sans placeholder:normal-case placeholder:tracking-normal placeholder:text-ink-400 focus:border-brand-400 focus:ring-2 focus:ring-brand-300"
                                    <?= e($needsTxn ? 'required' : '') ?>>
                                <p data-txn-hint class="text-xs leading-relaxed text-ink-400"><?= e($txnHint === '' ? 'The reference printed on your payment receipt or confirmation SMS.' : $txnHint) ?></p>
                            </div>

                            <?= $view->component('field', [
                                'name' => 'sender_number',
                                'label' => 'Number you paid from (optional)',
                                'type' => 'tel',
                                'placeholder' => '01XXXXXXXXX',
                                'hint' => 'Only needed when the money left a friend or family wallet instead of yours.',
                                'inputmode' => 'numeric',
                                'autocomplete' => 'tel',
                            ]) ?>
                        </fieldset>

                        <div class="flex flex-wrap items-center gap-3">
                            <button type="submit"
                                    class="group relative inline-flex items-center gap-2 overflow-hidden rounded-brand bg-gradient-to-r from-brand-600 via-brand-500 to-accent-500 px-6 py-3.5 text-sm font-black text-white shadow-glow transition-transform duration-200 hover:-translate-y-0.5">
                                <span class="animate-shimmer absolute inset-0 bg-gradient-to-r from-white/0 via-white/40 to-white/0" aria-hidden="true"></span>
                                <span class="relative">I have paid - submit my reference</span>
                                <svg class="relative h-4 w-4 transition-transform duration-200 group-hover:translate-x-1" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            </button>
                            <p class="text-xs leading-relaxed text-ink-400">
                                Submitting tells us where to look. <strong class="font-black text-ink">It does not mark anything paid</strong> - a person checks the statement first.
                            </p>
                        </div>
                    </form>

                    <p class="text-xs leading-relaxed text-ink-400">
                        Prefer to do it by hand?
                        <a href="<?= e(wa_link('Order ' . $orderNo . ': I have sent ' . taka($total) . ($selectedLabel === '' ? '' : ' via ' . $selectedLabel) . '. Please verify and deliver.')) ?>"
                           target="_blank" rel="noopener"
                           class="font-bold text-brand-700 underline decoration-accent-400 decoration-2 underline-offset-4">
                            Send us the reference on WhatsApp
                        </a>
                        - include the order number so we find it fast.
                    </p>
                <?php endif; ?>

                <?php if ($payments !== []): ?>
                    <div class="reveal rounded-card border border-brand-100 bg-white p-5 shadow-card" data-reveal>
                        <h2 class="mb-3 text-sm font-black uppercase tracking-widest text-ink-400">What you have declared</h2>
                        <?php include $view->path('pages.partials.order-payments'); ?>
                    </div>
                <?php endif; ?>
            </section>

            <?php /* ----------------------------- the recap ----------------------------*/ ?>
            <aside class="lg:sticky lg:top-24">
                <div class="reveal space-y-5 rounded-card border border-brand-100 bg-white p-5 shadow-card" data-reveal>
                    <h2 class="text-sm font-black uppercase tracking-widest text-ink-400">Order <?= e($orderNo) ?></h2>

                    <?php if ($items !== []): ?>
                        <?php $showStatus = false; include $view->path('pages.partials.order-items'); ?>
                    <?php endif; ?>

                    <dl class="space-y-1.5 border-t border-dashed border-brand-100 pt-4 text-sm">
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
                            <dt class="font-black text-ink">You send</dt>
                            <dd class="animate-pop text-2xl font-black tracking-tight text-brand-700"><?= e(taka($total)) ?></dd>
                        </div>
                    </dl>

                    <?php include $view->path('pages.partials.order-status'); ?>

                    <a href="<?= e(route('order.show', ['orderNo' => $orderNo])) ?>" class="block rounded-brand border-2 border-brand-200 bg-white px-4 py-2.5 text-center text-sm font-bold text-brand-700 transition-colors duration-200 hover:border-brand-400 hover:bg-brand-50">
                        Open the full order page
                    </a>
                </div>
            </aside>
        </div>
    </div>
</div>

<?php include $view->path('pages.partials.pay-live'); ?>
