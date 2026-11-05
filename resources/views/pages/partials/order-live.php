<?php

declare(strict_types=1);

/**
 * Print helpers for the order page.
 *
 * A receipt is the one thing a customer wants off the screen, and the scroll
 * reveal animation would otherwise print whatever was never scrolled to as blank
 * paper - so every revealed block is unlocked before the dialog opens.
 */
?>
<script nonce="<?= e($cspNonce ?? '') ?>">
    (function () {
        'use strict';

        var doc = document;
        var win = window;

        var revealAll = function () {
            [].forEach.call(doc.querySelectorAll('.reveal'), function (node) {
                node.classList.add('is-revealed');
            });
        };

        [].forEach.call(doc.querySelectorAll('[data-print]'), function (button) {
            if (button.dataset.printBound === '1') {
                return;
            }

            button.dataset.printBound = '1';

            button.addEventListener('click', function () {
                revealAll();
                win.print();
            });
        });

        win.addEventListener('beforeprint', revealAll);
    }());
</script>
