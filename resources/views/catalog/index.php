<?php

/**
 * Listing page shared by the catalog, search results, a category shelf, a brand
 * page and the deals view. The controller decides the heading and the preset
 * filters; everything else is the same toolbar, panel and grid.
 *
 * @var array<string, mixed> $result       paginate() output
 * @var array<string, mixed> $filters      normalised filter state
 * @var list<array<string, mixed>> $categories
 * @var list<array<string, mixed>> $brands
 * @var string $heading
 * @var string $subheading
 * @var list<array{label: string, url?: string}> $breadcrumbs
 * @var array<string, mixed>|null $shelf   category row when browsing a shelf
 * @var list<array<string, mixed>> $children sub-categories of that shelf
 */

$rows = $result['rows'] ?? [];
$total = (int) ($result['total'] ?? 0);
$page = (int) ($result['page'] ?? 1);
$lastPage = (int) ($result['last_page'] ?? 1);
$perPage = (int) ($result['per_page'] ?? 12);
$from = $total === 0 ? 0 : ($page - 1) * $perPage + 1;
$to = min($total, $page * $perPage);
$selectedBrands = array_values((array) ($filters['brand'] ?? []));
$shelf = $shelf ?? null;
$children = $children ?? [];
$ratingValue = (float) ($filters['rating'] ?? 0);

