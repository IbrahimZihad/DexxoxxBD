<?php

/**
 * Category directory: every live shelf with its sub-categories and plan counts.
 *
 * @var list<array<string, mixed>> $tree CategoryRepository::tree()
 */

$view->extend('layouts.app');
$view->setSection('title', t('page.categories.title') . ' | ' . (string) setting('brand.name', config('app.name')));
$view->setSection('meta_description', t('page.categories.subtitle'));
$view->setSection('canonical', url('categories'));
$view->setSection('body_class', 'dx-shop dx-directory');

$listed = [];
foreach ($tree as $shelf) {
    $listed[] = ['@type' => 'ListItem', 'position' => count($listed) + 1, 'name' => (string) $shelf['name'],
        'url' => url('categories/' . (string) $shelf['slug'])];
}
$schema = json_encode([
    '@context' => 'https://schema.org',
    '@type' => 'CollectionPage',
    'name' => t('page.categories.title'),
    'url' => url('categories'),
    'mainEntity' => ['@type' => 'ItemList', 'itemListElement' => $listed],
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';
$view->setSection('json_ld', $schema);
?>
<div class="container">
    <?= $view->component('breadcrumb', ['items' => [
        ['label' => t('nav.home'), 'url' => url('')],
        ['label' => t('page.categories.title')],
    ]]) ?>

    <?= $view->component('section-header', [
        'eyebrow' => t('home.categories.eyebrow'),
        'title' => t('page.categories.title'),
        'subtitle' => t('page.categories.subtitle'),
        'titleId' => 'dx-directory-title',
    ]) ?>

    <div class="dx-shelfgrid">
        <?php foreach ($tree as $shelf): ?>
            <?php $count = (int) ($shelf['product_count'] ?? 0); ?>
            <section class="dx-shelf">
                <a class="dx-shelf__media" href="<?= e(url('categories/' . (string) $shelf['slug'])) ?>">
                    <img src="<?= e(image_url($shelf['image'] ?? null)) ?>"
                         alt="<?= e((string) $shelf['name']) ?>"
                         width="160" height="120" loading="lazy">
                </a>
                <div class="dx-shelf__body">
                    <h2 class="dx-shelf__title">
                        <a href="<?= e(url('categories/' . (string) $shelf['slug'])) ?>">
                            <?= e((string) $shelf['name']) ?>
                        </a>
                    </h2>
                    <p class="dx-shelf__meta">
                        <?= e(choice('page.categories.plans_in', $count, ['count' => $count])) ?>
                    </p>
                    <?php if (!empty($shelf['description'])): ?>
                        <p class="dx-shelf__text"><?= e((string) $shelf['description']) ?></p>
                    <?php endif; ?>

                    <?php if (($shelf['children'] ?? []) !== []): ?>
                        <ul class="dx-chiprow" aria-label="<?= e(t('catalog.subcategories')) ?>">
                            <?php foreach ($shelf['children'] as $child): ?>
                                <li>
                                    <a class="dx-chip dx-chip--link"
                                       href="<?= e(url('categories/' . (string) $child['slug'])) ?>">
                                        <?= e((string) $child['name']) ?>
                                        <span class="dx-chip__count"><?= e((int) ($child['product_count'] ?? 0)) ?></span>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>

                    <a class="btn btn-dx btn-sm" href="<?= e(url('categories/' . (string) $shelf['slug'])) ?>">
                        <?= e(t('page.categories.browse')) ?>
                    </a>
                </div>
            </section>
        <?php endforeach; ?>
    </div>
</div>
