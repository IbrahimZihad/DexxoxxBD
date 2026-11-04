<?php

/**
 * Error page copy. Nothing here leaks stack detail - that stays in the logs.
 */

return [
    '404' => [
        'title' => 'That page is not on the shelf',
        'text' => 'The link may be from an older listing. Every plan we currently stock is one click away.',
        'cta' => 'Browse plans',
        'home' => 'Back to home',
    ],
    '403' => [
        'title' => 'You do not have access to this',
        'text' => 'If you expected to see it, sign in with the account that owns the order.',
        'cta' => 'Sign in',
    ],
    '400' => [
        'title' => 'We could not read that request',
        'text' => 'The form or link looked malformed. Try again from the page you came from.',
        'cta' => 'Back to home',
    ],
    '429' => [
        'title' => 'Too many attempts',
        'text' => 'Wait a minute and try again. This protects accounts from automated guessing.',
        'cta' => 'Back to home',
    ],
    '500' => [
        'title' => 'Something broke on our side',
        'text' => 'The incident is written to the server log. Reload once; if it persists, contact support '
            . 'and mention what you were doing.',
        'cta' => 'Contact support',
        'home' => 'Back to home',
    ],
    '503' => [
        'title' => 'Briefly unavailable',
        'text' => 'We are mid-deploy or a dependency is down. Orders already placed are safe.',
        'cta' => 'Try again',
    ],
    'debug_note' => 'Debug view (APP_DEBUG is on): this is exactly what was written to the error log.',
    'allowed' => 'Allowed methods',
    'actions' => 'Where to go next',
];
