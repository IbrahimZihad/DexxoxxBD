<?php

/**
 * Product detail page: gallery, buy box with the real term selector, tabbed
 * evidence (features, delivery, FAQs, reviews) and a related strip.
 *
 * @var array<string, mixed> $product
 * @var list<array<string, mixed>> $plans
 * @var list<array<string, mixed>> $images
 * @var list<array<string, mixed>> $features
 * @var list<array<string, mixed>> $faqs
 * @var list<array<string, mixed>> $reviews
 * @var array<int, int> $breakdown       approved reviews per star, from ratingBreakdown()
 * @var list<array<string, mixed>> $related
 * @var list<array{label: string, url?: string}> $breadcrumbs
 */

$slug = (string) $product['slug'];
$name = (string) $product['name'];
$href = url('products/' . $slug);
$primary = $images[0] ?? null;
$mainImage = image_url($primary['path'] ?? $product['image'] ?? null);
$gallery = $images === [] ? [['path' => $product['image'] ?? null]] : $images;
$cheapest = null;
foreach ($plans as $index => $plan) {
    if ($cheapest === null || (float) $plan['price'] < (float) $cheapest['price']) {
        $cheapest = $index;
    }
}
$delivery = (string) ($product['delivery_method'] ?? 'manual');

$view->extend('layouts.app');
$view->setSection('title', (string) ($product['meta_title'] ?? $name));
$view->setSection('meta_description', (string) ($product['meta_description']
    ?? mb_substr((string) $product['short_description'], 0, 155)));
