<?php

declare(strict_types=1);

/**
 * About. Data comes from PageController::about():
 * $stats, $testimonials, $note, $whatsappUrl.
 *
 * No stock photos on purpose: the illustration is inline SVG drawn in this file,
 * and every number on the page comes from settings or the live catalogue.
 *
 * @var App\Core\View $view
 * @var list<array<string, mixed>> $stats
 * @var list<array<string, mixed>> $testimonials
 * @var string $note
 * @var string $whatsappUrl
 */
$brandName = setting('brand.name', 'ZapSub');
$agentName = setting('whatsapp.agent_name', 'Rafi');
$agentRole = setting('whatsapp.agent_role', 'Order desk, ' . $brandName);
$hours = setting('whatsapp.hours', '9:00 AM - 11:00 PM, every day (Dhaka time)');
$email = setting('contact.email', 'our support address');
$address = setting('contact.address', 'Dhaka, Bangladesh');
$guarantee = setting('trust.guarantee', '7-day replacement on every personal plan');
$deliveryMinutes = max(1, (int) setting('trust.delivery_minutes', '10'));

$view->extend('layouts/app');
$view->setSection('title', 'About ' . $brandName . ' - a small Dhaka desk that sells subscriptions properly');
$view->setSection('meta_description', $brandName . ' is an independent Bangladeshi reseller of streaming, music, AI and storage subscriptions: hand-verified payments, WhatsApp delivery and a ' . $guarantee . '.');
$view->setSection('canonical', route('about'));

$chapters = [
    [
        'title' => 'It started with one overpriced bill',
        'body' => 'A YouTube Premium family seat that nobody in the house was using, an unused Spotify slot on a friend’s plan, and a Dhaka electricity bill that made a dollar-denominated subscription feel twice as expensive as it should. The maths was obvious: split properly, everyone pays less. So we started splitting it for other people - first for friends, then for their friends, then on WhatsApp for strangers who became regulars.',
    ],
    [
        'title' => 'What we actually do all day',
        'body' => 'We buy the plan, we split the seats, we keep the ledger, we answer the chat. When you pay with bKash, Nagad, Rocket or a bank transfer, a human opens the statement, matches the transaction ID and only then marks the order paid. After that the login is typed out and sent to you on WhatsApp, usually inside ' . $deliveryMinutes . ' minutes. Nothing about that part is automated, which is exactly why it works.',
    ],
    [
        'title' => 'Why our prices are lower than going direct',
        'body' => 'Because the official price of a multi-seat plan already assumes those seats are shared. We buy the whole plan once, fill the seats, and charge each seat a Bangladeshi price instead of a converted dollar price. We are not affiliated with, endorsed by, or partnered with the brands we list - we are a customer who buys in bulk and resells seats, with support and a replacement promise on top.',
    ],
    [
        'title' => 'Where we are honest about the limits',
        'body' => 'A subscription we resell is still governed by the brand’s own terms, and brands do change prices, regions and seat rules. When that happens we absorb it rather than pass it on mid-term - the seven-day replacement window exists so a change on their side never leaves you holding a dead account. If the idea of buying a seat from anyone other than the brand itself bothers you, we would genuinely rather you found that out now and bought direct.',
    ],
];

