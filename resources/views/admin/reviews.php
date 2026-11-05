<?php

declare(strict_types=1);

/**
 * Testimonial (social-proof) manager. A create form lives in the first
 * [data-accordion] group; each row carries one [data-accordion-btn] and one
 * [data-accordion-panel] in table order (the shipped accordion pairs them by
 * index and opens one at a time). Panels are left visible in the markup so every
 * form is reachable and submittable with JavaScript off.
 *
 * @var App\Core\View $view
 * @var list<array<string, mixed>> $testimonials
 */
$brandName = setting('brand.name', 'DexxozzBD');

$testimonials = array_values(array_map(static fn (mixed $row): array => (array) $row, (array) ($testimonials ?? [])));

$input = 'w-full rounded-brand border border-brand-200 bg-white px-3.5 py-2.5 text-sm text-ink outline-none transition duration-200 placeholder:text-ink-400 focus:border-brand-400 focus:ring-2 focus:ring-brand-300';
$labelClass = 'block text-xs font-black uppercase tracking-[0.12em] text-ink-400';
$box = 'mt-0.5 h-4 w-4 shrink-0 rounded accent-brand-600';

/** Rating as five star glyphs; the count comes from a validated 1-5 column. */
$stars = static function (int $rating): string {
    $rating = max(0, min(5, $rating));

    return '<span class="inline-flex gap-0.5 text-sm leading-none" role="img" aria-label="' . e((string) $rating) . ' out of 5">'
        . str_repeat('<span class="text-amber-400">&#9733;</span>', $rating)
        . str_repeat('<span class="text-ink-300">&#9734;</span>', 5 - $rating)
        . '</span>';
};

$view->extend('layouts/admin');
$view->setSection('title', 'Reviews - ' . $brandName);
$view->setSection('page_title', 'Reviews');
$view->setSection('page_actions', '<a href="' . e(route('admin.faqs')) . '" class="inline-flex items-center gap-2 rounded-brand border border-brand-200 bg-white px-4 py-2 text-sm font-bold text-brand-700 transition-colors duration-200 hover:bg-brand-50">'
    . '<svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M9.5 9.5A2.5 2.5 0 1 1 12 13v1.5" stroke-linecap="round"/></svg>'
    . 'Manage FAQs</a>');
?>