$view->setSection('canonical', $href);
$view->setSection('meta_image', $mainImage);
$view->setSection('og_type', 'product');
$offers = array_map(
    static fn (array $plan): array => [
        '@type' => 'Offer',
        'name' => (string) $plan['label'],
        'price' => number_format((float) $plan['price'], 2, '.', ''),
        'priceCurrency' => (string) config('app.currency_code', 'BDT'),
        'availability' => (int) $plan['stock'] === 0
            ? 'https://schema.org/OutOfStock'
            : 'https://schema.org/InStock',
        'sku' => (string) $plan['sku'],
        'url' => $href,
    ],
    $plans
);
$schema = json_encode([
    '@context' => 'https://schema.org',
    '@type' => 'Product',
    'name' => $name,
    'description' => (string) $product['short_description'],
    'image' => $mainImage,
    'sku' => (string) ($plans[0]['sku'] ?? $slug),
    'brand' => ['@type' => 'Brand', 'name' => (string) ($product['brand_name'] ?? '')],
    'category' => (string) ($product['category_name'] ?? ''),
    'aggregateRating' => (int) $product['rating_count'] > 0 ? [
        '@type' => 'AggregateRating',
        'ratingValue' => number_format((float) $product['rating_avg'], 1, '.', ''),
        'reviewCount' => (int) $product['rating_count'],
    ] : null,
    'offers' => $offers,
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
$schema = preg_replace('/"aggregateRating": null,\n\s*/', '', (string) $schema) ?? $schema;
$view->setSection('json_ld', $schema);
?>
<div class="container">
    <?= $view->component('breadcrumb', ['items' => $breadcrumbs]) ?>

    <div class="dx-product" data-plan-selector>
        <div class="dx-product__media">
            <div class="dx-gallery__main">
                <img src="<?= e($mainImage) ?>" alt="<?= e(t('product.image_for', ['name' => $name])) ?>"
                     data-gallery-main width="800" height="600">
                <?php if (!empty($product['badge'])): ?>
                    <span class="dx-corner"><?= e((string) $product['badge']) ?></span>
                <?php endif; ?>
                <?php if ((int) ($product['is_new_arrival'] ?? 0) === 1): ?>
                    <span class="dx-chip dx-chip--new"><?= e(t('common.badges.new')) ?></span>
                <?php endif; ?>
            </div>

            <?php if (count($gallery) > 1): ?>
                <div class="dx-gallery__thumbs" data-gallery>
                    <?php foreach ($gallery as $index => $shot): ?>
                        <button type="button" class="dx-gallery__thumb<?= $index === 0 ? ' is-active' : '' ?>"
                                data-full="<?= e(image_url($shot['path'] ?? null)) ?>"
                                data-alt="<?= e(t('product.image_for', ['name' => $name])) ?>"
                                aria-label="<?= e(t('product.thumbnail', ['index' => $index + 1])) ?>"
                                aria-current="<?= $index === 0 ? 'true' : 'false' ?>">
                            <img src="<?= e(image_url($shot['path'] ?? null)) ?>" alt="" loading="lazy"
                                 width="120" height="90">
                        </button>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="dx-gallery__thumbs" data-gallery>
                    <button type="button" class="dx-gallery__thumb is-active"
                            data-full="<?= e($mainImage) ?>"
                            data-alt="<?= e(t('product.image_for', ['name' => $name])) ?>"
                            aria-current="true" aria-label="<?= e(t('product.thumbnail', ['index' => 1])) ?>">
                        <img src="<?= e($mainImage) ?>" alt="" loading="lazy" width="120" height="90">
                    </button>
                </div>
            <?php endif; ?>
        </div>

        <div class="dx-buybox">
            <p class="dx-eyebrow">
                <?php if (!empty($product['brand_name'])): ?>
                    <a href="<?= e(url('brands/' . $product['brand_slug'])) ?>"><?= e((string) $product['brand_name']) ?></a>
                    <span aria-hidden="true">&middot;</span>
                <?php endif; ?>
                <a href="<?= e(url('categories/' . $product['category_slug'])) ?>">
                    <?= e((string) $product['category_name']) ?>
                </a>
            </p>

            <h1 class="dx-display"><?= e($name) ?></h1>
            <?= $view->component('rating', [
                'avg' => $product['rating_avg'] ?? 0,
                'count' => $product['rating_count'] ?? 0,
                'caption' => choice('product.sold', (int) ($product['sales_count'] ?? 0)),
            ]) ?>
            <p class="dx-buybox__short"><?= e((string) $product['short_description']) ?></p>

            <div class="dx-buybox__price">
                <p class="dx-price dx-price--lg">
                    <span class="dx-mono-price dx-price__now" data-plan-price>&nbsp;</span>
                    <s class="dx-price-was" data-plan-compare hidden></s>
                    <span class="dx-chip dx-chip--sale" data-plan-saving hidden></span>
                </p>
                <p class="dx-muted" data-plan-duration>&nbsp;</p>
            </div>

            <form method="post" action="<?= e(url('cart/add')) ?>" data-cart-add class="dx-buybox__form">
                <?= csrf_field() ?>
                <input type="hidden" name="product_id" value="<?= e((int) $product['id']) ?>">

                <fieldset class="dx-plans">
                    <legend class="dx-plans__label"><?= e(t('product.plans')) ?></legend>
                    <?php if ($plans === []): ?>
                        <p class="dx-muted"><?= e(t('product.unavailable')) ?></p>
                    <?php endif; ?>
                    <?php foreach ($plans as $index => $plan): ?>
                        <?= $view->component('plan-option', [
                            'plan' => $plan,
                            'selected' => $index === $cheapest,
                        ]) ?>
                    <?php endforeach; ?>
                </fieldset>

                <div class="dx-buybox__row">
                    <label class="dx-field">
                        <span><?= e(t('common.quantity')) ?></span>
                        <?= $view->component('quantity', [
                            'value' => (int) ($plans[$cheapest]['min_quantity'] ?? 1),
                            'min' => (int) ($plans[$cheapest]['min_quantity'] ?? 1),
                            'max' => (int) ($plans[$cheapest]['max_quantity'] ?? 10),
                        ]) ?>
                    </label>
                    <p class="dx-muted dx-buybox__stock" data-plan-stock>&nbsp;</p>
                </div>

                <button class="btn btn-dx btn-dx-lg" type="submit" <?= $plans === [] ? 'disabled' : '' ?>>
                    <i class="bi bi-bag-plus" aria-hidden="true"></i>
                    <?= e(t('common.add_to_cart')) ?>
                </button>
                <p class="dx-muted dx-buybox__sku">
                    <?= e(t('product.sku')) ?>: <code data-plan-sku>&nbsp;</code>
                </p>
            </form>

            <ul class="dx-buybox__trust">
                <li>
                    <i class="bi bi-shield-check" aria-hidden="true"></i>
                    <?= e(t('product.delivery.' . $delivery)) ?>
                </li>
                <?php if (!empty($product['warranty_info'])): ?>
                    <li>
                        <i class="bi bi-patch-check" aria-hidden="true"></i>
                        <?= e((string) $product['warranty_info']) ?>
                    </li>
                <?php endif; ?>
                <?php if (!empty($product['account_type'])): ?>
                    <li>
                        <i class="bi bi-person-badge" aria-hidden="true"></i>
                        <?= e((string) $product['account_type']) ?>
                    </li>
                <?php endif; ?>
                <li>
                    <i class="bi bi-headset" aria-hidden="true"></i>
                    <?= e((string) ($product['support_info'] ?? t('footer.support_hours'))) ?>
                </li>
            </ul>

            <p class="dx-buybox__share">
                <button class="btn btn-dx-ghost" type="button"
                        data-copy="<?= e($href) ?>">
                    <i class="bi bi-link-45deg" aria-hidden="true"></i>
                    <?= e(t('product.copy_link')) ?>
                </button>
                <a class="btn btn-dx-ghost" href="<?= e(url('faqs')) ?>">
                    <?= e(t('nav.faqs')) ?>
                </a>
            </p>
        </div>
    </div>

    <section class="dx-section dx-section--tight">
        <div class="container">
            <div class="dx-tabs" data-tabs role="tablist">
                <?php
                $tabs = [
                    'details' => t('product.tabs.details'),
                    'delivery' => t('product.tabs.delivery'),
                    'faqs' => t('product.tabs.faqs'),
                    'reviews' => t('product.tabs.reviews'),
                ];
                ?>
                <?php foreach ($tabs as $key => $label): ?>
                    <button class="dx-tabs__btn<?= $key === 'details' ? ' is-active' : '' ?>" type="button"
                            role="tab" data-tab="<?= e((string) $key) ?>"
                            aria-selected="<?= $key === 'details' ? 'true' : 'false' ?>"
                            aria-controls="tab-<?= e((string) $key) ?>">
                        <?= e((string) $label) ?>
                    </button>
                <?php endforeach; ?>
            </div>

            <div class="dx-tabpanels">
                <div class="dx-tabpanel" id="tab-details" role="tabpanel">
                    <div class="dx-prose">
                        <?php foreach (preg_split('/\R{2,}/u', (string) $product['description']) ?: [] as $paragraph): ?>
                            <p><?= e(trim((string) $paragraph)) ?></p>
                        <?php endforeach; ?>
                    </div>

                    <?php if ($features !== []): ?>
                        <h2 class="dx-h4"><?= e(t('product.features')) ?></h2>
                        <ul class="dx-feature-list">
                            <?php foreach ($features as $feature): ?>
                                <li>
                                    <i class="bi <?= e((string) ($feature['icon'] ?: 'bi-check-lg')) ?>"
                                       aria-hidden="true"></i>
                                    <?= e((string) $feature['label']) ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>

                    <h2 class="dx-h4"><?= e(t('product.specifications')) ?></h2>
                    <dl class="dx-specs">
                        <dt><?= e(t('nav.brands')) ?></dt>
                        <dd><?= e((string) ($product['brand_name'] ?? '')) ?></dd>
                        <dt><?= e(t('common.category')) ?></dt>
                        <dd><a href="<?= e(url('categories/' . $product['category_slug'])) ?>">
                            <?= e((string) ($product['category_name'] ?? '')) ?></a></dd>
                        <dt><?= e(t('common.delivery')) ?></dt>
                        <dd><?= e(t('product.delivery.' . $delivery)) ?></dd>
                        <dt><?= e(t('product.plans')) ?></dt>
                        <dd><?= e(choice('common.results_count', count($plans))) ?></dd>
                        <dt><?= e(t('common.availability')) ?></dt>
                        <dd>
                            <?php $units = array_sum(array_map(
                                static fn (array $plan): int => max(0, (int) $plan['stock']),
                                $plans
                            )); ?>
                            <?= e($units > 0 ? t('common.in_stock') : t('common.out_of_stock')) ?>
                        </dd>
                        <dt><?= e(t('product.views')) ?></dt>
                        <dd><?= e(number_format((int) ($product['view_count'] ?? 0))) ?></dd>
                    </dl>
                </div>

                <div class="dx-tabpanel" id="tab-delivery" role="tabpanel" hidden>
                    <h2 class="dx-h4"><?= e(t('product.delivery')) ?></h2>
                    <p><?= e(t('product.delivery.' . $delivery)) ?></p>
                    <?php if (!empty($product['activation_instructions'])): ?>
                        <h2 class="dx-h4"><?= e(t('product.activation')) ?></h2>
                        <div class="dx-prose">
                            <?php
                            $lines = preg_split('/\R+/u', (string) $product['activation_instructions']) ?: [];
                            ?>
                            <ol>
                                <?php foreach ($lines as $line): ?>
                                    <li><?= e(trim((string) $line)) ?></li>
                                <?php endforeach; ?>
                            </ol>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($product['warranty_info'])): ?>
                        <h2 class="dx-h4"><?= e(t('product.warranty')) ?></h2>
                        <p><?= e((string) $product['warranty_info']) ?></p>
                    <?php endif; ?>
                    <?php if (!empty($product['support_info'])): ?>
                        <h2 class="dx-h4"><?= e(t('product.support')) ?></h2>
                        <p><?= e((string) $product['support_info']) ?></p>
                    <?php endif; ?>
                    <?php foreach ($plans as $plan): ?>
                        <?php if (!empty($plan['notes'])): ?>
                            <p class="dx-muted">
                                <?= e((string) $plan['label']) ?>: <?= e((string) $plan['notes']) ?>
                            </p>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>

                <div class="dx-tabpanel" id="tab-faqs" role="tabpanel" hidden>
                    <?php if ($faqs === []): ?>
                        <p class="dx-muted"><?= e(t('common.empty_text')) ?></p>
                    <?php else: ?>
                        <div class="dx-accordion">
                            <?php foreach ($faqs as $index => $faq): ?>
                                <details class="dx-accordion__item" <?= $index === 0 ? 'open' : '' ?>>
                                    <summary><?= e((string) $faq['question']) ?></summary>
                                    <div class="dx-prose"><p><?= e((string) $faq['answer']) ?></p></div>
                                </details>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="dx-tabpanel" id="tab-reviews" role="tabpanel" hidden>
                    <?php if ($reviews === []): ?>
                        <p class="dx-muted"><?= e(t('product.no_reviews')) ?></p>
                        <p class="dx-hint"><?= e(t('product.review_hint')) ?></p>
                    <?php else: ?>
                        <?= $view->component('rating', [
                            'avg' => $product['rating_avg'] ?? 0,
                            'count' => $product['rating_count'] ?? 0,
                        ]) ?>
                        <?php if ($breakdown !== []): ?>
                            <?php $reviewTotal = array_sum($breakdown); ?>
                            <ul class="dx-breakdown" aria-label="<?= e(t('product.rating_breakdown')) ?>">
                                <?php for ($stars = 5; $stars >= 1; $stars--): ?>
                                    <?php
                                    $bucket = (int) ($breakdown[$stars] ?? 0);
                                    $share = $reviewTotal > 0 ? $bucket * 100 / $reviewTotal : 0.0;
                                    ?>
                                    <li>
                                        <span class="dx-breakdown__label"><?= e($stars) ?> <i class="bi bi-star-fill"
                                               aria-hidden="true"></i></span>
                                        <span class="dx-breakdown__bar">
                                            <span class="dx-breakdown__fill"
                                                  style="width: <?= e(number_format($share, 1)) ?>%"></span>
                                        </span>
                                        <span class="dx-breakdown__count"><?= e($bucket) ?></span>
                                    </li>
                                <?php endfor; ?>
                            </ul>
                        <?php endif; ?>
                        <?php foreach ($reviews as $review): ?>
                            <article class="dx-review">
                                <header>
                                    <strong><?= e((string) ($review['author_name'] ?? 'Customer')) ?></strong>
                                    <?= $view->component('rating', [
                                        'avg' => $review['rating'] ?? 0,
                                        'count' => 0,
                                    ]) ?>
                                    <time datetime="<?= e((string) ($review['created_at'] ?? '')) ?>">
                                        <?= e((string) ($review['created_at'] ?? '')) ?>
                                    </time>
                                </header>
                                <?php if (!empty($review['title'])): ?>
                                    <h3><?= e((string) $review['title']) ?></h3>
                                <?php endif; ?>
                                <p><?= e((string) ($review['body'] ?? '')) ?></p>
                                <?php if (!empty($review['is_verified_purchase'])): ?>
                                    <p class="dx-chip dx-chip--instant">
                                        <?= e(t('common.verified')) ?>
                                    </p>
                                <?php endif; ?>
                            </article>
                        <?php endforeach; ?>
                        <p class="dx-hint"><?= e(t('product.review_hint')) ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <?php if ($related !== []): ?>
        <section class="dx-section dx-section--panel">
            <div class="container">
                <?= $view->component('section-header', ['title' => t('product.related')]) ?>
                <div class="dx-grid">
                    <?php foreach ($related as $item): ?>
                        <?= $view->component('product-card', ['row' => $item]) ?>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
    <?php endif; ?>
</div>
