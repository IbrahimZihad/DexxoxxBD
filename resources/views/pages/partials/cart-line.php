<?php

declare(strict_types=1);

/**
 * One cart line: its own form, so quantity, removal and a plain page reload all
 * work without JavaScript. $line is a CartService::lines() row.
 *
 * @var array<string, mixed> $line
 */
$planId = (int) ($line['plan_id'] ?? 0);
$serviceName = (string) ($line['service_name'] ?? '');
$serviceSlug = (string) ($line['service_slug'] ?? '');
$title = (string) ($line['title'] ?? 'Plan');
$quantity = max(1, (int) ($line['quantity'] ?? 1));
$unitPrice = (float) ($line['unit_price'] ?? 0);
$lineTotal = (float) ($line['line_total'] ?? 0);
$months = max(1, (int) ($line['duration_months'] ?? 1));
$seats = max(1, (int) ($line['seats'] ?? 1));
$quality = trim((string) ($line['quality'] ?? ''));
$stock = (int) ($line['stock'] ?? -1);
$mark = trim((string) ($line['logo_mark'] ?? '')) !== '' ? trim((string) $line['logo_mark']) : mb_substr($serviceName, 0, 1);

// Admin-entered hex colour in a style attribute: pattern-checked, then escaped.
$rawColor = (string) ($line['brand_color'] ?? '');
$brand = preg_match('/^#[0-9a-fA-F]{3,8}$/', $rawColor) === 1 ? $rawColor : '#7c50f2';
$tileStyle = 'background-image:linear-gradient(135deg,' . $brand . ' 0%,' . $brand . ' 35%,#1c0b47 140%)';

$perLine = max(1, (int) app()->config('app.max_quantity_per_line', 5));
$maxQuantity = $stock >= 0 ? max(1, min($perLine, $stock)) : $perLine;
$scarce = $stock > 0 && $stock <= 3;
?>
<form data-ajax
      method="post"
      action="<?= e(route('cart.update')) ?>"
      data-line-id="<?= e((string) $planId) ?>"
      class="reveal hover-lift grid gap-4 rounded-card border border-brand-100 bg-white p-4 shadow-soft sm:grid-cols-[auto_1fr] sm:p-5"
      data-reveal
      style="--reveal-delay:80ms">
    <?= csrf_field() ?>
    <input type="hidden" name="plan_id" value="<?= e((string) $planId) ?>">

    <span class="relative grid h-16 w-16 shrink-0 place-items-center overflow-hidden rounded-brand" style="<?= e($tileStyle) ?>">
        <span class="animate-shimmer absolute inset-0 bg-gradient-to-r from-white/0 via-white/35 to-white/0"></span>
        <span class="relative text-xl font-black text-white"><?= e($mark) ?></span>
    </span>

    <div class="min-w-0">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="min-w-0">
                <p class="text-xs font-bold uppercase tracking-widest text-brand-600">
                    <?php if ($serviceSlug !== ''): ?>
                        <a href="<?= e(route('service.show', ['slug' => $serviceSlug])) ?>" class="hover:underline"><?= e($serviceName) ?></a>
                    <?php else: ?>
                        <?= e($serviceName) ?>
                    <?php endif; ?>
                </p>
                <h3 class="mt-1 truncate text-base font-bold text-ink" title="<?= e($title) ?>"><?= e($title) ?></h3>
                <p class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs font-semibold text-ink-400">
                    <span><?= e($months === 1 ? '1 month access' : $months . ' months access') ?></span>
                    <span aria-hidden="true">&middot;</span>
                    <span><?= e($seats === 1 ? '1 seat' : $seats . ' seats') ?></span>
                    <?php if ($quality !== ''): ?>
                        <span class="rounded-pill bg-accent-50 px-2 py-0.5 text-accent-700"><?= e($quality) ?></span>
                    <?php endif; ?>
                </p>
                <?php if ($scarce): ?>
                    <p class="mt-1.5 text-xs font-bold text-amber-600">Only <?= e((string) $stock) ?> left in stock</p>
                <?php endif; ?>
            </div>

            <p class="text-right">
                <span class="block text-lg font-black text-ink"><?= e(taka($lineTotal)) ?></span>
                <span class="block text-xs font-semibold text-ink-400"><?= e(taka($unitPrice, false)) ?> each</span>
            </p>
        </div>

        <div class="mt-4 flex flex-wrap items-center gap-2">
            <div data-qty data-min="1" data-max="<?= e((string) $maxQuantity) ?>"
                 class="flex items-center gap-1 rounded-pill border border-brand-200 bg-brand-50 p-1">
                <button type="button" data-qty-minus aria-label="Decrease quantity of <?= e($title) ?>"
                        class="grid h-8 w-8 place-items-center rounded-pill bg-white text-lg font-black text-brand-700 shadow-soft transition-transform duration-150 hover:scale-105 active:scale-95">&minus;</button>
                <input data-qty-input
                       type="number"
                       name="quantity"
                       value="<?= e((string) $quantity) ?>"
                       min="1"
                       max="<?= e((string) $maxQuantity) ?>"
                       inputmode="numeric"
                       aria-label="Quantity for <?= e($title) ?>"
                       class="w-12 bg-transparent text-center text-sm font-black text-ink outline-none">
                <button type="button" data-qty-plus aria-label="Increase quantity of <?= e($title) ?>"
                        class="grid h-8 w-8 place-items-center rounded-pill bg-white text-lg font-black text-brand-700 shadow-soft transition-transform duration-150 hover:scale-105 active:scale-95">+</button>
            </div>

            <button type="submit"
                    class="rounded-pill border border-brand-200 bg-white px-4 py-2 text-xs font-bold text-brand-700 transition-colors duration-200 hover:border-brand-400 hover:bg-brand-50">
                Update
            </button>

            <button type="submit"
                    formaction="<?= e(route('cart.remove')) ?>"
                    class="rounded-pill bg-rose-50 px-4 py-2 text-xs font-bold text-rose-700 ring-1 ring-rose-200 transition-colors duration-200 hover:bg-rose-100">
                Remove
            </button>
        </div>
    </div>
</form>
