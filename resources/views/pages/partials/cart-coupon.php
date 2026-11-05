<?php

declare(strict_types=1);

/**
 * Inside [data-coupon-slot]: either the code that is currently applied, or the
 * box to type one in. CartController sends this same file as coupon_html.
 *
 * @var array{subtotal: float, discount: float, total: float, coupon: string, coupon_message: string} $totals
 */
$totals = (array) ($totals ?? ['subtotal' => 0.0, 'discount' => 0.0, 'total' => 0.0, 'coupon' => '', 'coupon_message' => '']);
$coupon = trim((string) ($totals['coupon'] ?? ''));
$discount = (float) ($totals['discount'] ?? 0);
$message = trim((string) ($totals['coupon_message'] ?? ''));
?>
<?php if ($coupon !== ''): ?>
    <div class="animate-pop rounded-brand border border-lime-200 bg-lime-50 p-3">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <p class="flex items-center gap-2 text-sm font-bold text-lime-800">
                <span class="grid h-6 w-6 place-items-center rounded-full bg-lime-600 text-white" aria-hidden="true">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6"><path d="m7 12.5 3 3 7-7.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </span>
                <span class="rounded-pill bg-white px-2 py-0.5 font-black tracking-wide"><?= e($coupon) ?></span>
            </p>

            <form data-ajax method="post" action="<?= e(route('cart.coupon.remove')) ?>">
                <?= csrf_field() ?>
                <button type="submit" class="rounded-pill px-3 py-1 text-xs font-bold text-lime-800 underline decoration-lime-500 decoration-2 underline-offset-4 hover:bg-lime-100">
                    Remove coupon
                </button>
            </form>
        </div>

        <p class="mt-1.5 text-xs font-semibold text-lime-800">
            <?php if ($message !== ''): ?>
                <?= e($message) ?>
            <?php elseif ($discount > 0): ?>
                You save <?= e(taka($discount)) ?> on this cart.
            <?php else: ?>
                The coupon is saved - it needs a bigger cart before it applies.
            <?php endif; ?>
        </p>
    </div>
<?php else: ?>
    <form data-ajax method="post" action="<?= e(route('cart.coupon')) ?>" class="space-y-2">
        <?= csrf_field() ?>
        <label for="coupon-code" class="block text-xs font-bold uppercase tracking-widest text-ink-400">Have a coupon?</label>
        <div class="flex gap-2">
            <input id="coupon-code"
                   type="text"
                   name="code"
                   value="<?= e(old('code')) ?>"
                   placeholder="ZAPSUB10"
                   autocomplete="off"
                   spellcheck="false"
                   maxlength="32"
                   class="w-full rounded-brand border border-brand-200 bg-white px-3 py-2 text-sm font-semibold uppercase tracking-wide text-ink outline-none transition duration-200 placeholder:text-ink-400 focus:border-brand-400 focus:ring-2 focus:ring-brand-300">
            <button type="submit" class="shrink-0 rounded-brand bg-ink px-4 py-2 text-sm font-bold text-white transition-colors duration-200 hover:bg-brand-700 active:scale-95">
                Apply
            </button>
        </div>
        <p class="text-[11px] leading-relaxed text-ink-400">Codes are case-insensitive and only work on carts that meet their rules.</p>
    </form>
<?php endif; ?>
