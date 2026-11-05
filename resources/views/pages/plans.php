<?php

declare(strict_types=1);

/**
 * The full catalogue. Data comes from CatalogController::index():
 * $plans, $total, $pages, $page, $perPage, $filters, $categories, $durations,
 * $priceRange, $serviceSlugs.
 *
 * Filtering is plain GET: every control either submits the one form or is a
 * link that rebuilds the query string, so it all works with JavaScript off.
 *
 * @var App\Core\View $view
 * @var list<array<string, mixed>> $plans
 * @var int $total
 * @var int $pages
 * @var int $page
 * @var int $perPage
 * @var array<string, string> $filters
 * @var list<string> $categories
 * @var list<int> $durations
 * @var array{min: float, max: float} $priceRange
 * @var list<string> $serviceSlugs
 */
$brandName = setting('brand.name', 'DexxozzBD');
$filters = array_map('strval', (array) ($filters ?? []));
$activeSort = (string) ($filters['sort'] ?? 'popular');

/** @var array<string, string> $sortLabels mirrors the whitelist in Plan::applySort(). */
$sortLabels = [
    'popular' => 'Most popular',
    'price_low' => 'Cheapest first',
    'price_high' => 'Premium first',
    'newest' => 'Newest',
    'duration' => 'Longest plan',
    'longest_value' => 'Best value / month',
];

/** Rebuilds /plans?… keeping whatever is already selected. */
$plansUrl = static function (array $overrides) use ($filters): string {
    $query = array_merge($filters, $overrides);
    $page = max(1, (int) ($query['page'] ?? 1));
    unset($query['page']);

    $query = array_filter($query, static fn ($value): bool => (string) $value !== '');

    if ($page > 1) {
        $query['page'] = $page;
    }

    return route('plans', [], $query);
};

$monthLabel = static fn (int $months): string => $months === 1 ? '1 month' : $months . ' months';

/** Slugs are the only service text the controller sends, so make them readable. */
$serviceLabel = static fn (string $slug): string => ucwords(str_replace(['-', '_'], ' ', $slug));

$durationOptions = ['' => 'Any length'];
foreach ($durations as $months) {
    $durationOptions[$months] = $monthLabel((int) $months);
}

$activeChips = [];
if (($filters['search'] ?? '') !== '') {
    $activeChips['search'] = 'Search: "' . $filters['search'] . '"';
}
if (($filters['service'] ?? '') !== '') {
    $activeChips['service'] = $serviceLabel($filters['service']);
}
if (($filters['duration'] ?? '') !== '') {
    $activeChips['duration'] = $monthLabel((int) $filters['duration']);
}
if (($filters['seats'] ?? '') !== '') {
    $activeChips['seats'] = $filters['seats'] . ' seat' . ((int) $filters['seats'] === 1 ? '' : 's');
}
if (($filters['max_price'] ?? '') !== '') {
    $activeChips['max_price'] = 'Up to ' . taka((float) $filters['max_price'], false);
}

$from = $total === 0 ? 0 : (($page - 1) * $perPage) + 1;
$to = min($total, $page * $perPage);

$view->extend('layouts/app');
$view->setSection('title', 'All subscription plans - ' . $brandName);
$view->setSection('meta_description', 'Browse every ' . $brandName . ' plan: ' . $total . ' live tiers from '
    . taka($priceRange['min'], false) . ' to ' . taka($priceRange['max'], false) . ', filterable by brand, length and price.');
