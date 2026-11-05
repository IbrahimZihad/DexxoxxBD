<?php

declare(strict_types=1);

/**
 * Everything inside [data-cart-lines]: the list of line forms, or the empty
 * state when nothing is left. CartController renders this same file into
 * lines_html, so a fetch answer and a page load are always identical.
 *
 * @var App\Core\View $view
 * @var list<array<string, mixed>> $lines
 */
$lines = (array) ($lines ?? []);

if ($lines === []) {
    echo $view->component('empty-state', [
        'icon' => 'bag',
        'title' => 'Your cart is empty',
        'text' => 'Pick a brand, choose how many months you want, and it lands here. Nothing is charged until you send the payment and a human checks it.',
        'action_url' => route('plans'),
        'action_label' => 'Browse plans',
    ]);

    return;
}

foreach ($lines as $line) {
    include $view->path('pages.partials.cart-line');
}
