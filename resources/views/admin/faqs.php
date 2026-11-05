<?php

declare(strict_types=1);

/**
 * FAQ manager. The table is grouped by category (each category opens its own
 * <tbody> header row), and every row carries one [data-accordion-btn] and one
 * [data-accordion-panel] in document order inside a single [data-accordion]
 * group - the shipped accordion pairs buttons to panels by position and opens one
 * at a time. A create form lives in its own accordion section above. Panels are
 * left visible in the markup so each form works with JavaScript off.
 *
 * @var App\Core\View $view
 * @var list<array<string, mixed>> $faqs
 * @var list<string> $categories
 */
$brandName = setting('brand.name', 'DexxozzBD');

$faqs = array_values(array_map(static fn (mixed $row): array => (array) $row, (array) ($faqs ?? [])));
$categories = array_values(array_map('strval', (array) ($categories ?? [])));

$input = 'w-full rounded-brand border border-brand-200 bg-white px-3.5 py-2.5 text-sm text-ink outline-none transition duration-200 placeholder:text-ink-400 focus:border-brand-400 focus:ring-2 focus:ring-brand-300';
$labelClass = 'block text-xs font-black uppercase tracking-[0.12em] text-ink-400';
$box = 'mt-0.5 h-4 w-4 shrink-0 rounded accent-brand-600';

/** Group the flat, sort-ordered list into category => rows without losing order. */
$grouped = [];
foreach ($faqs as $faq) {
    $group = trim((string) ($faq['category'] ?? 'general'));
    $group = $group === '' ? 'general' : $group;
    $grouped[$group][] = $faq;
}

$view->extend('layouts/admin');
$view->setSection('title', 'FAQs - ' . $brandName);
$view->setSection('page_title', 'FAQs');
$view->setSection('page_actions', '<a href="' . e(route('home')) . '" target="_blank" rel="noopener" class="inline-flex items-center gap-2 rounded-brand border border-brand-200 bg-white px-4 py-2 text-sm font-bold text-brand-700 transition-colors duration-200 hover:bg-brand-50">'
    . '<svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path d="M14 4h6v6M20 4l-9 9" stroke-linecap="round" stroke-linejoin="round"/><path d="M18 14v5a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1V8a1 1 0 0 1 1-1h5" stroke-linecap="round" stroke-linejoin="round"/></svg>'
    . 'See on the shop</a>');
?>

