<?php

/**
 * Homepage: fifteen sections, all data-driven.
 *
 * Which shelves, plans, banners, quotes and FAQs appear comes from MySQL, so the
 * CMS can change the page without a template edit. Copy that belongs to the
 * design (headings, step text, trust points) lives in resources/lang/en/home.php
 * so a translation rewrites the whole page.
 */

use App\Repositories\BrandRepository;
use App\Repositories\CategoryRepository;
use App\Repositories\ContentRepository;
use App\Repositories\ProductRepository;

$products = new ProductRepository();
$content = new ContentRepository();

$hero = $content->banners('home_hero', current_locale(), 1)[0] ?? null;
$promos = $content->banners('home_promo', current_locale(), 2);
$spotlight = $content->banners('category', current_locale(), 1)[0] ?? null;

$stats = $products->marketplaceStats();
$categories = (new CategoryRepository())->topLevel(12);
$brands = (new BrandRepository())->popular(12);
$featured = $products->featured(8);
$bestSellers = $products->bestSellers(8);
$newArrivals = $products->newArrivals(8);
$onSale = $products->onSale(8);
$bargains = $products->lowestPricedVariations(6);
$testimonials = $content->testimonials(current_locale(), 6);
$faqs = $content->faqs(current_locale(), 6, true);

