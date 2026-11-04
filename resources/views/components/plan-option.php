<?php

/**
 * One purchasable term inside the plan selector.
 *
 * The numbers the buy box shows travel as data attributes, but nothing trusts
 * them: the same figures are re-read from MySQL when the cart is written.
 *
 * @var array<string, mixed> $plan
 * @var bool|null $selected
 */

$term = $plan ?? [];
$id = (int) ($term['id'] ?? 0);
$price = (float) ($term['price'] ?? 0);
$list = $term['compare_price'] === null ? null : (float) $term['compare_price'];
$stock = (int) ($term['stock'] ?? 0);
$soldOut = $stock === 0;
$selected = (bool) ($selected ?? false);
$delivery = (string) ($term['delivery_method'] ?? 'manual');
$durationLabel = (string) ($term['duration_label'] ?? '');
$off = $list !== null && $list > $price ? percent_off($list, $price) : 0;
?>
<label class="dx-plan-option<?= $soldOut ? ' is-unavailable' : '' ?><?= $selected ? ' is-selected' : '' ?>"
       data-plan-option>
    <input class="visually-hidden" type="radio" name="variation_id" value="<?= e($id) ?>"
           <?= $selected ? 'checked' : '' ?> <?= $soldOut ? 'disabled' : '' ?>
           aria-describedby="dx-plan-summary"
           data-price="<?= e($price) ?>"
           data-compare-price="<?= e((string) ($list ?? '')) ?>"
           data-duration-label="<?= e($durationLabel) ?>"
           data-sku="<?= e((string) ($term['sku'] ?? '')) ?>"
           data-stock="<?= e($stock) ?>"
           data-min-quantity="<?= e((int) ($term['min_quantity'] ?? 1)) ?>"
           data-max-quantity="<?= e((int) ($term['max_quantity'] ?? 10)) ?>">

    <span class="dx-plan-option__top">
        <strong><?= e((string) ($term['label'] ?? '')) ?></strong>
        <span class="dx-plan-option__price">
            <?= e(price($price)) ?>
            <?php if ($off > 0): ?>
                <em class="dx-chip dx-chip--off">&minus;<?= e($off) ?>%</em>
            <?php endif; ?>
        </span>
    </span>

    <span class="dx-plan-option__meta">
        <?php if ($durationLabel !== ''): ?>
            <span><?= e($durationLabel) ?></span>
        <?php endif; ?>
        <?php if (!empty($term['device_limit'])): ?>
            <span><?= e(t('product.device_limit', ['count' => (int) $term['device_limit']])) ?></span>
        <?php endif; ?>
        <?php if (!empty($term['account_type'])): ?>
            <span><?= e((string) $term['account_type']) ?></span>
        <?php endif; ?>
        <span class="dx-chip<?= str_starts_with($delivery, 'auto_') ? ' dx-chip--instant' : '' ?>">
            <?= e(t('product.delivery.' . $delivery)) ?>
        </span>
        <?php if ($list !== null && $list > $price): ?>
            <s class="dx-price-was"><?= e(price($list)) ?></s>
        <?php endif; ?>
        <?php if ($soldOut): ?>
            <span class="dx-plan-option__note"><?= e(t('product.sold_out')) ?></span>
        <?php elseif ($stock > 0 && $stock <= 5): ?>
            <span class="dx-plan-option__note"><?= e(t('common.low_stock', ['count' => $stock])) ?></span>
        <?php endif; ?>
    </span>
</label>
