<?php

declare(strict_types=1);

/**
 * Refund and replacement policy. Every number on the page comes from settings,
 * so the promise written here is the promise the shop is configured to keep.
 *
 * @var App\Core\View $view
 */
$brandName = setting('brand.name', 'DexxozzBD');
$agentName = setting('whatsapp.agent_name', 'the order desk');
$hours = setting('whatsapp.hours', '9:00 AM - 11:00 PM, every day (Dhaka time)');
$email = setting('contact.email', 'our support address');
$phone = setting('contact.phone');
$guarantee = setting('trust.guarantee', '7-day replacement on every personal plan');
$deliveryMinutes = max(1, (int) setting('trust.delivery_minutes', '10'));
$holdMinutes = max(5, (int) setting('order.pending_hold_minutes', '90'));
$replacementDays = 7;
$refundWorkingDays = 3;

$view->extend('layouts/app');
$view->setSection('title', 'Refund and replacement policy - ' . $brandName);
$view->setSection('meta_description', $brandName . ': full refund before delivery, ' . $replacementDays . '-day replacement window after it, pro-rata refund for unusable unused days, and a plain list of what we do not refund.');
$view->setSection('canonical', route('refund'));

$sections = [
    ['id' => 'short', 'title' => '1. The short version'],
    ['id' => 'before', 'title' => '2. Before delivery'],
    ['id' => 'after', 'title' => '3. After delivery'],
    ['id' => 'replace', 'title' => '4. The ' . $replacementDays . '-day replacement window'],
    ['id' => 'fault', 'title' => '5. Whose fault counts'],
    ['id' => 'money', 'title' => '6. How the money comes back'],
    ['id' => 'claim', 'title' => '7. Making a claim'],
    ['id' => 'not', 'title' => '8. What we do not refund'],
    ['id' => 'goodwill', 'title' => '9. Goodwill, in plain words'],
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
                        <path d="M4 12a8 8 0 1 1 8 8" stroke-linecap="round"/><path d="m4 8 4 4 4-4" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    Money and replacements
                </p>
                <h1 class="mt-4 text-4xl font-black tracking-tight md:text-5xl">
                    <span class="animate-gradient text-gradient">Refund and replacement policy</span>
                </h1>
                <p class="mt-4 max-w-2xl text-base leading-relaxed text-ink-400">
                    Digital subscriptions cannot be “returned” like a shirt, so this page explains what we promise
                    instead: full money back before we deliver, a real replacement when a seat dies, and a pro-rata
                    refund when we cannot replace it. <?= e($guarantee) ?> - that is not marketing copy, it is the
                    rule the desk works to.
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

        <?php /* --------------------------- the short version ---------------------------*/ ?>
        <div id="short" class="reveal mt-10 grid gap-4 sm:grid-cols-2" data-reveal>
            <div class="rounded-card border-2 border-emerald-200 bg-emerald-50/70 p-6">
                <p class="text-xs font-black uppercase tracking-[0.18em] text-emerald-700">Paid, not delivered</p>
                <p class="mt-2 text-2xl font-black tracking-tight text-ink">Full refund</p>
                <p class="mt-1.5 text-sm leading-relaxed text-ink-400">Or ask us to keep going. Your choice, no forms.</p>
            </div>
            <div class="rounded-card border-2 border-brand-200 bg-brand-50/70 p-6">
                <p class="text-xs font-black uppercase tracking-[0.18em] text-brand-700">Delivered, broke within <?= e((string) $replacementDays) ?> days</p>
                <p class="mt-2 text-2xl font-black tracking-tight text-ink">Replaced free</p>
                <p class="mt-1.5 text-sm leading-relaxed text-ink-400">New seat, same term, remaining days carried over.</p>
            </div>
            <div class="rounded-card border-2 border-amber-200 bg-amber-50/70 p-6">
                <p class="text-xs font-black uppercase tracking-[0.18em] text-amber-700">Delivered, you changed your mind</p>
                <p class="mt-2 text-2xl font-black tracking-tight text-ink">No refund</p>
                <p class="mt-1.5 text-sm leading-relaxed text-ink-400">The seat is yours for the rest of the term - use it or gift it inside your household.</p>
            </div>
            <div class="rounded-card border-2 border-rose-200 bg-rose-50/70 p-6">
                <p class="text-xs font-black uppercase tracking-[0.18em] text-rose-700">Unusable through our fault, cannot replace</p>
                <p class="mt-2 text-2xl font-black tracking-tight text-ink">Unused days back</p>
                <p class="mt-1.5 text-sm leading-relaxed text-ink-400">Counted in days from the date it stopped working.</p>
            </div>
        </div>

        <div class="mt-10 space-y-8">
            <article id="before" class="reveal rounded-card border border-brand-100 bg-white p-6 shadow-card sm:p-8" data-reveal>
                <h2 class="text-xl font-black tracking-tight text-ink md:text-2xl"><?= e($sections[1]['title']) ?></h2>
                <div class="mt-4 space-y-3 text-sm leading-relaxed text-ink-400">
                    <p>
                        Until the login actually reaches your WhatsApp, nothing has been consumed. Tell us to stop and
                        you get the full amount back, or simply let the order lapse - an unpaid order dies on its own
                        after <?= e((string) $holdMinutes) ?> minutes and costs you nothing.
                    </p>
                    <p>
                        If we cannot source the plan you ordered at the price you paid - it happens when a brand
                        raises prices overnight - we do not hold your money hostage: closest equivalent plan, or full
                        refund to the number it came from, usually within the same conversation.
                    </p>
                    <p>
                        Delivery is only possible inside the opening hours (<?= e($hours) ?>). If you paid at 1 AM,
                        “not yet delivered” means “the desk is asleep”, not “we took your money”.
                    </p>
                </div>
            </article>

            <article id="after" class="reveal rounded-card border border-brand-100 bg-white p-6 shadow-card sm:p-8" data-reveal>
                <h2 class="text-xl font-black tracking-tight text-ink md:text-2xl"><?= e($sections[2]['title']) ?></h2>
                <div class="mt-4 space-y-3 text-sm leading-relaxed text-ink-400">
                    <p>
                        Once the credentials are in your chat, the service has been provided and a change of mind is
                        not a refund reason. What remains refundable is time you genuinely could not use and that we
                        could not replace:
                    </p>
                    <ul class="space-y-2">
                        <li class="flex gap-2"><span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-brand-400"></span><span>A seat that stops working after the window, through our side of the fault, with no replacement available: the unused days are refunded pro rata.</span></li>
                        <li class="flex gap-2"><span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-brand-400"></span><span>A duplicate or over-payment: always returned in full, including the second copy of the same transaction.</span></li>
                        <li class="flex gap-2"><span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-brand-400"></span><span>Money sent to a number that is not ours because a scammer copied it: we refund what we can actually see arrive, and we help you build the case for your wallet provider.</span></li>
                    </ul>
                    <p>
                        Pro-rata means the term price divided by its days, multiplied by the days you lost. A 12-month
                        plan that dies on day 40 with no replacement available returns roughly eleven twelfths of what
                        you paid - we are not going to be clever about rounding.
                    </p>
                </div>
            </article>

            <article id="replace" class="reveal rounded-card border border-brand-100 bg-white p-6 shadow-card sm:p-8" data-reveal>
                <h2 class="text-xl font-black tracking-tight text-ink md:text-2xl"><?= e($sections[3]['title']) ?></h2>
                <div class="mt-4 space-y-3 text-sm leading-relaxed text-ink-400">
                    <p>
                        <strong class="font-bold text-ink"><?= e($guarantee) ?></strong>. If a personal seat stops
                        working within <?= e((string) $replacementDays) ?> days of delivery and it was not caused by
                        your usage, you get a working seat - same plan, remaining term carried over - not a coupon and
                        not a debate.
                    </p>
                    <p>
                        What we cover under the window: the account gets locked or reset by the brand, the email is
                        reclaimed, a payment on our side fails, the seat is removed from a family plan we did not ask
                        to be on, or the login simply never worked.
                    </p>
                    <p>
                        After the window, a fault on our side is still fixed; a fault on the brand’s side usually needs
                        a replacement seat rather than money. Message us the day something breaks - the earlier you
                        write, the less term you lose.
                    </p>
                </div>
            </article>

            <article id="fault" class="reveal rounded-card border border-brand-100 bg-white p-6 shadow-card sm:p-8" data-reveal>
                <h2 class="text-xl font-black tracking-tight text-ink md:text-2xl"><?= e($sections[4]['title']) ?></h2>
                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <div class="rounded-brand bg-emerald-50/70 p-4">
                        <p class="text-xs font-black uppercase tracking-[0.16em] text-emerald-700">Our side - we fix it</p>
                        <ul class="mt-2 space-y-1.5 text-xs leading-relaxed text-ink-400">
                            <li>Wrong or dead credentials sent to you.</li>
                            <li>The seat is removed by the plan owner because of us.</li>
                            <li>Our payment to the brand fails and the account locks.</li>
                            <li>A tier sold while out of stock (should not happen; if it did, refund or upgrade).</li>
                        </ul>
                    </div>
                    <div class="rounded-brand bg-rose-50/70 p-4">
                        <p class="text-xs font-black uppercase tracking-[0.16em] text-rose-700">Your side - goodwill only</p>
                        <ul class="mt-2 space-y-1.5 text-xs leading-relaxed text-ink-400">
                            <li>Credentials shared in a group, sold on, or handed to a stranger.</li>
                            <li>Password never changed after delivery and the account got taken over.</li>
                            <li>Device bans from mods, downloaders, scraping or bulk AI automation.</li>
                            <li>Paying a number that is not on our payment page.</li>
                        </ul>
                    </div>
                </div>
                <p class="mt-4 text-sm leading-relaxed text-ink-400">
                    Where fault is genuinely unclear - a family plan owner resetting a password, a brand-side region
                    change - we default to giving you a working seat rather than arguing about the category. It is
                    cheaper for us than losing a regular.
                </p>
            </article>

            <article id="money" class="reveal rounded-card border border-brand-100 bg-white p-6 shadow-card sm:p-8" data-reveal>
                <h2 class="text-xl font-black tracking-tight text-ink md:text-2xl"><?= e($sections[5]['title']) ?></h2>
                <div class="mt-4 space-y-3 text-sm leading-relaxed text-ink-400">
                    <p>
                        Approved refunds go back <strong class="font-bold text-ink">to the number or account the money
                        came from</strong> - never to a different one, because forwarding a refund is how a stolen
                        wallet gets laundered through a shop. Expect it within <?= e((string) $refundWorkingDays) ?> days
                        of us agreeing, and usually the same day during opening hours
                        (<?= e($hours) ?>).
                    </p>
                    <ul class="space-y-2">
                        <li class="flex gap-2"><span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-brand-400"></span><span>bKash, Nagad and Rocket refunds are sent from the same merchant number you paid; the credit appears as a wallet transaction you can check.</span></li>
                        <li class="flex gap-2"><span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-brand-400"></span><span>Bank transfers go back to the account you sent from, and take a few extra days because banks are banks.</span></li>
                        <li class="flex gap-2"><span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-brand-400"></span><span>Where a wallet charges its own transaction fee, we refund the amount you paid us; the operator’s fee is outside our control and we will not pretend otherwise.</span></li>
                        <li class="flex gap-2"><span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-brand-400"></span><span>Instead of money you may prefer a longer term on the next order - we will happily credit it, at whatever rate makes the inconvenience even.</span></li>
                    </ul>
                </div>
            </article>

            <article id="claim" class="reveal rounded-card border border-brand-100 bg-white p-6 shadow-card sm:p-8" data-reveal>
                <h2 class="text-xl font-black tracking-tight text-ink md:text-2xl"><?= e($sections[6]['title']) ?></h2>
                <ol class="mt-4 space-y-4">
                    <?php foreach ([
                        ['Message the desk', 'WhatsApp is fastest during ' . $hours . '; the contact form or ' . $email . ' work any time.'],
                        ['Give the order number', 'The code in your confirmation and in your account. It is how we find the payment in seconds.'],
                        ['Say what is wrong', 'Login failing, seat removed, region block, email reclaimed - one line is enough. A screenshot of the app error helps, and “it stopped working on the 3rd” helps more than a angry paragraph.'],
                        ['Let us check the ledger', 'We look at the plan, the brand account and our own statement, then tell you which of three things happens: replaced, refunded, or explained.'],
                    ] as $index => [$claimTitle, $claimText]): ?>
                        <li class="flex gap-3">
                            <span class="grid h-8 w-8 shrink-0 place-items-center rounded-pill bg-gradient-to-br from-brand-600 to-accent-500 text-xs font-black text-white shadow-pop"><?= e((string) ($index + 1)) ?></span>
                            <span>
                                <span class="block text-sm font-bold text-ink"><?= e($claimTitle) ?></span>
                                <span class="mt-0.5 block text-xs leading-relaxed text-ink-400"><?= e($claimText) ?></span>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ol>
                <p class="mt-5 rounded-brand bg-brand-50/70 px-4 py-3 text-xs leading-relaxed text-ink-400">
                    Claims sent within days are solved within minutes. A claim for a 12-month plan raised in the
                    eleventh month needs a bit more evidence from both sides - which is fair, and exactly why the
                    replacement window exists early on.
                </p>
            </article>

            <article id="not" class="reveal rounded-card border border-rose-200 bg-rose-50/50 p-6 shadow-card sm:p-8" data-reveal>
                <h2 class="text-xl font-black tracking-tight text-ink md:text-2xl"><?= e($sections[7]['title']) ?></h2>
                <ul class="mt-4 space-y-2 text-sm leading-relaxed text-ink-400">
                    <li class="flex gap-2"><span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-rose-400"></span><span>Buying a plan you did not need, then deciding the app was not for you after you used it.</span></li>
                    <li class="flex gap-2"><span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-rose-400"></span><span>Content availability: a series leaving a catalogue is the brand’s decision, not a broken seat.</span></li>
                    <li class="flex gap-2"><span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-rose-400"></span><span>Your own internet, TV box, or a household member who forgot the password.</span></li>
                    <li class="flex gap-2"><span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-rose-400"></span><span>Reselling the seat, running it as a service for others, or using it commercially against our terms.</span></li>
                    <li class="flex gap-2"><span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-rose-400"></span><span>Payments made to numbers not printed on our payment page - we cannot refund money we never received.</span></li>
                </ul>
            </article>

            <article id="goodwill" class="reveal rounded-card border border-brand-100 bg-white p-6 shadow-card sm:p-8" data-reveal>
                <h2 class="text-xl font-black tracking-tight text-ink md:text-2xl"><?= e($sections[8]['title']) ?></h2>
                <div class="mt-4 space-y-3 text-sm leading-relaxed text-ink-400">
                    <p>
                        The rules above are the floor, not the personality of the shop. We are a small desk that lives
                        on repeat customers and screenshots of happy chats, so a one-off problem - a plan that arrived
                        late during a busy night, a two-day gap while a brand fixed something - gets extended, credited
                        or replaced without you having to quote a clause at us.
                    </p>
                    <p>
                        What we promise not to do: silently mark something delivered when it was not, keep money for a
                        seat we could not hand over, or treat a refund request as an enemy. If you ever feel we did,
                        that is worth telling us directly - <?= e($agentName) ?> reads every message, and the
                        <a href="<?= e(route('contact')) ?>" class="font-bold text-brand-700 underline decoration-accent-400 decoration-2 underline-offset-4">contact form</a>
                        lands in the same inbox.
                    </p>
                </div>
            </article>
        </div>

        <?php /* ------------------------------- CTA -------------------------------*/ ?>
        <div class="reveal mt-10 relative overflow-hidden rounded-card bg-gradient-to-br from-brand-700 via-brand-600 to-accent-600 p-7 text-white shadow-pop" data-reveal>
            <span class="blob -right-10 -top-14 animate-blob bg-white/15" aria-hidden="true"></span>
            <div class="relative grid gap-5 md:grid-cols-[1.5fr_1fr] md:items-center">
                <div>
                    <h2 class="text-2xl font-black tracking-tight">Need to claim something right now?</h2>
                    <p class="mt-2 text-sm leading-relaxed text-white/80">
                        Send the order number and one line about what happened. Average first reply inside the opening
                        hours is well under the <?= e((string) $deliveryMinutes) ?>-minute delivery target we hold
                        ourselves to.
                    </p>
                    <p class="mt-2 text-xs text-white/65">
                        Read with the <a href="<?= e(route('terms')) ?>" class="font-bold underline decoration-white/40 underline-offset-4 hover:text-white">terms of service</a>
                        and the <a href="<?= e(route('privacy')) ?>" class="font-bold underline decoration-white/40 underline-offset-4 hover:text-white">privacy policy</a>.
                        <?php if ($phone !== ''): ?> Phone: <?= e($phone) ?>. <?php endif; ?>Email: <?= e($email) ?>.
                    </p>
                </div>
                <div class="flex flex-col gap-3">
                    <a href="<?= e(wa_link('Hi ' . $brandName . ', I need a replacement or refund. My order number is: ')) ?>" target="_blank" rel="noopener" class="relative flex items-center justify-center gap-2 overflow-hidden rounded-brand bg-white px-6 py-3.5 text-sm font-black text-brand-700 shadow-soft transition-transform duration-200 hover:-translate-y-0.5">
                        <span class="animate-shimmer absolute inset-0 bg-gradient-to-r from-white/0 via-brand-100/70 to-white/0" aria-hidden="true"></span>
                        <span class="relative">Claim on WhatsApp</span>
                    </a>
                    <a href="<?= e(route('account')) ?>" class="rounded-brand border-2 border-white/40 px-6 py-3 text-center text-sm font-bold text-white transition-colors duration-200 hover:bg-white/10">
                        Find your order number
                    </a>
                </div>
            </div>
        </div>
    </section>
</div>
