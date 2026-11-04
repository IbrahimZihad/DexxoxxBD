<?php

/**
 * Copy for the fifteen homepage sections. Section order and which shelves are
 * populated come from the database; the headings live here so a translation
 * changes the whole page without a CMS edit.
 */

return [
    'announcement' => [
        'default' => 'Automatic key delivery on eligible plans - pay with bKash, Nagad, card or bank.',
    ],
    'hero' => [
        'title' => 'Every subscription your work and weekend need',
        'subtitle' => 'AI, study, streaming, security and developer tools - verified resellers, '
            . 'delivered in minutes, supported by people who answer.',
        'primary_cta' => 'Browse plans',
        'secondary_cta' => 'How delivery works',
        'point_1' => 'Keys issued the moment payment clears',
        'point_2' => 'Monthly, annual and lifetime terms in one cart',
        'point_3' => 'Refund window on unused credentials',
    ],
    'stats' => [
        'title' => 'Live marketplace',
        'products' => 'Plans on the shelf',
        'plans' => 'Term options',
        'brands' => 'Brands resold',
        'categories' => 'Categories',
        'delivered' => 'Orders delivered',
        'reviews' => 'Verified reviews',
    ],
    'categories' => [
        'eyebrow' => 'Shelves',
        'title' => 'Start from a category',
        'subtitle' => 'Twelve shelves, each with the sub-categories our customers actually buy.',
        'cta' => 'See every category',
    ],
    'featured' => [
        'eyebrow' => 'Chosen by our team',
        'title' => 'Featured plans this week',
        'subtitle' => 'Hand-picked terms with the fastest delivery and the clearest warranty.',
    ],
    'bestsellers' => [
        'eyebrow' => 'What Dhaka renews',
        'title' => 'Bestsellers',
        'subtitle' => 'Ranked by completed orders, not by what we want to move.',
    ],
    'new_arrivals' => [
        'eyebrow' => 'Just listed',
        'title' => 'New arrivals',
        'subtitle' => 'New shelves and new terms, published this season.',
    ],
    'deals' => [
        'eyebrow' => 'Annual terms',
        'title' => 'Offers worth locking in',
        'subtitle' => 'Compare the list price against what you actually pay for a longer term.',
        'cta' => 'See all offers',
        'column_product' => 'Plan',
        'column_term' => 'Term',
        'column_list' => 'List',
        'column_price' => 'You pay',
        'column_saving' => 'Saving',
    ],
    'how_it_works' => [
        'eyebrow' => 'Four steps',
        'title' => 'How a DexxoxxBD order works',
        'subtitle' => 'No ticket numbers, no waiting on a stranger in a chat group.',
        'step_1_title' => 'Pick a term',
        'step_1_text' => 'Choose monthly, annual or lifetime. Prices update before you commit.',
        'step_2_title' => 'Pay your way',
        'step_2_text' => 'bKash, Nagad, card, net banking or a bank deposit you can evidence.',
        'step_3_title' => 'We verify',
        'step_3_text' => 'The gateway confirmation and the amount are matched server-side, never by a browser message.',
        'step_4_title' => 'Receive and activate',
        'step_4_text' => 'Keys and codes land in your dashboard and inbox, with activation steps beside them.',
    ],
    'brands' => [
        'eyebrow' => 'Resellers',
        'title' => 'Brands on the shelf',
        'subtitle' => 'Every brand here is stocked through an authorised channel and warrantied by us.',
        'cta' => 'Browse by brand',
    ],
    'promos' => [
        'eyebrow' => 'Current campaigns',
        'title' => 'Promotions running now',
    ],
    'bargain' => [
        'eyebrow' => 'Sharpest discounts',
        'title' => 'Cheapest term per plan',
        'subtitle' => 'The biggest percentage off, measured against each plan list price.',
        'cta' => 'Open the plan',
    ],
    'testimonials' => [
        'eyebrow' => 'Customer voices',
        'title' => 'What buyers tell us',
        'subtitle' => 'Reviews are published only after an approved purchase.',
    ],
    'faqs' => [
        'eyebrow' => 'Answers',
        'title' => 'Questions we get on every shift',
        'subtitle' => 'Still unsure? Support replies within two hours, every day.',
        'cta' => 'Read all FAQs',
    ],
    'support_cta' => [
        'title' => 'Not sure which term fits?',
        'subtitle' => 'Tell us the team size and the software you already run - we will map the plans '
            . 'and say plainly when a cheaper one covers it.',
        'primary_cta' => 'Talk to support',
        'secondary_cta' => 'Compare plans',
    ],
    'newsletter' => [
        'title' => 'Price drops and new shelves',
        'subtitle' => 'One short email when a plan you follow changes price. No daily campaigns.',
        'placeholder' => 'you@example.com',
        'cta' => 'Subscribe',
        'consent' => 'We keep your address for this newsletter only.',
        'success' => 'You are on the list. Check your inbox to confirm.',
        'error' => 'That address does not look right.',
    ],
    'trust' => [
        'title' => 'Why the order is safe',
        'item_1_title' => 'Server-side price checks',
        'item_1_text' => 'The amount you pay is recalculated from the database at checkout.',
        'item_2_title' => 'Encrypted credentials',
        'item_2_text' => 'Licence keys are stored AES-256-GCM encrypted, with the key outside MySQL.',
        'item_3_title' => 'Ownership-gated delivery',
        'item_3_text' => 'Only the account that owns an order can read its keys.',
        'item_4_title' => 'Audited every change',
        'item_4_text' => 'Staff actions on orders and inventory write to an immutable audit trail.',
    ],
];
