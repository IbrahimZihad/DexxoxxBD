<?php

declare(strict_types=1);

/**
 * The only script the cart needs. app.js handles the first paint of the line
 * list; when a fetch response replaces [data-cart-lines] the new steppers would
 * otherwise be inert, so this re-binds them, posts a line form as soon as its
 * quantity really changes, and puts the keyboard focus back on the button that
 * was pressed. Everything here is progressive: with JavaScript off every line
 * form still posts natively to cart/update or cart/remove.
 *
 * @var string $cspNonce
 */
?>
<script nonce="<?= e($cspNonce ?? '') ?>">
(function () {
    'use strict';

    var doc = document;
    var list = doc.querySelector('[data-cart-lines]');
    if (!list) return;

    var number = function (value, fallback) {
        var parsed = parseFloat(value);
        return isNaN(parsed) ? fallback : parsed;
    };

    var clamp = function (group, raw) {
        var min = number(group.getAttribute('data-min'), 1);
        var max = number(group.getAttribute('data-max'), 50);
        var value = Math.round(number(raw, min));
        return Math.min(Math.max(value, min), max);
    };

    var keyOf = function (node) {
        var form = node.closest ? node.closest('form') : null;
        return form ? (form.getAttribute('data-line-id') || '') : '';
    };

    // Last value we posted for a line, so a blur that changed nothing cannot
    // fire a pointless update request.
    var posted = {};

    var seed = function () {
        var inputs = list.querySelectorAll('[data-qty-input]');
        for (var i = 0; i !== inputs.length; i++) {
            posted[keyOf(inputs[i])] = inputs[i].value;
        }
    };

    /** Steppers inside freshly rendered lines (app.js only binds at first paint). */
    var enhance = function (root) {
        if (!root || !root.querySelectorAll) return;

        var groups = root.querySelectorAll('[data-qty]');

        for (var i = 0; i !== groups.length; i++) {
            var group = groups[i];
            if (group.getAttribute('data-qty-bound') === '1') continue;

            var input = group.querySelector('[data-qty-input]');
            if (!input) continue;

            group.setAttribute('data-qty-bound', '1');

            (function (group, input) {
                var step = function (button) {
                    var current = clamp(group, input.value);
                    var next = button.hasAttribute('data-qty-plus') ? current + 1 : current - 1;
                    input.value = String(clamp(group, next));
                    input.dispatchEvent(new Event('change', { bubbles: true }));
                };

                var minus = group.querySelector('[data-qty-minus]');
                var plus = group.querySelector('[data-qty-plus]');

                if (minus) minus.addEventListener('click', function (event) { event.preventDefault(); step(minus); });
                if (plus) plus.addEventListener('click', function (event) { event.preventDefault(); step(plus); });

                input.addEventListener('change', function () {
                    var clamped = String(clamp(group, input.value));
                    if (input.value !== clamped) input.value = clamped;
                });
            })(group, input);
        }
    };

    var submit = function (form) {
        if (typeof form.requestSubmit === 'function') {
            form.requestSubmit();
            return;
        }
        form.submit();
    };

    var pressed = null;

    list.addEventListener('click', function (event) {
        var button = event.target && event.target.closest ? event.target.closest('[data-qty-plus],[data-qty-minus]') : null;
        if (!button) return;
        pressed = { line: keyOf(button), plus: button.hasAttribute('data-qty-plus') };
    }, true);

    list.addEventListener('change', function (event) {
        var input = event.target;
        if (!input || !input.getAttribute || input.getAttribute('data-qty-input') === null) return;

        var form = input.closest ? input.closest('form') : null;
        if (!form || form.getAttribute('data-ajax') === null) return;

        var line = keyOf(form);
        if (line !== '' && posted[line] === input.value) return;
        posted[line] = input.value;

        submit(form);
    });

    var restoreFocus = function () {
        if (!pressed) return;

        var selector = '[data-line-id="' + pressed.line + '"] ' + (pressed.plus ? '[data-qty-plus]' : '[data-qty-minus]');
        var button = list.querySelector(selector);
        pressed = null;

        if (button && typeof button.focus === 'function') button.focus();
    };

    if (typeof MutationObserver === 'function') {
        new MutationObserver(function () {
            enhance(list);
            seed();
            restoreFocus();
        }).observe(list, { childList: true, subtree: true });
    }

    seed();

    // The steppers app.js already bound at first paint are marked so this file
    // never attaches a second pair of handlers to them.
    var shipped = list.querySelectorAll('[data-qty]');
    for (var s = 0; s !== shipped.length; s++) shipped[s].setAttribute('data-qty-bound', '1');
})();
</script>
