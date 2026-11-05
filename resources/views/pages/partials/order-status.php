<?php

declare(strict_types=1);

/**
 * The live status slot.
 *
 * [data-order-status] is the node app.js overwrites when a payload carries
 * status_html, so anything that must survive that swap - the poll url, the
 * "checked a moment ago" line - lives outside it. The poll below only ever
 * writes textContent and classes from closed maps, using the handful of fields
 * api.order.status is allowed to return: status, payment_status,
 * fulfillment_status, status_label, paid.
 *
 * @var App\Core\View $view
 * @var array<string, mixed> $order
 */
$order = (array) ($order ?? []);
$orderNo = (string) ($order['order_no'] ?? '');
$pollUrl = $orderNo === '' ? '' : route('api.order.status', ['orderNo' => $orderNo]);
?>
<div data-status-widget class="rounded-card border border-brand-100 bg-white p-4 shadow-soft">
    <div data-order-status<?= $pollUrl === '' ? '' : ' data-order-status-url="' . e($pollUrl) . '"' ?> role="status" aria-live="polite">
        <?php include $view->path('pages.partials.order-status-inner'); ?>
    </div>

    <?php if ($pollUrl !== ''): ?>
        <p class="mt-3 flex items-center gap-2 border-t border-dashed border-brand-100 pt-3 text-[11px] font-semibold text-ink-400">
            <svg class="h-3.5 w-3.5 shrink-0 animate-float" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true">
                <path d="M21 12a9 9 0 1 1-3-6.7" stroke-linecap="round"/><path d="M12 8v4l3 2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            <span data-status-stamp>Watching this order - it refreshes on its own.</span>
        </p>
    <?php endif; ?>
</div>

