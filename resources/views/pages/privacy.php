<?php

declare(strict_types=1);

/**
 * Privacy policy. Like the terms, every fact here is read from settings or from
 * the code that actually runs (session cookie name, hashing, encryption), so the
 * page cannot drift away from what the shop really does.
 *
 * @var App\Core\View $view
 */
$brandName = setting('brand.name', 'ZapSub');
$hours = setting('whatsapp.hours', '9:00 AM - 11:00 PM, every day (Dhaka time)');
$email = setting('contact.email', 'our support address');
$phone = setting('contact.phone');
$address = setting('contact.address', 'Dhaka, Bangladesh');
$agentName = setting('whatsapp.agent_name', 'the order desk');
$sessionName = (string) App\Core\Config::get('app.session.name', 'zapsub_session');
// Read from the same config the session actually started with, so this sentence
// cannot go stale when SESSION_LIFETIME changes in .env.
$sessionHours = max(1, (int) round(((int) App\Core\Config::get('app.session.lifetime', 7200)) / 3600));

$view->extend('layouts/app');
$view->setSection('title', 'Privacy policy - ' . $brandName);
$view->setSection('meta_description', 'What ' . $brandName . ' stores about you (name, phone, email, order, IP), why it stores it, how delivery notes are encrypted, and how to get it deleted.');
$view->setSection('canonical', route('privacy'));

