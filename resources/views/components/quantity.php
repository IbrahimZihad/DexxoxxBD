<?php

/**
 * Quantity stepper. The input stays a real number field so the form works
 * without JavaScript; the buttons only clamp it to the term's own limits.
 *
 * @var string|null $name
 * @var int|null $value
 * @var int|null $min
 * @var int|null $max
 * @var string|null $id
 */

$minimum = max(1, (int) ($min ?? 1));
$maximum = max($minimum, (int) ($max ?? 10));
$current = min($maximum, max($minimum, (int) ($value ?? $minimum)));
$field = (string) ($name ?? 'quantity');
$inputId = (string) ($id ?? 'dx-qty-' . $field);
?>
<div class="dx-qty">
    <button class="dx-qty__btn" type="button" data-qty-step="-1"
            aria-label="<?= e(t('common.quantity') . ' -1') ?>">
        <i class="bi bi-dash" aria-hidden="true"></i>
    </button>
    <input class="dx-qty__input" id="<?= e($inputId) ?>" name="<?= e($field) ?>" type="number"
           inputmode="numeric" min="<?= e($minimum) ?>" max="<?= e($maximum) ?>" step="1"
           value="<?= e($current) ?>" aria-label="<?= e(t('common.quantity')) ?>">
    <button class="dx-qty__btn" type="button" data-qty-step="1"
            aria-label="<?= e(t('common.quantity') . ' +1') ?>">
        <i class="bi bi-plus" aria-hidden="true"></i>
    </button>
</div>