$view->extend('layouts.app');
$view->setSection('title', (string) ($seo_title ?? $heading));
$view->setSection('meta_description', (string) ($seo_description ?? $subheading));
$view->setSection('canonical', (string) ($canonical ?? current_url()));
$view->setSection('meta_robots', $page > 1 ? 'noindex, follow' : 'index, follow');
$schema = json_encode([
    '@context' => 'https://schema.org',
    '@type' => 'BreadcrumbList',
    'itemListElement' => array_values(array_map(
        static fn (array $crumb, int $index): array => [
            '@type' => 'ListItem',
            'position' => $index + 1,
            'name' => (string) $crumb['label'],
            'item' => (string) ($crumb['url'] ?? current_url()),
        ],
        $breadcrumbs ?? [],
        array_keys($breadcrumbs ?? [])
    )),
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
$view->setSection('json_ld', $schema === false ? '' : $schema);
?>
<div class="container">
    <?= $view->component('breadcrumb', ['items' => $breadcrumbs ?? []]) ?>

    <header class="dx-pagehead">
        <div>
            <h1 class="dx-display"><?= e((string) $heading) ?></h1>
            <p class="dx-pagehead__text"><?= e((string) $subheading) ?></p>
        </div>
        <?php if ($shelf !== null && !empty($shelf['description'])): ?>
            <p class="dx-muted"><?= e((string) $shelf['description']) ?></p>
        <?php endif; ?>
    </header>

    <?php if ($children !== []): ?>
        <nav class="dx-chiprow" aria-label="<?= e(t('catalog.subcategories')) ?>">
            <?php foreach ($children as $child): ?>
                <a class="btn btn-pill btn-dx-soft" href="<?= e(url('categories/' . $child['slug'])) ?>">
                    <?= e((string) $child['name']) ?>
                    <span class="dx-nav__badge"><?= e((int) $child['product_count']) ?></span>
                </a>
            <?php endforeach; ?>
        </nav>
    <?php endif; ?>

    <div class="dx-layout">
        <aside class="dx-filterpanel" aria-label="<?= e(t('common.filters')) ?>">
            <form method="get" action="<?= e(url('products')) ?>" data-filters>
                <div class="dx-filterpanel__head">
                    <h2 class="dx-filterpanel__title">
                        <i class="bi bi-funnel" aria-hidden="true"></i>
                        <?= e(t('common.filters')) ?>
                    </h2>
                    <a class="dx-filterpanel__clear" href="<?= e(url('products')) ?>">
                        <?= e(t('common.clear_filters')) ?>
                    </a>
                </div>

                <div class="dx-filterpanel__body">
                    <fieldset class="dx-filtergroup">
                        <legend class="dx-filtergroup__label"><?= e(t('common.search')) ?></legend>
                        <input class="form-control" type="search" name="q" maxlength="120"
                               value="<?= e((string) ($filters['search'] ?? '')) ?>"
                               placeholder="<?= e(t('common.search_placeholder')) ?>">
                    </fieldset>

                    <fieldset class="dx-filtergroup">
                        <legend class="dx-filtergroup__label"><?= e(t('nav.categories')) ?></legend>
                        <select class="form-select" name="category">
                            <option value=""><?= e(t('common.all_categories')) ?></option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?= e((string) $category['slug']) ?>"
                                    <?= (string) ($filters['category'] ?? '') === (string) $category['slug']
                                        ? 'selected' : '' ?>>
                                    <?= e((string) $category['name']) ?>
                                    (<?= e((int) $category['product_count']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </fieldset>

                    <fieldset class="dx-filtergroup">
                        <legend class="dx-filtergroup__label"><?= e(t('nav.brands')) ?></legend>
                        <div class="dx-filtergroup__scroll">
                            <?php foreach ($brands as $brand): ?>
                                <?php $slug = (string) $brand['slug']; ?>
                                <label class="dx-check">
                                    <input type="checkbox" name="brand[]" value="<?= e($slug) ?>"
                                        <?= in_array($slug, $selectedBrands, true) ? 'checked' : '' ?>>
                                    <span><?= e((string) $brand['name']) ?></span>
                                    <em><?= e((int) $brand['product_count']) ?></em>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </fieldset>

                    <fieldset class="dx-filtergroup">
                        <legend class="dx-filtergroup__label"><?= e(t('catalog.price_band.title')) ?></legend>
                        <div class="dx-filtergroup__row">
                            <label class="dx-field">
                                <span><?= e(t('common.min')) ?></span>
                                <input class="form-control" type="number" name="min_price" min="0" step="50"
                                       value="<?= e((string) ($filters['min_price'] ?? '')) ?>">
                            </label>
                            <label class="dx-field">
                                <span><?= e(t('common.max')) ?></span>
                                <input class="form-control" type="number" name="max_price" min="0" step="50"
                                       value="<?= e((string) ($filters['max_price'] ?? '')) ?>">
                            </label>
                        </div>
                        <div class="dx-chiprow dx-chiprow--tight">
                            <?php
                            $bands = [
                                [t('catalog.price_band.under_500'), ['max_price' => 499]],
                                [t('catalog.price_band.500_1500'), ['min_price' => 500, 'max_price' => 1500]],
                                [t('catalog.price_band.1500_3000'), ['min_price' => 1500, 'max_price' => 3000]],
                                [t('catalog.price_band.over_3000'), ['min_price' => 3000]],
                            ];
                            ?>
                            <?php foreach ($bands as [$label, $params]): ?>
                                <a class="btn btn-pill btn-dx-ghost" href="<?= e(query_url($params)) ?>">
                                    <?= e((string) $label) ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </fieldset>

                    <fieldset class="dx-filtergroup">
                        <legend class="dx-filtergroup__label"><?= e(t('common.duration')) ?></legend>
                        <select class="form-select" name="duration">
                            <option value=""><?= e(t('common.any_duration')) ?></option>
                            <?php foreach (t_list('common.durations') as $code => $label): ?>
                                <?php $label = (string) $label; ?>
                                <option value="<?= e((string) $code) ?>"
                                    <?= (string) ($filters['duration'] ?? '') === (string) $code ? 'selected' : '' ?>>
                                    <?= e($label) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </fieldset>

                    <fieldset class="dx-filtergroup">
                        <legend class="dx-filtergroup__label"><?= e(t('common.availability')) ?></legend>
                        <select class="form-select" name="availability">
                            <option value=""><?= e(t('common.any_availability')) ?></option>
                            <?php foreach (t_list('common.availability_options') as $code => $label): ?>
                                <?php $label = (string) $label; ?>
                                <option value="<?= e((string) $code) ?>"
                                    <?= (string) ($filters['availability'] ?? '') === (string) $code
                                        ? 'selected' : '' ?>>
                                    <?= e($label) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </fieldset>

                    <fieldset class="dx-filtergroup">
                        <legend class="dx-filtergroup__label"><?= e(t('common.rating')) ?></legend>
                        <?php for ($stars = 4; $stars >= 1; $stars--): ?>
                            <label class="dx-check">
                                <input type="radio" name="rating" value="<?= e($stars) ?>"
                                    <?= abs($ratingValue - $stars) < 0.01 ? 'checked' : '' ?>>
                                <span><?= e(t('common.rating_and_up', ['score' => $stars])) ?></span>
                            </label>
                        <?php endfor; ?>
                    </fieldset>

                    <fieldset class="dx-filtergroup">
                        <legend class="dx-filtergroup__label"><?= e(t('catalog.refine')) ?></legend>
                        <?php
                        $flags = [
                            'featured' => t('catalog.flags.featured'),
                            'bestseller' => t('catalog.flags.bestseller'),
                            'new_arrival' => t('catalog.flags.new_arrival'),
                            'on_sale' => t('catalog.flags.on_sale'),
                            'digital_delivery' => t('catalog.flags.digital_delivery'),
                        ];
                        ?>
                        <?php foreach ($flags as $name => $label): ?>
                            <label class="dx-check">
                                <input type="checkbox" name="<?= e((string) $name) ?>" value="1"
                                    <?= filter_var($filters[$name] ?? false, FILTER_VALIDATE_BOOLEAN) ? 'checked' : '' ?>>
                                <span><?= e((string) $label) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </fieldset>
                </div>
            </form>
        </aside>

        <section class="dx-results" aria-live="polite">
            <div class="dx-toolbar">
                <p class="dx-toolbar__count">
                    <?= e(t('common.showing', ['from' => $from, 'to' => $to, 'total' => $total])) ?>
                    &middot; <?= e(choice('common.results_count', $total)) ?>
                </p>

                <div class="dx-toolbar__controls">
                    <?php
                    // Sorting must not drop the filters already in the URL, so the
                    // current query is mirrored into this form as hidden fields
                    // minus the page (a new sort starts at page 1) and itself.
                    $mirror = array_diff_key($request->query(), array_flip(['sort', 'page', '_token']));
                    ?>
                    <form method="get" action="<?= e(url('products')) ?>" class="dx-toolbar__sort" data-filters>
                        <?php foreach ($mirror as $name => $value): ?>
                            <?php foreach ((array) $value as $item): ?>
                                <?php if ($item === '' || $item === null) {
                                    continue;
                                } ?>
                                <input type="hidden" name="<?= e((string) $name) ?><?= is_array($value) ? '[]' : '' ?>"
                                       value="<?= e((string) $item) ?>">
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                        <label for="dx-sort"><?= e(t('common.sort_by')) ?></label>
                        <select class="form-select" id="dx-sort" name="sort">
                            <?php foreach (t_list('catalog.sort') as $code => $label): ?>
                                <?php $label = (string) $label; ?>
                                <option value="<?= e((string) $code) ?>"
                                    <?= (string) ($filters['sort'] ?? 'newest') === (string) $code ? 'selected' : '' ?>>
                                    <?= e($label) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </form>

                    <div class="dx-viewswitch" data-view-switch role="group" aria-label="<?= e(t('catalog.view_options')) ?>">
                        <button type="button" class="btn btn-pill" data-view="grid" aria-pressed="true">
                            <i class="bi bi-grid-3x2-gap" aria-hidden="true"></i>
                            <span class="visually-hidden"><?= e(t('common.grid')) ?></span>
                        </button>
                        <button type="button" class="btn btn-pill" data-view="list" aria-pressed="false">
                            <i class="bi bi-list-ul" aria-hidden="true"></i>
                            <span class="visually-hidden"><?= e(t('common.list')) ?></span>
                        </button>
                    </div>
                </div>
            </div>

            <?php $applied = []; ?>
            <?php foreach ((array) ($filters['active'] ?? []) as $key => $value): ?>
                <?php $applied[] = ['label' => (string) $value, 'url' => query_url([$key => null])]; ?>
            <?php endforeach; ?>
            <?php foreach ($selectedBrands as $brandSlug): ?>
                <?php
                $rest = array_values(array_filter($selectedBrands, static fn ($b): bool => $b !== $brandSlug));
                $applied[] = ['label' => $brandSlug, 'url' => query_url(['brand' => $rest === [] ? null : $rest])];
                ?>
            <?php endforeach; ?>

            <?php if ($applied !== []): ?>
                <div class="dx-applied">
                    <span class="dx-applied__label"><?= e(t('catalog.active_filters')) ?></span>
                    <?php foreach ($applied as $chip): ?>
                        <a class="btn btn-pill btn-dx-ghost" href="<?= e((string) $chip['url']) ?>">
                            <?= e((string) $chip['label']) ?>
                            <i class="bi bi-x-lg" aria-hidden="true"></i>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ($rows === []): ?>
                <?= $view->component('empty-state', [
                    'title' => ($filters['search'] ?? '') !== ''
                        ? t('catalog.search_empty_title', ['term' => (string) $filters['search']])
                        : t('common.empty_title'),
                    'text' => ($filters['search'] ?? '') !== ''
                        ? t('catalog.search_empty_text')
                        : t('common.empty_text'),
                    'cta_url' => url('products'),
                    'cta_label' => t('common.browse_all'),
                ]) ?>
            <?php else: ?>
                <div class="dx-grid" data-results>
                    <?php foreach ($rows as $product): ?>
                        <?= $view->component('product-card', ['row' => $product]) ?>
                    <?php endforeach; ?>
                </div>

                <?= $view->component('pagination', [
                    'page' => $page,
                    'lastPage' => $lastPage,
                    'total' => $total,
                ]) ?>
            <?php endif; ?>
        </section>
    </div>
</div>