$view->setSection('canonical', route('plans'));
?>
<div class="bg-white">
    <?php /* --------------------------- page header ---------------------------*/ ?>
    <section class="relative overflow-hidden border-b border-brand-100 bg-gradient-to-br from-brand-50 via-white to-accent-50">
        <span class="blob -left-20 -top-24 animate-blob opacity-50"></span>
        <span class="blob -right-10 top-4 animate-blob [animation-delay:-6s]"></span>

        <div class="relative mx-auto max-w-7xl px-4 py-12 lg:px-6">
            <nav aria-label="Breadcrumb">
                <ol class="flex flex-wrap items-center gap-2 text-xs font-semibold text-ink-400">
                    <li><a href="<?= e(route('home')) ?>" class="transition-colors duration-200 hover:text-brand-700">Home</a></li>
                    <li aria-hidden="true">&rsaquo;</li>
                    <li aria-current="page" class="text-brand-700">Plans</li>
                </ol>
            </nav>

            <h1 class="mt-4 text-3xl font-black tracking-tight md:text-5xl">
                <span class="animate-gradient text-gradient">Every plan on one shelf</span>
            </h1>
            <p class="mt-3 max-w-2xl text-sm leading-relaxed text-ink-400 md:text-base">
                <?= e((string) count($serviceSlugs)) ?> brands, <?= e((string) $total) ?> live tiers, prices from
                <?= e(taka($priceRange['min'], false)) ?> to <?= e(taka($priceRange['max'], false)) ?>.
                Pay with bKash, Nagad or Rocket and we deliver the login on WhatsApp.
            </p>

            <div class="mt-6 flex flex-wrap gap-2">
                <?php foreach ($categories as $category): ?>
                    <a href="<?= e($plansUrl(['category' => $category, 'page' => 1])) ?>"
                       class="rounded-pill border px-4 py-2 text-sm font-bold transition duration-200 hover:-translate-y-0.5 <?= e(($filters['category'] ?? '') === $category
                            ? 'border-brand-600 bg-brand-600 text-white shadow-pop'
                            : 'border-brand-200 bg-white/80 text-brand-800 hover:border-brand-400 hover:bg-brand-50') ?>">
                        <?= e($category) ?>
                    </a>
                <?php endforeach; ?>
                <?php if (($filters['category'] ?? '') !== ''): ?>
                    <a href="<?= e($plansUrl(['category' => '', 'page' => 1])) ?>"
                       class="rounded-pill border border-rose-200 bg-rose-50 px-4 py-2 text-sm font-bold text-rose-700 transition-colors duration-200 hover:bg-rose-100">
                        Show all categories
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <?php /* ----------------------- filters + results --------------------------*/ ?>
    <div class="mx-auto grid max-w-7xl gap-8 px-4 py-10 lg:grid-cols-[280px_minmax(0,1fr)] lg:px-6">
        <aside class="lg:sticky lg:top-24 lg:self-start">
            <form method="get" action="<?= e(route('plans')) ?>" class="space-y-4 rounded-card border border-brand-100 bg-white p-5 shadow-soft">
                <?php /* Hidden fields so applying a filter never silently drops the chips you already clicked. */ ?>
                <input type="hidden" name="category" value="<?= e($filters['category'] ?? '') ?>">
                <input type="hidden" name="service" value="<?= e($filters['service'] ?? '') ?>">
                <input type="hidden" name="sort" value="<?= e($activeSort) ?>">

                <p class="flex items-center gap-2 text-xs font-black uppercase tracking-widest text-brand-700">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path d="M4 5h16l-6 8v5l-4 2v-7L4 5Z" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    Refine
                </p>

                <?= $view->component('field', [
                    'name' => 'search',
                    'label' => 'Search',
                    'type' => 'text',
                    'value' => $filters['search'] ?? '',
                    'placeholder' => 'Netflix family, Canva, 4K…',
                ]) ?>

                <?= $view->component('field', [
                    'name' => 'duration',
                    'label' => 'Plan length',
                    'type' => 'select',
                    'value' => $filters['duration'] ?? '',
                    'options' => $durationOptions,
                ]) ?>

                <?= $view->component('field', [
                    'name' => 'seats',
                    'label' => 'Seats',
                    'type' => 'number',
                    'value' => $filters['seats'] ?? '',
                    'placeholder' => 'Any',
                    'hint' => '1 for personal plans, 2 or more for family and group tiers.',
                ]) ?>

                <?= $view->component('field', [
                    'name' => 'max_price',
                    'label' => 'Maximum budget',
                    'type' => 'number',
                    'value' => $filters['max_price'] ?? '',
                    'placeholder' => (string) (int) ceil($priceRange['max']),
                    'hint' => 'Shows plans costing up to that total, not per month.',
                ]) ?>

                <div class="flex flex-col gap-2 pt-1">
                    <button type="submit" class="relative overflow-hidden rounded-brand bg-gradient-to-r from-brand-600 to-brand-500 px-4 py-3 text-sm font-black text-white shadow-glow transition-transform duration-200 hover:-translate-y-0.5">
                        <span class="animate-shimmer absolute inset-0 bg-gradient-to-r from-white/0 via-white/30 to-white/0"></span>
                        Apply filters
                    </button>
                    <a href="<?= e(route('plans')) ?>" class="rounded-brand border border-brand-200 px-4 py-3 text-center text-sm font-bold text-brand-700 transition-colors duration-200 hover:bg-brand-50">
                        Clear everything
                    </a>
                </div>
            </form>

            <?php if ($serviceSlugs !== []): ?>
                <div class="mt-5 rounded-card border border-brand-100 bg-brand-50/60 p-5">
                    <p class="text-xs font-black uppercase tracking-widest text-brand-700">Jump to a brand</p>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <?php foreach (array_slice($serviceSlugs, 0, 12) as $slug): ?>
                            <a href="<?= e($plansUrl(['service' => $slug, 'page' => 1])) ?>"
                               class="rounded-pill border px-3 py-1.5 text-xs font-bold transition-colors duration-200 <?= e(($filters['service'] ?? '') === $slug
                                    ? 'border-brand-600 bg-white text-brand-800'
                                    : 'border-brand-200 bg-white/60 text-ink-400 hover:border-brand-400 hover:text-brand-700') ?>">
                                <?= e($serviceLabel((string) $slug)) ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </aside>

        <div>
            <div class="flex flex-col gap-4 rounded-card border border-brand-100 bg-gradient-to-r from-white to-brand-50/60 p-4 md:flex-row md:items-center md:justify-between">
                <p class="text-sm font-semibold text-ink" aria-live="polite">
                    <?php if ($total > 0): ?>
                        Showing <span class="text-brand-700"><?= e((string) $from) ?></span>&ndash;<span class="text-brand-700"><?= e((string) $to) ?></span>
                        of <span class="text-brand-700"><?= e((string) $total) ?></span> plans
                    <?php else: ?>
                        No plans match those filters
                    <?php endif; ?>
                </p>

                <div class="no-scrollbar flex gap-1.5 overflow-x-auto" role="group" aria-label="Sort plans">
                    <?php foreach ($sortLabels as $sortKey => $sortLabel): ?>
                        <a href="<?= e($plansUrl(['sort' => $sortKey, 'page' => 1])) ?>"
                           aria-current="<?= e($sortKey === $activeSort ? 'true' : 'false') ?>"
                           class="shrink-0 rounded-pill px-3 py-1.5 text-xs font-bold transition-colors duration-200 <?= e($sortKey === $activeSort
                                ? 'bg-ink text-white'
                                : 'bg-white text-ink-400 ring-1 ring-brand-200 hover:text-brand-700') ?>">
                            <?= e($sortLabel) ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <?php if ($activeChips !== []): ?>
                <div class="mt-4 flex flex-wrap items-center gap-2">
                    <span class="text-xs font-bold uppercase tracking-widest text-ink-400">Active</span>
                    <?php foreach ($activeChips as $key => $label): ?>
                        <a href="<?= e($plansUrl([$key => '', 'page' => 1])) ?>"
                           class="inline-flex items-center gap-1.5 rounded-pill bg-brand-100 px-3 py-1.5 text-xs font-bold text-brand-800 transition-colors duration-200 hover:bg-brand-200">
                            <?= e($label) ?>
                            <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" aria-hidden="true">
                                <path d="M6 6l12 12M18 6 6 18" stroke-linecap="round"/>
                            </svg>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ($plans === []): ?>
                <div class="mt-6">
                    <?= $view->component('empty-state', [
                        'icon' => 'search',
                        'title' => 'Nothing matches that combination',
                        'text' => 'Try a wider budget or a longer plan length - or ask us on WhatsApp, we can put custom bundles together for offices and study groups.',
                        'action_url' => route('plans'),
                        'action_label' => 'Reset the filters',
                    ]) ?>
                </div>
            <?php else: ?>
                <div class="mt-6 grid gap-6 sm:grid-cols-2 xl:grid-cols-3">
                    <?php foreach ($plans as $plan): ?>
                        <?= $view->component('plan-card', ['plan' => $plan]) ?>
                    <?php endforeach; ?>
                </div>

                <?php if ($pages > 1): ?>
                    <?php
                    $windowStart = max(1, min($page - 2, $pages - 4));
                    $windowEnd = min($pages, $windowStart + 4);
                    ?>
                    <nav aria-label="Plan pages" class="mt-10 flex flex-wrap items-center justify-center gap-2">
                        <?php if ($page > 1): ?>
                            <a href="<?= e($plansUrl(['page' => $page - 1])) ?>" rel="prev" class="inline-flex items-center gap-1.5 rounded-pill border border-brand-200 bg-white px-4 py-2 text-sm font-bold text-brand-700 transition-colors duration-200 hover:bg-brand-50">
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true"><path d="M15 6l-6 6 6 6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                Previous
                            </a>
                        <?php endif; ?>

                        <?php if ($windowStart > 1): ?>
                            <a href="<?= e($plansUrl(['page' => 1])) ?>" class="rounded-pill border border-brand-200 bg-white px-3.5 py-2 text-sm font-bold text-ink-400 hover:text-brand-700">1</a>
                            <?php if ($windowStart > 2): ?>
                                <span class="text-sm font-bold text-ink-400" aria-hidden="true">&hellip;</span>
                            <?php endif; ?>
                        <?php endif; ?>

                        <?php for ($number = $windowStart; $number <= $windowEnd; $number++): ?>
                            <?php if ($number === $page): ?>
                                <span aria-current="page" class="rounded-pill bg-gradient-to-r from-brand-600 to-brand-500 px-4 py-2 text-sm font-black text-white shadow-pop"><?= e((string) $number) ?></span>
                            <?php else: ?>
                                <a href="<?= e($plansUrl(['page' => $number])) ?>" class="rounded-pill border border-brand-200 bg-white px-4 py-2 text-sm font-bold text-brand-700 transition-colors duration-200 hover:bg-brand-50"><?= e((string) $number) ?></a>
                            <?php endif; ?>
                        <?php endfor; ?>

                        <?php if ($windowEnd < $pages): ?>
                            <?php if ($windowEnd < $pages - 1): ?>
                                <span class="text-sm font-bold text-ink-400" aria-hidden="true">&hellip;</span>
                            <?php endif; ?>
                            <a href="<?= e($plansUrl(['page' => $pages])) ?>" class="rounded-pill border border-brand-200 bg-white px-3.5 py-2 text-sm font-bold text-ink-400 hover:text-brand-700"><?= e((string) $pages) ?></a>
                        <?php endif; ?>

                        <?php if ($page < $pages): ?>
                            <a href="<?= e($plansUrl(['page' => $page + 1])) ?>" rel="next" class="inline-flex items-center gap-1.5 rounded-pill bg-ink px-4 py-2 text-sm font-bold text-white transition-colors duration-200 hover:bg-brand-700">
                                Next
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true"><path d="M9 6l6 6-6 6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            </a>
                        <?php endif; ?>
                    </nav>
                <?php endif; ?>
            <?php endif; ?>

            <section class="mt-12 overflow-hidden rounded-card bg-gradient-to-r from-brand-700 via-brand-500 to-accent-500 p-6 text-white md:p-8">
                <h2 class="text-xl font-black tracking-tight md:text-2xl">Can&rsquo;t find the tier you want?</h2>
                <p class="mt-2 max-w-xl text-sm leading-relaxed text-white/85">
                    We add brands every week and we can quote shared plans for offices, coaching centres and families.
                    Send one message and <?= e(setting('whatsapp.agent_name', 'our order desk')) ?> will price it.
                </p>
                <a href="<?= e(wa_link('Hi ' . $brandName . ', I am looking for a plan you do not list yet.')) ?>"
                   target="_blank" rel="noopener"
                   class="mt-5 inline-flex items-center gap-2 rounded-brand bg-white px-5 py-3 text-sm font-black text-brand-700 transition-transform duration-200 hover:-translate-y-0.5">
                    <svg class="h-5 w-5 text-emerald-600" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                        <path d="M12 2a10 10 0 0 0-8.6 15L2 22l5.2-1.3A10 10 0 1 0 12 2Zm5.3 14c-.2.6-1.2 1.2-1.7 1.2-.5.1-1 .1-1.7-.1-.9-.3-2.3-1-3.6-2.6-1-1.2-1.6-2.5-1.7-3-.1-.6.3-1.4.8-1.8.2-.2.4-.2.6-.2h.5c.2 0 .3 0 .4.3l.6 1.4c.1.2 0 .4-.1.5l-.4.5c-.1.1-.1.3 0 .4.3.5 1.2 1.5 2.1 1.9.2.1.3.1.5-.1l.5-.6c.1-.2.3-.2.5-.1l1.4.7c.2.1.3.2.3.4 0 .2 0 .7-.3 1.2Z"/>
                    </svg>
                    Ask on WhatsApp
                </a>
            </section>
        </div>
    </div>
</div>
