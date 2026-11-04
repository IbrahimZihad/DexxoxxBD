<?php

/**
 * Star rating. Filled stars are drawn as an image-like row and the numeric score
 * is carried in text for screen readers and for shoppers who cannot see colour.
 *
 * @var float|null $avg
 * @var int|null   $count
 * @var string|null $caption
 */

$score = round((float) ($avg ?? 0), 1);
$total = (int) ($count ?? 0);
$fullStars = (int) floor($score);
$half = $score - $fullStars >= 0.25 && $score - $fullStars < 0.75;
$extra = $score - $fullStars >= 0.75 ? 1 : 0;
$fullStars += $extra;
?>
<p class="dx-rating">
    <span class="dx-rating__stars" aria-hidden="true">
        <?php for ($i = 0; $i < 5; $i++): ?>
            <?php if ($i < $fullStars): ?>
                <i class="bi bi-star-fill"></i>
            <?php elseif ($i === $fullStars && $half): ?>
                <i class="bi bi-star-half"></i>
            <?php else: ?>
                <i class="bi bi-star"></i>
            <?php endif; ?>
        <?php endfor; ?>
    </span>
    <?php if ($total > 0): ?>
        <span class="dx-rating__score"><?= e(number_format($score, 1)) ?></span>
        <span class="dx-rating__count">
            <?= e(choice('product.reviews_count', $total)) ?>
        </span>
    <?php else: ?>
        <span class="dx-rating__count"><?= e(t('product.no_reviews')) ?></span>
    <?php endif; ?>
    <?php if (!empty($caption)): ?>
        <span class="dx-rating__caption"><?= e((string) $caption) ?></span>
    <?php endif; ?>
</p>
