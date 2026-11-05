<?php

declare(strict_types=1);

/**
 * The single error page for every non-2xx response. ErrorHandler::body() passes
 * exactly these six variables and nothing else:
 *
 *  status   int                  HTTP code
 *  title    string               short label ("Page not found")
 *  message  string               safe, human explanation
 *  errors   array<string, list<string>>  field errors on a 422
 *  allowed  list<string>         methods for a 405
 *  incident array|null           debug payload
 *
 * `incident` is deliberately never printed. Debug or not, this template ships no
 * stack trace, no file path and no class name - the log line is where that detail
 * lives, and a visitor gets a hint instead.
 *
 * @var App\Core\View $view
 * @var int $status
 * @var string $title
 * @var string $message
 * @var array<string, list<string>> $errors
 * @var list<string> $allowed
 * @var array<string, mixed>|null $incident
 */
$brandName = setting('brand.name', 'DexxozzBD');
$hours = setting('whatsapp.hours', '9:00 AM - 11:00 PM, every day (Dhaka time)');
$email = setting('contact.email', 'our support address');

$status = (int) ($status ?? 500);
$title = (string) ($title ?? 'Something went wrong');
$message = (string) ($message ?? '');
$allowed = array_values(array_filter(array_map('strval', (array) ($allowed ?? []))));
$hasErrors = is_array($errors) && $errors !== [];

// What a visitor can actually do about this particular number.
$hints = [
    400 => 'The request reached us but was not well formed - usually a bookmark that half copied itself. Reload it from the address above rather than the browser history entry.',
    401 => 'This page belongs to a signed-in customer. Sign in and it will show your own orders - nobody else’s data is reachable from here at any login.',
    403 => 'Either that link has already been used, it belongs to someone else, or your session went idle. A fresh page normally fixes it; a used reset link genuinely cannot be reopened.',
    404 => 'Check the spelling - especially order codes, which are case-sensitive and easy to mis-type from a screenshot. If you followed a link from our own page, tell us and we will fix it today.',
    405 => 'That address only answers with the methods listed below. A back button or an old bookmark is the usual cause.',
    422 => 'Something in the form did not pass. Go back, fix the highlighted fields and send it again - your typed text is kept, passwords excepted.',
    429 => 'Too many attempts from this connection, which is the limiter doing its job on a login, checkout or contact form. Wait it out - the retry note above says roughly how long - or message us on WhatsApp and we will help from there.',
    500 => 'This one is on us. The failure was written to our log the moment it happened, so the desk already knows; refreshing is usually enough, and anything that keeps failing gets fixed.',
    503 => 'The shop is briefly unavailable - commonly the database or a maintenance run. Refresh in a minute or two; payments and orders are safe.',
];

$hint = $hints[$status] ?? 'Something did not line up. Reload the page; if it repeats, the desk can pick your order up by number.';

$waHref = wa_link(
    'Hi ' . $brandName . ', I hit a problem on the site (' . $status . ' ' . $title . ').'
    . ($message === '' ? '' : ' It says: ' . short_text($message, 160))
);

$view->extend('layouts/app');
$view->setSection('title', $status . ' - ' . $title . ' | ' . $brandName);
$view->setSection('meta_description', 'The ' . $brandName . ' ' . $title . ' page: what happened and how to get back to ordering.');

