<?php

declare(strict_types=1);

/**
 * Checkout. One real form (so it works with JavaScript off) wrapped around three
 * tabs: review the basket, give us the details a human needs, choose how you
 * will pay. The amounts on this page come from CartService::totals() and are
 * written again by OrderService at insert time - nothing posted here decides
 * what the order costs.
 *
 * @var App\Core\View $view
 * @var list<array<string, mixed>> $lines
 * @var array{subtotal: float, discount: float, total: float, coupon: string, coupon_message: string} $totals
 * @var list<array<string, mixed>> $rails
 * @var array{name: string, email: string, phone: string} $customer
 */
$brandName = setting('brand.name', 'ZapSub');
$lines = (array) ($lines ?? []);
$rails = (array) ($rails ?? []);
$customer = (array) ($customer ?? ['name' => '', 'email' => '', 'phone' => '']);
$totals = (array) ($totals ?? ['subtotal' => 0.0, 'discount' => 0.0, 'total' => 0.0, 'coupon' => '', 'coupon_message' => '']);

$selectedMethod = old('method');
if ($selectedMethod === '' && $rails !== []) {
    $selectedMethod = (string) $rails[0]['key'];
}

/** Refill from old() after a failed submit, otherwise from the signed-in account. */
$valueFor = static function (string $field) use ($customer): ?string {
    $old = old($field);

    return $old !== '' ? null : (string) ($customer[$field] ?? '');
};

$steps = [
    ['key' => 'basket', 'label' => 'Your basket', 'hint' => count($lines) . ' plan' . (count($lines) === 1 ? '' : 's')],
    ['key' => 'details', 'label' => 'Your details', 'hint' => 'Where we deliver'],
    ['key' => 'payment', 'label' => 'How you pay', 'hint' => 'bKash, Nagad, Rocket...'],
];

