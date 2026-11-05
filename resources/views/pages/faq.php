<?php

declare(strict_types=1);

/**
 * Frequently asked questions. Data comes from PageController::faq():
 * $groups (category => list of question/answer), $total, $search,
 * $whatsappUrl, $deliveryMinutes, $jsonLd.
 *
 * Categories are [data-tabs] panels; the questions inside each category are one
 * [data-accordion] group, so a category shows a single open answer at a time -
 * the same behaviour the home page teasers use.
 *
 * @var App\Core\View $view
 * @var array<string, list<array{question: string, answer: string}>> $groups
 * @var int $total
 * @var string $search
 * @var string $whatsappUrl
 * @var int $deliveryMinutes
 * @var string $jsonLd
 */
$brandName = setting('brand.name', 'ZapSub');
$agentName = setting('whatsapp.agent_name', 'the order desk');

$view->extend('layouts/app');
$view->setSection('title', 'FAQ - ' . $brandName);
$view->setSection('meta_description', 'How ' . $brandName . ' delivery, payment, replacements and account access actually work - answered in plain Bangladeshi English.');
$view->setSection('canonical', route('faq'));
$view->setSection('json_ld', $jsonLd);

$categoryKeys = array_keys($groups);
$matched = 0;
foreach ($groups as $categoryRows) {
    $matched += count($categoryRows);
}

