<?php

declare(strict_types=1);

use App\Services\PaymentService;

/**
 * Single service page. Data comes from CatalogController::show():
 * $service, $plans, $comparison, $related, $faqs, $priceRange, $jsonLd.
 *
 * @var App\Core\View $view
 * @var array<string, mixed> $service
 * @var list<array<string, mixed>> $plans
 * @var list<array<string, mixed>> $comparison
 * @var list<array<string, mixed>> $related
 * @var list<array<string, mixed>> $faqs
 * @var array{min: float, max: float} $priceRange
 * @var string $jsonLd
 */
$brandName = setting('brand.name', 'ZapSub');
$serviceName = (string) ($service['name'] ?? 'Plan');
$serviceSlug = (string) ($service['slug'] ?? '');
$category = (string) ($service['category'] ?? '');
$tagline = (string) ($service['tagline'] ?? '');
$description = (string) ($service['description'] ?? '');

// A raw hex colour from the admin field goes into a style attribute, so it is
// validated here and falls back to the brand violet.
$rawColor = (string) ($service['brand_color'] ?? '');
$brand = preg_match('/^#[0-9a-fA-F]{3,8}$/', $rawColor) === 1 ? $rawColor : '#7c50f2';
$mark = trim((string) ($service['logo_mark'] ?? '')) !== '' ? trim((string) $service['logo_mark']) : mb_substr($serviceName, 0, 1);

$heroStyle = 'background-image:linear-gradient(140deg,' . $brand . ' 0%,#1c0b47 82%)';

$cheapestMonth = $plans === [] ? 0.0 : min(array_map(
    static fn (array $plan): float => (float) ($plan['price'] ?? 0) / max(1, (int) ($plan['duration_months'] ?? 1)),
    $plans
));

$monthList = [];
foreach ($plans as $plan) {
    $monthList[(int) ($plan['duration_months'] ?? 1)] = true;
}
ksort($monthList);
$monthLabels = array_map(static fn (int $months): string => $months === 1 ? '1 month' : $months . ' months', array_keys($monthList));

$view->extend('layouts/app');
$view->setSection('title', $serviceName . ' plans in Bangladesh from ' . taka($priceRange['min'], false) . ' - ' . $brandName);
$view->setSection('meta_description', $tagline === ''
    ? $serviceName . ' plans from ' . taka($priceRange['min'], false) . ' for 1 to 12 months, delivered on WhatsApp by ' . $brandName . '.'
    : $tagline . ' Live from ' . taka($priceRange['min'], false) . ' with ' . $brandName . '.');
