<?php

declare(strict_types=1);

/**
 * The workbench for one job. Three statuses, the money trail, the lines with
 * their sealed delivery notes and the history - plus the POST forms that move
 * any of them. Every form here is a real post with a CSRF token, so the page
 * works with no JavaScript at all.
 *
 * A delivery note is never refilled after a redirect and never appears in a
 * link: it is read from the row that already holds it, for staff only.
 *
 * @var App\Core\View $view
 * @var array<string, mixed> $order
 * @var list<array<string, mixed>> $items
 * @var list<array<string, mixed>> $payments
 * @var list<array<string, mixed>> $history
 * @var list<string> $statuses
 * @var list<string> $fulfillmentStates
 * @var bool $keyPresent
 * @var string $verifiedByName
 */
$brandName = setting('brand.name', 'ZapSub');

$order = (array) ($order ?? []);
$items = array_values(array_map(static fn (mixed $row): array => (array) $row, (array) ($items ?? [])));
$payments = array_values(array_map(static fn (mixed $row): array => (array) $row, (array) ($payments ?? [])));
$history = array_values(array_map(static fn (mixed $row): array => (array) $row, (array) ($history ?? [])));
$statuses = array_values((array) ($statuses ?? []));
$keyPresent = (bool) ($keyPresent ?? false);
$verifiedByName = trim((string) ($verifiedByName ?? ''));

$orderId = (int) ($order['id'] ?? 0);
$orderNo = (string) ($order['order_no'] ?? '');
$orderStatus = (string) ($order['status'] ?? '');
$paymentStatus = (string) ($order['payment_status'] ?? '');
$fulfillment = (string) ($order['fulfillment_status'] ?? 'unassigned');
$total = (float) ($order['total'] ?? 0);
$phone = (string) ($order['phone'] ?? '');
$email = (string) ($order['email'] ?? '');
$customerName = (string) ($order['customer_name'] ?? '');
$closed = in_array($orderStatus, ['completed', 'cancelled'], true);

/** The newest declared payment is the one a staff member is looking at. */
$pendingPayment = null;
foreach ($payments as $payment) {
    if ((string) ($payment['status'] ?? '') === 'pending') {
        $pendingPayment = $payment;
        break;
    }
}

$deliverMessage = 'Hi ' . $customerName . ', this is ' . $brandName
    . ' about order ' . $orderNo . ' (' . taka($total) . '). ';
$deliverMessage .= match ($paymentStatus) {
    'paid' => $fulfillment === 'delivered'
        ? 'Everything is delivered - tell us if anything does not open.'
        : 'Payment is confirmed and we are sending the account now.',
    'pending_verification' => 'We can see your payment claim and are checking it against the statement now.',
    'refunded' => 'Your payment has been refunded; write to us and we will sort a new order.',
    default => 'We have not received a payment claim yet - the payment page has the numbers to send money to.',
};

$requestPlain = decrypt_note($order['delivery_request'] ?? null);

$view->extend('layouts/admin');
$view->setSection('title', 'Order ' . $orderNo . ' - ' . $brandName);
$view->setSection('page_title', 'Order ' . $orderNo);
$view->setSection('page_actions', '<a href="' . e(wa_link($deliverMessage)) . '" target="_blank" rel="noopener" class="inline-flex items-center gap-2 rounded-brand bg-[#25d366] px-4 py-2 text-sm font-black text-white shadow-glow transition-transform duration-200 hover:-translate-y-0.5">'
    . '<svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5-1.4A10 10 0 1 0 12 2Zm5 13.3c-.3.8-1.6 1.5-2.3 1.5-.6 0-1.4-.2-2.2-.6-1.5-.7-2.8-1.9-3.9-3.2-.9-1.1-1.3-2.1-1.3-3 0-1 .7-1.7 1.2-2 .2-.1.4-.1.6 0l1 .5c.2.1.3.3.2.5l-.4.9c-.1.2 0 .4.1.5.5.7 1.2 1.4 2 1.8.2.1.4.1.5-.1l.4-.6c.1-.2.3-.2.5-.2l1.4.7c.2.1.3.3.2.5Z"/></svg>'
    . 'WhatsApp the customer</a> '
    . '<a href="' . e(route('admin.orders')) . '" class="rounded-brand border border-brand-200 bg-white px-4 py-2 text-sm font-bold text-brand-700 transition-colors duration-200 hover:bg-brand-50">Back to orders</a>');
