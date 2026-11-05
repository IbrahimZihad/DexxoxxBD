<?php

declare(strict_types=1);

/**
 * Home page. Data comes from HomeController::index():
 * $services, $featured, $testimonials, $faqs, $bestSellers, $priceRange, $stats,
 * $rating, $jsonLd.
 *
 * @var App\Core\View $view
 * @var list<array<string, mixed>> $services
 * @var list<array<string, mixed>> $featured
 * @var list<array<string, mixed>> $testimonials
 * @var list<array<string, mixed>> $faqs
 * @var list<array<string, mixed>> $bestSellers
 * @var array{min: float, max: float} $priceRange
 * @var list<array<string, mixed>> $stats
 * @var string $rating
 * @var string $jsonLd
 */
$brandName = setting('brand.name', 'DexxozzBD');
$shareNames = array_slice(array_map(static fn (array $row): string => (string) $row['name'], $services), 0, 3);

$view->extend('layouts/app');
$view->setSection('title', $brandName . ' - ' . ($shareNames === [] ? 'Premium subscriptions' : implode(', ', $shareNames) . ' plans') . ' in Bangladesh');
$view->setSection('meta_description', setting('meta.description'));
$view->setSection('canonical', route('home'));
$view->setSection('json_ld', $jsonLd);

$steps = [
    ['Pick a plan', 'Choose the brand, the seats and how many months you want. The price you see is the price you pay.', 'M4 6h16M4 12h10M4 18h7'],
    ['Pay with bKash or Nagad', 'Send the exact total to the number on the checkout page and paste the transaction ID.', 'M12 3v18M7 8h10M7 16h10'],
    ['Get it on WhatsApp', 'A human verifies the payment and sends your login within minutes - no ticket queue.', 'M4 5h16v10H8l-4 4V5Z'],
];
?>
<div class="bg-white">
    <?php /* ------------------------------ hero ------------------------------*/ ?>
    <section class="relative overflow-hidden bg-gradient-to-b from-brand-50 via-white to-white">
        <span class="blob -left-24 -top-24 animate-blob"></span>
        <span class="blob -right-16 top-10 animate-blob [animation-delay:-4s]"></span>
        <span class="blob bottom-[-6rem] left-1/3 animate-blob [animation-delay:-8s] opacity-40"></span>

        <div class="relative mx-auto grid max-w-7xl gap-12 px-4 py-16 lg:grid-cols-[1.15fr_0.85fr] lg:items-center lg:px-6 lg:py-24">
            <div>
                <?php if (setting('hero.badge') !== ''): ?>
                    <p class="animate-pop inline-flex items-center gap-2 rounded-pill border border-brand-200 bg-white/80 px-3.5 py-1.5 text-xs font-bold uppercase tracking-widest text-brand-700 shadow-soft">
                        <span class="h-2 w-2 animate-pulse-ring rounded-full bg-accent-400"></span>
                        <?= e(setting('hero.badge')) ?>
                    </p>
                <?php endif; ?>

                <h1 class="mt-5 text-4xl font-black leading-[1.05] tracking-tight md:text-6xl">
                    <span class="animate-gradient text-gradient"><?= e(setting('hero.title', 'Every premium app you actually use, at a Bangladeshi price.')) ?></span>
                </h1>

                <p class="mt-5 max-w-xl text-base leading-relaxed text-ink-400 md:text-lg">
                    <?= e(setting('hero.subtitle', 'Pick a plan, pay with bKash or Nagad, and get your account details on WhatsApp within minutes.')) ?>
                </p>

                <div class="mt-8 flex flex-wrap items-center gap-3">
                    <a href="<?= e(route('plans')) ?>" class="group relative inline-flex items-center gap-2 overflow-hidden rounded-brand bg-gradient-to-r from-brand-600 via-brand-500 to-accent-500 px-7 py-4 text-base font-black text-white shadow-glow transition-transform duration-200 hover:-translate-y-0.5">
                        <span class="animate-shimmer absolute inset-0 bg-gradient-to-r from-white/0 via-white/35 to-white/0"></span>
                        Browse all plans
                        <svg class="h-5 w-5 transition-transform duration-200 group-hover:translate-x-1" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true">
                            <path d="M5 12h14M13 6l6 6-6 6" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </a>
                    <a href="<?= e(route('how')) ?>" class="inline-flex items-center gap-2 rounded-brand border-2 border-brand-200 bg-white/70 px-6 py-4 text-base font-bold text-brand-700 transition-colors duration-200 hover:border-brand-400 hover:bg-brand-50">
                        How it works
                    </a>
                </div>

                <div class="mt-8 flex flex-wrap items-center gap-x-6 gap-y-3 text-sm">
                    <span class="flex items-center gap-2 font-semibold text-ink">
                        <span class="flex" aria-hidden="true">
                            <?php for ($star = 1; $star <= 5; $star++): ?>
                                <svg class="h-4 w-4 <?= e($star <= (int) round((float) $rating) ? 'text-amber-400' : 'text-brand-200') ?>" viewBox="0 0 24 24" fill="currentColor"><path d="m12 2 2.9 6.3 6.9.7-5.1 4.6 1.4 6.8L12 17l-6 3.4 1.4-6.8L3 9l6.9-.7L12 2Z"/></svg>
                            <?php endfor; ?>
                        </span>
                        <?= e($rating) ?>/5 from real customers
                    </span>
                    <span class="flex items-center gap-2 font-semibold text-emerald-700">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path d="m5 13 4 4L19 7" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        <?= e(setting('trust.guarantee')) ?>
                    </span>
                </div>
            </div>

            <div class="relative">
                <div class="glass animate-float rounded-card p-6">
                    <p class="text-xs font-black uppercase tracking-widest text-brand-600">Starts from</p>
                    <p class="mt-1 text-4xl font-black tracking-tight text-ink"><?= e(taka($priceRange['min'], false)) ?></p>
                    <p class="mt-1 text-sm text-ink-400">up to <?= e(taka($priceRange['max'], false)) ?> for a full year.</p>

                    <div class="mt-5 space-y-3 border-t border-brand-100 pt-5">
                        <?php foreach (array_slice($services, 0, 3) as $teaser): ?>
                            <a href="<?= e(route('service.show', ['slug' => (string) $teaser['slug']])) ?>" class="flex items-center gap-3 rounded-brand p-2 transition-colors duration-200 hover:bg-brand-50">
                                <span class="grid h-9 w-9 shrink-0 place-items-center rounded-brand text-sm font-black text-white" style="background-image:linear-gradient(135deg,<?= e((string) $teaser['brand_color']) ?>,#1c0b47)">
                                    <?= e((string) $teaser['logo_mark']) ?>
                                </span>
                                <span class="min-w-0 grow">
                                    <span class="block truncate text-sm font-bold text-ink"><?= e((string) $teaser['name']) ?></span>
                                    <span class="block truncate text-xs text-ink-400"><?= e((string) $teaser['tagline']) ?></span>
                                </span>
                                <span class="shrink-0 text-sm font-black text-brand-700"><?= e(taka((float) $teaser['from_price'], false)) ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>

                    <a href="<?= e(route('plans')) ?>" class="mt-5 block rounded-brand bg-brand-50 px-4 py-3 text-center text-sm font-bold text-brand-700 transition-colors duration-200 hover:bg-brand-100">
                        Compare every tier
                    </a>
                </div>
            </div>
        </div>

        <?php if ($bestSellers !== []): ?>
            <div class="relative mx-auto max-w-7xl px-4 pb-10 lg:px-6">
                <div class="no-scrollbar flex items-center gap-2 overflow-x-auto">
                    <span class="shrink-0 text-xs font-black uppercase tracking-widest text-ink-400">Trending</span>
                    <?php foreach ($bestSellers as $seller): ?>
                        <span class="shrink-0 rounded-pill border border-amber-200 bg-amber-50 px-3 py-1.5 text-xs font-bold text-amber-800">
                            <?= e((string) $seller['service_name']) ?>
                            <span class="text-amber-600">&middot; <?= e((string) (int) $seller['times']) ?> sold</span>
                        </span>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </section>

    <?php /* ---------------------------- trust strip ----------------------------*/ ?>
    <section class="border-y border-brand-100 bg-gradient-to-r from-brand-50 via-white to-accent-50">
        <div class="mx-auto grid max-w-7xl grid-cols-2 gap-6 px-4 py-10 md:grid-cols-4 lg:px-6">
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

    <?php /* --------------------------- how it works ---------------------------*/ ?>
    <section class="relative mx-auto max-w-7xl px-4 py-16 lg:px-6">
        <?= $view->component('section-heading', [
            'eyebrow' => 'Three steps',
            'title' => 'Order today, watch tonight',
            'subtitle' => 'No card forms, no waiting for an invoice. Pay the way everyone in Bangladesh already pays.',
            'align' => 'center',
        ]) ?>

        <div class="mt-10 grid gap-6 md:grid-cols-3">
            <?php foreach ($steps as $index => [$stepTitle, $stepText, $stepPath]): ?>
                <div class="reveal hover-lift relative overflow-hidden rounded-card border border-brand-100 bg-white p-6 shadow-soft" data-reveal style="--reveal-delay:<?= e((string) ($index * 90)) ?>ms">
                    <span class="blob -right-20 -top-20 h-32 w-32 opacity-20"></span>
                    <span class="relative grid h-12 w-12 place-items-center rounded-brand bg-gradient-to-br from-brand-600 to-accent-500 text-lg font-black text-white shadow-pop">
                        <?= e((string) ($index + 1)) ?>
                    </span>
                    <h3 class="relative mt-4 text-lg font-bold text-ink"><?= e($stepTitle) ?></h3>
                    <p class="relative mt-2 text-sm leading-relaxed text-ink-400"><?= e($stepText) ?></p>
                    <svg class="relative mt-4 h-6 w-6 text-accent-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                        <path d="<?= e($stepPath) ?>" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <?php /* ------------------------- featured plans ---------------------------*/ ?>
    <?php if ($featured !== []): ?>
        <section class="relative overflow-hidden bg-gradient-to-b from-white via-brand-50/60 to-white py-16">
            <div class="relative mx-auto max-w-7xl px-4 lg:px-6">
                <?= $view->component('section-heading', [
                    'eyebrow' => 'Popular right now',
                    'title' => 'Start a plan from ' . taka($priceRange['min'], false),
                    'subtitle' => 'One entry price per brand - open a brand page for the 3, 6 and 12-month tiers.',
                ]) ?>

                <div class="mt-10 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                    <?php foreach ($featured as $plan): ?>
                        <?= $view->component('plan-card', ['plan' => $plan]) ?>
                    <?php endforeach; ?>
                </div>

                <p class="mt-10 text-center">
                    <a href="<?= e(route('plans')) ?>" class="inline-flex items-center gap-2 rounded-brand bg-ink px-6 py-3 text-sm font-bold text-white transition-colors duration-200 hover:bg-brand-700">
                        See all <?= e((string) $stats[3]['to']) ?> plans
                    </a>
                </p>
            </div>
        </section>
    <?php endif; ?>

    <?php /* ------------------------- brand catalogue --------------------------*/ ?>
    <?php if ($services !== []): ?>
        <section class="mx-auto max-w-7xl px-4 py-16 lg:px-6">
            <?= $view->component('section-heading', [
                'eyebrow' => 'On the shelf',
                'title' => 'Pick your brand',
                'subtitle' => 'Streaming, music, AI, design and cloud storage - each with monthly, quarterly and yearly tiers.',
            ]) ?>

            <div class="mt-10 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                <?php foreach ($services as $service): ?>
                    <?= $view->component('service-tile', ['service' => $service]) ?>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <?php /* --------------------------- testimonials ---------------------------*/ ?>
    <?php if ($testimonials !== []): ?>
        <section class="overflow-hidden border-y border-brand-100 bg-ink py-16 text-white">
            <div class="mx-auto max-w-7xl px-4 lg:px-6">
                <?= $view->component('section-heading', [
                    'eyebrow' => 'What customers say',
                    'title' => 'Rated ' . $rating . '/5 by people who already paid',
                    'subtitle' => 'Quotes from delivered orders - the whole point of buying from a human.',
                ]) ?>
            </div>

            <div class="mt-10 overflow-hidden">
                <div data-marquee class="flex w-max gap-5 px-5 animate-marquee hover:[animation-play-state:paused]">
                    <div data-marquee-track class="flex shrink-0 gap-5">
                        <?php foreach ($testimonials as $testimonial): ?>
                            <article class="w-80 shrink-0 rounded-card border border-white/10 bg-white/5 p-5">
                                <p class="flex gap-0.5" aria-label="<?= e((string) (int) $testimonial['rating']) ?> out of 5">
                                    <?php for ($star = 1; $star <= 5; $star++): ?>
                                        <svg class="h-4 w-4 <?= e($star <= (int) $testimonial['rating'] ? 'text-amber-400' : 'text-white/20') ?>" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="m12 2 2.9 6.3 6.9.7-5.1 4.6 1.4 6.8L12 17l-6 3.4 1.4-6.8L3 9l6.9-.7L12 2Z"/></svg>
                                    <?php endfor; ?>
                                </p>
                                <p class="mt-3 text-sm leading-relaxed text-white/85">&ldquo;<?= e((string) $testimonial['quote']) ?>&rdquo;</p>
                                <p class="mt-4 text-sm font-bold text-white"><?= e((string) $testimonial['name']) ?></p>
                                <p class="text-xs text-accent-300">
                                    <?= e((string) $testimonial['service_label']) ?> &middot; <?= e((string) $testimonial['location']) ?>
                                </p>
                            </article>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </section>
    <?php endif; ?>

    <?php /* ------------------------------ faq ---------------------------------*/ ?>
    <?php if ($faqs !== []): ?>
        <section class="mx-auto max-w-3xl px-4 py-16 lg:px-6">
            <?= $view->component('section-heading', [
                'eyebrow' => 'Before you pay',
                'title' => 'Questions we get every day',
                'align' => 'center',
            ]) ?>

            <div data-accordion class="mt-8 divide-y divide-brand-100 overflow-hidden rounded-card border border-brand-100 bg-white shadow-soft">
                <?php foreach ($faqs as $index => $faq): ?>
                    <div>
                        <button type="button" data-accordion-btn aria-expanded="<?= e($index === 0 ? 'true' : 'false') ?>" class="flex w-full items-center justify-between gap-4 px-5 py-4 text-left text-sm font-bold text-ink transition-colors duration-200 hover:bg-brand-50">
                            <span><?= e((string) $faq['question']) ?></span>
                            <svg class="h-5 w-5 shrink-0 text-brand-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                <path d="m6 9 6 6 6-6" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </button>
                        <div data-accordion-panel <?= e($index === 0 ? '' : 'hidden') ?> class="px-5 pb-4 text-sm leading-relaxed text-ink-400">
                            <?= e((string) $faq['answer']) ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <p class="mt-6 text-center text-sm">
                <a href="<?= e(route('faq')) ?>" class="font-bold text-brand-700 underline decoration-accent-400 decoration-2 underline-offset-4">Read the full FAQ</a>
            </p>
        </section>
    <?php endif; ?>

    <?php /* -------------------------- whatsapp band ---------------------------*/ ?>
    <section class="relative overflow-hidden">
        <div class="animate-gradient absolute inset-0 bg-gradient-to-r from-brand-700 via-brand-500 to-accent-500"></div>
        <span class="blob -left-10 -top-16 opacity-40"></span>

        <div class="relative mx-auto flex max-w-7xl flex-col items-center gap-6 px-4 py-14 text-center lg:flex-row lg:justify-between lg:px-6 lg:text-left">
            <div>
                <h2 class="text-2xl font-black tracking-tight text-white md:text-3xl">Still deciding? Ask a human first.</h2>
                <p class="mt-2 max-w-xl text-sm leading-relaxed text-white/85">
                    Tell us what you watch or work with and we will name the cheapest tier that fits - usually
                    <?= e(setting('whatsapp.agent_name', 'our order desk')) ?> replies in a couple of minutes.
                </p>
            </div>
            <a href="<?= e(wa_link('Hi ' . $brandName . ', which plan do you suggest for me?')) ?>"
               target="_blank" rel="noopener"
               class="inline-flex shrink-0 items-center gap-3 rounded-brand bg-white px-6 py-4 text-base font-black text-brand-700 shadow-card transition-transform duration-200 hover:-translate-y-0.5">
                <svg class="h-6 w-6 text-emerald-600" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                    <path d="M12 2a10 10 0 0 0-8.6 15L2 22l5.2-1.3A10 10 0 1 0 12 2Zm5.3 14c-.2.6-1.2 1.2-1.7 1.2-.5.1-1 .1-1.7-.1-.9-.3-2.3-1-3.6-2.6-1-1.2-1.6-2.5-1.7-3-.1-.6.3-1.4.8-1.8.2-.2.4-.2.6-.2h.5c.2 0 .3 0 .4.3l.6 1.4c.1.2 0 .4-.1.5l-.4.5c-.1.1-.1.3 0 .4.3.5 1.2 1.5 2.1 1.9.2.1.3.1.5-.1l.5-.6c.1-.2.3-.2.5-.1l1.4.7c.2.1.3.2.3.4 0 .2 0 .7-.3 1.2Z"/>
                </svg>
                Chat on WhatsApp
            </a>
        </div>
    </section>
</div>
