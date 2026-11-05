<?php

declare(strict_types=1);

/**
 * The work list: every line of a paid order that has not been handed over yet,
 * oldest job first, with the delivery form attached to the row so a hand-over is
 * one text box and one click. The row link goes to the full order page when the
 * job needs the money side too.
 *
 * @var App\Core\View $view
 * @var list<array<string, mixed>> $queue
 * @var int $awaiting
 * @var bool $truncated
 */
$brandName = setting('brand.name', 'DexxozzBD');

$queue = array_values(array_map(static fn (mixed $row): array => (array) $row, (array) ($queue ?? [])));
$awaiting = max(0, (int) ($awaiting ?? 0));
$truncated = (bool) ($truncated ?? false);

$view->extend('layouts/admin');
$view->setSection('title', 'Delivery queue - ' . $brandName);
$view->setSection('page_title', 'Delivery queue');
$view->setSection('page_actions', '<a href="' . e(route('admin.orders', [], ['payment_status' => 'paid'])) . '" class="rounded-brand border border-brand-200 bg-white px-4 py-2 text-sm font-bold text-brand-700 transition-colors duration-200 hover:bg-brand-50">Paid orders</a>');
?>

<section aria-labelledby="queue-head" class="rounded-card border border-brand-100 bg-white p-4 shadow-soft sm:p-5">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 id="queue-head" class="text-base font-black tracking-tight text-ink">
                <?= e(number_format($awaiting)) ?> line<?= $awaiting === 1 ? '' : 's' ?> to send
            </h2>
            <p class="mt-0.5 text-xs font-semibold text-ink-400">
                Only paid orders get here. Sending an account on an unpaid job is possible from the order page,
                and the panel warns you when you do.
            </p>
        </div>
        <a href="<?= e(route('admin.orders', [], ['fulfillment' => 'unassigned', 'payment_status' => 'paid'])) ?>"
           class="rounded-brand bg-brand-600 px-4 py-2 text-sm font-black text-white shadow-glow transition-colors duration-200 hover:bg-brand-700">
            See paid, unassigned orders
        </a>
    </div>

    <?php if ($truncated): ?>
        <p class="mt-3 rounded-brand bg-amber-50 px-3.5 py-2.5 text-xs font-bold text-amber-900">
            This list shows the oldest <?= e(number_format(count($queue))) ?> of the <?= e(number_format($awaiting)) ?>
            waiting lines. Work top to bottom, or open the paid orders filter to see the rest.
        </p>
    <?php endif; ?>
</section>

<?php if ($queue === []): ?>
    <div class="mt-5">
        <?= $view->component('empty-state', [
            'title' => 'Queue is empty',
            'text' => 'Every paid line has an account attached and sent. Nothing is waiting on the desk right now.',
            'action_url' => route('admin.orders', [], ['payment_status' => 'pending_verification']),
            'action_label' => 'Payment checks instead',
            'icon' => 'check',
        ]) ?>
    </div>
