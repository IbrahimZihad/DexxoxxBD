<?php

declare(strict_types=1);

/**
 * Section heading: eyebrow label, title, optional subtitle, left or centred.
 *
 * @var string|null $eyebrow
 * @var string|null $title
 * @var string|null $subtitle
 * @var string|null $align
 */
$eyebrow = trim((string) ($eyebrow ?? ''));
$title = trim((string) ($title ?? ''));
$subtitle = trim((string) ($subtitle ?? ''));
$align = ($align ?? 'left') === 'center' ? 'center' : 'left';
$wrap = $align === 'center' ? 'mx-auto max-w-2xl text-center' : 'max-w-2xl';
?>
<div class="reveal <?= e($wrap) ?>" data-reveal>
    <?php if ($eyebrow !== ''): ?>
        <p class="flex <?= e($align === 'center' ? 'justify-center' : '') ?> items-center gap-2 text-xs font-black uppercase tracking-[0.18em] text-accent-600">
            <span class="h-px w-8 bg-gradient-to-r from-brand-400 to-accent-400"></span>
            <?= e($eyebrow) ?>
        </p>
    <?php endif; ?>

    <?php if ($title !== ''): ?>
        <h2 class="mt-2 text-3xl font-black tracking-tight text-ink md:text-4xl"><?= e($title) ?></h2>
    <?php endif; ?>

    <?php if ($subtitle !== ''): ?>
        <p class="mt-3 text-base leading-relaxed text-ink-400"><?= e($subtitle) ?></p>
    <?php endif; ?>
</div>