<section aria-labelledby="faq-add" class="rounded-card border border-brand-100 bg-white p-4 shadow-soft sm:p-5">
    <div data-accordion>
        <button type="button" data-accordion-btn aria-expanded="true" class="flex w-full items-center justify-between gap-3 text-left">
            <span>
                <span id="faq-add" class="text-base font-black tracking-tight text-ink">Add a question</span>
                <span class="mt-0.5 block text-xs text-ink-400">Answers customers ask before they buy - it feeds the storefront FAQ page.</span>
            </span>
            <span class="rounded-brand bg-brand-600 px-3 py-1.5 text-xs font-black text-white">Open form</span>
        </button>

        <div data-accordion-panel class="mt-4">
            <form method="post" action="<?= e(route('admin.faq.store')) ?>" class="space-y-4">
                <?= csrf_field() ?>
                <div class="grid gap-3 sm:grid-cols-2">
                    <label class="block sm:col-span-2">
                        <span class="<?= e($labelClass) ?>">Question <span class="text-rose-500">*</span></span>
                        <input type="text" name="question" value="<?= e(old('question')) ?>" required maxlength="200" class="<?= e($input) ?> mt-1" placeholder="How do I pay?">
                        <?php foreach (field_errors('question') as $m): ?><p class="mt-1 text-xs font-semibold text-rose-600"><?= e((string) $m) ?></p><?php endforeach; ?>
                    </label>

                    <label class="block sm:col-span-2">
                        <span class="<?= e($labelClass) ?>">Answer <span class="text-rose-500">*</span></span>
                        <textarea name="answer" rows="3" required maxlength="1000" class="<?= e($input) ?> mt-1 resize-y" placeholder="bKash, Nagad, Rocket or card. Paste the transaction ID and a person verifies it."><?= e(old('answer')) ?></textarea>
                        <?php foreach (field_errors('answer') as $m): ?><p class="mt-1 text-xs font-semibold text-rose-600"><?= e((string) $m) ?></p><?php endforeach; ?>
                        <span class="mt-1 block text-xs text-ink-400">1000 characters or fewer.</span>
                    </label>

                    <label class="block">
                        <span class="<?= e($labelClass) ?>">Category</span>
                        <input type="text" name="category" value="<?= e(old('category')) ?>" maxlength="40" list="faq-categories" class="<?= e($input) ?> mt-1" placeholder="general">
                        <span class="mt-1 block text-xs text-ink-400">Groups the question on the page. Blank means "general".</span>
                    </label>

                    <label class="block">
                        <span class="<?= e($labelClass) ?>">Sort order</span>
                        <input type="number" name="sort_order" value="<?= e(old('sort_order', '0')) ?>" step="1" class="<?= e($input) ?> mt-1">
                        <span class="mt-1 block text-xs text-ink-400">Lower shows first within its category.</span>
                    </label>
                </div>

                <datalist id="faq-categories"><?php foreach ($categories as $category): ?><option value="<?= e($category) ?>"></option><?php endforeach; ?></datalist>

                <div class="flex flex-wrap items-center gap-4 border-t border-dashed border-brand-100 pt-3">
                    <input type="hidden" name="is_active" value="0">
                    <label class="flex cursor-pointer items-center gap-2 text-sm font-bold text-ink">
                        <input type="checkbox" name="is_active" value="1" checked class="<?= e($box) ?>"> Publish on the FAQ page
                    </label>
                    <button type="submit" class="ms-auto rounded-brand bg-brand-600 px-5 py-2.5 text-sm font-black text-white shadow-glow transition-colors duration-200 hover:bg-brand-700">Add question</button>
                </div>
            </form>
        </div>
    </div>
</section>

<?php if ($faqs === []): ?>
    <div class="mt-5">
        <?= $view->component('empty-state', [
            'title' => 'No FAQs yet',
            'text' => 'Add the questions customers ask most and the answer page fills itself.',
            'icon' => 'check',
        ]) ?>
    </div>
