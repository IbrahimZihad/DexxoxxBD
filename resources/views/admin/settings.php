<?php

declare(strict_types=1);

/**
 * Settings, grouped by the real `group_name` column and rendered as one [data-tabs]
 * form. The whole thing is a single <form method="post"> with a save-all button,
 * so it works with JavaScript off: without the tab script every group panel simply
 * stays visible and stacked (no panel is hidden in the markup), and the one submit
 * posts every field regardless of which tab is showing.
 *
 * Only keys that already exist in the settings table (or the in-code defaults) are
 * ever rendered here, so APP_KEY and the DB credentials never appear as a field.
 *
 * @var App\Core\View $view
 * @var list<array{name: string, fields: list<array<string, string>>}> $tabs
 * @var array<string, array{label: string, description: string}> $groupMeta
 */
$brandName = setting('brand.name', 'ZapSub');

$tabs = array_values(array_map(static fn (mixed $tab): array => (array) $tab, (array) ($tabs ?? [])));
$groupMeta = array_map(static fn (mixed $meta): array => (array) $meta, (array) ($groupMeta ?? []));

$input = 'w-full rounded-brand border border-brand-200 bg-white px-3.5 py-2.5 text-sm text-ink outline-none transition duration-200 placeholder:text-ink-400 focus:border-brand-400 focus:ring-2 focus:ring-brand-300';
$labelClass = 'block text-xs font-black uppercase tracking-[0.12em] text-ink-400';
$box = 'mt-0.5 h-4 w-4 shrink-0 rounded accent-brand-600';

// Active/inactive look is driven by the aria-selected attribute that the shipped
// tab script toggles, exactly like resources/views/pages/faq.php.
$tabButton = 'flex shrink-0 items-center gap-2 whitespace-nowrap rounded-brand border border-brand-200 bg-white px-4 py-2 text-sm font-bold text-ink-700 transition-colors duration-200 aria-selected:border-brand-500 aria-selected:bg-brand-600 aria-selected:text-white aria-selected:shadow-pop';

$view->extend('layouts/admin');
$view->setSection('title', 'Settings - ' . $brandName);
$view->setSection('page_title', 'Settings');
$view->setSection('page_actions', '<a href="' . e(route('home')) . '" target="_blank" rel="noopener" class="inline-flex items-center gap-2 rounded-brand border border-brand-200 bg-white px-4 py-2 text-sm font-bold text-brand-700 transition-colors duration-200 hover:bg-brand-50">'
    . '<svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path d="M14 4h6v6M20 4l-9 9" stroke-linecap="round" stroke-linejoin="round"/><path d="M18 14v5a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1V8a1 1 0 0 1 1-1h5" stroke-linecap="round" stroke-linejoin="round"/></svg>'
    . 'Preview shop</a>');
?>

<?php if ($tabs === []): ?>
    <div class="rounded-card border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
        There are no editable settings yet. Run <code class="rounded bg-white px-1.5 py-0.5 font-mono text-xs">composer seed</code> to load the defaults.
    </div>
