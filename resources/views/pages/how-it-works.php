<?php

declare(strict_types=1);

/**
 * How ordering works. Data comes from PageController::howItWorks():
 * $rails (one entry per payment method that is actually switched on),
 * $instruction, $whatsappUrl.
 *
 * @var App\Core\View $view
 * @var list<array<string, mixed>> $rails
 * @var string $instruction
 * @var string $whatsappUrl
 */
$brandName = setting('brand.name', 'DexxozzBD');
$agentName = setting('whatsapp.agent_name', 'the order desk');
$hours = setting('whatsapp.hours', '9:00 AM - 11:00 PM, every day (Dhaka time)');
$guarantee = setting('trust.guarantee', '7-day replacement on every personal plan');
$deliveryMinutes = max(1, (int) setting('trust.delivery_minutes', '10'));
$holdMinutes = max(5, (int) setting('order.pending_hold_minutes', '90'));
$guestCheckout = setting_bool('checkout.allow_guest');
$email = setting('contact.email', 'our support address');

$view->extend('layouts/app');
$view->setSection('title', 'How it works - ' . $brandName . ' in four steps');
$view->setSection('meta_description', 'Pick a plan, send the money by bKash, Nagad, Rocket, card or bank, a human verifies it, and your login arrives on WhatsApp in about ' . $deliveryMinutes . ' minutes.');
$view->setSection('canonical', route('how'));

$steps = [
    [
        'title' => 'Choose the plan',
        'kicker' => 'Two minutes, no sign-up',
        'body' => 'Pick a brand, then the tier that fits: 1, 3, 6 or 12 months, personal, individual or family seats. The taka price on the page is the price you pay - there is no service fee added at the end and no currency conversion surprise.',
        'points' => [
            'Every tier lists the seat count and what the plan is for.',
            'Out-of-stock tiers say so instead of taking your money.',
            'The cart holds the exact total you will send.',
        ],
        'icon' => 'grid',
        'link' => ['route' => 'plans', 'label' => 'Browse plans'],
    ],
    [
        'title' => 'Pay with bKash, Nagad, Rocket, card or bank',
        'kicker' => 'Manual, on purpose',
        'body' => 'Checkout gives you our number and the exact amount. You send the money yourself from your own wallet, then paste the transaction ID. Nothing is auto-captured, so the only money that ever moves is money you chose to move.',
        'points' => [
            'Send the exact total - short payments sit unpaid until you top them up.',
            'Paste the TrxID exactly as the receipt shows it.',
            'An unpaid order holds its price and stock for ' . $holdMinutes . ' minutes.',
        ],
        'icon' => 'wallet',
        'link' => ['route' => 'track', 'label' => 'Track an order'],
    ],
    [
        'title' => 'A human verifies it',
        'kicker' => 'This is the slow, careful step',
        'body' => 'Whoever is on the desk opens our statement, finds your transaction ID, checks the amount and the number it came from, and only then marks the order paid. If it does not match, we message you on WhatsApp before touching anything - we never mark a payment paid on trust.',
        'points' => [
            'Verification happens inside the opening hours, typically in ' . $deliveryMinutes . ' minutes.',
            'A mismatch gets a message, not a silent cancellation.',
            'Payments are recorded with the ID you gave us, so a dispute has a paper trail.',
        ],
        'icon' => 'search',
        'link' => ['route' => 'contact', 'label' => 'Payment problem?'],
    ],
    [
        'title' => 'Delivered on WhatsApp',
        'kicker' => 'Login, email, and how to change it',
        'body' => 'The account details are typed out and sent to the WhatsApp number you used at checkout: email, password, which app it works on, and the one thing you should do first - change the password on your own device. Your order also stays visible in your account, and the delivery note itself is stored encrypted.',
        'points' => [
            'No courier, no waiting room, no ticket number.',
            'Keep the WhatsApp thread - it is your proof of delivery.',
            $guarantee . ', no argument if the account stops working.',
        ],
        'icon' => 'chat',
        'link' => ['route' => 'account', 'label' => 'Your orders'],
    ],
];

