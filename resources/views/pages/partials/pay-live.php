<?php

declare(strict_types=1);

/**
 * The rail picker decides what the reference field asks for. The wording lives
 * on each rail card (straight from config/payments.php), so switching rails only
 * ever copies text that is already in the document - and with JavaScript off the
 * card itself still states what it needs.
 */
?>
<script nonce="<?= e($cspNonce ?? '') ?>">
    (function () {
        'use strict';

        var form = document.querySelector('[data-pay-form]');

        if (form === null) {
            return;
        }

        var input = form.querySelector('[data-txn-input]');
        var label = form.querySelector('[data-txn-label]');
        var hint = form.querySelector('[data-txn-hint]');
        var star = form.querySelector('[data-txn-star]');

        if (input === null || label === null || hint === null) {
            return;
        }

        var FALLBACK_LABEL = 'Transaction ID';
        var FALLBACK_HINT = 'The reference printed on your payment receipt or confirmation SMS.';

        form.addEventListener('change', function (event) {
            var picked = event.target;

            if (picked === null || typeof picked.name !== 'string' || picked.name !== 'method') {
                return;
            }

            var text = picked.getAttribute('data-txn-label') || FALLBACK_LABEL;
            var needed = picked.getAttribute('data-txn-required') === '1';

            label.textContent = text;
            input.placeholder = text;
            hint.textContent = picked.getAttribute('data-txn-hint') || FALLBACK_HINT;

            // A browser guard only - PaymentController checks the same rule itself.
            if (needed) {
                input.setAttribute('required', 'required');
            } else {
                input.removeAttribute('required');
            }

            if (star !== null) {
                star.hidden = !needed;
            }
        });
    }());
</script>
