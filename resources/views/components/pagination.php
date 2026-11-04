<?php

/**
 * Pagination that keeps every other query parameter. The window collapses to the
 * first and last page with the current neighbourhood, so a 30-page catalogue
 * still renders seven links instead of thirty.
 *
 * @var int $page
 * @var int $lastPage
 * @var int|null $total
 */

$current = max(1, (int) ($page ?? 1));
$last = max(1, (int) ($lastPage ?? 1));
if ($last <= 1) {
    return;
}

$window = [];
for ($candidate = max(1, $current - 2); $candidate <= min($last, $current + 2); $candidate++) {
    $window[] = $candidate;
}
if (!in_array(1, $window, true)) {
    array_unshift($window, 1);
}
if (!in_array($last, $window, true)) {
    $window[] = $last;
}
sort($window);
?>
<nav class="dx-pager" aria-label="<?= e(choice('common.results_count', (int) ($total ?? 0))) ?>">
    <ul class="pagination">
        <li class="page-item<?= $current <= 1 ? ' disabled' : '' ?>">
            <a class="page-link" href="<?= e($current <= 1 ? '#' : query_url(['page' => $current - 1])) ?>"
               rel="prev" aria-disabled="<?= $current <= 1 ? 'true' : 'false' ?>">
                <i class="bi bi-chevron-left" aria-hidden="true"></i>
                <?= e(t('common.previous')) ?>
            </a>
        </li>

        <?php $previous = 0; ?>
        <?php foreach ($window as $number): ?>
            <?php if ($number - $previous > 1): ?>
                <li class="page-item page-item__gap" aria-hidden="true"><span class="page-link">&hellip;</span></li>
            <?php endif; ?>
            <?php $previous = $number; ?>
            <li class="page-item<?= $number === $current ? ' active' : '' ?>">
                <?php if ($number === $current): ?>
                    <span class="page-link" aria-current="page"><?= e($number) ?></span>
                <?php else: ?>
                    <a class="page-link" href="<?= e(query_url(['page' => $number])) ?>"><?= e($number) ?></a>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>

        <li class="page-item<?= $current >= $last ? ' disabled' : '' ?>">
            <a class="page-link" href="<?= e($current >= $last ? '#' : query_url(['page' => $current + 1])) ?>"
               rel="next" aria-disabled="<?= $current >= $last ? 'true' : 'false' ?>">
                <?= e(t('common.next')) ?>
                <i class="bi bi-chevron-right" aria-hidden="true"></i>
            </a>
        </li>
    </ul>
    <p class="dx-pager__meta">
        <?= e(t('common.page_of', ['current' => $current, 'last' => $last])) ?>
    </p>
</nav>
