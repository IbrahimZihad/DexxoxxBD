<?php
declare(strict_types=1);

return [
    [
        'name' => 'Starter Pass',
        'description' => 'For casual viewers who only need one subscription at a time.',
        'price' => 199.00,
        'duration_days' => 30,
        'features' => ['1 active redemption', 'Email support', 'Standard checkout'],
        'badge' => null,
        'sort_order' => 1,
    ],
    [
        'name' => 'Plus Pass',
        'description' => 'Our most popular pass for households that rotate services every month.',
        'price' => 499.00,
        'duration_days' => 30,
        'features' => ['Up to 5 active redemptions', 'Priority chat and email support', 'Early access to new products', '5% cashback credit'],
        'badge' => 'Most Popular',
        'sort_order' => 2,
    ],
    [
        'name' => 'Pro Pass — Annual',
        'description' => 'Best value for power users who subscribe to everything, all year.',
        'price' => 4999.00,
        'duration_days' => 365,
        'features' => ['Unlimited redemptions', 'Dedicated support line', 'Earliest access and beta products', '10% cashback credit', 'Two months free against monthly'],
        'badge' => 'Best Value',
        'sort_order' => 3,
    ],
];
