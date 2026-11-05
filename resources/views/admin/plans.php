<?php

declare(strict_types=1);

/**
 * Admin plans. The read view is a real .a-table joined through Plan::allForAdmin()
 * (the row already carries service_name / brand_color / logo_mark); a create form
 * and one edit form per row live in [data-accordion] panels.
 *
 * The panels are NOT hidden in the markup on purpose: every form is a plain
 * <form method="post"> with a CSRF token that stays reachable and submittable with
 * JavaScript switched off - the accordion only ever collapses what is already open.
 *
 * @var App\Core\View $view
 * @var list<array<string, mixed>> $plans
 * @var list<array<string, mixed>> $services
 * @var array{search: string, service_id: int, active: string} $filters
 */
$brandName = setting('brand.name', 'ZapSub');

$plans = array_values(array_map(static fn (mixed $row): array => (array) $row, (array) ($plans ?? [])));
$services = array_values(array_map(static fn (mixed $row): array => (array) $row, (array) ($services ?? [])));
$filters = (array) ($filters ?? []);
$search = trim((string) ($filters['search'] ?? ''));
$filterService = (int) ($filters['service_id'] ?? 0);
$filterActive = (string) ($filters['active'] ?? '');

$input = 'w-full rounded-brand border border-brand-200 bg-white px-3.5 py-2.5 text-sm text-ink outline-none transition duration-200 placeholder:text-ink-400 focus:border-brand-400 focus:ring-2 focus:ring-brand-300';
$labelClass = 'block text-xs font-black uppercase tracking-[0.12em] text-ink-400';
$box = 'mt-0.5 h-4 w-4 shrink-0 rounded accent-brand-600';

/** @param array<string, mixed> $plan */
$durationLabel = static function (array $plan): string {
    $months = (int) ($plan['duration_months'] ?? 0);

    return $months % 12 === 0 && $months > 0
        ? intdiv($months, 12) . ($months === 12 ? ' year' : ' years')
        : $months . ' mo';
};

$view->extend('layouts/admin');
$view->setSection('title', 'Plans - ' . $brandName);
$view->setSection('page_title', 'Plans');
$view->setSection('page_actions', '<a href="' . e(route('admin.services')) . '" class="inline-flex items-center gap-2 rounded-brand border border-brand-200 bg-white px-4 py-2 text-sm font-bold text-brand-700 transition-colors duration-200 hover:bg-brand-50">'
    . '<svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path d="M4 7h16v12H4z" stroke-linejoin="round"/><path d="M9 7V4h6v3" stroke-linejoin="round"/></svg>'
    . 'Manage services</a>');
?>

