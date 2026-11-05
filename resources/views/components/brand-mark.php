<?php

declare(strict_types=1);

/**
 * The brand mark. It renders the uploaded logo (setting `brand.logo`, relative
 * to public/assets) and falls back to the drawn bolt when that file is not on
 * disk, so a missing or renamed asset never shows as a broken image.
 *
 * @var mixed $size     Tailwind size classes, default h-10 w-10
 * @var mixed $radius   corner class
 * @var mixed $altText  empty keeps the mark decorative, because the wordmark next to it carries the name
 */

$size = (string) ($size ?? 'h-10 w-10');
$radius = (string) ($radius ?? 'rounded-brand');
$altText = (string) ($altText ?? '');
$src = brand_logo();

if ($src === '') {
    ?>
    <span class="grid <?= e($size) ?> <?= e($radius) ?> shrink-0 place-items-center bg-gradient-to-br from-brand-600 via-brand-500 to-accent-400 shadow-pop">
        <svg class="h-1/2 w-1/2 text-white" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
            <path d="M13.1 2 4.6 13.2h5.2L8.4 22l9.6-11.9h-5.4L13.1 2Z"/>
        </svg>
    </span>
    <?php
    return;
}
?>
<img
    src="<?= e($src) ?>"
    <?php if ($altText === ''): ?>alt="" aria-hidden="true"<?php else: ?>alt="<?= e($altText) ?>"<?php endif; ?>
    class="<?= e($size) ?> <?= e($radius) ?> shrink-0 bg-ink object-cover ring-1 ring-white/10"
    decoding="async"
>
