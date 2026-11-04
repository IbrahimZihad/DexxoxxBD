<?php

/**
 * Catalogue card: artwork, term price, rating and the two actions a shopper takes
 * before checkout. It reads the row shape both summaryQuery() and paginate()
 * return, so the homepage strips and the catalog grid never diverge.
 *
 * @var array<string, mixed> $row
 */

$product = $row ?? [];
$slug = (string) ($product['slug'] ?? '');
$name = (string) ($product['name'] ?? '');
$href = url('products/' . $slug);
$fromPrice = (float) ($product['from_price'] ?? 0);
$percent = (int) ($product['percent_off'] ?? 0);
$available = (bool) ($product['available'] ?? true);
$delivery = (string) ($product['delivery_method'] ?? '');
$badge = trim((string) ($product['badge'] ?? ''));
$isNew = (int) ($product['is_new_arrival'] ?? 0) === 1;
?>
<article class="dx-card">
    <div class="dx-card__media">
        <a href="<?= e($href) ?>" tabindex="-1" aria-hidden="true">
            <img src="<?= e(image_url($product['image'] ?? null)) ?>" alt="" loading="lazy" width="800" height="600">
        </a>

        <?php if ($badge !== ''): ?>
            <span class="dx-corner"><?= e($badge) ?></span>
        <?php elseif ($isNew): ?>
            <span class="dx-corner"><?= e(t('common.badges.new')) ?></span>
        <?php endif; ?>

        <?php if ($percent > 0): ?>
            <span class="dx-chip dx-chip--sale"><?= e(t('common.save')) ?> <?= e($percent) ?>%</span>
        <?php elseif (!$available): ?>
            <span class="dx-chip"><?= e(t('common.out_of_stock')) ?></span>
        <?php endif; ?>
    </div>

    <div class="dx-card__body">
        <h3 class="dx-card__title">
            <a href="<?= e($href) ?>"><?= e($name) ?></a>
        </h3>

        <p class="dx-card__meta">
            <?php if (!empty($product['brand_name'])): ?>
                <a href="<?= e(url('brands/' . $product['brand_slug'])) ?>"><?= e((string) $product['brand_name']) ?></a>
                <span aria-hidden="true">&middot;</span>
            <?php endif; ?>
            <?php if (!empty($product['category_name'])): ?>
                <a href="<?= e(url('categories/' . $product['category_slug'])) ?>">
                    <?= e((string) $product['category_name']) ?>
                </a>
            <?php endif; ?>
        </p>

        <p class="dx-card__text"><?= e((string) ($product['short_description'] ?? '')) ?></p>

        <?= $view->component('rating', ['avg' => $product['rating_avg'] ?? 0, 'count' => $product['rating_count'] ?? 0]) ?>
    </div>

    <div class="dx-card__foot">
        <?php if ($fromPrice > 0): ?>
            <p class="dx-price">
                <span class="dx-mono-price dx-price__now"><?= e(price($fromPrice)) ?></span>
                <?php if (!empty($product['from_compare_price'])): ?>
                    <s class="dx-price-was"><?= e(price((float) $product['from_compare_price'])) ?></s>
                <?php endif; ?>
            </p>
        <?php else: ?>
            <p class="dx-muted"><?= e(t('product.select_plan')) ?></p>
        <?php endif; ?>

        <?php if (str_starts_with($delivery, 'auto_')): ?>
            <span class="dx-chip dx-chip--instant">
                <i class="bi bi-lightning-charge" aria-hidden="true"></i>
                <?= e(t('common.instant_delivery')) ?>
            </span>
        <?php endif; ?>

        <div class="dx-card__actions">
            <a class="btn btn-pill btn-dx-soft" href="<?= e($href) ?>"
               data-quick-view data-url="<?= e(url('api/products/' . $slug . '/quick-view')) ?>">
                <i class="bi bi-eye" aria-hidden="true"></i>
                <?= e(t('common.quick_view')) ?>
            </a>
            <a class="btn btn-pill btn-dx" href="<?= e($href) ?>">
                <?= e($available ? t('common.view_plan') : t('common.view_details')) ?>
            </a>
        </div>
    </div>
</article>
