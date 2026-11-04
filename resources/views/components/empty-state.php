<?php

/**
 * Empty state for a shelf with no matching plans.
 *
 * @var string|null $title
 * @var string|null $text
 * @var string|null $ctaUrl
 * @var string|null $ctaLabel
 * @var string|null $icon
 */
?>
<div class="dx-empty">
    <i class="bi <?= e($icon ?? 'bi-inboxes') ?>" aria-hidden="true"></i>
    <h2><?= e($title ?? t('common.empty_title')) ?></h2>
    <p><?= e($text ?? t('common.empty_text')) ?></p>
    <?php if (!empty($ctaUrl)): ?>
        <a class="btn btn-dx" href="<?= e((string) $ctaUrl) ?>">
            <?= e($ctaLabel ?? t('common.browse_all')) ?>
        </a>
    <?php endif; ?>
</div>
