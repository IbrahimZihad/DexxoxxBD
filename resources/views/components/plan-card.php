<?php

declare(strict_types=1);

/**
 * Plan card. $plan is a plans row joined with its service (Plan::browse(),
 * Plan::browseAll(), Plan::withService()).
 *
 * @var array<string, mixed> $plan
 */
$plan = (array) ($plan ?? []);

$id = (int) ($plan['id'] ?? 0);
$title = (string) ($plan['title'] ?? 'Plan');
$price = (float) ($plan['price'] ?? 0);
$compare = (float) ($plan['compare_at_price'] ?? 0);
$months = max(1, (int) ($plan['duration_months'] ?? 1));
$seats = max(1, (int) ($plan['seats'] ?? 1));
$quality = trim((string) ($plan['quality'] ?? ''));
$device = trim((string) ($plan['device'] ?? ''));
$badge = trim((string) ($plan['badge'] ?? ''));
$description = trim((string) ($plan['description'] ?? ''));
$stock = (int) ($plan['stock'] ?? -1);
$serviceName = (string) ($plan['service_name'] ?? '');
$serviceSlug = (string) ($plan['service_slug'] ?? '');
$featured = (int) ($plan['is_featured'] ?? 0) === 1;

// A raw hex colour from the admin field goes into a style attribute, so it is
// validated here and falls back to the brand violet.
$rawColor = (string) ($plan['brand_color'] ?? '');
$brand = preg_match('/^#[0-9a-fA-F]{3,8}$/', $rawColor) === 1 ? $rawColor : '#7c50f2';
$mark = trim((string) ($plan['logo_mark'] ?? '')) !== '' ? trim((string) $plan['logo_mark']) : mb_substr($serviceName, 0, 1);

$soldOut = $stock === 0;
$scarce = $stock > 0 && $stock <= 3;
$perMonth = $price / $months;
$saving = $compare > $price ? $compare - $price : 0.0;
$tileStyle = 'background-image:linear-gradient(135deg,' . $brand . ' 0%,' . $brand . ' 30%,#1c0b47 135%)';
$badgeTone = match ($badge) {
    'Save 20%' => 'bg-amber-500',
    'Best value' => 'bg-emerald-600',
    'Popular' => 'bg-brand-600',
    default => 'bg-accent-600',
};
?>
<article class="reveal hover-lift group relative flex flex-col overflow-hidden rounded-card border bg-white shadow-soft <?= e($featured ? 'border-brand-300 ring-1 ring-brand-200' : 'border-brand-100') ?>" data-reveal style="--reveal-delay:60ms">
    <?php if ($badge !== ''): ?>
        <span class="absolute -right-9 top-3.5 z-10 w-32 rotate-45 <?= e($badgeTone) ?> py-1 text-center text-[10px] font-black uppercase tracking-wider text-white shadow-soft">
            <?= e($badge) ?>
        </span>
    <?php endif; ?>

    <div class="relative flex h-24 items-center gap-3 overflow-hidden px-4" style="<?= e($tileStyle) ?>">
        <span class="animate-shimmer absolute inset-0 bg-gradient-to-r from-white/0 via-white/30 to-white/0 opacity-0 transition-opacity duration-300 group-hover:opacity-100"></span>
        <span class="grid h-12 w-12 shrink-0 place-items-center rounded-brand bg-white/15 text-lg font-black text-white ring-1 ring-white/30 backdrop-blur">
            <?= e($mark) ?>
        </span>
        <span class="min-w-0">
            <?php if ($serviceSlug !== ''): ?>
                <a href="<?= e(route('service.show', ['slug' => $serviceSlug])) ?>" class="block truncate text-sm font-bold text-white hover:underline">
                    <?= e($serviceName) ?>
                </a>
            <?php else: ?>
                <span class="block truncate text-sm font-bold text-white"><?= e($serviceName) ?></span>
            <?php endif; ?>
            <span class="mt-0.5 block text-xs font-semibold uppercase tracking-wider text-white/80">
                <?= e($months === 1 ? '1 month' : $months . ' months') ?>
            </span>
        </span>
    </div>

    <div class="flex grow flex-col gap-3 p-4">
        <h3 class="text-base font-bold leading-tight text-ink"><?= e($title) ?></h3>

        <?php if ($description !== ''): ?>
            <p class="line-clamp-2 text-sm leading-relaxed text-ink-400"><?= e($description) ?></p>
        <?php endif; ?>

        <div class="mt-auto">
            <div class="flex flex-wrap items-baseline gap-x-2 gap-y-1">
                <span class="text-2xl font-black tracking-tight text-ink"><?= e(taka($price, false)) ?></span>
                <?php if ($saving > 0): ?>
                    <span class="text-sm font-medium text-ink-400 line-through"><?= e(taka($compare, false)) ?></span>
                <?php endif; ?>
            </div>
            <p class="mt-1 text-xs font-semibold text-brand-600">
                Only <?= e(taka($perMonth, false)) ?> / month
                <?php if ($saving > 0): ?>
                    <span class="ml-1 rounded-pill bg-lime-100 px-2 py-0.5 text-[11px] font-bold text-lime-700">You save <?= e(taka($saving, false)) ?></span>
                <?php endif; ?>
            </p>

            <div class="mt-3 flex flex-wrap gap-1.5">
                <span class="rounded-pill bg-brand-50 px-2.5 py-1 text-[11px] font-semibold text-brand-700">
                    <?= e($seats === 1 ? '1 seat' : $seats . ' seats') ?>
                </span>
                <?php if ($quality !== ''): ?>
                    <span class="rounded-pill bg-accent-50 px-2.5 py-1 text-[11px] font-semibold text-accent-700"><?= e($quality) ?></span>
                <?php endif; ?>
                <?php if ($device !== ''): ?>
                    <span class="rounded-pill bg-slate-100 px-2.5 py-1 text-[11px] font-semibold text-slate-700"><?= e($device) ?></span>
                <?php endif; ?>
            </div>

            <?php if ($scarce): ?>
                <p class="mt-2 text-xs font-bold text-amber-600">Only <?= e((string) $stock) ?> left in stock</p>
            <?php endif; ?>

            <?php if ($soldOut): ?>
                <div class="mt-4 rounded-brand border border-rose-200 bg-rose-50 p-3">
                    <p class="text-sm font-bold text-rose-700">Sold out for now</p>
                    <a href="<?= e(wa_link('Is the ' . $serviceName . ' ' . $title . ' plan back in stock yet?')) ?>"
                       target="_blank" rel="noopener"
                       class="mt-1 inline-block text-xs font-semibold text-brand-700 underline decoration-accent-400 decoration-2 underline-offset-4">
                        Ask us on WhatsApp
                    </a>
                </div>
            <?php else: ?>
                <form data-ajax method="post" action="<?= e(route('cart.add')) ?>" class="mt-4">
                    <?= csrf_field() ?>
                    <input type="hidden" name="plan_id" value="<?= e((string) $id) ?>">
                    <input type="hidden" name="quantity" value="1">
                    <button type="submit" class="w-full rounded-brand bg-gradient-to-r from-brand-600 to-brand-500 px-4 py-2.5 text-sm font-bold text-white shadow-glow transition-all duration-200 hover:from-brand-700 hover:to-brand-600 active:scale-95">
                        Add to cart
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</article>