$icons = [
    'grid' => '<rect x="4" y="4" width="7" height="7" rx="2"/><rect x="13" y="4" width="7" height="7" rx="2"/><rect x="4" y="13" width="7" height="7" rx="2"/><rect x="13" y="13" width="7" height="7" rx="2"/>',
    'wallet' => '<rect x="3" y="7" width="18" height="12" rx="3"/><path d="M3 11h18M16 14h2" stroke-linecap="round"/>',
    'search' => '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.6-3.6" stroke-linecap="round"/>',
    'chat' => '<path d="M4 5h16v10H8l-4 4V5Z" stroke-linejoin="round"/><path d="M8.5 10h7M8.5 12.5h4" stroke-linecap="round"/>',
];
?>
<div class="bg-white">
    <?php /* ------------------------------- hero -------------------------------*/ ?>
    <section class="relative overflow-hidden bg-gradient-to-b from-brand-50 via-white to-white">
        <span class="blob -left-24 -top-20 animate-blob opacity-40"></span>
        <span class="blob -right-20 top-8 animate-blob opacity-35 [animation-delay:-5s]"></span>
        <span class="blob bottom-[-8rem] left-1/3 animate-blob opacity-30 [animation-delay:-9s]"></span>

        <div class="relative mx-auto max-w-4xl px-4 py-14 text-center lg:px-6 lg:py-18">
            <div class="reveal" data-reveal>
                <p class="inline-flex items-center gap-2 rounded-pill border border-brand-200 bg-white/80 px-3.5 py-1.5 text-xs font-black uppercase tracking-[0.18em] text-brand-700 shadow-soft">
                    <span class="h-2 w-2 animate-pulse-ring rounded-full bg-accent-400"></span>
                    Four steps, one human
                </p>
                <h1 class="mt-4 text-4xl font-black leading-[1.05] tracking-tight md:text-6xl">
                    <span class="animate-gradient text-gradient">From “I want that plan” to logged in, in about <?= e((string) $deliveryMinutes) ?> minutes.</span>
                </h1>
                <p class="mx-auto mt-5 max-w-2xl text-base leading-relaxed text-ink-400">
                    <?= $brandName ?> runs on hand-built checks, not automation: you pay from your own bKash or
                    Nagad, a person matches it, and the login lands in your WhatsApp chat. Here is the whole
                    process, including the parts that can go wrong and how we fix them.
                </p>
                <div class="mt-7 flex flex-wrap items-center justify-center gap-3">
                    <a href="<?= e(route('plans')) ?>" class="group relative inline-flex items-center gap-2 overflow-hidden rounded-brand bg-gradient-to-r from-brand-600 via-brand-500 to-accent-500 px-6 py-3.5 text-sm font-black text-white shadow-glow transition-transform duration-200 hover:-translate-y-0.5">
                        <span class="animate-shimmer absolute inset-0 bg-gradient-to-r from-white/0 via-white/35 to-white/0" aria-hidden="true"></span>
                        <span class="relative">Start with a plan</span>
                        <svg class="relative h-4 w-4 transition-transform duration-200 group-hover:translate-x-1" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true">
                            <path d="M5 12h14M13 6l6 6-6 6" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </a>
                    <span class="inline-flex items-center gap-2 rounded-brand border-2 border-emerald-200 bg-emerald-50 px-5 py-3 text-sm font-bold text-emerald-800">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true">
                            <path d="m5 13 4 4L19 7" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        <?= e($guarantee) ?>
                    </span>
                </div>
            </div>
        </div>
    </section>

    <?php /* ------------------------------ timeline ------------------------------*/ ?>
    <section class="mx-auto max-w-5xl px-4 py-16 lg:px-6">
        <?= $view->component('section-heading', [
            'eyebrow' => 'The order, start to finish',
            'title' => 'What actually happens after you click checkout',
            'subtitle' => 'Nothing here is hidden: step three is a person looking at a bank statement, and step four is a WhatsApp message you can keep.',
        ]) ?>

        <ol class="relative mt-12 space-y-8">
            <span class="absolute left-[22px] top-2 hidden w-[3px] rounded-pill bg-gradient-to-b from-brand-500 via-accent-400 to-emerald-400 sm:block" aria-hidden="true"></span>

            <?php foreach ($steps as $index => $step): ?>
                <li class="reveal relative sm:pl-16" data-reveal style="--reveal-delay:<?= e((string) ($index * 110)) ?>ms">
                    <span class="absolute left-0 top-0 hidden sm:block">
                        <span class="relative grid h-11 w-11 place-items-center rounded-pill bg-gradient-to-br from-brand-600 to-accent-500 text-sm font-black text-white shadow-pop">
                            <span class="absolute inset-0 animate-pulse-ring rounded-pill bg-brand-400/40" aria-hidden="true"></span>
                            <span class="relative"><?= e((string) ($index + 1)) ?></span>
                        </span>
                    </span>

                    <div class="hover-lift rounded-card border border-brand-100 bg-white p-6 shadow-card">
                        <div class="flex flex-wrap items-center gap-3">
                            <span class="inline-flex h-9 w-9 items-center justify-center rounded-brand bg-brand-50 text-brand-600 sm:hidden" aria-hidden="true">
                                <?php /* This file's own icon whitelist, never request input. */ ?>
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9">
                                    <?= $icons[$step['icon']] ?? $icons['grid'] ?>
                                </svg>
                            </span>
                            <h3 class="text-xl font-black tracking-tight text-ink md:text-2xl"><?= e($step['title']) ?></h3>
                            <span class="rounded-pill bg-accent-50 px-3 py-1 text-[11px] font-black uppercase tracking-wider text-accent-700"><?= e($step['kicker']) ?></span>
                        </div>

                        <p class="mt-3 text-sm leading-relaxed text-ink-400"><?= e($step['body']) ?></p>

                        <ul class="mt-4 grid gap-2 border-t border-dashed border-brand-100 pt-4 sm:grid-cols-2">
                            <?php foreach ($step['points'] as $point): ?>
                                <li class="flex items-start gap-2 text-xs leading-relaxed text-ink-400">
                                    <svg class="mt-0.5 h-3.5 w-3.5 shrink-0 text-emerald-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" aria-hidden="true">
                                        <path d="m5 13 4 4L19 7" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                    <span><?= e($point) ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>

                        <a href="<?= e(route($step['link']['route'])) ?>" class="mt-4 inline-flex items-center gap-1.5 text-sm font-black text-brand-700 hover:text-brand-800">
                            <?= e($step['link']['label']) ?>
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true">
                                <path d="M5 12h14M13 6l6 6-6 6" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </a>
                    </div>
                </li>
            <?php endforeach; ?>
        </ol>
    </section>

    <?php /* ------------------------------ the rails ------------------------------*/ ?>
    <section class="relative overflow-hidden bg-ink py-16">
        <span class="blob -left-20 top-[-5rem] animate-blob bg-brand-600/40"></span>
        <span class="blob -right-16 bottom-[-6rem] animate-blob bg-accent-500/30 [animation-delay:-7s]"></span>

        <div class="relative mx-auto max-w-7xl px-4 lg:px-6">
            <div class="reveal max-w-2xl" data-reveal>
                <p class="flex items-center gap-2 text-xs font-black uppercase tracking-[0.18em] text-accent-300">
                    <span class="h-px w-8 bg-gradient-to-r from-accent-300 to-brand-400"></span>
                    Ways to pay
                </p>
                <h2 class="mt-2 text-3xl font-black tracking-tight text-white md:text-4xl">Every number below is ours - nothing else is</h2>
                <p class="mt-3 text-base leading-relaxed text-white/70">
                    Scams in this market copy merchant numbers into Telegram posts, so check the number on this
                    page against the one checkout shows you. If they differ, stop and message us first.
                </p>
            </div>

            <?php if ($rails === []): ?>
                <p class="reveal mt-10 rounded-card border border-dashed border-white/20 bg-white/5 p-6 text-sm leading-relaxed text-white/75" data-reveal>
                    No payment rail is switched on right now, so checkout cannot be completed at this moment.
                    Message us on WhatsApp and we will tell you when it is back - we never ask you to pay to a
                    personal number that is not listed here.
                </p>
            <?php else: ?>
                <div class="mt-10 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    <?php foreach ($rails as $index => $rail): ?>
                        <article class="reveal rounded-card border border-white/10 bg-white/5 p-6 backdrop-blur-sm" data-reveal style="--reveal-delay:<?= e((string) ($index * 80)) ?>ms">
                            <div class="flex items-center gap-3">
                                <span class="grid h-10 w-10 shrink-0 place-items-center rounded-brand text-sm font-black text-white shadow-pop" style="background-image:linear-gradient(135deg,<?= e((string) $rail['color']) ?>,#1c0b47)">
                                    <?= e(mb_strtoupper(mb_substr(trim((string) $rail['label']), 0, 2))) ?>
                                </span>
                                <div class="min-w-0">
                                    <h3 class="truncate text-base font-black text-white"><?= e((string) $rail['label']) ?></h3>
                                    <p class="text-[11px] font-semibold uppercase tracking-wider text-white/50"><?= e((string) $rail['kind']) ?></p>
                                </div>
                            </div>

                            <?php if ((string) $rail['number'] !== ''): ?>
                                <p class="mt-4 text-[11px] font-black uppercase tracking-wider text-accent-300"><?= e((string) $rail['number_label']) ?></p>
                                <p class="mt-1 flex items-center gap-2 rounded-brand bg-white/10 px-3 py-2 font-mono text-sm font-black tracking-wide text-white">
                                    <span class="truncate"><?= e((string) $rail['number']) ?></span>
                                    <span class="ml-auto shrink-0 rounded-pill bg-emerald-400/20 px-2 py-0.5 text-[10px] font-black uppercase text-emerald-300">ours</span>
                                </p>
                            <?php else: ?>
                                <p class="mt-4 rounded-brand bg-white/10 px-3 py-2 text-xs leading-relaxed text-white/65">
                                    This method is a hosted card page - checkout shows the reference field after
                                    the gateway confirms, and nothing is charged until you press pay yourself.
                                </p>
                            <?php endif; ?>

                            <p class="mt-4 text-xs font-bold text-white/85">
                                <?= e((string) ($rail['txn_label'] === '' ? 'Reference' : $rail['txn_label'])) ?> required
                            </p>
                            <p class="mt-1 text-xs leading-relaxed text-white/60"><?= e((string) $rail['instructions']) ?></p>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ($instruction !== ''): ?>
                <div class="reveal mt-8 rounded-card border-2 border-dashed border-amber-300/40 bg-amber-400/10 p-6" data-reveal>
                    <p class="flex items-center gap-2 text-xs font-black uppercase tracking-[0.18em] text-amber-300">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8h.01" stroke-linecap="round"/>
                        </svg>
                        The one rule that saves everyone time
                    </p>
                    <p class="mt-3 text-sm leading-relaxed text-white/80"><?= e($instruction) ?></p>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <?php /* ---------------------------- timeline facts ----------------------------*/ ?>
    <section class="mx-auto max-w-7xl px-4 py-16 lg:px-6">
        <div class="grid gap-5 md:grid-cols-3">
            <div class="reveal rounded-card border border-brand-100 bg-brand-50/50 p-6" data-reveal>
                <p class="text-3xl font-black tracking-tight text-brand-700"><?= e((string) $deliveryMinutes) ?> min</p>
                <h3 class="mt-1 text-sm font-black text-ink">Average delivery, during opening hours</h3>
                <p class="mt-2 text-xs leading-relaxed text-ink-400">
                    Measured from the moment a payment matches our statement, not from when you send it.
                    Outside <?= e($hours) ?> the clock simply waits with us.
                </p>
            </div>
            <div class="reveal rounded-card border border-amber-200 bg-amber-50/60 p-6" data-reveal style="--reveal-delay:90ms">
                <p class="text-3xl font-black tracking-tight text-amber-700"><?= e((string) $holdMinutes) ?> min</p>
                <h3 class="mt-1 text-sm font-black text-ink">How long an unpaid order waits</h3>
                <p class="mt-2 text-xs leading-relaxed text-ink-400">
                    Your price and the plan’s stock are held while you find your bKash PIN. Late on purpose? Say
                    so on WhatsApp and we reopen it instead of cancelling.
                </p>
            </div>
            <div class="reveal rounded-card border border-emerald-200 bg-emerald-50/60 p-6" data-reveal style="--reveal-delay:180ms">
                <p class="text-3xl font-black tracking-tight text-emerald-700"><?= e($guestCheckout ? 'Guest OK' : 'Sign-in') ?></p>
                <h3 class="mt-1 text-sm font-black text-ink">Do I need an account?</h3>
                <p class="mt-2 text-xs leading-relaxed text-ink-400">
                    <?php if ($guestCheckout): ?>
                        No - you can order as a guest and track the order by its number. An account just keeps all
                        your orders in one place with your delivery states.
                    <?php else: ?>
                        Yes, this shop asks for a sign-in so every order has an owner and a history. It takes an
                        email, a phone number and a password.
                    <?php endif; ?>
                </p>
            </div>
        </div>

        <div class="reveal mt-10 grid gap-4 rounded-card bg-gradient-to-br from-brand-700 via-brand-600 to-accent-600 p-7 text-white shadow-pop md:grid-cols-[1.5fr_1fr] md:items-center" data-reveal>
            <div>
                <h2 class="text-2xl font-black tracking-tight">Something not matching up?</h2>
                <p class="mt-2 text-sm leading-relaxed text-white/80">
                    Short payment, wrong TrxID, an account that stopped working after two days - send the order
                    number and <?= e($agentName) ?> sorts it out. Open <?= e($hours) ?>; anything outside those
                    hours is first in line when the desk opens.
                </p>
                <p class="mt-3 text-xs text-white/65">
                    Written rules live on the <a href="<?= e(route('terms')) ?>" class="font-bold underline decoration-white/40 underline-offset-4 hover:text-white">terms</a>,
                    <a href="<?= e(route('refund')) ?>" class="font-bold underline decoration-white/40 underline-offset-4 hover:text-white">refund</a> and
                    <a href="<?= e(route('privacy')) ?>" class="font-bold underline decoration-white/40 underline-offset-4 hover:text-white">privacy</a> pages - all three are short on purpose.
                </p>
            </div>
            <div class="flex flex-col gap-3">
                <a href="<?= e($whatsappUrl) ?>" target="_blank" rel="noopener" class="relative flex items-center justify-center gap-2 overflow-hidden rounded-brand bg-white px-6 py-3.5 text-sm font-black text-brand-700 shadow-soft transition-transform duration-200 hover:-translate-y-0.5">
                    <span class="animate-shimmer absolute inset-0 bg-gradient-to-r from-white/0 via-brand-100/70 to-white/0" aria-hidden="true"></span>
                    <span class="relative">WhatsApp the desk</span>
                </a>
                <a href="<?= e(route('faq')) ?>" class="rounded-brand border-2 border-white/40 px-6 py-3 text-center text-sm font-bold text-white transition-colors duration-200 hover:bg-white/10">
                    Read the FAQ
                </a>
                <?php if ($email !== ''): ?>
                    <a href="mailto:<?= e($email) ?>" class="text-center text-xs font-semibold text-white/70 underline decoration-white/40 underline-offset-4 hover:text-white">
                        <?= e($email) ?>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </section>
</div>
