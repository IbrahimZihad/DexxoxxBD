<?php

/**
 * Category rail under the header: the twelve shelves, scrollable, each with its
 * live plan count. Rendered from the database so a new shelf appears without a
 * template change.
 */

use App\Repositories\CategoryRepository;

$shelves = (new CategoryRepository())->topLevel(12);
?>
<nav class="dx-catstrip" aria-label="<?= e(t('nav.all_categories')) ?>">
    <div class="container">
        <ul class="dx-catstrip__list">
            <li>
                <a class="dx-catstrip__link<?= is_current_path('products') ? ' is-active' : '' ?>"
                   href="<?= e(url('products')) ?>">
                    <i class="bi bi-grid-1x2" aria-hidden="true"></i>
                    <?= e(t('nav.products')) ?>
                </a>
            </li>
            <?php foreach ($shelves as $shelf): ?>
                <li>
                    <a class="dx-catstrip__link<?= is_current_path('categories/' . $shelf['slug']) ? ' is-active' : '' ?>"
                       href="<?= e(url('categories/' . $shelf['slug'])) ?>">
                        <i class="bi <?= e((string) ($shelf['icon'] ?: 'bi-tag')) ?>" aria-hidden="true"></i>
                        <?= e((string) $shelf['name']) ?>
                        <span class="dx-catstrip__count"><?= e((int) $shelf['products_count']) ?></span>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
</nav>
