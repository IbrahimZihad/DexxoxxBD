<?php

/**
 * Section heading used by every homepage strip and listing page.
 *
 * @var string|null $eyebrow
 * @var string|null $title
 * @var string|null $subtitle
 * @var string|null $ctaUrl
 * @var string|null $ctaLabel
 * @var string|null $titleId
 */

if (($title ?? '') === '') {
    return;
}
?>
<div class="dx-section-head">
    <div>
        <?php if (!empty($eyebrow)): ?>
            <p class="dx-eyebrow"><?= e((string) $eyebrow) ?></p>
        <?php endif; ?>
        <<?php echo $heading ?? 'h2'; ?> class="dx-section-head__title"
           <?php if (!empty($titleId)): ?>id="<?= e((string) $titleId) ?>"<?php endif; ?>>
            <?= e((string) $title) ?>
        </<?php echo $heading ?? 'h2'; ?>>
        <?php if (!empty($subtitle)): ?>
            <p class="dx-section-head__text"><?= e((string) $subtitle) ?></p>
        <?php endif; ?>
    </div>

    <?php if (!empty($ctaUrl) && !empty($ctaLabel)): ?>
        <a class="btn btn-dx-soft" href="<?= e((string) $ctaUrl) ?>">
            <?= e((string) $ctaLabel) ?>
            <i class="bi bi-arrow-right" aria-hidden="true"></i>
        </a>
    <?php endif; ?>
</div>
