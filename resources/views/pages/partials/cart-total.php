<?php

declare(strict_types=1);

/**
 * The money block inside [data-cart-total]. CartService::totals() is the only
 * source of these numbers, so what the browser shows is what the order will be
 * written with.
 *
 * @var array{subtotal: float, discount: float, total: float, coupon: string, coupon_message: string} $totals
 * @var list<array<string, mixed>> $lines
 */
$totals = (array) ($totals ?? ['subtotal' => 0.0, 'discount' => 0.0, 'total' => 0.0, 'coupon' => '', 'coupon_message' => '']);
$lines = (array) ($lines ?? []);

$subtotal = (float) ($totals['subtotal'] ?? 0);
$discount = (float) ($totals['discount'] ?? 0);
$total = (float) ($totals['total'] ?? 0);
$coupon = trim((string) ($totals['coupon'] ?? ''));
$unitCount = 0;

foreach ($lines as $line) {
    $unitCount += max(1, (int) ($line['quantity'] ?? 1));
}
?>
<dl class="space-y-2.5 text-sm">
    <div class="flex items-baseline justify-between gap-3">
        <dt class="text-ink-400">Subtotal <span class="text-xs">(<?= e((string) $unitCount) ?> <?php echo $unitCount === 1 ? 'item' : 'items' ?>)</span></dt>
        <dd class="font-bold text-ink"><?= e(taka($subtotal)) ?></dd>
    </div>

    <?php if ($discount > 0): ?>
        <div class="flex items-baseline justify-between gap-3 text-lime-700">
            <dt class="font-semibold">
                Coupon <?= e($coupon) ?>
            </dt>
            <dd class="font-bold">-<?= e(taka($discount)) ?></dd>
        </div>
    <?php endif; ?>

    <div class="flex items-baseline justify-between gap-3 border-t border-dashed border-brand-200 pt-3">
        <dt class="text-base font-black text-ink">Total payable</dt>
        <dd class="animate-pop text-2xl font-black tracking-tight text-brand-700"><?= e(taka($total)) ?></dd>
    </div>
</dl>

<p class="mt-3 text-xs leading-relaxed text-ink-400">
    <?php if ($coupon !== '' && trim((string) ($totals['coupon_message'] ?? '')) !== ''): ?>
        <?= e((string) $totals['coupon_message']) ?>
    <?php else: ?>
        Recalculated on the server when you order. Delivery is on WhatsApp after a person confirms your payment.
    <?php endif; ?>
</p>