<?php if ($pollUrl !== ''): ?>
    <script nonce="<?= e($cspNonce ?? '') ?>">
        (function () {
            'use strict';

            var doc = document;
            var INTERVAL = 20000;
            var MAX_FAILS = 3;

            // Wording and colours mirror pages/partials/order-status-inner.php, so a
            // polled update reads exactly like a page load.
            var PAY_LABEL = {
                unpaid: 'Unpaid',
                pending_verification: 'Pending Verification',
                paid: 'Paid',
                refunded: 'Refunded'
            };
            var ORDER_LABEL = {
                pending: 'Pending',
                processing: 'Processing',
                completed: 'Completed',
                cancelled: 'Cancelled',
                failed: 'Failed'
            };
            var FULFIL_LABEL = {
                unassigned: 'Unassigned',
                assigned: 'Assigned',
                delivered: 'Delivered'
            };
            var DOT = {
                paid: 'bg-emerald-500',
                pending_verification: 'bg-amber-500 animate-pulse-ring',
                refunded: 'bg-rose-500',
                unpaid: 'bg-slate-400 animate-pulse-ring'
            };

            var noteFor = function (pay, fulfil, status) {
                if (status === 'cancelled') {
                    return 'This order was cancelled. Nothing is owed - start a new one whenever you are ready.';
                }
                if (pay === 'paid') {
                    return fulfil === 'delivered'
                        ? 'Verified and delivered. Check the credentials we sent you.'
                        : 'Payment verified - your plan is with the delivery desk now.';
                }
                if (pay === 'pending_verification') {
                    return 'Your reference is in the queue. A person checks the statement by hand, usually within minutes during open hours.';
                }
                if (pay === 'refunded') {
                    return 'This order was refunded. Message us on WhatsApp if that was not expected.';
                }
                return 'No payment recorded yet. Send the amount and submit your reference so we can match it.';
            };

            var labelFor = function (map, code, serverLabel) {
                if (typeof serverLabel === 'string' && serverLabel !== '') {
                    return serverLabel;
                }
                return Object.prototype.hasOwnProperty.call(map, code) ? map[code] : code;
            };

            var bind = function (slot) {
                if (slot.dataset.statusPolled === '1') {
                    return;
                }

                slot.dataset.statusPolled = '1';

                var url = slot.getAttribute('data-order-status-url');

                if (!url) {
                    return;
                }

                var widget = slot.closest('[data-status-widget]') || slot;
                var stamp = widget.querySelector('[data-status-stamp]');
                var timer = null;
                var fails = 0;
                var running = true;

                var write = function (body) {
                    var changed = false;

                    var put = function (selector, value) {
                        var node = slot.querySelector(selector);

                        if (node === null || node.textContent === value) {
                            return;
                        }

                        node.textContent = value;
                        changed = true;
                    };

                    var pay = typeof body.payment_status === 'string' ? body.payment_status : '';
                    var fulfil = typeof body.fulfillment_status === 'string' ? body.fulfillment_status : '';
                    var status = typeof body.status === 'string' ? body.status : '';

                    put('[data-status-payment]', labelFor(PAY_LABEL, pay, ''));
                    put('[data-status-order]', labelFor(ORDER_LABEL, status, body.status_label));
                    put('[data-status-fulfillment]', labelFor(FULFIL_LABEL, fulfil, ''));
                    put('[data-status-note]', noteFor(pay, fulfil, status));

                    var dot = slot.querySelector('[data-status-dot]');

                    if (dot !== null && Object.prototype.hasOwnProperty.call(DOT, pay)) {
                        var wanted = 'h-3 w-3 shrink-0 rounded-full ' + DOT[pay];

                        if (dot.getAttribute('class') !== wanted) {
                            dot.setAttribute('class', wanted);
                            changed = true;
                        }
                    }

                    var badge = slot.querySelector('[data-status-paid-badge]');

                    if (badge !== null) {
                        badge.hidden = pay !== 'paid';
                    }

                    if (changed) {
                        var pulse = slot.querySelector('[data-status-payment]');

                        if (pulse !== null) {
                            pulse.classList.add('animate-pop');
                            setTimeout(function () {
                                pulse.classList.remove('animate-pop');
                            }, 900);
                        }
                    }

                    if (stamp !== null) {
                        stamp.textContent = 'Checked ' + new Date().toLocaleTimeString();
                    }

                    return pay === 'paid';
                };

                var stop = function (message) {
                    running = false;

                    if (timer !== null) {
                        clearTimeout(timer);
                        timer = null;
                    }

                    if (stamp !== null && message) {
                        stamp.textContent = message;
                    }
                };

                var schedule = function () {
                    if (running) {
                        timer = setTimeout(tick, INTERVAL);
                    }
                };

                function tick() {
                    if (!running) {
                        return;
                    }

                    if (timer !== null) {
                        clearTimeout(timer);
                        timer = null;
                    }

                    if (doc.hidden) {
                        return;
                    }

                    fetch(url, {
                        credentials: 'same-origin',
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    })
                        .then(function (response) {
                            return response.json().catch(function () {
                                return null;
                            });
                        })
                        .then(function (body) {
                            if (body && body.ok === true) {
                                fails = 0;

                                if (write(body) === true) {
                                    stop('Paid and verified here - nothing left to watch.');
                                }

                                return;
                            }

                            fails += 1;

                            if (body && typeof body.message === 'string' && stamp !== null) {
                                stamp.textContent = body.message;
                            }
                        })
                        .catch(function () {
                            fails += 1;
                        })
                        .then(function () {
                            if (fails >= MAX_FAILS) {
                                stop('Live updates paused. Reload this page to check again.');

                                return;
                            }

                            schedule();
                        });
                }

                doc.addEventListener('visibilitychange', function () {
                    if (doc.hidden) {
                        if (timer !== null) {
                            clearTimeout(timer);
                            timer = null;
                        }

                        return;
                    }

                    if (running) {
                        tick();
                    }
                });

                schedule();
            };

            [].forEach.call(doc.querySelectorAll('[data-order-status-url]'), bind);
        }());
    </script>
<?php endif; ?>
