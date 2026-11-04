<?php

/**
 * Public FAQ page. Answers come from the CMS faqs table; the filter box is a
 * plain GET form so it works with JavaScript switched off.
 *
 * @var array<string, list<array<string, mixed>>> $groups category => rows
 * @var string $query
 */

$view->extend('layouts.app');
$view->setSection('title', t('page.faqs.title') . ' | ' . (string) setting('brand.name', config('app.name')));
$view->setSection('meta_description', t('page.faqs.subtitle'));
$view->setSection('canonical', $query === '' ? url('faqs') : current_url());
$view->setSection('meta_robots', $query === '' ? 'index, follow' : 'noindex, follow');

$flat = [];
foreach ($groups as $rows) {
    foreach ($rows as $row) {
        $flat[] = $row;
    }
}

$schema = json_encode([
    '@context' => 'https://schema.org',
    '@type' => 'FAQPage',
    'mainEntity' => array_values(array_map(
        static fn (array $row): array => [
            '@type' => 'Question',
            'name' => (string) $row['question'],
            'acceptedAnswer' => ['@type' => 'Answer', 'text' => (string) $row['answer']],
        ],
        $query === '' ? $flat : []
    )),
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';
if ($schema !== '' && $query === '') {
    $view->setSection('json_ld', $schema);
}
?>
<div class="container">
    <?= $view->component('breadcrumb', ['items' => [
        ['label' => t('nav.home'), 'url' => url('')],
        ['label' => t('page.faqs.title')],
    ]]) ?>

    <div class="dx-pagehead">
        <h1 class="dx-pagehead__title"><?= e(t('page.faqs.title')) ?></h1>
        <p class="dx-pagehead__sub"><?= e(t('page.faqs.subtitle')) ?></p>

        <form class="dx-faqfilter" method="get" action="<?= e(url('faqs')) ?>">
            <label class="visually-hidden" for="dx-faq-q"><?= e(t('page.faqs.search')) ?></label>
            <input class="form-control" id="dx-faq-q" type="search" name="q"
                   value="<?= e($query) ?>" placeholder="<?= e(t('page.faqs.search')) ?>" autocomplete="off">
            <button class="btn btn-dx" type="submit"><?= e(t('common.search')) ?></button>
        </form>
    </div>

    <?php if ($flat === []): ?>
        <?= $view->component('empty-state', ['title' => t('page.faqs.title'), 'text' => t('page.faqs.empty'),
            'ctaUrl' => url('contact'), 'ctaLabel' => t('page.faqs.more'), 'icon' => 'bi-question-circle']) ?>
    <?php else: ?>
        <?php foreach ($groups as $category => $rows): ?>
            <section class="dx-faqgroup">
                <h2 class="dx-faqgroup__title"><?= e((string) ($category === '' ? t('page.faqs.title') : $category)) ?></h2>
                <?php foreach ($rows as $row): ?>
                    <details class="dx-accordion">
                        <summary><?= e((string) $row['question']) ?></summary>
                        <div class="dx-prose dx-prose--tight">
                            <?= html_clean((string) $row['answer']) ?>
                        </div>
                    </details>
                <?php endforeach; ?>
            </section>
        <?php endforeach; ?>

        <p class="dx-faqgroup__foot">
            <?= e(t('page.faqs.more')) ?>
            <a class="dx-link" href="<?= e(url('contact')) ?>"><?= e(t('nav.contact')) ?></a>
        </p>
    <?php endif; ?>
</div>
