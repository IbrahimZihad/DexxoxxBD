<?php

/**
 * Quick view dialog. It ships empty: app.js fills every slot from the JSON
 * endpoint with textContent, so no catalogue text is ever parsed as markup.
 * The triggers are real links, so with JavaScript disabled the shopper simply
 * lands on the product page.
 */

?>
<div class="dx-quickview" id="dx-quick-view" role="dialog" aria-modal="true" aria-labelledby="dx-qv-title" hidden>
    <div class="dx-quickview__backdrop" data-qv-close></div>
    <div class="dx-quickview__panel">
        <button class="dx-quickview__close" type="button" data-qv-close>
            <i class="bi bi-x-lg" aria-hidden="true"></i>
            <span class="visually-hidden"><?= e(t('common.close')) ?></span>
        </button>

        <p class="dx-quickview__loading" data-qv-loading><?= e(t('common.loading')) ?></p>

        <div class="dx-quickview__body">
            <img class="dx-quickview__image" data-qv-image src="" alt="">

            <div class="dx-quickview__info">
                <h2 class="dx-quickview__title" id="dx-qv-title" data-qv-title></h2>
                <p class="dx-quickview__rating" data-qv-rating></p>
                <p class="dx-quickview__text" data-qv-text></p>
                <p class="dx-price dx-price--lg" data-qv-price></p>
                <ul class="dx-quickview__plans" data-qv-plans></ul>

                <form class="dx-quickview__form" data-cart-add method="post" action="<?= e(url('cart/add')) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="product_id" value="0">
                    <input type="hidden" name="variation_id" value="0">
                    <input type="hidden" name="quantity" value="1">
                </form>

                <a class="btn btn-dx-soft dx-quickview__link" data-qv-link href="#">&nbsp;</a>
            </div>
        </div>
    </div>
</div>