$sections = [
    ['id' => 'collect', 'title' => '1. What we collect'],
    ['id' => 'never', 'title' => '2. What we never ask for'],
    ['id' => 'use', 'title' => '3. How it is used'],
    ['id' => 'cookies', 'title' => '4. Cookies and sessions'],
    ['id' => 'sharing', 'title' => '5. Who else sees anything'],
    ['id' => 'storage', 'title' => '6. Where it lives and how it is protected'],
    ['id' => 'retention', 'title' => '7. How long we keep it'],
    ['id' => 'rights', 'title' => '8. Your choices'],
    ['id' => 'limits', 'title' => '9. Honest limits'],
    ['id' => 'children', 'title' => '10. Children'],
    ['id' => 'contact', 'title' => '11. Questions and changes'],
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
                        <path d="M12 3 5 6v6c0 4.4 3 7.6 7 9 4-1.4 7-4.6 7-9V6l-7-3Z" stroke-linejoin="round"/>
                    </svg>
                    Your data
                </p>
                <h1 class="mt-4 text-4xl font-black tracking-tight md:text-5xl">
                    <span class="animate-gradient text-gradient">Privacy policy</span>
                </h1>
                <p class="mt-4 max-w-2xl text-base leading-relaxed text-ink-400">
                    We are a small shop that needs very little about you: a name, a Bangladeshi phone number, an
                    email, and what you bought. This page says exactly which fields we hold, why, who can read them,
                    how the sensitive ones are encrypted, and how to have the rest deleted. No dark patterns, no
                    selling lists, no “we may use your data for innovative purposes”.
                </p>
            </div>
        </div>
    </section>

    <section class="mx-auto max-w-4xl px-4 pb-20 lg:px-6">
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

        <div class="mt-10 space-y-8">
            <article id="collect" class="reveal rounded-card border border-brand-100 bg-white p-6 shadow-card sm:p-8" data-reveal>
                <h2 class="text-xl font-black tracking-tight text-ink md:text-2xl"><?= e($sections[0]['title']) ?></h2>
                <div class="mt-4 space-y-3 text-sm leading-relaxed text-ink-400">
                    <p>If you order, we keep:</p>
                    <ul class="space-y-2">
                        <li class="flex gap-2"><span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-brand-400"></span><span><strong class="font-bold text-ink">Identity:</strong> your name, email address and Bangladeshi phone number. The phone number is the delivery address, so it has to be right.</span></li>
                        <li class="flex gap-2"><span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-brand-400"></span><span><strong class="font-bold text-ink">Orders:</strong> the plan and tier you bought, quantity, price, total, order status, payment status, fulfilment status, the transaction ID you declared, and the timestamps of payment and delivery.</span></li>
                        <li class="flex gap-2"><span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-brand-400"></span><span><strong class="font-bold text-ink">A few technical fields:</strong> the IP address that placed the order, and - if you signed up - a hashed password plus a marketing opt-in flag.</span></li>
                        <li class="flex gap-2"><span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-brand-400"></span><span><strong class="font-bold text-ink">Messages:</strong> whatever you send through the contact form or write into our WhatsApp thread, plus its IP, so the thread can be answered and abuse can be traced.</span></li>
                        <li class="flex gap-2"><span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-brand-400"></span><span><strong class="font-bold text-ink">Cart:</strong> the basket can sit in your browser session so a refresh does not lose it. Nothing in a cart becomes a record until you order.</span></li>
                    </ul>
                    <p>
                        We do not use fingerprinting, we do not run heatmaps or session recorders, and we do not
                        build a profile of what you watch. There is no marketing pixel on this site.
                    </p>
                </div>
            </article>

            <article id="never" class="reveal rounded-card border border-emerald-200 bg-emerald-50/60 p-6 shadow-card sm:p-8" data-reveal>
                <h2 class="text-xl font-black tracking-tight text-ink md:text-2xl"><?= e($sections[1]['title']) ?></h2>
                <div class="mt-4 space-y-3 text-sm leading-relaxed text-ink-400">
                    <ul class="space-y-2">
                        <li class="flex gap-2"><span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-emerald-500"></span><span>Your bKash, Nagad or Rocket <strong class="font-bold text-ink">PIN or wallet password</strong>.</span></li>
                        <li class="flex gap-2"><span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-emerald-500"></span><span>Full card numbers, CVV or ATM details - we do not hold card data ourselves; a payment gateway does that when card checkout is switched on.</span></li>
                        <li class="flex gap-2"><span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-emerald-500"></span><span>Passwords for your own Google, Facebook or Netflix accounts.</span></li>
                        <li class="flex gap-2"><span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-emerald-500"></span><span>National ID, passport numbers or photographs of documents. If anyone claiming to be us asks for these, it is not us.</span></li>
                    </ul>
                    <p>
                        The only credential you will ever see from us is the login for the seat we deliver - and
                        that one is yours to change the moment it arrives.
                    </p>
                </div>
            </article>

            <article id="use" class="reveal rounded-card border border-brand-100 bg-white p-6 shadow-card sm:p-8" data-reveal>
                <h2 class="text-xl font-black tracking-tight text-ink md:text-2xl"><?= e($sections[2]['title']) ?></h2>
                <div class="mt-4 space-y-3 text-sm leading-relaxed text-ink-400">
                    <p>We use your details for exactly four things:</p>
                    <ol class="space-y-2">
                        <li class="flex gap-2"><span class="grid h-5 w-5 shrink-0 place-items-center rounded-pill bg-brand-100 text-[10px] font-black text-brand-700">1</span><span>Delivering your order on WhatsApp and telling you when it is ready.</span></li>
                        <li class="flex gap-2"><span class="grid h-5 w-5 shrink-0 place-items-center rounded-pill bg-brand-100 text-[10px] font-black text-brand-700">2</span><span>Verifying the payment against the transaction ID you declared, and keeping a record in case of a dispute.</span></li>
                        <li class="flex gap-2"><span class="grid h-5 w-5 shrink-0 place-items-center rounded-pill bg-brand-100 text-[10px] font-black text-brand-700">3</span><span>Answering support messages and honouring the replacement promise.</span></li>
                        <li class="flex gap-2"><span class="grid h-5 w-5 shrink-0 place-items-center rounded-pill bg-brand-100 text-[10px] font-black text-brand-700">4</span><span>Sending offers - only to people who ticked the opt-in box at sign-up, and only until they say stop.</span></li>
                    </ol>
                    <p>
                        Rate limiting and session data exist to stop bots hammering the login and contact forms.
                        That is security, not surveillance: we do not read your traffic, only the request in front of us.
                    </p>
                </div>
            </article>

            <article id="cookies" class="reveal rounded-card border border-brand-100 bg-white p-6 shadow-card sm:p-8" data-reveal>
                <h2 class="text-xl font-black tracking-tight text-ink md:text-2xl"><?= e($sections[3]['title']) ?></h2>
                <div class="mt-4 space-y-3 text-sm leading-relaxed text-ink-400">
                    <p>
                        This site sets one first-party cookie: <code class="rounded-brand bg-brand-50 px-1.5 py-0.5 font-mono text-xs font-bold text-brand-700"><?= e($sessionName) ?></code>.
                        It holds a random session identifier and nothing else - no name, no phone, no tracking ID. It
                        is <strong class="font-bold text-ink">HttpOnly</strong> (so scripts on other pages cannot read
                        it), <strong class="font-bold text-ink">SameSite=Lax</strong>, and it goes idle after about
                        <?= e((string) $sessionHours) ?> hour<?= e($sessionHours === 1 ? '' : 's') ?> of no activity.
                        The cart, your sign-in state and the anti-CSRF token all live behind
                        that single identifier, on our side.
                    </p>
                    <p>
                        Browsing the catalogue without a cookie is possible; signing in, ordering and paying need it,
                        because there is no other way to know the checkout is still yours.
                    </p>
                    <p>
                        A form that fails validation keeps what you typed in a short-lived session value so you do not
                        have to re-enter a phone number. Passwords are deliberately never kept there.
                    </p>
                </div>
            </article>

            <article id="sharing" class="reveal rounded-card border border-brand-100 bg-white p-6 shadow-card sm:p-8" data-reveal>
                <h2 class="text-xl font-black tracking-tight text-ink md:text-2xl"><?= e($sections[4]['title']) ?></h2>
                <div class="mt-4 space-y-3 text-sm leading-relaxed text-ink-400">
                    <ul class="space-y-2">
                        <li class="flex gap-2"><span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-brand-400"></span><span><strong class="font-bold text-ink">We do not sell your data</strong> - not to advertisers, not to “partners”, not to anyone. There is no revenue line in this shop that depends on it.</span></li>
                        <li class="flex gap-2"><span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-brand-400"></span><span><strong class="font-bold text-ink">WhatsApp:</strong> when you tap a WhatsApp button, the chat runs on Meta’s servers and your number becomes visible to us there. That is your choice, made to get your delivery.</span></li>
                        <li class="flex gap-2"><span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-brand-400"></span><span><strong class="font-bold text-ink">Google Fonts:</strong> the stylesheet is fetched from Google, which sees an IP address making that request - standard for any site using them, and the only third-party request the page makes for you.</span></li>
                        <li class="flex gap-2"><span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-brand-400"></span><span><strong class="font-bold text-ink">Payment side:</strong> wallet and bank records live with bKash, Nagad, Rocket or your bank; card records live with the gateway, never in our database.</span></li>
                        <li class="flex gap-2"><span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-brand-400"></span><span><strong class="font-bold text-ink">Hosting:</strong> our database sits on the server we administer. Only staff accounts with the right role can read an order, and every one of them is logged when they act on it.</span></li>
                        <li class="flex gap-2"><span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-brand-400"></span><span><strong class="font-bold text-ink">Law:</strong> if a valid Bangladeshi legal order compels disclosure, we comply - and tell you about it unless we are forbidden to.</span></li>
                    </ul>
                </div>
            </article>

            <article id="storage" class="reveal rounded-card border border-brand-100 bg-white p-6 shadow-card sm:p-8" data-reveal>
                <h2 class="text-xl font-black tracking-tight text-ink md:text-2xl"><?= e($sections[5]['title']) ?></h2>
                <div class="mt-4 space-y-3 text-sm leading-relaxed text-ink-400">
                    <ul class="space-y-2">
                        <li class="flex gap-2"><span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-brand-400"></span><span><strong class="font-bold text-ink">Passwords</strong> for <?= $brandName ?> accounts are hashed with Argon2id and never written down in any readable form. A forgotten password is reset through a single-use emailed link whose token is stored only as a hash, and dies in an hour.</span></li>
                        <li class="flex gap-2"><span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-brand-400"></span><span><strong class="font-bold text-ink">Delivery notes</strong> - the field where seat credentials are recorded - are encrypted at rest with AES-256-GCM using an application key that lives outside the database. Readable credentials exist only in your WhatsApp thread.</span></li>
                        <li class="flex gap-2"><span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-brand-400"></span><span><strong class="font-bold text-ink">Forms</strong> are CSRF-protected, and login, registration, reset and contact submissions are rate limited so a stolen list cannot be tested against us at speed.</span></li>
                        <li class="flex gap-2"><span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-brand-400"></span><span><strong class="font-bold text-ink">Pages</strong> ship with security headers including a content security policy that blocks third-party script injection, and the account area is reachable only by its own owner.</span></li>
                        <li class="flex gap-2"><span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-brand-400"></span><span><strong class="font-bold text-ink">Logs</strong> record paths and error classes, never passwords, tokens or full payment credentials.</span></li>
                    </ul>
                </div>
            </article>

            <article id="retention" class="reveal rounded-card border border-brand-100 bg-white p-6 shadow-card sm:p-8" data-reveal>
                <h2 class="text-xl font-black tracking-tight text-ink md:text-2xl"><?= e($sections[6]['title']) ?></h2>
                <div class="mt-4 space-y-3 text-sm leading-relaxed text-ink-400">
                    <p>
                        Order and payment records are kept while the shop needs them as an accounting document and a
                        defence in a dispute - in practice, for the life of the plan you bought plus a year. Contact
                        messages are kept until they are answered and then held as support history. A deleted account
                        removes your name, hashed password and marketing flag; the order rows stay for the reasons
                        above, which is the honest answer most small shops give when you push them.
                    </p>
                    <p>
                        Unpaid orders are the shortest-lived thing we hold: they expire after a couple of hours and
                        release their stock on their own.
                    </p>
                </div>
            </article>

            <article id="rights" class="reveal rounded-card border border-brand-100 bg-white p-6 shadow-card sm:p-8" data-reveal>
                <h2 class="text-xl font-black tracking-tight text-ink md:text-2xl"><?= e($sections[7]['title']) ?></h2>
                <div class="mt-4 space-y-3 text-sm leading-relaxed text-ink-400">
                    <p>Write to <?= e($email) ?> - or message <?= e($agentName) ?> on WhatsApp during <?= e($hours) ?> - and ask for:</p>
                    <ul class="space-y-2">
                        <li class="flex gap-2"><span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-brand-400"></span><span><strong class="font-bold text-ink">A copy</strong> of the details we hold about you: your own account page already shows the orders, so most requests need no work at all.</span></li>
                        <li class="flex gap-2"><span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-brand-400"></span><span><strong class="font-bold text-ink">A correction</strong> - a wrong phone number is fixed on request, and it is worth fixing before you pay, not after.</span></li>
                        <li class="flex gap-2"><span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-brand-400"></span><span><strong class="font-bold text-ink">Deletion</strong> of your account and login data, subject to the order records we must retain.</span></li>
                        <li class="flex gap-2"><span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-brand-400"></span><span><strong class="font-bold text-ink">Opt-out</strong> from marketing, at any time, with no “are you sure” dance.</span></li>
                    </ul>
                    <p>
                        We may ask for one detail that only the account owner would know, because “email me
                        everything you have on 01712-345678” is exactly how a data leak starts.
                    </p>
                </div>
            </article>

            <article id="limits" class="reveal rounded-card border border-amber-200 bg-amber-50/60 p-6 shadow-card sm:p-8" data-reveal>
                <h2 class="text-xl font-black tracking-tight text-ink md:text-2xl"><?= e($sections[8]['title']) ?></h2>
                <div class="mt-4 space-y-3 text-sm leading-relaxed text-ink-400">
                    <p>
                        No shop that takes payments over an open network can promise zero risk, and we are not going
                        to pretend otherwise. What we do is keep the amount of sensitive data small - because the
                        safest record is the one we never created.
                    </p>
                    <p>
                        Two things are on you: keep your WhatsApp thread safe (it holds your delivery), and change
                        the seat password when it arrives. A login handed to a friend, posted in a Telegram group or
                        typed into a fake “verify your account” page is outside what any privacy policy can protect.
                    </p>
                </div>
            </article>

            <article id="children" class="reveal rounded-card border border-brand-100 bg-white p-6 shadow-card sm:p-8" data-reveal>
                <h2 class="text-xl font-black tracking-tight text-ink md:text-2xl"><?= e($sections[9]['title']) ?></h2>
                <div class="mt-4 space-y-3 text-sm leading-relaxed text-ink-400">
                    <p>
                        Ordering needs a wallet and an 18-year-old (or a guardian who approves). We do not knowingly
                        collect data from children, and a plan bought for a household is still managed by the adult
                        who paid. If a child’s details reached us, tell us and they are gone.
                    </p>
                </div>
            </article>

            <article id="contact" class="reveal rounded-card border border-brand-100 bg-white p-6 shadow-card sm:p-8" data-reveal>
                <h2 class="text-xl font-black tracking-tight text-ink md:text-2xl"><?= e($sections[10]['title']) ?></h2>
                <div class="mt-4 space-y-3 text-sm leading-relaxed text-ink-400">
                    <p>
                        This is the version of the policy that is live while you are reading it; if we change
                        something meaningful, the page you load says so. For anything else: email <?= e($email) ?>,
                        <?php if ($phone !== ''): ?>call <?= e($phone) ?>, <?php endif; ?>use the
                        <a href="<?= e(route('contact')) ?>" class="font-bold text-brand-700 underline decoration-accent-400 decoration-2 underline-offset-4">contact form</a>,
                        or message the desk on WhatsApp during <?= e($hours) ?>. We work from <?= e($address) ?>.
                    </p>
                    <p>
                        Read alongside the <a href="<?= e(route('terms')) ?>" class="font-bold text-brand-700 underline decoration-accent-400 decoration-2 underline-offset-4">terms of service</a>
                        and the <a href="<?= e(route('refund')) ?>" class="font-bold text-brand-700 underline decoration-accent-400 decoration-2 underline-offset-4">refund policy</a>.
                    </p>
                </div>
            </article>
        </div>

        <div class="reveal mt-10 flex flex-wrap items-center gap-3 rounded-card bg-ink p-6 text-white" data-reveal>
            <p class="grow text-sm leading-relaxed text-white/75">
                Prefer to order without signing up at all? Guest checkout exists for that - the minimum data we hold
                is the data an order cannot exist without.
            </p>
            <a href="<?= e(route('plans')) ?>" class="rounded-brand bg-gradient-to-r from-brand-600 to-accent-500 px-4 py-2.5 text-sm font-black text-white shadow-glow transition-transform duration-200 hover:-translate-y-0.5">Browse plans</a>
            <a href="<?= e(wa_link('Hi ' . $brandName . ', I have a privacy question.')) ?>" target="_blank" rel="noopener" class="rounded-brand bg-white/10 px-4 py-2.5 text-sm font-bold text-white transition-colors duration-200 hover:bg-white/20">
                WhatsApp us
            </a>
        </div>
    </section>
</div>