$view->extend('layouts/app');
$view->setSection('title', 'Checkout - ' . $brandName);
$view->setSection('meta_description', 'Place your ' . $brandName . ' order and pay with bKash, Nagad, Rocket, card or bank transfer.');
$view->setSection('canonical', route('checkout'));
?>
<div class="relative bg-gradient-to-b from-brand-50 via-white to-white">
    <span class="blob -right-24 top-16 animate-blob opacity-30" aria-hidden="true"></span>
    <span class="blob -left-28 bottom-40 animate-blob opacity-25 [animation-delay:-7s]" aria-hidden="true"></span>

    <div class="relative mx-auto max-w-7xl px-4 py-10 lg:px-6 lg:py-14">
        <?= $view->component('section-heading', [
            'eyebrow' => 'Checkout',
            'title' => 'Three steps and it is on its way',
            'subtitle' => 'We hold this cart for you while you send the payment. A real person checks it and replies on WhatsApp with your login.',
        ]) ?>

        <div class="mt-8 grid gap-8 lg:grid-cols-[1.35fr_1fr] lg:items-start">
            {{-- ------------------------------- tabs ------------------------------- --}}
            <div data-tabs>
                <div role="tablist" aria-label="Checkout steps" class="no-scrollbar flex gap-2 overflow-x-auto pb-1">
                    <?php foreach ($steps as $index => $step): ?>
                        <button type="button"
                                role="tab"
                                data-tab-btn="<?= e($step['key']) ?>"
                                aria-selected="<?= e($index === 0 ? 'true' : 'false') ?>"
                                class="flex shrink-0 items-center gap-2.5 rounded-pill border border-brand-200 bg-white px-4 py-2.5 text-left transition-colors duration-200 aria-selected:border-brand-500 aria-selected:bg-gradient-to-r aria-selected:from-brand-600 aria-selected:to-brand-500 aria-selected:text-white aria-selected:shadow-pop <?= e($index === 0 ? 'is-active' : '') ?>">
                            <span class="grid h-6 w-6 shrink-0 place-items-center rounded-full bg-brand-100 text-[11px] font-black text-brand-700 aria-selected:bg-white/25 aria-selected:text-white">
                                <?= e((string) ($index + 1)) ?>
                            </span>
                            <span class="leading-tight">
                                <span class="block text-sm font-black"><?= e($step['label']) ?></span>
                                <span class="block text-[11px] font-semibold opacity-80"><?= e($step['hint']) ?></span>
                            </span>
                        </button>
                    <?php endforeach; ?>
                </div>

                <form method="post" action="<?= e(route('checkout.store')) ?>" data-ajax class="mt-5 space-y-5">
                    <?= csrf_field() ?>

                    {{-- ------------------------ step 1: basket ------------------------ --}}
                    <section data-tab-panel="basket"
                             role="tabpanel"
                             aria-label="Your basket"
                             class="reveal overflow-hidden rounded-card border border-brand-100 bg-white shadow-card"
                             data-reveal>
                        <ul class="divide-y divide-brand-50">
                            <?php foreach ($lines as $line): ?>
                                <?php
                                $lineColor = (string) ($line['brand_color'] ?? '');
                                $lineBrand = preg_match('/^#[0-9a-fA-F]{3,8}$/', $lineColor) === 1 ? $lineColor : '#7c50f2';
                                ?>
                                <li class="flex items-start gap-3 p-4">
                                    <span class="grid h-11 w-11 shrink-0 place-items-center rounded-brand text-sm font-black text-white"
                                          style="<?= e('background-image:linear-gradient(135deg,' . $lineBrand . ' 0%,' . $lineBrand . ' 40%,#1c0b47 140%)') ?>">
                                        <?= e(mb_strtoupper(mb_substr(trim((string) ($line['logo_mark'] ?? '')) !== '' ? (string) $line['logo_mark'] : (string) ($line['service_name'] ?? 'Z'), 0, 1))) ?>
                                    </span>
                                    <span class="min-w-0 grow">
                                        <span class="block text-xs font-bold uppercase tracking-widest text-brand-600"><?= e((string) $line['service_name']) ?></span>
                                        <span class="mt-0.5 block truncate text-sm font-bold text-ink"><?= e((string) $line['title']) ?></span>
                                        <span class="mt-0.5 block text-xs text-ink-400">
                                            <?= e((int) $line['duration_months'] === 1 ? '1 month' : (int) $line['duration_months'] . ' months') ?>
                                            &middot;
                                            <?= e((string) $line['quantity']) ?> x <?= e(taka((float) $line['unit_price'], false)) ?>
                                        </span>
                                    </span>
                                    <span class="shrink-0 text-sm font-black text-ink"><?= e(taka((float) $line['line_total'])) ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>

                        <div class="flex flex-wrap items-center justify-between gap-3 border-t border-brand-50 bg-brand-50/50 p-4">
                            <a href="<?= e(route('cart')) ?>" class="rounded-pill border border-brand-200 bg-white px-4 py-2 text-xs font-bold text-brand-700 transition-colors duration-200 hover:bg-brand-100">
                                Change quantities in the cart
                            </a>
                            <p class="text-xs text-ink-400">Prices are read from our server, never from this page.</p>
                        </div>
                    </section>

                    {{-- ----------------------- step 2: details ----------------------- --}}
                    <section data-tab-panel="details"
                             role="tabpanel"
                             aria-label="Your details"
                             class="reveal rounded-card border border-brand-100 bg-white p-5 shadow-card sm:p-6"
                             data-reveal>
                        <div class="grid gap-5 sm:grid-cols-2">
                            <?= $view->component('field', [
                                'name' => 'name',
                                'label' => 'Your name',
                                'required' => true,
                                'value' => $valueFor('name'),
                                'placeholder' => 'Nusrat Jahan',
                                'autocomplete' => 'name',
                                'hint' => 'We call this name when we hand the account over.',
                            ]) ?>

                            <?= $view->component('field', [
                                'name' => 'email',
                                'label' => 'Email',
                                'type' => 'email',
                                'required' => true,
                                'value' => $valueFor('email'),
                                'placeholder' => 'you@example.com',
                                'hint' => 'Your receipt and the only thing you need to track this order.',
                            ]) ?>

                            <?= $view->component('field', [
                                'name' => 'phone',
                                'label' => 'WhatsApp number',
                                'type' => 'tel',
                                'required' => true,
                                'value' => $valueFor('phone'),
                                'placeholder' => '01712-345678',
                                'inputmode' => 'tel',
                                'hint' => 'Delivery happens in this chat, so keep it reachable.',
                            ]) ?>

                            <?= $view->component('field', [
                                'name' => 'requirement',
                                'label' => 'What should we deliver to?',
                                'value' => $valueFor('requirement'),
                                'placeholder' => 'theemail@gmail.com / 2 seats / Smart TV profile',
                                'hint' => 'Optional. Stored encrypted and read only by the person preparing your order.',
                            ]) ?>
                        </div>

                        <div class="mt-5">
                            <?= $view->component('field', [
                                'name' => 'note',
                                'label' => 'Anything else we should know?',
                                'type' => 'textarea',
                                'rows' => 3,
                                'value' => $valueFor('note'),
                                'placeholder' => 'Renewal date, brand new account preferred, urgent - whatever helps.',
                                'hint' => 'Optional, 500 characters.',
                            ]) ?>
                        </div>

                        <p class="mt-4 rounded-brand bg-accent-50 p-3 text-xs leading-relaxed text-accent-900">
                            We only use these details to deliver this order and to answer you on WhatsApp.
                            <a href="<?= e(route('privacy')) ?>" class="font-bold underline decoration-accent-400 decoration-2 underline-offset-4">How we handle your data</a>.
                        </p>
                    </section>

                    {{-- ----------------------- step 3: payment ----------------------- --}}
                    <section data-tab-panel="payment"
                             role="tabpanel"
                             aria-label="How you pay"
                             class="reveal space-y-4"
                             data-reveal>
                        <?php if ($rails === []): ?>
                            <div class="rounded-card border border-amber-200 bg-amber-50 p-5 text-sm font-semibold text-amber-900">
                                No payment rail is open right now. Message us on WhatsApp and we will settle the details with you directly.
                            </div>
                        <?php else: ?>
                            <p class="text-sm font-bold text-ink">Choose how you want to pay</p>

                            <div class="grid gap-4 sm:grid-cols-2">
                                <?php
                                $selected = (string) $selectedMethod;
                                $showNumber = false;

                                foreach ($rails as $rail):
                                    ?>
                                    <?php include $view->path('pages.partials.rail-option'); ?>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <div class="rounded-card border-2 border-dashed border-brand-200 bg-brand-50/70 p-4">
                            <p class="flex items-start gap-2 text-sm font-bold text-brand-800">
                                <svg class="mt-0.5 h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                    <path d="M12 3.5 20 6v6c0 4.2-3.3 7.4-8 8.5-4.7-1.1-8-4.3-8-8.5V6l8-2.5Z" stroke-linejoin="round"/>
                                    <path d="m9 12 2 2 4-4.5" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                                Nothing is marked paid until a human verifies your transaction.
                            </p>
                            <p class="mt-2 text-xs leading-relaxed text-brand-800">
                                Placing this order reserves the cart. The next page gives you the number to send money to
                                and asks for the reference - only then does our order desk check its own statement and deliver.
                            </p>
                        </div>

                        <div class="flex flex-wrap items-center gap-3">
                            <button type="submit" class="group relative inline-flex items-center gap-2 overflow-hidden rounded-brand bg-gradient-to-r from-brand-600 via-brand-500 to-accent-500 px-7 py-4 text-base font-black text-white shadow-glow transition-transform duration-200 hover:-translate-y-0.5 active:scale-[0.99]">
                                <span class="animate-shimmer absolute inset-0 bg-gradient-to-r from-white/0 via-white/40 to-white/0" aria-hidden="true"></span>
                                <span class="relative">Place order &mdash; <?= e(taka((float) ($totals['total'] ?? 0))) ?></span>
                                <svg class="relative h-5 w-5 transition-transform duration-200 group-hover:translate-x-1" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            </button>
                            <p class="text-xs text-ink-400">No card details are typed on this site.</p>
                        </div>
                    </section>
                </form>
            </div>

            {{-- ------------------------------ summary ----------------------------- --}}
            <aside class="lg:sticky lg:top-24">
                <div class="reveal overflow-hidden rounded-card border border-brand-100 bg-white shadow-card" data-reveal>
                    <div class="relative overflow-hidden bg-gradient-to-r from-brand-600 to-brand-500 px-5 py-4">
                        <span class="animate-shimmer absolute inset-0 bg-gradient-to-r from-white/0 via-white/30 to-white/0" aria-hidden="true"></span>
                        <h2 class="relative flex items-center gap-2 text-sm font-black uppercase tracking-widest text-white">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M6 8h12l-1 12H7L6 8Z" stroke-linejoin="round"/><path d="M9 8a3 3 0 0 1 6 0" stroke-linecap="round"/></svg>
                            Amount due
                        </h2>
                    </div>

                    <div class="space-y-5 p-5">
                        <div data-cart-total>
                            <?php include $view->path('pages.partials.cart-total'); ?>
                        </div>

                        <div data-coupon-slot class="rounded-brand bg-brand-50/70 p-3">
                            <?php include $view->path('pages.partials.cart-coupon'); ?>
                        </div>

                        <ol class="space-y-3 border-t border-dashed border-brand-200 pt-4 text-xs leading-relaxed text-ink-400">
                            <li class="flex gap-2.5">
                                <span class="grid h-6 w-6 shrink-0 place-items-center rounded-full bg-brand-600 text-[11px] font-black text-white">1</span>
                                <span>Order reserved here on the spot.</span>
                            </li>
                            <li class="flex gap-2.5">
                                <span class="grid h-6 w-6 shrink-0 place-items-center rounded-full bg-brand-600 text-[11px] font-black text-white">2</span>
                                <span>Send the money with any personal number and paste the reference.</span>
                            </li>
                            <li class="flex gap-2.5">
                                <span class="grid h-6 w-6 shrink-0 place-items-center rounded-full bg-brand-600 text-[11px] font-black text-white">3</span>
                                <span>A human verifies it and delivers on WhatsApp, usually inside
                                    <?= e(setting('trust.delivery_minutes', '10')) ?> minutes during open hours.</span>
                            </li>
                        </ol>

                        <p class="text-[11px] leading-relaxed text-ink-400">
                            Trouble choosing?
                            <a href="<?= e(wa_link('I am at checkout and need help with the ' . $brandName . ' order.')) ?>"
                               target="_blank" rel="noopener"
                               class="font-bold text-brand-700 underline decoration-accent-400 decoration-2 underline-offset-4">Talk to us on WhatsApp</a>.
                        </p>
                    </div>
                </div>
            </aside>
        </div>
    </div>
</div>
