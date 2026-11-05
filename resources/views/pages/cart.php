<?php

declare(strict_types=1);

/**
 * Cart page. $lines / $totals come from CartService only - the amounts rendered
 * here are the amounts OrderService will write, never anything from the request.
 *
 * @var App\Core\View $view
 * @var list<array<string, mixed>> $lines
 * @var array{subtotal: float, discount: float, total: float, coupon: string, coupon_message: string} $totals
 */
$brandName = setting('brand.name', 'ZapSub');
$lines = (array) ($lines ?? []);
$totals = (array) ($totals ?? ['subtotal' => 0.0, 'discount' => 0.0, 'total' => 0.0, 'coupon' => '', 'coupon_message' => '']);
$unitCount = 0;

foreach ($lines as $line) {
    $unitCount += max(1, (int) ($line['quantity'] ?? 1));
}

$isEmpty = $lines === [];

$view->extend('layouts/app');
$view->setSection('title', 'Your cart - ' . $brandName);
$view->setSection('meta_description', 'Review the subscriptions in your cart and check out with bKash, Nagad, Rocket or card.');
$view->setSection('canonical', route('cart'));
?>
<div class="relative bg-gradient-to-b from-brand-50 via-white to-white">
    <span class="blob -left-20 top-24 animate-blob opacity-40" aria-hidden="true"></span>
    <span class="blob -right-24 top-64 animate-blob opacity-30 [animation-delay:-6s]" aria-hidden="true"></span>

    <div class="relative mx-auto max-w-7xl px-4 py-10 lg:px-6 lg:py-14">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <?= $view->component('section-heading', [
                'eyebrow' => 'Step 1 of 3',
                'title' => 'Your cart',
                'subtitle' => 'Change quantities, drop anything you changed your mind about, and add a coupon. Nothing is charged here - you pay by hand and a human verifies it.',
            ]) ?>

            <?php if (!$isEmpty): ?>
                <form data-ajax method="post" action="<?= e(route('cart.clear')) ?>" class="shrink-0">
                    <?= csrf_field() ?>
                    <button type="submit" class="rounded-pill border border-rose-200 bg-white px-4 py-2 text-xs font-bold text-rose-700 transition-colors duration-200 hover:bg-rose-50">
                        Clear cart
                    </button>
                </form>
            <?php endif; ?>
        </div>

        <div class="mt-8 grid gap-8 lg:grid-cols-[1.5fr_1fr] lg:items-start">
            <?php /* ------------------------------ lines -----------------------------*/ ?>
            <section aria-label="Cart items">
                <p class="mb-3 text-xs font-bold uppercase tracking-widest text-ink-400">
                    <?= $isEmpty ? 'Nothing in the bag yet' : e((string) count($lines)) . ' plan' . (count($lines) === 1 ? '' : 's') . ' &middot; ' . e((string) $unitCount) . ' item' . ($unitCount === 1 ? '' : 's') ?>
                </p>

                <div data-cart-lines class="space-y-4">
                    <?php include $view->path('pages.partials.cart-lines'); ?>
                </div>

                <?php if (!$isEmpty): ?>
                    <div class="mt-6 flex flex-wrap gap-3">
                        <a href="<?= e(route('plans')) ?>" class="inline-flex items-center gap-2 rounded-brand border-2 border-brand-200 bg-white px-5 py-3 text-sm font-bold text-brand-700 transition-colors duration-200 hover:border-brand-400 hover:bg-brand-50">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path d="M19 12H5M11 6l-6 6 6 6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            Continue shopping
                        </a>
                        <a href="<?= e(route('checkout')) ?>" class="group relative inline-flex items-center gap-2 overflow-hidden rounded-brand bg-gradient-to-r from-brand-600 via-brand-500 to-accent-500 px-6 py-3 text-sm font-black text-white shadow-glow transition-transform duration-200 hover:-translate-y-0.5">
                            <span class="animate-shimmer absolute inset-0 bg-gradient-to-r from-white/0 via-white/40 to-white/0" aria-hidden="true"></span>
                            <span class="relative">Secure checkout</span>
                            <svg class="relative h-4 w-4 transition-transform duration-200 group-hover:translate-x-1" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        </a>
                    </div>
                <?php endif; ?>
            </section>

            <?php /* ----------------------------- summary ----------------------------*/ ?>
            <aside class="lg:sticky lg:top-24">
                <div class="reveal overflow-hidden rounded-card border border-brand-100 bg-white shadow-card" data-reveal>
                    <div class="relative overflow-hidden bg-gradient-to-r from-brand-600 to-brand-500 px-5 py-4">
                        <span class="animate-shimmer absolute inset-0 bg-gradient-to-r from-white/0 via-white/30 to-white/0" aria-hidden="true"></span>
                        <h2 class="relative flex items-center gap-2 text-sm font-black uppercase tracking-widest text-white">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M6 8h12l-1 12H7L6 8Z" stroke-linejoin="round"/><path d="M9 8a3 3 0 0 1 6 0" stroke-linecap="round"/></svg>
                            Order summary
                        </h2>
                    </div>

                    <div class="space-y-5 p-5">
                        <div data-cart-total>
                            <?php include $view->path('pages.partials.cart-total'); ?>
                        </div>

                        <div data-coupon-slot class="rounded-brand bg-brand-50/70 p-3">
                            <?php include $view->path('pages.partials.cart-coupon'); ?>
                        </div>

                        <?php if (!$isEmpty): ?>
                            <a href="<?= e(route('checkout')) ?>" class="block rounded-brand bg-gradient-to-r from-brand-600 to-brand-500 px-4 py-3 text-center text-sm font-black text-white shadow-pop transition-all duration-200 hover:from-brand-700 hover:to-brand-600">
                                Checkout &amp; pay
                            </a>
                        <?php endif; ?>

                        <ul class="space-y-2 text-xs leading-relaxed text-ink-400">
                            <li class="flex gap-2">
                                <span class="mt-0.5 text-accent-500" aria-hidden="true">&#10003;</span>
                                Pay with bKash, Nagad, Rocket, card or bank transfer.
                            </li>
                            <li class="flex gap-2">
                                <span class="mt-0.5 text-accent-500" aria-hidden="true">&#10003;</span>
                                Nothing is marked paid until a person checks their wallet statement.
                            </li>
                            <li class="flex gap-2">
                                <span class="mt-0.5 text-accent-500" aria-hidden="true">&#10003;</span>
                                <?= e(setting('trust.guarantee', '7-day replacement on every personal plan')) ?>.
                            </li>
                        </ul>

                        <p class="text-[11px] leading-relaxed text-ink-400">
                            Need help picking a tier?
                            <a href="<?= e(wa_link('Which plan suits me best?')) ?>" target="_blank" rel="noopener" class="font-bold text-brand-700 underline decoration-accent-400 decoration-2 underline-offset-4">Ask us on WhatsApp</a>
                            - we answer <?= e(setting('whatsapp.hours', 'every day')) ?>.
                        </p>
                    </div>
                </div>
            </aside>
        </div>
    </div>
</div>

<?php /* Kept outside [data-cart-lines]: a fresh render of the list must not lose the steppers. */ ?>
<?php include $view->path('pages.partials.cart-live'); ?>
