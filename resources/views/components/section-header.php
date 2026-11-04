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
 * @var string|null $heading
 */

if (($title ?? '') === '') {
    return;
}

// Whitelisted rather than echoed raw: the tag name comes from a caller, and a
// stray string there must not be able to open arbitrary markup.
$tag = in_array((string) ($heading ?? ''), ['h1', 'h2', 'h3', 'h4'], true) ? (string) $heading : 'h2';
$id = empty($titleId) ? '' : ' id="' . e((string) $titleId) . '"';
?>
<div class="dx-section-head">
    <div>
        <?php if (!empty($eyebrow)): ?>
            <p class="dx-eyebrow"><?= e((string) $eyebrow) ?></p>
        <?php endif; ?>
        <<?= $tag ?> class="dx-section-head__title"<?= $id ?>>
            <?= e((string) $title) ?>
        </<?= $tag ?>>
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
