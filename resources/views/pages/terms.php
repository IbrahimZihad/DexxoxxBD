<?php

declare(strict_types=1);

/**
 * Terms of service. No controller data is needed: every number and address on
 * this page is read from settings so the policy can never drift away from how
 * the shop is actually configured.
 *
 * @var App\Core\View $view
 */
$brandName = setting('brand.name', 'ZapSub');
$agentName = setting('whatsapp.agent_name', 'the order desk');
$hours = setting('whatsapp.hours', '9:00 AM - 11:00 PM, every day (Dhaka time)');
$email = setting('contact.email', 'our support address');
$phone = setting('contact.phone');
$address = setting('contact.address', 'Dhaka, Bangladesh');
$guarantee = setting('trust.guarantee', '7-day replacement on every personal plan');
$deliveryMinutes = max(1, (int) setting('trust.delivery_minutes', '10'));
$holdMinutes = max(5, (int) setting('order.pending_hold_minutes', '90'));
$replacementDays = 7;

$view->extend('layouts/app');
$view->setSection('title', 'Terms of service - ' . $brandName);
$view->setSection('meta_description', 'The rules for buying a subscription seat from ' . $brandName . ': what you get, how payment is verified, delivery times, seat limits, replacements and who is liable for what.');
$view->setSection('canonical', route('terms'));

