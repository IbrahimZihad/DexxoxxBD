<?php

/**
 * Sticky storefront header: brand, type-ahead search, cart, account and the CMS
 * menu. Every link here is also reachable without JavaScript - the nav uses
 * Bootstrap's own dropdown, and the search form posts as a GET.
 */

use App\Repositories\CategoryRepository;
use App\Repositories\ContentRepository;

$categories = (new CategoryRepository())->topLevel(12);
$menu = (new ContentRepository())->menu('header');
$brandName = (string) setting('brand.name', config('app.name', 'DexxoxxBD'));
?>
<header class="dx-header" data-sticky-header>
    <div class="container">
        <div class="dx-header__bar">
            <a class="dx-logo" href="<?= e(url('/')) ?>" aria-label="<?= e($brandName) ?>">
                <span class="dx-logo__mark" aria-hidden="true">D<span class="dx-logo__x">X</span></span>
                <span class="dx-logo__word">
                    <?= e(mb_substr($brandName, 0, 7)) ?><em><?= e(mb_substr($brandName, 7)) ?></em>
                </span>
            </a>

            <form class="dx-search" role="search" method="get" action="<?= e(url('products')) ?>" data-search
                  data-endpoint="<?= e(url('api/search/suggest')) ?>">
                <label class="visually-hidden" for="dx-q"><?= e(t('common.search')) ?></label>
                <input class="form-control dx-search__input" id="dx-q" name="q" type="search" autocomplete="off"
                       role="combobox" aria-expanded="false" aria-controls="dx-suggestions" aria-autocomplete="list"
                       placeholder="<?= e(t('common.search_placeholder')) ?>"
                       value="<?= e((string) $request->queryValue('q', '')) ?>">
                <button class="btn btn-dx dx-search__submit" type="submit">
                    <i class="bi bi-search" aria-hidden="true"></i>
                    <span class="d-none d-lg-inline"><?= e(t('common.search')) ?></span>
                </button>
                <div class="dx-suggestions" id="dx-suggestions" data-search-results hidden></div>
            </form>

            <div class="dx-header__actions">
                <?php if (auth_check()): ?>
                    <a class="dx-iconlink" href="<?= e(url('account')) ?>">
                        <i class="bi bi-person-circle" aria-hidden="true"></i>
                        <span class="dx-iconlink__text">
                            <?= e((string) (auth_user()['name'] ?? t('nav.account'))) ?>
                        </span>
                    </a>
                <?php else: ?>
                    <a class="dx-iconlink" href="<?= e(url('login')) ?>">
                        <i class="bi bi-box-arrow-in-right" aria-hidden="true"></i>
                        <span class="dx-iconlink__text"><?= e(t('common.sign_in')) ?></span>
                    </a>
                <?php endif; ?>

                <a class="dx-cartlink" href="<?= e(url('cart')) ?>"
                   aria-label="<?= e(t('common.cart')) ?>">
                    <i class="bi bi-bag" aria-hidden="true"></i>
                    <span class="dx-cartlink__text"><?= e(t('common.cart')) ?></span>
                    <span class="dx-cartlink__count" data-cart-count
                          <?= cart_count() === 0 ? 'hidden' : '' ?>><?= e(cart_count()) ?></span>
                </a>
            </div>
        </div>

        <nav class="dx-nav" aria-label="<?= e(t('nav.home')) ?>">
            <ul class="dx-nav__list">
                <?php foreach ($menu as $item): ?>
                    <li class="dx-nav__item<?= $item['children'] !== [] ? ' dx-nav__item--parent dropdown' : '' ?>">
                        <?php if ($item['children'] !== []): ?>
                            <button class="dx-nav__link dx-nav__toggle" type="button" data-bs-toggle="dropdown"
                                    aria-expanded="false">
                                <?= e((string) $item['label']) ?>
                                <i class="bi bi-chevron-down" aria-hidden="true"></i>
                            </button>
                            <ul class="dropdown-menu dx-nav__dropdown">
                                <?php foreach ($item['children'] as $child): ?>
                                    <li>
                                        <a class="dropdown-item" href="<?= e((string) $child['href']) ?>">
                                            <?= e((string) $child['label']) ?>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <a class="dx-nav__link<?= is_current_path((string) parse_url((string) $item['href'],
                                PHP_URL_PATH) ?? '') ? ' is-active' : '' ?>"
                               href="<?= e((string) $item['href']) ?>">
                                <?= e((string) $item['label']) ?>
                            </a>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>

                <li class="dx-nav__item dx-nav__item--parent dropdown">
                    <button class="dx-nav__link dx-nav__toggle" type="button" data-bs-toggle="dropdown"
                            aria-expanded="false">
                        <?= e(t('nav.categories')) ?>
                        <i class="bi bi-chevron-down" aria-hidden="true"></i>
                    </button>
                    <ul class="dropdown-menu dx-nav__dropdown dx-nav__dropdown--wide">
                        <?php foreach ($categories as $category): ?>
                            <li>
                                <a class="dropdown-item" href="<?= e(url('categories/' . $category['slug'])) ?>">
                                    <i class="bi <?= e((string) ($category['icon'] ?: 'bi-collection')) ?>"
                                       aria-hidden="true"></i>
                                    <?= e((string) $category['name']) ?>
                                    <span class="dx-nav__badge"><?= e((int) $category['product_count']) ?></span>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </li>
            </ul>
        </nav>
    </div>
</header>
