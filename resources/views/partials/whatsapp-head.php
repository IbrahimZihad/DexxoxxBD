<?php

declare(strict_types=1);

use App\Core\Settings;

/**
 * The floating chat head. It only ever builds a wa.me link - no requests, no
 * iframe, no third-party script. The bubble is a real <a href> so a visitor with
 * JavaScript disabled still reaches WhatsApp; app.js then intercepts the click,
 * keeps the href and swaps it for the chosen quick reply.
 */
if (!setting_bool('whatsapp.enabled')) {
    return;
}

$number = wa_number();

if ($number === '') {
    return;
}

$agentName = setting('whatsapp.agent_name', setting('brand.name', 'Support'));
$agentRole = setting('whatsapp.agent_role', 'Order desk');
$greeting = setting('whatsapp.greeting', 'Hi! Need a hand choosing a plan?');
$hours = setting('whatsapp.hours');
$composeHref = wa_link(setting('whatsapp.default_message'));
$quickReplies = Settings::list('whatsapp.quick_replies');
$initial = mb_strtoupper(mb_substr(trim($agentName) === '' ? 'Z' : trim($agentName), 0, 1));
?>
<div data-wa-number="<?= e($number) ?>" class="fixed bottom-4 right-4 z-[70] flex flex-col items-end gap-3 sm:bottom-6 sm:right-6">
    <div data-wa-panel hidden class="glass w-72 overflow-hidden rounded-card sm:w-80">
        <div class="flex items-center gap-3 bg-gradient-to-r from-wa-700 to-wa-600 px-4 py-3 text-white">
            <span class="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-white/20 text-base font-black ring-2 ring-white/40"><?= e($initial) ?></span>
            <span class="min-w-0">
                <span class="block truncate text-sm font-bold"><?= e($agentName) ?></span>
                <span class="block truncate text-xs text-white/80"><?= e($agentRole) ?></span>
            </span>
            <span class="ml-auto flex items-center gap-1.5 text-[11px] font-semibold text-white/90">
                <span class="h-2 w-2 animate-pulse rounded-full bg-wa-400"></span>
                Online
            </span>
        </div>

        <div class="space-y-3 bg-brand-50/60 px-4 py-4">
            <div class="max-w-[85%] rounded-brand rounded-tl-sm bg-white p-3 text-sm shadow-soft">
                <p class="text-ink"><?= e($greeting) ?></p>
            </div>

            <?php if ($hours !== ''): ?>
                <p class="flex items-start gap-2 text-xs font-medium text-ink-400">
                    <svg class="mt-0.5 h-3.5 w-3.5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <circle cx="12" cy="12" r="9"/><path d="M12 7.5V12l3 2" stroke-linecap="round"/>
                    </svg>
                    <?= e($hours) ?>
                </p>
            <?php endif; ?>

            <?php if ($quickReplies !== []): ?>
                <div class="flex flex-wrap gap-2 pt-1">
                    <?php foreach ($quickReplies as $reply): ?>
                        <button type="button" data-wa-quick="<?= e($reply) ?>" class="rounded-pill border border-brand-300 bg-white px-3 py-1.5 text-xs font-semibold text-brand-700 transition-colors duration-200 hover:border-brand-500 hover:bg-brand-600 hover:text-white">
                            <?= e($reply) ?>
                        </button>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <a data-wa-compose
               href="<?= e($composeHref) ?>"
               target="_blank"
               rel="noopener"
               class="flex items-center justify-center gap-2 rounded-brand bg-wa-600 px-4 py-3 text-sm font-bold text-white transition-colors duration-200 hover:bg-wa-700">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                    <path d="M12 2a10 10 0 0 0-8.6 15L2 22l5.2-1.3A10 10 0 1 0 12 2Zm5.3 14c-.2.6-1.2 1.2-1.7 1.2-.5.1-1 .1-1.7-.1-.9-.3-2.3-1-3.6-2.6-1-1.2-1.6-2.5-1.7-3-.1-.6.3-1.4.8-1.8.2-.2.4-.2.6-.2h.5c.2 0 .3 0 .4.3l.6 1.4c.1.2 0 .4-.1.5l-.4.5c-.1.1-.1.3 0 .4.3.5 1.2 1.5 2.1 1.9.2.1.3.1.5-.1l.5-.6c.1-.2.3-.2.5-.1l1.4.7c.2.1.3.2.3.4 0 .2 0 .7-.3 1.2Z"/>
                </svg>
                Chat on WhatsApp
            </a>

            <p class="text-center text-[11px] text-ink-400">No bots - a person replies.</p>
        </div>
    </div>

    <a data-wa-toggle
       href="<?= e($composeHref) ?>"
       target="_blank"
       rel="noopener"
       aria-expanded="false"
       aria-label="Chat with <?= e($agentName) ?> on WhatsApp"
       class="relative grid h-14 w-14 place-items-center rounded-full bg-gradient-to-br from-wa-500 to-wa-700 text-white shadow-card transition-transform duration-200 hover:scale-105">
        <span class="animate-pulse-ring-wa absolute inset-0 rounded-full"></span>
        <svg class="relative h-7 w-7" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
            <path d="M12 2a10 10 0 0 0-8.6 15L2 22l5.2-1.3A10 10 0 1 0 12 2Zm5.3 14c-.2.6-1.2 1.2-1.7 1.2-.5.1-1 .1-1.7-.1-.9-.3-2.3-1-3.6-2.6-1-1.2-1.6-2.5-1.7-3-.1-.6.3-1.4.8-1.8.2-.2.4-.2.6-.2h.5c.2 0 .3 0 .4.3l.6 1.4c.1.2 0 .4-.1.5l-.4.5c-.1.1-.1.3 0 .4.3.5 1.2 1.5 2.1 1.9.2.1.3.1.5-.1l.5-.6c.1-.2.3-.2.5-.1l1.4.7c.2.1.3.2.3.4 0 .2 0 .7-.3 1.2Z"/>
        </svg>
    </a>
</div>