<section aria-labelledby="review-add" class="rounded-card border border-brand-100 bg-white p-4 shadow-soft sm:p-5">
    <div data-accordion>
        <button type="button" data-accordion-btn aria-expanded="true" class="flex w-full items-center justify-between gap-3 text-left">
            <span>
                <span id="review-add" class="text-base font-black tracking-tight text-ink">Add a review</span>
                <span class="mt-0.5 block text-xs text-ink-400">What a happy customer said - it shows in the storefront trust section.</span>
            </span>
            <span class="rounded-brand bg-brand-600 px-3 py-1.5 text-xs font-black text-white">Open form</span>
        </button>

        <div data-accordion-panel class="mt-4">
            <form method="post" action="<?= e(route('admin.testimonial.store')) ?>" class="space-y-4">
                <?= csrf_field() ?>
                <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                    <label class="block">
                        <span class="<?= e($labelClass) ?>">Name <span class="text-rose-500">*</span></span>
                        <input type="text" name="name" value="<?= e(old('name')) ?>" required maxlength="80" class="<?= e($input) ?> mt-1" placeholder="Tanvir A.">
                        <?php foreach (field_errors('name') as $m): ?><p class="mt-1 text-xs font-semibold text-rose-600"><?= e((string) $m) ?></p><?php endforeach; ?>
                    </label>

                    <label class="block">
                        <span class="<?= e($labelClass) ?>">Location</span>
                        <input type="text" name="location" value="<?= e(old('location')) ?>" maxlength="60" class="<?= e($input) ?> mt-1" placeholder="Dhaka">
                    </label>

                    <label class="block">
                        <span class="<?= e($labelClass) ?>">Service</span>
                        <input type="text" name="service_label" value="<?= e(old('service_label')) ?>" maxlength="60" class="<?= e($input) ?> mt-1" placeholder="Netflix Premium">
                    </label>

                    <label class="block">
                        <span class="<?= e($labelClass) ?>">Rating <span class="text-rose-500">*</span></span>
                        <select name="rating" required class="<?= e($input) ?> mt-1">
                            <?php $currentRating = (int) old('rating', '5'); ?>
                            <?php for ($star = 5; $star >= 1; $star--): ?>
                                <option value="<?= e((string) $star) ?>" <?= e($star === $currentRating ? 'selected' : '') ?>><?= e((string) $star) ?> star<?= $star === 1 ? '' : 's' ?></option>
                            <?php endfor; ?>
                        </select>
                        <?php foreach (field_errors('rating') as $m): ?><p class="mt-1 text-xs font-semibold text-rose-600"><?= e((string) $m) ?></p><?php endforeach; ?>
                    </label>

                    <label class="block">
                        <span class="<?= e($labelClass) ?>">Sort order</span>
                        <input type="number" name="sort_order" value="<?= e(old('sort_order', '0')) ?>" step="1" class="<?= e($input) ?> mt-1">
                        <span class="mt-1 block text-xs text-ink-400">Lower shows first.</span>
                    </label>

                    <label class="block sm:col-span-2 xl:col-span-3">
                        <span class="<?= e($labelClass) ?>">Quote <span class="text-rose-500">*</span></span>
                        <textarea name="quote" rows="3" required maxlength="400" class="<?= e($input) ?> mt-1 resize-y" placeholder="Paid at 9:40 PM, login was in my WhatsApp by 9:52."><?= e(old('quote')) ?></textarea>
                        <?php foreach (field_errors('quote') as $m): ?><p class="mt-1 text-xs font-semibold text-rose-600"><?= e((string) $m) ?></p><?php endforeach; ?>
                        <span class="mt-1 block text-xs text-ink-400">400 characters or fewer.</span>
                    </label>
                </div>

                <div class="flex flex-wrap items-center gap-4 border-t border-dashed border-brand-100 pt-3">
                    <input type="hidden" name="is_active" value="0">
                    <label class="flex cursor-pointer items-center gap-2 text-sm font-bold text-ink">
                        <input type="checkbox" name="is_active" value="1" checked class="<?= e($box) ?>"> Show on the storefront
                    </label>
                    <button type="submit" class="ms-auto rounded-brand bg-brand-600 px-5 py-2.5 text-sm font-black text-white shadow-glow transition-colors duration-200 hover:bg-brand-700">Add review</button>
                </div>
            </form>
        </div>
    </div>
</section>

<?php if ($testimonials === []): ?>
    <div class="mt-5">
        <?= $view->component('empty-state', [
            'title' => 'No reviews yet',
            'text' => 'Add the first one above and it can appear in the storefront trust strip right away.',
            'icon' => 'check',
        ]) ?>
    </div>