$principles = [
    ['Hand-verified payments', 'No gateway auto-capture, no bot. A person matches your TrxID against our statement before anything is marked paid, so a typo cannot silently eat your money.'],
    ['One desk, one name', 'You chat with ' . $agentName . ', not a queue. The person who takes your order is the person who sends your login and the person who replaces it if it breaks.'],
    ['Written promises', 'Delivery times, the replacement window and the refund rules are on this site in plain English, not buried in a clause you have to argue about later.'],
    ['Your data stays small', 'We keep what an order needs: name, phone, email, the plan, the amount and the IP that placed it. Delivery notes are encrypted at rest. We do not sell or share customer lists.'],
];
?>
<div class="bg-white">
    {{-- ------------------------------- hero ------------------------------- --}}
    <section class="relative overflow-hidden bg-gradient-to-b from-brand-50 via-white to-white">
        <span class="blob -left-24 -top-20 animate-blob opacity-40"></span>
        <span class="blob -right-20 top-10 animate-blob opacity-35 [animation-delay:-5s]"></span>
        <span class="blob bottom-[-8rem] left-1/3 animate-blob opacity-30 [animation-delay:-9s]"></span>

        <div class="relative mx-auto grid max-w-7xl gap-10 px-4 py-14 lg:grid-cols-[1.1fr_0.9fr] lg:items-center lg:px-6 lg:py-20">
            <div class="reveal" data-reveal>
                <p class="inline-flex items-center gap-2 rounded-pill border border-brand-200 bg-white/80 px-3.5 py-1.5 text-xs font-black uppercase tracking-[0.18em] text-brand-700 shadow-soft">
                    <span class="h-2 w-2 animate-pulse-ring rounded-full bg-emerald-400"></span>
                    Independent reseller &middot; <?= e($address) ?>
                </p>

                <h1 class="mt-5 text-4xl font-black leading-[1.05] tracking-tight md:text-6xl">
                    <span class="animate-gradient text-gradient">One desk in Dhaka, a stack of subscriptions, and a promise we keep.</span>
                </h1>

                <p class="mt-5 max-w-xl text-base leading-relaxed text-ink-400 md:text-lg">
                    <?= $brandName ?> sells premium streaming, music, AI, design and storage plans at Bangladeshi
                    prices - in 1, 3, 6 and 12-month tiers - and delivers them by hand on WhatsApp. No app store
                    account juggling, no card in a foreign currency, no waiting on a ticket queue.
                </p>

                <div class="mt-8 flex flex-wrap items-center gap-3">
                    <a href="<?= e(route('plans')) ?>" class="group relative inline-flex items-center gap-2 overflow-hidden rounded-brand bg-gradient-to-r from-brand-600 via-brand-500 to-accent-500 px-6 py-3.5 text-sm font-black text-white shadow-glow transition-transform duration-200 hover:-translate-y-0.5">
                        <span class="animate-shimmer absolute inset-0 bg-gradient-to-r from-white/0 via-white/35 to-white/0" aria-hidden="true"></span>
                        <span class="relative">See the plans</span>
                        <svg class="relative h-4 w-4 transition-transform duration-200 group-hover:translate-x-1" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true">
                            <path d="M5 12h14M13 6l6 6-6 6" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </a>
                    <a href="<?= e(route('how')) ?>" class="rounded-brand border-2 border-brand-200 bg-white/70 px-6 py-3.5 text-sm font-bold text-brand-700 transition-colors duration-200 hover:border-brand-400 hover:bg-brand-50">
                        How an order runs
                    </a>
                </div>
            </div>

            {{-- photo-free "team of one" illustration, drawn inline --}}
            <div class="reveal relative" data-reveal style="--reveal-delay:120ms">
                <div class="glass animate-float rounded-card p-6 shadow-card">
                    <svg viewBox="0 0 320 220" class="w-full" role="img" aria-label="A single desk with a laptop, a phone showing a delivery message, and a stack of subscription cards.">
                        <defs>
                            <linearGradient id="aboutDesk" x1="0" y1="0" x2="1" y2="1">
                                <stop offset="0%" stop-color="#6a31e0"/>
                                <stop offset="100%" stop-color="#22d3ee"/>
                            </linearGradient>
                            <linearGradient id="aboutScreen" x1="0" y1="0" x2="0" y1="1">
                                <stop offset="0%" stop-color="#ffffff"/>
                                <stop offset="100%" stop-color="#efe7ff"/>
                            </linearGradient>
                        </defs>

                        <rect x="14" y="150" width="292" height="12" rx="6" fill="url(#aboutDesk)" opacity=".9"/>
                        <rect x="40" y="162" width="10" height="44" rx="5" fill="#c4b5fd"/>
                        <rect x="270" y="162" width="10" height="44" rx="5" fill="#c4b5fd"/>

                        <g class="animate-float">
                            <rect x="60" y="62" width="140" height="88" rx="8" fill="#1c0b47"/>
                            <rect x="68" y="70" width="124" height="72" rx="5" fill="url(#aboutScreen)"/>
                            <rect x="78" y="82" width="58" height="8" rx="4" fill="#6a31e0" opacity=".85"/>
                            <rect x="78" y="98" width="98" height="6" rx="3" fill="#a78bfa" opacity=".7"/>
                            <rect x="78" y="110" width="80" height="6" rx="3" fill="#a78bfa" opacity=".5"/>
                            <rect x="78" y="124" width="44" height="12" rx="6" fill="#22d3ee"/>
                        </g>

                        <g class="animate-float [animation-delay:-2s]">
                            <rect x="214" y="74" width="52" height="92" rx="10" fill="#1c0b47"/>
                            <rect x="220" y="82" width="40" height="74" rx="6" fill="#ffffff"/>
                            <rect x="226" y="90" width="28" height="7" rx="3.5" fill="#10b981"/>
                            <rect x="226" y="102" width="24" height="7" rx="3.5" fill="#c4b5fd"/>
                            <rect x="226" y="114" width="30" height="7" rx="3.5" fill="#c4b5fd"/>
                            <circle cx="240" cy="136" r="6" fill="#6a31e0"/>
                        </g>

                        <g class="animate-pop">
                            <path d="M26 96h68v30H58l-12 12v-12H26V96Z" fill="#10b981" opacity=".92"/>
                            <rect x="36" y="106" width="44" height="5" rx="2.5" fill="#ffffff"/>
                            <rect x="36" y="115" width="30" height="5" rx="2.5" fill="#d1fae5"/>
                        </g>

                        <g class="animate-float [animation-delay:-4s]">
                            <rect x="104" y="22" width="52" height="30" rx="8" fill="#f59e0b" opacity=".92"/>
                            <rect x="112" y="32" width="30" height="5" rx="2.5" fill="#fff7ed"/>
                            <rect x="112" y="41" width="18" height="5" rx="2.5" fill="#fde68a"/>
                        </g>
                    </svg>

                    <p class="mt-4 text-center text-xs font-semibold text-ink-400">
                        <?= e($agentName) ?> &middot; <?= e($agentRole) ?> &middot; <?= e($hours) ?>
                    </p>
                </div>

                <span class="blob -right-6 -top-6 animate-blob bg-accent-300/50" aria-hidden="true"></span>
            </div>
        </div>
    </section>

    {{-- ------------------------------ counters ------------------------------ --}}
    <section class="border-y border-brand-100 bg-gradient-to-r from-brand-50 via-white to-accent-50">
        <div class="mx-auto grid max-w-7xl grid-cols-2 gap-6 px-4 py-12 md:grid-cols-4 lg:px-6">
            <?php foreach ($stats as $stat): ?>
                <div class="reveal text-center" data-reveal>
                    <p class="text-3xl font-black tracking-tight text-ink md:text-4xl">
                        <span data-counter data-counter-to="<?= e((string) (int) $stat['to']) ?>" data-counter-suffix="<?= e((string) $stat['suffix']) ?>">
                            <?= e(number_format((float) $stat['to'])) ?><?= e((string) $stat['suffix']) ?>
                        </span>
                    </p>
                    <p class="mt-1 text-sm font-bold text-brand-700"><?= e((string) $stat['label']) ?></p>
                    <p class="mt-0.5 text-xs text-ink-400"><?= e((string) $stat['note']) ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    {{-- ------------------------------ the story ------------------------------ --}}
    <section class="mx-auto max-w-5xl px-4 py-16 lg:px-6">
        <?= $view->component('section-heading', [
            'eyebrow' => 'The story',
            'title' => 'Four things worth knowing before you order',
            'subtitle' => 'Written by the desk, not by a copywriter. If a sentence here is not true of the shop, it gets changed.',
        ]) ?>

        <div class="mt-10 space-y-6">
            <?php foreach ($chapters as $index => $chapter): ?>
                <article class="reveal grid gap-4 rounded-card border border-brand-100 bg-white p-6 shadow-card sm:grid-cols-[auto_1fr] sm:gap-6" data-reveal style="--reveal-delay:<?= e((string) ($index * 90)) ?>ms">
                    <span class="grid h-11 w-11 shrink-0 place-items-center rounded-card bg-gradient-to-br from-brand-600 to-accent-500 text-base font-black text-white shadow-pop">
                        <?= e((string) ($index + 1)) ?>
                    </span>
                    <div>
                        <h3 class="text-lg font-black tracking-tight text-ink md:text-xl"><?= e($chapter['title']) ?></h3>
                        <p class="mt-2 text-sm leading-relaxed text-ink-400"><?= e($chapter['body']) ?></p>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </section>

    {{-- ----------------------------- principles ----------------------------- --}}
    <section class="relative overflow-hidden bg-ink py-16">
        <span class="blob -left-20 top-[-4rem] animate-blob bg-brand-600/40"></span>
        <span class="blob -right-16 bottom-[-6rem] animate-blob bg-accent-500/30 [animation-delay:-7s]"></span>

        <div class="relative mx-auto max-w-7xl px-4 lg:px-6">
            <div class="reveal max-w-2xl" data-reveal>
                <p class="flex items-center gap-2 text-xs font-black uppercase tracking-[0.18em] text-accent-300">
                    <span class="h-px w-8 bg-gradient-to-r from-accent-300 to-brand-400"></span>
                    House rules
                </p>
                <h2 class="mt-2 text-3xl font-black tracking-tight text-white md:text-4xl">Four promises we would happily put in writing</h2>
                <p class="mt-3 text-base leading-relaxed text-white/70">They are, in fact, in writing - on the terms, privacy and refund pages.</p>
            </div>

            <div class="mt-10 grid gap-4 md:grid-cols-2">
                <?php foreach ($principles as $index => [$principleTitle, $principleText]): ?>
                    <div class="reveal rounded-card border border-white/10 bg-white/5 p-6 backdrop-blur-sm" data-reveal style="--reveal-delay:<?= e((string) ($index * 80)) ?>ms">
                        <span class="inline-flex h-10 w-10 items-center justify-center rounded-brand bg-gradient-to-br from-brand-500 to-accent-400 shadow-pop">
                            <svg class="h-5 w-5 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true">
                                <path d="m5 13 4 4L19 7" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </span>
                        <h3 class="mt-4 text-base font-black text-white"><?= e($principleTitle) ?></h3>
                        <p class="mt-1.5 text-sm leading-relaxed text-white/70"><?= e($principleText) ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    {{-- ----------------------------- testimonials ----------------------------- --}}
    <?php if ($testimonials !== []): ?>
        <section class="mx-auto max-w-7xl px-4 py-16 lg:px-6">
            <?= $view->component('section-heading', [
                'eyebrow' => 'In their words',
                'title' => 'Three customers who let us quote them',
                'subtitle' => 'First names, a district and the plan they bought - nothing else, and nobody invented.',
                'align' => 'center',
            ]) ?>

            <div class="mt-10 grid gap-5 md:grid-cols-3">
                <?php foreach ($testimonials as $index => $testimonial): ?>
                    <article class="reveal rounded-card border border-brand-100 bg-gradient-to-br from-white to-brand-50/60 p-6 shadow-card" data-reveal style="--reveal-delay:<?= e((string) ($index * 90)) ?>ms">
                        <p class="flex gap-0.5" aria-label="<?= e((string) (int) $testimonial['rating']) ?> out of 5">
                            <?php for ($star = 1; $star <= 5; $star++): ?>
                                <svg class="h-4 w-4 <?= e($star <= (int) $testimonial['rating'] ? 'text-amber-400' : 'text-brand-200') ?>" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="m12 2 2.9 6.3 6.9.7-5.1 4.6 1.4 6.8L12 17l-6 3.4 1.4-6.8L3 9l6.9-.7L12 2Z"/></svg>
                            <?php endfor; ?>
                        </p>
                        <p class="mt-4 text-sm leading-relaxed text-ink">&ldquo;<?= e((string) $testimonial['quote']) ?>&rdquo;</p>
                        <p class="mt-5 flex items-center gap-3 border-t border-dashed border-brand-200 pt-4">
                            <span class="grid h-9 w-9 shrink-0 place-items-center rounded-pill bg-gradient-to-br from-brand-600 to-accent-500 text-xs font-black text-white">
                                <?= e(mb_strtoupper(mb_substr(trim((string) $testimonial['name']), 0, 1))) ?>
                            </span>
                            <span class="min-w-0">
                                <span class="block truncate text-sm font-bold text-ink"><?= e((string) $testimonial['name']) ?></span>
                                <span class="block truncate text-xs text-ink-400">
                                    <?= e((string) $testimonial['service_label']) ?><?php if ((string) $testimonial['location'] !== ''): ?> &middot; <?= e((string) $testimonial['location']) ?><?php endif; ?>
                                </span>
                            </span>
                        </p>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    {{-- ------------------------------ honesty note ------------------------------ --}}
    <?php if ($note !== ''): ?>
        <section class="mx-auto max-w-5xl px-4 pb-16 lg:px-6">
            <div class="reveal rounded-card border-2 border-dashed border-brand-200 bg-brand-50/60 p-6" data-reveal>
                <p class="flex items-center gap-2 text-xs font-black uppercase tracking-[0.18em] text-brand-700">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8h.01" stroke-linecap="round"/>
                    </svg>
                    The part we are obliged to say clearly
                </p>
                <p class="mt-3 text-sm leading-relaxed text-ink-400"><?= e($note) ?></p>
                <p class="mt-3 text-sm leading-relaxed text-ink-400">
                    We are a single-desk reseller, not the brand’s own store: your account is provided by us
                    under a plan we purchased, prices are in Bangladeshi taka, and the
                    <strong class="font-bold text-ink"><?= e($guarantee) ?></strong> is our commitment rather
                    than the brand’s warranty. Anything we promise on this site, we honour from our own pocket.
                </p>
            </div>
        </section>
    <?php endif; ?>

    {{-- -------------------------------- CTA -------------------------------- --}}
    <section class="mx-auto max-w-7xl px-4 pb-20 lg:px-6">
        <div class="reveal relative overflow-hidden rounded-card bg-gradient-to-br from-brand-700 via-brand-600 to-accent-600 p-8 text-white shadow-pop md:p-10" data-reveal>
            <span class="blob -right-10 -top-14 animate-blob bg-white/15" aria-hidden="true"></span>
            <div class="relative grid gap-6 md:grid-cols-[1.4fr_1fr] md:items-center">
                <div>
                    <h2 class="text-2xl font-black tracking-tight md:text-3xl">Ask us anything - the same person answers on WhatsApp and by email.</h2>
                    <p class="mt-3 text-sm leading-relaxed text-white/80">
                        Open <?= e($hours) ?>. Email goes to <?= e($email) ?> and is read once a day between
                        chats. If you are deciding between two tiers, message us the number of people and what
                        you watch - we will tell you the cheaper one.
                    </p>
                </div>
                <div class="flex flex-col gap-3">
                    <a href="<?= e($whatsappUrl) ?>" target="_blank" rel="noopener" class="group relative flex items-center justify-center gap-2 overflow-hidden rounded-brand bg-white px-6 py-3.5 text-sm font-black text-brand-700 shadow-soft transition-transform duration-200 hover:-translate-y-0.5">
                        <span class="animate-shimmer absolute inset-0 bg-gradient-to-r from-white/0 via-brand-100/70 to-white/0" aria-hidden="true"></span>
                        <span class="relative">Message <?= e($agentName) ?></span>
                    </a>
                    <a href="mailto:<?= e($email) ?>" class="rounded-brand border-2 border-white/40 px-6 py-3 text-center text-sm font-bold text-white transition-colors duration-200 hover:bg-white/10">
                        Email <?= e($email) ?>
                    </a>
                    <a href="<?= e(route('contact')) ?>" class="text-center text-xs font-semibold text-white/70 underline decoration-white/40 underline-offset-4 hover:text-white">
                        Or use the contact form
                    </a>
                </div>
            </div>
        </div>
    </section>
</div>