<?php else: ?>
    <ul class="mt-5 space-y-4">
        <?php foreach ($queue as $row): ?>
            <?php
            $itemId = (int) ($row['id'] ?? 0);
            $orderId = (int) ($row['order_id'] ?? 0);
            $orderNo = (string) ($row['order_no'] ?? '');
            $name = (string) ($row['customer_name'] ?? '');
            $phone = (string) ($row['phone'] ?? '');
            $textarea = 'queue-note-' . $itemId;
            $message = 'Hi ' . $name . ', your ' . $brandName . ' order ' . $orderNo
                . ' (' . plan_label((string) ($row['service_name'] ?? ''), (string) ($row['plan_title'] ?? ''))
                . ') is paid. Sending the account now - keep the message private.';
            ?>
            <li class="rounded-card border border-brand-100 bg-white p-4 shadow-soft sm:p-5">
                <div class="flex flex-wrap items-start gap-3">
                    <div class="min-w-0 flex-1">
                        <p class="flex flex-wrap items-center gap-2">
                            <a href="<?= e(route('admin.order.show', ['id' => $orderId])) ?>" class="text-sm font-black text-brand-700 underline decoration-brand-200 underline-offset-4 hover:text-brand-800">
                                <?= e($orderNo) ?>
                            </a>
                            <span class="rounded-pill bg-brand-50 px-2.5 py-0.5 text-xs font-bold text-ink-400">
                                <?= e(time_ago((string) ($row['ordered_at'] ?? 'now'))) ?>
                            </span>
                            <span class="rounded-pill bg-lime-100 px-2.5 py-0.5 text-xs font-black text-lime-800">paid</span>
                        </p>
                        <p class="mt-1.5 text-sm font-black text-ink">
                            <?= e(plan_label((string) ($row['service_name'] ?? ''), (string) ($row['plan_title'] ?? ''))) ?>
                            <span class="ms-1 text-xs font-bold text-ink-400">&times; <?= e((string) (int) ($row['quantity'] ?? 1)) ?></span>
                        </p>
                        <p class="mt-0.5 truncate text-xs font-semibold text-ink-400">
                            <?= e($name) ?> &middot; <?= e($phone) ?> &middot; <?= e((string) ($row['email'] ?? '')) ?>
                        </p>
                    </div>

                    <div class="flex shrink-0 flex-wrap items-center gap-2">
                        <a href="<?= e(wa_link($message)) ?>" target="_blank" rel="noopener"
                           class="inline-flex items-center gap-1.5 rounded-brand bg-[#25d366] px-3 py-2 text-xs font-black text-white transition-transform duration-200 hover:-translate-y-0.5">
                            <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5-1.4A10 10 0 1 0 12 2Zm5 13.3c-.3.8-1.6 1.5-2.3 1.5-.6 0-1.4-.2-2.2-.6-1.5-.7-2.8-1.9-3.9-3.2-.9-1.1-1.3-2.1-1.3-3 0-1 .7-1.7 1.2-2 .2-.1.4-.1.6 0l1 .5c.2.1.3.3.2.5l-.4.9c-.1.2 0 .4.1.5.5.7 1.2 1.4 2 1.8.2.1.4.1.5-.1l.4-.6c.1-.2.3-.2.5-.2l1.4.7c.2.1.3.3.2.5Z"/></svg>
                            <?= e($phone === '' ? 'WhatsApp' : $phone) ?>
                        </a>
                        <a href="<?= e(route('admin.order.show', ['id' => $orderId])) ?>"
                           class="rounded-brand border border-brand-200 bg-white px-3 py-2 text-xs font-bold text-brand-700 transition-colors duration-200 hover:bg-brand-50">
                            Full order
                        </a>
                    </div>
                </div>

                <form method="post" action="<?= e(route('admin.item.deliver', ['id' => $itemId])) ?>" class="mt-3 space-y-2">
                    <?= csrf_field() ?>
                    <input type="hidden" name="back" value="queue">
                    <label for="<?= e($textarea) ?>" class="block text-xs font-black uppercase tracking-[0.12em] text-ink-400">
                        Account details to store and send
                    </label>
                    <textarea id="<?= e($textarea) ?>" name="note" rows="4" required minlength="4" maxlength="2000"
                              placeholder="Email&#10;Password&#10;Profile / PIN / recovery mail&#10;Anything the customer needs to keep the account"
                              class="w-full rounded-brand border border-brand-200 bg-white px-3 py-2.5 font-mono text-xs leading-relaxed outline-none transition duration-200 focus:border-brand-400 focus:ring-2 focus:ring-brand-300 <?= e(has_error('note') ? 'border-rose-300 ring-2 ring-rose-400' : '') ?>"></textarea>
                    <div class="flex flex-wrap items-center gap-3">
                        <button type="submit" class="rounded-brand bg-brand-600 px-4 py-2 text-sm font-black text-white shadow-glow transition-colors duration-200 hover:bg-brand-700">
                            Deliver this line
                        </button>
                        <p class="text-xs font-semibold text-ink-400">
                            Encrypted before it is stored; you stay on this queue and the text is not repeated back here.
                        </p>
                    </div>
                </form>
            </li>
        <?php endforeach; ?>
    </ul>

    <p class="mt-5 text-xs font-semibold text-ink-400">
        <?= e((string) count($queue)) ?> job<?= count($queue) === 1 ? '' : 's' ?> shown &middot;
        <?= e(number_format($awaiting)) ?> waiting in total &middot;
        a line only leaves this list once it is delivered or the order is cancelled.
    </p>
<?php endif; ?>