$heroTitle = (string) ($hero['title'] ?? setting('homepage.hero_title', t('home.hero.title')));
$heroText = (string) ($hero['subtitle'] ?? setting('homepage.hero_text', t('home.hero.subtitle')));
$steps = [1, 2, 3, 4];
$schema = json_encode([
    '@context' => 'https://schema.org',
    '@type' => 'WebSite',
    'name' => (string) setting('brand.name', config('app.name')),
    'url' => url('/'),
    'description' => (string) setting('brand.tagline', ''),
    'inLanguage' => current_locale() === 'bn' ? 'bn' : 'en',
    'potentialAction' => [
        '@type' => 'SearchAction',
        'target' => ['@type' => 'EntryPoint', 'urlTemplate' => url('products?q={search_term_string}')],
        'query-input' => 'required name=search_term_string',
    ],
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
?>
<?php $view->startSection('title', (string) setting('brand.name', config('app.name')) . ' - '
    . (string) setting('brand.tagline', config('app.brand.tagline', ''))); ?>
<?php $view->startSection('meta_description', $heroText); ?>
<?php $view->startSection('canonical', url('/')); ?>
<?php $view->startSection('json_ld', $schema === false ? '' : $schema); ?>
<?php $view->startSection('og_type', 'website'); ?>
<?php $view->endSection(); ?>
<?php $view->startSection('content'); ?>

<?php /* 1. Hero */ ?>
<section class="dx-hero">
    <div class="container dx-hero__grid">
        <div class="dx-hero__copy">
            <p class="dx-eyebrow">
                <i class="bi bi-patch-check" aria-hidden="true"></i>
                <?= e((string) ($hero['badge'] ?? t('common.verified'))) ?>
            </p>
            <h1 class="dx-display"><?= e($heroTitle) ?></h1>
            <p class="dx-hero__text"><?= e($heroText) ?></p>

            <div class="dx-hero__cta">
                <a class="btn btn-dx btn-dx-lg" href="<?= e(link_url((string) ($hero['link'] ?? '/products'))) ?>">
                    <?= e((string) ($hero['button_text'] ?? t('home.hero.primary_cta'))) ?>
                </a>
                <a class="btn btn-dx-ghost" href="#how-it-works">
                    <?= e(t('home.hero.secondary_cta')) ?>
                    <i class="bi bi-arrow-down-short" aria-hidden="true"></i>
                </a>
            </div>

            <ul class="dx-hero__points">
                <?php foreach (['point_1', 'point_2', 'point_3'] as $point): ?>
                    <li>
                        <i class="bi bi-check-circle" aria-hidden="true"></i>
                        <?= e(t('home.hero.' . $point)) ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>

        <div class="dx-planmock" aria-hidden="true">
            <span class="dx-planmock__ring"></span>
            <p class="dx-planmock__title"><?= e((string) ($hero['title'] ?? $heroTitle)) ?></p>
            <?php foreach (array_slice($featured, 0, 3) as $offset => $preview): ?>
                <div class="dx-planmock__row<?= $offset === 0 ? ' dx-planmock__row--on' : '' ?>">
                    <span><?= e((string) $preview['name']) ?></span>
                    <strong><?= e(price((float) $preview['from_price'])) ?></strong>
                </div>
            <?php endforeach; ?>
            <p class="dx-planmock__price">
                <?= e(t('common.from')) ?> <?= e(price((float) ($featured[0]['from_price'] ?? 0))) ?>
            </p>
        </div>
    </div>
</section>

<?php /* 2. Live marketplace figures */ ?>
<section class="dx-section dx-section--tight dx-section--panel">
    <div class="container">
        <ul class="dx-hero__stats">
            <?php
            $statItems = [
                ['key' => 'products', 'value' => $stats['products'], 'icon' => 'bi-box-seam'],
                ['key' => 'plans', 'value' => $stats['plans'], 'icon' => 'bi-sliders'],
                ['key' => 'brands', 'value' => $stats['brands'], 'icon' => 'bi-patch-check'],
                ['key' => 'categories', 'value' => $stats['categories'], 'icon' => 'bi-collection'],
            ];
            if ((int) $stats['delivered'] > 0) {
                $statItems[] = ['key' => 'delivered', 'value' => $stats['delivered'], 'icon' => 'bi-truck'];
            }
            if ((int) $stats['reviews'] > 0) {
                $statItems[] = ['key' => 'reviews', 'value' => $stats['reviews'], 'icon' => 'bi-chat-quote'];
            }
            ?>
            <?php foreach ($statItems as $stat): ?>
                <li class="dx-hero__stat">
                    <i class="bi <?= e($stat['icon']) ?>" aria-hidden="true"></i>
                    <strong><?= e(number_format((int) $stat['value'])) ?></strong>
                    <span><?= e(t('home.stats.' . $stat['key'])) ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
</section>

<?php /* 3. Category shelves */ ?>
<section class="dx-section" id="categories">
    <div class="container">
        <?= $view->component('section-header', [
            'eyebrow' => t('home.categories.eyebrow'),
            'title' => t('home.categories.title'),
            'subtitle' => t('home.categories.subtitle'),
            'cta_url' => url('categories'),
            'cta_label' => t('home.categories.cta'),
        ]) ?>
        <div class="dx-catgrid">
            <?php foreach ($categories as $category): ?>
                <a class="dx-cat-tile" href="<?= e(url('categories/' . $category['slug'])) ?>">
                    <img class="dx-cat-tile__img" src="<?= e(image_url($category['image'] ?? null)) ?>" alt=""
                         loading="lazy" width="800" height="600">
                    <span class="dx-cat-tile__body">
                        <span class="dx-cat-tile__name"><?= e((string) $category['name']) ?></span>
                        <span class="dx-cat-tile__meta">
                            <?= e(choice('common.results_count', (int) $category['products_count'])) ?>
                        </span>
                    </span>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<?php /* 4. Featured plans */ ?>
<?php if ($featured !== []): ?>
    <section class="dx-section dx-section--panel" id="featured">
        <div class="container">
            <?= $view->component('section-header', [
                'eyebrow' => t('home.featured.eyebrow'),
                'title' => t('home.featured.title'),
                'subtitle' => t('home.featured.subtitle'),
                'cta_url' => url('products?sort=popular'),
                'cta_label' => t('common.view_all'),
            ]) ?>
            <div class="dx-grid" data-results>
                <?php foreach ($featured as $product): ?>
                    <?= $view->component('product-card', ['row' => $product]) ?>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
<?php endif; ?>

<?php /* 5. Bestsellers */ ?>
<?php if ($bestSellers !== [] && filter_var(setting('homepage.show_bestsellers', 1), FILTER_VALIDATE_BOOLEAN)): ?>
    <section class="dx-section" id="bestsellers">
        <div class="container">
            <?= $view->component('section-header', [
                'eyebrow' => t('home.bestsellers.eyebrow'),
                'title' => t('home.bestsellers.title'),
                'subtitle' => t('home.bestsellers.subtitle'),
                'cta_url' => url('products?sort=popular'),
                'cta_label' => t('common.browse_all'),
            ]) ?>
            <div class="dx-grid">
                <?php foreach ($bestSellers as $product): ?>
                    <?= $view->component('product-card', ['row' => $product]) ?>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
<?php endif; ?>

<?php /* 6. New arrivals */ ?>
<?php if ($newArrivals !== []): ?>
    <section class="dx-section dx-section--tight" id="new-arrivals">
        <div class="container">
            <?= $view->component('section-header', [
                'eyebrow' => t('home.new_arrivals.eyebrow'),
                'title' => t('home.new_arrivals.title'),
                'subtitle' => t('home.new_arrivals.subtitle'),
                'cta_url' => url('products?sort=newest'),
                'cta_label' => t('common.view_all'),
            ]) ?>
            <div class="dx-grid">
                <?php foreach ($newArrivals as $product): ?>
                    <?= $view->component('product-card', ['row' => $product]) ?>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
<?php endif; ?>

<?php /* 7. Deals: cheapest term per plan, measured against list price */ ?>
<?php if ($bargains !== []): ?>
    <section class="dx-section dx-section--panel" id="deals">
        <div class="container">
            <?= $view->component('section-header', [
                'eyebrow' => t('home.deals.eyebrow'),
                'title' => t('home.deals.title'),
                'subtitle' => t('home.deals.subtitle'),
                'cta_url' => url('products?sort=discount&on_sale=1'),
                'cta_label' => t('home.deals.cta'),
            ]) ?>
            <div class="table-responsive">
                <table class="table dx-dealstable align-middle">
                    <caption class="visually-hidden"><?= e(t('home.deals.title')) ?></caption>
                    <thead>
                        <tr>
                            <th scope="col"><?= e(t('home.deals.column_product')) ?></th>
                            <th scope="col"><?= e(t('home.deals.column_term')) ?></th>
                            <th scope="col" class="text-end"><?= e(t('home.deals.column_list')) ?></th>
                            <th scope="col" class="text-end"><?= e(t('home.deals.column_price')) ?></th>
                            <th scope="col" class="text-end"><?= e(t('home.deals.column_saving')) ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($bargains as $bargain): ?>
                            <?php $off = percent_off($bargain['compare_price'], $bargain['price']); ?>
                            <tr>
                                <th scope="row">
                                    <a href="<?= e(url('products/' . $bargain['slug'])) ?>">
                                        <?= e((string) $bargain['name']) ?>
                                    </a>
                                </th>
                                <td><?= e((string) ($bargain['label'] ?? '')) ?></td>
                                <td class="text-end"><s class="dx-price-was"><?= e(price($bargain['compare_price'])) ?></s></td>
                                <td class="text-end dx-mono-price"><?= e(price($bargain['price'])) ?></td>
                                <td class="text-end">
                                    <span class="dx-chip dx-chip--sale"><?= e($off) ?>%</span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </section>
<?php endif; ?>

<?php /* 8. Campaign banners */ ?>
<?php if ($promos !== []): ?>
    <section class="dx-section dx-section--tight" id="campaigns">
        <div class="container">
            <?= $view->component('section-header', [
                'eyebrow' => t('home.promos.eyebrow'),
                'title' => t('home.promos.title'),
            ]) ?>
            <div class="dx-promo">
                <?php foreach ($promos as $index => $promo): ?>
                    <article class="dx-promo__panel<?= $index % 2 === 1 ? ' dx-promo__panel--mango' : '' ?>">
                        <p class="dx-eyebrow"><?= e((string) ($promo['badge'] ?? '')) ?></p>
                        <h3><?= e((string) $promo['title']) ?></h3>
                        <p><?= e((string) ($promo['subtitle'] ?? '')) ?></p>
                        <?php if (!empty($promo['description'])): ?>
                            <p class="dx-muted"><?= e((string) $promo['description']) ?></p>
                        <?php endif; ?>
                        <a class="btn btn-dx-soft" href="<?= e(link_url((string) ($promo['link'] ?? ''))) ?>">
                            <?= e((string) ($promo['button_text'] ?? t('common.view_details'))) ?>
                        </a>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
<?php endif; ?>

<?php /* 9. How an order works */ ?>
<section class="dx-section" id="how-it-works">
    <div class="container">
        <?= $view->component('section-header', [
            'eyebrow' => t('home.how_it_works.eyebrow'),
            'title' => t('home.how_it_works.title'),
            'subtitle' => t('home.how_it_works.subtitle'),
        ]) ?>
        <ol class="dx-steps">
            <?php foreach ($steps as $step): ?>
                <li class="dx-step">
                    <h3><?= e(t('home.how_it_works.step_' . $step . '_title')) ?></h3>
                    <p><?= e(t('home.how_it_works.step_' . $step . '_text')) ?></p>
                </li>
            <?php endforeach; ?>
        </ol>
        <?php if (filter_var(setting('homepage.show_how_it_works', 1), FILTER_VALIDATE_BOOLEAN)): ?>
            <p class="dx-section__foot">
                <a class="btn btn-dx-ghost" href="<?= e(url('page/how-it-works')) ?>">
                    <?= e(t('nav.how_it_works')) ?>
                    <i class="bi bi-arrow-right" aria-hidden="true"></i>
                </a>
            </p>
        <?php endif; ?>
    </div>
</section>

<?php /* 10. Brands on the shelf */ ?>
<section class="dx-section dx-section--tight dx-section--panel" id="brands">
    <div class="container">
        <?= $view->component('section-header', [
            'eyebrow' => t('home.brands.eyebrow'),
            'title' => t('home.brands.title'),
            'subtitle' => t('home.brands.subtitle'),
            'cta_url' => url('brands'),
            'cta_label' => t('home.brands.cta'),
        ]) ?>
        <div class="dx-brandgrid">
            <?php foreach ($brands as $brand): ?>
                <a class="dx-brand-tile" href="<?= e(url('brands/' . $brand['slug'])) ?>">
                    <img src="<?= e(image_url($brand['logo'] ?? null)) ?>" alt="" loading="lazy" width="400" height="400">
                    <span class="dx-brand-tile__name"><?= e((string) $brand['name']) ?></span>
                    <span class="dx-brand-tile__meta">
                        <?= e(choice('common.results_count', (int) $brand['products_count'])) ?>
                    </span>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<?php /* 11. Discounted terms */ ?>
<?php if ($onSale !== []): ?>
    <section class="dx-section" id="offers">
        <div class="container">
            <?= $view->component('section-header', [
                'eyebrow' => t('home.bargain.eyebrow'),
                'title' => t('home.bargain.title'),
                'subtitle' => t('home.bargain.subtitle'),
                'cta_url' => url('products?on_sale=1'),
                'cta_label' => t('home.bargain.cta'),
            ]) ?>
            <div class="dx-grid">
                <?php foreach ($onSale as $product): ?>
                    <?= $view->component('product-card', ['row' => $product]) ?>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
<?php endif; ?>

<?php /* 12. Category spotlight banner */ ?>
<?php if ($spotlight !== null): ?>
    <section class="dx-section dx-section--tight">
        <div class="container">
            <article class="dx-promo__panel">
                <p class="dx-eyebrow"><?= e((string) ($spotlight['badge'] ?? t('common.verified'))) ?></p>
                <h2><?= e((string) $spotlight['title']) ?></h2>
                <p><?= e((string) ($spotlight['subtitle'] ?? '')) ?></p>
                <a class="btn btn-dx" href="<?= e(link_url((string) ($spotlight['link'] ?? ''))) ?>">
                    <?= e((string) ($spotlight['button_text'] ?? t('common.browse_all'))) ?>
                </a>
            </article>
        </div>
    </section>
<?php endif; ?>

<?php /* 13. Customer voices */ ?>
<?php if ($testimonials !== []): ?>
    <section class="dx-section dx-section--panel" id="reviews">
        <div class="container">
            <?= $view->component('section-header', [
                'eyebrow' => t('home.testimonials.eyebrow'),
                'title' => t('home.testimonials.title'),
                'subtitle' => t('home.testimonials.subtitle'),
            ]) ?>
            <div class="dx-quotegrid">
                <?php foreach ($testimonials as $quote): ?>
                    <figure class="dx-quote">
                        <i class="bi bi-quote dx-quote__mark" aria-hidden="true"></i>
                        <blockquote>
                            <p><?= e((string) $quote['quote']) ?></p>
                        </blockquote>
                        <figcaption class="dx-quote__who">
                            <?php if (!empty($quote['customer_name'])): ?>
                                <strong><?= e((string) $quote['customer_name']) ?></strong>
                            <?php endif; ?>
                            <?php if (!empty($quote['customer_location'])): ?>
                                <span><?= e((string) $quote['customer_location']) ?></span>
                            <?php endif; ?>
                            <?php if (!empty($quote['product_name'])): ?>
                                <a href="<?= e(url('products/' . $quote['product_slug'])) ?>">
                                    <?= e((string) $quote['product_name']) ?>
                                </a>
                            <?php endif; ?>
                        </figcaption>
                        <?= $view->component('rating', ['avg' => $quote['rating'] ?? 0, 'count' => 0]) ?>
                    </figure>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
<?php endif; ?>

<?php /* 14. Frequently asked questions */ ?>
<?php if ($faqs !== []): ?>
    <section class="dx-section" id="faqs">
        <div class="container">
            <?= $view->component('section-header', [
                'eyebrow' => t('home.faqs.eyebrow'),
                'title' => t('home.faqs.title'),
                'subtitle' => t('home.faqs.subtitle'),
                'cta_url' => url('faqs'),
                'cta_label' => t('home.faqs.cta'),
            ]) ?>
            <div class="dx-accordion">
                <?php foreach ($faqs as $index => $faq): ?>
                    <details class="dx-accordion__item" <?= $index === 0 ? 'open' : '' ?>>
                        <summary><?= e((string) $faq['question']) ?></summary>
                        <div class="dx-prose">
                            <p><?= e((string) $faq['answer']) ?></p>
                        </div>
                    </details>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
<?php endif; ?>

<?php /* 15. Trust points and the support call-out */ ?>
<section class="dx-section dx-section--tight dx-section--panel">
    <div class="container">
        <ul class="dx-trust">
            <?php foreach ([1, 2, 3, 4] as $index): ?>
                <li class="dx-trust__item">
                    <h2><?= e(t('home.trust.item_' . $index . '_title')) ?></h2>
                    <p><?= e(t('home.trust.item_' . $index . '_text')) ?></p>
                </li>
            <?php endforeach; ?>
        </ul>

        <div class="dx-promo__panel">
            <h2 class="dx-display"><?= e(t('home.support_cta.title')) ?></h2>
            <p><?= e(t('home.support_cta.subtitle')) ?></p>
            <div class="dx-hero__cta">
                <a class="btn btn-dx" href="<?= e(url('contact')) ?>">
                    <?= e(t('home.support_cta.primary_cta')) ?>
                </a>
                <a class="btn btn-dx-ghost" href="<?= e(url('products')) ?>">
                    <?= e(t('home.support_cta.secondary_cta')) ?>
                </a>
            </div>
        </div>
    </div>
</section>

<?php $view->stopSection(); ?>
<?= $view->partial('layouts.app') ?>