<?php else: ?>
    <div data-accordion class="mt-5 overflow-hidden rounded-card border border-brand-100 bg-white shadow-soft">
        <div class="border-b border-brand-100 px-4 py-3 text-xs font-bold text-ink-400"><?= e((string) count($faqs)) ?> question(s) in <?= e((string) count($grouped)) ?> category(ies)</div>

        <div class="overflow-x-auto">
            <table class="a-table">
                <caption class="sr-only">Frequently asked questions grouped by category</caption>
                <thead>
                    <tr>
                        <th scope="col">Question</th>
                        <th scope="col">Answer</th>
                        <th scope="col">Order</th>
                        <th scope="col">Status</th>
                        <th scope="col"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <?php foreach ($grouped as $group => $items): ?>
                    <tbody>
                        <tr>
                            <td colspan="5" class="bg-brand-50/70 px-4 py-2 text-xs font-black uppercase tracking-[0.14em] text-brand-700">
                                <a href="<?= e(route('faq')) ?>?category=<?= e(rawurlencode($group)) ?>" class="hover:underline"><?= e(status_label($group)) ?></a>
                                <span class="ms-2 font-bold text-ink-400">(<?= e((string) count($items)) ?>)</span>
                            </td>
                        </tr>
                        <?php foreach ($items as $faq): ?>
                            <?php
                            $fid = (int) ($faq['id'] ?? 0);
                            $active = (int) ($faq['is_active'] ?? 0) === 1;
                            ?>
                            <tr>
                                <td class="max-w-[22rem]"><span class="block font-bold text-ink"><?= e((string) ($faq['question'] ?? '')) ?></span></td>
                                <td class="max-w-[30rem]"><span class="block text-sm text-ink-700"><?= e(short_text((string) ($faq['answer'] ?? ''), 120)) ?></span></td>
                                <td class="whitespace-nowrap font-bold"><?= e((string) (int) ($faq['sort_order'] ?? 0)) ?></td>
                                <td><span class="<?= e($active ? 'rounded-pill bg-emerald-100 px-2 py-0.5 text-xs font-black text-emerald-800' : 'rounded-pill bg-rose-100 px-2 py-0.5 text-xs font-black text-rose-800') ?>"><?= $active ? 'Live' : 'Draft' ?></span></td>
                                <td class="text-right whitespace-nowrap">
                                    <button type="button" data-accordion-btn aria-expanded="true" class="rounded-brand bg-brand-50 px-3 py-1.5 text-xs font-black text-brand-700 transition-colors duration-200 hover:bg-brand-100">Edit / hide</button>
                                </td>
                            </tr>
                            <tr>
                                <td colspan="5" data-accordion-panel class="bg-brand-50/40">
                                    <form method="post" action="<?= e(route('admin.faq.update', ['id' => $fid])) ?>" class="space-y-4 py-2">
                                        <?= csrf_field() ?>
                                        <div class="grid gap-3 sm:grid-cols-2">
                                            <label class="block sm:col-span-2">
                                                <span class="<?= e($labelClass) ?>">Question</span>
                                                <input type="text" name="question" value="<?= e((string) ($faq['question'] ?? '')) ?>" required maxlength="200" class="<?= e($input) ?> mt-1">
                                            </label>
                                            <label class="block sm:col-span-2">
                                                <span class="<?= e($labelClass) ?>">Answer</span>
                                                <textarea name="answer" rows="3" required maxlength="1000" class="<?= e($input) ?> mt-1 resize-y"><?= e((string) ($faq['answer'] ?? '')) ?></textarea>
                                            </label>
                                            <label class="block">
                                                <span class="<?= e($labelClass) ?>">Category</span>
                                                <input type="text" name="category" value="<?= e((string) ($faq['category'] ?? '')) ?>" maxlength="40" list="faq-categories" class="<?= e($input) ?> mt-1">
                                            </label>
                                            <label class="block">
                                                <span class="<?= e($labelClass) ?>">Sort order</span>
                                                <input type="number" name="sort_order" value="<?= e((string) (int) ($faq['sort_order'] ?? 0)) ?>" step="1" class="<?= e($input) ?> mt-1">
                                            </label>
                                        </div>

                                        <div class="flex flex-wrap items-center gap-4 border-t border-dashed border-brand-100 pt-3">
                                            <input type="hidden" name="is_active" value="0">
                                            <label class="flex cursor-pointer items-center gap-2 text-sm font-bold text-ink">
                                                <input type="checkbox" name="is_active" value="1" <?= e($active ? 'checked' : '') ?> class="<?= e($box) ?>"> Publish on the FAQ page
                                            </label>
                                            <button type="submit" class="ms-auto rounded-brand bg-brand-600 px-4 py-2 text-sm font-black text-white transition-colors duration-200 hover:bg-brand-700">Save changes</button>
                                        </div>
                                    </form>

                                    <form method="post" action="<?= e(route('admin.faq.delete', ['id' => $fid])) ?>" class="mt-3 rounded-brand border border-rose-200 bg-rose-50/70 p-3">
                                        <?= csrf_field() ?>
                                        <div class="flex flex-wrap items-center justify-between gap-3">
                                            <label class="flex cursor-pointer items-start gap-2 text-xs font-semibold text-rose-900">
                                                <input type="checkbox" name="confirm" value="1" required class="<?= e($box) ?> accent-rose-600">
                                                <span>Permanently delete: <strong><?= e(short_text((string) ($faq['question'] ?? ''), 70)) ?></strong></span>
                                            </label>
                                            <button type="submit" class="rounded-brand bg-rose-600 px-4 py-2 text-xs font-black text-white transition-colors duration-200 hover:bg-rose-700">Delete FAQ</button>
                                        </div>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                <?php endforeach; ?>
            </table>
        </div>
    </div>
<?php endif; ?>
