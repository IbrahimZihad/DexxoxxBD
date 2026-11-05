<?php

declare(strict_types=1);

/**
 * Admin services. The read view is a real .a-table; a create form and one edit
 * form per row live in [data-accordion] panels. The panels are NOT hidden in the
 * markup on purpose: every form is a plain <form method="post"> with a CSRF token
 * that stays reachable and submittable with JavaScript switched off.
 *
 * @var App\Core\View $view
 * @var list<array<string, mixed>> $services
 * @var array<int, int> $planCounts
 * @var list<string> $categories
 * @var string $search
 */
$brandName = setting('brand.name', 'ZapSub');

$services = array_values(array_map(static fn (mixed $row): array => (array) $row, (array) ($services ?? [])));
$planCounts = (array) ($planCounts ?? []);
$categories = array_values(array_map('strval', (array) ($categories ?? [])));
$search = trim((string) ($search ?? ''));

$input = 'w-full rounded-brand border border-brand-200 bg-white px-3.5 py-2.5 text-sm text-ink outline-none transition duration-200 placeholder:text-ink-400 focus:border-brand-400 focus:ring-2 focus:ring-brand-300';
$labelClass = 'block text-xs font-black uppercase tracking-[0.12em] text-ink-400';
$box = 'mt-0.5 h-4 w-4 shrink-0 rounded accent-brand-600';

$hexError = field_errors('brand_color');

$view->extend('layouts/admin');
$view->setSection('title', 'Services - ' . $brandName);
$view->setSection('page_title', 'Services');
$view->setSection('page_actions', '<a href="' . e(route('admin.plans')) . '" class="inline-flex items-center gap-2 rounded-brand border border-brand-200 bg-white px-4 py-2 text-sm font-bold text-brand-700 transition-colors duration-200 hover:bg-brand-50">'
    . '<svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path d="M4 7h16v12H4z" stroke-linejoin="round"/><path d="M9 7V4h6v3" stroke-linejoin="round"/></svg>'
    . 'Manage plans</a>');
?>

