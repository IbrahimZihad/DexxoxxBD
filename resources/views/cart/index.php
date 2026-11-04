<?php

/**
 * Cart page.
 *
 * Every figure printed here comes from CartService::lines(), which reloads each
 * variation from MySQL, clamps quantities to live stock and drops lines whose
 * product was unpublished. The forms below only ever send identifiers.
 *
 * @var array<string, mixed> $cart
 * @var array{lines: list<array<string, mixed>>, removed: list<string>, subtotal: float, item_count: int} $summary
 * @var array{subtotal: float, discount: float, delivery: float, total: float, item_count: int} $totals
 * @var array<string, mixed>|null $coupon
 * @var bool $canCheckout
 */

use App\Core\Auth;

$view->extend('layouts.app');

$lines = $summary['lines'];
$visitor = Auth::instance()->user();

$view->setSection('title', t('cart.title') . ' | ' . (string) setting('brand.name', config('app.name')));
$view->setSection('meta_description', t('cart.empty_text'));
$view->setSection('canonical', url('cart'));
$view->setSection('meta_robots', 'noindex, follow');
?>
<div class="container">
    <?= $view->component('breadcrumb', ['items' => [
        ['label' => t('nav.home'), 'url' => url('')],
        ['label' => t('cart.title')],
    ]]) ?>

    <div class="dx-pagehead">
        <h1 class="dx-pagehead__title"><?= e(t('cart.title')) ?></h1>
        <p class="dx-pagehead__sub"><?= e(choice('cart.items_count', (int) $totals['item_count'])) ?></p>
    </div>

    <?php if ($summary['removed'] !== []): ?>
        <div class="dx-flash dx-flash--warning" role="status">
            <i class="bi bi-exclamation-triangle" aria-hidden="true"></i>
            <span>
                <?= e(t('cart.dropped')) ?>
                <?= e(implode(', ', $summary['removed'])) ?>
            </span>
        </div>
    <?php endif; ?>

    <?php if ($lines === []): ?>
        <?= $view->component('empty-state', [
            'title' => t('cart.empty_title'),
            'text' => t('cart.empty_text'),
            'ctaUrl' => url('products'),
            'ctaLabel' => t('cart.continue_shopping'),
            'icon' => 'bi-bag',
        ]) ?>
    <?php else: ?>
        <div class="dx-layout">
            <section class="dx-results" aria-live="polite">
                <ul class="dx-cartlines">
                    <?php foreach ($lines as $line): ?>
                        <li class="dx-cartline">
                            <span class="dx-cartline__media">
                                <img src="<?= e(image_url($line['image'] === '' ? null : (string) $line['image'])) ?>"
                                     alt="<?= e((string) $line['name']) ?>" loading="lazy" width="96" height="96">
                            </span>

                            <span class="dx-cartline__body">
                                <a class="dx-cartline__name" href="<?= e(url('products/' . (string) $line['product_slug'])) ?>">
                                    <?= e((string) $line['name']) ?>
                                </a>
                                <span class="dx-cartline__plan"><?= e((string) $line['plan']) ?></span>
                                <span class="dx-cartline__meta">
                                    <span><?= e(t('cart.unit_price')) ?>: <?= e(price((float) $line['unit_price'])) ?></span>
                                    <span>SKU <?= e((string) $line['sku']) ?></span>
                                    <span class="dx-chip<?= str_starts_with((string) $line['delivery_method'], 'auto_') ? ' dx-chip--instant' : '' ?>">
                                        <?= e(t('product.delivery.' . (string) $line['delivery_method'])) ?>
                                    </span>
                                </span>
                            </span>

                            <form class="dx-cartline__qty" method="post" action="<?= e(url('cart/update')) ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="item_id" value="<?= e((int) $line['id']) ?>">
                                <?= $view->component('quantity', [
                                    'name' => 'quantity',
                                    'value' => (int) $line['quantity'],
                                    'min' => 1,
                                    'max' => max(1, (int) config('shop.max_per_cart', 5)),
                                    'id' => 'dx-cart-qty-' . (int) $line['id'],
                                ]) ?>
                                <button class="btn btn-dx-ghost btn-sm" type="submit">
                                    <i class="bi bi-arrow-repeat" aria-hidden="true"></i>
                                    <?= e(t('cart.update')) ?>
                                </button>
                            </form>

                            <span class="dx-cartline__total">
                                <strong><?= e(price((float) $line['line_total'])) ?></strong>
                                <form method="post" action="<?= e(url('cart/remove')) ?>">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="item_id" value="<?= e((int) $line['id']) ?>">
                                    <button class="dx-cartline__remove" type="submit"
                                            aria-label="<?= e(t('cart.remove') . ' - ' . (string) $line['name']) ?>">
                                        <i class="bi bi-trash3" aria-hidden="true"></i>
                                        <?= e(t('cart.remove')) ?>
                                    </button>
                                </form>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>

                <div class="dx-cartfoot">
                    <a class="btn btn-dx-ghost btn-sm" href="<?= e(url('products')) ?>">
                        <i class="bi bi-arrow-left" aria-hidden="true"></i>
                        <?= e(t('cart.continue_shopping')) ?>
                    </a>
                    <form method="post" action="<?= e(url('cart/clear')) ?>">
                        <?= csrf_field() ?>
                        <button class="btn btn-outline-danger btn-sm" type="submit"><?= e(t('cart.clear')) ?></button>
                    </form>
                </div>
            </section>

            <aside class="dx-summary">
                <h2 class="dx-h4"><?= e(t('cart.summary')) ?></h2>

                <dl class="dx-summary__rows">
                    <div>
                        <dt><?= e(t('cart.subtotal')) ?></dt>
                        <dd><?= e(price($totals['subtotal'])) ?></dd>
                    </div>
                    <?php if ($totals['discount'] > 0): ?>
                        <div>
                            <dt><?= e(t('cart.discount')) ?></dt>
                            <dd class="text-success">&minus;<?= e(price($totals['discount'])) ?></dd>
                        </div>
                    <?php endif; ?>
                    <div>
                        <dt><?= e(t('cart.delivery_fee')) ?></dt>
                        <dd><?= $totals['delivery'] > 0 ? e(price($totals['delivery'])) : e(t('cart.free')) ?></dd>
                    </div>
                    <div class="dx-summary__total">
                        <dt><?= e(t('cart.total')) ?></dt>
                        <dd><?= e(price($totals['total'])) ?></dd>
                    </div>
                </dl>

                <?php if ($coupon !== null): ?>
                    <form method="post" action="<?= e(url('cart/coupon/remove')) ?>" class="dx-summary__coupon">
                        <?= csrf_field() ?>
                        <p class="dx-hint">
                            <i class="bi bi-ticket-perforated" aria-hidden="true"></i>
                            <?= e(t('cart.applied', ['code' => (string) $coupon['code']])) ?>
                        </p>
                        <button class="btn btn-link p-0" type="submit"><?= e(t('cart.remove_coupon')) ?></button>
                    </form>
                <?php else: ?>
                    <form method="post" action="<?= e(url('cart/coupon')) ?>" class="dx-summary__coupon">
                        <?= csrf_field() ?>
                        <label class="form-label" for="dx-coupon"><?= e(t('cart.coupon')) ?></label>
                        <div class="input-group">
                            <input class="form-control" id="dx-coupon" name="code" type="text"
                                   maxlength="60" placeholder="<?= e(t('cart.coupon_placeholder')) ?>" autocomplete="off">
                            <button class="btn btn-dx-soft" type="submit"><?= e(t('cart.apply')) ?></button>
                        </div>
                    </form>
                <?php endif; ?>

                <?php if ($visitor === null): ?>
                    <a class="btn btn-dx btn-dx-lg w-100" href="<?= e(url('login')) ?>">
                        <?= e(t('cart.login_to_checkout')) ?>
                    </a>
                    <p class="dx-hint"><?= e(t('cart.checkout_note')) ?></p>
                <?php elseif (!$canCheckout): ?>
                    <button class="btn btn-dx btn-dx-lg w-100" type="button" disabled>
                        <?= e(t('cart.checkout')) ?>
                    </button>
                <?php else: ?>
                    <a class="btn btn-dx btn-dx-lg w-100" href="<?= e(url('checkout')) ?>">
                        <i class="bi bi-lock" aria-hidden="true"></i>
                        <?= e(t('cart.checkout')) ?>
                    </a>
                    <p class="dx-hint"><?= e(t('cart.checkout_note')) ?></p>
                <?php endif; ?>

                <ul class="dx-trustlist">
                    <li><i class="bi bi-shield-check" aria-hidden="true"></i> <?= e(t('cart.total_note')) ?></li>
                    <li><i class="bi bi-arrow-repeat" aria-hidden="true"></i> <?= e(t('cart.stock_note')) ?></li>
                </ul>
            </aside>
        </div>
    <?php endif; ?>
</div>