// The first four published answers, previewed in full so the most common
// question is answered before anyone has to click a tab.
$hot = [];
foreach ($groups as $category => $rows) {
    foreach ($rows as $row) {
        if (count($hot) < 4) {
            $hot[] = ['category' => $category] + $row;
        }
    }
}
?>
<div class="bg-white">
    <section class="relative overflow-hidden bg-gradient-to-b from-brand-50 via-white to-white">
        <span class="blob -left-24 -top-20 animate-blob opacity-40"></span>
        <span class="blob -right-20 top-8 animate-blob opacity-35 [animation-delay:-5s]"></span>
        <span class="blob bottom-[-8rem] left-1/3 animate-blob opacity-30 [animation-delay:-9s]"></span>

        <div class="relative mx-auto max-w-4xl px-4 py-12 text-center lg:px-6 lg:py-16">
            <div class="reveal" data-reveal>
                <p class="inline-flex items-center gap-2 rounded-pill border border-brand-200 bg-white/80 px-3.5 py-1.5 text-xs font-black uppercase tracking-[0.18em] text-brand-700 shadow-soft">
                    <span class="h-2 w-2 animate-pulse-ring rounded-full bg-accent-400"></span>
                    <?= e((string) $total) ?> published answers
                </p>
                <h1 class="mt-4 text-4xl font-black leading-[1.05] tracking-tight md:text-5xl">
                    <span class="animate-gradient text-gradient">Everything people ask before their first order</span>
                </h1>
                <p class="mx-auto mt-4 max-w-2xl text-base leading-relaxed text-ink-400">
                    Written by the same people who verify the payments and send the logins, so the answers
                    describe what really happens at <?= e($brandName) ?> - average handover
                    <?= e((string) $deliveryMinutes) ?> minutes, every plan priced in Bangladeshi taka.
                </p>
            </div>

            {{-- ------------------------------ search ------------------------------ --}}
            <form method="get" action="<?= e(route('faq')) ?>" class="reveal mt-8" data-reveal style="--reveal-delay:100ms">
                <div class="mx-auto flex max-w-xl items-center gap-2 rounded-pill border border-brand-200 bg-white p-1.5 shadow-soft focus-within:border-brand-400 focus-within:ring-2 focus-within:ring-brand-200">
                    <span class="pl-3 text-brand-400" aria-hidden="true">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="11" cy="11" r="7"/><path d="m20 20-3.6-3.6" stroke-linecap="round"/>
                        </svg>
                    </span>
                    <label for="faq-search" class="sr-only">Search the questions</label>
                    <input
                        id="faq-search"
                        type="search"
                        name="q"
                        value="<?= e($search) ?>"
                        maxlength="80"
                        placeholder="Search: replacement, bKash, family plan, password…"
                        class="min-w-0 grow bg-transparent px-1 py-2 text-sm text-ink outline-none placeholder:text-ink-400"
                    >
                    <button type="submit" class="shrink-0 rounded-pill bg-gradient-to-r from-brand-600 to-accent-500 px-5 py-2.5 text-sm font-black text-white shadow-glow transition-transform duration-200 hover:-translate-y-0.5">
                        Search
                    </button>
                </div>
                <?php if ($search !== ''): ?>
                    <p class="mt-3 text-sm text-ink-400">
                        <strong class="font-black text-ink"><?= e((string) $matched) ?></strong> answer<?= e($matched === 1 ? '' : 's') ?>
                        match “<?= e($search) ?>”.
                        <a href="<?= e(route('faq')) ?>" class="font-bold text-brand-700 underline decoration-accent-400 decoration-2 underline-offset-4">Clear the search</a>
                    </p>
                <?php endif; ?>
            </form>
        </div>
    </section>

    <section class="mx-auto max-w-4xl px-4 py-14 lg:px-6">
        <?php if ($groups === []): ?>
            <?= $view->component('empty-state', [
                'title' => 'Nothing matched that search',
                'text' => 'Try a shorter word - “replace”, “bkash”, “family”, “password” - or ask us directly on WhatsApp. A person reads every message during opening hours.',
                'action_url' => route('faq'),
                'action_label' => 'Show all questions',
                'icon' => 'search',
            ]) ?>
            <p class="mt-6 text-center text-sm text-ink-400">
                Or jump straight to
                <a href="<?= e(route('contact')) ?>" class="font-bold text-brand-700 underline decoration-accent-400 decoration-2 underline-offset-4">the contact page</a>.
            </p>
        <?php else: ?>
            <?php if (count($hot) === 4 && $matched > 4): ?>
                <div class="reveal grid gap-3 sm:grid-cols-2" data-reveal>
                    <?php foreach ($hot as $index => $item): ?>
                        <article class="flex items-start gap-3 rounded-brand border border-brand-100 bg-brand-50/50 p-4">
                            <span class="grid h-7 w-7 shrink-0 place-items-center rounded-pill bg-gradient-to-br from-brand-600 to-accent-500 text-[11px] font-black text-white shadow-pop"><?= e((string) ($index + 1)) ?></span>
                            <span class="min-w-0">
                                <span class="block text-sm font-bold text-ink"><?= e($item['question']) ?></span>
                                <span class="mt-1 block text-xs leading-relaxed text-ink-400"><?= e(short_text($item['answer'], 130)) ?></span>
                                <span class="mt-1.5 block text-[11px] font-semibold uppercase tracking-wider text-brand-600"><?= e($item['category']) ?></span>
                            </span>
                        </article>
                    <?php endforeach; ?>
                </div>
                <p class="mt-3 text-xs text-ink-400">The first four answers in full below - every other question on this page is one tap away.</p>
            <?php endif; ?>

            <div data-tabs class="mt-10">
                <div role="tablist" aria-label="Question categories" class="no-scrollbar flex gap-2 overflow-x-auto pb-1">
                    <?php foreach ($categoryKeys as $index => $category): ?>
                        <button type="button"
                                role="tab"
                                data-tab-btn="<?= e('cat-' . $index) ?>"
                                aria-selected="<?= e($index === 0 ? 'true' : 'false') ?>"
                                class="flex shrink-0 items-center gap-2 rounded-pill border border-brand-200 bg-white px-4 py-2.5 text-left transition-colors duration-200 aria-selected:border-brand-500 aria-selected:bg-gradient-to-r aria-selected:from-brand-600 aria-selected:to-brand-500 aria-selected:text-white aria-selected:shadow-pop <?= e($index === 0 ? 'is-active' : '') ?>">
                            <span class="text-sm font-black"><?= e((string) $category) ?></span>
                            <span class="rounded-pill bg-brand-100 px-2 py-0.5 text-[11px] font-black text-brand-700 aria-selected:bg-white/25 aria-selected:text-white">
                                <?= e((string) count($groups[$category])) ?>
                            </span>
                        </button>
                    <?php endforeach; ?>
                </div>

                <?php foreach ($categoryKeys as $index => $category): ?>
                    <section data-tab-panel="<?= e('cat-' . $index) ?>" role="tabpanel" aria-label="<?= e($category) ?>" <?= e($index === 0 ? '' : 'hidden') ?> class="mt-6">
                        <div class="reveal" data-reveal style="--reveal-delay:<?= e((string) ($index * 60)) ?>ms">
                            <h2 class="text-xl font-black tracking-tight text-ink"><?= e($category) ?></h2>
                            <p class="mt-1 text-sm text-ink-400"><?= e((string) count($groups[$category])) ?> question<?= e(count($groups[$category]) === 1 ? '' : 's') ?> in this group.</p>
                        </div>

                        <div data-accordion class="mt-4 divide-y divide-brand-100 overflow-hidden rounded-card border border-brand-100 bg-white shadow-soft">
                            <?php foreach ($groups[$category] as $qIndex => $faqRow): ?>
                                <div>
                                    <button type="button" data-accordion-btn aria-expanded="<?= e($qIndex === 0 && $index === 0 ? 'true' : 'false') ?>" class="flex w-full items-center justify-between gap-4 px-5 py-4 text-left text-sm font-bold text-ink transition-colors duration-200 hover:bg-brand-50">
                                        <span><?= e($faqRow['question']) ?></span>
                                        <svg class="h-5 w-5 shrink-0 text-brand-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                            <path d="m6 9 6 6 6-6" stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
                                    </button>
                                    <div data-accordion-panel class="px-5 pb-4 text-sm leading-relaxed text-ink-400" <?= e($qIndex === 0 && $index === 0 ? '' : 'hidden') ?>>
                                        <?= e($faqRow['answer']) ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    {{-- --------------------------- still stuck band --------------------------- --}}
    <section class="relative overflow-hidden bg-ink">
        <span class="blob -left-16 top-[-4rem] animate-blob bg-brand-600/40"></span>
        <span class="blob -right-12 bottom-[-5rem] animate-blob bg-accent-500/30 [animation-delay:-7s]"></span>

        <div class="relative mx-auto grid max-w-5xl gap-6 px-4 py-12 lg:grid-cols-[1.2fr_0.8fr] lg:items-center lg:px-6">
            <div class="reveal" data-reveal>
                <h2 class="text-2xl font-black tracking-tight text-white md:text-3xl">Still stuck on something specific?</h2>
                <p class="mt-3 text-sm leading-relaxed text-white/70">
                    An account that stopped working, a payment we could not match, a plan you are not sure
                    suits you - send the order number and <?= e($agentName) ?> answers on WhatsApp.
                    Open <?= e(setting('whatsapp.hours', 'our opening hours')) ?>.
                </p>
                <ul class="mt-4 space-y-1.5 text-sm text-white/70">
                    <li class="flex items-center gap-2">
                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-400" aria-hidden="true"></span>
                        Average answer time inside opening hours: minutes, not days.
                    </li>
                    <li class="flex items-center gap-2">
                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-400" aria-hidden="true"></span>
                        A broken personal account is replaced, not argued about - <?= e(setting('trust.guarantee', '7-day replacement on every personal plan')) ?>.
                    </li>
                    <li class="flex items-center gap-2">
                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-400" aria-hidden="true"></span>
                        Never send a password or a bKash PIN; we do not need either.
                    </li>
                </ul>
            </div>

            <div class="reveal flex flex-col gap-3" data-reveal style="--reveal-delay:120ms">
                <a href="<?= e($whatsappUrl) ?>" target="_blank" rel="noopener" class="group relative flex items-center justify-center gap-2 overflow-hidden rounded-brand bg-gradient-to-r from-emerald-500 to-emerald-400 px-6 py-4 text-sm font-black text-white shadow-pop transition-transform duration-200 hover:-translate-y-0.5">
                    <span class="animate-shimmer absolute inset-0 bg-gradient-to-r from-white/0 via-white/35 to-white/0" aria-hidden="true"></span>
                    <svg class="relative h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path d="M4 5h16v10H8l-4 4V5Z" stroke-linejoin="round"/>
                    </svg>
                    <span class="relative">Ask on WhatsApp</span>
                </a>
                <a href="<?= e(route('contact')) ?>" class="rounded-brand border-2 border-white/20 px-6 py-3.5 text-center text-sm font-bold text-white transition-colors duration-200 hover:border-white/50 hover:bg-white/10">
                    Use the contact form
                </a>
                <div class="flex flex-wrap gap-3 text-xs font-semibold text-white/60">
                    <a href="<?= e(route('track')) ?>" class="underline decoration-white/30 underline-offset-4 hover:text-white">Track an order</a>
                    <a href="<?= e(route('terms')) ?>" class="underline decoration-white/30 underline-offset-4 hover:text-white">Terms</a>
                    <a href="<?= e(route('refund')) ?>" class="underline decoration-white/30 underline-offset-4 hover:text-white">Refunds</a>
                </div>
            </div>
        </div>
    </section>
</div>
