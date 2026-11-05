<?php

declare(strict_types=1);

/**
 * Service tile for the brand grid. Row shape comes from Service::catalogue().
 *
 * @var array<string, mixed> $service
 */
$service = (array) ($service ?? []);

$slug = (string) ($service['slug'] ?? '');
$name = (string) ($service['name'] ?? 'Service');
$category = (string) ($service['category'] ?? '');
$tagline = trim((string) ($service['tagline'] ?? ''));
$mark = trim((string) ($service['logo_mark'] ?? '')) !== '' ? trim((string) $service['logo_mark']) : mb_substr($name, 0, 1);
$planCount = (int) ($service['plan_count'] ?? 0);
$fromPrice = (float) ($service['from_price'] ?? 0);
$isPopular = (int) ($service['popular'] ?? 0) >= 9;

$rawColor = (string) ($service['brand_color'] ?? '');
$brand = preg_match('/^#[0-9a-fA-F]{3,8}$/', $rawColor) === 1 ? $rawColor : '#7c50f2';
?>
<a href="<?= e($slug === '' ? route('plans') : route('service.show', ['slug' => $slug])) ?>"
   class="reveal hover-lift group relative flex flex-col overflow-hidden rounded-card border border-brand-100 bg-white p-5 shadow-soft"
   data-reveal>
    <span class="blob -right-16 -top-16 h-40 w-40 opacity-30 transition-opacity duration-300 group-hover:opacity-50" style="background-image:linear-gradient(135deg,<?= e($brand) ?>,#1c0b47)"></span>

    <?php if ($isPopular): ?>
        <span class="absolute right-4 top-4 z-10 rounded-pill bg-amber-100 px-2.5 py-1 text-[10px] font-black uppercase tracking-wider text-amber-800">Popular</span>
    <?php endif; ?>

    <span class="relative grid h-14 w-14 place-items-center rounded-brand text-xl font-black text-white shadow-pop transition-transform duration-300 group-hover:-translate-y-1" style="background-image:linear-gradient(135deg,<?= e($brand) ?> 0%,<?= e($brand) ?> 40%,#1c0b47 140%)">
        <?= e($mark) ?>
    </span>

    <span class="relative mt-4 block text-base font-bold leading-tight text-ink group-hover:text-brand-700"><?= e($name) ?></span>

    <?php if ($category !== ''): ?>
        <span class="relative mt-1 block text-[11px] font-bold uppercase tracking-widest text-accent-600"><?= e($category) ?></span>
    <?php endif; ?>

    <?php if ($tagline !== ''): ?>
        <span class="relative mt-2 block text-sm leading-relaxed text-ink-400"><?= e($tagline) ?></span>
    <?php endif; ?>

    <span class="relative mt-4 flex items-center justify-between border-t border-brand-100 pt-3 text-sm">
        <?php if ($fromPrice > 0): ?>
            <span class="font-black text-ink">
                <span class="text-[11px] font-semibold uppercase tracking-wide text-ink-400">From</span>
                <?= e(taka($fromPrice, false)) ?>
            </span>
        <?php else: ?>
            <span class="font-semibold text-ink-400">See plans</span>
        <?php endif; ?>

        <span class="inline-flex items-center gap-1 font-bold text-brand-600">
            <?= e($planCount === 1 ? '1 plan' : $planCount . ' plans') ?>
            <svg class="h-4 w-4 transition-transform duration-200 group-hover:translate-x-1" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path d="M5 12h14M13 6l6 6-6 6" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        </span>
    </span>
</a>
