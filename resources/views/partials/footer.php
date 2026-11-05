<?php

declare(strict_types=1);

use App\Models\Service;
use App\Services\PaymentService;

/**
 * Four columns: the live catalogue, the legal pages, the money rails the shop
 * actually accepts, and the contact block. Every link goes through a route name.
 */
$brandName = setting('brand.name', 'DexxozzBD');

$services = (new Service())->catalogue(7);

$legal = [
    ['label' => 'About us', 'route' => 'about'],
    ['label' => 'How it works', 'route' => 'how'],
    ['label' => 'FAQ', 'route' => 'faq'],
    ['label' => 'Track an order', 'route' => 'track'],
    ['label' => 'Terms of sale', 'route' => 'terms'],
    ['label' => 'Privacy policy', 'route' => 'privacy'],
    ['label' => 'Refund policy', 'route' => 'refund'],
    ['label' => 'Contact', 'route' => 'contact'],
];

$rails = PaymentService::available();
$phoneDigits = preg_replace('/[^\d+]/', '', setting('contact.phone')) ?? '';
?>
<footer class="relative mt-20 overflow-hidden border-t border-brand-100 bg-ink text-white/80">
    <span class="blob -left-24 top-0 opacity-30"></span>
    <span class="blob -right-20 bottom-0 opacity-20"></span>

    <div class="relative mx-auto max-w-7xl px-4 py-14 lg:px-6">
        <div class="grid gap-10 md:grid-cols-2 lg:grid-cols-4">
            <div>
                <a href="<?= e(route('home')) ?>" class="flex items-center gap-2.5">
                    <span class="grid h-10 w-10 place-items-center rounded-brand bg-gradient-to-br from-brand-500 to-accent-400">
                        <svg class="h-5 w-5 text-ink" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                            <path d="M13.1 2 4.6 13.2h5.2L8.4 22l9.6-11.9h-5.4L13.1 2Z"/>
                        </svg>
                    </span>
                    <span class="text-lg font-black tracking-tight text-white"><?= e($brandName) ?></span>
                </a>
                <p class="mt-4 text-sm leading-relaxed"><?= e(setting('brand.tagline', 'Premium subscriptions, delivered in minutes.')) ?></p>
                <a
                    href="<?= e(wa_link()) ?>"
                    target="_blank"
                    rel="noopener"
                    class="mt-5 inline-flex items-center gap-2 rounded-pill bg-emerald-500 px-4 py-2 text-sm font-bold text-white transition-transform duration-200 hover:-translate-y-0.5 hover:bg-emerald-600"
                >
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                        <path d="M12 2a10 10 0 0 0-8.6 15L2 22l5.2-1.3A10 10 0 1 0 12 2Zm5.3 14c-.2.6-1.2 1.2-1.7 1.2-.5.1-1 .1-1.7-.1-.9-.3-2.3-1-3.6-2.6-1-1.2-1.6-2.5-1.7-3-.1-.6.3-1.4.8-1.8.2-.2.4-.2.6-.2h.5c.2 0 .3 0 .4.3l.6 1.4c.1.2 0 .4-.1.5l-.4.5c-.1.1-.1.3 0 .4.3.5 1.2 1.5 2.1 1.9.2.1.3.1.5-.1l.5-.6c.1-.2.3-.2.5-.1l1.4.7c.2.1.3.2.3.4 0 .2 0 .7-.3 1.2Z"/>
                    </svg>
                    Chat with us
                </a>
            </div>

            <div>
                <h3 class="text-sm font-bold uppercase tracking-widest text-accent-300">Subscriptions</h3>
                <ul class="mt-4 space-y-2 text-sm">
                    <?php foreach ($services as $footerService): ?>
                        <li>
                            <a href="<?= e(route('service.show', ['slug' => (string) $footerService['slug']])) ?>" class="inline-flex items-center gap-2 transition-colors duration-200 hover:text-white">
                                <span class="h-2 w-2 shrink-0 rounded-full" style="background-color: <?= e(preg_match('/^#[0-9a-fA-F]{3,8}$/', (string) ($footerService['brand_color'] ?? '')) === 1 ? (string) $footerService['brand_color'] : '#7c50f2') ?>"></span>
                                <?= e((string) $footerService['name']) ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                    <li><a href="<?= e(route('plans')) ?>" class="font-semibold text-white underline decoration-accent-400 decoration-2 underline-offset-4">All plans &amp; prices</a></li>
                </ul>
            </div>

            <div>
                <h3 class="text-sm font-bold uppercase tracking-widest text-accent-300">Company</h3>
                <ul class="mt-4 space-y-2 text-sm">
                    <?php foreach ($legal as $item): ?>
                        <li><a href="<?= e(route($item['route'])) ?>" class="transition-colors duration-200 hover:text-white"><?= e($item['label']) ?></a></li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <div>
                <h3 class="text-sm font-bold uppercase tracking-widest text-accent-300">Pay with</h3>
                <ul class="mt-4 space-y-2 text-sm">
                    <?php foreach ($rails as $rail): ?>
                        <li class="flex items-center gap-2">
                            <span class="h-2 w-2 shrink-0 rounded-full" style="background-color: <?= e(preg_match('/^#[0-9a-fA-F]{3,8}$/', (string) ($rail['color'] ?? '')) === 1 ? (string) $rail['color'] : '#7c50f2') ?>"></span>
                            <span class="font-semibold text-white"><?= e((string) $rail['label']) ?></span>
                            <?php $railNumber = PaymentService::numberFor((string) ($rail['number_key'] ?? '')); ?>
                            <?php if ($railNumber !== ''): ?>
                                <span class="text-white/60"><?= e($railNumber) ?></span>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>

                <h3 class="mt-8 text-sm font-bold uppercase tracking-widest text-accent-300">Reach us</h3>
                <ul class="mt-4 space-y-2 text-sm">
                    <?php if (setting('contact.email') !== ''): ?>
                        <li><a href="mailto:<?= e(setting('contact.email')) ?>" class="transition-colors duration-200 hover:text-white"><?= e(setting('contact.email')) ?></a></li>
                    <?php endif; ?>
                    <?php if (setting('contact.phone') !== ''): ?>
                        <li><a href="tel:<?= e($phoneDigits) ?>" class="transition-colors duration-200 hover:text-white"><?= e(setting('contact.phone')) ?></a></li>
                    <?php endif; ?>
                    <?php if (setting('contact.address') !== ''): ?>
                        <li class="text-white/70"><?= e(setting('contact.address')) ?></li>
                    <?php endif; ?>
                    <li class="text-white/70"><?= e(setting('whatsapp.hours')) ?></li>
                </ul>
            </div>
        </div>

        <?php if (setting('footer.note') !== ''): ?>
            <p class="mt-10 rounded-brand border border-white/10 bg-white/5 p-4 text-xs leading-relaxed text-white/60">
                <?= e(setting('footer.note')) ?>
            </p>
        <?php endif; ?>

        <div class="mt-8 flex flex-col items-start justify-between gap-3 border-t border-white/10 pt-6 text-xs text-white/60 sm:flex-row sm:items-center">
            <p>&copy; <span data-year><?= e(date('Y')) ?></span> <?= e($brandName) ?>. All rights reserved.</p>
            <p class="flex flex-wrap items-center gap-x-4 gap-y-1">
                <span>Made in Dhaka</span>
                <a href="<?= e(route('health')) ?>" class="hover:text-white">System status</a>
                <a href="<?= e(route('terms')) ?>" class="hover:text-white">Terms</a>
                <a href="<?= e(route('refund')) ?>" class="hover:text-white">Refunds</a>
            </p>
        </div>
    </div>
</footer>