$view->setSection('canonical', route('service.show', ['slug' => $serviceSlug]));
$view->setSection('json_ld', $jsonLd);
?>
<div class="bg-white">
    <?php /* ----------------------------- brand hero ----------------------------*/ ?>
    <section class="relative overflow-hidden text-white" style="<?= e($heroStyle) ?>">
        <span class="blob -left-16 -top-20 animate-blob bg-white/10 opacity-70"></span>
        <span class="blob right-0 top-10 animate-blob [animation-delay:-5s] bg-white/10 opacity-60"></span>
        <span class="animate-gradient absolute inset-x-0 bottom-0 h-1 bg-gradient-to-r from-accent-400 via-white to-accent-400"></span>

        <div class="relative mx-auto max-w-7xl px-4 py-14 lg:px-6">
            <nav aria-label="Breadcrumb">
                <ol class="flex flex-wrap items-center gap-2 text-xs font-semibold text-white/70">
                    <li><a href="<?= e(route('home')) ?>" class="transition-colors duration-200 hover:text-white">Home</a></li>
                    <li aria-hidden="true">&rsaquo;</li>
                    <li><a href="<?= e(route('plans')) ?>" class="transition-colors duration-200 hover:text-white">Plans</a></li>
                    <li aria-hidden="true">&rsaquo;</li>
                    <li aria-current="page" class="text-white"><?= e($serviceName) ?></li>
                </ol>
            </nav>

            <div class="mt-6 grid gap-10 lg:grid-cols-[1.1fr_0.9fr] lg:items-center">
                <div>
                    <div class="flex flex-wrap items-center gap-3">
                        <span class="grid h-14 w-14 place-items-center rounded-card bg-white/15 text-2xl font-black ring-1 ring-white/30 backdrop-blur">
                            <?= e($mark) ?>
                        </span>
                        <div>
                            <h1 class="text-3xl font-black tracking-tight md:text-4xl"><?= e($serviceName) ?></h1>
                            <?php if ($category !== ''): ?>
                                <p class="mt-1 text-xs font-black uppercase tracking-[0.18em] text-white/70"><?= e($category) ?></p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if ($tagline !== ''): ?>
                        <p class="mt-5 max-w-xl text-base leading-relaxed text-white/85 md:text-lg"><?= e($tagline) ?></p>
                    <?php endif; ?>

                    <?php if ($description !== ''): ?>
                        <p class="mt-3 max-w-xl text-sm leading-relaxed text-white/70"><?= e($description) ?></p>
                    <?php endif; ?>

                    <div class="mt-7 flex flex-wrap items-center gap-3">
                        <a href="#compare" class="group relative inline-flex items-center gap-2 overflow-hidden rounded-brand bg-white px-6 py-3.5 text-sm font-black text-ink transition-transform duration-200 hover:-translate-y-0.5">
                            <span class="animate-shimmer absolute inset-0 bg-gradient-to-r from-white/0 via-brand-100/70 to-white/0"></span>
                            Compare the tiers
                            <svg class="h-4 w-4 transition-transform duration-200 group-hover:translate-y-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true">
                                <path d="M12 5v14M6 13l6 6 6-6" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </a>
                        <a href="<?= e(wa_link('Hi ' . $brandName . ', I have a question about the ' . $serviceName . ' plans.')) ?>"
                           target="_blank" rel="noopener"
                           class="inline-flex items-center gap-2 rounded-brand border-2 border-white/40 px-6 py-3.5 text-sm font-bold text-white transition-colors duration-200 hover:bg-white/10">
                            Ask about this service
                        </a>
                    </div>

                    <?php if ($monthLabels !== []): ?>
                        <p class="mt-5 text-sm font-semibold text-white/75">
                            Live tiers: <?= e(implode(' · ', $monthLabels)) ?>
                        </p>
                    <?php endif; ?>
                </div>

                <div class="glass animate-float rounded-card p-6 text-ink">
                    <p class="text-xs font-black uppercase tracking-widest text-brand-600">This brand starts at</p>
                    <p class="mt-1 text-4xl font-black tracking-tight"><?= e(taka($priceRange['min'], false)) ?></p>
                    <p class="mt-1 text-sm text-ink-400">
                        only <?= e(taka($cheapestMonth, false)) ?> a month on the longest plan.
                    </p>

                    <ul class="mt-5 space-y-2.5 border-t border-brand-100 pt-5 text-sm">
                        <li class="flex items-start gap-2">
                            <svg class="mt-0.5 h-4 w-4 shrink-0 text-emerald-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path d="m5 13 4 4L19 7" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            <?= e(setting('trust.guarantee', '7-day replacement on every personal plan')) ?>
                        </li>
                        <li class="flex items-start gap-2">
                            <svg class="mt-0.5 h-4 w-4 shrink-0 text-emerald-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path d="m5 13 4 4L19 7" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            Delivered in about <?= e(setting('trust.delivery_minutes', '10')) ?> minutes on WhatsApp
                        </li>
                        <li class="flex items-start gap-2">
                            <svg class="mt-0.5 h-4 w-4 shrink-0 text-emerald-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path d="m5 13 4 4L19 7" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            Pay with bKash, Nagad, Rocket, bank or card
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </section>

    <?php /* ------------------------- tier comparison --------------------------*/ ?>
    <?php if ($comparison !== []): ?>
        <section id="compare" class="mx-auto max-w-7xl scroll-mt-24 px-4 py-16 lg:px-6">
            <?= $view->component('section-heading', [
                'eyebrow' => 'Side by side',
                'title' => 'Which ' . $serviceName . ' length pays best?',
                'subtitle' => 'Monthly pricing gets cheaper the longer you commit - the last column is what each tier really costs you per month.',
            ]) ?>

            <div <?= count($comparison) > 1 ? 'data-tabs' : '' ?> class="mt-8">
                <?php if (count($comparison) > 1): ?>
                    <div role="tablist" aria-label="<?= e($serviceName) ?> plan variants" class="no-scrollbar flex gap-2 overflow-x-auto pb-1">
                        <?php foreach ($comparison as $index => $table): ?>
                            <button type="button"
                                    role="tab"
                                    data-tab-btn="<?= e((string) $table['variant']) ?>"
                                    aria-selected="<?= e($index === 0 ? 'true' : 'false') ?>"
                                    class="shrink-0 rounded-pill border border-brand-200 bg-white px-5 py-2.5 text-sm font-bold text-ink-400 transition-colors duration-200 hover:text-brand-700 aria-selected:bg-gradient-to-r aria-selected:from-brand-600 aria-selected:to-brand-500 aria-selected:text-white aria-selected:shadow-pop <?= e($index === 0 ? 'is-active' : '') ?>">
                                <?= e((string) $table['variant']) ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-sm font-semibold text-ink-400">One variant on sale, every length below.</p>
                <?php endif; ?>

                <?php foreach ($comparison as $index => $table): ?>
                <div data-tab-panel="<?= e((string) $table['variant']) ?>"
                     role="<?= e(count($comparison) > 1 ? 'tabpanel' : 'presentation') ?>"
                     <?= e($index === 0 ? '' : 'hidden') ?>
                     class="mt-5 overflow-hidden rounded-card border border-brand-100 shadow-soft">
                    <div class="overflow-x-auto">
                        <table class="a-table w-full min-w-[640px] text-left text-sm">
                            <caption class="sr-only">
                                <?= e((string) $table['variant']) ?> pricing for <?= e($serviceName) ?> by plan length
                            </caption>
                            <thead>
                                <tr>
                                    <th scope="col">Length</th>
                                    <th scope="col">Total price</th>
                                    <th scope="col">Per month</th>
                                    <th scope="col">You save</th>
                                    <th scope="col">Seats</th>
                                    <th scope="col">Stock</th>
                                    <th scope="col" class="text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ((array) $table['rows'] as $row): ?>
                                    <?php $rowStock = (int) ($row['stock'] ?? -1); ?>
                                    <tr>
                                        <th scope="row" class="font-bold text-ink">
                                            <?= e($row['months'] === 1 ? '1 month' : $row['months'] . ' months') ?>
                                            <?php if ((string) $row['badge'] !== ''): ?>
                                                <span class="ml-2 rounded-pill bg-brand-100 px-2 py-0.5 text-[11px] font-black uppercase tracking-wide text-brand-700"><?= e((string) $row['badge']) ?></span>
                                            <?php endif; ?>
                                        </th>
                                        <td class="text-base font-black text-ink"><?= e(taka((float) $row['price'], false)) ?></td>
                                        <td class="font-semibold text-brand-700"><?= e(taka((float) $row['per_month'], false)) ?></td>
                                        <td class="<?= e((float) $row['saving'] > 0 ? 'font-bold text-lime-700' : 'text-ink-400') ?>">
                                            <?php if ((float) $row['saving'] > 0): ?>
                                                <?= e(taka((float) $row['saving'], false)) ?>
                                            <?php else: ?>
                                                &mdash;
                                            <?php endif; ?>
                                        </td>
                                        <td><?= e((int) $row['seats'] === 1 ? '1 seat' : (int) $row['seats'] . ' seats') ?></td>
                                        <td>
                                            <?php if ($rowStock === 0): ?>
                                                <span class="font-bold text-rose-600">Sold out</span>
                                            <?php elseif ($rowStock > 0 && $rowStock <= 3): ?>
                                                <span class="font-bold text-amber-600">Only <?= e((string) $rowStock) ?></span>
                                            <?php else: ?>
                                                <span class="text-ink-400">Available</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-right">
                                            <?php if ($rowStock === 0): ?>
                                                <a href="<?= e(wa_link('Is the ' . $serviceName . ' ' . (string) $row['title'] . ' tier back in stock?')) ?>"
                                                   target="_blank" rel="noopener"
                                                   class="text-xs font-bold text-brand-700 underline decoration-accent-400 decoration-2 underline-offset-4">Ask us</a>
                                            <?php else: ?>
                                                <form data-ajax method="post" action="<?= e(route('cart.add')) ?>" class="inline-flex">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="plan_id" value="<?= e((string) $row['plan_id']) ?>">
                                                    <input type="hidden" name="quantity" value="1">
                                                    <button type="submit" class="rounded-pill bg-ink px-4 py-2 text-xs font-bold text-white transition-colors duration-200 hover:bg-brand-700 active:scale-95">
                                                        Add to cart
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <?php /* --------------------------- plan cards ------------------------------*/ ?>
    <?php if ($plans !== []): ?>
        <section class="bg-gradient-to-b from-white via-brand-50/60 to-white py-16">
            <div class="mx-auto max-w-7xl px-4 lg:px-6">
                <?= $view->component('section-heading', [
                    'eyebrow' => 'Buy straight away',
                    'title' => 'Every ' . $serviceName . ' tier we can deliver today',
                    'subtitle' => 'Add one to the cart, pay with a mobile wallet, and we hand over the login on WhatsApp.',
                ]) ?>

                <div class="mt-10 grid gap-6 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                    <?php foreach ($plans as $plan): ?>
                        <?= $view->component('plan-card', ['plan' => $plan]) ?>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
    <?php endif; ?>

    <?php /* --------------------------- delivery note ---------------------------*/ ?>
    <section class="mx-auto max-w-7xl px-4 py-16 lg:px-6">
        <div class="reveal overflow-hidden rounded-card border border-brand-100 bg-white shadow-card" data-reveal>
            <div class="grid gap-0 md:grid-cols-[1.05fr_0.95fr]">
                <div class="p-6 md:p-8">
                    <?= $view->component('section-heading', [
                        'eyebrow' => 'After you order',
                        'title' => 'How delivery works',
                    ]) ?>

                    <ol class="mt-6 space-y-4 text-sm leading-relaxed text-ink-400">
                        <li class="flex gap-3">
                            <span class="grid h-7 w-7 shrink-0 place-items-center rounded-full bg-brand-600 text-xs font-black text-white">1</span>
                            <span>Checkout asks for your name, WhatsApp number and the email the account should sit on.</span>
                        </li>
                        <li class="flex gap-3">
                            <span class="grid h-7 w-7 shrink-0 place-items-center rounded-full bg-brand-600 text-xs font-black text-white">2</span>
                            <span>
                                <?= e(setting('payment.instruction', 'Send the exact amount with any personal number, then paste the TrxID below. We verify it manually and deliver within minutes.')) ?>
                            </span>
                        </li>
                        <li class="flex gap-3">
                            <span class="grid h-7 w-7 shrink-0 place-items-center rounded-full bg-brand-600 text-xs font-black text-white">3</span>
                            <span>A human matches the reference and sends the credentials, usually inside
                                <?= e(setting('trust.delivery_minutes', '10')) ?> minutes during open hours.</span>
                        </li>
                        <li class="flex gap-3">
                            <span class="grid h-7 w-7 shrink-0 place-items-center rounded-full bg-brand-600 text-xs font-black text-white">4</span>
                            <span>Something stops working? Unpaid orders hold for
                                <?= e(setting('order.pending_hold_minutes', '90')) ?> minutes, and
                                <?= e(setting('trust.guarantee')) ?> covers the plan itself.</span>
                        </li>
                    </ol>

                    <p class="mt-6 text-xs text-ink-400"><?= e(setting('footer.note')) ?></p>
                </div>

                <div class="bg-gradient-to-br from-brand-50 to-accent-50 p-6 md:p-8">
                    <p class="text-xs font-black uppercase tracking-widest text-brand-700">Rails we accept right now</p>
                    <ul class="mt-4 space-y-3">
                        <?php foreach (PaymentService::available() as $rail): ?>
                            <?php $railNumber = PaymentService::numberFor((string) ($rail['number_key'] ?? '')); ?>
                            <li class="flex items-center justify-between gap-3 rounded-brand border border-white bg-white/80 px-4 py-3">
                                <span class="flex items-center gap-3">
                                    <span class="h-3 w-3 shrink-0 rounded-full" style="background-color:<?= e((string) ($rail['color'] ?? '#7c50f2')) ?>"></span>
                                    <span class="text-sm font-bold text-ink"><?= e((string) $rail['label']) ?></span>
                                </span>
                                <?php if ($railNumber !== ''): ?>
                                    <button type="button" data-copy data-copy-value="<?= e($railNumber) ?>"
                                            class="rounded-pill bg-brand-50 px-3 py-1.5 text-xs font-bold text-brand-700 transition-colors duration-200 hover:bg-brand-100">
                                        <?= e($railNumber) ?>
                                        <span class="ml-1 text-ink-400">copy</span>
                                    </button>
                                <?php else: ?>
                                    <span class="text-xs font-semibold text-ink-400">at checkout</span>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>

                    <p class="mt-5 text-xs leading-relaxed text-ink-400">
                        Numbers are shown so you can send money before the order exists - paste the TrxID in checkout
                        and it lands in the same order.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <?php /* ------------------------------- faq ---------------------------------*/ ?>
    <?php if ($faqs !== []): ?>
        <section class="mx-auto max-w-3xl px-4 pb-16 lg:px-6">
            <?= $view->component('section-heading', [
                'eyebrow' => 'Good to know',
                'title' => 'Questions about ordering ' . $serviceName,
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

    <?php /* ----------------------------- related -------------------------------*/ ?>
    <?php if ($related !== []): ?>
        <section class="border-t border-brand-100 bg-gradient-to-b from-white to-brand-50/60 py-16">
            <div class="mx-auto max-w-7xl px-4 lg:px-6">
                <?= $view->component('section-heading', [
                    'eyebrow' => 'Bundle it',
                    'title' => 'Customers usually add these too',
                ]) ?>

                <div class="mt-10 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                    <?php foreach ($related as $other): ?>
                        <?= $view->component('service-tile', ['service' => $other]) ?>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
    <?php endif; ?>

    <?php /* ------------------------- whatsapp band ------------------------------*/ ?>
    <section class="relative overflow-hidden">
        <div class="animate-gradient absolute inset-0 bg-gradient-to-r from-brand-700 via-brand-500 to-accent-500"></div>
        <span class="blob -right-14 -top-16 opacity-40"></span>

        <div class="relative mx-auto flex max-w-7xl flex-col items-center gap-6 px-4 py-14 text-center lg:flex-row lg:justify-between lg:px-6 lg:text-left">
            <div>
                <h2 class="text-2xl font-black tracking-tight text-white md:text-3xl">Not sure which <?= e($serviceName) ?> plan fits?</h2>
                <p class="mt-2 max-w-xl text-sm leading-relaxed text-white/85">
                    Tell us how many people will use it and what you watch or build with it -
                    <?= e(setting('whatsapp.agent_name', 'our order desk')) ?> will name the cheapest tier that works,
                    usually in a couple of minutes between 9 AM and 11 PM.
                </p>
            </div>
            <a href="<?= e(wa_link('Hi ' . $brandName . ', which ' . $serviceName . ' plan should I take?')) ?>"
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