<?php else: ?>
    <div data-accordion class="mt-5 overflow-hidden rounded-card border border-brand-100 bg-white shadow-soft">
        <div class="border-b border-brand-100 px-4 py-3 text-xs font-bold text-ink-400"><?= e((string) count($testimonials)) ?> review(s) on file</div>

        <div class="overflow-x-auto">
            <table class="a-table">
                <caption class="sr-only">Testimonials with their rating, service and visibility</caption>
                <thead>
                    <tr>
                        <th scope="col">Customer</th>
                        <th scope="col">Service</th>
                        <th scope="col">Rating</th>
                        <th scope="col">Quote</th>
                        <th scope="col">Status</th>
                        <th scope="col"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($testimonials as $review): ?>
                        <?php
                        $rid = (int) ($review['id'] ?? 0);
                        $rating = (int) ($review['rating'] ?? 0);
                        $active = (int) ($review['is_active'] ?? 0) === 1;
                        ?>
                        <tr>
                            <td>
                                <span class="block font-black text-ink"><?= e((string) ($review['name'] ?? '')) ?></span>
                                <span class="block text-xs text-ink-400"><?= e((string) ($review['location'] ?? '')) ?></span>
                            </td>
                            <td><span class="rounded-pill bg-brand-50 px-2.5 py-1 text-xs font-bold text-brand-800"><?= e((string) ($review['service_label'] ?? '')) ?></span></td>
                            <td class="whitespace-nowrap"><?= $stars($rating) ?></td>
                            <td class="max-w-[26rem]"><span class="block text-sm text-ink-700"><?= e(short_text((string) ($review['quote'] ?? ''), 110)) ?></span></td>
                            <td><span class="<?= e($active ? 'rounded-pill bg-emerald-100 px-2 py-0.5 text-xs font-black text-emerald-800' : 'rounded-pill bg-rose-100 px-2 py-0.5 text-xs font-black text-rose-800') ?>"><?= $active ? 'Shown' : 'Hidden' ?></span></td>
                            <td class="text-right whitespace-nowrap">
                                <button type="button" data-accordion-btn aria-expanded="true" class="rounded-brand bg-brand-50 px-3 py-1.5 text-xs font-black text-brand-700 transition-colors duration-200 hover:bg-brand-100">Edit / hide</button>
                            </td>
                        </tr>
                        <tr>
                            <td colspan="6" data-accordion-panel class="bg-brand-50/40">
                                <form method="post" action="<?= e(route('admin.testimonial.update', ['id' => $rid])) ?>" class="space-y-4 py-2">
                                    <?= csrf_field() ?>
                                    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                                        <label class="block">
                                            <span class="<?= e($labelClass) ?>">Name</span>
                                            <input type="text" name="name" value="<?= e((string) ($review['name'] ?? '')) ?>" required maxlength="80" class="<?= e($input) ?> mt-1">
                                        </label>
                                        <label class="block">
                                            <span class="<?= e($labelClass) ?>">Location</span>
                                            <input type="text" name="location" value="<?= e((string) ($review['location'] ?? '')) ?>" maxlength="60" class="<?= e($input) ?> mt-1">
                                        </label>
                                        <label class="block">
                                            <span class="<?= e($labelClass) ?>">Service</span>
                                            <input type="text" name="service_label" value="<?= e((string) ($review['service_label'] ?? '')) ?>" maxlength="60" class="<?= e($input) ?> mt-1">
                                        </label>
                                        <label class="block">
                                            <span class="<?= e($labelClass) ?>">Rating</span>
                                            <select name="rating" required class="<?= e($input) ?> mt-1">
                                                <?php for ($star = 5; $star >= 1; $star--): ?>
                                                    <option value="<?= e((string) $star) ?>" <?= e($star === $rating ? 'selected' : '') ?>><?= e((string) $star) ?> star<?= $star === 1 ? '' : 's' ?></option>
                                                <?php endfor; ?>
                                            </select>
                                        </label>
                                        <label class="block">
                                            <span class="<?= e($labelClass) ?>">Sort order</span>
                                            <input type="number" name="sort_order" value="<?= e((string) (int) ($review['sort_order'] ?? 0)) ?>" step="1" class="<?= e($input) ?> mt-1">
                                        </label>
                                        <label class="block sm:col-span-2 xl:col-span-3">
                                            <span class="<?= e($labelClass) ?>">Quote</span>
                                            <textarea name="quote" rows="3" required maxlength="400" class="<?= e($input) ?> mt-1 resize-y"><?= e((string) ($review['quote'] ?? '')) ?></textarea>
                                        </label>
                                    </div>

                                    <div class="flex flex-wrap items-center gap-4 border-t border-dashed border-brand-100 pt-3">
                                        <input type="hidden" name="is_active" value="0">
                                        <label class="flex cursor-pointer items-center gap-2 text-sm font-bold text-ink">
                                            <input type="checkbox" name="is_active" value="1" <?= e($active ? 'checked' : '') ?> class="<?= e($box) ?>"> Show on the storefront
                                        </label>
                                        <button type="submit" class="ms-auto rounded-brand bg-brand-600 px-4 py-2 text-sm font-black text-white transition-colors duration-200 hover:bg-brand-700">Save changes</button>
                                    </div>
                                </form>

                                <form method="post" action="<?= e(route('admin.testimonial.delete', ['id' => $rid])) ?>" class="mt-3 rounded-brand border border-rose-200 bg-rose-50/70 p-3">
                                    <?= csrf_field() ?>
                                    <div class="flex flex-wrap items-center justify-between gap-3">
                                        <label class="flex cursor-pointer items-start gap-2 text-xs font-semibold text-rose-900">
                                            <input type="checkbox" name="confirm" value="1" required class="<?= e($box) ?> accent-rose-600">
                                            <span>Delete this review from <strong><?= e((string) ($review['name'] ?? '')) ?></strong> permanently. This cannot be undone.</span>
                                        </label>
                                        <button type="submit" class="rounded-brand bg-rose-600 px-4 py-2 text-xs font-black text-white transition-colors duration-200 hover:bg-rose-700">Delete review</button>
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