<?php else: ?>
    <form method="post" action="<?= e(route('admin.settings.update')) ?>" data-tabs class="rounded-card border border-brand-100 bg-white p-4 shadow-soft sm:p-5">
        <?= csrf_field() ?>

        <div role="tablist" aria-label="Setting groups" class="no-scrollbar -mx-1 mb-4 flex gap-2 overflow-x-auto px-1 pb-1">
            <?php foreach ($tabs as $index => $tab): ?>
                <?php $meta = $groupMeta[$tab['name']] ?? ['label' => ucwords((string) $tab['name']), 'description' => '']; ?>
                <button type="button" role="tab" data-tab-btn="<?= e((string) $tab['name']) ?>" class="<?= e($tabButton . ($index === 0 ? ' is-active' : '')) ?>" aria-selected="<?= e($index === 0 ? 'true' : 'false') ?>">
                    <?= e((string) $meta['label']) ?>
                    <span class="rounded-pill bg-brand-100 px-1.5 py-0.5 text-[0.65rem] font-black text-brand-700 aria-selected:bg-white/25 aria-selected:text-white"><?= e((string) count($tab['fields'])) ?></span>
                </button>
            <?php endforeach; ?>
        </div>

        <?php foreach ($tabs as $index => $tab): ?>
            <?php $meta = $groupMeta[$tab['name']] ?? ['label' => ucwords((string) $tab['name']), 'description' => '']; ?>
            <div data-tab-panel="<?= e((string) $tab['name']) ?>" role="tabpanel" aria-label="<?= e((string) $meta['label']) ?>" class="mt-1">
                <p class="text-sm font-black text-ink"><?= e((string) $meta['label']) ?></p>
                <?php if (($meta['description'] ?? '') !== ''): ?>
                    <p class="mt-1 text-sm leading-relaxed text-ink-400"><?= e((string) $meta['description']) ?></p>
                <?php endif; ?>

                <?php if ((string) $tab['name'] === 'whatsapp'): ?>
                    <p class="mt-3 rounded-brand border border-accent-200 bg-accent-50 px-3 py-2 text-xs font-semibold text-accent-900">
                        The WhatsApp number is stored in this database table, never in the code. The storefront chat head
                        reads it from here, so change it here and it changes everywhere.
                    </p>
                <?php endif; ?>

                <div class="mt-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    <?php foreach ($tab['fields'] as $field): ?>
                        <?php
                        $key = (string) $field['key'];
                        $value = (string) $field['value'];
                        $type = (string) $field['type'];
                        $label = (string) $field['label'];
                        $errors = field_errors($key);
                        ?>
                        <?php if ($type === 'bool'): ?>
                            <div class="sm:col-span-2 xl:col-span-1">
                                <input type="hidden" name="<?= e($key) ?>" value="0">
                                <label class="flex cursor-pointer items-center gap-2.5 rounded-brand border border-brand-100 bg-brand-50/50 px-3.5 py-2.5 text-sm font-bold text-ink">
                                    <input type="checkbox" name="<?= e($key) ?>" value="1" <?= e($value === '1' ? 'checked' : '') ?> class="<?= e($box) ?>">
                                    <?= e($label) ?>
                                </label>
                            </div>
                        <?php else: ?>
                            <label class="block <?= e($type === 'textarea' ? 'sm:col-span-2 xl:col-span-3' : '') ?>">
                                <span class="<?= e($labelClass) ?>"><?= e($label) ?></span>
                                <?php if ($type === 'textarea'): ?>
                                    <textarea name="<?= e($key) ?>" rows="3" maxlength="2000" class="<?= e($input) ?> mt-1 resize-y <?= e($errors !== [] ? 'border-rose-300 ring-2 ring-rose-400' : '') ?>"><?= e($value) ?></textarea>
                                <?php elseif ($type === 'int'): ?>
                                    <input type="number" name="<?= e($key) ?>" value="<?= e($value) ?>" min="0" max="100000000" step="1" inputmode="numeric" class="<?= e($input) ?> mt-1 <?= e($errors !== [] ? 'border-rose-300 ring-2 ring-rose-400' : '') ?>">
                                <?php elseif ($type === 'number'): ?>
                                    <input type="number" name="<?= e($key) ?>" value="<?= e($value) ?>" min="0" max="10" step="0.1" class="<?= e($input) ?> mt-1 <?= e($errors !== [] ? 'border-rose-300 ring-2 ring-rose-400' : '') ?>">
                                <?php else: ?>
                                    <input type="text" name="<?= e($key) ?>" value="<?= e($value) ?>" maxlength="2000" class="<?= e($input) ?> mt-1 <?= e($errors !== [] ? 'border-rose-300 ring-2 ring-rose-400' : '') ?>">
                                <?php endif; ?>
                                <?php foreach ($errors as $message): ?>
                                    <p class="mt-1 text-xs font-semibold text-rose-600"><?= e((string) $message) ?></p>
                                <?php endforeach; ?>
                            </label>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>

        <div class="mt-5 flex flex-wrap items-center gap-3 border-t border-dashed border-brand-100 pt-4">
            <p class="text-xs text-ink-400">Every field on the page is saved together, whichever tab is showing. Blank clears a value.</p>
            <button type="submit" class="ms-auto rounded-brand bg-brand-600 px-6 py-2.5 text-sm font-black text-white shadow-glow transition-colors duration-200 hover:bg-brand-700">Save all settings</button>
        </div>
    </form>
<?php endif; ?>