<section aria-labelledby="plans-add" class="rounded-card border border-brand-100 bg-white p-4 shadow-soft sm:p-5">
    <div data-accordion>
        <button type="button" data-accordion-btn aria-expanded="true" class="flex w-full items-center justify-between gap-3 text-left">
            <span>
                <span id="plans-add" class="text-base font-black tracking-tight text-ink">Add a plan</span>
                <span class="mt-0.5 block text-xs text-ink-400">A duration tier a customer can buy - 1, 3, 6 or 12 months, seats, quality.</span>
            </span>
            <span class="rounded-brand bg-brand-600 px-3 py-1.5 text-xs font-black text-white">Open form</span>
        </button>

        <div data-accordion-panel class="mt-4">
            <?php if ($services === []): ?>
                <p class="rounded-brand bg-amber-50 px-3 py-2 text-sm font-semibold text-amber-800">
                    Add a service first - a plan always belongs to one.
                    <a class="underline" href="<?= e(route('admin.services')) ?>">Go to services</a>.
                </p>
            <?php else: ?>
                <form method="post" action="<?= e(route('admin.plan.store')) ?>" class="space-y-4">
                    <?= csrf_field() ?>
                    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                        <label class="block">
                            <span class="<?= e($labelClass) ?>">Service <span class="text-rose-500">*</span></span>
                            <select name="service_id" required class="<?= e($input) ?> mt-1 <?= e(error_class('service_id')) ?>">
                                <option value="">Choose a service&hellip;</option>
                                <?php foreach ($services as $service): ?>
                                    <?php $sid = (string) (int) ($service['id'] ?? 0); ?>
                                    <option value="<?= e($sid) ?>" <?= e(old('service_id') === $sid ? 'selected' : '') ?>><?= e((string) ($service['name'] ?? '')) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php foreach (field_errors('service_id') as $message): ?><p class="mt-1 text-xs font-semibold text-rose-600"><?= e((string) $message) ?></p><?php endforeach; ?>
                        </label>

                        <label class="block sm:col-span-2 xl:col-span-2">
                            <span class="<?= e($labelClass) ?>">Title <span class="text-rose-500">*</span></span>
                            <input type="text" name="title" value="<?= e(old('title')) ?>" required maxlength="120" class="<?= e($input) ?> mt-1 <?= e(error_class('title')) ?>" placeholder="Premium - 4 screens">
                            <?php foreach (field_errors('title') as $message): ?><p class="mt-1 text-xs font-semibold text-rose-600"><?= e((string) $message) ?></p><?php endforeach; ?>
                        </label>

                        <label class="block">
                            <span class="<?= e($labelClass) ?>">Duration (months) <span class="text-rose-500">*</span></span>
                            <input type="number" name="duration_months" value="<?= e(old('duration_months', '1')) ?>" required min="1" max="600" step="1" inputmode="numeric" class="<?= e($input) ?> mt-1 <?= e(error_class('duration_months')) ?>">
                            <?php foreach (field_errors('duration_months') as $message): ?><p class="mt-1 text-xs font-semibold text-rose-600"><?= e((string) $message) ?></p><?php endforeach; ?>
                        </label>

                        <label class="block">
                            <span class="<?= e($labelClass) ?>">Seats</span>
                            <input type="number" name="seats" value="<?= e(old('seats', '1')) ?>" min="1" max="99" step="1" inputmode="numeric" class="<?= e($input) ?> mt-1">
                            <span class="mt-1 block text-xs text-ink-400">Screens / people sharing the account.</span>
                        </label>

                        <label class="block">
                            <span class="<?= e($labelClass) ?>">Quality</span>
                            <input type="text" name="quality" value="<?= e(old('quality')) ?>" maxlength="40" class="<?= e($input) ?> mt-1" placeholder="4K + HDR">
                        </label>

                        <label class="block">
                            <span class="<?= e($labelClass) ?>">Devices</span>
                            <input type="text" name="device" value="<?= e(old('device')) ?>" maxlength="60" class="<?= e($input) ?> mt-1" placeholder="TV, phone, laptop">
                        </label>

                        <label class="block">
                            <span class="<?= e($labelClass) ?>">Price (&#2547;) <span class="text-rose-500">*</span></span>
                            <input type="number" name="price" value="<?= e(old('price')) ?>" required min="0.01" step="0.01" inputmode="decimal" class="<?= e($input) ?> mt-1 <?= e(error_class('price')) ?>" placeholder="250">
                            <?php foreach (field_errors('price') as $message): ?><p class="mt-1 text-xs font-semibold text-rose-600"><?= e((string) $message) ?></p><?php endforeach; ?>
                        </label>

                        <label class="block">
                            <span class="<?= e($labelClass) ?>">Compare-at price (&#2547;)</span>
                            <input type="number" name="compare_at_price" value="<?= e(old('compare_at_price')) ?>" min="0" step="0.01" inputmode="decimal" class="<?= e($input) ?> mt-1 <?= e(error_class('compare_at_price')) ?>" placeholder="optional strike-through">
                            <?php foreach (field_errors('compare_at_price') as $message): ?><p class="mt-1 text-xs font-semibold text-rose-600"><?= e((string) $message) ?></p><?php endforeach; ?>
                            <span class="mt-1 block text-xs text-ink-400">The list price shown struck through; never below the price.</span>
                        </label>

                        <label class="block">
                            <span class="<?= e($labelClass) ?>">Stock</span>
                            <input type="number" name="stock" value="<?= e(old('stock', '-1')) ?>" min="-1" max="9999" step="1" inputmode="numeric" class="<?= e($input) ?> mt-1">
                            <span class="mt-1 block text-xs text-ink-400">-1 means unlimited.</span>
                        </label>

                        <label class="block">
                            <span class="<?= e($labelClass) ?>">Badge</span>
                            <input type="text" name="badge" value="<?= e(old('badge')) ?>" maxlength="40" class="<?= e($input) ?> mt-1" placeholder="Best value">
                        </label>

                        <label class="block">
                            <span class="<?= e($labelClass) ?>">Sort order</span>
                            <input type="number" name="sort_order" value="<?= e(old('sort_order', '0')) ?>" step="1" inputmode="numeric" class="<?= e($input) ?> mt-1">
                            <span class="mt-1 block text-xs text-ink-400">Lower shows first within the service.</span>
                        </label>

                        <label class="block sm:col-span-2 xl:col-span-3">
                            <span class="<?= e($labelClass) ?>">Description</span>
                            <textarea name="description" rows="2" maxlength="500" class="<?= e($input) ?> mt-1 resize-y" placeholder="One line about what this tier includes."><?= e(old('description')) ?></textarea>
                        </label>
                    </div>

                    <div class="flex flex-wrap items-center gap-4 border-t border-dashed border-brand-100 pt-3">
                        <label class="flex cursor-pointer items-center gap-2 text-sm font-bold text-ink">
                            <input type="checkbox" name="is_featured" value="1" <?= e(old('is_featured') === '1' ? 'checked' : '') ?> class="<?= e($box) ?>"> Featured
                        </label>
                        <label class="flex cursor-pointer items-center gap-2 text-sm font-bold text-ink">
                            <input type="checkbox" name="is_active" value="1" checked class="<?= e($box) ?>"> Active
                        </label>
                        <button type="submit" class="ms-auto rounded-brand bg-brand-600 px-5 py-2.5 text-sm font-black text-white shadow-glow transition-colors duration-200 hover:bg-brand-700">Add plan</button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
</section>

<form method="get" action="<?= e(route('admin.plans')) ?>" class="mt-5 flex flex-wrap items-end gap-3 rounded-card border border-brand-100 bg-white p-4 shadow-soft">
    <label class="block min-w-[14rem] flex-1">
        <span class="<?= e($labelClass) ?>">Search</span>
        <input type="search" name="search" value="<?= e($search) ?>" placeholder="Service or plan title" class="<?= e($input) ?> mt-1">
    </label>
    <label class="block min-w-[12rem]">
        <span class="<?= e($labelClass) ?>">Service</span>
        <select name="service_id" class="<?= e($input) ?> mt-1">
            <option value="0">All services</option>
            <?php foreach ($services as $service): ?>
                <?php $sid = (int) ($service['id'] ?? 0); ?>
                <option value="<?= e((string) $sid) ?>" <?= e($filterService === $sid ? 'selected' : '') ?>><?= e((string) ($service['name'] ?? '')) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <label class="block min-w-[10rem]">
        <span class="<?= e($labelClass) ?>">Status</span>
        <select name="active" class="<?= e($input) ?> mt-1">
            <option value="">All</option>
            <option value="1" <?= e($filterActive === '1' ? 'selected' : '') ?>>Active only</option>
            <option value="0" <?= e($filterActive === '0' ? 'selected' : '') ?>>Inactive only</option>
        </select>
    </label>
    <button type="submit" class="rounded-brand bg-ink px-4 py-2.5 text-sm font-black text-white transition-colors duration-200 hover:bg-brand-700">Filter</button>
    <?php if ($search !== '' || $filterService > 0 || $filterActive !== ''): ?>
        <a href="<?= e(route('admin.plans')) ?>" class="rounded-brand border border-brand-200 bg-white px-4 py-2.5 text-sm font-bold text-ink-400 transition-colors duration-200 hover:text-brand-700">Clear</a>
    <?php endif; ?>
</form>

<?php if ($plans === []): ?>
    <div class="mt-5">
        <?= $view->component('empty-state', [
            'title' => ($search !== '' || $filterService > 0 || $filterActive !== '') ? 'No plan matches those filters' : 'No plans yet',
            'text' => ($search !== '' || $filterService > 0 || $filterActive !== '')
                ? 'Loosen the filters above, or clear them to see the whole catalogue.'
                : 'Add a duration tier above and it goes live on the storefront straight away.',
            'action_url' => ($search !== '' || $filterService > 0 || $filterActive !== '') ? route('admin.plans') : null,
            'action_label' => ($search !== '' || $filterService > 0 || $filterActive !== '') ? 'Clear the filters' : null,
            'icon' => ($search !== '' || $filterService > 0 || $filterActive !== '') ? 'search' : 'bag',
        ]) ?>
    </div>
<?php else: ?>
    <div data-accordion class="mt-5 overflow-hidden rounded-card border border-brand-100 bg-white shadow-soft">
        <div class="overflow-x-auto">
            <table class="a-table">
                <caption class="sr-only">Plans with their service, duration, price, stock and status</caption>
                <thead>
                    <tr>
                        <th scope="col">Plan</th>
                        <th scope="col">Duration</th>
                        <th scope="col">Price</th>
                        <th scope="col">Stock</th>
                        <th scope="col">Flags</th>
                        <th scope="col"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($plans as $plan): ?>
                        <?php
                        $pid = (int) ($plan['id'] ?? 0);
                        $psid = (int) ($plan['service_id'] ?? 0);
                        $color = (string) ($plan['brand_color'] ?? '#6366f1');
                        $active = (int) ($plan['is_active'] ?? 0) === 1;
                        $featured = (int) ($plan['is_featured'] ?? 0) === 1;
                        $stock = (int) ($plan['stock'] ?? -1);
                        $price = (float) ($plan['price'] ?? 0);
                        $compare = $plan['compare_at_price'] === null ? null : (float) $plan['compare_at_price'];
                        ?>
                        <tr>
                            <td>
                                <span class="flex items-center gap-3">
                                    <span class="grid h-10 w-10 shrink-0 place-items-center rounded-brand text-sm font-black text-white shadow-soft" style="background-color: <?= e($color) ?>" aria-hidden="true">
                                        <?= e((string) ($plan['logo_mark'] ?? '')) ?>
                                    </span>
                                    <span class="min-w-0">
                                        <span class="block truncate font-black text-ink"><?= e((string) ($plan['title'] ?? '')) ?></span>
                                        <span class="block truncate text-xs text-ink-400"><?= e((string) ($plan['service_name'] ?? '')) ?> &middot; <?= e((string) (int) ($plan['seats'] ?? 1)) ?> seat(s)</span>
                                        <?php if (($plan['badge'] ?? null) !== null && (string) $plan['badge'] !== ''): ?>
                                            <span class="mt-0.5 inline-block rounded-pill bg-accent-100 px-2 py-0.5 text-[10px] font-black uppercase text-accent-800"><?= e((string) $plan['badge']) ?></span>
                                        <?php endif; ?>
                                    </span>
                                </span>
                            </td>
                            <td class="whitespace-nowrap font-bold"><?= e($durationLabel($plan)) ?></td>
                            <td class="whitespace-nowrap">
                                <span class="block font-black text-ink"><?= e(taka($price)) ?></span>
                                <?php if ($compare !== null && $compare > $price): ?>
                                    <span class="block text-xs font-semibold text-ink-400 line-through"><?= e(taka($compare)) ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="whitespace-nowrap">
                                <?php if ($stock < 0): ?>
                                    <span class="text-sm font-bold text-ink-400">Unlimited</span>
                                <?php elseif ($stock === 0): ?>
                                    <span class="rounded-pill bg-rose-100 px-2 py-0.5 text-xs font-black text-rose-800">Out of stock</span>
                                <?php elseif ($stock <= 3): ?>
                                    <span class="rounded-pill bg-amber-100 px-2 py-0.5 text-xs font-black text-amber-800">Low &mdash; <?= e((string) $stock) ?> left</span>
                                <?php else: ?>
                                    <span class="font-black text-ink"><?= e((string) $stock) ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="inline-flex flex-wrap gap-1">
                                    <span class="<?= e($active ? 'rounded-pill bg-emerald-100 px-2 py-0.5 text-xs font-black text-emerald-800' : 'rounded-pill bg-rose-100 px-2 py-0.5 text-xs font-black text-rose-800') ?>"><?= $active ? 'Active' : 'Hidden' ?></span>
                                    <?php if ($featured): ?><span class="rounded-pill bg-accent-100 px-2 py-0.5 text-xs font-black text-accent-800">Featured</span><?php endif; ?>
                                </span>
                            </td>
                            <td class="text-right whitespace-nowrap">
                                <button type="button" data-accordion-btn aria-expanded="true" class="rounded-brand bg-brand-50 px-3 py-1.5 text-xs font-black text-brand-700 transition-colors duration-200 hover:bg-brand-100">Edit / hide</button>
                            </td>
                        </tr>
                        <tr>
                            <td colspan="6" data-accordion-panel class="bg-brand-50/40">
                                <form method="post" action="<?= e(route('admin.plan.update', ['id' => $pid])) ?>" class="space-y-4 py-2">
                                    <?= csrf_field() ?>
                                    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                                        <label class="block">
                                            <span class="<?= e($labelClass) ?>">Service</span>
                                            <select name="service_id" required class="<?= e($input) ?> mt-1">
                                                <?php foreach ($services as $service): ?>
                                                    <?php $sid = (int) ($service['id'] ?? 0); ?>
                                                    <option value="<?= e((string) $sid) ?>" <?= e($psid === $sid ? 'selected' : '') ?>><?= e((string) ($service['name'] ?? '')) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <?php if ($services === []): ?><input type="hidden" name="service_id" value="<?= e((string) $psid) ?>"><?php endif; ?>
                                        </label>

                                        <label class="block sm:col-span-2 xl:col-span-2">
                                            <span class="<?= e($labelClass) ?>">Title</span>
                                            <input type="text" name="title" value="<?= e((string) ($plan['title'] ?? '')) ?>" required maxlength="120" class="<?= e($input) ?> mt-1">
                                        </label>

                                        <label class="block">
                                            <span class="<?= e($labelClass) ?>">Duration (months)</span>
                                            <input type="number" name="duration_months" value="<?= e((string) (int) ($plan['duration_months'] ?? 1)) ?>" required min="1" max="600" step="1" inputmode="numeric" class="<?= e($input) ?> mt-1">
                                        </label>

                                        <label class="block">
                                            <span class="<?= e($labelClass) ?>">Seats</span>
                                            <input type="number" name="seats" value="<?= e((string) (int) ($plan['seats'] ?? 1)) ?>" min="1" max="99" step="1" inputmode="numeric" class="<?= e($input) ?> mt-1">
                                        </label>

                                        <label class="block">
                                            <span class="<?= e($labelClass) ?>">Quality</span>
                                            <input type="text" name="quality" value="<?= e((string) ($plan['quality'] ?? '')) ?>" maxlength="40" class="<?= e($input) ?> mt-1">
                                        </label>

                                        <label class="block">
                                            <span class="<?= e($labelClass) ?>">Devices</span>
                                            <input type="text" name="device" value="<?= e((string) ($plan['device'] ?? '')) ?>" maxlength="60" class="<?= e($input) ?> mt-1">
                                        </label>

                                        <label class="block">
                                            <span class="<?= e($labelClass) ?>">Price (&#2547;)</span>
                                            <input type="number" name="price" value="<?= e(number_format($price, 2, '.', '')) ?>" required min="0.01" step="0.01" inputmode="decimal" class="<?= e($input) ?> mt-1">
                                        </label>

                                        <label class="block">
                                            <span class="<?= e($labelClass) ?>">Compare-at price (&#2547;)</span>
                                            <input type="number" name="compare_at_price" value="<?= e($compare === null ? '' : number_format($compare, 2, '.', '')) ?>" min="0" step="0.01" inputmode="decimal" class="<?= e($input) ?> mt-1">
                                            <span class="mt-1 block text-xs text-ink-400">Blank removes the strike-through.</span>
                                        </label>

                                        <label class="block">
                                            <span class="<?= e($labelClass) ?>">Stock</span>
                                            <input type="number" name="stock" value="<?= e((string) $stock) ?>" min="-1" max="9999" step="1" inputmode="numeric" class="<?= e($input) ?> mt-1">
                                            <span class="mt-1 block text-xs text-ink-400">-1 means unlimited.</span>
                                        </label>

                                        <label class="block">
                                            <span class="<?= e($labelClass) ?>">Badge</span>
                                            <input type="text" name="badge" value="<?= e((string) ($plan['badge'] ?? '')) ?>" maxlength="40" class="<?= e($input) ?> mt-1">
                                        </label>

                                        <label class="block">
                                            <span class="<?= e($labelClass) ?>">Sort order</span>
                                            <input type="number" name="sort_order" value="<?= e((string) (int) ($plan['sort_order'] ?? 0)) ?>" step="1" inputmode="numeric" class="<?= e($input) ?> mt-1">
                                        </label>

                                        <label class="block sm:col-span-2 xl:col-span-3">
                                            <span class="<?= e($labelClass) ?>">Description</span>
                                            <textarea name="description" rows="2" maxlength="500" class="<?= e($input) ?> mt-1 resize-y"><?= e((string) ($plan['description'] ?? '')) ?></textarea>
                                        </label>
                                    </div>

                                    <div class="flex flex-wrap items-center gap-4 border-t border-dashed border-brand-100 pt-3">
                                        <label class="flex cursor-pointer items-center gap-2 text-sm font-bold text-ink">
                                            <input type="checkbox" name="is_featured" value="1" <?= e($featured ? 'checked' : '') ?> class="<?= e($box) ?>"> Featured
                                        </label>
                                        <label class="flex cursor-pointer items-center gap-2 text-sm font-bold text-ink">
                                            <input type="checkbox" name="is_active" value="1" <?= e($active ? 'checked' : '') ?> class="<?= e($box) ?>"> Active
                                        </label>
                                        <button type="submit" class="ms-auto rounded-brand bg-brand-600 px-4 py-2 text-sm font-black text-white transition-colors duration-200 hover:bg-brand-700">Save changes</button>
                                    </div>
                                </form>

                                <form method="post" action="<?= e(route('admin.plan.delete', ['id' => $pid])) ?>" class="mt-3 rounded-brand border border-rose-200 bg-rose-50/70 p-3">
                                    <?= csrf_field() ?>
                                    <div class="flex flex-wrap items-center justify-between gap-3">
                                        <label class="flex cursor-pointer items-start gap-2 text-xs font-semibold text-rose-900">
                                            <input type="checkbox" name="confirm" value="1" required class="<?= e($box) ?> accent-rose-600">
                                            <span>Delete <strong><?= e((string) ($plan['title'] ?? '')) ?></strong> permanently. If it already appears on an order this is refused - deactivate it instead - and this cannot be undone.</span>
                                        </label>
                                        <button type="submit" class="rounded-brand bg-rose-600 px-4 py-2 text-xs font-black text-white transition-colors duration-200 hover:bg-rose-700">Delete plan</button>
                                    </div>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>
