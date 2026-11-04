<?php
declare(strict_types=1);

/**
 * Editable site settings. Payment numbers below are placeholders — replace them
 * from Admin → Settings before going live; they are the values customers send money to.
 */
return [
    ['key' => 'bkash_number', 'value' => '01700000000', 'type' => 'string', 'group' => 'payments', 'label' => 'bKash number', 'public' => 1],
    ['key' => 'bkash_kind', 'value' => 'Merchant', 'type' => 'string', 'group' => 'payments', 'label' => 'bKash account type', 'public' => 1],
    ['key' => 'nagad_number', 'value' => '01800000000', 'type' => 'string', 'group' => 'payments', 'label' => 'Nagad number', 'public' => 1],
    ['key' => 'nagad_kind', 'value' => 'Merchant', 'type' => 'string', 'group' => 'payments', 'label' => 'Nagad account type', 'public' => 1],
    ['key' => 'rocket_number', 'value' => '01900000000', 'type' => 'string', 'group' => 'payments', 'label' => 'Rocket number', 'public' => 1],
    ['key' => 'rocket_kind', 'value' => 'Agent', 'type' => 'string', 'group' => 'payments', 'label' => 'Rocket account type', 'public' => 1],
    ['key' => 'bank_number', 'value' => '0000-0000000000', 'type' => 'string', 'group' => 'payments', 'label' => 'Bank account number', 'public' => 1],
    ['key' => 'bank_name', 'value' => 'Dutch-Bangla Bank, Dhanmondi branch', 'type' => 'string', 'group' => 'payments', 'label' => 'Bank and branch', 'public' => 1],
    ['key' => 'bank_account', 'value' => 'DexxoxxBD Trading', 'type' => 'string', 'group' => 'payments', 'label' => 'Account title', 'public' => 1],
    ['key' => 'manual_payment_instructions', 'value' => 'Send the exact order total, then submit the transaction ID with the number you paid from. We verify within 30 minutes during business hours.', 'type' => 'string', 'group' => 'payments', 'label' => 'Manual payment instructions', 'public' => 1],

    ['key' => 'support_phone', 'value' => '01700000000', 'type' => 'string', 'group' => 'contact', 'label' => 'Support phone', 'public' => 1],
    ['key' => 'support_email', 'value' => 'support@dexxoxxbd.test', 'type' => 'string', 'group' => 'contact', 'label' => 'Support email', 'public' => 1],
    ['key' => 'support_hours', 'value' => 'Every day, 10:00 to 22:00 (GMT+6)', 'type' => 'string', 'group' => 'contact', 'label' => 'Support hours', 'public' => 1],

    ['key' => 'site_currency', 'value' => 'BDT', 'type' => 'string', 'group' => 'general', 'label' => 'Currency code', 'public' => 1],
    ['key' => 'site_tagline', 'value' => 'Digital subscriptions, delivered in minutes.', 'type' => 'string', 'group' => 'general', 'label' => 'Homepage tagline', 'public' => 1],
    ['key' => 'announcement_bar', 'value' => '', 'type' => 'string', 'group' => 'general', 'label' => 'Announcement banner (leave empty to hide)', 'public' => 1],
    ['key' => 'min_order_amount', 'value' => '99', 'type' => 'number', 'group' => 'general', 'label' => 'Minimum order total', 'public' => 0],
    ['key' => 'auto_expire_passes', 'value' => '1', 'type' => 'bool', 'group' => 'general', 'label' => 'Mark overdue passes expired automatically', 'public' => 0],
];
