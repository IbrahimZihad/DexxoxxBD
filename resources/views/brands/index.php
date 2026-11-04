<?php

/**
 * Brand directory: each supplier, its logo, and how many terms are live.
 *
 * @var list<array<string, mixed>> $brands BrandRepository::allWithCounts()
 */

$view->extend('layouts.app');
$view->setSection('title', t('page.brands.title') . ' | ' . (string) setting('brand.name', config('app.name')));
$view->setSection('meta_description', t('page.brands.subtitle'));
$view->setSection('canonical', url('brands'));
$view->setSection('body_class', 'dx-shop dx-directory');

$listed = [];
foreach ($brands as $brand) {
    if ((int) ($brand['product_count'] ?? 0) < 1) {
        continue;
    }
    $listed[] = ['@type' => 'ListItem', 'position' => count($listed) + 1, 'name' => (string) $brand['name'],
        'url' => url('brands/' . (string) $brand['slug'])];
}
$schema = json_encode([
    '@context' => 'https://schema.org',
    '@type' => 'CollectionPage',
    'name' => t('page.brands.title'),
    'url' => url('brands'),
    'mainEntity' => ['@type' => 'ItemList', 'itemListElement' => $listed],
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';
$view->setSection('json_ld', $schema);

// Letters present in the list, so the jump rail never links to an empty heading.
$letters = [];
foreach ($brands as $brand) {
    $first = mb_strtoupper(mb_substr(trim((string) $brand['name']), 0, 1));
    if ($first !== '') {
        $letters[$first] = true;
    }
}
$letters = array_keys($letters);
sort($letters);
$grouped = [];
foreach ($brands as $brand) {
    $grouped[mb_strtoupper(mb_substr(trim((string) $brand['name']), 0, 1))][] = $brand;
}
?>
<div class="container">
    <?= $view->component('breadcrumb', ['items' => [
        ['label' => t('nav.home'), 'url' => url('')],
        ['label' => t('page.brands.title')],
    ]]) ?>

    <?= $view->component('section-header', [
        'eyebrow' => t('home.brands.eyebrow'),
        'title' => t('page.brands.title'),
        'subtitle' => t('page.brands.subtitle'),
        'titleId' => 'dx-brands-title',
    ]) ?>

    <?php if ($letters !== []): ?>
        <nav class="dx-jumprail" aria-label="<?= e(t('page.brands.a_to_z')) ?>">
            <?php foreach ($letters as $letter): ?>
                <a href="#dx-brand-<?= e($letter) ?>"><?= e($letter) ?></a>
            <?php endforeach; ?>
        </nav>
    <?php endif; ?>

    <?php foreach ($grouped as $letter => $group): ?>
        <section class="dx-brandletter" id="dx-brand-<?= e($letter) ?>">
            <h2 class="dx-brandletter__letter"><?= e($letter) ?></h2>
            <div class="dx-brandlist">
                <?php foreach ($group as $brand): ?>
                    <?php $count = (int) ($brand['product_count'] ?? 0); ?>
                    <article class="dx-brandcard<?= $count === 0 ? ' is-empty' : '' ?>">
                        <img class="dx-brandcard__logo" src="<?= e(image_url($brand['logo'] ?? null)) ?>"
                             alt="<?= e((string) $brand['name']) ?>" width="72" height="72" loading="lazy">
                        <div class="dx-brandcard__body">
                            <h3 class="dx-brandcard__name">
                                <?php if ($count === 0): ?>
                                    <?= e((string) $brand['name']) ?>
                                <?php else: ?>
                                    <a href="<?= e(url('brands/' . (string) $brand['slug'])) ?>">
                                        <?= e((string) $brand['name']) ?>
                                    </a>
                                <?php endif; ?>
                            </h3>
                            <p class="dx-brandcard__meta">
                                <?= e(choice('page.brands.plans_in', $count, ['count' => $count])) ?>
                                <?php if (!empty($brand['country'])): ?>
                                    · <?= e(t('page.brands.country', ['country' => (string) $brand['country']])) ?>
                                <?php endif; ?>
                            </p>
                            <?php if (!empty($brand['description'])): ?>
                                <p class="dx-brandcard__text"><?= e((string) $brand['description']) ?></p>
                            <?php endif; ?>
                        </div>
                        <?php if ($count > 0): ?>
                            <a class="btn btn-dx-ghost btn-sm"
                               href="<?= e(url('brands/' . (string) $brand['slug'])) ?>">
                                <?= e(t('page.brands.browse')) ?>
                            </a>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endforeach; ?>
</div>