<section aria-labelledby="services-add" class="rounded-card border border-brand-100 bg-white p-4 shadow-soft sm:p-5">
    <div data-accordion>
        <button type="button" data-accordion-btn aria-expanded="true" class="flex w-full items-center justify-between gap-3 text-left">
            <span>
                <span id="services-add" class="text-base font-black tracking-tight text-ink">Add a service</span>
                <span class="mt-0.5 block text-xs text-ink-400">A brand you can sell plans for - Netflix, Spotify, ChatGPT&hellip;</span>
            </span>
            <span class="rounded-brand bg-brand-600 px-3 py-1.5 text-xs font-black text-white">Open form</span>
        </button>

        <div data-accordion-panel class="mt-4">
            <form method="post" action="<?= e(route('admin.service.store')) ?>" class="space-y-4">
                <?= csrf_field() ?>
                <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                    <label class="block sm:col-span-2 xl:col-span-1">
                        <span class="<?= e($labelClass) ?>">Name <span class="text-rose-500">*</span></span>
                        <input type="text" name="name" value="<?= e(old('name')) ?>" required maxlength="80" class="<?= e($input) ?> mt-1" placeholder="Netflix Premium">
                        <?php foreach (field_errors('name') as $message): ?><p class="mt-1 text-xs font-semibold text-rose-600"><?= e((string) $message) ?></p><?php endforeach; ?>
                    </label>

                    <label class="block">
                        <span class="<?= e($labelClass) ?>">Slug</span>
                        <input type="text" name="slug" value="<?= e(old('slug')) ?>" maxlength="80" class="<?= e($input) ?> mt-1" placeholder="auto from name">
                        <?php foreach (field_errors('slug') as $message): ?><p class="mt-1 text-xs font-semibold text-rose-600"><?= e((string) $message) ?></p><?php endforeach; ?>
                        <span class="mt-1 block text-xs text-ink-400">Lowercase, numbers and dashes. Left blank it is built from the name.</span>
                    </label>

                    <label class="block">
                        <span class="<?= e($labelClass) ?>">Category <span class="text-rose-500">*</span></span>
                        <input type="text" name="category" value="<?= e(old('category')) ?>" required maxlength="60" list="service-categories" class="<?= e($input) ?> mt-1" placeholder="Streaming">
                        <?php foreach (field_errors('category') as $message): ?><p class="mt-1 text-xs font-semibold text-rose-600"><?= e((string) $message) ?></p><?php endforeach; ?>
                    </label>

                    <label class="block">
                        <span class="<?= e($labelClass) ?>">Brand colour</span>
                        <input type="text" name="brand_color" value="<?= e(old('brand_color', '#6366f1')) ?>" maxlength="20" class="<?= e($input) ?> mt-1 <?= e($hexError !== [] ? 'border-rose-300 ring-2 ring-rose-400' : '') ?>" placeholder="#6366f1">
                        <?php foreach ($hexError as $message): ?><p class="mt-1 text-xs font-semibold text-rose-600"><?= e((string) $message) ?></p><?php endforeach; ?>
                    </label>

                    <label class="block">
                        <span class="<?= e($labelClass) ?>">Logo mark</span>
                        <input type="text" name="logo_mark" value="<?= e(old('logo_mark', 'Z')) ?>" maxlength="6" class="<?= e($input) ?> mt-1" placeholder="N">
                        <span class="mt-1 block text-xs text-ink-400">One or two characters shown in the colour swatch.</span>
                    </label>

                    <label class="block">
                        <span class="<?= e($labelClass) ?>">Popularity</span>
                        <input type="number" name="popular" value="<?= e(old('popular', '0')) ?>" min="0" max="127" step="1" inputmode="numeric" class="<?= e($input) ?> mt-1">
                        <span class="mt-1 block text-xs text-ink-400">Higher sorts first on the home grid.</span>
                    </label>

                    <label class="block sm:col-span-2 xl:col-span-3">
                        <span class="<?= e($labelClass) ?>">Tagline</span>
                        <input type="text" name="tagline" value="<?= e(old('tagline')) ?>" maxlength="160" class="<?= e($input) ?> mt-1" placeholder="4K Ultra HD with HDR, two screens at once.">
                    </label>

                    <label class="block sm:col-span-2 xl:col-span-3">
                        <span class="<?= e($labelClass) ?>">Description</span>
                        <textarea name="description" rows="3" maxlength="20000" class="<?= e($input) ?> mt-1 resize-y" placeholder="What the customer gets."><?= e(old('description')) ?></textarea>
                    </label>
                </div>

                <datalist id="service-categories"><?php foreach ($categories as $category): ?><option value="<?= e($category) ?>"></option><?php endforeach; ?></datalist>

                <div class="flex flex-wrap items-center gap-4 border-t border-dashed border-brand-100 pt-3">
                    <label class="flex cursor-pointer items-center gap-2 text-sm font-bold text-ink">
                        <input type="checkbox" name="show_in_home" value="1" checked class="<?= e($box) ?>"> Show on home
                    </label>
                    <label class="flex cursor-pointer items-center gap-2 text-sm font-bold text-ink">
                        <input type="checkbox" name="is_active" value="1" checked class="<?= e($box) ?>"> Active
                    </label>
                    <button type="submit" class="ms-auto rounded-brand bg-brand-600 px-5 py-2.5 text-sm font-black text-white shadow-glow transition-colors duration-200 hover:bg-brand-700">Add service</button>
                </div>
            </form>
        </div>
    </div>
</section>

<form method="get" action="<?= e(route('admin.services')) ?>" class="mt-5 flex flex-wrap items-end gap-3 rounded-card border border-brand-100 bg-white p-4 shadow-soft">
    <label class="block min-w-[16rem] flex-1">
        <span class="<?= e($labelClass) ?>">Search</span>
        <input type="search" name="search" value="<?= e($search) ?>" placeholder="Name, category, tagline" class="<?= e($input) ?> mt-1">
    </label>
    <button type="submit" class="rounded-brand bg-ink px-4 py-2.5 text-sm font-black text-white transition-colors duration-200 hover:bg-brand-700">Filter</button>
    <?php if ($search !== ''): ?>
        <a href="<?= e(route('admin.services')) ?>" class="rounded-brand border border-brand-200 bg-white px-4 py-2.5 text-sm font-bold text-ink-400 transition-colors duration-200 hover:text-brand-700">Clear</a>
    <?php endif; ?>
</form>