$sections = [
    ['id' => 'what-you-buy', 'title' => '1. What you are actually buying'],
    ['id' => 'orders', 'title' => '2. Placing an order'],
    ['id' => 'payment', 'title' => '3. Payment and verification'],
    ['id' => 'delivery', 'title' => '4. Delivery'],
    ['id' => 'use', 'title' => '5. How the seat may be used'],
    ['id' => 'replacements', 'title' => '6. Replacements and refunds'],
    ['id' => 'your-account', 'title' => '7. Your ' . $brandName . ' account'],
    ['id' => 'changes', 'title' => '8. Price, plan and availability changes'],
    ['id' => 'liability', 'title' => '9. Liability, and what we do not cover'],
    ['id' => 'suspension', 'title' => '10. Cancelling or refusing an order'],
    ['id' => 'law', 'title' => '11. Governing law and contact'],
];
?>
<div class="bg-white">
    <section class="relative overflow-hidden bg-gradient-to-b from-brand-50 via-white to-white">
        <span class="blob -left-24 -top-20 animate-blob opacity-35"></span>
        <span class="blob -right-20 top-10 animate-blob opacity-30 [animation-delay:-5s]"></span>

        <div class="relative mx-auto max-w-4xl px-4 py-12 lg:px-6 lg:py-16">
            <div class="reveal" data-reveal>
                <p class="inline-flex items-center gap-2 rounded-pill border border-brand-200 bg-white/80 px-3.5 py-1.5 text-xs font-black uppercase tracking-[0.18em] text-brand-700 shadow-soft">
                    <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path d="M6 4h9l3 3v13H6V4Z" stroke-linejoin="round"/><path d="M9 10h6M9 14h6" stroke-linecap="round"/>
                    </svg>
                    Shop rules
                </p>
                <h1 class="mt-4 text-4xl font-black tracking-tight md:text-5xl">
                    <span class="animate-gradient text-gradient">Terms of service</span>
                </h1>
                <p class="mt-4 max-w-2xl text-base leading-relaxed text-ink-400">
                    These are the rules for buying a subscription seat from <?= $brandName ?>, a Bangladeshi
                    reseller of publicly available subscription services. We have written them the way we would
                    want them explained on WhatsApp: short sentences, real numbers, and nothing hidden. Ordering
                    from this site means you accept them.
                </p>
                <p class="mt-3 text-sm text-ink-400">
                    Key numbers, straight from our own settings: delivery target
                    <strong class="font-black text-ink"><?= e((string) $deliveryMinutes) ?> minutes</strong> inside
                    <strong class="font-black text-ink"><?= e($hours) ?></strong>, unpaid orders held
                    <strong class="font-black text-ink"><?= e((string) $holdMinutes) ?> minutes</strong>,
                    <strong class="font-black text-ink"><?= e($guarantee) ?></strong>.
                </p>
            </div>
        </div>
    </section>

    <section class="mx-auto max-w-4xl px-4 pb-20 lg:px-6">
        <?php /* ------------------------------ contents ------------------------------*/ ?>
        <nav aria-label="Contents" class="reveal rounded-card border border-brand-100 bg-brand-50/50 p-6" data-reveal>
            <h2 class="text-xs font-black uppercase tracking-[0.18em] text-brand-700">What is in here</h2>
            <ol class="mt-3 grid gap-2 sm:grid-cols-2">
                <?php foreach ($sections as $section): ?>
                    <li>
                        <a href="#<?= e($section['id']) ?>" class="group flex items-center gap-2 text-sm font-semibold text-ink transition-colors duration-200 hover:text-brand-700">
                            <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-accent-400 transition-transform duration-200 group-hover:scale-150" aria-hidden="true"></span>
                            <?= e($section['title']) ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ol>
        </nav>

        <?php /* ------------------------------- body -------------------------------*/ ?>
        <div class="mt-10 space-y-8">
            <article id="what-you-buy" class="reveal rounded-card border border-brand-100 bg-white p-6 shadow-card sm:p-8" data-reveal>
                <h2 class="text-xl font-black tracking-tight text-ink md:text-2xl"><?= e($sections[0]['title']) ?></h2>
                <div class="mt-4 space-y-3 text-sm leading-relaxed text-ink-400">
                    <p>
                        You are buying a <strong class="font-bold text-ink">seat on a subscription plan that we
                        own and pay for</strong>, plus our support in keeping that seat working for the term you
                        bought. You are not buying the brand’s own subscription in your name, and you are not
                        buying anything from the brand directly.
                    </p>
                    <p>
                        Terms are measured in months from the moment we deliver: 1, 3, 6 or 12 months, as shown on
                        the plan you ordered. The plan page states the seat count and whether it is personal,
                        individual or family. Prices are in Bangladeshi taka and include everything except the
                        money you send to your own bKash, Nagad, Rocket or bank.
                    </p>
                    <p>
                        <?= $brandName ?> is independent. We are not affiliated with, sponsored by or endorsed by
                        Netflix, Spotify, YouTube, OpenAI, Anthropic, Canva, Adobe, Microsoft, Google, Disney+ or
                        Amazon Prime Video - the brands on our plan pages - and those names belong to their owners,
                        appearing here only to describe which plans we hold seats on.
                    </p>
                </div>
            </article>

            <article id="orders" class="reveal rounded-card border border-brand-100 bg-white p-6 shadow-card sm:p-8" data-reveal>
                <h2 class="text-xl font-black tracking-tight text-ink md:text-2xl"><?= e($sections[1]['title']) ?></h2>
                <div class="mt-4 space-y-3 text-sm leading-relaxed text-ink-400">
                    <p>
                        An order is created on this site and is confirmed only when we verify your payment. Each
                        order gets a number (the code in your confirmation and in your account). Quote it in every
                        message - it is how we find your thread in seconds.
                    </p>
                    <p>
                        You agree to give a correct Bangladeshi phone number and email. The WhatsApp number you
                        enter <strong class="font-bold text-ink">is the delivery address</strong>: if it is wrong,
                        the login goes to someone else, and we cannot recover it for you. Check it before you pay.
                    </p>
                    <p>
                        You must be 18 or older, or ordering with a guardian who accepts these terms. Guest
                        checkout is allowed where the site shows it; an account simply keeps your order history and
                        delivery states in one place.
                    </p>
                </div>
            </article>

            <article id="payment" class="reveal rounded-card border border-brand-100 bg-white p-6 shadow-card sm:p-8" data-reveal>
                <h2 class="text-xl font-black tracking-tight text-ink md:text-2xl"><?= e($sections[2]['title']) ?></h2>
                <div class="mt-4 space-y-3 text-sm leading-relaxed text-ink-400">
                    <p>
                        Payment is manual and we consider that a safety feature. You send the exact total to the
                        number shown on the checkout page for your chosen method, then paste the transaction ID.
                        A person on the desk compares it against our own statement, and only a match marks the
                        order paid. We do not mark anything paid on trust, and we do not auto-capture your wallet.
                    </p>
                    <ul class="mt-2 space-y-2">
                        <li class="flex gap-2"><span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-brand-400"></span><span>Short or partial payments stay unpaid. Top up the difference and resend the new ID, or ask us to reopen the order.</span></li>
                        <li class="flex gap-2"><span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-brand-400"></span><span>An unpaid order holds its price and its stock for <?= e((string) $holdMinutes) ?> minutes. After that it can be released and repriced.</span></li>
                        <li class="flex gap-2"><span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-brand-400"></span><span>Only the numbers printed on this site’s payment page are ours. If a number arrives by Telegram, Facebook comment or SMS, it is not us - do not send money to it.</span></li>
                        <li class="flex gap-2"><span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-brand-400"></span><span>Never send us your bKash or Nagad PIN, your wallet password, or any card number. We have no use for them and asking for them is a scam signal.</span></li>
                    </ul>
                    <p>
                        Duplicate payments are returned in full to the number they came from as soon as you point
                        them out; keep both transaction IDs.
                    </p>
                </div>
            </article>

            <article id="delivery" class="reveal rounded-card border border-brand-100 bg-white p-6 shadow-card sm:p-8" data-reveal>
                <h2 class="text-xl font-black tracking-tight text-ink md:text-2xl"><?= e($sections[3]['title']) ?></h2>
                <div class="mt-4 space-y-3 text-sm leading-relaxed text-ink-400">
                    <p>
                        Delivery is a WhatsApp message containing the login details for your seat and, where the
                        brand needs it, the email on the account. Our target inside opening hours
                        (<?= e($hours) ?>) is about <?= e((string) $deliveryMinutes) ?> minutes after payment is
                        verified. It is a target, not a guarantee of the minute - a plan that needs the brand’s own
                        seat invite can take longer, and we tell you when it will.
                    </p>
                    <p>
                        An order is <strong class="font-bold text-ink">delivered</strong> when the details reach
                        the WhatsApp number you gave us. Your first job is to change any password we hand you on
                        your own device and set your own recovery email or phone where the brand allows it. From
                        that moment the account is yours to guard; a login you shared with a stranger is not
                        something we can replace for free.
                    </p>
                    <p>
                        Outside opening hours we deliver when the desk opens next, and we say so on the order page
                        rather than leaving you watching a spinner at 2 AM.
                    </p>
                </div>
            </article>

            <article id="use" class="reveal rounded-card border border-brand-100 bg-white p-6 shadow-card sm:p-8" data-reveal>
                <h2 class="text-xl font-black tracking-tight text-ink md:text-2xl"><?= e($sections[4]['title']) ?></h2>
                <div class="mt-4 space-y-3 text-sm leading-relaxed text-ink-400">
                    <p>
                        Use the seat yourself or with the people in your own household, within the seat count shown
                        on the plan. Do not resell it, do not list it for rent, do not hand it to strangers, and do
                        not use it for a business, hotel, café or public screening - the brand’s own licence does
                        not allow that, and neither do we.
                    </p>
                    <p>
                        Keep the account within the brand’s rules: no downloading for redistribution, no
                        circumventing region locks outside what the plan permits, no boosting streams, no scraping,
                        no using an AI seat for bulk automation the brand prohibits. If the brand flags the plan
                        because of your usage, the fix is yours to make and a replacement may not be free.
                    </p>
                    <p>
                        Content you watch, listen to, generate or store on a seat belongs to its owner. We never
                        look inside your Netflix history, your Spotify playlists or your ChatGPT chats, and our
                        staff have no reason to.
                    </p>
                </div>
            </article>

            <article id="replacements" class="reveal rounded-card border border-brand-100 bg-white p-6 shadow-card sm:p-8" data-reveal>
                <h2 class="text-xl font-black tracking-tight text-ink md:text-2xl"><?= e($sections[5]['title']) ?></h2>
                <div class="mt-4 space-y-3 text-sm leading-relaxed text-ink-400">
                    <p>
                        <strong class="font-bold text-ink"><?= e($guarantee) ?></strong> - if a seat stops working
                        within the first <?= e((string) $replacementDays) ?> days after delivery through no fault of
                        yours, we replace it or credit the rest of the term. After that window, we still fix
                        anything that broke because of our side of the deal; you simply lose fewer days arguing
                        about it if you message us early.
                    </p>
                    <p>
                        We do not refund a delivered plan because you changed your mind, found the app on a friend’s
                        device, or finished watching the series. Where a paid term is genuinely unusable through our
                        fault and cannot be replaced, the unused portion is refunded pro rata. The full detail,
                        including what counts as “our fault”, is on the
                        <a href="<?= e(route('refund')) ?>" class="font-bold text-brand-700 underline decoration-accent-400 decoration-2 underline-offset-4">refund policy</a>.
                    </p>
                </div>
            </article>

            <article id="your-account" class="reveal rounded-card border border-brand-100 bg-white p-6 shadow-card sm:p-8" data-reveal>
                <h2 class="text-xl font-black tracking-tight text-ink md:text-2xl"><?= e($sections[6]['title']) ?></h2>
                <div class="mt-4 space-y-3 text-sm leading-relaxed text-ink-400">
                    <p>
                        Your <?= $brandName ?> password is hashed with Argon2id and never stored in plain text. If you
                        lose it, a single-use reset link goes to your email. Marketing messages happen only if you
                        ticked the opt-in box; a “stop emailing me” message is honoured the same day, with no
                        goodbye sequence.
                    </p>
                    <p>
                        You can ask us to delete your account at any time by emailing <?= e($email) ?> from the
                        address on it. We remove what we can and keep what we must: order and payment records are
                        retained for accounting and dispute purposes, because those are the documents that prove you
                        bought something and we delivered it. Delivery notes are encrypted at rest; passwords for the
                        seats we hand you are never stored in a readable form after delivery.
                    </p>
                    <p>
                        Do not share your <?= $brandName ?> login either. Anyone holding it can read your order
                        history, and a shared session is how accounts get abused.
                    </p>
                </div>
            </article>

            <article id="changes" class="reveal rounded-card border border-brand-100 bg-white p-6 shadow-card sm:p-8" data-reveal>
                <h2 class="text-xl font-black tracking-tight text-ink md:text-2xl"><?= e($sections[7]['title']) ?></h2>
                <div class="mt-4 space-y-3 text-sm leading-relaxed text-ink-400">
                    <p>
                        Brands change prices, seat rules, regions and features, and the taka rate moves. Our prices
                        may change at any time for <strong class="font-bold text-ink">new</strong> orders. A term you
                        already paid for is honoured for its full length at the price you paid, even if our cost went
                        up - that risk sits with us, not with you.
                    </p>
                    <p>
                        If a brand makes a plan we sell impossible to provide, we tell you in the same WhatsApp
                        thread, offer the closest equivalent or refund the unused days. We do not quietly let orders
                        rot.
                    </p>
                    <p>
                        Stock is real, not unlimited: each tier is tied to seats we actually hold. Out-of-stock tiers
                        are shown as out of stock rather than sold on hope.
                    </p>
                </div>
            </article>

            <article id="liability" class="reveal rounded-card border border-brand-100 bg-white p-6 shadow-card sm:p-8" data-reveal>
                <h2 class="text-xl font-black tracking-tight text-ink md:text-2xl"><?= e($sections[8]['title']) ?></h2>
                <div class="mt-4 space-y-3 text-sm leading-relaxed text-ink-400">
                    <p>
                        Our responsibility for an order is limited to the amount you paid for it: replacement, credit
                        or a pro-rata refund as described above. We are not liable for lost business, lost data in
                        someone else’s cloud, a missed deadline, or a show you could not binge because the brand had
                        an outage on our side of the world.
                    </p>
                    <p>
                        The streaming, music, AI or storage service itself is provided by its brand under its own
                        terms; we are a reseller of access, not the provider. Account history you build on a seat may
                        not survive a replacement, so keep your own downloads and playlists where you can.
                    </p>
                    <p>
                        Nothing here excludes liability that Bangladeshi law will not let anyone exclude, such as for
                        fraud or deliberate harm.
                    </p>
                </div>
            </article>

            <article id="suspension" class="reveal rounded-card border border-brand-100 bg-white p-6 shadow-card sm:p-8" data-reveal>
                <h2 class="text-xl font-black tracking-tight text-ink md:text-2xl"><?= e($sections[9]['title']) ?></h2>
                <div class="mt-4 space-y-3 text-sm leading-relaxed text-ink-400">
                    <p>
                        We can refuse or close an order - and stop serving a customer - for fabricated transaction
                        IDs, stolen or third-party-funded wallets, reselling seats against these terms, abuse of our
                        staff on WhatsApp, repeated claims that contradict the evidence, or use that puts the whole
                        plan at risk with the brand. Where money was already paid and no delivery happened, it is
                        returned; where it was delivered and consumed, the unused term is assessed case by case.
                    </p>
                    <p>
                        You can cancel any unpaid order yourself from your account, or just stop paying - it expires
                        on its own after <?= e((string) $holdMinutes) ?> minutes.
                    </p>
                </div>
            </article>

            <article id="law" class="reveal rounded-card border border-brand-100 bg-white p-6 shadow-card sm:p-8" data-reveal>
                <h2 class="text-xl font-black tracking-tight text-ink md:text-2xl"><?= e($sections[10]['title']) ?></h2>
                <div class="mt-4 space-y-3 text-sm leading-relaxed text-ink-400">
                    <p>
                        These terms are governed by the law of Bangladesh, with the courts of Dhaka having
                        jurisdiction. Before anyone reaches for a lawyer: message us first. Almost every dispute we
                        have ever had ended with a working account or money back in the same afternoon.
                    </p>
                    <p>
                        Reach the desk on WhatsApp during <?= e($hours) ?>, by email at <?= e($email) ?>,
                        <?php if ($phone !== ''): ?>by phone on <?= e($phone) ?>, <?php endif; ?>or through the
                        <a href="<?= e(route('contact')) ?>" class="font-bold text-brand-700 underline decoration-accent-400 decoration-2 underline-offset-4">contact form</a>.
                        We work from <?= e($address) ?>. We are a small shop, not a company with a legal department,
                        so we describe ourselves plainly rather than quoting a registration number at you.
                    </p>
                    <p class="text-xs text-ink-400">
                        If these terms and any message on WhatsApp disagree, this page wins - which is why we keep it
                        current rather than copying an old PDF from the internet.
                    </p>
                </div>
            </article>
        </div>

        <?php /* ------------------------------- footer links -------------------------------*/ ?>
        <div class="reveal mt-10 flex flex-wrap items-center gap-3 rounded-card bg-ink p-6 text-white" data-reveal>
            <p class="grow text-sm leading-relaxed text-white/75">
                Read the rest before you order: the short refund rules and how we handle your data.
            </p>
            <a href="<?= e(route('refund')) ?>" class="rounded-brand bg-white/10 px-4 py-2.5 text-sm font-bold text-white transition-colors duration-200 hover:bg-white/20">Refund policy</a>
            <a href="<?= e(route('privacy')) ?>" class="rounded-brand bg-white/10 px-4 py-2.5 text-sm font-bold text-white transition-colors duration-200 hover:bg-white/20">Privacy</a>
            <a href="<?= e(route('plans')) ?>" class="rounded-brand bg-gradient-to-r from-brand-600 to-accent-500 px-4 py-2.5 text-sm font-black text-white shadow-glow transition-transform duration-200 hover:-translate-y-0.5">Browse plans</a>
        </div>
    </section>
</div>
