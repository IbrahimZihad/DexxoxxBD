<?php

declare(strict_types=1);

/**
 * One selected subscription. A plan is always a single subscription, so there
 * is no quantity control here - only the plan details and a remove action.
 * $line is a CartService::lines() row.
 *
 * @var array<string, mixed> $line
 */
$planId = (int) ($line['plan_id'] ?? 0);
$serviceName = (string) ($line['service_name'] ?? '');
$serviceSlug = (string) ($line['service_slug'] ?? '');
$title = (string) ($line['title'] ?? 'Plan');
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

$scarce = $stock > 0 && $stock <= 3;
?>
<form data-ajax
      method="post"
      action="<?= e(route('cart.remove')) ?>"
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
                <span class="block text-xs font-semibold text-ink-400">per subscription</span>
            </p>
        </div>

        <div class="mt-4 flex flex-wrap items-center gap-2">
            <span class="rounded-pill bg-brand-50 px-3 py-1.5 text-xs font-bold text-brand-700">1 subscription</span>

            <button type="submit"
                    class="rounded-pill bg-rose-50 px-4 py-2 text-xs font-bold text-rose-700 ring-1 ring-rose-200 transition-colors duration-200 hover:bg-rose-100">
                Remove
            </button>
        </div>
    </div>
</form>
