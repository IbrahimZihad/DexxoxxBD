<?php

/**
 * Price line: current amount, struck-through list price and the saving chip.
 *
 * @var float|null $price
 * @var float|null $compare
 * @var int|null   $percent
 * @var string|null $size
 * @var string|null $note
 */

$amount = (float) ($price ?? 0);
$list = $compare === null ? null : (float) $compare;
$off = (int) ($percent ?? 0);
$showCompare = $list !== null && $list > $amount;
?>
<p class="dx-price<?= ($size ?? '') === 'lg' ? ' dx-price--lg' : '' ?>">
    <span class="dx-price__now"><?= e(price($amount)) ?></span>
    <?php if ($showCompare): ?>
        <s class="dx-price-was"><?= e(price($list)) ?></s>
    <?php endif; ?>
    <?php if ($showCompare && $off > 0): ?>
        <span class="dx-chip dx-chip--off">&minus;<?= e($off) ?>%</span>
    <?php endif; ?>
    <?php if (!empty($note)): ?>
        <span class="dx-price__note"><?= e((string) $note) ?></span>
    <?php endif; ?>
</p>