$links = [
    ['route' => 'home', 'label' => 'Back to the shop', 'note' => 'Everything starts here', 'primary' => true],
    ['route' => 'plans', 'label' => 'Browse plans', 'note' => 'Prices in taka, 1/3/6/12 months', 'primary' => false],
    ['route' => 'track', 'label' => 'Track an order', 'note' => 'By order number, no login needed', 'primary' => false],
    ['route' => 'contact', 'label' => 'Contact the desk', 'note' => 'Form lands in a real inbox', 'primary' => false],
];
?>
<div class="bg-white">
    <section class="relative overflow-hidden bg-gradient-to-b from-brand-50 via-white to-white">
        <span class="blob -left-24 -top-20 animate-blob opacity-40"></span>
        <span class="blob -right-20 top-10 animate-blob opacity-30 [animation-delay:-5s]"></span>
        <span class="blob bottom-[-8rem] left-1/3 animate-blob opacity-25 [animation-delay:-9s]"></span>

        <div class="relative mx-auto max-w-3xl px-4 py-16 text-center lg:px-6 lg:py-24">
            <?php /* big status number: pure typography, no script*/ ?>
            <div class="reveal" data-reveal>
                <p class="animate-pop select-none text-[7rem] font-black leading-[0.85] tracking-tighter md:text-[11rem]">
                    <span class="animate-gradient bg-gradient-to-br from-brand-600 via-accent-500 to-brand-400 bg-clip-text text-transparent"><?= e((string) $status) ?></span>
                </p>
                <span class="relative mt-2 inline-flex items-center gap-2 rounded-pill border border-brand-200 bg-white/80 px-4 py-1.5 text-xs font-black uppercase tracking-[0.2em] text-brand-700 shadow-soft">
                    <span class="h-2 w-2 animate-pulse-ring rounded-full <?= e($status >= 500 ? 'bg-rose-400' : 'bg-amber-400') ?>"></span>
                    <?= e($title) ?>
                </span>

                <h1 class="mt-6 text-3xl font-black tracking-tight text-ink md:text-4xl">
                    <?php if ($status >= 500): ?>
                        This one is ours, not yours.
                    <?php elseif ($status === 404): ?>
                        Nothing lives at that address.
                    <?php elseif ($status === 401): ?>
                        Who are you? (No offence.)
                    <?php elseif ($status === 429): ?>
                        Breathe for a moment.
                    <?php else: ?>
                        That did not go as planned.
                    <?php endif; ?>
                </h1>

                <?php if ($message !== ''): ?>
                    <p class="mx-auto mt-4 max-w-xl text-base leading-relaxed text-ink-400"><?= e($message) ?></p>
                <?php endif; ?>

                <p class="mx-auto mt-4 max-w-xl rounded-card border border-brand-100 bg-white/80 p-5 text-left text-sm leading-relaxed text-ink shadow-soft">
                    <span class="mb-1 block text-xs font-black uppercase tracking-[0.16em] text-brand-700">What to do</span>
                    <?= e($hint) ?>
                </p>

                <?php if ($allowed !== []): ?>
                    <p class="mt-4 text-xs text-ink-400">
                        This address accepts:
                        <?php foreach ($allowed as $method): ?>
                            <span class="ml-1 inline-block rounded-pill bg-brand-100 px-2 py-0.5 font-mono text-[11px] font-black uppercase text-brand-700"><?= e($method) ?></span>
                        <?php endforeach; ?>
                    </p>
                <?php endif; ?>

                <?php if ($hasErrors): ?>
                    <div class="mx-auto mt-5 max-w-md rounded-card border border-rose-200 bg-rose-50/70 p-5 text-left">
                        <p class="text-xs font-black uppercase tracking-[0.16em] text-rose-700">Fields that need attention</p>
                        <ul class="mt-3 space-y-2">
                            <?php foreach ($errors as $field => $messages): ?>
                                <li class="text-sm leading-relaxed text-ink-400">
                                    <span class="font-bold text-ink"><?= e(ucwords(str_replace('_', ' ', (string) $field))) ?></span>
                                    <?php foreach ((array) $messages as $errorText): ?>
                                        <span class="block text-xs"><?= e((string) $errorText) ?></span>
                                    <?php endforeach; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
            </div>

            <?php /* primary actions*/ ?>
            <div class="reveal mt-9 flex flex-wrap items-center justify-center gap-3" data-reveal style="--reveal-delay:120ms">
                <a href="<?= e(route('home')) ?>" class="group relative inline-flex items-center gap-2 overflow-hidden rounded-brand bg-gradient-to-r from-brand-600 via-brand-500 to-accent-500 px-6 py-3.5 text-sm font-black text-white shadow-glow transition-transform duration-200 hover:-translate-y-0.5">
                    <span class="animate-shimmer absolute inset-0 bg-gradient-to-r from-white/0 via-white/35 to-white/0" aria-hidden="true"></span>
                    <span class="relative">Back to the shop</span>
                    <svg class="relative h-4 w-4 transition-transform duration-200 group-hover:translate-x-1" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true">
                        <path d="M5 12h14M13 6l6 6-6 6" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </a>
                <a href="<?= e($waHref) ?>" target="_blank" rel="noopener" class="inline-flex items-center gap-2 rounded-brand border-2 border-emerald-300 bg-emerald-50 px-5 py-3 text-sm font-bold text-emerald-800 transition-colors duration-200 hover:border-emerald-500 hover:bg-emerald-100">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" aria-hidden="true">
                        <path d="M4 5h16v10H8l-4 4V5Z" stroke-linejoin="round"/>
                    </svg>
                    Tell us what happened
                </a>
            </div>
        </div>
    </section>

    <?php /* ------------------------------ link grid ------------------------------*/ ?>
    <section class="mx-auto max-w-5xl px-4 pb-16 lg:px-6">
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <?php foreach ($links as $index => $link): ?>
                <a href="<?= e(route($link['route'])) ?>" class="reveal hover-lift rounded-card border <?= e($link['primary'] ? 'border-brand-300 bg-gradient-to-br from-brand-600 to-accent-500 text-white shadow-pop' : 'border-brand-100 bg-white text-ink shadow-card') ?> p-5" data-reveal style="--reveal-delay:<?= e((string) ($index * 80)) ?>ms">
                    <p class="text-sm font-black <?= e($link['primary'] ? '' : 'text-brand-700') ?>"><?= e($link['label']) ?></p>
                    <p class="mt-1 text-xs leading-relaxed <?= e($link['primary'] ? 'text-white/75' : 'text-ink-400') ?>"><?= e($link['note']) ?></p>
                </a>
            <?php endforeach; ?>
        </div>

        <div class="reveal mt-10 rounded-card bg-ink p-6 text-white" data-reveal>
            <div class="grid gap-5 md:grid-cols-[1.5fr_1fr] md:items-center">
                <div>
                    <h2 class="text-lg font-black tracking-tight">Mid-order and stuck? The desk can finish it by hand.</h2>
                    <p class="mt-2 text-sm leading-relaxed text-white/70">
                        Send the order number (or the transaction ID if you already paid) on WhatsApp during
                        <?= e($hours) ?>, or email <?= e($email) ?>. Nothing you paid is lost because a page failed:
                        every payment we can match is delivered or refunded, and the ledger proves it either way.
                    </p>
                </div>
                <div class="flex flex-col gap-3">
                    <a href="<?= e(route('track')) ?>" class="rounded-brand bg-white/10 px-5 py-3 text-center text-sm font-bold text-white transition-colors duration-200 hover:bg-white/20">
                        Track by order number
                    </a>
                    <a href="<?= e(route('how')) ?>" class="rounded-brand bg-white/10 px-5 py-3 text-center text-sm font-bold text-white transition-colors duration-200 hover:bg-white/20">
                        How delivery is supposed to run
                    </a>
                </div>
            </div>
        </div>
    </section>
</div>