<?php if ($services === []): ?>
    <div class="mt-5">
        <?= $view->component('empty-state', [
            'title' => $search !== '' ? 'No service matches that search' : 'No services yet',
            'text' => $search !== ''
                ? 'Widen the word above, or clear it to see the whole catalogue.'
                : 'Add a brand above and its plans can start selling within the hour.',
            'action_url' => $search !== '' ? route('admin.services') : null,
            'action_label' => $search !== '' ? 'Clear the search' : null,
            'icon' => $search !== '' ? 'search' : 'bag',
        ]) ?>
    </div>
<?php else: ?>
    <div data-accordion class="mt-5 overflow-hidden rounded-card border border-brand-100 bg-white shadow-soft">
        <div class="overflow-x-auto">
            <table class="a-table">
                <caption class="sr-only">Services with their brand colour, category, plan count and status</caption>
                <thead>
                    <tr>
                        <th scope="col">Brand</th>
                        <th scope="col">Category</th>
                        <th scope="col">Plans</th>
                        <th scope="col">Popularity</th>
                        <th scope="col">Status</th>
                        <th scope="col"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($services as $service): ?>
                        <?php
                        $sid = (int) ($service['id'] ?? 0);
                        $color = (string) ($service['brand_color'] ?? '#6366f1');
                        $active = (int) ($service['is_active'] ?? 0) === 1;
                        $inHome = (int) ($service['show_in_home'] ?? 0) === 1;
                        $count = (int) ($planCounts[$sid] ?? 0);
                        ?>
                        <tr>
                            <td>
                                <span class="flex items-center gap-3">
                                    <span class="grid h-10 w-10 shrink-0 place-items-center rounded-brand text-sm font-black text-white shadow-soft" style="background-color: <?= e($color) ?>" aria-hidden="true">
                                        <?= e((string) ($service['logo_mark'] ?? '')) ?>
                                    </span>
                                    <span class="min-w-0">
                                        <span class="block truncate font-black text-ink"><?= e((string) ($service['name'] ?? '')) ?></span>
                                        <span class="block truncate font-mono text-xs text-ink-400">/<?= e((string) ($service['slug'] ?? '')) ?></span>
                                    </span>
                                </span>
                            </td>
                            <td><span class="rounded-pill bg-brand-50 px-2.5 py-1 text-xs font-bold text-brand-800"><?= e((string) ($service['category'] ?? '')) ?></span></td>
                            <td class="font-black <?= e($count > 0 ? 'text-ink' : 'text-ink-400') ?>"><?= e((string) $count) ?></td>
                            <td class="font-bold"><?= e((string) (int) ($service['popular'] ?? 0)) ?></td>
                            <td>
                                <span class="inline-flex flex-wrap gap-1">
                                    <span class="<?= e($active ? 'rounded-pill bg-emerald-100 px-2 py-0.5 text-xs font-black text-emerald-800' : 'rounded-pill bg-rose-100 px-2 py-0.5 text-xs font-black text-rose-800') ?>"><?= $active ? 'Active' : 'Hidden' ?></span>
                                    <?php if ($inHome): ?><span class="rounded-pill bg-accent-100 px-2 py-0.5 text-xs font-black text-accent-800">Home</span><?php endif; ?>
                                </span>
                            </td>
                            <td class="text-right whitespace-nowrap">
                                <button type="button" data-accordion-btn aria-expanded="true" class="rounded-brand bg-brand-50 px-3 py-1.5 text-xs font-black text-brand-700 transition-colors duration-200 hover:bg-brand-100">Edit / hide</button>
                            </td>
                        </tr>
                        <tr>
                            <td colspan="6" data-accordion-panel class="bg-brand-50/40">
                                <form method="post" action="<?= e(route('admin.service.update', ['id' => $sid])) ?>" class="space-y-4 py-2">
                                    <?= csrf_field() ?>
                                    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                                        <label class="block sm:col-span-2 xl:col-span-1">
                                            <span class="<?= e($labelClass) ?>">Name</span>
                                            <input type="text" name="name" value="<?= e((string) ($service['name'] ?? '')) ?>" required maxlength="80" class="<?= e($input) ?> mt-1">
                                        </label>
                                        <label class="block">
                                            <span class="<?= e($labelClass) ?>">Slug</span>
                                            <input type="text" name="slug" value="<?= e((string) ($service['slug'] ?? '')) ?>" maxlength="80" class="<?= e($input) ?> mt-1">
                                            <span class="mt-1 block text-xs text-ink-400">Blank keeps the current slug.</span>
                                        </label>
                                        <label class="block">
                                            <span class="<?= e($labelClass) ?>">Category</span>
                                            <input type="text" name="category" value="<?= e((string) ($service['category'] ?? '')) ?>" required maxlength="60" class="<?= e($input) ?> mt-1">
                                        </label>
                                        <label class="block">
                                            <span class="<?= e($labelClass) ?>">Brand colour</span>
                                            <input type="text" name="brand_color" value="<?= e((string) ($service['brand_color'] ?? '')) ?>" maxlength="20" class="<?= e($input) ?> mt-1">
                                        </label>
                                        <label class="block">
                                            <span class="<?= e($labelClass) ?>">Logo mark</span>
                                            <input type="text" name="logo_mark" value="<?= e((string) ($service['logo_mark'] ?? '')) ?>" maxlength="6" class="<?= e($input) ?> mt-1">
                                        </label>
                                        <label class="block">
                                            <span class="<?= e($labelClass) ?>">Popularity</span>
                                            <input type="number" name="popular" value="<?= e((string) (int) ($service['popular'] ?? 0)) ?>" min="0" max="127" step="1" inputmode="numeric" class="<?= e($input) ?> mt-1">
                                        </label>
                                        <label class="block sm:col-span-2 xl:col-span-3">
                                            <span class="<?= e($labelClass) ?>">Tagline</span>
                                            <input type="text" name="tagline" value="<?= e((string) ($service['tagline'] ?? '')) ?>" maxlength="160" class="<?= e($input) ?> mt-1">
                                        </label>
                                        <label class="block sm:col-span-2 xl:col-span-3">
                                            <span class="<?= e($labelClass) ?>">Description</span>
                                            <textarea name="description" rows="3" maxlength="20000" class="<?= e($input) ?> mt-1 resize-y"><?= e((string) ($service['description'] ?? '')) ?></textarea>
                                        </label>
                                    </div>

                                    <div class="flex flex-wrap items-center gap-4 border-t border-dashed border-brand-100 pt-3">
                                        <label class="flex cursor-pointer items-center gap-2 text-sm font-bold text-ink">
                                            <input type="checkbox" name="show_in_home" value="1" <?= e($inHome ? 'checked' : '') ?> class="<?= e($box) ?>"> Show on home
                                        </label>
                                        <label class="flex cursor-pointer items-center gap-2 text-sm font-bold text-ink">
                                            <input type="checkbox" name="is_active" value="1" <?= e($active ? 'checked' : '') ?> class="<?= e($box) ?>"> Active
                                        </label>
                                        <button type="submit" class="ms-auto rounded-brand bg-brand-600 px-4 py-2 text-sm font-black text-white transition-colors duration-200 hover:bg-brand-700">Save changes</button>
                                    </div>
                                </form>

                                <?php if ($count > 0): ?>
                                    <p class="mt-2 rounded-brand bg-amber-50 px-3 py-2 text-xs font-semibold text-amber-800">
                                        This service has <?= e((string) $count) ?> plan(s). It cannot be deleted while they exist - set it to inactive instead.
                                    </p>
                                <?php else: ?>
                                    <form method="post" action="<?= e(route('admin.service.delete', ['id' => $sid])) ?>" class="mt-3 rounded-brand border border-rose-200 bg-rose-50/70 p-3">
                                        <?= csrf_field() ?>
                                        <div class="flex flex-wrap items-center justify-between gap-3">
                                            <label class="flex cursor-pointer items-start gap-2 text-xs font-semibold text-rose-900">
                                                <input type="checkbox" name="confirm" value="1" required class="<?= e($box) ?> accent-rose-600">
                                                <span>Delete <strong><?= e((string) ($service['name'] ?? '')) ?></strong> permanently. It has no plans now, and this cannot be undone.</span>
                                            </label>
                                            <button type="submit" class="rounded-brand bg-rose-600 px-4 py-2 text-xs font-black text-white transition-colors duration-200 hover:bg-rose-700">Delete service</button>
                                        </div>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>
