<?php

declare(strict_types=1);

/**
 * Gradient ticker strip. Duplicated by app.js ([data-marquee] clones the track
 * once) so the -50% loop is seamless. Renders nothing when the shop turns it off.
 */
if (!setting_bool('announcement.active')) {
    return;
}

$text = setting('announcement.text');

if ($text === '') {
    return;
}
?>
<div class="relative z-40 overflow-hidden border-b border-white/10 bg-gradient-to-r from-brand-700 via-brand-500 to-accent-500">
    <div class="flex w-max animate-marquee hover:[animation-play-state:paused]">
        <div data-marquee-track class="flex items-center gap-x-10 py-2 pr-10">
            <span class="flex items-center gap-2 whitespace-nowrap text-[13px] font-semibold text-white">
                <svg class="h-4 w-4 shrink-0 text-accent-200" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                    <path d="M13.1 2 4.6 13.2h5.2L8.4 22l9.6-11.9h-5.4L13.1 2Z"/>
                </svg>
                <?= e($text) ?>
            </span>
        </div>
    </div>
</div>