?>

<section aria-labelledby="job-state" class="rounded-card border border-brand-100 bg-white p-4 shadow-soft sm:p-5">
    <h2 id="job-state" class="sr-only">Where this order stands</h2>
    <div class="grid gap-3 sm:grid-cols-3">
        <?php foreach ([
            ['label' => 'Order status', 'status' => $orderStatus, 'foot' => 'Placed ' . time_ago((string) ($order['created_at'] ?? 'now'))],
            ['label' => 'Payment', 'status' => $paymentStatus, 'foot' => $paymentStatus === 'paid'
                ? ($verifiedByName !== '' ? 'Verified by ' . $verifiedByName : 'Verified by staff')
                : ($pendingPayment === null ? 'No payment waiting on you' : 'A claim is waiting on you')],
            ['label' => 'Delivery', 'status' => $fulfillment, 'foot' => count($items) . ' line' . (count($items) === 1 ? '' : 's') . ' on this order'],
        ] as $state): ?>
            <div class="rounded-brand border border-brand-100 bg-brand-50/60 p-3">
                <p class="text-xs font-black uppercase tracking-[0.12em] text-ink-400"><?= e($state['label']) ?></p>
                <p class="mt-2"><?= $view->component('status-pill', ['status' => (string) $state['status']]) ?></p>
                <p class="mt-2 text-xs font-semibold text-ink-400"><?= e((string) $state['foot']) ?></p>
            </div>
        <?php endforeach; ?>
    </div>

    <dl class="mt-4 grid gap-x-6 gap-y-2 border-t border-dashed border-brand-100 pt-4 text-sm sm:grid-cols-2 xl:grid-cols-4">
        <div class="flex justify-between gap-3">
            <dt class="text-ink-400">Subtotal</dt>
            <dd class="font-bold"><?= e(taka((float) ($order['subtotal'] ?? 0))) ?></dd>
        </div>
        <div class="flex justify-between gap-3">
            <dt class="text-ink-400">Discount</dt>
            <dd class="font-bold <?= e((float) ($order['discount'] ?? 0) > 0 ? 'text-lime-700' : '') ?>">
                -<?= e(taka((float) ($order['discount'] ?? 0))) ?>
                <?php if ((string) ($order['coupon_code'] ?? '') !== ''): ?>
                    <span class="ms-1 text-xs font-black">&middot; <?= e((string) $order['coupon_code']) ?></span>
                <?php endif; ?>
            </dd>
        </div>
        <div class="flex justify-between gap-3">
            <dt class="text-ink-400">Total due</dt>
            <dd class="font-black"><?= e(taka($total)) ?></dd>
        </div>
        <div class="flex justify-between gap-3">
            <dt class="text-ink-400">Paid at</dt>
            <dd class="font-bold"><?= e((string) ($order['paid_at'] ?? '') === '' ? 'not paid' : date('d M Y, H:i', strtotime((string) $order['paid_at']))) ?></dd>
        </div>
    </dl>
</section>

