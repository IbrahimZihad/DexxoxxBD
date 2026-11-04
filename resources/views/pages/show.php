<?php

/**
 * A CMS page (about, terms, privacy, delivery, refund, how it works).
 *
 * $page is the rows.pages record the ContentRepository returned. Its `content`
 * column is staff-authored HTML, so it only reaches the browser through the
 * whitelist sanitizer - never printed raw.
 *
 * @var array<string, mixed> $page
 * @var list<array{label: string, url?: string}> $breadcrumbs
 */

$title = (string) $page['title'];
$body = html_clean((string) ($page['content'] ?? ''));
$updated = $page['updated_at'] ?? null;

$view->extend('layouts.app');
$view->setSection('title', (string) ($page['meta_title'] ?? $title));
$view->setSection('meta_description', (string) ($page['meta_description']
    ?? mb_substr((string) ($page['excerpt'] ?? strip_tags($body)), 0, 155)));
$view->setSection('canonical', url('page/' . (string) $page['slug']));

$schema = json_encode([
    '@context' => 'https://schema.org',
    '@type' => 'Article',
    'headline' => $title,
    'inLanguage' => current_locale() === 'bn' ? 'bn' : 'en',
    'about' => (string) setting('brand.name', config('app.name')),
    'publisher' => [
        '@type' => 'Organization',
        'name' => (string) setting('brand.name', config('app.name')),
        'url' => url('/'),
    ],
    'mainEntityOfPage' => url('page/' . (string) $page['slug']),
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) ?: '';
$view->setSection('json_ld', $schema);
?>
<div class="container">
    <?= $view->component('breadcrumb', ['items' => $breadcrumbs ?? [['label' => t('nav.home'), 'url' => url('')]]]) ?>

    <article class="dx-pagearticle">
        <header class="dx-pagearticle__head">
            <h1 class="dx-pagearticle__title"><?= e($title) ?></h1>
            <?php if (!empty($page['excerpt'])): ?>
                <p class="dx-pagearticle__lead"><?= e((string) $page['excerpt']) ?></p>
            <?php endif; ?>
            <?php if ($updated !== null): ?>
                <p class="dx-pagearticle__stamp">
                    <?= e(t('page.updated')) ?> <?= e(date('d M Y', (int) strtotime((string) $updated))) ?>
                </p>
            <?php endif; ?>
        </header>

        <div class="dx-prose">
            <?php if (trim($body) === ''): ?>
                <p><?= e(t('page.empty')) ?></p>
            <?php else: ?>
                <?= $body ?>
            <?php endif; ?>
        </div>

        <aside class="dx-pagearticle__aside">
            <h2 class="dx-h4"><?= e(t('page.need_help')) ?></h2>
            <p><?= e(t('page.help_text', ['email' => (string) setting('contact.email', '')])) ?></p>
            <p class="dx-pagearticle__actions">
                <a class="btn btn-dx" href="<?= e(url('contact')) ?>"><?= e(t('nav.contact')) ?></a>
                <a class="btn btn-dx-ghost" href="<?= e(url('products')) ?>"><?= e(t('nav.products')) ?></a>
            </p>
        </aside>
    </article>
</div>
