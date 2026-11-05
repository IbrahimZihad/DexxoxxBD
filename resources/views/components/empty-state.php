<?php

declare(strict_types=1);

/**
 * Empty state with a ship-it icon set: search, bag, inbox, check.
 *
 * @var string|null $title
 * @var string|null $text
 * @var string|null $actionUrl
 * @var string|null $actionLabel
 * @var string|null $icon
 */
$title = trim((string) ($title ?? 'Nothing here yet'));
$text = trim((string) ($text ?? ''));
$actionUrl = trim((string) ($actionUrl ?? ''));
$actionLabel = trim((string) ($actionLabel ?? ''));
$iconKey = (string) ($icon ?? 'search');

/** @var array<string, string> $paths */
$paths = [
    'search' => '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.6-3.6"/>',
    'bag' => '<path d="M6 8h12l-1 12H7L6 8Z" stroke-linejoin="round"/><path d="M9 8a3 3 0 0 1 6 0"/>',
    'inbox' => '<path d="M4 13h4l1.5 3h5L16 13h4" stroke-linejoin="round"/><path d="M4 13 6 5h12l2 8v6H4v-6Z" stroke-linejoin="round"/>',
    'check' => '<circle cx="12" cy="12" r="9"/><path d="m8.5 12.5 2.5 2.5 4.5-5.5" stroke-linecap="round" stroke-linejoin="round"/>',
];

$body = $paths[$iconKey] ?? $paths['search'];
?>
<div class="reveal flex flex-col items-center rounded-card border border-dashed border-brand-200 bg-brand-50/50 px-6 py-12 text-center" data-reveal>
    <span class="grid h-16 w-16 place-items-center rounded-full bg-white shadow-soft">
        <?php /* Markup below is this file's own whitelist, never request input. */ ?>
        <svg class="h-8 w-8 text-brand-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
            <?= $body ?>
        </svg>
    </span>
    <h3 class="mt-4 text-lg font-bold text-ink"><?= e($title) ?></h3>
    <?php if ($text !== ''): ?>
        <p class="mt-2 max-w-md text-sm leading-relaxed text-ink-400"><?= e($text) ?></p>
    <?php endif; ?>
    <?php if ($actionUrl !== '' && $actionLabel !== ''): ?>
        <a href="<?= e($actionUrl) ?>" class="mt-6 rounded-brand bg-brand-600 px-5 py-2.5 text-sm font-bold text-white shadow-glow transition-colors duration-200 hover:bg-brand-700">
            <?= e($actionLabel) ?>
        </a>
    <?php endif; ?>
</div>