<div class="mt-5 grid gap-5 xl:grid-cols-3">
    <div class="space-y-5 xl:col-span-2">
        <section aria-labelledby="job-money" class="rounded-card border border-brand-100 bg-white p-4 shadow-soft sm:p-5">
            <h2 id="job-money" class="text-base font-black tracking-tight text-ink">Money</h2>
            <p class="mt-0.5 text-xs font-semibold text-ink-400">
                Only a human marks this paid. Pressing Verify does nothing unless a payment has actually been
                declared, and the site can never press it for you.
            </p>

            <?php if ($payments === []): ?>
                <p class="mt-3 rounded-brand bg-brand-50 px-4 py-4 text-sm text-ink-400">
                    Nothing declared yet. The customer has the wallet numbers from the payment page; when they
                    submit a TrxID it appears here for you to check against the statement.
                </p>
            <?php else: ?>
                <ul class="mt-3 space-y-3">
                    <?php foreach ($payments as $payment): ?>
                        <?php
                        $rowStatus = (string) ($payment['status'] ?? '');
                        $txn = (string) ($payment['txn_id'] ?? '');
                        $declared = (float) ($payment['amount'] ?? 0);
                        $short = abs($declared - $total) > 0.5;
                        ?>
                        <li class="rounded-brand border <?= e($rowStatus === 'pending' ? 'border-amber-300 bg-amber-50/70' : ($rowStatus === 'verified' ? 'border-emerald-200 bg-emerald-50/60' : 'border-rose-200 bg-rose-50/60')) ?> p-3.5">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="rounded-pill bg-white px-2.5 py-1 text-xs font-black text-ink">
                                    <?= e((string) ($payment['method_label'] ?? $payment['method'] ?? '')) ?>
                                </span>
                                <span class="text-sm font-black <?= e($short ? 'text-rose-700' : 'text-ink') ?>"><?= e(taka($declared)) ?></span>
                                <?php if ($short): ?>
                                    <span class="rounded-pill bg-rose-600 px-2 py-0.5 text-[0.65rem] font-black text-white">Amount does not match the <?= e(taka($total)) ?> due</span>
                                <?php endif; ?>
                                <span class="ms-auto"><?= $view->component('status-pill', ['status' => $rowStatus]) ?></span>
                            </div>

                            <dl class="mt-2 grid gap-x-5 gap-y-1 text-xs sm:grid-cols-2">
                                <div class="flex gap-2">
                                    <dt class="font-bold text-ink-400">TrxID</dt>
                                    <dd class="min-w-0 flex-1 break-all font-mono font-bold text-ink"><?= e($txn === '' ? 'not given' : $txn) ?></dd>
                                    <?php if ($txn !== ''): ?>
                                        <dd>
                                            <button type="button" data-copy data-copy-value="<?= e($txn) ?>"
                                                    class="rounded bg-white px-1.5 py-0.5 text-[0.65rem] font-black text-brand-700 ring-1 ring-brand-200 transition-colors duration-200 hover:bg-brand-50">
                                                Copy
                                            </button>
                                        </dd>
                                    <?php endif; ?>
                                </div>
                                <div class="flex gap-2">
                                    <dt class="font-bold text-ink-400">Sender</dt>
                                    <dd class="font-mono font-bold text-ink"><?= e((string) ($payment['sender_number'] ?? '') === '' ? 'not given' : (string) $payment['sender_number']) ?></dd>
                                </div>
                                <div class="flex gap-2">
                                    <dt class="font-bold text-ink-400">Declared</dt>
                                    <dd class="font-bold text-ink"><?= e(time_ago((string) ($payment['created_at'] ?? 'now'))) ?></dd>
                                </div>
                                <?php if ((string) ($payment['admin_note'] ?? '') !== ''): ?>
                                    <div class="flex gap-2">
                                        <dt class="font-bold text-ink-400">Staff note</dt>
                                        <dd class="font-semibold text-ink"><?= e((string) $payment['admin_note']) ?></dd>
                                    </div>
                                <?php endif; ?>
                            </dl>

                            <?php if ($rowStatus === 'pending'): ?>
                                <div class="mt-3 grid gap-3 sm:grid-cols-2">
                                    <form method="post" action="<?= e(route('admin.order.verify', ['id' => $orderId])) ?>" class="space-y-2 rounded-brand bg-white p-3">
                                        <?= csrf_field() ?>
                                        <label for="verify-note" class="block text-xs font-black uppercase tracking-[0.12em] text-ink-400">Statement reference (optional)</label>
                                        <textarea id="verify-note" name="note" rows="2" maxlength="300"
                                                  placeholder="Matched against the bKash statement, 8412 tail&hellip;"
                                                  class="w-full rounded-brand border border-brand-200 px-3 py-2 text-sm outline-none transition duration-200 focus:border-brand-400 focus:ring-2 focus:ring-brand-300"></textarea>
                                        <button type="submit" class="w-full rounded-brand bg-emerald-600 px-4 py-2 text-sm font-black text-white transition-colors duration-200 hover:bg-emerald-700">
                                            I checked the statement &mdash; mark paid
                                        </button>
                                    </form>

                                    <form method="post" action="<?= e(route('admin.order.reject', ['id' => $orderId])) ?>" class="space-y-2 rounded-brand bg-white p-3">
                                        <?= csrf_field() ?>
                                        <label for="reject-note" class="block text-xs font-black uppercase tracking-[0.12em] text-ink-400">Why it is rejected (required)</label>
                                        <textarea id="reject-note" name="note" rows="2" maxlength="300" required
                                                  placeholder="Wrong amount, TrxID not found, sender number is not theirs&hellip;"
                                                  class="w-full rounded-brand border border-brand-200 px-3 py-2 text-sm outline-none transition duration-200 focus:border-rose-400 focus:ring-2 focus:ring-rose-300"></textarea>
                                        <button type="submit" class="w-full rounded-brand bg-rose-600 px-4 py-2 text-sm font-black text-white transition-colors duration-200 hover:bg-rose-700">
                                            Reject this payment
                                        </button>
                                    </form>
                                </div>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>

        <section aria-labelledby="job-lines" class="rounded-card border border-brand-100 bg-white p-4 shadow-soft sm:p-5">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h2 id="job-lines" class="text-base font-black tracking-tight text-ink">Lines and delivery</h2>
                <a href="<?= e(route('admin.delivery')) ?>" class="text-xs font-bold text-brand-700 underline underline-offset-4 hover:text-brand-800">Full delivery queue</a>
            </div>

            <?php if ($items === []): ?>
                <p class="mt-3 rounded-brand bg-brand-50 px-4 py-4 text-sm text-ink-400">
                    This order has no lines recorded, so there is nothing to hand over.
                </p>
            <?php else: ?>
                <ul class="mt-3 space-y-3">
                    <?php foreach ($items as $item): ?>
                        <?php
                        $itemId = (int) ($item['id'] ?? 0);
                        $itemStatus = (string) ($item['status'] ?? '');
                        $notePlain = (string) ($item['delivery_note_plain'] ?? '');
                        $isDelivered = $itemStatus === 'delivered';
                        $textarea = 'deliver-note-' . $itemId;
                        ?>
                        <li class="rounded-brand border border-brand-100 bg-brand-50/40 p-3.5">
                            <div class="flex flex-wrap items-start gap-2">
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-black text-ink">
                                        <?= e(plan_label((string) ($item['service_name'] ?? ''), (string) ($item['plan_title'] ?? ''))) ?>
                                    </p>
                                    <p class="mt-0.5 text-xs font-semibold text-ink-400">
                                        <?= e((string) (int) ($item['quantity'] ?? 1)) ?> &times; <?= e(taka((float) ($item['unit_price'] ?? 0))) ?>
                                        &middot; line total <?= e(taka((float) ($item['line_total'] ?? 0))) ?>
                                        <?php if ($itemStatus === 'delivered' && ($item['delivered_at'] ?? null) !== null): ?>
                                            &middot; sent <?= e(time_ago((string) $item['delivered_at'])) ?>
                                        <?php endif; ?>
                                    </p>
                                </div>
                                <span class="<?= e($isDelivered ? 'rounded-pill bg-emerald-100 px-2.5 py-1 text-xs font-black text-emerald-800' : 'rounded-pill bg-amber-100 px-2.5 py-1 text-xs font-black text-amber-800') ?>">
                                    <?= e(status_label($itemStatus)) ?>
                                </span>
                            </div>

                            <?php if ($notePlain !== ''): ?>
                                <div class="mt-3 rounded-brand border border-brand-100 bg-white p-3">
                                    <p class="flex flex-wrap items-center gap-2 text-[0.65rem] font-black uppercase tracking-[0.14em] text-ink-400">
                                        Account details on file
                                        <span class="rounded-pill <?= e($keyPresent ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800') ?>">
                                            <?= $keyPresent ? 'encrypted at rest' : 'stored unencrypted, APP_KEY is empty' ?>
                                        </span>
                                        <button type="button" data-copy data-copy-value="<?= e($notePlain) ?>"
                                                class="ms-auto rounded bg-brand-600 px-2 py-0.5 text-[0.65rem] font-black text-white transition-colors duration-200 hover:bg-brand-700">
                                            Copy to send
                                        </button>
                                    </p>
                                    <p class="mt-2 whitespace-pre-wrap break-words font-mono text-xs leading-relaxed text-ink"><?= e($notePlain) ?></p>
                                    <?php if (!$keyPresent): ?>
                                        <p class="mt-2 text-xs font-bold text-amber-700">
                                            APP_KEY is empty on this install, so anything sealed earlier cannot be
                                            opened here. Set the key and reload - the bytes in the database are untouched.
                                        </p>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>

                            <?php if ($isDelivered): ?>
                                <form method="post" action="<?= e(route('admin.item.undo', ['id' => $itemId])) ?>" class="mt-3 flex flex-wrap items-center gap-3">
                                    <?= csrf_field() ?>
                                    <p class="flex-1 text-xs font-semibold text-ink-400">
                                        Wrong account, dead login, customer says it does not open? Put the line back in the queue -
                                        the stored note is deleted, so nothing stale can be copied later.
                                    </p>
                                    <button type="submit" class="rounded-brand border border-rose-300 bg-white px-3.5 py-2 text-xs font-black text-rose-700 transition-colors duration-200 hover:bg-rose-50">
                                        Undo this delivery
                                    </button>
                                </form>
                            <?php else: ?>
                                <form method="post" action="<?= e(route('admin.item.deliver', ['id' => $itemId])) ?>" class="mt-3 space-y-2">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="back" value="order">
                                    <label for="<?= e($textarea) ?>" class="block text-xs font-black uppercase tracking-[0.12em] text-ink-400">
                                        Account details to store for <?= e((string) ($item['plan_title'] ?? 'this line')) ?>
                                    </label>
                                    <textarea id="<?= e($textarea) ?>" name="note" rows="4" required minlength="4" maxlength="2000"
                                              placeholder="Email&#10;Password&#10;Profile / PIN / recovery mail&#10;Anything the customer needs to keep the account"
                                              class="w-full rounded-brand border border-brand-200 bg-white px-3 py-2.5 font-mono text-xs leading-relaxed outline-none transition duration-200 focus:border-brand-400 focus:ring-2 focus:ring-brand-300 <?= e(has_error('note') ? 'border-rose-300 ring-2 ring-rose-400' : '') ?>"></textarea>
                                    <p class="text-xs font-semibold text-ink-400">
                                        Saved encrypted with APP_KEY and shown on staff screens only. It is not repeated in
                                        this page after you submit, and it never goes into a link.
                                    </p>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <button type="submit" class="rounded-brand bg-brand-600 px-4 py-2 text-sm font-black text-white shadow-glow transition-colors duration-200 hover:bg-brand-700">
                                            Mark this line delivered
                                        </button>
                                        <?php if ($paymentStatus !== 'paid'): ?>
                                            <span class="rounded-pill bg-rose-100 px-2.5 py-1 text-xs font-black text-rose-800">
                                                Payment is <?= e(status_label($paymentStatus)) ?> - delivering now leaves the order unpaid
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </form>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>

        <section aria-labelledby="job-history" class="rounded-card border border-brand-100 bg-white p-4 shadow-soft sm:p-5">
            <h2 id="job-history" class="text-base font-black tracking-tight text-ink">History</h2>
            <p class="mt-0.5 text-xs text-ink-400">Every status write this order has had, oldest first.</p>

            <?php if ($history === []): ?>
                <p class="mt-3 rounded-brand bg-brand-50 px-4 py-4 text-sm text-ink-400">No history rows recorded.</p>
            <?php else: ?>
                <ol class="mt-4 space-y-4 border-s-2 border-dashed border-brand-200 ps-4">
                    <?php foreach (array_slice($history, 0, 40) as $entry): ?>
                        <li class="relative">
                            <span class="absolute -start-[1.35rem] top-1 grid h-3.5 w-3.5 place-items-center rounded-full bg-brand-500 ring-4 ring-white" aria-hidden="true"></span>
                            <p class="flex flex-wrap items-center gap-2">
                                <span class="text-sm font-black text-ink"><?= e(status_label((string) ($entry['status'] ?? ''))) ?></span>
                                <span class="text-xs font-semibold text-ink-400"><?= e(time_ago((string) ($entry['created_at'] ?? 'now'))) ?></span>
                            </p>
                            <?php if ((string) ($entry['note'] ?? '') !== ''): ?>
                                <p class="mt-1 text-xs leading-relaxed text-ink-400"><?= e((string) $entry['note']) ?></p>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ol>
            <?php endif; ?>
        </section>
    </div>

    <div class="space-y-5">
        <section aria-labelledby="job-customer" class="rounded-card border border-brand-100 bg-white p-4 shadow-soft sm:p-5">
            <h2 id="job-customer" class="text-base font-black tracking-tight text-ink">Customer</h2>
            <p class="mt-3 text-sm font-black text-ink"><?= e($customerName) ?></p>
            <ul class="mt-2 space-y-2 text-sm">
                <li class="flex items-center gap-2">
                    <svg class="h-4 w-4 shrink-0 text-brand-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M5 4h4l2 5-2.5 1.5a12 12 0 0 0 5 5L15 13l5 2v4a1 1 0 0 1-1 1A16 16 0 0 1 4 5a1 1 0 0 1 1-1Z" stroke-linejoin="round"/></svg>
                    <a href="tel:<?= e(preg_replace('/[^0-9+]/', '', $phone) ?? '') ?>" class="font-mono font-bold text-brand-700 hover:text-brand-800"><?= e($phone) ?></a>
                </li>
                <li class="flex items-center gap-2">
                    <svg class="h-4 w-4 shrink-0 text-brand-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m4 7 8 6 8-6"/></svg>
                    <a href="mailto:<?= e($email) ?>" class="min-w-0 truncate font-semibold text-brand-700 hover:text-brand-800"><?= e($email) ?></a>
                </li>
                <li class="flex items-center gap-2 text-xs font-semibold text-ink-400">
                    <svg class="h-4 w-4 shrink-0 text-brand-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7.5V12l3 2" stroke-linecap="round"/></svg>
                    Placed <?= e(time_ago((string) ($order['created_at'] ?? 'now'))) ?>
                    &middot; <?= $orderId > 0 && (int) ($order['user_id'] ?? 0) > 0 ? 'signed-in order' : 'guest order' ?>
                </li>
            </ul>

            <?php if ((string) ($order['note'] ?? '') !== ''): ?>
                <div class="mt-3 rounded-brand bg-brand-50 p-3">
                    <p class="text-[0.65rem] font-black uppercase tracking-[0.14em] text-ink-400">Checkout note</p>
                    <p class="mt-1 whitespace-pre-wrap break-words text-xs leading-relaxed text-ink"><?= e(short_text((string) $order['note'], 500)) ?></p>
                </div>
            <?php endif; ?>

            <div class="mt-3 rounded-brand border <?= e($requestPlain === '' ? 'border-brand-100 bg-brand-50/50' : 'border-accent-300 bg-accent-50') ?> p-3">
                <p class="flex flex-wrap items-center gap-2 text-[0.65rem] font-black uppercase tracking-[0.14em] text-ink-400">
                    What they asked to be delivered to
                    <?php if ($requestPlain !== ''): ?>
                        <span class="rounded-pill <?= e($keyPresent ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800') ?>">
                            <?= $keyPresent ? 'encrypted at rest' : 'sealed without a key to open it' ?>
                        </span>
                    <?php endif; ?>
                </p>
                <?php if ($requestPlain === ''): ?>
                    <p class="mt-1 text-xs text-ink-400">They left the delivery requirement blank - use the email on the order and confirm it on WhatsApp.</p>
                <?php else: ?>
                    <p class="mt-2 whitespace-pre-wrap break-words text-xs leading-relaxed text-ink"><?= e($requestPlain) ?></p>
                <?php endif; ?>
            </div>

            <a href="<?= e(wa_link($deliverMessage)) ?>" target="_blank" rel="noopener" class="mt-3 flex items-center justify-center gap-2 rounded-brand bg-[#25d366] px-4 py-2.5 text-sm font-black text-white transition-transform duration-200 hover:-translate-y-0.5">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5-1.4A10 10 0 1 0 12 2Zm5 13.3c-.3.8-1.6 1.5-2.3 1.5-.6 0-1.4-.2-2.2-.6-1.5-.7-2.8-1.9-3.9-3.2-.9-1.1-1.3-2.1-1.3-3 0-1 .7-1.7 1.2-2 .2-.1.4-.1.6 0l1 .5c.2.1.3.3.2.5l-.4.9c-.1.2 0 .4.1.5.5.7 1.2 1.4 2 1.8.2.1.4.1.5-.1l.4-.6c.1-.2.3-.2.5-.2l1.4.7c.2.1.3.3.2.5Z"/></svg>
                Chat with <?= e($customerName === '' ? 'them' : short_text($customerName, 20)) ?>
            </a>
        </section>

        <section aria-labelledby="job-status" class="rounded-card border border-brand-100 bg-white p-4 shadow-soft sm:p-5">
            <h2 id="job-status" class="text-base font-black tracking-tight text-ink">Move the order</h2>
            <p class="mt-0.5 text-xs text-ink-400">Changes here are written to the history and the audit trail.</p>
            <p class="mt-2 rounded-brand bg-brand-50 px-3 py-2 text-xs font-semibold text-ink-400">
                Delivery is not set by hand - it walks itself
                <?= e(implode(' → ', array_map('status_label', $fulfillmentStates))) ?>
                as lines go out.
            </p>

            <form method="post" action="<?= e(route('admin.order.status', ['id' => $orderId])) ?>" class="mt-3 space-y-3">
                <?= csrf_field() ?>
                <label for="set-status" class="block text-xs font-black uppercase tracking-[0.12em] text-ink-400">Order status</label>
                <select id="set-status" name="status" class="w-full rounded-brand border border-brand-200 px-3 py-2.5 text-sm outline-none transition duration-200 focus:border-brand-400 focus:ring-2 focus:ring-brand-300">
                    <?php foreach ($statuses as $value): ?>
                        <option value="<?= e($value) ?>" <?= e($value === $orderStatus ? 'selected' : '') ?>><?= e(status_label($value)) ?></option>
                    <?php endforeach; ?>
                </select>

                <label for="status-note" class="block text-xs font-black uppercase tracking-[0.12em] text-ink-400">Note for the history (optional)</label>
                <textarea id="status-note" name="note" rows="2" maxlength="300"
                          placeholder="Waiting on the customer to re-declare the payment."
                          class="w-full rounded-brand border border-brand-200 px-3 py-2.5 text-sm outline-none transition duration-200 focus:border-brand-400 focus:ring-2 focus:ring-brand-300"></textarea>

                <button type="submit" class="w-full rounded-brand bg-ink px-4 py-2.5 text-sm font-black text-white transition-colors duration-200 hover:bg-brand-700">
                    Save status
                </button>
            </form>

            <?php if ($paymentStatus === 'paid' && $fulfillment !== 'delivered'): ?>
                <a href="<?= e(route('admin.delivery')) ?>" class="mt-3 block rounded-brand bg-accent-100 px-3 py-2.5 text-xs font-black text-accent-900 transition-colors duration-200 hover:bg-accent-200">
                    Paid and not fully delivered &mdash; go to the queue
                </a>
            <?php endif; ?>
        </section>

        <section aria-labelledby="job-cancel" class="rounded-card border border-rose-200 bg-white p-4 shadow-soft sm:p-5">
            <h2 id="job-cancel" class="text-base font-black tracking-tight text-rose-700">Cancel this order</h2>

            <?php if ($closed): ?>
                <p class="mt-2 rounded-brand bg-rose-50 px-3 py-2.5 text-xs font-semibold text-rose-800">
                    The order is already <?= e(status_label($orderStatus)) ?>, so cancelling it again is refused until
                    the money side is settled by hand.
                </p>
            <?php else: ?>
                <form method="post" action="<?= e(route('admin.order.cancel', ['id' => $orderId])) ?>" class="mt-3 space-y-3">
                    <?= csrf_field() ?>
                    <label for="cancel-reason" class="block text-xs font-black uppercase tracking-[0.12em] text-ink-400">Reason (kept in the history)</label>
                    <textarea id="cancel-reason" name="reason" rows="2" maxlength="200"
                              placeholder="Customer asked to drop it; plan no longer available."
                              class="w-full rounded-brand border border-rose-200 px-3 py-2.5 text-sm outline-none transition duration-200 focus:border-rose-400 focus:ring-2 focus:ring-rose-300"></textarea>
                    <p class="text-xs font-semibold text-ink-400">
                        Cancelling returns plan stock and frees the coupon again. Money already verified stays
                        marked as paid until you refund it, so check the wallet before you press this.
                    </p>
                    <button type="submit" class="w-full rounded-brand bg-rose-600 px-4 py-2.5 text-sm font-black text-white transition-colors duration-200 hover:bg-rose-700">
                        Cancel order <?= e($orderNo) ?>
                    </button>
                </form>
            <?php endif; ?>
        </section>
    </div>
</div>
