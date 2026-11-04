<?php

/**
 * Breadcrumb trail; also emitted as JSON-LD by pages that pass the same items.
 *
 * @var list<array{label: string, url?: string}>|null $items
 */

$trail = $items ?? [];
if ($trail === []) {
    return;
}
$lastIndex = count($trail) - 1;
?>
<nav class="dx-breadcrumb" aria-label="<?= e(t('nav.home')) ?>">
    <ol>
        <?php foreach ($trail as $index => $crumb): ?>
            <li>
                <?php if ($index === $lastIndex || empty($crumb['url'])): ?>
                    <span aria-current="page"><?= e((string) $crumb['label']) ?></span>
                <?php else: ?>
                    <a href="<?= e((string) $crumb['url']) ?>"><?= e((string) $crumb['label']) ?></a>
                    <i class="bi bi-chevron-right" aria-hidden="true"></i>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ol>
</nav>
