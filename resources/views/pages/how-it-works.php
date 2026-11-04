<?php

/**
 * Delivery explainer: the four steps, what payment methods are actually enabled
 * on this install, and the policy pages a customer should read before paying.
 *
 * @var list<array{title: string, text: string}> $steps
 * @var list<array{label: string, url: string}> $legal
 * @var string $summary
 */

$view->extend('layouts.app');

$title = t('page.how_it_works.title');
$subtitle = t('page.how_it_works.subtitle');

$view->setSection('title', $title . ' | ' . (string) setting('brand.name', config('app.name')));
$view->setSection('meta_description', $subtitle);
$view->setSection('canonical', url('how-it-works'));

$breadcrumbs = [
    ['label' => t('nav.home'), 'url' => url('')],
    ['label' => t('nav.how_it_works')],
];

$methods = array_values(array_filter(
    (array) config('payment.methods', []),
    static fn (array $method): bool => (bool) ($method['enabled'] ?? false)
));

$schema = json_encode([
    '@context' => 'https://schema.org',
    '@type' => 'HowTo',
    'name' => $title,
    'description' => $subtitle,
    'url' => url('how-it-works'),
    'step' => array_map(static fn (array $step): array => [
        '@type' => 'HowToStep',
        'name' => $step['title'],
        'text' => $step['text'],
    ], $steps),
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';
$view->setSection('json_ld', $schema);
?>
<div class="container">
    <?= $view->component('breadcrumb', ['items' => $breadcrumbs]) ?>

    <div class="dx-pagehead">
        <span class="dx-eyebrow"><?= e(t('page.how_it_works.eyebrow')) ?></span>
        <h1 class="dx-pagehead__title"><?= e($title) ?></h1>
        <p class="dx-pagehead__sub"><?= e($subtitle) ?></p>
    </div>

    <ol class="dx-steps">
        <?php foreach ($steps as $step): ?>
            <li class="dx-step">
                <b><?= e($step['title']) ?></b>
                <p><?= e($step['text']) ?></p>
            </li>
        <?php endforeach; ?>
    </ol>

    <div class="dx-layout">
        <section class="dx-cardpanel">
            <h2 class="dx-h4"><?= e(t('page.how_it_works.pay_title')) ?></h2>
            <?php if ($methods === []): ?>
                <p class="dx-muted"><?= e(t('page.how_it_works.pay_none')) ?></p>
            <?php else: ?>
                <ul class="dx-paylist">
                    <?php foreach ($methods as $method): ?>
                        <li>
                            <i class="bi bi-<?= e((string) ($method['icon'] ?? 'wallet2')) ?>" aria-hidden="true"></i>
                            <span>
                                <b><?= e((string) $method['label']) ?></b>
                                <p><?= e((string) ($method['description'] ?? '')) ?></p>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            <p class="dx-hint"><?= e(t('page.how_it_works.pay_note')) ?></p>
        </section>

        <aside class="dx-cardpanel dx-cardpanel--ink">
            <h2 class="dx-h4"><?= e(t('page.how_it_works.promise_title')) ?></h2>
            <p><?= e($summary) ?></p>

            <?php if ($legal !== []): ?>
                <h3 class="dx-h4"><?= e(t('page.how_it_works.policy_title')) ?></h3>
                <div class="dx-chiprow dx-chiprow--tight">
                    <?php foreach ($legal as $item): ?>
                        <a class="dx-chip dx-chip--link" href="<?= e($item['url']) ?>"><?= e($item['label']) ?></a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <p class="dx-muted"><?= e(t('page.how_it_works.help_text')) ?></p>
            <div class="d-flex flex-wrap gap-2">
                <a class="btn btn-dx" href="<?= e(url('products')) ?>"><?= e(t('cart.continue_shopping')) ?></a>
                <a class="btn btn-outline-light" href="<?= e(url('contact')) ?>"><?= e(t('nav.contact')) ?></a>
            </div>
        </aside>
    </div>
</div>
